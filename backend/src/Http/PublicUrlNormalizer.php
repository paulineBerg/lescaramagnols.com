<?php

declare(strict_types=1);

namespace Caramagnols\Http;

final class PublicUrlNormalizer
{
    /**
     * @var array<string, string>
     */
    private const ROUTE_ALIASES = [
        '/blog/index' => '/blog',
        '/blog/index.php' => '/blog',
        '/blog/proposer.php' => '/blog/proposer',
        '/search.php' => '/search',
        '/rss.php' => '/rss',
        '/assets/rss.php' => '/rss',
        '/auto-retro-panhard-la-dyna-modele-z12.php' => '/auto-retro/panhard/la-dyna-modele-z12.php',
        '/auto-retro/simca/simca-aronde-icone-francaise.php' => '/auto-retro/simca/histoire-simca-aronde-icone-francaise.php',
    ];

    /**
     * @var array<int, string>
     */
    private const DEFAULT_LOCAL_HOSTS = [
        'lescaramagnols.com',
        'www.lescaramagnols.com',
    ];

    private const IMAGE_PLACEHOLDER_PATH = '/assets/images/structure/logo.png';

    /**
     * @var array<int, string>
     */
    private const IMAGE_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'avif',
        'svg',
    ];

    public static function normalizeRoute(?string $route, ?string $baseRoute = null): ?string
    {
        if (!is_string($route)) {
            return null;
        }

        $route = self::cleanValue($route);
        if ($route === '' || $route === '#') {
            return null;
        }

        if (self::isNonHttpScheme($route)) {
            return $route;
        }

        if (self::isExternalHttpUrl($route)) {
            return $route;
        }

        return self::normalizeLocalRoute($route, $baseRoute);
    }

    public static function normalizeImageSource(string $src, ?string $baseRoute = null, bool $fallbackToPlaceholder = true): string
    {
        $normalized = self::normalizeHtmlAttributeUrl($src, $baseRoute, $fallbackToPlaceholder);
        if ($normalized === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $normalized) === 1) {
            return $normalized;
        }

        if (
            preg_match('#^/assets/images/#', $normalized) === 1
            || preg_match('#^/uploads/editorial/#', $normalized) === 1
        ) {
            if ($fallbackToPlaceholder && self::looksLikeImagePath($normalized) && !self::publicPathExists($normalized)) {
                return self::missingImagePlaceholderPath();
            }

            return $normalized;
        }

        return '';
    }

    public static function normalizeHtmlAttributeUrl(string $value, ?string $baseRoute = null, bool $allowImageFallback = false): string
    {
        $value = self::cleanValue($value);
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, '#')) {
            return $value;
        }

        if (preg_match('#^(?:mailto|tel|data):#i', $value) === 1) {
            return $value;
        }

        if (preg_match('#^javascript:#i', $value) === 1) {
            return '#';
        }

        $normalized = self::normalizeRoute($value, $baseRoute);
        if (!is_string($normalized) || $normalized === '') {
            return $value;
        }

        if (
            $allowImageFallback
            && !preg_match('#^https?://#i', $normalized)
            && self::looksLikeImagePath($normalized)
            && !self::publicPathExists($normalized)
        ) {
            return self::missingImagePlaceholderPath();
        }

        return $normalized;
    }

    public static function rewriteHtmlFragment(string $html, ?string $baseRoute = null): string
    {
        if ($html === '') {
            return '';
        }

        $rewritten = preg_replace_callback(
            '/\b(href|src|poster)\s*=\s*(["\'])(.*?)\2/is',
            static function (array $matches) use ($baseRoute): string {
                $attribute = strtolower((string) ($matches[1] ?? ''));
                $quote = (string) ($matches[2] ?? '"');
                $value = (string) ($matches[3] ?? '');
                $normalized = self::normalizeHtmlAttributeUrl($value, $baseRoute, $attribute !== 'href');

                return (string) ($matches[1] ?? $attribute)
                    . '='
                    . $quote
                    . htmlspecialchars($normalized, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . $quote;
            },
            $html
        );

        return is_string($rewritten) ? $rewritten : $html;
    }

    public static function publicPathExists(string $path): bool
    {
        if ($path === '' || $path[0] !== '/') {
            return false;
        }

        $absolutePath = ROOT_PATH . '/public' . $path;

        return is_file($absolutePath);
    }

    public static function missingImagePlaceholderPath(): string
    {
        return self::IMAGE_PLACEHOLDER_PATH;
    }

    private static function normalizeLocalRoute(string $route, ?string $baseRoute = null): ?string
    {
        $path = parse_url($route, PHP_URL_PATH);
        $query = parse_url($route, PHP_URL_QUERY);
        $fragment = parse_url($route, PHP_URL_FRAGMENT);

        if (!is_string($path)) {
            return null;
        }

        $path = self::resolveRelativePath($path, $baseRoute);
        $path = self::canonicalizeLocalPath($path);
        $path = self::applyRouteAlias($path);

        $normalized = $path;
        if (is_string($query) && $query !== '') {
            $normalized .= '?' . $query;
        }

        if (is_string($fragment) && $fragment !== '') {
            $normalized .= '#' . $fragment;
        }

        return $normalized;
    }

    private static function canonicalizeLocalPath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?? $path;

        if ($path === '') {
            return '/';
        }

        if ($path[0] !== '/') {
            $path = '/' . ltrim($path, '/');
        }

        $path = self::stripConfiguredBasePath($path);
        $path = preg_replace('#/+#', '/', $path) ?? $path;

        $trimmed = trim($path, '/');
        if ($trimmed !== '' && preg_match('#(?:^|/)site/(.+)$#i', $trimmed, $matches) === 1) {
            $path = '/' . ltrim((string) ($matches[1] ?? ''), '/');
        }

        if (preg_match('#/core/api/lang\.php$#i', $path) === 1) {
            return '/core/api/lang.php';
        }

        if (preg_match('#^/structure/images/(.+)$#i', $path, $matches) === 1) {
            $path = '/assets/images/structure/' . ltrim((string) ($matches[1] ?? ''), '/');
        } elseif (preg_match('#^/images/(.+)$#i', $path, $matches) === 1) {
            $path = '/assets/images/' . ltrim((string) ($matches[1] ?? ''), '/');
        }

        $path = preg_replace('#^/assets/images/images/#i', '/assets/images/', $path) ?? $path;
        $path = preg_replace('#^/assets/images/structure/images/#i', '/assets/images/structure/', $path) ?? $path;

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : $path;
    }

    private static function resolveRelativePath(string $path, ?string $baseRoute = null): string
    {
        if ($path === '' || str_starts_with($path, '/')) {
            return $path;
        }

        $basePath = is_string($baseRoute) ? parse_url($baseRoute, PHP_URL_PATH) : null;
        if (!is_string($basePath) || $basePath === '') {
            return '/' . ltrim($path, '/');
        }

        $basePath = self::canonicalizeLocalPath($basePath);
        $baseDirectory = preg_replace('#/[^/]*$#', '/', $basePath) ?? '/';

        return self::removeDotSegments($baseDirectory . ltrim($path, '/'));
    }

    private static function removeDotSegments(string $path): string
    {
        $segments = explode('/', $path);
        $resolved = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($resolved);
                continue;
            }

            $resolved[] = $segment;
        }

        return '/' . implode('/', $resolved);
    }

    private static function applyRouteAlias(string $path): string
    {
        return self::ROUTE_ALIASES[$path] ?? $path;
    }

    private static function cleanValue(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace('\\', '/', $value);

        return trim($value);
    }

    private static function isExternalHttpUrl(string $value): bool
    {
        if (preg_match('#^https?://#i', $value) !== 1) {
            return false;
        }

        $parts = parse_url($value);
        if (!is_array($parts)) {
            return true;
        }

        $host = strtolower(trim((string) ($parts['host'] ?? '')));
        if ($host === '') {
            return true;
        }

        return !in_array($host, self::localHosts(), true);
    }

    private static function isNonHttpScheme(string $value): bool
    {
        return preg_match('#^[a-z][a-z0-9+.-]*:#i', $value) === 1
            && preg_match('#^https?://#i', $value) !== 1;
    }

    /**
     * @return array<int, string>
     */
    private static function localHosts(): array
    {
        $hosts = self::DEFAULT_LOCAL_HOSTS;

        foreach (['site.url.domain', 'site.url.ssl_domain'] as $configKey) {
            $configured = trim((string) app_config($configKey, ''));
            if ($configured === '') {
                continue;
            }

            $host = parse_url(str_contains($configured, '://') ? $configured : ('https://' . $configured), PHP_URL_HOST);
            if (!is_string($host) || trim($host) === '') {
                continue;
            }

            $hosts[] = strtolower(trim($host));
        }

        return array_values(array_unique($hosts));
    }

    private static function stripConfiguredBasePath(string $path): string
    {
        $basePath = trim((string) app_config('site.url.base_path', '/'));
        if ($basePath === '' || $basePath === '/') {
            return $path;
        }

        $basePath = '/' . trim($basePath, '/');
        if (!str_starts_with($path, $basePath . '/')) {
            return $path;
        }

        return substr($path, strlen($basePath));
    }

    private static function looksLikeImagePath(string $path): bool
    {
        $extension = strtolower((string) pathinfo(parse_url($path, PHP_URL_PATH) ?? $path, PATHINFO_EXTENSION));

        return in_array($extension, self::IMAGE_EXTENSIONS, true);
    }
}
