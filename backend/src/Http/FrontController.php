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
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;

final class FrontController
{
    private Dispatcher $dispatcher;
    private AppEventLogger $eventLogger;

    public function __construct(
        private readonly AdminRouteResolver $adminRouteResolver,
        private readonly AdminController $adminController,
        private readonly BlogApiController $blogApiController,
        ?AppEventLogger $eventLogger = null
    ) {
        $this->eventLogger = $eventLogger ?? app_event_logger();
        $this->dispatcher = \FastRoute\simpleDispatcher(function (RouteCollector $routes): void {
            $routes->addRoute('GET', '/core/api/lang.php', ['type' => 'api-lang']);
            $routes->addRoute('POST', '/core/blog/save_article.php', ['type' => 'blog', 'action' => 'save_article']);
            $routes->addRoute('POST', '/core/blog/submit_discussion.php', ['type' => 'blog', 'action' => 'submit_discussion']);

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
        app_request_context_set($this->requestContext($request, $requestId));

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
            app_request_context_clear();
        }
    }

    private function dispatch(Request $request): Response
    {
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

                $query = $request->query();
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
                        app_event_logger()
                    ))->submit($request);
                }

                return Response::json(['error' => 'Action blog inconnue.'], 404);
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

        return $this->pageResponse($request);
    }

    private function canonicalRedirectResponse(Request $request): ?Response
    {
        if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
            return null;
        }

        $currentPath = request_path($request->uri());
        $normalizedPath = normalize_public_route($currentPath);
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
        $adminPath = $this->adminRouteResolver->canonicalPath('login');
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: ' . $adminPath,
            'Sitemap: ' . app_url('/sitemap.xml', $request),
        ];

        return new Response(
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
            implode(PHP_EOL, $lines) . PHP_EOL
        );
    }

    private function languageApiResponse(Request $request): Response
    {
        require_once ROOT_PATH . '/core/api/lang.php';

        $query = $request->query();
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

        $pageBufferLevel = ob_get_level();
        ob_start();
        try {
            include $pagePath;
            $content = ob_get_clean();
        } catch (\Throwable $exception) {
            $this->discardOutputBuffers($pageBufferLevel);
            throw $exception;
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

    private function resolveRequestId(Request $request): string
    {
        $candidates = [
            $request->header('X-Request-Id'),
            $request->header('X-Correlation-Id'),
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            if (strlen($candidate) <= 128 && preg_match('/^[A-Za-z0-9._:-]+$/', $candidate) === 1) {
                return $candidate;
            }
        }

        return app_generate_request_id();
    }

    /**
     * @return array<string, scalar|null>
     */
    private function requestContext(Request $request, string $requestId): array
    {
        return [
            'request_id' => $requestId,
            'method' => $request->method(),
            'uri' => $request->uri(),
            'path' => request_path($request->uri()),
            'client_ip' => $request->clientIp((bool) app_config('admin.trust_proxy_headers', false)) ?? 'unknown',
        ];
    }
}
