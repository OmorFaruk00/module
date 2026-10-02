<?php

namespace Modules\Student\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Student\App\Models\Student;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'department_id' => fake()->numberBetween(1, 100),
            'is_active' => fake()->boolean(),
            'age' => fake()->numberBetween(1, 100),
        ];
    }
}
