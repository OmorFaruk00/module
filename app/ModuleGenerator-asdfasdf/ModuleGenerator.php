<?php

namespace App\ModuleGenerator;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModuleGenerator
{
    public function __construct(
        protected FieldParser $parser
    ) {}

    public function generate(
        string $name,
        ?string $fields = null,
        bool $force = false
    ): void {
        $name = Str::studly($name);

        $parsedFields = $this->parser->parse($fields);

        $basePath = base_path("Modules/{$name}");

        if (File::exists($basePath) && !$force) {
            throw new \RuntimeException(
                "Module {$name} already exists."
            );
        }

        $directories = [
            'App/Http/Controllers',
            'App/Http/Requests',
            'App/Http/Resources',
            'App/Models',
            'App/Repositories/Contracts',
            'App/Repositories',
            'App/Services',
            'Database/factories',
            'Database/migrations',
            'Providers',
            'Tests/Feature',
            'Tests/Unit',
            'routes',
        ];

        foreach ($directories as $directory) {
            File::ensureDirectoryExists(
                "{$basePath}/{$directory}"
            );
        }

        $this->generateModel(
            $name,
            $basePath,
            $parsedFields
        );

        $this->generateMigration(
            $name,
            $basePath,
            $parsedFields
        );

        $this->generateRepository(
            $name,
            $basePath
        );

        $this->generateService(
            $name,
            $basePath
        );

        $this->generateProvider(
            $name,
            $basePath
        );

        $this->generateRoutes(
            $name,
            $basePath
        );
    }

    protected function generateModel(
        string $name,
        string $basePath,
        array $fields
    ): void {
        $fillable = $this->parser->fillable($fields);
        $casts = $this->parser->casts($fields);

        $content = <<<PHP
<?php

namespace Modules\\{$name}\\App\\Models;

use Illuminate\\Database\\Eloquent\\Model;

class {$name} extends Model
{
    protected \$fillable = [
{$fillable}
    ];

    protected \$casts = [
{$casts}
    ];
}

PHP;

        File::put(
            "{$basePath}/App/Models/{$name}.php",
            $content
        );
    }

    protected function generateMigration(
        string $name,
        string $basePath,
        array $fields
    ): void {
        $table = Str::snake(Str::pluralStudly($name));

        $columns = collect($fields)
            ->map(fn ($field) => '            ' . $this->parser->migration($field))
            ->implode("\n");

        $timestamp = now()->format('Y_m_d_His');

        $content = <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
            \$table->id();
{$columns}
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};

PHP;

        File::put(
            "{$basePath}/Database/migrations/{$timestamp}_create_{$table}_table.php",
            $content
        );
    }

    protected function generateRepository(
        string $name,
        string $basePath
    ): void {
        $interface = <<<PHP
<?php

namespace Modules\\{$name}\\App\\Repositories\\Contracts;

use Modules\\{$name}\\App\\Models\\{$name};

interface {$name}RepositoryInterface
{
    public function all();

    public function find(int \$id): {$name};

    public function create(array \$data): {$name};

    public function update(int \$id, array \$data): {$name};

    public function delete(int \$id): bool;
}

PHP;

        File::put(
            "{$basePath}/App/Repositories/Contracts/{$name}RepositoryInterface.php",
            $interface
        );

        $repository = <<<PHP
<?php

namespace Modules\\{$name}\\App\\Repositories;

use Modules\\{$name}\\App\\Models\\{$name};
use Modules\\{$name}\\App\\Repositories\\Contracts\\{$name}RepositoryInterface;

class {$name}Repository implements {$name}RepositoryInterface
{
    public function __construct(
        protected {$name} \$model
    ) {}

    public function all()
    {
        return \$this->model
            ->latest()
            ->get();
    }

    public function find(int \$id): {$name}
    {
        return \$this->model->findOrFail(\$id);
    }

    public function create(array \$data): {$name}
    {
        return \$this->model->create(\$data);
    }

    public function update(int \$id, array \$data): {$name}
    {
        \$model = \$this->find(\$id);

        \$model->update(\$data);

        return \$model->fresh();
    }

    public function delete(int \$id): bool
    {
        return (bool) \$this->find(\$id)->delete();
    }
}

PHP;

        File::put(
            "{$basePath}/App/Repositories/{$name}Repository.php",
            $repository
        );
    }

    protected function generateService(
        string $name,
        string $basePath
    ): void {
        $content = <<<PHP
<?php

namespace Modules\\{$name}\\App\\Services;

use Modules\\{$name}\\App\\Repositories\\Contracts\\{$name}RepositoryInterface;

class {$name}Service
{
    public function __construct(
        protected {$name}RepositoryInterface \$repository
    ) {}

    public function all()
    {
        return \$this->repository->all();
    }

    public function find(int \$id)
    {
        return \$this->repository->find(\$id);
    }

    public function create(array \$data)
    {
        return \$this->repository->create(\$data);
    }

    public function update(int \$id, array \$data)
    {
        return \$this->repository->update(\$id, \$data);
    }

    public function delete(int \$id)
    {
        return \$this->repository->delete(\$id);
    }
}

PHP;

        File::put(
            "{$basePath}/App/Services/{$name}Service.php",
            $content
        );
    }

    protected function generateProvider(
        string $name,
        string $basePath
    ): void {
        $content = <<<PHP
<?php

namespace Modules\\{$name}\\Providers;

use Illuminate\\Support\\ServiceProvider;
use Modules\\{$name}\\App\\Repositories\\{$name}Repository;
use Modules\\{$name}\\App\\Repositories\\Contracts\\{$name}RepositoryInterface;

class {$name}ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        \$this->app->bind(
            {$name}RepositoryInterface::class,
            {$name}Repository::class
        );
    }

    public function boot(): void
    {
        //
    }
}

PHP;

        File::put(
            "{$basePath}/Providers/{$name}ServiceProvider.php",
            $content
        );
    }

    protected function generateRoutes(
        string $name,
        string $basePath
    ): void {
        $routeName = Str::kebab(Str::pluralStudly($name));

        $content = <<<PHP
<?php

use Illuminate\\Support\\Facades\\Route;

Route::prefix('{$routeName}')
    ->group(function () {

    // Controller routes will be generated here.

});

PHP;

        File::put(
            "{$basePath}/routes/api.php",
            $content
        );
    }
}
