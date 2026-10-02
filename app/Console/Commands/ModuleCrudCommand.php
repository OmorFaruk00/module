<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class ModuleCrudCommand extends Command
{
    protected $signature = 'module:crud
        {name : Module name}
        {--fields= : Fields for creating CRUD}
        {--add-fields= : Add new fields}
        {--edit-fields= : Edit existing fields}
        {--seed-count=10 : Seeder record count}
        {--force : Overwrite existing files}';

    protected $description = 'Generate a complete modular CRUD structure';

    protected string $module;
    protected string $moduleLower;
    protected string $table;
    protected string $namespace;
    protected string $routePrefix;

    /** Stubs that depend on fields (regenerated on add/edit) */
    protected array $fieldStubs = [
        'model.stub',
        'store-request.stub',
        'update-request.stub',
        'resource.stub',
        'factory.stub',
    ];

    public function handle(): int
    {
        $this->initializeModule();

        $fields     = $this->option('fields');
        $addFields  = $this->option('add-fields');
        $editFields = $this->option('edit-fields');

        try {
            if ($addFields) {
                $parsed = $this->parseFields($addFields);
                if (empty($parsed)) {
                    $this->error('No fields provided for adding.');
                    return self::FAILURE;
                }
                return $this->addFields($parsed);
            }

            if ($editFields) {
                $parsed = $this->parseFields($editFields);
                if (empty($parsed)) {
                    $this->error('No fields provided for editing.');
                    return self::FAILURE;
                }
                return $this->editFields($parsed);
            }

            if (!$fields) {
                $this->error('No fields provided.');
                $this->line('');
                $this->line('Example:');
                $this->line('php artisan module:crud Student --fields="name:string,email:string:unique,phone:string:nullable"');
                return self::FAILURE;
            }

            return $this->createCrud($this->parseFields($fields));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    /* ---------------------------------------------------------
     |  CREATE
     * --------------------------------------------------------*/
    protected function createCrud(array $fields): int
    {
        if ($this->metaExists() && !$this->option('force')) {
            $this->error("Module {$this->module} already exists. Use --add-fields / --edit-fields or --force.");
            return self::FAILURE;
        }

        $this->info("Creating CRUD for {$this->module}...");

        $this->createDirectories();
        $this->saveFields($fields);
        $this->generateFiles($fields);
        $this->generateSeeder();
        $this->registerSeeder();

        $this->info('Done.');
        return self::SUCCESS;
    }

    /* ---------------------------------------------------------
     |  ADD FIELDS
     * --------------------------------------------------------*/
    protected function addFields(array $newFields): int
    {
        $existing = $this->loadFields();
        $existingNames = array_column($existing, 'name');

        foreach ($newFields as $field) {
            if (in_array($field['name'], $existingNames, true)) {
                throw new RuntimeException(
                    "Field '{$field['name']}' already exists. Use --edit-fields to change it."
                );
            }
        }

        $this->info("Adding fields to {$this->module}...");

        // 1. Migration
        $up = collect($newFields)
            ->map(fn ($f) => '            ' . $this->buildColumnLine($f))
            ->implode("\n");

        $dropCols = collect($newFields)
            ->map(fn ($f) => "'{$f['name']}'")
            ->implode(', ');

        $this->writeMigration(
            'add_' . implode('_', array_column($newFields, 'name')) . "_to_{$this->table}_table",
            "        Schema::table('{$this->table}', function (Blueprint \$table) {\n{$up}\n        });",
            "        Schema::table('{$this->table}', function (Blueprint \$table) {\n            \$table->dropColumn([{$dropCols}]);\n        });"
        );

        // 2. Metadata
        $all = array_merge($existing, $newFields);
        $this->saveFields($all);

        // 3. Regenerate model / requests / resource / factory
        $this->generateFiles($all, $this->fieldStubs, true);

        // 4. Make sure seeder exists + registered
        $this->generateSeeder();
        $this->registerSeeder();

        foreach ($newFields as $f) {
            $this->line(" + {$f['name']}:{$f['type']}");
        }

        return self::SUCCESS;
    }

    /* ---------------------------------------------------------
     |  EDIT FIELDS
     * --------------------------------------------------------*/
    protected function editFields(array $changedFields): int
    {
        $existing = $this->loadFields();
        $byName = collect($existing)->keyBy('name')->all();

        $up = [];
        $down = [];

        foreach ($changedFields as $field) {
            if (!isset($byName[$field['name']])) {
                throw new RuntimeException(
                    "Field '{$field['name']}' not found. Use --add-fields to add it."
                );
            }

            $up[]   = '            ' . $this->buildColumnLine($field, true);
            $down[] = '            ' . $this->buildColumnLine($byName[$field['name']], true);

            $byName[$field['name']] = $field;
        }

        $this->info("Editing fields in {$this->module}...");

        $this->writeMigration(
            'modify_' . implode('_', array_column($changedFields, 'name')) . "_in_{$this->table}_table",
            "        Schema::table('{$this->table}', function (Blueprint \$table) {\n" . implode("\n", $up) . "\n        });",
            "        Schema::table('{$this->table}', function (Blueprint \$table) {\n" . implode("\n", $down) . "\n        });"
        );

        $all = array_values($byName);
        $this->saveFields($all);
        $this->generateFiles($all, $this->fieldStubs, true);

        foreach ($changedFields as $f) {
            $mods = $f['modifiers'] ? ':' . implode(':', $f['modifiers']) : '';
            $this->line(" ~ {$f['name']}:{$f['type']}{$mods}");
        }

        return self::SUCCESS;
    }

    /* ---------------------------------------------------------
     |  METADATA (fields.json)
     * --------------------------------------------------------*/
    protected function metaPath(): string
    {
        return base_path("Modules/{$this->module}/fields.json");
    }

    protected function metaExists(): bool
    {
        return File::exists($this->metaPath());
    }

    protected function loadFields(): array
    {
        if (!$this->metaExists()) {
            throw new RuntimeException(
                "Module {$this->module} not found. Create it first with --fields."
            );
        }

        return json_decode(File::get($this->metaPath()), true) ?? [];
    }

    protected function saveFields(array $fields): void
    {
        File::ensureDirectoryExists(dirname($this->metaPath()));
        File::put(
            $this->metaPath(),
            json_encode(array_values($fields), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /* ---------------------------------------------------------
     |  INIT / PARSE
     * --------------------------------------------------------*/
    protected function initializeModule(): void
    {
        $name = trim($this->argument('name'));
        $name = str_replace(['/', '\\', '-', '_'], ' ', $name);

        $this->module      = Str::studly($name);
        $this->moduleLower = Str::lower($this->module);
        $this->table       = Str::snake(Str::pluralStudly($this->module));
        $this->namespace   = "Modules\\{$this->module}";
        $this->routePrefix = Str::kebab(Str::pluralStudly($this->module));
    }

    protected function parseFields(?string $fieldString): array
    {
        if (!$fieldString) {
            return [];
        }

        $fields = [];

        foreach (explode(',', $fieldString) as $field) {
            $field = trim($field);
            if (!$field) {
                continue;
            }

            $parts = explode(':', $field);

            $fields[] = [
                'name'      => trim($parts[0]),
                'type'      => isset($parts[1]) ? trim($parts[1]) : 'string',
                'modifiers' => array_map('trim', array_slice($parts, 2)),
            ];
        }

        return $fields;
    }

    /* ---------------------------------------------------------
     |  DIRECTORIES
     * --------------------------------------------------------*/
    protected function createDirectories(): void
    {
        $directories = [
            "Modules/{$this->module}/App/Http/Controllers",
            "Modules/{$this->module}/App/Http/Requests",
            "Modules/{$this->module}/App/Http/Resources",
            "Modules/{$this->module}/App/Models",
            "Modules/{$this->module}/App/Repositories/Contracts",
            "Modules/{$this->module}/App/Services",
            "Modules/{$this->module}/Database/migrations",
            "Modules/{$this->module}/Database/Factories",
            "Modules/{$this->module}/Database/Seeders",
            "Modules/{$this->module}/Providers",
            "Modules/{$this->module}/Tests/Feature",
            "Modules/{$this->module}/Tests/Unit",
            "Modules/{$this->module}/routes",
        ];

        foreach ($directories as $directory) {
            File::ensureDirectoryExists(base_path($directory));
        }
    }

    /* ---------------------------------------------------------
     |  FILE GENERATION
     * --------------------------------------------------------*/
    protected function generateFiles(array $fields, ?array $only = null, bool $force = false): void
    {
        $replacements = $this->buildReplacements($fields);

        $files = [
            'controller.stub'           => "App/Http/Controllers/{$this->module}Controller.php",
            'store-request.stub'        => "App/Http/Requests/Store{$this->module}Request.php",
            'update-request.stub'       => "App/Http/Requests/Update{$this->module}Request.php",
            'resource.stub'             => "App/Http/Resources/{$this->module}Resource.php",
            'model.stub'                => "App/Models/{$this->module}.php",
            'repository-interface.stub' => "App/Repositories/Contracts/{$this->module}RepositoryInterface.php",
            'repository.stub'           => "App/Repositories/{$this->module}Repository.php",
            'service.stub'              => "App/Services/{$this->module}Service.php",
            'provider.stub'             => "Providers/{$this->module}ServiceProvider.php",
            'routes.stub'               => "routes/api.php",
            'migration.stub'            => "Database/migrations/" . date('Y_m_d_His') . "_create_{$this->table}_table.php",
            'factory.stub'              => "Database/Factories/{$this->module}Factory.php",
            'feature-test.stub'         => "Tests/Feature/{$this->module}ControllerTest.php",
            'unit-test.stub'            => "Tests/Unit/{$this->module}ServiceTest.php",
        ];

        if ($only !== null) {
            $files = array_intersect_key($files, array_flip($only));
        }

        foreach ($files as $stub => $destination) {
            $source = resource_path("stubs/module-crud/{$stub}");

            if (!File::exists($source)) {
                throw new RuntimeException("Stub not found: {$source}");
            }

            $content = File::get($source);

            foreach ($replacements as $key => $value) {
                $content = str_replace("{{ {$key} }}", $value, $content);
            }

            $path = base_path("Modules/{$this->module}/{$destination}");

            if (File::exists($path) && !$force && !$this->option('force')) {
                $this->warn("Skipped existing file: {$path}");
                continue;
            }

            File::put($path, $content);
            $this->line("<info>Created:</info> {$path}");
        }
    }

    protected function writeMigration(string $name, string $up, string $down): void
    {
        $file = "Modules/{$this->module}/Database/migrations/" . date('Y_m_d_His') . "_{$name}.php";

        $content = <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
{$up}
    }

    public function down(): void
    {
{$down}
    }
};
PHP;

        File::ensureDirectoryExists(dirname(base_path($file)));
        File::put(base_path($file), $content);
        $this->line("<info>Created:</info> " . base_path($file));
    }

    /* ---------------------------------------------------------
     |  SEEDER
     * --------------------------------------------------------*/
    protected function generateSeeder(): void
    {
        $path = base_path("Modules/{$this->module}/Database/Seeders/{$this->module}Seeder.php");

        if (File::exists($path)) {
            return;
        }

        $count = (int) $this->option('seed-count');

        $content = <<<PHP
<?php

namespace {$this->namespace}\Database\Seeders;

use Illuminate\Database\Seeder;
use {$this->namespace}\App\Models\\{$this->module};

class {$this->module}Seeder extends Seeder
{
    public function run(): void
    {
        {$this->module}::factory()->count({$count})->create();
    }
}
PHP;

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $content);
        $this->line("<info>Created:</info> {$path}");
    }

   protected function registerSeeder(): void
{
    $path = database_path('seeders/DatabaseSeeder.php');

    if (!File::exists($path)) {
        $this->warn('DatabaseSeeder.php not found, register seeder manually.');
        return;
    }

    $class   = "\\{$this->namespace}\\Database\\Seeders\\{$this->module}Seeder::class";
    $content = File::get($path);

    // already registered
    if (str_contains($content, "{$this->module}Seeder::class")) {
        return;
    }

    $call  = "        \$this->call({$class});\n";
    $count = 0;

    $updated = preg_replace_callback(
        '/(public\s+function\s+run\s*\(\s*\)\s*(?::\s*void)?\s*\{[ \t]*\n?)/',
        fn ($m) => $m[1] . $call,
        $content,
        1,
        $count
    );

    if ($count === 0 || $updated === null) {
        $this->warn('Could not auto-register seeder, add manually: $this->call(' . $class . ');');
        return;
    }

    File::put($path, $updated);
    $this->line('<info>Registered seeder in DatabaseSeeder</info>');
}

    /* ---------------------------------------------------------
     |  REPLACEMENTS
     * --------------------------------------------------------*/
 protected function buildReplacements(array $fields): array
{
    $fillable = collect($fields)
        ->pluck('name')
        ->map(fn ($f) => "'{$f}'")
        ->implode(",\n        ");

    $rules = $this->buildValidationRules($fields);

    $resourceFields = collect($fields)
        ->map(fn ($f) => "'{$f['name']}' => \$this->{$f['name']}")
        ->implode(",\n            ");

    $factoryFields = collect($fields)
        ->map(fn ($f) => "'{$f['name']}' => " . $this->fakerFor($f))
        ->implode(",\n            ");

    return [
        'module'             => $this->module,
        'module_lower'       => $this->moduleLower,
        'namespace'          => $this->namespace,
        'table'              => $this->table,
        'route_prefix'       => $this->routePrefix,
        'fillable'           => $fillable,
        'migration_fields'   => $this->buildMigrationFields($fields),
        'store_rules'        => $rules['store'],
        'update_rules'       => $rules['update'],
        'resource_fields'    => $resourceFields,
        'factory_fields'     => $factoryFields,
        'factory_definition' => $factoryFields . ',',   // <-- notun
    ];
}

    protected function fakerFor(array $field): string
    {
        $name   = $field['name'];
        $type   = $field['type'];
        $unique = in_array('unique', $field['modifiers'], true) ? 'unique()->' : '';

        return match (true) {
            $name === 'email' || $type === 'email'  => "fake()->{$unique}safeEmail()",
            str_contains($name, 'phone')            => "fake()->{$unique}phoneNumber()",
            $name === 'name'                        => "fake()->{$unique}name()",
            $name === 'slug'                        => "fake()->{$unique}slug()",
            str_contains($name, 'address')          => 'fake()->address()',
            $type === 'text'                        => 'fake()->paragraph()',
            in_array($type, ['integer', 'int', 'bigInteger', 'unsignedBigInteger'], true)
                                                    => 'fake()->numberBetween(1, 100)',
            in_array($type, ['boolean', 'bool'], true) => 'fake()->boolean()',
            $type === 'date'                        => "fake()->date()",
            $type === 'datetime'                    => "fake()->dateTime()->format('Y-m-d H:i:s')",
            $type === 'decimal'                     => 'fake()->randomFloat(2, 1, 1000)',
            $type === 'json'                        => '[]',
            default                                 => "fake()->{$unique}words(3, true)",
        };
    }

    /* ---------------------------------------------------------
     |  MIGRATION COLUMN
     * --------------------------------------------------------*/
    protected function buildColumnLine(array $field, bool $change = false): string
    {
        $name = $field['name'];

        $line = match ($field['type']) {
            'text'                    => "\$table->text('{$name}')",
            'integer', 'int'          => "\$table->integer('{$name}')",
            'bigInteger'              => "\$table->bigInteger('{$name}')",
            'unsignedBigInteger'      => "\$table->unsignedBigInteger('{$name}')",
            'boolean', 'bool'         => "\$table->boolean('{$name}')",
            'date'                    => "\$table->date('{$name}')",
            'datetime'                => "\$table->dateTime('{$name}')",
            'decimal'                 => "\$table->decimal('{$name}', 10, 2)",
            'json'                    => "\$table->json('{$name}')",
            default                   => "\$table->string('{$name}')", // string, email, etc.
        };

        foreach ($field['modifiers'] as $modifier) {
            if ($modifier === 'nullable') {
                $line .= '->nullable()';
            } elseif ($modifier === 'unique') {
                $line .= '->unique()';
            } elseif ($modifier === 'index') {
                $line .= '->index()';
            } elseif (str_starts_with($modifier, 'default=')) {
                $value = substr($modifier, 8);

                if ($value === 'null') {
                    $line .= '->default(null)';
                } elseif (is_numeric($value)) {
                    $line .= "->default({$value})";
                } else {
                    $line .= "->default('{$value}')";
                }
            }
        }

        // unique/index on ->change() can fail if index already exists
        if ($change) {
            $line = str_replace(['->unique()', '->index()'], '', $line);
            $line .= '->change()';
        }

        return $line . ';';
    }

    protected function buildMigrationFields(array $fields): string
    {
        return collect($fields)
            ->map(fn ($f) => '            ' . $this->buildColumnLine($f))
            ->implode("\n");
    }

    /* ---------------------------------------------------------
     |  VALIDATION RULES
     * --------------------------------------------------------*/
    protected function buildValidationRules(array $fields): array
    {
        $store = [];
        $update = [];

        foreach ($fields as $field) {
            $name = $field['name'];
            $type = $field['type'];

            $rules = [];

            if ($type === 'string')                                          { $rules = ['string', 'max:255']; }
            if (in_array($type, ['integer', 'int', 'bigInteger', 'unsignedBigInteger'])) { $rules[] = 'integer'; }
            if (in_array($type, ['boolean', 'bool']))                        { $rules[] = 'boolean'; }
            if (in_array($type, ['date', 'datetime']))                       { $rules[] = 'date'; }
            if ($type === 'email')                                           { $rules = ['email', 'max:255']; }
            if ($type === 'json')                                            { $rules[] = 'array'; }
            if ($type === 'decimal')                                         { $rules[] = 'numeric'; }

            $first = in_array('nullable', $field['modifiers'], true) ? 'nullable' : 'required';

            $storeRules = array_merge([$first], $rules);
            if (in_array('unique', $field['modifiers'], true)) {
                $storeRules[] = "unique:{$this->table},{$name}";
            }

            $updateRules = array_merge([$first === 'required' ? 'sometimes' : 'nullable'], $rules);
            if (in_array('unique', $field['modifiers'], true)) {
                // route param assumed to be the id
                $updateRules[] = "unique:{$this->table},{$name},' . \$this->route('{$this->moduleLower}') . '";
            }

            $store[]  = "'{$name}' => '" . implode('|', $storeRules) . "',";
            $update[] = "'{$name}' => '" . implode('|', $updateRules) . "',";
        }

        return [
            'store'  => implode("\n        ", $store),
            'update' => implode("\n        ", $update),
        ];
    }
}
