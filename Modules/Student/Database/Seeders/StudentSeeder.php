<?php

namespace Modules\Student\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Student\App\Models\Student;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        Student::factory()->count(10)->create();
    }
}