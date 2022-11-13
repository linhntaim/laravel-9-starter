<?php

namespace App\Support\Cache;

use Closure;
use Illuminate\Cache\RateLimiter as BaseRateLimiter;
use Psr\SimpleCache\InvalidArgumentException;

class RateLimiter extends BaseRateLimiter
{
    /**
     * @throws InvalidArgumentException
     */
    protected function locked(string $key): bool
    {
        return $this->cache->has($key . ':lock_timer');
    }

    protected function lock(string $key, int $lockSeconds = 3600): static
    {
        $this->cache->add(
            $this->cleanRateLimiterKey($key) . ':lock_timer', $this->availableAt($lockSeconds), $lockSeconds
        );
        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function lockAvailableIn(string $key): int
    {
        return max(
            $this->availableIn($key),
            $this->cache->get($this->cleanRateLimiterKey($key) . ':lock_timer') - $this->currentTime()
        );
    }

    public function lockClear(string $key): void
    {
        $this->clear($key);
        $this->cache->forget($this->cleanRateLimiterKey($key) . ':lock_timer');
    }

    protected function safeExecuteMaxAttempts(int $maxAttempts, Closure $originCallback, Closure $callback): bool
    {
        return match (true) {
            $maxAttempts <= 0 => $originCallback() ?: true,
            default => $callback(),
        };
    }

    public function attempt($key, $maxAttempts, Closure $callback, $decaySeconds = 60): bool
    {
        return $this->safeExecuteMaxAttempts(
            $maxAttempts,
            $callback,
            fn() => parent::attempt($key, $maxAttempts, $callback, $decaySeconds)
        );
    }

    public function attemptWithDelay(string $key, int $maxAttempts, Closure $callback, int $decaySeconds = 60): bool
    {
        return $this->safeExecuteMaxAttempts(
            $maxAttempts,
            $callback,
            function () use ($key, $maxAttempts, $callback, $decaySeconds) {
                if ($this->tooManyAttempts($key, $maxAttempts)) {
                    sleep($this->availableIn($key));
                }

                return take($callback() ?: true, function () use ($key, $decaySeconds) {
                    $this->hit($key, $decaySeconds);
                });
            }
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    public function attemptWithLock(string $key, int $maxAttempts, Closure $callback, int $decaySeconds = 60, int $lockSeconds = 3600): bool
    {
        return $this->safeExecuteMaxAttempts(
            $maxAttempts,
            $callback,
            function () use ($key, $maxAttempts, $callback, $decaySeconds, $lockSeconds) {
                if ($this->locked($key)) {
                    return false;
                }
                if ($this->tooManyAttempts($key, $maxAttempts)) {
                    $this->lock($key, $lockSeconds);
                    return false;
                }

                return take($callback() ?: true, function () use ($key, $decaySeconds) {
                    $this->hit($key, $decaySeconds);
                });
            }
        );
    }
}
