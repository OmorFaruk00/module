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
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'department_id' => fake()->numberBetween(1, 100),
            'is_active' => fake()->boolean()
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
