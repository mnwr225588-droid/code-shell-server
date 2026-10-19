<?php

namespace Tests\Feature;

use App\Models\Admin;
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
}
