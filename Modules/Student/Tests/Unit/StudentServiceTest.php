<?php

namespace Modules\Student\Tests\Unit;

use Tests\TestCase;
use Mockery;
use Modules\Student\App\Services\StudentService;
use Modules\Student\App\Repositories\Contracts\StudentRepositoryInterface;

class StudentServiceTest extends TestCase
{
    public function test_can_create_student(): void
    {
        $repository = Mockery::mock(
            StudentRepositoryInterface::class
        );

        $data = [
            'name' => \App\Models\User::factory()->make()->name,
            'email' => \App\Models\User::factory()->make()->name,
            'phone' => \App\Models\User::factory()->make()->name,
            'department_id' => \App\Models\User::factory()->make()->name,
            'is_active' => \App\Models\User::factory()->make()->name
        ];

        $repository
            ->shouldReceive('create')
            ->once()
            ->with($data);

        $service = new StudentService(
            $repository
        );

        $service->store($data);

        $this->assertTrue(true);
    }
}
