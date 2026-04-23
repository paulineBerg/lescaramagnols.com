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
        return PublicUrlNormalizer::normalizeRoute($route);
    }

    /**
     * @return array<int, string>
     */
    public static function publicRouteVariants(string $route): array
    {
        $normalized = self::normalizePublicRoute($route);
        if ($normalized === null || $normalized === '' || $normalized[0] !== '/') {
            return [];
        }

        return [$normalized];
    }
}
