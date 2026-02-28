<?php
// backend/core/rate_limiter.php

declare(strict_types=1);

require_once __DIR__ . '/security.php';

/**
 * Simple rate limiter basé sur la session.
 */
class SessionRateLimiter
{
    private string $key;
    private int $capacity;
    private int $window;

    public function __construct(string $key, int $capacity = 5, int $window = 60)
    {
        ensure_session_started();

        $this->key = $key;
        $this->capacity = $capacity;
        $this->window = $window;

        if (!isset($_SESSION[$this->key]) || !is_array($_SESSION[$this->key])) {
            $_SESSION[$this->key] = [];
        }

        $this->prune();
    }

    private function prune(): void
    {
        $cutOff = time() - $this->window;
        $_SESSION[$this->key] = array_values(array_filter(
            $_SESSION[$this->key],
            static fn (int $timestamp): bool => $timestamp >= $cutOff
        ));
    }

    public function hit(): bool
    {
        if ($this->allow()) {
            $_SESSION[$this->key][] = time();
            return true;
        }

        return false;
    }

    public function allow(): bool
    {
        return count($_SESSION[$this->key]) < $this->capacity;
    }

    public function remaining(): int
    {
        return max(0, $this->capacity - count($_SESSION[$this->key]));
    }

    public function retryAfter(): int
    {
        if ($this->allow()) {
            return 0;
        }

        $first = $_SESSION[$this->key][0] ?? time();
        $diff = ($first + $this->window) - time();
        return max(1, $diff);
    }
}
