<?php

namespace Tests\Feature;

use App\Services\LoginAttemptLimiter;
use Tests\TestCase;

class LoginAttemptLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
    }

    public function test_fifth_failed_attempt_starts_a_fifteen_minute_lockout(): void
    {
        $limiter = app(LoginAttemptLimiter::class);
        $email = 'student@example.com';
        $ipAddress = '192.0.2.10';

        for ($attempt = 1; $attempt < 5; $attempt++) {
            $this->assertSame(0, $limiter->recordFailure($email, $ipAddress));
        }

        $this->assertSame(900, $limiter->recordFailure($email, $ipAddress));
        $this->assertSame(900, $limiter->lockoutSeconds($email, $ipAddress));
        $this->assertSame(900, $limiter->recordFailure($email, $ipAddress));

        $limiter->clear($email, $ipAddress);
    }

    public function test_lockout_expires_after_fifteen_minutes(): void
    {
        $limiter = app(LoginAttemptLimiter::class);
        $email = 'student@example.com';
        $ipAddress = '192.0.2.11';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $limiter->recordFailure($email, $ipAddress);
        }

        $this->travel(15)->minutes();

        $this->assertSame(0, $limiter->lockoutSeconds($email, $ipAddress));
        $this->assertSame(0, $limiter->recordFailure($email, $ipAddress));

        $limiter->clear($email, $ipAddress);
    }

    public function test_attempts_are_scoped_to_email_and_ip_and_can_be_cleared(): void
    {
        $limiter = app(LoginAttemptLimiter::class);
        $limiter->recordFailure('student@example.com', '192.0.2.12');

        $this->assertSame(0, $limiter->lockoutSeconds('other@example.com', '192.0.2.12'));
        $this->assertSame(0, $limiter->lockoutSeconds('student@example.com', '192.0.2.13'));

        $limiter->clear('student@example.com', '192.0.2.12');
        $this->assertSame(0, $limiter->recordFailure('student@example.com', '192.0.2.12'));
    }
}
