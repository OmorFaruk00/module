<?php

namespace Modules\Student\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Student\App\Models\Student;

class StudentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_student(): void
    {
        $response = $this->postJson(
            '/api/students',
            [
                'name' => \App\Models\User::factory()->make()->name,
            'email' => \App\Models\User::factory()->make()->name,
            'phone' => \App\Models\User::factory()->make()->name,
            'department_id' => \App\Models\User::factory()->make()->name,
            'is_active' => \App\Models\User::factory()->make()->name
            ]
        );

        $response
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_can_get_student_list(): void
    {
        Student::factory()->count(3)->create();

        $response = $this->getJson(
            '/api/students'
        );

        $response->assertOk();
    }

    public function test_can_get_single_student(): void
    {
        $model = Student::factory()->create();

        $response = $this->getJson(
            "/api/students/{$model->id}"
        );

        $response->assertOk();
    }

    public function test_can_delete_student(): void
    {
        $model = Student::factory()->create();

        $response = $this->deleteJson(
            "/api/students/{$model->id}"
        );

        $response->assertOk();

        $this->assertDatabaseMissing(
            'students',
            [
                'id' => $model->id,
            ]
        );
    }
}
