<?php

declare(strict_types=1);

namespace Caramagnols\Admin;

use Caramagnols\Blog\BlogSaveService;
use Caramagnols\Content\PageRepository;
use Caramagnols\Cron\CronJobRepository;
use Caramagnols\Http\Request;
use Caramagnols\Http\Response;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;

final class AdminController
{
    private const LIST_FILTER_RESET_QUERY_PARAM = 'reset_filters';

    private AppEventLogger $eventLogger;
    private AdminPageService $pageService;
    private AdminBlogService $blogService;
    private AdminDiscussionService $discussionService;
    private AdminNavigationService $navigationService;
    private AdminSettingsService $settingsService;
    private AdminCronCenterService $cronCenterService;
    private AdminLogService $logService;
    private AdminMediaLibraryService $mediaLibraryService;
    private AdminTileService $tileService;
    private AdminSerializedFormNormalizer $serializedFormNormalizer;
    private AdminEditorialImageService $editorialImageService;
    private AdminPrivateMembersService $privateMembersService;

    public function __construct(
        private readonly AdminRouteResolver $routeResolver,
        ?AppEventLogger $eventLogger = null,
        ?AdminPageService $pageService = null,
        ?AdminNavigationService $navigationService = null,
        ?AdminSettingsService $settingsService = null,
        ?AdminLogService $logService = null,
        ?AdminMediaLibraryService $mediaLibraryService = null,
        ?AdminBlogService $blogService = null,
        ?AdminDiscussionService $discussionService = null,
        ?AdminSerializedFormNormalizer $serializedFormNormalizer = null,
        ?AdminEditorialImageService $editorialImageService = null,
        ?AdminTileService $tileService = null,
        ?AdminPrivateMembersService $privateMembersService = null,
        ?AdminCronCenterService $cronCenterService = null
    ) {
        require_once ROOT_PATH . '/core/auth/admin.php';
        require_once ROOT_PATH . '/core/menu_loader.php';
        require_once ROOT_PATH . '/core/rate_limiter.php';

        $this->eventLogger = $eventLogger ?? app_event_logger();
        $pageRepository = page_repository(pages_data_path());
        $this->pageService = $pageService ?? new AdminPageService(
            $pageRepository,
            site_available_languages(),
            (string) app_config('default_lang', 'fr'),
            navigation_repository(menus_data_path()),
            null,
            tile_repository()
        );
        $this->blogService = $blogService ?? new AdminBlogService(
            blog_repository(),
            new BlogSaveService(blog_repository(), $this->eventLogger, $pageRepository),
            site_available_languages(),
            (string) app_config('default_lang', 'fr'),
            $pageRepository
        );
        $this->discussionService = $discussionService ?? new AdminDiscussionService(
            blog_discussion_repository(),
            blog_repository(),
            site_available_languages(),
            (string) app_config('default_lang', 'fr')
        );
        $this->navigationService = $navigationService ?? new AdminNavigationService(
            navigation_repository(menus_data_path()),
            $pageRepository
        );
        $this->settingsService = $settingsService ?? new AdminSettingsService(
            ROOT_PATH . '/config/database.override.php',
            ROOT_PATH . '/config/admin.override.php',
            $this->eventLogger,
            ROOT_PATH . '/config/site.override.php'
        );
        $this->cronCenterService = $cronCenterService ?? new AdminCronCenterService(
            new CronJobRepository(editorial_database()),
            $this->eventLogger
        );
        $this->logService = $logService ?? new AdminLogService(app_sql_log_store());
        $this->mediaLibraryService = $mediaLibraryService ?? new AdminMediaLibraryService(
            ROOT_PATH . '/public',
            max(1048576, (int) app_config('admin.media.max_upload_bytes', 62914560)),
            max(10485760, (int) app_config('admin.media.max_archive_bytes', 209715200))
        );
        $this->serializedFormNormalizer = $serializedFormNormalizer ?? new AdminSerializedFormNormalizer();
        $this->editorialImageService = $editorialImageService ?? new AdminEditorialImageService(
            ROOT_PATH . '/public',
            max(1048576, (int) app_config('admin.editorial_image.max_upload_bytes', 6291456))
        );
        $this->tileService = $tileService ?? new AdminTileService(
            tile_repository(),
            $pageRepository,
            site_available_languages(),
            (string) app_config('default_lang', 'fr')
        );
        $this->privateMembersService = $privateMembersService ?? new AdminPrivateMembersService(
            new PrivateUserRepository(editorial_database()),
            new PrivateModulePermissionRepository(editorial_database(), new PrivateModuleRegistry()),
            $this->eventLogger
        );
    }

    private function adminInterfaceLanguage(): string
    {
        $configuredLanguage = function_exists('admin_interface_language')
            ? admin_interface_language()
            : (string) app_config('default_lang', 'fr');
        $normalizedLanguage = strtolower(trim((string) $configuredLanguage));
        $availableLanguages = $this->blogService->availableLanguages();

        return in_array($normalizedLanguage, $availableLanguages, true)
            ? $normalizedLanguage
            : (string) ($availableLanguages[0] ?? 'fr');
    }

    private function adminText(string $key, string $fallback = ''): string
    {
        if (function_exists('admin_translate')) {
            return admin_translate($key, $fallback);
        }

        if (!function_exists('t')) {
            return $fallback;
        }

        $translated = t($key);
        if (!is_string($translated) || $translated === '' || $translated === '[[' . $key . ']]') {
            return $fallback;
        }

        return $translated;
    }

    private function adminTextf(string $key, string $fallback, mixed ...$args): string
    {
        return sprintf($this->adminText($key, $fallback), ...$args);
    }

    /**
     * @param array<int, string> $filterKeys
     * @param callable(array<string, mixed>): array<string, mixed> $normalizer
     * @param array<string, mixed> $defaults
     * @return array{filters: array<string, mixed>, resetRequested: bool}
     */
    private function resolveRememberedListFilters(
        Request $request,
        string $scope,
        array $filterKeys,
        callable $normalizer,
        array $defaults
    ): array {
        $query = is_array($request->query()) ? $request->query() : [];

        if ($this->isResetFiltersRequest($query)) {
            $this->clearRememberedListFilters($scope);

            return [
                'filters' => $normalizer([]),
                'resetRequested' => true,
            ];
        }

        $hasExplicitFilters = $this->requestContainsAnyFilterKey($query, $filterKeys);
        $effectiveInput = $query;

        if (!$hasExplicitFilters) {
            $remembered = $this->rememberedListFilters($scope);
            foreach ($filterKeys as $filterKey) {
                if (array_key_exists($filterKey, $effectiveInput) || !array_key_exists($filterKey, $remembered)) {
                    continue;
                }

                $effectiveInput[$filterKey] = $remembered[$filterKey];
            }
        }

        $filters = $normalizer($effectiveInput);

        if (!$hasExplicitFilters) {
            foreach ($filterKeys as $filterKey) {
                if (!array_key_exists($filterKey, $filters) || $filters[$filterKey] !== null) {
                    continue;
                }

                if (!array_key_exists($filterKey, $defaults) || $defaults[$filterKey] === null) {
                    continue;
                }

                $filters[$filterKey] = $defaults[$filterKey];
            }
        }

        if ($hasExplicitFilters) {
            $remembered = [];

            foreach ($filterKeys as $filterKey) {
                $value = $filters[$filterKey] ?? ($defaults[$filterKey] ?? null);
                $defaultValue = $defaults[$filterKey] ?? null;

                if ($value !== $defaultValue) {
                    $remembered[$filterKey] = $value;
                }
            }

            if ($remembered === []) {
                $this->clearRememberedListFilters($scope);
            } else {
                $this->storeRememberedListFilters($scope, $remembered);
            }
        }

        return [
            'filters' => $filters,
            'resetRequested' => false,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<int, string> $filterKeys
     */
    private function requestContainsAnyFilterKey(array $input, array $filterKeys): bool
    {
        foreach ($filterKeys as $filterKey) {
            if (array_key_exists($filterKey, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function isResetFiltersRequest(array $input): bool
    {
        $raw = $input[self::LIST_FILTER_RESET_QUERY_PARAM] ?? null;

        if (is_bool($raw)) {
            return $raw;
        }

        $normalized = strtolower(trim((string) $raw));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function rememberedListFilters(string $scope): array
    {
        $stored = $_SESSION[$this->listFiltersSessionKey($scope)] ?? null;

        return is_array($stored) ? $stored : [];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function storeRememberedListFilters(string $scope, array $filters): void
    {
        $_SESSION[$this->listFiltersSessionKey($scope)] = $filters;
    }

    private function clearRememberedListFilters(string $scope): void
    {
        unset($_SESSION[$this->listFiltersSessionKey($scope)]);
    }

    /**
     * @param array<string, scalar|null> $query
     */
    private function buildFilterResetUrl(string $basePath, array $query = []): string
    {
        $params = $query;
        $params[self::LIST_FILTER_RESET_QUERY_PARAM] = '1';

        return $basePath . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function listFiltersSessionKey(string $scope): string
    {
        $normalizedScope = strtolower((string) preg_replace('/[^a-z0-9_-]+/i', '_', trim($scope)));
        if ($normalizedScope === '') {
            $normalizedScope = 'default';
        }

        return admin_session_key() . '_list_filters_' . $normalizedScope;
    }

    /**
     * @param array<string, mixed> $routeParams
     */
    public function handle(string $page, Request $request, array $routeParams = []): Response
    {
        $response = match ($page) {
            'login' => $this->login($request),
            'dashboard' => $this->dashboard($request),
            'pages' => $this->pages($request),
            'pages_new' => $this->pageEditor($request),
            'articles' => $this->articles($request),
            'discussions' => $this->discussions($request),
            'media' => $this->mediaLibrary($request),
            'tiles' => $this->tiles($request),
            'articles_new' => $this->articleEditor($request),
            'tiles_new' => $this->tileEditor($request),
            'articles_edit' => $this->articleEditor(
                $request,
                is_string($routeParams['slug'] ?? null) ? rawurldecode($routeParams['slug']) : null,
                is_string($routeParams['lang'] ?? null) ? rawurldecode($routeParams['lang']) : null
            ),
            'tiles_edit' => $this->tileEditor(
                $request,
                is_numeric($routeParams['id'] ?? null) ? (int) $routeParams['id'] : null
            ),
            'pages_edit' => $this->pageEditor(
                $request,
                is_string($routeParams['slug'] ?? null) ? rawurldecode($routeParams['slug']) : null
            ),
            'menus' => $this->menus($request),
            'logs' => $this->logs($request),
            'settings' => $this->settings($request),
            'private_members' => $this->privateMembers($request),
            'session_ping' => $this->sessionPing($request),
            'logout' => $this->logout($request),
            default => new Response(404, ['Content-Type' => 'text/html; charset=utf-8'], 'Page admin introuvable.'),
        };

        return $this->withAdminHeaders($response);
    }

    private function login(Request $request): Response
    {
        $networkGuard = $this->guardAdminNetwork($request, 'login');
        if ($networkGuard !== null) {
            return $networkGuard;
        }

        if (admin_is_authenticated()) {
            return $this->redirect($this->routeResolver->canonicalPath('dashboard'));
        }

        $error = null;
        $notice = $this->noticeMessageFromCode(admin_pop_notice_code());
        if ($notice === null && (($request->query()['reauth'] ?? null) === '1')) {
            $notice = $this->noticeMessageFromCode('reauth_required');
        }
        $submittedIdentifier = admin_configured_identifier();
        $totpRequired = admin_totp_should_challenge();
        $passwordRequired = !admin_local_passwordless_localhost_allowed();

        if ($request->method() === 'POST') {
            $body = $request->body();
            $token = $body['csrf_token'] ?? '';
            $identifier = is_string($body['identifier'] ?? null)
                ? $body['identifier']
                : (is_string($body['email'] ?? null) ? $body['email'] : '');
            $password = is_string($body['password'] ?? null) ? $body['password'] : '';
            $totpCode = is_string($body['totp_code'] ?? null) ? $body['totp_code'] : '';
            $submittedIdentifier = $identifier;
            $clientIp = $request->clientIp((bool) app_config('admin.trust_proxy_headers', false)) ?? 'unknown';
            $requestContext = [
                'identifier' => AppEventLogger::maskIdentifier($identifier),
                'ip' => $clientIp,
                'uri' => $request->uri(),
                'method' => $request->method(),
                'user_agent' => (string) ($request->header('User-Agent') ?? ''),
                'referer' => (string) ($request->header('Referer') ?? $request->header('Referrer') ?? ''),
            ];

            if (!admin_validate_csrf_token(is_string($token) ? $token : null)) {
                $this->eventLogger->security(
                    'admin.login.invalid_csrf',
                    $requestContext,
                    'warning'
                );
                $error = $this->adminText('TXT_ADMIN_MESSAGE_SESSION_EXPIRED', 'Session expirée, merci de réessayer.');
            } else {
                $limiter = new \FileRateLimiter(
                    'admin-login:' . $clientIp,
                    (int) app_config('admin.login_rate_limit_attempts', 5),
                    (int) app_config('admin.login_rate_limit_window', 900)
                );

                if (!$limiter->allow()) {
                    $retryAfter = $limiter->retryAfter();
                    $this->eventLogger->security(
                        'admin.login.rate_limited',
                        array_merge($requestContext, ['retry_after' => $retryAfter]),
                        'warning'
                    );
                    $error = $this->adminTextf(
                        'TXT_ADMIN_LOGIN_RATE_LIMITED',
                        'Trop de tentatives de connexion. Merci de réessayer dans %d secondes.',
                        $retryAfter
                    );
                    $body = $this->renderTemplate(
                        'layout.php',
                        [
                            'pageTitle' => $this->adminText('TXT_ADMIN_PAGE_TITLE_LOGIN', 'Connexion Admin · Les Caramagnols'),
                            'error' => $error,
                            'csrfToken' => admin_csrf_token(),
                            'submittedIdentifier' => $submittedIdentifier,
                            'passwordRequired' => $passwordRequired,
                            'loginPath' => $this->routeResolver->loginPath(),
                            'contentTemplate' => 'login.php',
                        ]
                    );

                    return new Response(429, ['Content-Type' => 'text/html; charset=utf-8'], $body);
                }

                if (admin_login($identifier, $password, $totpCode)) {
                    $limiter->clear();

                    $this->eventLogger->security(
                        'admin.login.connected',
                        array_merge(
                            $requestContext,
                            ['actor' => admin_current_masked_identifier()]
                        )
                    );

                    return $this->redirect($this->routeResolver->canonicalPath('dashboard'));
                }

                $limiter->hit();
                $failureReason = admin_pop_login_failure_reason();
                $this->eventLogger->security(
                    'admin.login.failed',
                    array_merge($requestContext, ['reason' => is_string($failureReason) ? $failureReason : 'unknown']),
                    'warning'
                );
                $error = $this->adminText('TXT_ADMIN_LOGIN_INVALID_CREDENTIALS', 'Identifiants invalides.');
            }
        }

        return $this->renderPage(
            'login.php',
            [
                'pageTitle' => $this->adminText('TXT_ADMIN_PAGE_TITLE_LOGIN', 'Connexion Admin · Les Caramagnols'),
                'notice' => $notice,
                'error' => $error,
                'csrfToken' => admin_csrf_token(),
                'submittedIdentifier' => $submittedIdentifier,
                'passwordRequired' => $passwordRequired,
                'loginPath' => $this->routeResolver->loginPath(),
                'totpRequired' => $totpRequired,
            ]
        );
    }

    private function dashboard(Request $request): Response
    {
        $guard = $this->guardAuthenticated($request, 'dashboard');
        if ($guard !== null) {
            return $guard;
        }

        $pageSummary = $this->pageService->dashboardSummary();
        $articleSummary = $this->blogService->dashboardSummary();
        $discussionSummary = $this->discussionService->dashboardSummary();
        $navigationSummary = $this->navigationService->dashboardSummary();

        return $this->renderPage(
            'dashboard.php',
            [
                'pageTitle' => $this->adminText('TXT_ADMIN_PAGE_TITLE_DASHBOARD', 'Tableau de bord'),
                'activeMenu' => 'dashboard',
                'adminPath' => $this->routeResolver->loginPath(),
                'blogMode' => (string) app_config('blog.mode', 'experimental'),
                'blogStorage' => blog_storage_mode(),
                'pageSummary' => $pageSummary,
                'articleSummary' => $articleSummary,
                'discussionSummary' => $discussionSummary,
                'navigationSummary' => $navigationSummary,
                'publishedContentCount' => (int) $pageSummary['published'] + (int) $articleSummary['published'],
                'draftContentCount' => (int) $pageSummary['drafts'] + (int) $articleSummary['drafts'],
            ]
        );
    }

    private function privateMembers(Request $request): Response
    {
        $guard = $this->guardAuthenticated($request, 'private_members');
        if ($guard !== null) {
            return $guard;
        }

        $query = $request->query();
        $statusFilter = is_string($query['status'] ?? null) ? trim((string) $query['status']) : null;
        $search = is_string($query['q'] ?? null) ? trim((string) $query['q']) : '';
        $flash = admin_pop_flash_message();
        $message = null;
        $error = null;
        if (is_array($flash)) {
            if (($flash['type'] ?? '') === 'success') {
                $message = (string) ($flash['message'] ?? '');
            } elseif (($flash['type'] ?? '') === 'error') {
                $error = (string) ($flash['message'] ?? '');
            }
        }

        if ($request->method() === 'POST') {
            $body = is_array($request->body()) ? $request->body() : [];
            $token = $body['csrf_token'] ?? '';

            if (!admin_validate_csrf_token(is_string($token) ? $token : null)) {
                $this->eventLogger->security(
                    'admin.private_members.invalid_csrf',
                    [
                        'uri' => $request->uri(),
                        'actor' => admin_current_masked_identifier(),
                    ],
                    'warning'
                );
                admin_set_flash_message(
                    'error',
                    $this->adminText('TXT_ADMIN_MESSAGE_SESSION_EXPIRED', 'Session expirée, merci de réessayer.')
                );

                return $this->redirect($this->privateMembersRedirectLocation($body));
            }

            $result = $this->privateMembersService->handleAction(
                $body,
                admin_current_identifier(),
                $request->clientIp((bool) app_config('admin.trust_proxy_headers', false)),
                (string) ($request->server('HTTP_USER_AGENT', '') ?? '')
            );
            admin_set_flash_message(
                ($result['success'] ?? false) ? 'success' : 'error',
                (string) (($result['success'] ?? false) ? ($result['message'] ?? '') : ($result['error'] ?? ''))
            );

            return $this->redirect($this->privateMembersRedirectLocation($body));
        }

        $viewModel = $this->privateMembersService->listMembersViewModel($statusFilter, $search);

        return $this->renderPage(
            'private_members_list.php',
            [
                'pageTitle' => $this->adminText('TXT_ADMIN_PAGE_TITLE_PRIVATE_MEMBERS', 'Membres de l’espace privé'),
                'activeMenu' => 'private_members',
                'adminPrivateMembersUrl' => $this->routeResolver->canonicalPath('private_members'),
                'adminPrivatePortalLoginUrl' => private_route_resolver()->canonicalPath('login'),
                'statusFilter' => is_string($viewModel['statusFilter'] ?? null) ? $viewModel['statusFilter'] : '',
                'searchQuery' => is_string($viewModel['searchQuery'] ?? null) ? $viewModel['searchQuery'] : '',
                'privateMembers' => is_array($viewModel['members'] ?? null) ? $viewModel['members'] : [],
                'privateMembersStats' => is_array($viewModel['stats'] ?? null) ? $viewModel['stats'] : [],
                'privateModuleRegistry' => is_array($viewModel['moduleRegistry'] ?? null) ? $viewModel['moduleRegistry'] : [],
                'message' => $message,
                'error' => $error,
                'csrfToken' => admin_csrf_token(),
            ]
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private function privateMembersRedirectLocation(array $body): string
    {
        $location = $this->routeResolver->canonicalPath('private_members');
        $fragment = $this->privateMembersReturnFragment($body['private_member_return_fragment'] ?? null);

        return $fragment !== '' ? $location . '#' . $fragment : $location;
    }

    private function privateMembersReturnFragment(mixed $fragment): string
    {
        if (!is_string($fragment)) {
            return '';
        }

        $normalized = trim($fragment);
        if (!preg_match('/\Aprivate-member-[1-9][0-9]*\z/', $normalized)) {
            return '';
        }

        return $normalized;
    }

    private function pages(Request $request): Response
    {
        $guard = $this->guardAuthenticated($request, 'pages');
        if ($guard !== null) {
            return $guard;
        }

        $query = $request->query();
        $deletedSlug = is_string($query['deleted'] ?? null) ? rawurldecode($query['deleted']) : null;
        $defaultFilters = $this->pageService->normalizeListFilters([]);
        $filterState = $this->resolveRememberedListFilters(
            $request,
            'pages',
            ['status', 'lang', 'q'],
            fn (array $input): array => $this->pageService->normalizeListFilters($input),
            array_merge($defaultFilters, ['lang' => $this->adminInterfaceLanguage()])
        );

        if ($filterState['resetRequested']) {
            return $this->redirect($this->routeResolver->canonicalPath('pages'));
        }

        $filters = $filterState['filters'];
        $statusFilter = is_string($filters['status'] ?? null) ? $filters['status'] : null;
        $languageFilter = is_string($filters['lang'] ?? null) ? $filters['lang'] : null;
        $search = is_string($filters['q'] ?? null) ? $filters['q'] : '';

        return $this->renderPage(
            'pages_list.php',
            [
                'pageTitle' => $this->adminText('TXT_ADMIN_PAGE_TITLE_PAGES', 'Pages du site'),
                'activeMenu' => 'pages',
                'csrfToken' => admin_csrf_token(),
                'availableLanguages' => $this->pageService->availableLanguages(),
                'supportedStatuses' => $this->pageService->supportedStatuses(),
                'statusFilter' => $statusFilter,
                'languageFilter' => $languageFilter,
                'searchQuery' => $search,
                'pages' => $this->pageService->listPages($statusFilter, $languageFilter, $search),
                'createPageUrl' => $this->routeResolver->pageCreatePath(),
                'pagesResetUrl' => $this->buildFilterResetUrl($this->routeResolver->canonicalPath('pages')),
                'message' => $deletedSlug !== null && $deletedSlug !== ''
                    ? $this->adminTextf('TXT_ADMIN_PAGES_DELETED_MESSAGE', 'Page "%s" supprimée avec toutes ses traductions.', $deletedSlug)
                    : null,
            ]
        );
    }

    private function pageEditor(Request $request, ?string $slug = null): Response
    {
        $guard = $this->guardAuthenticated($request, 'pages');
        if ($guard !== null) {
            return $guard;
        }

        $existingForm = $slug !== null ? $this->pageService->formDataForSlug($slug) : null;
        if ($slug !== null && $existingForm === null) {
            return new Response(404, ['Content-Type' => 'text/html; charset=utf-8'], $this->adminText('TXT_ADMIN_PAGE_NOT_FOUND', 'Page admin introuvable.'));
        }

        $message = $request->query()['saved'] ?? null;
        $message = $message === '1' ? $this->adminText('TXT_ADMIN_PAGE_SAVED', 'Page sauvegardée.') : null;
        $error = null;
        $formData = $existingForm ?? $this->pageService->emptyFormData();
        $deleteInfo = $slug !== null ? $this->pageService->deletionInfoForSlug($slug) : ['canDelete' => false, 'references' => []];

        if ($request->method() === 'POST') {
            $body = $this->serializedFormNormalizer->pageEditor(is_array($request->body()) ? $request->body() : []);
            $token = $body['csrf_token'] ?? '';

            if (!admin_validate_csrf_token(is_string($token) ? $token : null)) {
                $this->eventLogger->security(
                    'admin.pages.invalid_csrf',
                    [
                        'uri' => $request->uri(),
                        'actor' => admin_current_masked_identifier(),
                    ],
                    'warning'
                );
                $error = $this->adminText('TXT_ADMIN_MESSAGE_SESSION_EXPIRED', 'Session expirée, merci de réessayer.');
            } else {
                $action = is_string($body['page_action'] ?? null) ? $body['page_action'] : 'save';

                if ($action === 'delete' && $slug !== null) {
                    $confirmDelete = !empty($body['confirm_delete']);
                    if (!$confirmDelete) {
                        $error = $this->adminText('TXT_ADMIN_DELETE_CONFIRM_REQUIRED', 'Merci de confirmer la suppression avant de continuer.');
                    } elseif (($sensitiveGuard = $this->guardSensitiveAction($request, 'pages.delete')) !== null) {
                        return $sensitiveGuard;
                    } else {
                        $result = $this->pageService->delete($slug);
                        $deleteInfo = [
                            'canDelete' => ($result['references'] ?? []) === [],
                            'references' => is_array($result['references'] ?? null) ? $result['references'] : [],
                        ];

                        if ($result['success'] === true) {
                            $this->eventLogger->content(
                                'admin.pages.deleted',
                                [
                                    'actor' => admin_current_masked_identifier(),
                                    'slug' => $slug,
                                ]
                            );

                            $redirectQuery = ['deleted' => $slug];
                            $returnStatus = is_string($body['return_status'] ?? null) ? trim((string) $body['return_status']) : '';
                            if (in_array($returnStatus, $this->pageService->supportedStatuses(), true)) {
                                $redirectQuery['status'] = $returnStatus;
                            }

                            $returnLanguage = is_string($body['return_lang'] ?? null) ? trim((string) $body['return_lang']) : '';
                            if (in_array($returnLanguage, $this->pageService->availableLanguages(), true)) {
                                $redirectQuery['lang'] = $returnLanguage;
                            }

                            $returnSearch = is_string($body['return_q'] ?? null) ? trim((string) $body['return_q']) : '';
                            if ($returnSearch !== '') {
                                $redirectQuery['q'] = $returnSearch;
                            }

                            $location = $this->routeResolver->canonicalPath('pages')
                                . '?'
                                . http_build_query($redirectQuery, '', '&', PHP_QUERY_RFC3986);

                            return $this->redirect($location);
                        }

                        $this->eventLogger->content(
                            'admin.pages.delete_failed',
                            [
                                'actor' => admin_current_masked_identifier(),
                                'slug' => $slug,
                                'error' => (string) ($result['error'] ?? 'unknown'),
                            ],
                            'warning'
                        );
                        $error = (string) ($result['error'] ?? $this->adminText('TXT_ADMIN_PAGES_DELETE_FAILED', 'Impossible de supprimer la page.'));
                    }
                } else {
                    $uploadError = $this->applyUploadedPageSharedMedia($body, $request);
                    if ($uploadError === null) {
                        $uploadError = $this->applyUploadedPageImages($body, $request);
                    }
                    if ($uploadError !== null) {
                        $error = $uploadError;
                    } else {
                        $result = $this->pageService->save($body, $slug);
                        $formData = $result['form'];

                        if ($result['success'] === true && is_string($result['slug'] ?? null)) {
                            $this->eventLogger->content(
                                'admin.pages.saved',
                                [
                                    'actor' => admin_current_masked_identifier(),
                                    'slug' => (string) $result['slug'],
                                    'status' => (string) ($formData['status'] ?? PageRepository::STATUS_DRAFT),
                                ]
                            );

                            return $this->redirect($this->routeResolver->pageEditPath((string) $result['slug']) . '?saved=1');
                        }

                        $this->eventLogger->content(
                            'admin.pages.save_failed',
                            [
                                'actor' => admin_current_masked_identifier(),
                                'slug' => (string) ($formData['slug'] ?? $slug ?? ''),
                            ],
                            'warning'
                        );
                        $error = (string) ($result['error'] ?? $this->adminText('TXT_ADMIN_PAGE_SAVE_FAILED', 'Impossible de sauvegarder la page.'));
                    }
                }
            }
        }

        $currentSlug = trim((string) ($formData['slug'] ?? $slug ?? ''));

        return $this->renderPage(
            'pages_form.php',
            [
                'pageTitle' => $slug === null
                    ? $this->adminText('TXT_ADMIN_PAGE_TITLE_PAGE_NEW', 'Nouvelle page')
                    : $this->adminText('TXT_ADMIN_PAGE_TITLE_PAGE_EDIT', 'Éditer une page'),
                'activeMenu' => 'pages',
                'csrfToken' => admin_csrf_token(),
                'message' => $message,
                'error' => $error,
                'availableLanguages' => $this->pageService->availableLanguages(),
                'supportedStatuses' => $this->pageService->supportedStatuses(),
                'formData' => $formData,
                'isNewPage' => $slug === null,
                'currentSlug' => $currentSlug,
                'currentPageUrl' => $currentSlug !== '' ? $this->routeResolver->pageEditPath($currentSlug) : $this->routeResolver->pageCreatePath(),
                'pagesIndexUrl' => $this->routeResolver->canonicalPath('pages'),
                'deleteInfo' => $deleteInfo,
                'tileSupportEnabled' => $this->pageService->tileSupportEnabled(),
                'tileGroupOptions' => $this->pageService->tileGroupReferenceOptions(),
                'tileGroupCatalog' => $this->pageService->tileGroupCatalogForEditor(),
                'tilePageOptions' => $this->pageService->pageReferenceOptions(),
                'sharedMediaLibrary' => $this->editorialImageService->listUploads('media', 160),
                'contentMediaPicker' => $this->mediaLibraryService->mediaPickerViewModel('page', 260),
            ]
        );
    }

    private function tiles(Request $request): Response
    {
        $guard = $this->guardAuthenticated($request, 'tiles');
        if ($guard !== null) {
            return $guard;
        }

        $query = $request->query();
        $deletedId = is_numeric($query['deleted'] ?? null) ? (int) $query['deleted'] : null;
        $flash = admin_pop_flash_message();
        $message = null;
        $error = null;
        if (is_array($flash)) {
            if (($flash['type'] ?? '') === 'success') {
                $message = (string) ($flash['message'] ?? '');
            } elseif (($flash['type'] ?? '') === 'error') {
                $error = (string) ($flash['message'] ?? '');
            }
        }

        if ($request->method() === 'POST' && $this->tileService->isEnabled()) {
            $body = is_array($request->body()) ? $request->body() : [];
            $token = $body['csrf_token'] ?? '';

            if (!admin_validate_csrf_token(is_string($token) ? $token : null)) {
                $this->eventLogger->security(
                    'admin.tiles.invalid_csrf',
                    [
                        'uri' => $request->uri(),
                        'actor' => admin_current_masked_identifier(),
                    ],
                    'warning'
                );
                $error = $this->adminText('TXT_ADMIN_MESSAGE_SESSION_EXPIRED', 'Session expirée, merci de réessayer.');
            } else {
                $action = is_string($body['tile_list_action'] ?? null)
                    ? trim((string) $body['tile_list_action'])
                    : '';
                $groupId = is_numeric($body['group_id'] ?? null) ? (int) $body['group_id'] : 0;

                if ($action === 'duplicate' && $groupId > 0) {
                    $result = $this->tileService->duplicate($groupId);

                    if (($result['success'] ?? false) === true && is_numeric($result['id'] ?? null)) {
                        $duplicatedGroupId = (int) $result['id'];
                        $this->eventLogger->content(
                            'admin.tiles.duplicated',
                            [
                                'actor' => admin_current_masked_identifier(),
                                'source_group_id' => $groupId,
                                'group_id' => $duplicatedGroupId,
                                'name' => (string) ($result['name'] ?? ''),
                            ]
                        );

                        admin_set_flash_message(
                            'success',
                            $this->tileDuplicateSuccessMessage(
                                $groupId,
                                $duplicatedGroupId,
                                (string) ($result['name'] ?? ''),
                                (int) ($result['tileCount'] ?? 0)
                            )
                        );

                        return $this->redirect($this->routeResolver->canonicalPath('tiles'));
                    }

                    $this->eventLogger->content(
                        'admin.tiles.duplicate_failed',
                        [
                            'actor' => admin_current_masked_identifier(),
                            'source_group_id' => $groupId,
                            'error' => (string) ($result['error'] ?? 'unknown'),
                        ],
                        'warning'
                    );

                    admin_set_flash_message(
                        'error',
                        $this->tileDuplicateErrorMessage(
                            $groupId,
                            (string) ($result['error'] ?? 'Impossible de dupliquer le groupe de tuiles.')
                        )
                    );

                    return $this->redirect($this->routeResolver->canonicalPath('tiles'));
                }
            }
        }

        return $this->renderPage(
            'tiles_list.php',
            [
                'pageTitle' => $this->adminText('TXT_ADMIN_PAGE_TITLE_TILES', 'Groupes de tuiles'),
                'activeMenu' => 'tiles',
                'tilesEnabled' => $this->tileService->isEnabled(),
                'groups' => $this->tileService->listGroups(),
                'createTileUrl' => $this->routeResolver->tileCreatePath(),
                'message' => $message ?? ($deletedId !== null && $deletedId > 0
                    ? $this->adminTextf('TXT_ADMIN_TILES_DELETED_MESSAGE', 'Groupe de tuiles #%d supprimé.', $deletedId)
                    : null),
                'error' => $error,
            ]
        );
    }

    private function tileEditor(Request $request, ?int $groupId = null): Response
    {
        $guard = $this->guardAuthenticated($request, 'tiles');
        if ($guard !== null) {
            return $guard;
        }

        if (!$this->tileService->isEnabled()) {
            return $this->renderPage(
                'tiles_list.php',
                [
                    'pageTitle' => $this->adminText('TXT_ADMIN_PAGE_TITLE_TILES', 'Groupes de tuiles'),
                    'activeMenu' => 'tiles',
                    'tilesEnabled' => false,
                    'groups' => [],
                    'createTileUrl' => $this->routeResolver->tileCreatePath(),
                    'error' => $this->adminText('TXT_ADMIN_TILES_SQL_ONLY', 'Le module Tuiles est disponible uniquement quand l éditorial SQL est actif.'),
                ]
            );
        }

        $existingForm = ($groupId ?? 0) > 0 ? $this->tileService->formDataForGroup((int) $groupId) : null;
        if (($groupId ?? 0) > 0 && $existingForm === null) {
            return new Response(404, ['Content-Type' => 'text/html; charset=utf-8'], $this->adminText('TXT_ADMIN_TILES_GROUP_NOT_FOUND', 'Groupe de tuiles introuvable.'));
        }

        $message = null;
        if (($request->query()['duplicated'] ?? null) === '1') {
            $message = $this->adminText('TXT_ADMIN_TILES_DUPLICATED', 'Groupe de tuiles duplique.');
        } elseif (($request->query()['saved'] ?? null) === '1') {
            $message = $this->adminText('TXT_ADMIN_TILES_SAVED', 'Groupe de tuiles sauvegarde.');
        }
        $error = null;
        $formData = $existingForm ?? $this->tileService->emptyFormData();

        if ($request->method() === 'POST') {
            $body = $this->serializedFormNormalizer->tileEditor(is_array($request->body()) ? $request->body() : []);
            $token = $body['csrf_token'] ?? '';

            if (!admin_validate_csrf_token(is_string($token) ? $token : null)) {
                $this->eventLogger->security(
                    'admin.tiles.invalid_csrf',
                    [
                        'uri' => $request->uri(),
                        'actor' => admin_current_masked_identifier(),
                    ],
                    'warning'
                );
                $error = $this->adminText('TXT_ADMIN_MESSAGE_SESSION_EXPIRED', 'Session expirée, merci de réessayer.');
            } else {
                $action = is_string($body['tile_action'] ?? null) ? trim((string) $body['tile_action']) : 'save';

                if ($action === 'delete' && ($groupId ?? 0) > 0) {
                    $confirmDelete = !empty($body['confirm_delete']);
                    if (!$confirmDelete) {
                        $error = $this->adminText('TXT_ADMIN_DELETE_CONFIRM_REQUIRED', 'Merci de confirmer la suppression avant de continuer.');
                    } elseif (($sensitiveGuard = $this->guardSensitiveAction($request, 'tiles.delete')) !== null) {
                        return $sensitiveGuard;
                    } else {
                        $result = $this->tileService->delete((int) $groupId);
                        if (($result['success'] ?? false) === true) {
                            $this->eventLogger->content(
                                'admin.tiles.deleted',
                                [
                                    'actor' => admin_current_masked_identifier(),
                                    'group_id' => (int) $groupId,
                                ]
                            );

                            return $this->redirect(
                                $this->routeResolver->canonicalPath('tiles')
                                . '?deleted='
                                . rawurlencode((string) $groupId)
                            );
                        }

                        $this->eventLogger->content(
                            'admin.tiles.delete_failed',
                            [
                                'actor' => admin_current_masked_identifier(),
                                'group_id' => (int) $groupId,
                                'error' => (string) ($result['error'] ?? 'unknown'),
                            ],
                            'warning'
                        );
                        $error = (string) ($result['error'] ?? $this->adminText('TXT_ADMIN_TILES_DELETE_FAILED', 'Impossible de supprimer le groupe de tuiles.'));
                    }
                } else {
                    $result = $this->tileService->save($body, $groupId);
                    $formData = $result['form'];

                    if (($result['success'] ?? false) === true && is_numeric($result['id'] ?? null)) {
                        $savedGroupId = (int) $result['id'];
                        $this->eventLogger->content(
                            'admin.tiles.saved',
                            [
                                'actor' => admin_current_masked_identifier(),
                                'group_id' => $savedGroupId,
                                'name' => (string) ($formData['name'] ?? ''),
                            ]
                        );

                        return $this->redirect($this->routeResolver->tileEditPath($savedGroupId) . '?saved=1');
                    }

                    $this->eventLogger->content(
                        'admin.tiles.save_failed',
                        [
                            'actor' => admin_current_masked_identifier(),
                            'group_id' => (int) ($groupId ?? 0),
                            'error' => (string) ($result['error'] ?? 'unknown'),
                        ],
                        'warning'
                    );
                    $error = (string) ($result['error'] ?? $this->adminText('TXT_ADMIN_TILES_SAVE_FAILED', 'Impossible de sauvegarder le groupe de tuiles.'));
                }
            }
        }

        $currentGroupId = max(0, (int) ($formData['id'] ?? $groupId ?? 0));

        return $this->renderPage(
            'tiles_form.php',
            [
                'pageTitle' => $currentGroupId > 0
                    ? $this->adminText('TXT_ADMIN_PAGE_TITLE_TILE_EDIT', 'Éditer un groupe de tuiles')
                    : $this->adminText('TXT_ADMIN_PAGE_TITLE_TILE_NEW', 'Nouveau groupe de tuiles'),
                'activeMenu' => 'tiles',
                'csrfToken' => admin_csrf_token(),
                'message' => $message,
                'error' => $error,
                'formData' => $formData,
                'isNewTileGroup' => $currentGroupId <= 0,
                'currentTileUrl' => $currentGroupId > 0
                    ? $this->routeResolver->tileEditPath($currentGroupId)
                    : $this->routeResolver->tileCreatePath(),
                'tilesIndexUrl' => $this->routeResolver->canonicalPath('tiles'),
                'availableLanguages' => $this->tileService->availableLanguages(),
                'tileThemes' => $this->tileService->availableThemes(),
                'tileSizes' => $this->tileService->availableSizes(),
                'tileColors' => $this->tileService->availableColors(),
                'tilePageOptions' => $this->tileService->pageReferenceOptions(),
                'contentMediaPicker' => $this->mediaLibraryService->mediaPickerViewModel('page', 260),
            ]
        );
    }

    private function articles(Request $request): Response
    {
        $guard = $this->guardAuthenticated($request, 'articles');
        if ($guard !== null) {
            return $guard;
        }

        $query = $request->query();
        $saved = ($query['saved'] ?? null) === '1';
        $deletedSlug = is_string($query['deleted'] ?? null) ? rawurldecode((string) $query['deleted']) : null;
        $deletedLanguage = is_string($query['deleted_lang'] ?? null) ? rawurldecode((string) $query['deleted_lang']) : null;
        $deletedDiscussions = (int) ($query['deleted_discussions'] ?? 0);
        $detachedChildren = (int) ($query['detached_children'] ?? 0);
        $defaultFilters = $this->blogService->normalizeFilters([]);
        $filterState = $this->resolveRememberedListFilters(
            $request,
            'articles',
            ['status', 'scheduled_date', 'category', 'tag', 'q'],
            fn (array $input): array => $this->blogService->normalizeFilters($input),
            $defaultFilters
        );

        if ($filterState['resetRequested']) {
            return $this->redirect($this->routeResolver->canonicalPath('articles'));
        }

        $filters = $filterState['filters'];
        $articles = $this->blogService->listArticles($filters);
        $articleListSummary = $this->blogService->summarizeArticles($articles);
        $articles = $this->blogService->groupArticlesBySlug($articles);
        $message = null;

        if ($saved) {
            $message = $this->adminText('TXT_ADMIN_ARTICLE_SAVED', 'Article sauvegardé.');
        } elseif (is_string($deletedSlug) && $deletedSlug !== '') {
            $languageLabel = is_string($deletedLanguage) && $deletedLanguage !== ''
                ? strtoupper($deletedLanguage)
                : strtoupper((string) $this->adminInterfaceLanguage());
            $message = $this->adminTextf(
                'TXT_ADMIN_ARTICLE_DELETED_MESSAGE',
                'Article "%s" (%s) supprimé. %d discussion(s) associée(s) supprimée(s).',
                $deletedSlug,
                $languageLabel,
                max(0, $deletedDiscussions)
            );

            if ($detachedChildren > 0) {
                $message .= ' ' . $this->adminTextf(
                    'TXT_ADMIN_ARTICLE_CHILDREN_DETACHED',
                    '%d article(s) enfant détaché(s) du parent supprimé.',
                    $detachedChildren
                );
            }
        }

        return $this->renderPage(
            'articles_list.php',
            [
                'pageTitle' => $this->adminText('TXT_ADMIN_PAGE_TITLE_ARTICLES', 'Articles du blog'),
                'activeMenu' => 'articles',
                'filters' => $filters,
                'supportedStatuses' => $this->blogService->supportedStatuses(),
                'availableCategoryOptions' => $this->blogService->availableCategoryOptions($this->adminInterfaceLanguage()),
                'availableTagOptions' => $this->blogService->availableTagOptions($this->adminInterfaceLanguage()),
                'articles' => $articles,
                'articleListSummary' => $articleListSummary,
                'createArticleUrl' => $this->routeResolver->articleCreatePath(),
                'articlesResetUrl' => $this->buildFilterResetUrl($this->routeResolver->canonicalPath('articles')),
                'csrfToken' => admin_csrf_token(),
                'message' => $message,
            ]
        );
    }

    private function articleEditor(Request $request, ?string $slug = null, ?string $language = null): Response
    {
        $guard = $this->guardAuthenticated($request, 'articles');
        if ($guard !== null) {
            return $guard;
        }

        $existingForm = ($slug !== null && $language !== null)
            ? $this->blogService->formDataForArticle($slug, $language)
            : null;
        if ($slug !== null && $language !== null && $existingForm === null) {
            return new Response(404, ['Content-Type' => 'text/html; charset=utf-8'], $this->adminText('TXT_ADMIN_ARTICLE_NOT_FOUND', 'Article admin introuvable.'));
        }

        $message = ($request->query()['saved'] ?? null) === '1'
            ? $this->adminText('TXT_ADMIN_ARTICLE_SAVED', 'Article sauvegardé.')
            : null;
        $error = null;
        $formData = $existingForm ?? $this->blogService->emptyFormData();

        if ($request->method() === 'POST') {
            $body = $request->body();
            $token = $body['csrf_token'] ?? '';
            $action = is_string($body['article_action'] ?? null) ? trim((string) $body['article_action']) : 'save';

            if (!admin_validate_csrf_token(is_string($token) ? $token : null)) {
                $this->eventLogger->security(
                    'admin.articles.invalid_csrf',
                    [
                        'uri' => $request->uri(),
                        'actor' => admin_current_masked_identifier(),
                    ],
                    'warning'
                );
                $error = $this->adminText('TXT_ADMIN_MESSAGE_SESSION_EXPIRED', 'Session expirée, merci de réessayer.');
            } else {
                if ($action === 'delete') {
                    $activeLanguage = '';
                    $articleBody = is_array($body['article'] ?? null) ? $body['article'] : [];
                    if (is_string($articleBody['active_language'] ?? null)) {
                        $candidateLanguage = strtolower(trim((string) $articleBody['active_language']));
                        if (in_array($candidateLanguage, $this->blogService->availableLanguages(), true)) {
                            $activeLanguage = $candidateLanguage;
                        }
                    }

                    if ($slug === null || $language === null) {
                        $error = $this->adminText('TXT_ADMIN_ARTICLE_DELETE_NOT_FOUND', 'Suppression impossible : article introuvable.');
                    } else {
                        $confirmDelete = !empty($body['confirm_delete']);
                        if (!$confirmDelete) {
                            $error = $this->adminText('TXT_ADMIN_DELETE_CONFIRM_REQUIRED', 'Merci de confirmer la suppression avant de continuer.');
                        } elseif (($sensitiveGuard = $this->guardSensitiveAction($request, 'articles.delete')) !== null) {
                            return $sensitiveGuard;
                        } else {
                            $deleteLanguage = $activeLanguage !== '' ? $activeLanguage : $language;
                            $result = $this->blogService->delete($slug, $deleteLanguage);

                            if (($result['success'] ?? false) === true) {
                                $deletedDiscussions = max(0, (int) ($result['deletedDiscussions'] ?? 0));
                                $detachedChildren = max(0, (int) ($result['detachedChildren'] ?? 0));

                                $this->eventLogger->content(
                                    'admin.articles.deleted',
                                    [
                                        'actor' => admin_current_masked_identifier(),
                                        'slug' => $slug,
                                        'lang' => $deleteLanguage,
                                        'deleted_discussions' => $deletedDiscussions,
                                        'detached_children' => $detachedChildren,
                                    ]
                                );

                                $location = $this->routeResolver->canonicalPath('articles')
                                    . '?deleted=' . rawurlencode($slug)
                                    . '&deleted_lang=' . rawurlencode($deleteLanguage)
                                    . '&deleted_discussions=' . $deletedDiscussions
                                    . '&detached_children=' . $detachedChildren;

                                return $this->redirect($location);
                            }

                            $this->eventLogger->content(
                                'admin.articles.delete_failed',
                                [
                                    'actor' => admin_current_masked_identifier(),
                                    'slug' => $slug,
                                    'lang' => $deleteLanguage,
                                    'error' => (string) ($result['error'] ?? 'unknown'),
                                ],
                                'warning'
                            );
                            $error = (string) ($result['error'] ?? $this->adminText('TXT_ADMIN_ARTICLE_DELETE_FAILED', 'Impossible de supprimer l’article.'));
                        }
                    }
                } else {
                    $uploadError = $this->applyUploadedArticleImage($body, $request);
                    if ($uploadError !== null) {
                        $error = $uploadError;
                    } else {
                        $result = $this->blogService->save(
                            is_array($body) ? $body : [],
                            $slug,
                            $language,
                            admin_current_identifier()
                        );
                        $formData = $result['form'];

                        if (($result['success'] ?? false) === true && is_string($result['slug'] ?? null) && is_string($result['lang'] ?? null)) {
                            $this->eventLogger->content(
                                'admin.articles.saved',
                                [
                                    'actor' => admin_current_masked_identifier(),
                                    'slug' => (string) $result['slug'],
                                    'lang' => (string) $result['lang'],
                                    'status' => (string) ($formData['status'] ?? 'draft'),
                                    'category' => (string) ($formData['category'] ?? ''),
                                    'tags' => (string) ($formData['tags_input'] ?? ''),
                                ]
                            );

                            return $this->redirect(
                                $this->routeResolver->articleEditPath((string) $result['slug'], (string) $result['lang']) . '?saved=1'
                            );
                        }

                        $this->eventLogger->content(
                            'admin.articles.save_failed',
                            [
                                'actor' => admin_current_masked_identifier(),
                                'slug' => (string) ($formData['slug'] ?? $slug ?? ''),
                                'lang' => (string) ($formData['active_language'] ?? $formData['lang'] ?? $language ?? ''),
                                'error' => (string) ($result['error'] ?? 'unknown'),
                            ],
                            'warning'
                        );

                        $error = (string) ($result['error'] ?? $this->adminText('TXT_ADMIN_ARTICLE_SAVE_FAILED', 'Impossible de sauvegarder l’article.'));
                    }
                }
            }
        }

        $currentSlug = trim((string) ($formData['slug'] ?? $slug ?? ''));
        $currentLanguage = trim((string) ($formData['active_language'] ?? $formData['lang'] ?? $language ?? app_config('default_lang', 'fr')));
        $existingLanguages = is_array($formData['existing_languages'] ?? null)
            ? array_values(array_map('strval', $formData['existing_languages']))
            : [];
        $currentHierarchyLanguage = in_array($currentLanguage, $existingLanguages, true)
            ? $currentLanguage
            : trim((string) ($language ?? $currentLanguage));

        return $this->renderPage(
            'articles_form.php',
            [
                'pageTitle' => $slug === null
                    ? $this->adminText('TXT_ADMIN_PAGE_TITLE_ARTICLE_NEW', 'Nouvel article')
                    : $this->adminText('TXT_ADMIN_PAGE_TITLE_ARTICLE_EDIT', 'Éditer un article'),
                'activeMenu' => 'articles',
                'csrfToken' => admin_csrf_token(),
                'message' => $message,
                'error' => $error,
                'formData' => $formData,
                'isNewArticle' => $slug === null || $language === null,
                'currentArticleUrl' => $slug !== null && $language !== null
                    ? $this->routeResolver->articleEditPath($slug, $language)
                    : $this->routeResolver->articleCreatePath(),
                'articlesIndexUrl' => $this->routeResolver->canonicalPath('articles'),
                'availableLanguages' => $this->blogService->availableLanguages(),
                'supportedStatuses' => $this->blogService->supportedStatuses(),
                'availableCategoryOptions' => $this->blogService->availableCategoryOptions($currentLanguage),
                'availableSubcategoryOptions' => $this->blogService->availableSubcategoryOptions($currentLanguage),
                'availableTagOptions' => $this->blogService->availableTagOptions($currentLanguage),
                'availablePageOptions' => $this->blogService->availablePageOptions($currentLanguage),
                'availableParentArticles' => $this->blogService->availableParentArticles($currentLanguage, $currentSlug, $currentLanguage),
                'childArticles' => $currentSlug !== '' && $currentHierarchyLanguage !== ''
                    ? $this->blogService->childArticlesForArticle($currentSlug, $currentHierarchyLanguage)
                    : [],
                'contentMediaPicker' => $this->mediaLibraryService->mediaPickerViewModel('article', 260),
            ]
        );
    }

    private function discussions(Request $request): Response
    {
        $guard = $this->guardAuthenticated($request, 'discussions');
        if ($guard !== null) {
            return $guard;
        }

        $message = null;
        $error = null;
        $defaultFilters = $this->discussionService->normalizeFilters([]);
        $filterState = $this->resolveRememberedListFilters(
            $request,
            'discussions',
            ['status', 'lang', 'q'],
            fn (array $input): array => $this->discussionService->normalizeFilters($input),
            $defaultFilters
        );

        if ($filterState['resetRequested']) {
            return $this->redirect($this->routeResolver->canonicalPath('discussions'));
        }

        $filters = $filterState['filters'];

        if ($request->method() === 'POST') {
            $body = is_array($request->body()) ? $request->body() : [];
            $token = $body['csrf_token'] ?? '';
            $filters = $this->discussionService->normalizeFilters($body);

            if (!admin_validate_csrf_token(is_string($token) ? $token : null)) {
                $this->eventLogger->security(
                    'admin.discussions.invalid_csrf',
                    [
                        'uri' => $request->uri(),
                        'actor' => admin_current_masked_identifier(),
                    ],
                    'warning'
                );
                $error = $this->adminText('TXT_ADMIN_MESSAGE_SESSION_EXPIRED', 'Session expirée, merci de réessayer.');
            } else {
                $discussionAction = is_string($body['discussion_action'] ?? null)
                    ? strtolower(trim((string) $body['discussion_action']))
                    : '';
                if ($discussionAction === 'delete' && ($sensitiveGuard = $this->guardSensitiveAction($request, 'discussions.delete')) !== null) {
                    return $sensitiveGuard;
                }

                $result = $this->discussionService->handleAction($body, admin_current_identifier());
                $message = $result['message'];
                $error = $result['error'];

                if (($result['success'] ?? false) === true) {
                    $this->eventLogger->content(
                        'admin.discussions.moderated',
                        [
                            'actor' => admin_current_masked_identifier(),
                            'discussion_id' => (string) ($body['discussion_id'] ?? ''),
                            'action' => (string) ($body['discussion_action'] ?? ''),
                        ]
                    );
                } elseif ($error !== null && $error !== $this->adminText('TXT_ADMIN_MESSAGE_SESSION_EXPIRED', 'Session expirée, merci de réessayer.')) {
                    $this->eventLogger->content(
                        'admin.discussions.moderation_failed',
                        [
                            'actor' => admin_current_masked_identifier(),
                            'discussion_id' => (string) ($body['discussion_id'] ?? ''),
                            'action' => (string) ($body['discussion_action'] ?? ''),
                            'error' => $error,
                        ],
                        'warning'
                    );
                }
            }
        }

        $view = $this->discussionService->viewModel($filters);

        return $this->renderPage(
            'discussions.php',
            [
                'pageTitle' => $this->adminText('TXT_ADMIN_PAGE_TITLE_DISCUSSIONS', 'Discussions du blog'),
                'activeMenu' => 'discussions',
                'csrfToken' => admin_csrf_token(),
                'message' => $message,
                'error' => $error,
                'discussionFilters' => $view['filters'],
                'discussionRows' => $view['rows'],
                'discussionCounts' => $view['counts'],
                'availableLanguages' => $this->discussionService->availableLanguages(),
                'supportedStatuses' => $this->discussionService->supportedStatuses(),
                'discussionsResetUrl' => $this->buildFilterResetUrl($this->routeResolver->canonicalPath('discussions')),
            ]
        );
    }

    private function mediaLibrary(Request $request): Response
    {
        $guard = $this->guardAuthenticated($request, 'media');
        if ($guard !== null) {
            return $guard;
        }

        $query = $request->query();
        $folder = $this->mediaLibraryService->normalizeFolderPath(
            is_string($query['folder'] ?? null) ? (string) $query['folder'] : ''
        );
        $defaultFilters = $this->mediaLibraryService->normalizeFilters([]);
        $filterState = $this->resolveRememberedListFilters(
            $request,
            'media',
            ['q', 'type', 'min_size_kb', 'max_size_kb', 'date_from', 'date_to', 'sort'],
            fn (array $input): array => $this->mediaLibraryService->normalizeFilters($input),
            $defaultFilters
        );

        if ($filterState['resetRequested']) {
            $query = [];
            if ($folder !== '') {
                $query['folder'] = $folder;
            }

            return $this->redirect(
                $query === []
                    ? $this->routeResolver->canonicalPath('media')
                    : $this->routeResolver->canonicalPath('media') . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986)
            );
        }

        $filters = $filterState['filters'];
        $message = null;
        $error = null;

        if ($request->method() === 'POST') {
            $body = is_array($request->body()) ? $request->body() : [];
            $token = $body['csrf_token'] ?? '';
            $folder = $this->mediaLibraryService->normalizeFolderPath(
                is_string($body['folder'] ?? null) ? (string) $body['folder'] : $folder
            );
            $filtersInput = is_array($body['filters'] ?? null) ? $body['filters'] : $body;
            $filters = $this->mediaLibraryService->normalizeFilters(is_array($filtersInput) ? $filtersInput : []);

            if (!admin_validate_csrf_token(is_string($token) ? $token : null)) {
                $this->eventLogger->security(
                    'admin.media.invalid_csrf',
                    [
                        'uri' => $request->uri(),
                        'actor' => admin_current_masked_identifier(),
                    ],
                    'warning'
                );
                $error = $this->adminText('TXT_ADMIN_MESSAGE_SESSION_EXPIRED', 'Session expirée, merci de réessayer.');
            } else {
                $action = is_string($body['media_action'] ?? null)
                    ? strtolower(trim((string) $body['media_action']))
                    : '';
                $maxWidth = max(
                    320,
                    min(
                        8192,
                        is_numeric($body['upload_max_width'] ?? null) ? (int) $body['upload_max_width'] : 2560
                    )
                );
                $maxHeight = max(
                    320,
                    min(
                        8192,
                        is_numeric($body['upload_max_height'] ?? null) ? (int) $body['upload_max_height'] : 2560
                    )
                );
                $quality = max(
                    30,
                    min(
                        100,
                        is_numeric($body['upload_quality'] ?? null) ? (int) $body['upload_quality'] : 82
                    )
                );
                $autoWebp = (string) ($body['upload_auto_webp'] ?? '1') === '1';

                if ($action === 'create_folder') {
                    $result = $this->mediaLibraryService->createFolder(
                        $folder,
                        is_string($body['new_folder_name'] ?? null) ? (string) $body['new_folder_name'] : ''
                    );
                    if (($result['success'] ?? false) === true) {
                        $folder = is_string($result['folder'] ?? null) ? (string) $result['folder'] : $folder;
                        $message = $this->adminText('TXT_ADMIN_MEDIA_FOLDER_CREATED', 'Dossier créé.');
                        $this->eventLogger->content(
                            'admin.media.folder_created',
                            [
                                'actor' => admin_current_masked_identifier(),
                                'folder' => $folder,
                            ]
                        );
                    } else {
                        $error = is_string($result['error'] ?? null) ? (string) $result['error'] : 'Creation du dossier impossible.';
                    }
                } elseif ($action === 'upload') {
                    $files = $this->uploadedFiles($request, 'media_files');
                    $result = $this->mediaLibraryService->uploadFiles(
                        $folder,
                        $files,
                        $autoWebp,
                        $maxWidth,
                        $maxHeight,
                        $quality
                    );
                    if (($result['success'] ?? false) === true) {
                        $message = $this->adminTextf(
                            'TXT_ADMIN_MEDIA_FILES_IMPORTED',
                            '%d fichier(s) importé(s), %d converti(s) en WebP, %d ignore(s).',
                            max(0, (int) ($result['uploadedCount'] ?? 0)),
                            max(0, (int) ($result['convertedCount'] ?? 0)),
                            max(0, (int) ($result['skippedCount'] ?? 0))
                        );
                        $this->eventLogger->content(
                            'admin.media.uploaded',
                            [
                                'actor' => admin_current_masked_identifier(),
                                'folder' => $folder,
                                'uploaded' => (int) ($result['uploadedCount'] ?? 0),
                                'converted' => (int) ($result['convertedCount'] ?? 0),
                                'skipped' => (int) ($result['skippedCount'] ?? 0),
                                'auto_webp' => $autoWebp,
                            ]
                        );
                    } else {
                        $error = is_string($result['error'] ?? null) ? (string) $result['error'] : 'Import des fichiers impossible.';
                    }
                } elseif ($action === 'import_zip') {
                    $archiveFile = $this->uploadedFile($request, 'media_zip_file');
                    $result = is_array($archiveFile)
                        ? $this->mediaLibraryService->importArchive(
                            $folder,
                            $archiveFile,
                            $autoWebp,
                            $maxWidth,
                            $maxHeight,
                            $quality
                        )
                        : [
                            'success' => false,
                            'error' => 'Aucune archive ZIP transmise.',
                            'importedCount' => 0,
                            'convertedCount' => 0,
                            'skippedCount' => 0,
                        ];
                    if (($result['success'] ?? false) === true) {
                        $message = $this->adminTextf(
                            'TXT_ADMIN_MEDIA_ZIP_IMPORTED',
                            'Import ZIP terminé : %d fichier(s) importé(s), %d converti(s) en WebP, %d ignore(s).',
                            max(0, (int) ($result['importedCount'] ?? 0)),
                            max(0, (int) ($result['convertedCount'] ?? 0)),
                            max(0, (int) ($result['skippedCount'] ?? 0))
                        );
                        $this->eventLogger->content(
                            'admin.media.zip_imported',
                            [
                                'actor' => admin_current_masked_identifier(),
                                'folder' => $folder,
                                'imported' => (int) ($result['importedCount'] ?? 0),
                                'converted' => (int) ($result['convertedCount'] ?? 0),
                                'skipped' => (int) ($result['skippedCount'] ?? 0),
                                'auto_webp' => $autoWebp,
                            ]
                        );
                    } else {
                        $error = is_string($result['error'] ?? null) ? (string) $result['error'] : 'Import ZIP impossible.';
                    }
                } elseif ($action === 'export_folder') {
                    $result = $this->mediaLibraryService->exportFolderArchive($folder);
                    if (($result['success'] ?? false) !== true) {
                        $error = is_string($result['error'] ?? null) ? (string) $result['error'] : 'Export ZIP impossible.';
                    } else {
                        $filename = is_string($result['filename'] ?? null) ? (string) $result['filename'] : 'media-export.zip';
                        $content = is_string($result['content'] ?? null) ? (string) $result['content'] : '';
                        $this->eventLogger->content(
                            'admin.media.zip_exported',
                            [
                                'actor' => admin_current_masked_identifier(),
                                'folder' => $folder,
                                'filename' => $filename,
                            ]
                        );

                        return new Response(
                            200,
                            [
                                'Content-Type' => 'application/zip',
                                'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
                                'Content-Length' => (string) strlen($content),
                                'Cache-Control' => 'no-store',
                            ],
                            $content
                        );
                    }
                } elseif ($action === 'rename_file') {
                    $targetFile = is_string($body['target_file'] ?? null) ? (string) $body['target_file'] : '';
                    $newFilename = is_string($body['new_file_name'] ?? null) ? (string) $body['new_file_name'] : '';
                    $result = $this->mediaLibraryService->renameFile($targetFile, $newFilename);
                    if (($result['success'] ?? false) === true) {
                        $message = $this->adminText('TXT_ADMIN_MEDIA_FILE_RENAMED', 'Fichier renomme.');
                        $this->eventLogger->content(
                            'admin.media.file_renamed',
                            [
                                'actor' => admin_current_masked_identifier(),
                                'folder' => $folder,
                                'file' => $targetFile,
                                'new_name' => $newFilename,
                            ]
                        );
                    } else {
                        $error = is_string($result['error'] ?? null) ? (string) $result['error'] : 'Renommage du fichier impossible.';
                    }
                } elseif ($action === 'move_file') {
                    $targetFile = is_string($body['target_file'] ?? null) ? (string) $body['target_file'] : '';
                    $destinationFolder = is_string($body['destination_folder'] ?? null) ? (string) $body['destination_folder'] : '';
                    $result = $this->mediaLibraryService->moveFile($targetFile, $destinationFolder);
                    if (($result['success'] ?? false) === true) {
                        $message = $this->adminText('TXT_ADMIN_MEDIA_FILE_MOVED', 'Fichier deplace.');
                        $this->eventLogger->content(
                            'admin.media.file_moved',
                            [
                                'actor' => admin_current_masked_identifier(),
                                'folder' => $folder,
                                'file' => $targetFile,
                                'destination_folder' => $destinationFolder,
                            ]
                        );
                    } else {
                        $error = is_string($result['error'] ?? null) ? (string) $result['error'] : 'Deplacement du fichier impossible.';
                    }
                } elseif ($action === 'delete_file') {
                    $result = $this->mediaLibraryService->deleteFile(
                        is_string($body['target_file'] ?? null) ? (string) $body['target_file'] : ''
                    );
                    $folder = is_string($result['folder'] ?? null) ? (string) $result['folder'] : $folder;
                    if (($result['success'] ?? false) === true) {
                        $message = $this->adminText('TXT_ADMIN_MEDIA_FILE_DELETED', 'Fichier supprimé.');
                        $this->eventLogger->content(
                            'admin.media.file_deleted',
                            [
                                'actor' => admin_current_masked_identifier(),
                                'folder' => $folder,
                                'file' => is_string($body['target_file'] ?? null) ? (string) $body['target_file'] : '',
                            ]
                        );
                    } else {
                        $error = is_string($result['error'] ?? null) ? (string) $result['error'] : 'Suppression du fichier impossible.';
                    }
                } elseif ($action === 'delete_folder') {
                    $targetFolder = is_string($body['target_folder'] ?? null) ? (string) $body['target_folder'] : '';
                    $result = $this->mediaLibraryService->deleteFolder($targetFolder);
                    $folder = is_string($result['parentFolder'] ?? null) ? (string) $result['parentFolder'] : $folder;
                    if (($result['success'] ?? false) === true) {
                        $message = $this->adminText('TXT_ADMIN_MEDIA_FOLDER_DELETED', 'Dossier supprimé.');
                        $this->eventLogger->content(
                            'admin.media.folder_deleted',
                            [
                                'actor' => admin_current_masked_identifier(),
                                'folder' => $targetFolder,
                            ]
                        );
                    } else {
                        $error = is_string($result['error'] ?? null) ? (string) $result['error'] : 'Suppression du dossier impossible.';
                    }
                } elseif ($action === 'rename_folder') {
                    $targetFolder = is_string($body['target_folder'] ?? null) ? (string) $body['target_folder'] : '';
                    $newFolderName = is_string($body['new_folder_name'] ?? null) ? (string) $body['new_folder_name'] : '';
                    $result = $this->mediaLibraryService->renameFolder($targetFolder, $newFolderName);
                    if (($result['success'] ?? false) === true) {
                        $message = $this->adminText('TXT_ADMIN_MEDIA_FOLDER_RENAMED', 'Dossier renomme.');
                        $this->eventLogger->content(
                            'admin.media.folder_renamed',
                            [
                                'actor' => admin_current_masked_identifier(),
                                'folder' => $targetFolder,
                                'new_name' => $newFolderName,
                            ]
                        );
                    } else {
                        $error = is_string($result['error'] ?? null) ? (string) $result['error'] : 'Renommage du dossier impossible.';
                    }
                } elseif ($action === 'move_folder') {
                    $targetFolder = is_string($body['target_folder'] ?? null) ? (string) $body['target_folder'] : '';
                    $destinationFolder = is_string($body['destination_folder'] ?? null) ? (string) $body['destination_folder'] : '';
                    $result = $this->mediaLibraryService->moveFolder($targetFolder, $destinationFolder);
                    if (($result['success'] ?? false) === true) {
                        $message = $this->adminText('TXT_ADMIN_MEDIA_FOLDER_MOVED', 'Dossier deplace.');
                        $this->eventLogger->content(
                            'admin.media.folder_moved',
                            [
                                'actor' => admin_current_masked_identifier(),
                                'folder' => $targetFolder,
                                'destination_folder' => $destinationFolder,
                            ]
                        );
                    } else {
                        $error = is_string($result['error'] ?? null) ? (string) $result['error'] : 'Deplacement du dossier impossible.';
                    }
                } elseif ($action === 'convert_file_webp') {
                    $result = $this->mediaLibraryService->convertFileToWebp(
                        is_string($body['target_file'] ?? null) ? (string) $body['target_file'] : '',
                        $maxWidth,
                        $maxHeight,
                        $quality
                    );
                    $folder = is_string($result['folder'] ?? null) ? (string) $result['folder'] : $folder;
                    if (($result['success'] ?? false) === true) {
                        $message = $this->adminText('TXT_ADMIN_MEDIA_WEBP_CONVERTED', 'Conversion WebP réalisée.');
                        $this->eventLogger->content(
                            'admin.media.file_converted_webp',
                            [
                                'actor' => admin_current_masked_identifier(),
                                'folder' => $folder,
                                'file' => is_string($body['target_file'] ?? null) ? (string) $body['target_file'] : '',
                                'output' => is_string($result['outputSrc'] ?? null) ? (string) $result['outputSrc'] : '',
                            ]
                        );
                    } else {
                        $error = is_string($result['error'] ?? null) ? (string) $result['error'] : 'Conversion WebP impossible.';
                    }
                } else {
                    $error = $this->adminText('TXT_ADMIN_MEDIA_UNKNOWN_ACTION', 'Action media inconnue.');
                }
            }
        }

        return $this->renderPage(
            'media.php',
            [
                'pageTitle' => $this->adminText('TXT_ADMIN_PAGE_TITLE_MEDIA', 'Bibliotheque medias'),
                'activeMenu' => 'media',
                'csrfToken' => admin_csrf_token(),
                'message' => $message,
                'error' => $error,
                'mediaView' => $this->mediaLibraryService->viewModel($folder, $filters),
                'mediaResetUrl' => $this->buildFilterResetUrl(
                    $this->routeResolver->canonicalPath('media'),
                    $folder !== '' ? ['folder' => $folder] : []
                ),
            ]
        );
    }

    private function menus(Request $request): Response
    {
        $guard = $this->guardAuthenticated($request, 'menus');
        if ($guard !== null) {
            return $guard;
        }

        $message = null;
        $error = null;
        $view = $this->navigationService->viewModel(
            is_string($request->query()['location'] ?? null) ? $request->query()['location'] : null,
            is_string($request->query()['selection'] ?? null) ? $request->query()['selection'] : null
        );
        $view['openContextualEditor'] = false;

        if ($request->method() === 'POST') {
            $body = $this->serializedFormNormalizer->menuBuilder($request->body());
            $token = $body['csrf_token'] ?? '';
            $builderAction = is_string($body['builder_action'] ?? null) ? $body['builder_action'] : '';

            if (!admin_validate_csrf_token(is_string($token) ? $token : null)) {
                $this->eventLogger->security(
                    'admin.menus.invalid_csrf',
                    [
                        'uri' => $request->uri(),
                        'actor' => admin_current_masked_identifier(),
                    ],
                    'warning'
                );
                $error = $this->adminText('TXT_ADMIN_MESSAGE_SESSION_EXPIRED', 'Session expirée, merci de réessayer.');
            } else {
                $result = $this->navigationService->handle(is_array($body) ? $body : []);
                $message = $result['message'];
                $error = $result['error'];
                $view = $result['view'];
                $view['openContextualEditor'] = $this->shouldOpenMenuContextualEditor($builderAction, $error, $view);

                if ($message !== null) {
                    $this->eventLogger->content(
                        'admin.menus.saved',
                        [
                            'actor' => admin_current_masked_identifier(),
                            'sections' => array_keys((array) ($view['locations'] ?? [])),
                        ]
                    );
                } elseif ($error !== null && $error !== $this->adminText('TXT_ADMIN_MESSAGE_SESSION_EXPIRED', 'Session expirée, merci de réessayer.')) {
                    $this->eventLogger->content(
                        'admin.menus.save_failed',
                        [
                            'actor' => admin_current_masked_identifier(),
                            'error' => $error,
                        ],
                        'warning'
                    );
                }
            }
        }

        return $this->renderPage(
            'menus.php',
            [
                'pageTitle' => $this->adminText('TXT_ADMIN_PAGE_TITLE_MENUS', 'Menus du site'),
                'activeMenu' => 'menus',
                'csrfToken' => admin_csrf_token(),
                'message' => $message,
                'error' => $error,
                'menusView' => $view,
            ]
        );
    }

    private function settings(Request $request): Response
    {
        $guard = $this->guardAuthenticated($request, 'settings');
        if ($guard !== null) {
            return $guard;
        }

        $message = null;
        $error = null;
        $view = $this->settingsViewWithCron($this->settingsService->viewModel());
        $openSettingsSection = null;
        $settingsAutoscrollTarget = null;

        if ($request->method() === 'POST') {
            $body = $request->body();
            $token = $body['csrf_token'] ?? '';
            $requestedSettingsSection = is_string($body['settings_section'] ?? null)
                ? trim((string) $body['settings_section'])
                : '';
            $allowedSettingsSections = ['database', 'admin', 'url', 'head', 'tarteaucitron', 'discussions', 'instagram', 'observability', 'backup', 'cron', 'translations', 'security'];
            if (in_array($requestedSettingsSection, $allowedSettingsSections, true)) {
                $openSettingsSection = $requestedSettingsSection;
            }

            if (!admin_validate_csrf_token(is_string($token) ? $token : null)) {
                $this->eventLogger->security(
                    'admin.settings.invalid_csrf',
                    [
                        'uri' => $request->uri(),
                        'actor' => admin_current_masked_identifier(),
                    ],
                    'warning'
                );
                $error = $this->adminText('TXT_ADMIN_MESSAGE_SESSION_EXPIRED', 'Session expirée, merci de réessayer.');
            } else {
                $settingsAction = is_string($body['settings_action'] ?? null)
                    ? trim((string) $body['settings_action'])
                    : 'save';

                if (str_starts_with($settingsAction, 'cron_') && $openSettingsSection === 'cron') {
                    $result = $this->cronCenterService->handle(
                        is_array($body) ? $body : [],
                        admin_current_identifier()
                    );
                    $result['view'] = $this->settingsViewWithCron(
                        $this->settingsService->viewModel(),
                        is_array($result['view'] ?? null) ? $result['view'] : null
                    );
                } elseif ($settingsAction === 'instagram_test' && $openSettingsSection === 'instagram') {
                    $result = $this->settingsService->testInstagramConnection(
                        is_array($body) ? $body : [],
                        admin_current_identifier()
                    );
                } elseif ($settingsAction === 'cache_clear') {
                    $result = $this->settingsService->clearCaches(
                        admin_current_identifier()
                    );
                } elseif ($settingsAction === 'backup_save' && $openSettingsSection === 'backup') {
                    $result = $this->settingsService->saveBackup(
                        is_array($body) ? $body : [],
                        admin_current_identifier()
                    );
                } else {
                    $result = $this->settingsService->save(
                        is_array($body) ? $body : [],
                        admin_current_identifier()
                    );
                }

                $message = $result['message'];
                $error = $result['error'];
                $view = $this->settingsViewWithCron(is_array($result['view'] ?? null) ? $result['view'] : []);

                if (($result['success'] ?? false) === true && is_string($result['adminIdentifier'] ?? null)) {
                    admin_update_authenticated_identifier((string) $result['adminIdentifier']);
                }

                if ($settingsAction === 'cron_test' && $openSettingsSection === 'cron') {
                    $settingsAutoscrollTarget = 'cron-center-history';
                }

                if (($result['success'] ?? false) === true && str_starts_with($settingsAction, 'cron_')) {
                    $openSettingsSection = 'cron';
                } elseif (($result['success'] ?? false) === true && $settingsAction === 'backup_save') {
                    $openSettingsSection = 'backup';
                } elseif (($result['success'] ?? false) === true && !($settingsAction === 'instagram_test' && $openSettingsSection === 'instagram')) {
                    $openSettingsSection = null;
                }
            }
        }

        return $this->renderPage(
            'settings.php',
            [
                'pageTitle' => $this->adminText('TXT_ADMIN_PAGE_TITLE_SETTINGS', 'Paramètres d’exploitation'),
                'activeMenu' => 'settings',
                'csrfToken' => admin_csrf_token(),
                'message' => $message,
                'error' => $error,
                'openSettingsSection' => $openSettingsSection,
                'settingsAutoscrollTarget' => $settingsAutoscrollTarget,
                'settingsView' => $view,
            ]
        );
    }

    /**
     * @param array<string, mixed> $view
     * @param array<string, mixed>|null $cronCenterView
     * @return array<string, mixed>
     */
    private function settingsViewWithCron(array $view, ?array $cronCenterView = null): array
    {
        if ($cronCenterView === null && is_array($view['cronCenter'] ?? null)) {
            return $view;
        }

        $view['cronCenter'] = $cronCenterView ?? $this->cronCenterService->viewModel();

        return $view;
    }

    private function logs(Request $request): Response
    {
        $guard = $this->guardAuthenticated($request, 'logs');
        if ($guard !== null) {
            return $guard;
        }

        $message = null;
        $error = null;
        $defaultFilters = $this->logService->normalizeFilters([]);
        $filterState = $this->resolveRememberedListFilters(
            $request,
            'logs',
            ['q', 'channel', 'level', 'date_from', 'date_to'],
            fn (array $input): array => $this->logService->normalizeFilters($input),
            $defaultFilters
        );

        if ($filterState['resetRequested']) {
            return $this->redirect($this->routeResolver->canonicalPath('logs'));
        }

        $filters = $filterState['filters'];

        if ($request->method() === 'POST') {
            $body = $request->body();
            $token = $body['csrf_token'] ?? '';

            if (!admin_validate_csrf_token(is_string($token) ? $token : null)) {
                $this->eventLogger->security(
                    'admin.logs.invalid_csrf',
                    [
                        'uri' => $request->uri(),
                        'actor' => admin_current_masked_identifier(),
                    ],
                    'warning'
                );
                $error = $this->adminText('TXT_ADMIN_MESSAGE_SESSION_EXPIRED', 'Session expirée, merci de réessayer.');
                $filters = $this->logService->normalizeFilters(is_array($body) ? $body : []);
            } else {
                $sensitiveGuard = $this->guardSensitiveAction($request, 'logs.write');
                if ($sensitiveGuard !== null) {
                    return $sensitiveGuard;
                }

                $action = is_string($body['log_action'] ?? null) ? $body['log_action'] : '';
                $result = match ($action) {
                    'delete_selected' => $this->logService->deleteSelected(is_array($body) ? $body : []),
                    'purge_filtered' => $this->logService->purgeFiltered(is_array($body) ? $body : []),
                    default => [
                        'success' => false,
                        'message' => null,
                        'error' => $this->adminText('TXT_ADMIN_LOGS_UNKNOWN_ACTION', 'Action de logs inconnue.'),
                        'deletedCount' => 0,
                        'filters' => $this->logService->normalizeFilters(is_array($body) ? $body : []),
                    ],
                };

                $message = $result['message'];
                $error = $result['error'];
                $filters = is_array($result['filters'] ?? null)
                    ? $this->logService->normalizeFilters($result['filters'])
                    : $filters;

                if (($result['success'] ?? false) === true) {
                    $this->eventLogger->security(
                        $action === 'purge_filtered'
                            ? 'admin.logs.purged_filtered'
                            : 'admin.logs.deleted_selected',
                        [
                            'actor' => admin_current_masked_identifier(),
                            'deleted_count' => (int) ($result['deletedCount'] ?? 0),
                            'filters' => $filters,
                        ]
                    );
                } elseif ($error !== null && $error !== $this->adminText('TXT_ADMIN_MESSAGE_SESSION_EXPIRED', 'Session expirée, merci de réessayer.')) {
                    $this->eventLogger->security(
                        'admin.logs.action_failed',
                        [
                            'actor' => admin_current_masked_identifier(),
                            'action' => $action,
                            'filters' => $filters,
                            'error' => $error,
                        ],
                        'warning'
                    );
                }
            }
        }

        return $this->renderPage(
            'logs.php',
            [
                'pageTitle' => $this->adminText('TXT_ADMIN_PAGE_TITLE_LOGS', 'Journaux système'),
                'activeMenu' => 'logs',
                'csrfToken' => admin_csrf_token(),
                'message' => $message,
                'error' => $error,
                'logsView' => $this->logService->viewModel($filters),
                'logsResetUrl' => $this->buildFilterResetUrl($this->routeResolver->canonicalPath('logs')),
            ]
        );
    }

    private function logout(Request $request): Response
    {
        $guard = $this->guardAuthenticated($request, 'logout');
        if ($guard !== null) {
            return $guard;
        }

        admin_logout();

        return $this->redirect($this->routeResolver->canonicalPath('login'), 302);
    }

    private function sessionPing(Request $request): Response
    {
        $networkGuard = $this->guardAdminNetwork($request, 'session_ping');
        if ($networkGuard !== null) {
            return Response::json(['ok' => false, 'error' => 'access_denied'], 403);
        }

        if ($request->method() !== 'POST') {
            return Response::json(['ok' => false, 'error' => 'method_not_allowed'], 405);
        }

        if (!admin_is_authenticated()) {
            return Response::json(
                [
                    'ok' => false,
                    'error' => 'unauthenticated',
                    'loginUrl' => $this->routeResolver->canonicalPath('login'),
                ],
                401
            );
        }

        $body = is_array($request->body()) ? $request->body() : [];
        $tokenFromBody = is_string($body['csrf_token'] ?? null) ? $body['csrf_token'] : null;
        $tokenFromHeader = $request->header('X-CSRF-Token');
        $csrfToken = $tokenFromBody !== null && trim($tokenFromBody) !== ''
            ? $tokenFromBody
            : (is_string($tokenFromHeader) ? $tokenFromHeader : null);

        if (!admin_validate_csrf_token($csrfToken)) {
            $this->eventLogger->security(
                'admin.session.ping.invalid_csrf',
                [
                    'uri' => $request->uri(),
                    'method' => $request->method(),
                    'actor' => admin_current_masked_identifier(),
                ],
                'warning'
            );

            return Response::json(['ok' => false, 'error' => 'invalid_csrf'], 419);
        }

        $user = admin_current_user();
        $lastActivityAt = is_array($user) ? (int) ($user['last_activity_at'] ?? time()) : time();
        $timeoutSeconds = admin_inactivity_timeout_seconds();
        $remainingSeconds = max(0, $timeoutSeconds - max(0, time() - $lastActivityAt));

        return Response::json(
            [
                'ok' => true,
                'remainingSeconds' => $remainingSeconds,
                'timeoutSeconds' => $timeoutSeconds,
            ]
        );
    }

    private function guardAuthenticated(Request $request, string $page): ?Response
    {
        $networkGuard = $this->guardAdminNetwork($request, $page);
        if ($networkGuard !== null) {
            return $networkGuard;
        }

        if (admin_is_authenticated()) {
            return null;
        }

        $this->eventLogger->security(
            'admin.access.denied',
            [
                'page' => $page,
                'uri' => $request->uri(),
                'method' => $request->method(),
            ],
            'warning'
        );

        return $this->redirect($this->routeResolver->canonicalPath('login'), 302);
    }

    private function guardAdminNetwork(Request $request, string $page): ?Response
    {
        $allowedIps = app_config('admin.allowed_ips', []);
        $allowedIps = is_array($allowedIps) ? array_values(array_filter(array_map('strval', $allowedIps))) : [];

        if ($allowedIps === []) {
            return null;
        }

        $clientIp = $request->clientIp((bool) app_config('admin.trust_proxy_headers', false));
        if (ip_matches_allowlist($clientIp, $allowedIps)) {
            return null;
        }

        $this->eventLogger->security(
            'admin.access.denied_ip',
            [
                'page' => $page,
                'uri' => $request->uri(),
                'ip' => $clientIp,
            ],
            'warning'
        );

        return new Response(403, ['Content-Type' => 'text/html; charset=utf-8'], $this->adminText('TXT_ADMIN_ACCESS_DENIED', 'Accès admin interdit.'));
    }

    private function guardSensitiveAction(Request $request, string $action): ?Response
    {
        if ($request->method() !== 'POST') {
            return null;
        }

        if (admin_reauth_is_fresh()) {
            return null;
        }

        $this->eventLogger->security(
            'admin.reauth.required',
            [
                'action' => $action,
                'uri' => $request->uri(),
                'method' => $request->method(),
                'actor' => admin_current_masked_identifier(),
            ],
            'warning'
        );

        admin_logout('reauth_required');

        return $this->redirect($this->routeResolver->canonicalPath('login') . '?reauth=1', 302);
    }

    private function noticeMessageFromCode(?string $noticeCode): ?string
    {
        return match ($noticeCode) {
            'inactive_timeout' => $this->adminTextf(
                'TXT_ADMIN_NOTICE_INACTIVE_TIMEOUT',
                'Session expirée après inactivité (%d minutes). Merci de vous reconnecter.',
                (int) floor(admin_inactivity_timeout_seconds() / 60)
            ),
            'reauth_required' => $this->adminText('TXT_ADMIN_NOTICE_REAUTH_REQUIRED', 'Veuillez vous reconnecter pour valider une action sensible.'),
            default => null,
        };
    }

    private function tileDuplicateSuccessMessage(int $sourceGroupId, int $duplicatedGroupId, string $name, int $tileCount): string
    {
        $message = $this->adminTextf(
            'TXT_ADMIN_TILES_DUPLICATE_SUCCESS_BASE',
            'Duplication réussie : le groupe #%d a été recopié dans le groupe #%d',
            $sourceGroupId,
            $duplicatedGroupId
        );

        $normalizedName = trim($name);
        if ($normalizedName !== '') {
            $message .= ' ' . $this->adminTextf(
                'TXT_ADMIN_TILES_DUPLICATE_SUCCESS_NAME',
                'sous le titre "%s"',
                $normalizedName
            );
        }

        if ($tileCount > 0) {
            $message .= ' ' . $this->adminTextf(
                'TXT_ADMIN_TILES_DUPLICATE_SUCCESS_COUNT',
                'avec %d tuile(s) copiée(s)',
                $tileCount
            );
        }

        return $message . '.';
    }

    private function tileDuplicateErrorMessage(int $sourceGroupId, string $error): string
    {
        $normalizedError = trim($error);
        if ($normalizedError === '') {
            $normalizedError = $this->adminText('TXT_ADMIN_TILES_DUPLICATE_FAILED', 'Impossible de dupliquer le groupe de tuiles.');
        }

        return $this->adminTextf(
            'TXT_ADMIN_TILES_DUPLICATE_ERROR_MESSAGE',
            'Duplication impossible pour le groupe #%d : %s',
            $sourceGroupId,
            $normalizedError
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderPage(string $template, array $context): Response
    {
        $user = admin_current_user();
        $loginAt = is_array($user) ? (int) ($user['login_at'] ?? time()) : time();
        $adminInterfaceLanguage = function_exists('admin_interface_language')
            ? admin_interface_language()
            : strtolower(trim((string) app_config('default_lang', 'fr')));
        $defaultContext = [
            'activeMenu' => 'dashboard',
            'adminInterfaceLanguage' => $adminInterfaceLanguage,
            'adminIdentifier' => is_array($user)
                ? (string) ($user['identifier'] ?? $user['email'] ?? admin_configured_identifier())
                : admin_configured_identifier(),
            'formattedLogin' => date('d/m/Y H:i', $loginAt),
            'adminLoginUrl' => $this->routeResolver->canonicalPath('login'),
            'adminDashboardUrl' => $this->routeResolver->canonicalPath('dashboard'),
            'adminPagesUrl' => $this->routeResolver->canonicalPath('pages'),
            'adminPageCreateUrl' => $this->routeResolver->pageCreatePath(),
            'adminArticlesUrl' => $this->routeResolver->canonicalPath('articles'),
            'adminArticleCreateUrl' => $this->routeResolver->articleCreatePath(),
            'adminDiscussionsUrl' => $this->routeResolver->canonicalPath('discussions'),
            'adminMediaUrl' => $this->routeResolver->canonicalPath('media'),
            'adminTilesUrl' => $this->routeResolver->canonicalPath('tiles'),
            'adminTileCreateUrl' => $this->routeResolver->tileCreatePath(),
            'adminMenusUrl' => $this->routeResolver->canonicalPath('menus'),
            'adminLogsUrl' => $this->routeResolver->canonicalPath('logs'),
            'adminSettingsUrl' => $this->routeResolver->canonicalPath('settings'),
            'adminPrivateMembersUrl' => $this->routeResolver->canonicalPath('private_members'),
            'adminLogoutUrl' => $this->routeResolver->canonicalPath('logout'),
            'adminSessionPingUrl' => $this->routeResolver->sessionPingPath(),
            'adminBlogSaveUrl' => $this->routeResolver->blogSavePath(),
            'loginPath' => $this->routeResolver->loginPath(),
            'csrfToken' => admin_csrf_token(),
            'adminSessionTimeoutSeconds' => admin_inactivity_timeout_seconds(),
            'adminSessionWarningLeadSeconds' => min(120, admin_inactivity_timeout_seconds()),
            'adminSessionDecisionSeconds' => 120,
            'adminSessionWarningTitle' => $this->adminText('TXT_ADMIN_SESSION_WARNING_TITLE', 'Session en fin de validité'),
            'adminSessionWarningMessage' => $this->adminText('TXT_ADMIN_SESSION_WARNING_MESSAGE', 'Voulez-vous prolonger la session ?'),
            'adminSessionWarningCountdownTemplate' => $this->adminText('TXT_ADMIN_SESSION_WARNING_COUNTDOWN_TEMPLATE', 'Déconnexion automatique dans %d secondes.'),
            'adminSessionWarningConfirmLabel' => $this->adminText('TXT_ADMIN_SESSION_WARNING_CONFIRM', 'Oui, prolonger'),
            'adminSessionWarningLogoutLabel' => $this->adminText('TXT_ADMIN_SESSION_WARNING_LOGOUT', 'Non, se déconnecter'),
            'adminSessionWarningNetworkError' => $this->adminText('TXT_ADMIN_SESSION_WARNING_NETWORK_ERROR', 'Session expirée ou inaccessible. Merci de vous reconnecter.'),
        ];

        $body = $this->renderTemplate(
            'layout.php',
            array_merge(
                $defaultContext,
                $context,
                [
                    'contentTemplate' => $template,
                ]
            )
        );

        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $body);
    }

    /**
     * @param array<string, mixed> $view
     */
    private function shouldOpenMenuContextualEditor(string $builderAction, ?string $error, array $view): bool
    {
        if (!is_array($view['selectedItem']['item'] ?? null) || !is_string($view['selectedItemPath'] ?? null)) {
            return false;
        }

        if ($error !== null && $error !== $this->adminText('TXT_ADMIN_MESSAGE_SESSION_EXPIRED', 'Session expirée, merci de réessayer.')) {
            return true;
        }

        foreach (['select@', 'append@', 'append_child@', 'duplicate@'] as $prefix) {
            if (str_starts_with($builderAction, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function applyUploadedArticleImage(array &$body, Request $request): ?string
    {
        $file = $this->uploadedFile($request, 'article_image_file');
        if ($file === null) {
            return null;
        }

        $articleData = is_array($body['article'] ?? null) ? $body['article'] : [];
        $slugHint = is_string($articleData['slug'] ?? null) ? (string) $articleData['slug'] : 'article';

        try {
            $uploaded = $this->editorialImageService->upload($file, 'article', $slugHint);
        } catch (\RuntimeException $exception) {
            return $exception->getMessage();
        }

        $articleData['featured_image_src'] = (string) $uploaded['src'];

        $hasWidth = is_string($articleData['featured_image_width'] ?? null)
            ? trim((string) $articleData['featured_image_width']) !== ''
            : !empty($articleData['featured_image_width']);
        if (!$hasWidth && is_int($uploaded['width'])) {
            $articleData['featured_image_width'] = (string) $uploaded['width'];
        }

        $hasHeight = is_string($articleData['featured_image_height'] ?? null)
            ? trim((string) $articleData['featured_image_height']) !== ''
            : !empty($articleData['featured_image_height']);
        if (!$hasHeight && is_int($uploaded['height'])) {
            $articleData['featured_image_height'] = (string) $uploaded['height'];
        }

        $body['article'] = $articleData;

        return null;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function applyUploadedPageImages(array &$body, Request $request): ?string
    {
        $translations = is_array($body['translations'] ?? null) ? $body['translations'] : [];
        $slugHint = is_string($body['slug'] ?? null) ? (string) $body['slug'] : 'page';

        foreach ($this->pageService->availableLanguages() as $language) {
            $fieldName = 'page_image_file_' . strtolower($language);
            $file = $this->uploadedFile($request, $fieldName);
            if ($file === null) {
                continue;
            }

            try {
                $uploaded = $this->editorialImageService->upload($file, 'page', $slugHint . '-' . $language);
            } catch (\RuntimeException $exception) {
                $prefix = strtoupper((string) $language);

                return '[' . $prefix . '] ' . $exception->getMessage();
            }

            $translation = is_array($translations[$language] ?? null) ? $translations[$language] : [];
            $translation['meta_image_src'] = (string) $uploaded['src'];

            $hasWidth = is_string($translation['meta_image_width'] ?? null)
                ? trim((string) $translation['meta_image_width']) !== ''
                : !empty($translation['meta_image_width']);
            if (!$hasWidth && is_int($uploaded['width'])) {
                $translation['meta_image_width'] = (string) $uploaded['width'];
            }

            $hasHeight = is_string($translation['meta_image_height'] ?? null)
                ? trim((string) $translation['meta_image_height']) !== ''
                : !empty($translation['meta_image_height']);
            if (!$hasHeight && is_int($uploaded['height'])) {
                $translation['meta_image_height'] = (string) $uploaded['height'];
            }

            $translations[$language] = $translation;
        }

        $body['translations'] = $translations;

        return null;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function applyUploadedPageSharedMedia(array &$body, Request $request): ?string
    {
        $sharedMediaInput = is_array($body['shared_media'] ?? null) ? $body['shared_media'] : [];
        $sharedMedia = [];

        foreach (array_values($sharedMediaInput) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $sanitized = AdminEditorialImageService::sanitizeImageMetadata([
                'src' => (string) ($item['src'] ?? ''),
                'alt' => (string) ($item['alt'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'caption' => (string) ($item['caption'] ?? ''),
                'width' => $item['width'] ?? null,
                'height' => $item['height'] ?? null,
            ]);
            if ($sanitized === null) {
                continue;
            }

            $sharedMedia[] = $sanitized;
        }

        $slugHint = is_string($body['slug'] ?? null) ? (string) $body['slug'] : 'page';
        $uploadedFiles = $this->uploadedFiles($request, 'page_shared_media_files');
        foreach ($uploadedFiles as $file) {
            try {
                $uploaded = $this->editorialImageService->uploadWebp($file, 'media', $slugHint, 2048, 2048, 82);
            } catch (\RuntimeException $exception) {
                return '[Media] ' . $exception->getMessage();
            }

            $originalName = is_string($file['name'] ?? null) ? (string) $file['name'] : '';
            $fallbackAlt = trim(str_replace(['-', '_'], ' ', (string) pathinfo($originalName, PATHINFO_FILENAME)));
            if ($fallbackAlt === '') {
                $fallbackAlt = trim((string) $slugHint);
            }

            $sharedMedia[] = [
                'src' => (string) ($uploaded['src'] ?? ''),
                'alt' => $fallbackAlt,
                'title' => '',
                'caption' => '',
                'width' => isset($uploaded['width']) ? (int) $uploaded['width'] : null,
                'height' => isset($uploaded['height']) ? (int) $uploaded['height'] : null,
            ];
        }

        if (count($sharedMedia) > 24) {
            $sharedMedia = array_slice($sharedMedia, 0, 24);
        }

        $body['shared_media'] = array_values($sharedMedia);

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function uploadedFile(Request $request, string $fieldName): ?array
    {
        $files = $this->uploadedFiles($request, $fieldName);

        return $files[0] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function uploadedFiles(Request $request, string $fieldName): array
    {
        $files = $request->files();
        $entry = $files[$fieldName] ?? null;
        if (!is_array($entry)) {
            return [];
        }

        $names = $entry['name'] ?? null;
        if (is_array($names)) {
            $errors = is_array($entry['error'] ?? null) ? $entry['error'] : [];
            $tmpNames = is_array($entry['tmp_name'] ?? null) ? $entry['tmp_name'] : [];
            $types = is_array($entry['type'] ?? null) ? $entry['type'] : [];
            $sizes = is_array($entry['size'] ?? null) ? $entry['size'] : [];
            $normalized = [];

            foreach (array_keys($names) as $index) {
                $error = isset($errors[$index]) ? (int) $errors[$index] : UPLOAD_ERR_NO_FILE;
                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $normalized[] = [
                    'name' => is_string($names[$index] ?? null) ? (string) $names[$index] : '',
                    'type' => is_string($types[$index] ?? null) ? (string) $types[$index] : '',
                    'tmp_name' => is_string($tmpNames[$index] ?? null) ? (string) $tmpNames[$index] : '',
                    'error' => $error,
                    'size' => is_numeric($sizes[$index] ?? null) ? (int) $sizes[$index] : 0,
                ];
            }

            return $normalized;
        }

        $error = isset($entry['error']) ? (int) $entry['error'] : UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return [];
        }

        return [$entry];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderTemplate(string $template, array $context = []): string
    {
        $templatePath = ROOT_PATH . '/templates/admin/' . $template;

        extract($context, EXTR_SKIP);

        ob_start();
        require $templatePath;

        return (string) ob_get_clean();
    }

    private function redirect(string $location, int $status = 302): Response
    {
        return new Response($status, ['Location' => $location], '');
    }

    private function withAdminHeaders(Response $response): Response
    {
        $response->headers['X-Robots-Tag'] = 'noindex, nofollow, noarchive';
        $response->headers['Cache-Control'] = 'private, no-store, no-cache, must-revalidate';
        $response->headers['Pragma'] = 'no-cache';
        $response->headers['Expires'] = '0';
        $response->headers['X-Frame-Options'] = 'DENY';
        $response->headers['X-Content-Type-Options'] = 'nosniff';
        $response->headers['Referrer-Policy'] = 'no-referrer';
        $response->headers['Permissions-Policy'] = 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), browsing-topics=()';
        $response->headers['Content-Security-Policy'] = $this->backOfficeContentSecurityPolicy();

        return $response;
    }

    private function backOfficeContentSecurityPolicy(): string
    {
        $nonce = is_string($GLOBALS['csp_nonce'] ?? null) ? (string) $GLOBALS['csp_nonce'] : '';
        $scriptSrc = $nonce !== '' ? "'self' 'nonce-{$nonce}'" : "'self' 'unsafe-inline'";

        return "default-src 'self'; script-src {$scriptSrc}; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; media-src 'self' blob:; frame-src 'none'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none';";
    }
}
