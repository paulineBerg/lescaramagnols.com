<?php

declare(strict_types=1);

namespace Caramagnols\Http;

use Caramagnols\Admin\AdminController;
use Caramagnols\Admin\AdminRouteResolver;
use Caramagnols\Blog\BlogApiController;
use Caramagnols\Blog\BlogDiscussionApiController;
use Caramagnols\Blog\BlogSaveService;
use Caramagnols\Feed\RssFeedService;
use Caramagnols\Feed\SitemapService;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PbGestion\Api\PbGestionAgentApiController;
use Caramagnols\PbGestion\Persistence\PbGestionRepository;
use Caramagnols\PrivateApps\WebDevelopment\Http\PreviewGatewayController;
use Caramagnols\PrivateApps\WebDevelopment\Repository\PreviewSessionRepository;
use Caramagnols\PrivateApps\WebDevelopment\Repository\PreviewTicketRepository;
use Caramagnols\PrivateApps\WebDevelopment\Repository\WebDevelopmentProjectRepository;
use Caramagnols\PrivateApps\WebDevelopment\Security\PreviewAccessGuard;
use Caramagnols\PrivateApps\WebDevelopment\Service\PreviewFileService;
use Caramagnols\PrivateApps\Documents\PrivateDocumentRepository;
use Caramagnols\PrivateApps\Documents\PrivateDocumentStorage;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use Caramagnols\PrivatePortal\Http\PrivateHttpBoundary;
use Caramagnols\PrivatePortal\Http\PrivatePortalController;
use Caramagnols\PrivatePortal\Http\PrivateRouteResolver;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivateSession;
use Caramagnols\Seo\SeoUrlNormalizer;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;

final class FrontController
{
    private Dispatcher $dispatcher;
    private AppEventLogger $eventLogger;
    private ?PrivateHttpBoundary $privateHttpBoundary;
    private PreviewGatewayController $previewGateway;

    public function __construct(
        private readonly AdminRouteResolver $adminRouteResolver,
        private readonly AdminController $adminController,
        private readonly BlogApiController $blogApiController,
        ?AppEventLogger $eventLogger = null,
        ?PrivateRouteResolver $privateRouteResolver = null,
        ?PrivatePortalController $privatePortalController = null,
        ?PrivateHttpBoundary $privateHttpBoundary = null,
        ?PreviewGatewayController $previewGateway = null
    ) {
        $this->eventLogger = $eventLogger ?? app_event_logger();
        $privateEnabled = private_portal_enabled() || $privatePortalController instanceof PrivatePortalController;
        $privateRouteResolver ??= private_route_resolver();
        $this->privateHttpBoundary = $privateHttpBoundary ?? new PrivateHttpBoundary(
            $privateRouteResolver,
            $privateEnabled ? ($privatePortalController ?? $this->createPrivatePortalController()) : null,
            $privateEnabled,
            null,
            null,
            $this->eventLogger
        );
        $this->previewGateway = $previewGateway ?? $this->createPreviewGatewayController();

        $this->dispatcher = \FastRoute\simpleDispatcher(function (RouteCollector $routes): void {
            $routes->addRoute('GET', '/core/api/lang.php', ['type' => 'api-lang']);
            $routes->addRoute('POST', '/core/blog/save_article.php', ['type' => 'blog', 'action' => 'save_article']);
            $routes->addRoute('POST', '/core/blog/submit_discussion.php', ['type' => 'blog', 'action' => 'submit_discussion']);
            $routes->addRoute('POST', '/core/blog/log_discussion_client.php', ['type' => 'blog', 'action' => 'log_discussion_client']);
            $routes->addRoute('POST', '/api/pbgestion/v1/enrollment/claim', ['type' => 'pbgestion_agent_api', 'action' => 'enrollment_claim']);
            $routes->addRoute('POST', '/api/pbgestion/v1/sync', ['type' => 'pbgestion_agent_api', 'action' => 'sync']);
            $routes->addRoute('POST', '/api/pbgestion/v1/commands/poll', ['type' => 'pbgestion_agent_api', 'action' => 'commands_poll']);
            $routes->addRoute('POST', '/api/pbgestion/v1/commands/ack', ['type' => 'pbgestion_agent_api', 'action' => 'commands_ack']);
            $routes->addRoute('POST', '/api/pbgestion/v1/details/upload', ['type' => 'pbgestion_agent_api', 'action' => 'details_upload']);
            $routes->addRoute('POST', '/api/pbgestion/v1/recovery/prepare', ['type' => 'pbgestion_agent_api', 'action' => 'recovery_prepare']);

            $routes->addRoute('GET', '/rss', ['type' => 'rss']);
            $routes->addRoute('GET', '/rss.php', ['type' => 'redirect', 'location' => '/rss', 'status' => 301]);
            $routes->addRoute('GET', '/assets/rss.php', ['type' => 'redirect', 'location' => '/rss', 'status' => 301]);
            $routes->addRoute('GET', '/sitemap.xml', ['type' => 'sitemap']);
            $routes->addRoute('GET', '/robots.txt', ['type' => 'robots']);

            foreach ($this->adminRouteResolver->routeDefinitions() as $definition) {
                foreach ($definition['methods'] as $method) {
                    $routes->addRoute($method, $definition['path'], $definition['handler']);
                }
            }

            if ($this->privateHttpBoundary instanceof PrivateHttpBoundary) {
                foreach ($this->privateHttpBoundary->routeDefinitions() as $definition) {
                    foreach ($definition['methods'] as $method) {
                        $routes->addRoute($method, $definition['path'], $definition['handler']);
                    }
                }
            }
        });
    }

    public static function boot(): self
    {
        $adminRouteResolver = admin_route_resolver();

        return new self(
            $adminRouteResolver,
            new AdminController($adminRouteResolver),
            new BlogApiController(
                new BlogSaveService(blog_repository(), app_event_logger()),
                app_event_logger()
            ),
            app_event_logger()
        );
    }

    public function handle(Request $request): Response
    {
        $requestId = $this->resolveRequestId($request);
        app_request_id($requestId);
        app_request_context_set($this->requestContext($request, $requestId, $this->resolveCorrelationId($request, $requestId)));
        $legacyRequestState = $this->captureLegacyRequestGlobals();
        $this->applyLegacyRequestGlobals($request);

        try {
            try {
                $response = $this->dispatch($request);
            } catch (\Throwable $exception) {
                $this->logRequestError($request, $exception);
                $response = $this->internalServerErrorResponse();
            }

            $response->headers['X-Request-Id'] = $requestId;

            return $response;
        } finally {
            $this->restoreLegacyRequestGlobals($legacyRequestState);
            app_request_context_clear();
        }
    }

    private function dispatch(Request $request): Response
    {
        if ($this->previewGateway->matchesPreviewHost($request)) {
            return $this->previewGateway->handle($request);
        }

        $canonicalRedirect = $this->canonicalRedirectResponse($request);
        if ($canonicalRedirect !== null) {
            return $canonicalRedirect;
        }

        $missingImageFallback = $this->missingImageFallbackResponse($request);
        if ($missingImageFallback !== null) {
            return $missingImageFallback;
        }

        $routeInfo = $this->dispatcher->dispatch($request->method(), request_path($request->uri()));

        if ($routeInfo[0] === Dispatcher::FOUND) {
            $handler = $routeInfo[1];
            $routeVars = is_array($routeInfo[2] ?? null) ? $routeInfo[2] : [];

            if (is_array($handler) && ($handler['type'] ?? null) === 'api-lang') {
                return $this->languageApiResponse($request);
            }

            if (is_array($handler) && ($handler['type'] ?? null) === 'admin') {
                return $this->adminController->handle((string) ($handler['page'] ?? 'login'), $request, $routeVars);
            }

            if (is_array($handler) && ($handler['type'] ?? null) === 'rss') {
                $rssService = new RssFeedService(
                    blog_repository(),
                    app_base_url($request),
                    site_available_languages(),
                    (string) app_config('default_lang', 'fr')
                );

                $query = $this->queryParameters($request);
                $language = is_string($query['lang'] ?? null) ? $query['lang'] : null;

                return $rssService->response($language);
            }

            if (is_array($handler) && ($handler['type'] ?? null) === 'sitemap') {
                $sitemapService = new SitemapService(
                    page_repository(pages_data_path()),
                    blog_repository(),
                    app_base_url($request),
                    site_available_languages(),
                    (string) app_config('default_lang', 'fr')
                );

                return $sitemapService->response();
            }

            if (is_array($handler) && ($handler['type'] ?? null) === 'robots') {
                return $this->robotsTxtResponse($request);
            }

            if (is_array($handler) && ($handler['type'] ?? null) === 'blog') {
                $action = (string) ($handler['action'] ?? '');

                if ($action === 'save_article') {
                    return $this->blogApiController->saveArticle($request);
                }

                if ($action === 'submit_discussion') {
                    return (new BlogDiscussionApiController(
                        blog_repository(),
                        blog_discussion_repository(),
                        null,
                        $this->eventLogger
                    ))->submit($request);
                }

                if ($action === 'log_discussion_client') {
                    return (new BlogDiscussionApiController(
                        blog_repository(),
                        blog_discussion_repository(),
                        null,
                        $this->eventLogger
                    ))->logClientEvent($request);
                }

                return Response::json(['error' => 'Action blog inconnue.'], 404);
            }

            if (is_array($handler) && ($handler['type'] ?? null) === 'pbgestion_agent_api') {
                return $this->createPbGestionAgentApiController()->handle(
                    (string) ($handler['action'] ?? ''),
                    $request
                );
            }

            if (
                is_array($handler)
                && $this->privateHttpBoundary instanceof PrivateHttpBoundary
                && $this->privateHttpBoundary->ownsHandler($handler)
            ) {
                return $this->privateHttpBoundary->handle($handler, $request, $routeVars);
            }

            if (is_array($handler) && ($handler['type'] ?? null) === 'redirect') {
                $queryString = parse_url($request->uri(), PHP_URL_QUERY);
                $location = (string) ($handler['location'] ?? '/');

                foreach ($routeVars as $key => $value) {
                    if (!is_string($key) || !is_scalar($value)) {
                        continue;
                    }

                    $location = str_replace('{' . $key . '}', rawurlencode((string) $value), $location);
                }

                if (is_string($queryString) && $queryString !== '') {
                    $location .= '?' . $queryString;
                }

                return new Response((int) ($handler['status'] ?? 302), ['Location' => $location], '');
            }
        }

        if (
            $this->privateHttpBoundary instanceof PrivateHttpBoundary
            && $this->privateHttpBoundary->matchesPrivatePath($request)
        ) {
            return $this->privateHttpBoundary->disabledResponse();
        }

        return $this->pageResponse($request);
    }

    private function createPrivatePortalController(): PrivatePortalController
    {
        $privateUserRepository = new PrivateUserRepository(editorial_database());
        $privateModulePermissionRepository = new PrivateModulePermissionRepository(
            editorial_database(),
            new PrivateModuleRegistry()
        );

        return new PrivatePortalController(
            new PrivateAuth(
                new PrivateSession(
                    (string) app_config('private.session_name', 'caramagnols_private')
                ),
                $this->eventLogger,
                $privateUserRepository
            ),
            null,
            $this->eventLogger,
            $privateUserRepository,
            $privateModulePermissionRepository,
            new PrivateDocumentRepository(editorial_database()),
            PrivateDocumentStorage::fromAppConfig($this->eventLogger)
        );
    }

    private function createPreviewGatewayController(): PreviewGatewayController
    {
        $database = editorial_database();
        $projectRepository = new WebDevelopmentProjectRepository($database);

        return new PreviewGatewayController(
            (string) app_config('web_development.preview_host', ''),
            new PreviewAccessGuard(
                $projectRepository,
                new PreviewTicketRepository($database),
                new PreviewSessionRepository($database),
                (string) app_config('web_development.preview_session_name', 'caramagnols_preview'),
                (int) app_config('web_development.preview_session_ttl_seconds', 14400)
            ),
            new PreviewFileService((string) app_config('web_development.deployments_root', '')),
            $projectRepository
        );
    }

    private function createPbGestionAgentApiController(): PbGestionAgentApiController
    {
        return new PbGestionAgentApiController(
            new PbGestionRepository(editorial_database()),
            $this->eventLogger
        );
    }

    private function canonicalRedirectResponse(Request $request): ?Response
    {
        if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
            return null;
        }

        $currentPath = request_path($request->uri());
        $normalizedPath = normalize_public_route($currentPath);

        $blogArticlePath = is_string($normalizedPath) && $normalizedPath !== ''
            ? $normalizedPath
            : $currentPath;
        $blogArticleRedirect = $this->blogArticleCanonicalRedirectResponse($request, $blogArticlePath);
        if ($blogArticleRedirect !== null) {
            return $blogArticleRedirect;
        }

        if (!is_string($normalizedPath) || $normalizedPath === $currentPath || $normalizedPath === '') {
            return null;
        }

        if ($this->isLegacyImagePath($currentPath)) {
            if (PublicUrlNormalizer::publicPathExists($normalizedPath)) {
                return $this->redirectResponse($request, $normalizedPath, 301);
            }

            return $this->redirectResponse($request, PublicUrlNormalizer::missingImagePlaceholderPath(), 302, false);
        }

        if ($this->isKnownPublicPath($normalizedPath)) {
            return $this->redirectResponse($request, $normalizedPath, 301);
        }

        return null;
    }

    private function blogArticleCanonicalRedirectResponse(Request $request, string $path): ?Response
    {
        if (
            preg_match('#^/(?:([a-z]{2}(?:-[a-z]{2})?)/)?blog/article/([^/]+)$#i', $path, $matches) !== 1
        ) {
            return null;
        }

        $defaultLanguage = (string) app_config('default_lang', 'fr');
        $requestedLanguage = strtolower(trim((string) $matches[1]));
        $language = in_array($requestedLanguage, site_available_languages(), true)
            ? $requestedLanguage
            : $defaultLanguage;
        $slug = strtolower(trim((string) $matches[2]));
        $slug = preg_replace('/[^a-z0-9-]+/i', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        if ($slug === '') {
            return null;
        }

        $repository = blog_repository();
        $article = $repository->findPublished($slug, $language);
        if (!is_array($article) && $language !== $defaultLanguage) {
            $article = $repository->findPublished($slug, $defaultLanguage);
        }

        if (!is_array($article)) {
            return null;
        }

        $resolver = new \Caramagnols\Blog\BlogPublicUrlResolver(
            $repository,
            page_repository(pages_data_path()),
            $defaultLanguage
        );
        $canonicalPath = $resolver->attachedPathForArticle($article);

        if ($canonicalPath === null) {
            return null;
        }

        return $this->redirectResponse($request, $canonicalPath, 301, false);
    }

    private function missingImageFallbackResponse(Request $request): ?Response
    {
        if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
            return null;
        }

        $currentPath = request_path($request->uri());
        if (!$this->isManagedImagePath($currentPath) || PublicUrlNormalizer::publicPathExists($currentPath)) {
            return null;
        }

        $fallbackPath = PublicUrlNormalizer::missingImagePlaceholderPath();
        if ($currentPath === $fallbackPath || !PublicUrlNormalizer::publicPathExists($fallbackPath)) {
            return null;
        }

        return $this->redirectResponse($request, $fallbackPath, 302, false);
    }

    private function redirectResponse(Request $request, string $path, int $status, bool $preserveQueryString = true): Response
    {
        $location = $path;
        if ($preserveQueryString) {
            $queryString = parse_url($request->uri(), PHP_URL_QUERY);
            if (is_string($queryString) && $queryString !== '') {
                $location .= '?' . $queryString;
            }
        }

        return new Response($status, ['Location' => $location], '');
    }

    private function isKnownPublicPath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($this->dispatcher->dispatch('GET', $path)[0] === Dispatcher::FOUND) {
            return true;
        }

        return resolve_route($path) !== 'pages/404.php';
    }

    private function isLegacyImagePath(string $path): bool
    {
        return preg_match('#^/(?:images|structure/images)/.+$#i', $path) === 1;
    }

    private function isManagedImagePath(string $path): bool
    {
        return preg_match(
            '#^/(?:assets/images|images|structure/images|uploads/editorial)/.+\.(?:avif|gif|jpe?g|png|svg|webp)$#i',
            $path
        ) === 1;
    }

    private function robotsTxtResponse(Request $request): Response
    {
        if ($this->shouldNoindexAll($request)) {
            return new Response(
                200,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
                implode(PHP_EOL, ['User-agent: *', 'Disallow: /']) . PHP_EOL
            );
        }

        $lines = [
            'User-agent: *',
            'Allow: /',
        ];
        $lines[] = 'Sitemap: ' . app_url('/sitemap.xml', $request);

        return new Response(
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
            implode(PHP_EOL, $lines) . PHP_EOL
        );
    }

    private function shouldNoindexAll(Request $request): bool
    {
        $host = trim((string) ($request->header('Host') ?? $request->server('HTTP_HOST', '')));
        if ($host !== '' && preg_match('/^\[(.+)\](?::\d+)?$/', $host, $matches) === 1) {
            $host = (string) $matches[1];
        } elseif (substr_count($host, ':') === 1) {
            [$candidateHost, $candidatePort] = array_pad(explode(':', $host, 2), 2, '');
            if ($candidateHost !== '' && ctype_digit($candidatePort)) {
                $host = $candidateHost;
            }
        }

        $host = strtolower(trim($host));
        if (in_array($host, ['preprod.lescaramagnols.com', 'www.preprod.lescaramagnols.com'], true)) {
            return true;
        }

        return (function_exists('env_bool') && (env_bool('PREPROD_NOINDEX', false) || env_bool('NOINDEX_ALL', false)));
    }

    private function languageApiResponse(Request $request): Response
    {
        require_once ROOT_PATH . '/core/api/lang.php';

        $query = $this->queryParameters($request);
        $language = is_string($query['lang'] ?? null) ? $query['lang'] : null;

        return lang_api_response($language, $request->header('If-None-Match'));
    }

    private function pageResponse(Request $request): Response
    {
        $startedAt = microtime(true);
        $currentPath = request_path($request->uri());
        if ($request->method() === 'GET' && $currentPath === '/accueil/bienvenue-aux-caramagnols.php') {
            $queryString = parse_url($request->uri(), PHP_URL_QUERY);
            $location = '/';

            if (is_string($queryString) && $queryString !== '') {
                $location .= '?' . $queryString;
            }

            return new Response(301, ['Location' => $location], '');
        }

        $normalizedPath = normalize_public_route($currentPath);

        if (
            $request->method() === 'GET'
            && is_string($normalizedPath)
            && $normalizedPath !== $currentPath
            && resolve_route($normalizedPath) !== 'pages/404.php'
        ) {
            $queryString = parse_url($request->uri(), PHP_URL_QUERY);
            $location = $normalizedPath;

            if (is_string($queryString) && $queryString !== '') {
                $location .= '?' . $queryString;
            }

            return new Response(301, ['Location' => $location], '');
        }

        $pageFile = resolve_route($request->uri());
        $pagePath = TEMPLATES_PATH . '/' . $pageFile;
        $isNotFound = $pageFile === 'pages/404.php';

        if ($isNotFound || !file_exists($pagePath)) {
            $pagePath = TEMPLATES_PATH . '/pages/404.php';
            $pageTitle = (string) t('TXT_PAGE_NOT_FOUND_TITLE');
            $statusCode = 404;
        } else {
            $pageTitle = (string) t('TXT_PAGE_DEFAULT_TITLE');
            $statusCode = 200;
        }

        $pageMetaDescription = null;
        unset($GLOBALS['pageCanonicalUrl'], $GLOBALS['currentDynamicOpenArticle']);

        $pageBufferLevel = ob_get_level();
        ob_start();
        try {
            include $pagePath;
            $content = ob_get_clean();
        } catch (\Throwable $exception) {
            $this->discardOutputBuffers($pageBufferLevel);
            throw $exception;
        }

        if (!isset($pageCanonicalUrl) || trim((string) $pageCanonicalUrl) === '') {
            $pageCanonicalUrl = $this->resolveDynamicAttachedArticleCanonicalUrl($request);
        }
        if (is_string($pageCanonicalUrl ?? null) && trim($pageCanonicalUrl) !== '') {
            $pageCanonicalUrl = SeoUrlNormalizer::withoutFragment((string) $pageCanonicalUrl);
            $GLOBALS['pageCanonicalUrl'] = $pageCanonicalUrl;
        }

        $layoutBufferLevel = ob_get_level();
        ob_start();
        try {
            require TEMPLATES_PATH . '/partials/layout.php';
            $body = ob_get_clean();
        } catch (\Throwable $exception) {
            $this->discardOutputBuffers($layoutBufferLevel);
            throw $exception;
        }

        $response = new Response($statusCode, [], (string) $body);
        unset($GLOBALS['pageCanonicalUrl'], $GLOBALS['currentDynamicOpenArticle']);
        $this->logPageVisit($request, $response, $pageFile, $isNotFound, (microtime(true) - $startedAt) * 1000);

        return $response;
    }

    private function internalServerErrorResponse(): Response
    {
        $title = function_exists('t')
            ? (string) t('TXT_PAGE_INTERNAL_ERROR_TITLE')
            : 'Erreur interne';
        $message = function_exists('t')
            ? (string) t('TXT_PAGE_INTERNAL_ERROR_BODY')
            : 'Impossible actuellement de traiter cette demande.';

        $body = implode('', [
            '<!DOCTYPE html>',
            '<html lang="', htmlspecialchars((string) (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr'), ENT_QUOTES, 'UTF-8'), '">',
            '<head>',
            '<meta charset="utf-8">',
            '<meta name="viewport" content="width=device-width, initial-scale=1">',
            '<title>', htmlspecialchars($title, ENT_QUOTES, 'UTF-8'), '</title>',
            '</head>',
            '<body>',
            '<main>',
            '<h1>', htmlspecialchars($title, ENT_QUOTES, 'UTF-8'), '</h1>',
            '<p>', htmlspecialchars($message, ENT_QUOTES, 'UTF-8'), '</p>',
            '</main>',
            '</body>',
            '</html>',
        ]);

        return new Response(500, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
    }

    private function logPageVisit(
        Request $request,
        Response $response,
        string $pageFile,
        bool $isNotFound,
        float $renderMs
    ): void {
        if ($request->method() !== 'GET') {
            return;
        }

        $clientIp = $request->clientIp((bool) app_config('admin.trust_proxy_headers', false)) ?? 'unknown';
        $userAgent = trim((string) ($request->header('User-Agent') ?? ''));
        $referer = trim((string) ($request->header('Referer') ?? $request->header('Referrer') ?? ''));
        $queryString = parse_url($request->uri(), PHP_URL_QUERY);
        $visitorSalt = (string) app_config('admin.session_key', 'caramagnols');
        $visitorId = hash('sha256', $clientIp . '|' . $userAgent . '|' . $visitorSalt);

        $this->eventLogger->access(
            $isNotFound ? 'site.visit.not_found' : 'site.visit.page',
            [
                'ip' => $clientIp,
                'method' => $request->method(),
                'uri' => $request->uri(),
                'path' => request_path($request->uri()),
                'status' => $response->status,
                'template' => $pageFile,
                'query' => is_string($queryString) ? $queryString : '',
                'referer' => $referer,
                'user_agent' => $userAgent,
                'visitor_id' => substr($visitorId, 0, 16),
                'render_ms' => round($renderMs, 2),
            ],
            $isNotFound ? 'warning' : 'info'
        );
    }

    private function logRequestError(Request $request, \Throwable $exception): void
    {
        $clientIp = $request->clientIp((bool) app_config('admin.trust_proxy_headers', false)) ?? 'unknown';
        $userAgent = trim((string) ($request->header('User-Agent') ?? ''));
        $referer = trim((string) ($request->header('Referer') ?? $request->header('Referrer') ?? ''));
        $queryString = parse_url($request->uri(), PHP_URL_QUERY);

        $this->eventLogger->access(
            'site.request.error',
            [
                'ip' => $clientIp,
                'method' => $request->method(),
                'uri' => $request->uri(),
                'path' => request_path($request->uri()),
                'status' => 500,
                'query' => is_string($queryString) ? $queryString : '',
                'referer' => $referer,
                'user_agent' => $userAgent,
                'exception' => $exception::class,
                'error' => trim($exception->getMessage()),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ],
            'error'
        );
    }

    private function discardOutputBuffers(int $targetLevel): void
    {
        while (ob_get_level() > $targetLevel) {
            ob_end_clean();
        }
    }

    /**
     * @return array{server: array<string, mixed>, get: array<string, mixed>, post: array<string, mixed>, cookie: array<string, mixed>, files: array<string, mixed>}
     */
    private function captureLegacyRequestGlobals(): array
    {
        return [
            'server' => $_SERVER,
            'get' => $_GET,
            'post' => $_POST,
            'cookie' => $_COOKIE,
            'files' => $_FILES,
        ];
    }

    private function applyLegacyRequestGlobals(Request $request): void
    {
        $_GET = $this->queryParameters($request);
        $_POST = $request->body();
        $_COOKIE = $request->cookies();
        $_FILES = $request->files();

        $_SERVER['REQUEST_METHOD'] = $request->method();
        $_SERVER['REQUEST_URI'] = $request->uri();
        $_SERVER['QUERY_STRING'] = (string) (parse_url($request->uri(), PHP_URL_QUERY) ?? '');

        $this->applyOptionalServerValue('REMOTE_ADDR', $request->server('REMOTE_ADDR'));
        $this->applyOptionalServerValue('SERVER_PORT', $request->server('SERVER_PORT'));
        $this->applyOptionalServerValue('HTTPS', $request->server('HTTPS'));
        $this->applyOptionalServerValue('HTTP_HOST', $request->header('Host'));
        $this->applyOptionalServerValue('HTTP_X_FORWARDED_PROTO', $request->header('X-Forwarded-Proto'));
        $this->applyOptionalServerValue('HTTP_X_FORWARDED_FOR', $request->header('X-Forwarded-For'));
        $this->applyOptionalServerValue('HTTP_X_REAL_IP', $request->header('X-Real-IP'));
    }

    /**
     * @param array{server: array<string, mixed>, get: array<string, mixed>, post: array<string, mixed>, cookie: array<string, mixed>, files: array<string, mixed>} $state
     */
    private function restoreLegacyRequestGlobals(array $state): void
    {
        $_SERVER = $state['server'];
        $_GET = $state['get'];
        $_POST = $state['post'];
        $_COOKIE = $state['cookie'];
        $_FILES = $state['files'];
    }

    private function applyOptionalServerValue(string $key, mixed $value): void
    {
        if ($value === null) {
            unset($_SERVER[$key]);
            return;
        }

        if (is_scalar($value)) {
            $_SERVER[$key] = (string) $value;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function queryParameters(Request $request): array
    {
        $query = $request->query();
        if ($query !== []) {
            return $query;
        }

        $queryString = parse_url($request->uri(), PHP_URL_QUERY);
        if (!is_string($queryString) || $queryString === '') {
            return [];
        }

        parse_str($queryString, $query);

        return is_array($query) ? $query : [];
    }

    private function resolveDynamicAttachedArticleCanonicalUrl(Request $request): ?string
    {
        $query = $this->queryParameters($request);
        $openArticleSlug = is_string($query['open_article'] ?? null) ? trim((string) $query['open_article']) : '';
        $openArticleSlug = preg_replace('/[^a-z0-9-]+/i', '-', strtolower($openArticleSlug)) ?? '';
        $openArticleSlug = trim($openArticleSlug, '-');

        $page = $GLOBALS['currentDynamicPage'] ?? null;
        if (!is_array($page) || $openArticleSlug === '') {
            return null;
        }

        $pageSlug = trim((string) ($page['slug'] ?? ''));
        $pageRoute = normalize_public_route((string) ($page['route'] ?? ''));
        if ($pageSlug === '' || $pageRoute === null) {
            return null;
        }

        $defaultLanguage = (string) app_config('default_lang', 'fr');
        $language = defined('CURRENT_LANG') ? (string) CURRENT_LANG : $defaultLanguage;
        $repository = blog_repository();
        $article = $repository->findPublished($openArticleSlug, $language);
        if (!is_array($article) && $language !== $defaultLanguage) {
            $article = $repository->findPublished($openArticleSlug, $defaultLanguage);
        }

        if (!is_array($article) || trim((string) ($article['page_slug'] ?? '')) !== $pageSlug) {
            return null;
        }

        $resolver = new \Caramagnols\Blog\BlogPublicUrlResolver(
            $repository,
            page_repository(pages_data_path()),
            $defaultLanguage
        );
        $path = $resolver->attachedPathForPageRoute(
            $openArticleSlug,
            (string) ($article['lang'] ?? $language),
            $pageRoute
        );

        if (!is_string($path) || $path === '') {
            return null;
        }

        return SeoUrlNormalizer::withoutFragment(app_url(ltrim($path, '/'), $request));
    }

    private function resolveRequestId(Request $request): string
    {
        if (!$this->requestIdSourceTrusted($request)) {
            return app_generate_request_id();
        }

        $candidates = [
            $request->header('X-Request-Id'),
        ];

        foreach ($candidates as $candidate) {
            if ($this->validTraceIdentifier($candidate)) {
                return $candidate;
            }
        }

        return app_generate_request_id();
    }

    private function resolveCorrelationId(Request $request, string $requestId): string
    {
        if ($this->requestIdSourceTrusted($request)) {
            $candidate = $request->header('X-Correlation-Id');
            if ($this->validTraceIdentifier($candidate)) {
                return trim((string) $candidate);
            }
        }

        return $requestId;
    }

    private function requestIdSourceTrusted(Request $request): bool
    {
        $trustedSources = app_config('logging.trusted_request_id_sources', []);
        if (!is_array($trustedSources) || $trustedSources === []) {
            return false;
        }

        $clientIp = $request->clientIp((bool) app_config('admin.trust_proxy_headers', false));
        if (function_exists('ip_matches_allowlist')) {
            return ip_matches_allowlist($clientIp, array_values(array_map('strval', $trustedSources)));
        }

        return is_string($clientIp) && in_array($clientIp, $trustedSources, true);
    }

    private function validTraceIdentifier(mixed $candidate): bool
    {
        if (!is_string($candidate)) {
            return false;
        }

        $candidate = trim($candidate);

        return $candidate !== ''
            && strlen($candidate) <= 128
            && preg_match('/^[A-Za-z0-9._:-]+$/', $candidate) === 1;
    }

    /**
     * @return array<string, scalar|null>
     */
    private function requestContext(Request $request, string $requestId, string $correlationId): array
    {
        return [
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'method' => $request->method(),
            'uri' => $request->uri(),
            'path' => request_path($request->uri()),
            'client_ip' => $request->clientIp((bool) app_config('admin.trust_proxy_headers', false)) ?? 'unknown',
        ];
    }
}
