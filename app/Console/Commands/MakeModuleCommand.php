<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\ModuleGenerator\ModuleGenerator;

class MakeModuleCommand extends Command
{
    protected $signature = 'make:module
        {name : Module name}
        {--fields= : Module fields}
        {--force : Overwrite existing module}';

    protected $description =
        'Generate a complete modular monolith module';

    public function handle(ModuleGenerator $generator): int
    {
        $name = $this->argument('name');

        try {
            $generator->generate(
                $name,
                $this->option('fields'),
                (bool) $this->option('force')
            );

            $this->components->info(
                "Module {$name} generated successfully."
            );

            return self::SUCCESS;

        } catch (\Throwable $e) {

            $this->components->error(
                $e->getMessage()
            );

            return self::FAILURE;
        }
    }
}
