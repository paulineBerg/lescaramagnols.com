<?php

declare(strict_types=1);

namespace Caramagnols\Identity\PersistentSession;

use Caramagnols\Http\Request;
use Caramagnols\Identity\SessionScope;

final class PersistentSessionCookieManager
{
    /**
     * @return array{selector: string, secret: string}|null
     */
    public function read(Request $request, string $scope): ?array
    {
        $name = $this->cookieName($scope);
        $raw = is_string($request->cookies()[$name] ?? null) ? trim((string) $request->cookies()[$name]) : '';
        if ($raw === '' || !str_contains($raw, '.')) {
            return null;
        }

        [$selector, $secret] = explode('.', $raw, 2);
        $selector = trim($selector);
        $secret = trim($secret);
        if (
            preg_match('/\A[a-f0-9]{32}\z/', $selector) !== 1
            || preg_match('/\A[A-Za-z0-9_-]{32,96}\z/', $secret) !== 1
        ) {
            return null;
        }

        return ['selector' => $selector, 'secret' => $secret];
    }

    public function cookieName(string $scope): string
    {
        return match (SessionScope::normalize($scope)) {
            SessionScope::ADMIN => (string) app_config('identity.persistent.admin_cookie_name', 'caramagnols_admin_persistent'),
            SessionScope::PRIVATE => (string) app_config('identity.persistent.private_cookie_name', 'caramagnols_private_persistent'),
            default => (string) app_config('identity.persistent.identity_cookie_name', 'caramagnols_identity'),
        };
    }

    public function issueHeader(string $scope, string $selector, string $secret, int $expiresAt): string
    {
        return $this->header($scope, $selector . '.' . $secret, $expiresAt);
    }

    public function clearHeader(string $scope): string
    {
        return $this->header($scope, '', time() - 3600);
    }

    private function header(string $scope, string $value, int $expiresAt): string
    {
        $parts = [
            $this->cookieName($scope) . '=' . rawurlencode($value),
            'Expires=' . gmdate('D, d M Y H:i:s', $expiresAt) . ' GMT',
            'Max-Age=' . max(0, $expiresAt - time()),
            'Path=' . $this->path($scope),
            'SameSite=Strict',
            'HttpOnly',
        ];

        if ($this->secureCookies()) {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }

    private function path(string $scope): string
    {
        return match (SessionScope::normalize($scope)) {
            SessionScope::ADMIN => function_exists('admin_url') ? admin_url('login') : '/',
            SessionScope::PRIVATE => function_exists('private_portal_enabled') && private_portal_enabled()
                ? rtrim(private_route_resolver()->basePath(), '/') . '/'
                : '/',
            default => '/',
        };
    }

    private function secureCookies(): bool
    {
        if (function_exists('request_is_secure') && request_is_secure()) {
            return true;
        }

        return (bool) app_config('security.cookie_secure', false)
            || strtolower((string) app_config('environment', env('APP_ENV', 'production'))) === 'production';
    }
}
