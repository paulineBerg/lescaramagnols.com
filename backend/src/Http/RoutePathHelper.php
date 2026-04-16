<?php

declare(strict_types=1);

namespace Caramagnols\Http;

final class RoutePathHelper
{
    public static function requestPath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return '/';
        }

        $normalized = '/' . ltrim($path, '/');

        if ($normalized === '/') {
            return '/';
        }

        return rtrim($normalized, '/');
    }

    public static function normalizePublicRoute(?string $route): ?string
    {
        if (!is_string($route)) {
            return null;
        }

        $route = str_replace('\\', '/', trim($route));
        if ($route === '' || $route === '#') {
            return null;
        }

        if (preg_match('#^https?://#i', $route) === 1) {
            return $route;
        }

        return '/' . ltrim($route, '/');
    }

    /**
     * @return array<int, string>
     */
    public static function publicRouteVariants(string $route): array
    {
        $normalized = self::normalizePublicRoute($route);
        if ($normalized === null || preg_match('#^https?://#i', $normalized) === 1) {
            return [];
        }

        return [$normalized];
    }
}

