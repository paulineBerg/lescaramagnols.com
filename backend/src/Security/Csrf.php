<?php

declare(strict_types=1);

namespace Caramagnols\Security;

use Caramagnols\Http\Request;

class Csrf
{
    private const TOKEN_KEY = '_csrf_token';

    public static function token(string $scope = 'default'): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $key = self::scopeKey($scope);
        if (!isset($_SESSION[$key]) || !is_string($_SESSION[$key]) || $_SESSION[$key] === '') {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$key];
    }

    public static function validate(Request $request, string $scope = 'default', bool $rotate = false): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $key = self::scopeKey($scope);
        $sessionToken = $_SESSION[$key] ?? '';
        $token = $request->body()['_csrf'] ?? $request->header('X-CSRF-Token');

        $isValid = is_string($token) && is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $token);

        if ($isValid && $rotate) {
            unset($_SESSION[$key]);
        }

        return $isValid;
    }

    private static function scopeKey(string $scope): string
    {
        return sprintf('%s_%s', self::TOKEN_KEY, $scope);
    }
}
