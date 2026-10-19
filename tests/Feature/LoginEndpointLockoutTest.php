<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginEndpointLockoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_login_is_blocked_after_five_invalid_credentials_for_fifteen_minutes(): void
    {
        User::create([
            'name' => 'Login Limit Student',
            'first_name' => 'Login Limit',
            'middle_name' => 'Test',
            'last_name' => 'Student',
            'email' => 'login-limit-student@example.com',
            'password' => Hash::make('correct-password'),
            'country' => 'EGP',
            'birth_date' => '2000-01-01',
            'phone' => '01000000451',
        ]);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postJson('/api/login', [
                'email' => 'login-limit-student@example.com',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/login', [
            'email' => 'login-limit-student@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429)
            ->assertHeader('Retry-After', '900');

        $this->postJson('/api/login', [
            'email' => 'login-limit-student@example.com',
            'password' => 'correct-password',
        ])->assertStatus(429);

        $this->travel(15)->minutes();

        $this->postJson('/api/login', [
            'email' => 'login-limit-student@example.com',
            'password' => 'correct-password',
        ])->assertOk();
    }

    public function test_admin_login_is_blocked_after_five_invalid_credentials(): void
    {
        Admin::create([
            'name' => 'Login Limit Admin',
            'email' => 'login-limit-admin@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postJson('/api/admin/login', [
                'email' => 'login-limit-admin@example.com',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/admin/login', [
            'email' => 'login-limit-admin@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429)
            ->assertHeader('Retry-After', '900');

        $this->postJson('/api/admin/login', [
            'email' => 'login-limit-admin@example.com',
            'password' => 'correct-password',
        ])->assertStatus(429);
    }

    public function test_user_account_linked_to_a_teacher_is_still_identified_as_a_teacher(): void
    {
        User::create([
        'name' => 'Linked Teacher',
        'first_name' => 'Linked',
        'middle_name' => 'Teacher',
        'last_name' => 'Account',
        'email' => 'linked-teacher@example.com',
        'password' => Hash::make('correct-password'),
        'country' => 'EG',
        'birth_date' => '2000-01-01',
        'phone' => '01000000452',
        ]);
        Teacher::create([
        'name' => 'Linked Teacher',
        'email' => 'linked-teacher@example.com',
        'password' => Hash::make('correct-password'),
        ]);

        $this->post('/api/login', [
        'email' => 'linked-teacher@example.com',
        'password' => 'correct-password',
        ])->assertOk()
        ->assertJsonPath('user.user_type', 'teacher')
        ->assertJsonMissingPath('user.is_teacher_account');
    }

    public function test_successful_login_resets_the_failed_attempt_counter(): void
    {
        User::create([
        'name' => 'Reset Login Limit',
        'first_name' => 'Reset',
        'middle_name' => 'Login',
        'last_name' => 'Limit',
        'email' => 'reset-login-limit@example.com',
        'password' => Hash::make('correct-password'),
        'country' => 'EG',
        'birth_date' => '2000-01-01',
        'phone' => '01000000453',
        ]);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
        $this->postJson('/api/login', [
            'email' => 'reset-login-limit@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
        }

        $this->post('/api/login', [
        'email' => 'reset-login-limit@example.com',
        'password' => 'correct-password',
        ])->assertOk();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
        $this->postJson('/api/login', [
            'email' => 'reset-login-limit@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
        }

        $this->postJson('/api/login', [
        'email' => 'reset-login-limit@example.com',
        'password' => 'wrong-password',
        ])->assertStatus(429)
        ->assertHeader('Retry-After', '900');
    }
}
