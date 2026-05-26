<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Http;

final class PrivateRouteResolver
{
    public function __construct(private readonly string $configuredBasePath)
    {
    }

    public function canonicalPath(string $page = 'login'): string
    {
        $basePath = $this->basePath();

        return match ($page) {
            'login' => $basePath . '/login',
            'dashboard' => $basePath . '/dashboard',
            'logout' => $basePath . '/logout',
            'activate' => $basePath . '/activate',
            'password_forgot' => $basePath . '/password/forgot',
            'password_reset' => $basePath . '/password/reset',
            'files' => $basePath . '/files',
            default => $basePath,
        };
    }

    public function basePath(): string
    {
        $normalized = strtolower(trim((string) $this->configuredBasePath));
        $normalized = trim($normalized, '/');
        $normalized = preg_replace('/[^a-z0-9_-]+/', '-', $normalized) ?: 'private';

        return '/' . trim($normalized, '-_');
    }

    /**
     * @return array<int, array{methods: array<int, string>, path: string, handler: array<string, mixed>}>
     */
    public function routeDefinitions(): array
    {
        $basePath = $this->basePath();
        $loginPath = $this->canonicalPath('login');
        $dashboardPath = $this->canonicalPath('dashboard');

        return [
            [
                'methods' => ['GET'],
                'path' => $basePath,
                'handler' => ['type' => 'redirect', 'location' => $loginPath, 'status' => 302],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $loginPath,
                'handler' => ['type' => 'private', 'page' => 'login'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $loginPath . '/index.php',
                'handler' => ['type' => 'private', 'page' => 'login'],
            ],
            [
                'methods' => ['GET'],
                'path' => $dashboardPath,
                'handler' => ['type' => 'private', 'page' => 'dashboard'],
            ],
            [
                'methods' => ['GET'],
                'path' => $dashboardPath . '.php',
                'handler' => ['type' => 'redirect', 'location' => $dashboardPath, 'status' => 301],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('logout'),
                'handler' => ['type' => 'private', 'page' => 'logout'],
            ],
            [
                'methods' => ['GET'],
                'path' => $this->canonicalPath('activate') . '/{token:[A-Za-z0-9._-]+}',
                'handler' => ['type' => 'private', 'page' => 'activate'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('password_forgot'),
                'handler' => ['type' => 'private', 'page' => 'password_forgot'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('password_reset') . '/{token:[A-Za-z0-9._-]+}',
                'handler' => ['type' => 'private', 'page' => 'password_reset'],
            ],
            [
                'methods' => ['GET'],
                'path' => $this->canonicalPath('files') . '/{documentId:[A-Za-z0-9._-]+}',
                'handler' => ['type' => 'private', 'page' => 'files'],
            ],
        ];
    }
}
