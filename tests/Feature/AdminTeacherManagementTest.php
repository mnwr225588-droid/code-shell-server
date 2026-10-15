<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTeacherManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_teacher_with_name_email_and_password(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/teachers', [
                'name' => 'New Test Teacher',
                'email' => 'new-teacher@example.com',
                'password' => 'teacher-password-123',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', 'New Test Teacher')
            ->assertJsonPath('data.email', 'new-teacher@example.com')
            ->assertJsonMissingPath('data.password');

        $teacher = Teacher::where('email', 'new-teacher@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('teacher-password-123', $teacher->password));

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/teachers')
            ->assertOk()
            ->assertJsonFragment([
                'email' => 'new-teacher@example.com',
            ]);
    }

    public function test_teacher_creation_requires_a_login_password_of_at_least_eight_characters(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/teachers', [
                'name' => 'Missing Password Teacher',
                'email' => 'missing-password@example.com',
                'phone' => '',
                'bio' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('teachers', 0);
    }

    private function createAdmin(): User
    {
        return User::create([
            'name' => 'Teacher Management Admin',
            'first_name' => 'Teacher',
            'middle_name' => 'Management',
            'last_name' => 'Admin',
            'email' => 'teacher-admin@example.com',
            'password' => Hash::make('admin-password'),
            'country' => 'EGP',
            'birth_date' => '1990-01-01',
            'phone' => '01000000000',
            'is_admin' => true,
        ]);
    }
}
