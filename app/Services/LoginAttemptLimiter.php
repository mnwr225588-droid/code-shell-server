<?php

namespace App\Services;

use Illuminate\Support\Facades\RateLimiter;

class LoginAttemptLimiter
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const ATTEMPT_WINDOW_SECONDS = 900;
    private const LOCKOUT_SECONDS = 900;

    public function lockoutSeconds(string $email, string $ipAddress): int
    {
        $key = $this->key($email, $ipAddress, 'lockout');

        return RateLimiter::tooManyAttempts($key, 1)
            ? RateLimiter::availableIn($key)
            : 0;
    }

    public function recordFailure(string $email, string $ipAddress): int
    {
        $lockoutSeconds = $this->lockoutSeconds($email, $ipAddress);
        if ($lockoutSeconds > 0) {
            return $lockoutSeconds;
        }

        $attemptsKey = $this->key($email, $ipAddress, 'attempts');
        RateLimiter::hit($attemptsKey, self::ATTEMPT_WINDOW_SECONDS);

        if (RateLimiter::attempts($attemptsKey) < self::MAX_FAILED_ATTEMPTS) {
            return 0;
        }

        $lockoutKey = $this->key($email, $ipAddress, 'lockout');
        RateLimiter::hit($lockoutKey, self::LOCKOUT_SECONDS);

        return RateLimiter::availableIn($lockoutKey);
    }

    public function clear(string $email, string $ipAddress): void
    {
        RateLimiter::clear($this->key($email, $ipAddress, 'attempts'));
        RateLimiter::clear($this->key($email, $ipAddress, 'lockout'));
    }

    private function key(string $email, string $ipAddress, string $purpose): string
    {
        $identity = mb_strtolower(trim($email)) . '|' . $ipAddress;

        return 'login:' . $purpose . ':' . hash('sha256', $identity);
    }
}
