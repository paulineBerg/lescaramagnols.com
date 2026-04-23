<?php

use Caramagnols\Admin\AdminRouteResolver;
use Caramagnols\Assets\ViteAssetManager;
use Caramagnols\Blog\BlogDiscussionRepositoryInterface;
use Caramagnols\Blog\BlogRepositoryInterface;
use Caramagnols\Blog\DualWriteBlogDiscussionRepository;
use Caramagnols\Blog\DualWriteBlogRepository;
use Caramagnols\Blog\JsonBlogDiscussionRepository;
use Caramagnols\Blog\JsonBlogRepository;
use Caramagnols\Blog\SqlBlogDiscussionRepository;
use Caramagnols\Blog\SqlBlogRepository;
use Caramagnols\Content\PageTileRenderer;
use Caramagnols\Content\PageRepository;
use Caramagnols\Content\TileRepository;
use Caramagnols\Database\DatabaseConfig;
use Caramagnols\Database\EditorialDatabase;
use Caramagnols\Database\EditorialSchemaManager;
use Caramagnols\Http\Request;
use Caramagnols\Http\RoutePathHelper;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\Logging\LoggerFactory;
use Caramagnols\Logging\SqlLogStore;
use Caramagnols\Navigation\NavigationRepository;
use Caramagnols\Social\InstagramFeedService;

function request_path(string $uri): string
{
    return RoutePathHelper::requestPath($uri);
}

function normalize_public_route(?string $route): ?string
{
    return RoutePathHelper::normalizePublicRoute($route);
}

/**
 * @return array<int, string>
 */
function public_route_variants(string $route): array
{
    return RoutePathHelper::publicRouteVariants($route);
}

function app_host_strip_port(string $host): string
{
    $normalized = strtolower(trim($host));
    if ($normalized === '') {
        return '';
    }

    if (str_starts_with($normalized, '[')) {
        $closingBracket = strpos($normalized, ']');
        if ($closingBracket !== false) {
            return substr($normalized, 1, $closingBracket - 1);
        }

        return trim($normalized, '[]');
    }

    if (substr_count($normalized, ':') === 1) {
        [$hostname, $port] = explode(':', $normalized, 2);
        if ($hostname !== '' && $port !== '' && ctype_digit($port)) {
            return $hostname;
        }
    }

    return $normalized;
}

function app_host_is_local_or_private(string $host): bool
{
    $normalizedHost = app_host_strip_port($host);
    if ($normalizedHost === '') {
        return false;
    }

    if (
        $normalizedHost === 'localhost'
        || $normalizedHost === '127.0.0.1'
        || $normalizedHost === '::1'
        || str_ends_with($normalizedHost, '.localhost')
        || str_ends_with($normalizedHost, '.local')
    ) {
        return true;
    }

    if (filter_var($normalizedHost, FILTER_VALIDATE_IP) === false) {
        return false;
    }

    return filter_var(
        $normalizedHost,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) === false;
}

function app_base_url(?Request $request = null): string
{
    $configured = app_config('base_url', '/');
    $configuredSiteUrl = app_config('site.url', []);
    $siteUrl = is_array($configuredSiteUrl) ? $configuredSiteUrl : [];
    $configuredBasePath = normalize_public_route((string) ($siteUrl['base_path'] ?? ''));

    if ($configuredBasePath === null && is_string($configured) && preg_match('#^https?://#i', $configured) === 1) {
        return rtrim($configured, '/');
    }

    $scheme = 'http';

    if ($request !== null) {
        $forwardedProto = $request->header('X-Forwarded-Proto', '') ?? '';
        if ($forwardedProto === 'https') {
            $scheme = 'https';
        }
    }

    if (
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
    ) {
        $scheme = 'https';
    }

    $basePath = $configuredBasePath;
    if ($basePath === null) {
        $basePath = is_string($configured) ? trim($configured) : '/';
        $basePath = $basePath === '' ? '/' : '/' . trim($basePath, '/');
    }

    $preferredHost = trim((string) ($scheme === 'https'
        ? ($siteUrl['ssl_domain'] ?? $siteUrl['domain'] ?? '')
        : ($siteUrl['domain'] ?? $siteUrl['ssl_domain'] ?? '')));
    $requestHost = $request?->header('Host', '') ?? '';
    if (!is_string($requestHost) || $requestHost === '') {
        $requestHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
    }

    if ($preferredHost !== '') {
        $canUseConfiguredHost = true;

        if (app_host_is_local_or_private($preferredHost) && $requestHost !== '' && !app_host_is_local_or_private($requestHost)) {
            $canUseConfiguredHost = false;
        }

        if ($canUseConfiguredHost) {
            return rtrim($scheme . '://' . $preferredHost, '/') . ($basePath === '/' ? '' : $basePath);
        }
    }

    if ($requestHost === '') {
        return $basePath;
    }

    return rtrim($scheme . '://' . $requestHost, '/') . ($basePath === '/' ? '' : $basePath);
}

function app_url(string $path = '', ?Request $request = null): string
{
    if (preg_match('#^https?://#i', $path) === 1) {
        return $path;
    }

    $baseUrl = app_base_url($request);
    if ($path === '') {
        return $baseUrl;
    }

    $normalizedPath = '/' . ltrim($path, '/');

    if ($baseUrl === '/') {
        return $normalizedPath;
    }

    return rtrim($baseUrl, '/') . $normalizedPath;
}

function &app_request_context_store(): array
{
    static $context = [];

    return $context;
}

/**
 * @param array<string, scalar|null> $context
 */
function app_request_context_set(array $context): void
{
    $store =& app_request_context_store();
    $normalized = [];

    foreach ($context as $key => $value) {
        if (!is_string($key) || trim($key) === '') {
            continue;
        }

        if ($value === null || is_scalar($value)) {
            $normalized[$key] = $value;
        }
    }

    $store = $normalized;
}

/**
 * @return array<string, scalar|null>
 */
function app_request_context_get(): array
{
    return app_request_context_store();
}

function app_request_context_clear(): void
{
    $store =& app_request_context_store();
    $store = [];
}

function app_request_id(?string $requestId = null): string
{
    $store =& app_request_context_store();

    if ($requestId !== null) {
        $normalized = trim($requestId);
        if ($normalized !== '') {
            $store['request_id'] = $normalized;
        }
    }

    return is_string($store['request_id'] ?? null) ? $store['request_id'] : '';
}

function app_generate_request_id(): string
{
    try {
        return bin2hex(random_bytes(12));
    } catch (\Throwable) {
        return uniqid('req-', true);
    }
}

/**
 * @param array<int, string> $scopes
 */
function app_runtime_cache_clear(array $scopes = ['pages', 'navigation', 'translations']): void
{
    if (in_array('pages', $scopes, true) && function_exists('pages_cache_clear')) {
        pages_cache_clear();
    }

    if (in_array('navigation', $scopes, true)) {
        if (function_exists('navigation_repository_cache_clear')) {
            navigation_repository_cache_clear();
        }
        if (function_exists('navigation_view_model_cache_clear')) {
            navigation_view_model_cache_clear();
        }
    }

    if (in_array('translations', $scopes, true) && function_exists('translation_runtime_cache_clear')) {
        translation_runtime_cache_clear();
    }

    if (in_array('tiles', $scopes, true) && function_exists('tile_repository_cache_clear')) {
        tile_repository_cache_clear();
    }
}

function admin_route_resolver(): AdminRouteResolver
{
    static $resolver = null;
    static $configuredLoginPath = null;

    $currentLoginPath = (string) app_config('admin.login_path', 'admin');

    if (!$resolver instanceof AdminRouteResolver || $configuredLoginPath !== $currentLoginPath) {
        $resolver = new AdminRouteResolver($currentLoginPath);
        $configuredLoginPath = $currentLoginPath;
    }

    return $resolver;
}

function admin_url(string $page = 'login'): string
{
    return admin_route_resolver()->canonicalPath($page);
}

function admin_blog_save_url(): string
{
    return admin_route_resolver()->blogSavePath();
}

function blog_data_dir(): string
{
    $configured = app_config('blog.data_dir', ROOT_PATH . '/data/blog');

    if (!is_string($configured) || trim($configured) === '') {
        return ROOT_PATH . '/data/blog';
    }

    return $configured;
}

function blog_discussions_data_dir(): string
{
    $configured = app_config('blog.discussions_data_dir', blog_data_dir() . '/discussions');

    if (!is_string($configured) || trim($configured) === '') {
        return blog_data_dir() . '/discussions';
    }

    return $configured;
}

function blog_storage_mode(): string
{
    $mode = strtolower(trim((string) app_config('blog.storage', editorial_storage_mode())));

    if (in_array($mode, ['json', 'sql', 'dual-write'], true)) {
        return $mode;
    }

    return editorial_storage_mode();
}

function blog_repository(): BlogRepositoryInterface
{
    static $repository = null;
    static $state = null;

    $currentState = [
        'mode' => blog_storage_mode(),
        'path' => blog_data_dir(),
        'database' => (array) app_config('database', []),
        'database_prefix' => (string) app_config('database_prefix', 'car_'),
        'schema_dir' => (string) app_config('editorial.schema_dir', ROOT_PATH . '/sql/editorial'),
    ];

    if (!$repository instanceof BlogRepositoryInterface || $state !== $currentState) {
        $jsonRepository = new JsonBlogRepository($currentState['path']);

        if ($currentState['mode'] === 'sql') {
            $repository = new SqlBlogRepository(editorial_database());
        } elseif ($currentState['mode'] === 'dual-write') {
            $repository = new DualWriteBlogRepository(
                $jsonRepository,
                new SqlBlogRepository(editorial_database())
            );
        } else {
            $repository = $jsonRepository;
        }

        $state = $currentState;
    }

    return $repository;
}

function blog_discussion_repository(): BlogDiscussionRepositoryInterface
{
    static $repository = null;
    static $state = null;

    $currentState = [
        'mode' => blog_storage_mode(),
        'path' => blog_discussions_data_dir(),
        'database' => (array) app_config('database', []),
        'database_prefix' => (string) app_config('database_prefix', 'car_'),
        'schema_dir' => (string) app_config('editorial.schema_dir', ROOT_PATH . '/sql/editorial'),
    ];

    if (!$repository instanceof BlogDiscussionRepositoryInterface || $state !== $currentState) {
        $jsonRepository = new JsonBlogDiscussionRepository($currentState['path']);

        if ($currentState['mode'] === 'sql') {
            $repository = new SqlBlogDiscussionRepository(editorial_database());
        } elseif ($currentState['mode'] === 'dual-write') {
            $repository = new DualWriteBlogDiscussionRepository(
                $jsonRepository,
                new SqlBlogDiscussionRepository(editorial_database())
            );
        } else {
            $repository = $jsonRepository;
        }

        $state = $currentState;
    }

    return $repository;
}

function instagram_feed_service(): InstagramFeedService
{
    static $service = null;
    static $configuredPath = null;

    $currentPath = (string) app_config('site.instagram.cache_path', ROOT_PATH . '/var/cache/instagram-feed.json');
    if (trim($currentPath) === '') {
        $currentPath = ROOT_PATH . '/var/cache/instagram-feed.json';
    }

    if (!$service instanceof InstagramFeedService || $configuredPath !== $currentPath) {
        $service = new InstagramFeedService($currentPath);
        $configuredPath = $currentPath;
    }

    return $service;
}

function editorial_storage_mode(): string
{
    $mode = strtolower(trim((string) app_config('editorial.storage', 'json')));

    return in_array($mode, ['json', 'sql', 'dual-write'], true) ? $mode : 'json';
}

function editorial_database(): EditorialDatabase
{
    static $database = null;
    static $state = null;

    $currentState = [
        'config' => (array) app_config('database', []),
        'prefix' => (string) app_config('database_prefix', 'car_'),
        'schema_dir' => (string) app_config('editorial.schema_dir', ROOT_PATH . '/sql/editorial'),
    ];

    if (!$database instanceof EditorialDatabase || $state !== $currentState) {
        $database = new EditorialDatabase(
            DatabaseConfig::fromArray($currentState['config']),
                $currentState['prefix'],
                new EditorialSchemaManager(
                    $currentState['prefix'],
                    editorial_schema_migration_files($currentState['schema_dir'])
                )
            );
        $state = $currentState;
    }

    return $database;
}

/**
 * @return array<int, string>
 */
function editorial_schema_migration_files(string $schemaDir): array
{
    $files = [];

    foreach (glob(rtrim($schemaDir, '/') . '/*.sql') ?: [] as $filePath) {
        $basename = basename($filePath);
        if (preg_match('/^(\d+)_.*\.sql$/', $basename, $matches) !== 1) {
            continue;
        }

        $files[(int) $matches[1]] = $filePath;
    }

    ksort($files);

    return $files;
}

function page_repository(?string $path = null): PageRepository
{
    return new PageRepository($path ?? ROOT_PATH . '/data/pages.json');
}

function tile_repository(): TileRepository
{
    static $repository = null;
    static $state = null;

    $currentState = [
        'database' => (array) app_config('database', []),
        'database_prefix' => (string) app_config('database_prefix', 'car_'),
        'schema_dir' => (string) app_config('editorial.schema_dir', ROOT_PATH . '/sql/editorial'),
    ];

    if (!$repository instanceof TileRepository || $state !== $currentState) {
        $repository = new TileRepository(editorial_database());
        $state = $currentState;
    }

    return $repository;
}

function tile_repository_cache_clear(): void
{
    tile_repository()->clearCache();
}

function getTileImage(string $size, ?string $image = null): string
{
    $normalizedSize = TileRepository::normalizeTileSizeValue($size);
    $imageName = trim((string) ($image ?? ''));

    if ($imageName === '') {
        $imageName = TileRepository::buttonFilename($normalizedSize, 'bleu');
    }

    if (preg_match('#^(?:https?:)?//#i', $imageName) === 1 || str_starts_with($imageName, '/')) {
        return $imageName;
    }

    return '/assets/images/structure/menu/'
        . TileRepository::buttonFolderForSize($normalizedSize)
        . '/'
        . ltrim($imageName, '/');
}

function getTileButtonImage(string $size, string $colorToken = 'bleu', string $state = 'default'): string
{
    return getTileImage($size, TileRepository::buttonFilename($size, $colorToken, $state));
}

function page_tile_renderer(): PageTileRenderer
{
    static $renderer = null;
    static $state = null;

    $currentState = [
        'pages_path' => pages_data_path(),
        'storage_mode' => editorial_storage_mode(),
        'database' => (array) app_config('database', []),
        'database_prefix' => (string) app_config('database_prefix', 'car_'),
        'schema_dir' => (string) app_config('editorial.schema_dir', ROOT_PATH . '/sql/editorial'),
    ];

    if (!$renderer instanceof PageTileRenderer || $state !== $currentState) {
        $renderer = new PageTileRenderer(tile_repository(), page_repository($currentState['pages_path']));
        $state = $currentState;
    }

    return $renderer;
}

function render_page_tiles_after_body(string $pageSlug, string $language): string
{
    $normalizedSlug = trim($pageSlug);
    if ($normalizedSlug === '') {
        return '';
    }

    $normalizedLanguage = trim($language) !== '' ? trim($language) : 'fr';

    try {
        return page_tile_renderer()->renderAfterBody($normalizedSlug, $normalizedLanguage);
    } catch (\Throwable $exception) {
        if (!str_contains($exception->getMessage(), 'Configuration SQL éditoriale incomplète.')) {
            error_log('[page_tile_renderer] ' . $exception->getMessage());
        }

        return '';
    }
}

function navigation_repository(?string $path = null): NavigationRepository
{
    return new NavigationRepository($path ?? ROOT_PATH . '/data/menus.json');
}

function app_event_logger(): AppEventLogger
{
    static $logger = null;
    static $state = null;

    $currentState = [
        'dir' => ROOT_PATH . '/data/logs',
        'env' => (string) app_config('env', 'development'),
        'database' => (array) app_config('database', []),
        'database_prefix' => (string) app_config('database_prefix', 'car_'),
        'schema_dir' => (string) app_config('editorial.schema_dir', ROOT_PATH . '/sql/editorial'),
    ];

    if (!$logger instanceof AppEventLogger || $state !== $currentState) {
        $logger = new AppEventLogger(
            new LoggerFactory($currentState['dir'], $currentState['env'], app_sql_log_store())
        );
        $state = $currentState;
    }

    return $logger;
}

function app_sql_log_store(): SqlLogStore
{
    static $store = null;
    static $state = null;

    $currentState = [
        'database' => (array) app_config('database', []),
        'database_prefix' => (string) app_config('database_prefix', 'car_'),
        'schema_dir' => (string) app_config('editorial.schema_dir', ROOT_PATH . '/sql/editorial'),
    ];

    if (!$store instanceof SqlLogStore || $state !== $currentState) {
        $store = new SqlLogStore(editorial_database());
        $state = $currentState;
    }

    return $store;
}

function vite_asset_manager(): ViteAssetManager
{
    static $manager = null;
    static $state = null;

    $currentState = [
        'manifest' => ROOT_PATH . '/public/.vite/manifest.json',
        'dev_url' => env('VITE_DEV_SERVER_URL', 'http://localhost:5173'),
        'dev_mode' => defined('APP_ENV') && APP_ENV !== 'production',
    ];

    if (!$manager instanceof ViteAssetManager || $state !== $currentState) {
        $manager = new ViteAssetManager(
            $currentState['manifest'],
            is_string($currentState['dev_url']) ? $currentState['dev_url'] : 'http://localhost:5173',
            (bool) $currentState['dev_mode']
        );
        $state = $currentState;
    }

    return $manager;
}

/**
 * Charge le manifest Vite une seule fois par requete.
 */
function load_vite_manifest(): ?array
{
    return vite_asset_manager()->loadManifest();
}

/**
 * URL du serveur Vite en mode dev.
 */
function vite_dev_server_url(): string
{
    return vite_asset_manager()->devServerUrl();
}

/**
 * Detecte si le serveur Vite dev est joignable.
 */
function vite_dev_server_is_reachable(): bool
{
    return vite_asset_manager()->devServerReachable();
}

/**
 * Retourne les balises HTML necessaires pour charger Vite.
 *
 * En dev :
 * - charge @vite/client
 * - charge l'entree TypeScript/JS directement depuis le serveur Vite
 *
 * En production :
 * - charge les fichiers references par le manifest buildé
 */
function vite_tags(string $entry = 'src/js/main.ts'): string
{
    $nonce = $GLOBALS['csp_nonce'] ?? '';
    return vite_asset_manager()->tags($entry, is_string($nonce) ? $nonce : '');
}

function vite_asset(string $entry): string
{
    return vite_asset_manager()->assetUrl($entry);
}

/**
 * @return array<int, string>
 */
function vite_css(string $entry): array
{
    return vite_asset_manager()->cssUrls($entry);
}

function db_table(string $name): string
{
    $prefix = defined('DB_TABLE_PREFIX') ? DB_TABLE_PREFIX : 'car_';
    return $prefix . ltrim($name, '_');
}
