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

class FileRateLimiter
{
    private string $path;
    private int $capacity;
    private int $window;

    public function __construct(string $key, int $capacity = 5, int $window = 60, ?string $directory = null)
    {
        $this->capacity = max(1, $capacity);
        $this->window = max(1, $window);
        $directory = $directory ?? (string) app_config('security.rate_limit_dir', ROOT_PATH . '/var/rate-limits');
        $directory = rtrim($directory, '/');

        if (!is_dir($directory)) {
            @mkdir($directory, 0700, true);
        }

        $this->path = $directory . '/' . hash('sha256', $key) . '.json';
    }

    public function allow(): bool
    {
        return count($this->entries()) < $this->capacity;
    }

    public function hit(): bool
    {
        $entries = $this->entries();
        if (count($entries) >= $this->capacity) {
            return false;
        }

        $entries[] = time();
        $this->writeEntries($entries);

        return true;
    }

    public function clear(): void
    {
        if (file_exists($this->path)) {
            @unlink($this->path);
        }
    }

    public function retryAfter(): int
    {
        $entries = $this->entries();
        if (count($entries) < $this->capacity) {
            return 0;
        }

        $first = $entries[0] ?? time();

        return max(1, ($first + $this->window) - time());
    }

    /**
     * @return array<int, int>
     */
    private function entries(): array
    {
        $raw = @file_get_contents($this->path);
        $decoded = is_string($raw) ? json_decode($raw, true) : [];
        $entries = is_array($decoded) ? $decoded : [];
        $cutOff = time() - $this->window;

        $entries = array_values(array_filter(
            $entries,
            static fn (mixed $timestamp): bool => is_int($timestamp) && $timestamp >= $cutOff
        ));

        if ($raw !== false) {
            $this->writeEntries($entries);
        }

        return $entries;
    }

    /**
     * @param array<int, int> $entries
     */
    private function writeEntries(array $entries): void
    {
        if ($entries === []) {
            if (file_exists($this->path)) {
                @unlink($this->path);
            }

            return;
        }

        @file_put_contents($this->path, json_encode($entries, JSON_UNESCAPED_SLASHES), LOCK_EX);
        @chmod($this->path, 0600);
    }
}

/**
 * @param array<int, string> $allowedIps
 */
function ip_matches_allowlist(?string $clientIp, array $allowedIps): bool
{
    if ($allowedIps === []) {
        return true;
    }

    if (!is_string($clientIp) || $clientIp === '') {
        return false;
    }

    foreach ($allowedIps as $allowedIp) {
        $allowedIp = trim($allowedIp);
        if ($allowedIp === '') {
            continue;
        }

        if ($clientIp === $allowedIp) {
            return true;
        }

        if (str_contains($allowedIp, '/') && ip_matches_cidr($clientIp, $allowedIp)) {
            return true;
        }
    }

    return false;
}

function ip_matches_cidr(string $clientIp, string $cidr): bool
{
    [$subnet, $prefixLength] = array_pad(explode('/', $cidr, 2), 2, null);
    $subnet = trim((string) $subnet);
    $prefixLength = is_numeric($prefixLength) ? (int) $prefixLength : -1;

    $clientBinary = @inet_pton($clientIp);
    $subnetBinary = @inet_pton($subnet);

    if ($clientBinary === false || $subnetBinary === false || strlen($clientBinary) !== strlen($subnetBinary)) {
        return false;
    }

    $maxBits = strlen($clientBinary) * 8;
    if ($prefixLength < 0 || $prefixLength > $maxBits) {
        return false;
    }

    $bytes = intdiv($prefixLength, 8);
    $bits = $prefixLength % 8;

    if ($bytes > 0 && substr($clientBinary, 0, $bytes) !== substr($subnetBinary, 0, $bytes)) {
        return false;
    }

    if ($bits === 0) {
        return true;
    }

    $mask = (~(0xff >> $bits)) & 0xff;

    return (ord($clientBinary[$bytes]) & $mask) === (ord($subnetBinary[$bytes]) & $mask);
}
