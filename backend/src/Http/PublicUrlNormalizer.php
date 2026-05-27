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

    private const RESPONSIVE_IMAGE_SIZES = '(max-width: 768px) 100vw, (max-width: 1200px) 90vw, 1200px';

    /**
     * @var array<int, int>
     */
    private const RESPONSIVE_IMAGE_WIDTHS = [
        480,
        640,
        768,
        960,
        1200,
        1600,
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
            if ($fallbackToPlaceholder && self::shouldFallbackToPlaceholder($normalized)) {
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
            && self::shouldFallbackToPlaceholder($normalized)
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
                $attribute = strtolower((string) $matches[1]);
                $quote = (string) $matches[2];
                $value = (string) $matches[3];
                $allowImageFallback = $attribute !== 'href';
                $normalized = self::normalizeHtmlAttributeUrl($value, $baseRoute, $allowImageFallback);
                $normalizedWithoutFallback = $allowImageFallback
                    ? self::normalizeHtmlAttributeUrl($value, $baseRoute, false)
                    : '';
                $fallbackMarker = '';

                if (
                    $attribute === 'src'
                    && $normalized === self::missingImagePlaceholderPath()
                    && $normalizedWithoutFallback !== ''
                    && $normalizedWithoutFallback !== self::missingImagePlaceholderPath()
                ) {
                    $fallbackMarker = ' data-fallback-image="placeholder"';
                }

                return (string) $matches[1]
                    . '='
                    . $quote
                    . htmlspecialchars($normalized, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . $quote
                    . $fallbackMarker;
            },
            $html
        );

        if (!is_string($rewritten)) {
            return $html;
        }

        return self::enrichImageTags($rewritten);
    }

    public static function prioritizeFirstImageInHtml(string $html): string
    {
        if ($html === '' || stripos($html, '<img') === false) {
            return $html;
        }

        $updated = preg_replace_callback(
            '/<img\b[^>]*>/i',
            static function (array $matches): string {
                $tag = (string) $matches[0];
                $tag = self::setTagAttribute($tag, 'loading', 'eager');
                $tag = self::setTagAttribute($tag, 'fetchpriority', 'high');
                if (!self::tagHasAttribute($tag, 'decoding')) {
                    $tag = self::addTagAttributeIfMissing($tag, 'decoding', 'async');
                }

                return $tag;
            },
            $html,
            1
        );

        return is_string($updated) ? $updated : $html;
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
        static $resolvedPlaceholder = null;

        if ($resolvedPlaceholder !== null) {
            return $resolvedPlaceholder;
        }

        if (self::publicPathExists(self::IMAGE_PLACEHOLDER_PATH)) {
            return $resolvedPlaceholder = self::IMAGE_PLACEHOLDER_PATH;
        }

        if (self::publicPathExists('/assets/images/structure/logo.webp')) {
            return $resolvedPlaceholder = '/assets/images/structure/logo.webp';
        }

        return $resolvedPlaceholder = self::IMAGE_PLACEHOLDER_PATH;
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
            $path = '/' . ltrim((string) $matches[1], '/');
        }

        if (preg_match('#/core/api/lang\.php$#i', $path) === 1) {
            return '/core/api/lang.php';
        }

        if (preg_match('#^/structure/images/(.+)$#i', $path, $matches) === 1) {
            $path = '/assets/images/structure/' . ltrim((string) $matches[1], '/');
        } elseif (preg_match('#^/images/(.+)$#i', $path, $matches) === 1) {
            $path = '/assets/images/' . ltrim((string) $matches[1], '/');
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

    private static function shouldFallbackToPlaceholder(string $path): bool
    {
        if (!self::looksLikeImagePath($path)) {
            return false;
        }

        if (preg_match('#^/assets/images/#', $path) === 1) {
            return !self::managedVersionedImageExists($path);
        }

        return !self::publicPathExists($path);
    }

    private static function managedVersionedImageExists(string $path): bool
    {
        return self::publicPathExists($path) || self::sourceImagePathExists($path);
    }

    private static function sourceImagePathExists(string $path): bool
    {
        if (preg_match('#^/assets/images/#', $path) !== 1) {
            return false;
        }

        $relativePath = substr($path, strlen('/assets/images/'));
        if (!is_string($relativePath) || $relativePath === '') {
            return false;
        }

        $frontendRoot = dirname(ROOT_PATH) . '/frontend/src/assets/images/';

        return is_file($frontendRoot . ltrim($relativePath, '/'));
    }

    private static function enrichImageTags(string $html): string
    {
        if (stripos($html, '<img') === false) {
            return $html;
        }

        $rewritten = preg_replace_callback(
            '/<img\b[^>]*>/i',
            static fn (array $matches): string => self::enrichImageTag((string) $matches[0]),
            $html
        );

        return is_string($rewritten) ? $rewritten : $html;
    }

    private static function enrichImageTag(string $tag): string
    {
        if ($tag === '' || stripos($tag, '<img') === false) {
            return $tag;
        }

        if (!self::tagHasAttribute($tag, 'loading')) {
            $tag = self::addTagAttributeIfMissing($tag, 'loading', 'lazy');
        }

        if (!self::tagHasAttribute($tag, 'decoding')) {
            $tag = self::addTagAttributeIfMissing($tag, 'decoding', 'async');
        }

        if (!self::tagHasAttribute($tag, 'fetchpriority')) {
            $tag = self::addTagAttributeIfMissing($tag, 'fetchpriority', 'low');
        }

        $src = self::extractImageSrcFromTag($tag);
        if ($src === '') {
            return $tag;
        }

        $hasWidth = self::tagHasAttribute($tag, 'width');
        $hasHeight = self::tagHasAttribute($tag, 'height');

        $dimensions = null;
        if (!$hasWidth || !$hasHeight) {
            $dimensions = self::resolveImageDimensionsForTag($src);
            if ($dimensions !== null) {
                if (!$hasWidth) {
                    $tag = self::addTagAttributeIfMissing($tag, 'width', (string) $dimensions['width']);
                }
                if (!$hasHeight) {
                    $tag = self::addTagAttributeIfMissing($tag, 'height', (string) $dimensions['height']);
                }
            }
        }

        if ($dimensions === null && $hasWidth && $hasHeight) {
            $dimensions = self::resolveImageDimensionsForTag($src);
        }

        return self::enrichResponsiveImageTag($tag, $src, $dimensions);
    }

    private static function extractImageSrcFromTag(string $tag): string
    {
        if (preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/i', $tag, $matches) !== 1) {
            return '';
        }

        return trim(html_entity_decode((string) $matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function tagHasAttribute(string $tag, string $attribute): bool
    {
        return preg_match('/\b' . preg_quote($attribute, '/') . '\s*=/i', $tag) === 1;
    }

    private static function addTagAttributeIfMissing(string $tag, string $name, string $value): string
    {
        if (self::tagHasAttribute($tag, $name)) {
            return $tag;
        }

        $encodedValue = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $updated = preg_replace(
            '/\s*\/?>$/',
            ' ' . $name . '="' . $encodedValue . '"$0',
            $tag,
            1
        );

        return is_string($updated) ? $updated : $tag;
    }

    private static function setTagAttribute(string $tag, string $name, string $value): string
    {
        $encodedValue = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if (self::tagHasAttribute($tag, $name)) {
            $updated = preg_replace(
                '/\b' . preg_quote($name, '/') . '\s*=\s*(["\']).*?\1/i',
                $name . '="' . $encodedValue . '"',
                $tag,
                1
            );

            return is_string($updated) ? $updated : $tag;
        }

        return self::addTagAttributeIfMissing($tag, $name, $value);
    }

    /**
     * @param array{width: int, height: int}|null $dimensions
     */
    private static function enrichResponsiveImageTag(string $tag, string $src, ?array $dimensions): string
    {
        if (
            self::tagHasAttribute($tag, 'srcset')
            || stripos($tag, '<picture') !== false
            || stripos($tag, 'data-no-responsive') !== false
        ) {
            return $tag;
        }

        $sourcePath = self::publicPathForImageSource($src);
        if (!is_string($sourcePath) || !self::isResponsiveEditorialPath($sourcePath)) {
            return $tag;
        }

        $resolvedDimensions = $dimensions ?? self::resolveImageDimensionsForTag($sourcePath);
        if (!is_array($resolvedDimensions) || (int) ($resolvedDimensions['width'] ?? 0) < 320) {
            return $tag;
        }

        $variants = self::buildResponsiveVariantSrcsets($sourcePath, (int) $resolvedDimensions['width']);
        if ($variants === null) {
            return $tag;
        }

        if ($variants['default'] !== '') {
            $tag = self::addTagAttributeIfMissing($tag, 'srcset', $variants['default']);
            $tag = self::addTagAttributeIfMissing($tag, 'sizes', self::RESPONSIVE_IMAGE_SIZES);
        }

        if ($variants['webp'] === '') {
            return $tag;
        }

        $webpSrcset = htmlspecialchars($variants['webp'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $sizes = htmlspecialchars(self::RESPONSIVE_IMAGE_SIZES, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<picture><source type="image/webp" srcset="' . $webpSrcset . '" sizes="' . $sizes . '">' . $tag . '</picture>';
    }

    private static function isResponsiveEditorialPath(string $path): bool
    {
        return preg_match('#^/uploads/editorial/(?:media|library)/#i', $path) === 1;
    }

    /**
     * @return array{default: string, webp: string}|null
     */
    private static function buildResponsiveVariantSrcsets(string $sourcePath, int $sourceWidth): ?array
    {
        $sourceWidth = max(1, $sourceWidth);
        $sourceExtension = strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION));
        if (!in_array($sourceExtension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return null;
        }

        $defaultCandidates = self::buildVariantCandidates($sourcePath, $sourceExtension, $sourceWidth);
        $webpCandidates = self::buildVariantCandidates($sourcePath, 'webp', $sourceWidth);

        if (count($defaultCandidates) < 2 && count($webpCandidates) < 2) {
            return null;
        }

        return [
            'default' => self::renderSrcsetValue($defaultCandidates),
            'webp' => self::renderSrcsetValue($webpCandidates),
        ];
    }

    /**
     * @return array<string, int>
     */
    private static function buildVariantCandidates(string $sourcePath, string $targetExtension, int $sourceWidth): array
    {
        $parsed = pathinfo($sourcePath);
        $directory = isset($parsed['dirname']) && is_string($parsed['dirname']) ? rtrim($parsed['dirname'], '/') : '';
        $filename = isset($parsed['filename']) && is_string($parsed['filename']) ? $parsed['filename'] : '';
        if ($directory === '' || $filename === '') {
            return [];
        }

        $candidates = [];
        $fullSizePath = $directory . '/' . $filename . '.' . $targetExtension;
        if (self::publicPathExists($fullSizePath)) {
            $fullSizeDimensions = self::resolveImageDimensionsForTag($fullSizePath);
            if (is_array($fullSizeDimensions) && (int) ($fullSizeDimensions['width'] ?? 0) > 0) {
                $candidates[$fullSizePath] = (int) $fullSizeDimensions['width'];
            }
        }

        foreach (self::RESPONSIVE_IMAGE_WIDTHS as $width) {
            if ($width >= $sourceWidth) {
                continue;
            }

            $variantPath = $directory . '/' . $filename . '-w' . $width . '.' . $targetExtension;
            if (!self::publicPathExists($variantPath)) {
                continue;
            }

            $variantDimensions = self::resolveImageDimensionsForTag($variantPath);
            if (!is_array($variantDimensions) || (int) ($variantDimensions['width'] ?? 0) <= 0) {
                continue;
            }

            $candidates[$variantPath] = (int) $variantDimensions['width'];
        }

        asort($candidates, SORT_NUMERIC);

        return $candidates;
    }

    /**
     * @param array<string, int> $candidates
     */
    private static function renderSrcsetValue(array $candidates): string
    {
        if ($candidates === []) {
            return '';
        }

        $parts = [];
        foreach ($candidates as $path => $width) {
            if ($width <= 0) {
                continue;
            }

            $parts[] = $path . ' ' . $width . 'w';
        }

        return implode(', ', $parts);
    }

    /**
     * @return array{width: int, height: int}|null
     */
    private static function resolveImageDimensionsForTag(string $src): ?array
    {
        $path = self::publicPathForImageSource($src);
        if ($path === null) {
            return null;
        }

        /** @var array<string, array{width: int, height: int}|null> $cache */
        static $cache = [];
        if (array_key_exists($path, $cache)) {
            return $cache[$path];
        }

        $publicFile = ROOT_PATH . '/public' . $path;
        $dimensions = @getimagesize($publicFile);
        if (
            is_array($dimensions)
            && (int) $dimensions[0] > 0
            && (int) $dimensions[1] > 0
        ) {
            return $cache[$path] = [
                'width' => (int) $dimensions[0],
                'height' => (int) $dimensions[1],
            ];
        }

        if (preg_match('#^/assets/images/#', $path) === 1) {
            $relativePath = ltrim(substr($path, strlen('/assets/images/')), '/');
            if ($relativePath !== '') {
                $sourceFile = dirname(ROOT_PATH) . '/frontend/src/assets/images/' . $relativePath;
                $sourceDimensions = @getimagesize($sourceFile);
                if (
                    is_array($sourceDimensions)
                    && (int) $sourceDimensions[0] > 0
                    && (int) $sourceDimensions[1] > 0
                ) {
                    return $cache[$path] = [
                        'width' => (int) $sourceDimensions[0],
                        'height' => (int) $sourceDimensions[1],
                    ];
                }
            }
        }

        $cache[$path] = null;

        return null;
    }

    private static function publicPathForImageSource(string $src): ?string
    {
        $normalized = trim($src);
        if ($normalized === '' || preg_match('#^(?:data|blob):#i', $normalized) === 1) {
            return null;
        }

        if (preg_match('#^https?://#i', $normalized) === 1) {
            $parts = parse_url($normalized);
            if (!is_array($parts)) {
                return null;
            }

            $host = strtolower(trim((string) ($parts['host'] ?? '')));
            if ($host === '' || !in_array($host, self::localHosts(), true)) {
                return null;
            }

            $normalized = (string) ($parts['path'] ?? '');
        }

        if (!str_starts_with($normalized, '/')) {
            return null;
        }

        if (
            preg_match('#^/assets/images/#', $normalized) !== 1
            && preg_match('#^/uploads/editorial/#', $normalized) !== 1
        ) {
            return null;
        }

        return $normalized;
    }
}
