<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class ModuleCrudCommand extends Command
{
    protected $signature = 'module:crud
                            {name : Module name e.g. Student}
                            {--fields= : Fields e.g. name:string,email:string,age:integer}
                            {--force : Overwrite existing files}';

    protected $description = 'Generate a complete modular CRUD structure';

    protected string $module;
    protected string $moduleLower;
    protected string $table;
    protected string $namespace;
    protected string $routePrefix;

    public function handle(): int
    {
        $this->initializeModule();

        $fields = $this->parseFields(
            $this->option('fields')
        );

        if (empty($fields)) {
            $this->error('No fields provided.');

            $this->line('');
            $this->line('Example:');

            $this->line(
                'php artisan module:crud Student --fields="name:string,email:string,phone:string"'
            );

            return self::FAILURE;
        }

        $this->info("Generating {$this->module} module...");

        $this->createDirectories();

        $this->generateFiles($fields);

        $this->newLine();

        $this->info("Module [{$this->module}] generated successfully.");

        $this->newLine();

        $this->line('Next steps:');

        $this->line('1. composer dump-autoload');
        $this->line('2. php artisan migrate');
        $this->line("3. php artisan test Modules/{$this->module}/Tests");

        return self::SUCCESS;
    }

    protected function initializeModule(): void
    {
        $name = trim($this->argument('name'));

        $name = str_replace(
            ['/', '\\', '-', '_'],
            ' ',
            $name
        );

        $this->module = Str::studly($name);

        $this->moduleLower = Str::lower($this->module);

        $this->table = Str::snake(
            Str::pluralStudly($this->module)
        );

        $this->namespace = "Modules\\{$this->module}";

        $this->routePrefix = Str::kebab(
            Str::pluralStudly($this->module)
        );
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

            $name = trim($parts[0]);
            $type = isset($parts[1])
                ? trim($parts[1])
                : 'string';

            $modifiers = array_slice($parts, 2);

            $fields[] = [
                'name' => $name,
                'type' => $type,
                'modifiers' => $modifiers,
            ];
        }

        return $fields;
    }

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
            "Modules/{$this->module}/Providers",
            "Modules/{$this->module}/Tests/Feature",
            "Modules/{$this->module}/Tests/Unit",
            "Modules/{$this->module}/routes",
        ];

        foreach ($directories as $directory) {
            File::ensureDirectoryExists(
                base_path($directory)
            );
        }
    }

    protected function generateFiles(array $fields): void
    {
        $replacements = $this->buildReplacements($fields);

        $files = [
            'controller.stub' => "App/Http/Controllers/{$this->module}Controller.php",

            'store-request.stub' => "App/Http/Requests/Store{$this->module}Request.php",

            'update-request.stub' => "App/Http/Requests/Update{$this->module}Request.php",

            'resource.stub' => "App/Http/Resources/{$this->module}Resource.php",

            'model.stub' => "App/Models/{$this->module}.php",

            'repository-interface.stub' =>
                "App/Repositories/Contracts/{$this->module}RepositoryInterface.php",

            'repository.stub' =>
                "App/Repositories/{$this->module}Repository.php",

            'service.stub' =>
                "App/Services/{$this->module}Service.php",

            'provider.stub' =>
                "Providers/{$this->module}ServiceProvider.php",

            'routes.stub' =>
                "routes/api.php",

            'migration.stub' =>
                "Database/migrations/" .
                date('Y_m_d_His') .
                "_create_{$this->table}_table.php",

            'factory.stub' =>
            "Database/Factories/{$this->module}Factory.php",

            'feature-test.stub' =>
                "Tests/Feature/{$this->module}ControllerTest.php",

            'unit-test.stub' =>
                "Tests/Unit/{$this->module}ServiceTest.php",
        ];

        foreach ($files as $stub => $destination) {

            $source = resource_path(
                "stubs/module-crud/{$stub}"
            );

            if (!File::exists($source)) {
                throw new RuntimeException(
                    "Stub not found: {$source}"
                );
            }

            $content = File::get($source);

            foreach ($replacements as $key => $value) {
                $content = str_replace(
                    "{{ {$key} }}",
                    $value,
                    $content
                );
            }

            $path = base_path(
                "Modules/{$this->module}/{$destination}"
            );

            if (File::exists($path) && !$this->option('force')) {
                $this->warn(
                    "Skipped existing file: {$path}"
                );

                continue;
            }

            File::put($path, $content);

            $this->line(
                "<info>Created:</info> {$path}"
            );
        }
    }

    protected function buildReplacements(array $fields): array
    {
        $fillable = collect($fields)
            ->pluck('name')
            ->map(fn ($field) => "'{$field}'")
            ->implode(",\n        ");

        $migrationFields = $this->buildMigrationFields($fields);

        $rules = $this->buildValidationRules($fields);

        $resourceFields = collect($fields)
            ->map(function ($field) {
                return "'{$field['name']}' => \$this->{$field['name']}";
            })
            ->implode(",\n            ");

        $factoryFields = collect($fields)
            ->map(function ($field) {
                return "'{$field['name']}' => \App\Models\User::factory()->make()->name";
            })
            ->implode(",\n            ");

        return [
            'module' => $this->module,
            'module_lower' => $this->moduleLower,
            'namespace' => $this->namespace,
            'table' => $this->table,
            'route_prefix' => $this->routePrefix,

            'fillable' => $fillable,

            'migration_fields' => $migrationFields,

            'store_rules' => $rules['store'],
            'update_rules' => $rules['update'],

            'resource_fields' => $resourceFields,

            'factory_fields' => $factoryFields,
        ];
    }

    protected function buildMigrationFields(array $fields): string
    {
        $output = [];

        foreach ($fields as $field) {

            $name = $field['name'];
            $type = $field['type'];
            $modifiers = $field['modifiers'];

            $line = match ($type) {
                'string' => "\$table->string('{$name}')",
                'text' => "\$table->text('{$name}')",
                'integer', 'int' => "\$table->integer('{$name}')",
                'bigInteger' => "\$table->bigInteger('{$name}')",
                'unsignedBigInteger' =>
                    "\$table->unsignedBigInteger('{$name}')",
                'boolean', 'bool' =>
                    "\$table->boolean('{$name}')",
                'date' =>
                    "\$table->date('{$name}')",
                'datetime' =>
                    "\$table->dateTime('{$name}')",
                'decimal' =>
                    "\$table->decimal('{$name}', 10, 2)",
                'json' =>
                    "\$table->json('{$name}')",
                default =>
                    "\$table->string('{$name}')",
            };

            foreach ($modifiers as $modifier) {

                if ($modifier === 'nullable') {
                    $line .= '->nullable()';
                }

                if ($modifier === 'unique') {
                    $line .= '->unique()';
                }

                if ($modifier === 'index') {
                    $line .= '->index()';
                }

                if ($modifier === 'default:null') {
                    $line .= '->default(null)';
                }

                if (str_starts_with($modifier, 'default=')) {
                    $value = substr(
                        $modifier,
                        strlen('default=')
                    );

                    if (is_numeric($value)) {
                        $line .= "->default({$value})";
                    } else {
                        $line .= "->default('{$value}')";
                    }
                }
            }

            $line .= ';';

            $output[] = "            {$line}";
        }

        return implode("\n", $output);
    }

    protected function buildValidationRules(array $fields): array
    {
        $store = [];
        $update = [];

        foreach ($fields as $field) {

            $name = $field['name'];
            $type = $field['type'];

            $rules = [];

            if ($type === 'string') {
                $rules[] = 'string';
                $rules[] = 'max:255';
            }

            if (in_array($type, ['integer', 'int', 'bigInteger'])) {
                $rules[] = 'integer';
            }

            if (in_array($type, ['boolean', 'bool'])) {
                $rules[] = 'boolean';
            }

            if ($type === 'date') {
                $rules[] = 'date';
            }

            if ($type === 'datetime') {
                $rules[] = 'date';
            }

            if ($type === 'email') {
                $rules[] = 'email';
            }

            if ($type === 'json') {
                $rules[] = 'array';
            }

            $isNullable = in_array(
                'nullable',
                $field['modifiers']
            );

            $storeRule = $isNullable
                ? 'nullable'
                : 'required';

            $rules = array_merge(
                [$storeRule],
                $rules
            );

            $store[] =
                "'{$name}' => '" .
                implode('|', $rules) .
                "',";

            $updateRules = $rules;

            $updateRules[0] = 'sometimes';

            $update[] =
                "'{$name}' => '" .
                implode('|', $updateRules) .
                "',";
        }

        return [
            'store' => implode("\n        ", $store),
            'update' => implode("\n        ", $update),
        ];
    }
}
