<?php

namespace Modules\Student\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Student\App\Repositories\StudentRepository;
use Modules\Student\App\Repositories\Contracts\StudentRepositoryInterface;

class StudentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            StudentRepositoryInterface::class,
            StudentRepository::class
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(
            base_path(
                'Modules/Student/routes/api.php'
            )
        );

        $this->loadMigrationsFrom(
            base_path(
                'Modules/Student/Database/migrations'
            )
        );
    }
}
