<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class LoginAttemptLimiter
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const ATTEMPT_WINDOW_SECONDS = 900;
    private const LOCKOUT_SECONDS = 900;

    public function lockoutSeconds(string $email, string $ipAddress): int
    {
        $key = $this->key($email, $ipAddress, 'lockout');
        $cachedValues = Cache::many([$key, $key . ':timer']);
        $attempts = (int) ($cachedValues[$key] ?? 0);
        $availableAt = (int) ($cachedValues[$key . ':timer'] ?? 0);
        $now = now()->getTimestamp();

        if ($attempts < 1) {
            return 0;
        }

        if ($availableAt <= $now) {
            RateLimiter::clear($key);
            return 0;
        }

        return $availableAt - $now;
    }

    public function recordFailure(string $email, string $ipAddress): int
    {
        // The controller checks the lockout before authentication; reuse the increment result
        // to avoid another cache read for the failed-attempt count.
        $attemptsKey = $this->key($email, $ipAddress, 'attempts');
        $attempts = RateLimiter::hit($attemptsKey, self::ATTEMPT_WINDOW_SECONDS);

        if ($attempts < self::MAX_FAILED_ATTEMPTS) {
            return 0;
        }

        $lockoutKey = $this->key($email, $ipAddress, 'lockout');
        RateLimiter::hit($lockoutKey, self::LOCKOUT_SECONDS);

        return RateLimiter::availableIn($lockoutKey);
    }

    public function clear(string $email, string $ipAddress): void
    {
        RateLimiter::clear($this->key($email, $ipAddress, 'attempts'));
    }

    private function key(string $email, string $ipAddress, string $purpose): string
    {
        $identity = mb_strtolower(trim($email)) . '|' . $ipAddress;

        return 'login:' . $purpose . ':' . hash('sha256', $identity);
    }
}
