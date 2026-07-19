<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Security;

final class PrivateSession
{
    private const DEFAULT_SESSION_NAME = 'caramagnols_private';

    private string $sessionName;

    public function __construct(?string $sessionName = null)
    {
        $this->sessionName = $this->normalizeSessionName((string) $sessionName);
    }

    public function start(): void
    {
        if ($this->isStarted()) {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');

        $secure = function_exists('request_is_secure') ? request_is_secure() : false;
        ini_set('session.cookie_secure', $secure ? '1' : '0');

        $cookieParams = [
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ];

        session_name($this->sessionName);
        $sessionId = $this->cookieSessionId();
        session_id($sessionId ?? '');
        session_set_cookie_params($cookieParams);
        session_cache_limiter('nocache');
        session_start();

        if (!isset($_SESSION['created_at'])) {
            $_SESSION['created_at'] = time();
        }
    }

    public function isStarted(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE && session_name() === $this->sessionName;
    }

    public function contextKey(): string
    {
        return 'private_user';
    }

    public function all(): array
    {
        if (!$this->isStarted()) {
            return [];
        }

        $context = $_SESSION[$this->contextKey()] ?? null;
        return is_array($context) ? $context : [];
    }

    public function setAll(array $context): void
    {
        $this->start();

        $_SESSION[$this->contextKey()] = $context;
    }

    public function get(string $key): mixed
    {
        $context = $this->all();
        return $context[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $context = $this->all();
        $context[$key] = $value;

        $this->setAll($context);
    }

    public function clear(): void
    {
        if (!$this->isStarted()) {
            return;
        }

        $this->setAll([]);

        if (function_exists('session_regenerate_id')) {
            session_regenerate_id(true);
        }
    }

    public function name(): string
    {
        return $this->sessionName;
    }

    private function normalizeSessionName(string $sessionName): string
    {
        $normalized = strtolower(trim($sessionName));
        $normalized = trim($normalized, "-_");
        $normalized = preg_replace('/[^a-z0-9_-]+/', '_', $normalized) ?? '';

        if ($normalized === '') {
            return self::DEFAULT_SESSION_NAME;
        }

        return $normalized;
    }

    private function cookieSessionId(): ?string
    {
        $value = $_COOKIE[$this->sessionName] ?? null;
        if (!is_string($value) || $value === '') {
            return null;
        }

        return preg_match('/\A[A-Za-z0-9,-]{16,128}\z/', $value) === 1 ? $value : null;
    }
}
