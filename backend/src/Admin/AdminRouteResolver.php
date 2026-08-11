<?php

declare(strict_types=1);

namespace Caramagnols\Admin;

final class AdminRouteResolver
{
    public function __construct(
        private readonly string $configuredLoginPath,
        private readonly ?string $basePathOverride = null
    ) {
    }

    public function loginPath(): string
    {
        return $this->sanitizePathSegment($this->configuredLoginPath);
    }

    public function canonicalPath(string $page = 'login'): string
    {
        $basePath = $this->adminBasePath();

        return match ($page) {
            'login' => $basePath,
            'dashboard' => $basePath . '/dashboard',
            'pages' => $basePath . '/pages',
            'articles' => $basePath . '/articles',
            'discussions' => $basePath . '/discussions',
            'media' => $basePath . '/media',
            'tiles' => $basePath . '/tiles',
            'menus' => $basePath . '/menus',
            'logs' => $basePath . '/logs',
            'settings' => $basePath . '/settings',
            'security_devices' => $basePath . '/securite/appareils-sessions',
            'pbgestion' => $basePath . '/pbgestion',
            'private_members' => $basePath . '/parametres/espace-prive',
            'logout' => $basePath . '/logout',
            'session_ping' => $basePath . '/session/ping',
            default => $basePath,
        };
    }

    public function pageCreatePath(): string
    {
        return $this->canonicalPath('pages') . '/new';
    }

    public function pageEditPath(string $slug): string
    {
        return $this->canonicalPath('pages') . '/' . rawurlencode(trim($slug));
    }

    public function tileCreatePath(): string
    {
        return $this->canonicalPath('tiles') . '/new';
    }

    public function tileEditPath(int $groupId): string
    {
        return $this->canonicalPath('tiles') . '/' . rawurlencode((string) $groupId);
    }

    public function blogSavePath(): string
    {
        return $this->canonicalPath('login') . '/articles/save';
    }

    public function sessionPingPath(): string
    {
        return $this->canonicalPath('session_ping');
    }

    public function articleCreatePath(): string
    {
        return $this->canonicalPath('articles') . '/new';
    }

    public function articleEditPath(string $slug, string $language): string
    {
        return $this->canonicalPath('articles')
            . '/'
            . rawurlencode(trim($slug))
            . '/'
            . rawurlencode(trim($language));
    }

    /**
     * @return array<int, array{methods: array<int, string>, path: string, handler: array<string, mixed>}>
     */
    public function routeDefinitions(): array
    {
        $loginPath = $this->canonicalPath('login');
        $dashboardPath = $this->canonicalPath('dashboard');
        $pagesPath = $this->canonicalPath('pages');
        $pageCreatePath = $this->pageCreatePath();
        $articlesPath = $this->canonicalPath('articles');
        $articleCreatePath = $this->articleCreatePath();
        $discussionsPath = $this->canonicalPath('discussions');
        $mediaPath = $this->canonicalPath('media');
        $tilesPath = $this->canonicalPath('tiles');
        $tileCreatePath = $this->tileCreatePath();
        $menusPath = $this->canonicalPath('menus');
        $logsPath = $this->canonicalPath('logs');
        $settingsPath = $this->canonicalPath('settings');
        $securityDevicesPath = $this->canonicalPath('security_devices');
        $pbGestionPath = $this->canonicalPath('pbgestion');
        $privateMembersPath = $this->canonicalPath('private_members');
        $logoutPath = $this->canonicalPath('logout');
        $blogSavePath = $this->blogSavePath();
        $sessionPingPath = $this->sessionPingPath();

        $routes = [
            [
                'methods' => ['GET', 'POST'],
                'path' => $loginPath,
                'handler' => ['type' => 'admin', 'page' => 'login'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $loginPath . '/index.php',
                'handler' => ['type' => 'admin', 'page' => 'login'],
            ],
            [
                'methods' => ['GET'],
                'path' => $dashboardPath,
                'handler' => ['type' => 'admin', 'page' => 'dashboard'],
            ],
            [
                'methods' => ['GET'],
                'path' => $dashboardPath . '.php',
                'handler' => ['type' => 'redirect', 'location' => $dashboardPath, 'status' => 301],
            ],
            [
                'methods' => ['GET'],
                'path' => $pagesPath,
                'handler' => ['type' => 'admin', 'page' => 'pages'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $pageCreatePath,
                'handler' => ['type' => 'admin', 'page' => 'pages_new'],
            ],
            [
                'methods' => ['GET'],
                'path' => $articlesPath,
                'handler' => ['type' => 'admin', 'page' => 'articles'],
            ],
            [
                'methods' => ['GET'],
                'path' => $articlesPath . '.php',
                'handler' => ['type' => 'admin', 'page' => 'articles'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $articleCreatePath,
                'handler' => ['type' => 'admin', 'page' => 'articles_new'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $articlesPath . '/{slug:[A-Za-z0-9_-]+}/{lang:[A-Za-z]+}',
                'handler' => ['type' => 'admin', 'page' => 'articles_edit'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $pagesPath . '/{slug:[A-Za-z0-9_-]+}',
                'handler' => ['type' => 'admin', 'page' => 'pages_edit'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $discussionsPath,
                'handler' => ['type' => 'admin', 'page' => 'discussions'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $discussionsPath . '.php',
                'handler' => ['type' => 'admin', 'page' => 'discussions'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $mediaPath,
                'handler' => ['type' => 'admin', 'page' => 'media'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $mediaPath . '.php',
                'handler' => ['type' => 'admin', 'page' => 'media'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $tilesPath,
                'handler' => ['type' => 'admin', 'page' => 'tiles'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $tilesPath . '.php',
                'handler' => ['type' => 'admin', 'page' => 'tiles'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $tileCreatePath,
                'handler' => ['type' => 'admin', 'page' => 'tiles_new'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $tilesPath . '/{id:[0-9]+}',
                'handler' => ['type' => 'admin', 'page' => 'tiles_edit'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $menusPath,
                'handler' => ['type' => 'admin', 'page' => 'menus'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $menusPath . '.php',
                'handler' => ['type' => 'admin', 'page' => 'menus'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $logsPath,
                'handler' => ['type' => 'admin', 'page' => 'logs'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $logsPath . '.php',
                'handler' => ['type' => 'admin', 'page' => 'logs'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $settingsPath,
                'handler' => ['type' => 'admin', 'page' => 'settings'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $securityDevicesPath,
                'handler' => ['type' => 'admin', 'page' => 'security_devices'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $pbGestionPath,
                'handler' => ['type' => 'admin', 'page' => 'pbgestion'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $settingsPath . '.php',
                'handler' => ['type' => 'admin', 'page' => 'settings'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $privateMembersPath,
                'handler' => ['type' => 'admin', 'page' => 'private_members'],
            ],
            [
                'methods' => ['GET'],
                'path' => $logoutPath,
                'handler' => ['type' => 'admin', 'page' => 'logout'],
            ],
            [
                'methods' => ['GET'],
                'path' => $logoutPath . '.php',
                'handler' => ['type' => 'admin', 'page' => 'logout'],
            ],
            [
                'methods' => ['POST'],
                'path' => $sessionPingPath,
                'handler' => ['type' => 'admin', 'page' => 'session_ping'],
            ],
            [
                'methods' => ['POST'],
                'path' => $blogSavePath,
                'handler' => ['type' => 'blog', 'action' => 'save_article'],
            ],
        ];

        return $routes;
    }

    private function adminBasePath(): string
    {
        $basePath = $this->normalizedBasePath();

        if ($basePath === '/') {
            return '/' . $this->loginPath();
        }

        return rtrim($basePath, '/') . '/' . $this->loginPath();
    }

    private function normalizedBasePath(): string
    {
        $configuredBasePath = $this->basePathOverride
            ?? (string) \app_config('site.url.base_path', \app_config('base_url', '/'));
        $basePath = \normalize_public_route($configuredBasePath) ?? '/';

        if (\preg_match('#^https?://#i', $basePath) === 1) {
            return '/';
        }

        return $basePath;
    }

    private function sanitizePathSegment(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = trim($normalized, '/');
        $normalized = preg_replace('/[^A-Za-z0-9_-]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-_');

        return $normalized !== '' ? $normalized : 'admin';
    }
}
