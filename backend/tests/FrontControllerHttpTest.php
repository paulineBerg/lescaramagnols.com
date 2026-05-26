<?php

declare(strict_types=1);

use Caramagnols\Admin\AdminController;
use Caramagnols\Admin\AdminRouteResolver;
use Caramagnols\Admin\AdminSettingsService;
use Caramagnols\Blog\BlogApiController;
use Caramagnols\Blog\BlogSaveService;
use Caramagnols\Http\FrontController;
use Caramagnols\Http\Request;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\Logging\LoggerFactory;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/auth/admin.php';

final class FrontControllerHttpTest extends TestCase
{
    private string $logDir;
    private string $rateLimitDir;
    private string $blogDir;
    private string $pagesFile;
    private string $menusFile;
    private string $databaseOverrideFile;
    private string $adminOverrideFile;
    private string $instagramCacheFile;
    private ?string $previousBlogDataDir = null;

    protected function setUp(): void
    {
        ensure_session_started();
        $_SESSION = [];

        global $appConfig;
        $appConfig['admin']['email'] = 'admin@example.com';
        $appConfig['admin']['password_hash'] = password_hash('topsecret', PASSWORD_DEFAULT);
        $appConfig['admin']['session_key'] = '_front_controller_http_test';
        $appConfig['admin']['login_path'] = 'admin';
        $appConfig['admin']['allowed_ips'] = [];
        $appConfig['admin']['trust_proxy_headers'] = false;
        $appConfig['admin']['login_rate_limit_attempts'] = 5;
        $appConfig['admin']['login_rate_limit_window'] = 900;
        $appConfig['site']['url'] = [
            'domain' => '',
            'ssl_domain' => '',
            'base_path' => '/',
        ];
        $this->instagramCacheFile = ROOT_PATH . '/var/front-controller-instagram-cache-' . uniqid('', true) . '.json';
        $appConfig['site']['instagram'] = [
            'enabled' => false,
            'username' => '',
            'user_id' => '',
            'access_token' => '',
            'limit' => 6,
            'rotation_interval_ms' => 5500,
            'cache_ttl_seconds' => 1800,
            'timeout_seconds' => 8,
            'cache_path' => $this->instagramCacheFile,
        ];

        $this->logDir = sys_get_temp_dir() . '/caramagnols-front-controller-logs-' . bin2hex(random_bytes(6));
        mkdir($this->logDir, 0777, true);
        $this->rateLimitDir = sys_get_temp_dir() . '/caramagnols-front-controller-rate-limits-' . bin2hex(random_bytes(6));
        mkdir($this->rateLimitDir, 0777, true);
        $appConfig['security']['rate_limit_dir'] = $this->rateLimitDir;
        $this->blogDir = sys_get_temp_dir() . '/caramagnols-front-controller-blog-' . bin2hex(random_bytes(6));
        mkdir($this->blogDir, 0777, true);
        $this->previousBlogDataDir = is_string($appConfig['blog']['data_dir'] ?? null) ? $appConfig['blog']['data_dir'] : null;
        $appConfig['blog']['data_dir'] = $this->blogDir;

        $this->pagesFile = ROOT_PATH . '/var/front-controller-pages-' . uniqid() . '.json';
        $this->menusFile = ROOT_PATH . '/var/front-controller-menus-' . uniqid() . '.json';
        $this->databaseOverrideFile = ROOT_PATH . '/var/front-controller-database-override-' . uniqid() . '.php';
        $this->adminOverrideFile = ROOT_PATH . '/var/front-controller-admin-override-' . uniqid() . '.php';
        pages_data_set_path_override($this->pagesFile);
        menus_data_set_path_override($this->menusFile);
        pages_cache_clear();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];

        $files = glob($this->logDir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }

        @rmdir($this->logDir);
        $rateLimitFiles = glob($this->rateLimitDir . '/*');
        if (is_array($rateLimitFiles)) {
            foreach ($rateLimitFiles as $file) {
                @unlink($file);
            }
        }
        @rmdir($this->rateLimitDir);
        $blogFiles = glob($this->blogDir . '/*');
        if (is_array($blogFiles)) {
            foreach ($blogFiles as $file) {
                @unlink($file);
            }
        }
        @rmdir($this->blogDir);

        foreach (
            [
                $this->pagesFile,
                $this->pagesFile . '.bak',
                $this->menusFile,
                $this->menusFile . '.bak',
                $this->databaseOverrideFile,
                $this->adminOverrideFile,
                $this->instagramCacheFile,
            ] as $file
        ) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        pages_data_set_path_override(null);
        menus_data_set_path_override(null);
        pages_cache_clear();

        global $appConfig;
        if ($this->previousBlogDataDir !== null) {
            $appConfig['blog']['data_dir'] = $this->previousBlogDataDir;
        } else {
            unset($appConfig['blog']['data_dir']);
        }
    }

    public function testLoginRouteIsServedViaFrontController(): void
    {
        $response = $this->frontController()->handle($this->request('GET', '/admin'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Connexion Admin', $response->body);
    }

    public function testAssetsIndexPhpIsNotExposedThroughFrontController(): void
    {
        $response = $this->frontController()->handle($this->request('GET', '/assets/index.php'));

        $this->assertSame(404, $response->status);
    }

    public function testLegacyAdminLoginAliasIsNoLongerExposed(): void
    {
        $response = $this->frontController()->handle($this->request('GET', '/legacy-admin'));

        $this->assertSame(404, $response->status);
    }

    public function testProtectedAdminPagesRouteRedirectsWhenUnauthenticated(): void
    {
        $response = $this->frontController()->handle($this->request('GET', '/admin/pages'));

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin', $response->headers['Location'] ?? null);
    }

    public function testLegacyAdminMenusAliasIsNoLongerExposed(): void
    {
        $response = $this->frontController()->handle($this->request('GET', '/legacy-admin/menus.php'));

        $this->assertSame(404, $response->status);
    }

    public function testSessionPingReturnsUnauthorizedWhenUnauthenticated(): void
    {
        $response = $this->frontController()->handle(
            $this->request('POST', '/admin/session/ping', [], ['csrf_token' => 'invalid'])
        );

        $this->assertSame(401, $response->status);
        $this->assertSame('application/json; charset=utf-8', $response->headers['Content-Type'] ?? null);

        $payload = json_decode($response->body, true);
        $this->assertIsArray($payload);
        $this->assertSame(false, $payload['ok'] ?? null);
        $this->assertSame('unauthenticated', $payload['error'] ?? null);
        $this->assertSame('/admin', $payload['loginUrl'] ?? null);
    }

    public function testSessionPingRefreshesAuthenticatedSession(): void
    {
        admin_login('admin@example.com', 'topsecret');

        $sessionKey = admin_session_key();
        $_SESSION[$sessionKey]['last_activity_at'] = time() - max(1, admin_inactivity_timeout_seconds() - 25);

        $response = $this->frontController()->handle(
            $this->request('POST', '/admin/session/ping', [], ['csrf_token' => admin_csrf_token()])
        );

        $this->assertSame(200, $response->status);
        $this->assertSame('application/json; charset=utf-8', $response->headers['Content-Type'] ?? null);

        $payload = json_decode($response->body, true);
        $this->assertIsArray($payload);
        $this->assertSame(true, $payload['ok'] ?? null);
        $this->assertGreaterThanOrEqual(admin_inactivity_timeout_seconds() - 2, (int) ($payload['remainingSeconds'] ?? 0));
        $this->assertSame(admin_inactivity_timeout_seconds(), (int) ($payload['timeoutSeconds'] ?? 0));
    }

    public function testAdminRouteReturnsForbiddenWhenIpIsNotAllowlisted(): void
    {
        global $appConfig;
        $appConfig['admin']['allowed_ips'] = ['203.0.113.0/24'];

        $response = $this->frontController()->handle($this->request('GET', '/admin', [], [], '10.0.0.7'));

        $this->assertSame(403, $response->status);
        $this->assertStringContainsString('Accès admin interdit', $response->body);
    }

    public function testAuthenticatedMenusRouteRendersBuilderThroughFrontController(): void
    {
        admin_login('admin@example.com', 'topsecret');

        $response = $this->frontController()->handle($this->request('GET', '/admin/menus'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Builder des menus', $response->body);
        $this->assertStringContainsString('Aperçu simplifié · header desktop', $response->body);
        $this->assertStringContainsString('Les Caramagnols', $response->body);
    }

    public function testAuthenticatedMenusPostSwitchesLocationTabThroughFrontController(): void
    {
        admin_login('admin@example.com', 'topsecret');

        $response = $this->frontController()->handle(
            $this->request(
                'POST',
                '/admin/menus',
                [],
                [
                    'csrf_token' => admin_csrf_token(),
                    'active_location' => 'primary',
                    'selected_item' => '',
                    'builder_action' => 'switch_location@footer',
                    'banner' => [],
                    'remonter' => [],
                    'locations' => [
                        'utility' => [],
                        'primary' => [],
                        'footer' => [],
                        'sideRight' => [],
                        'sideLeft' => [],
                    ],
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('<h3>Pied de page</h3>', $response->body);
        $this->assertStringContainsString('switch_location@footer', $response->body);
        $this->assertMatchesRegularExpression('/name="active_location"[^>]*value="footer"/', $response->body);
    }

    public function testAuthenticatedMenusPostPersistsVisualBuilderPayloadThroughFrontController(): void
    {
        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'association',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'route' => '/association',
                            'translations' => [
                                'fr' => ['title' => 'Association'],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        admin_login('admin@example.com', 'topsecret');

        $response = $this->frontController()->handle(
            $this->request(
                'POST',
                '/admin/menus',
                [],
                [
                    'csrf_token' => admin_csrf_token(),
                    'active_location' => 'primary',
                    'selected_item' => 'primary|0',
                    'builder_action' => 'save',
                    'banner' => [
                        'image' => '/assets/images/structure/banniere.jpg',
                        'headline' => 'Voyage dans le golfe',
                        'alt' => 'Voyage dans le golfe',
                        'title' => 'Voyage dans le golfe',
                    ],
                    'remonter' => [
                        'label' => 'Top',
                        'alt' => 'Remonter',
                        'title' => 'Remonter',
                    ],
                    'locations' => [
                        'utility' => [],
                        'primary' => [
                            [
                                'id' => 'primary-home',
                                'kind' => 'page',
                                'label_text' => 'Association',
                                'target_mode' => 'page',
                                'target_page_slug' => 'association',
                                'target_route' => '',
                                'target_url' => '',
                                'image' => '',
                                'content_text' => '',
                                'alt' => 'Association',
                                'title' => 'Association',
                            ],
                        ],
                        'footer' => [],
                        'sideRight' => [],
                        'sideLeft' => [],
                    ],
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Menus sauvegardés via le builder visuel.', $response->body);

        $decoded = json_decode((string) file_get_contents($this->menusFile), true);
        $this->assertIsArray($decoded);
        $this->assertSame('page', $decoded['locations']['primary'][0]['kind'] ?? null);
        $this->assertSame('association', $decoded['locations']['primary'][0]['target']['pageSlug'] ?? null);
    }

    public function testPagesNewPostRejectsInvalidCsrfThroughFrontController(): void
    {
        admin_login('admin@example.com', 'topsecret');

        $response = $this->frontController()->handle(
            $this->request(
                'POST',
                '/admin/pages/new',
                [],
                [
                    'csrf_token' => 'invalid',
                    'slug' => 'page-csrf-invalid',
                    'type' => 'structured_page',
                    'status' => 'draft',
                    'route' => '/page-csrf-invalid',
                    'layout' => 'standard_page',
                    'translations' => [
                        'fr' => ['title' => 'Page CSRF'],
                        'en' => [],
                        'de' => [],
                    ],
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Session expirée, merci de réessayer.', $response->body);
        $this->assertFileDoesNotExist($this->pagesFile);
    }

    public function testPagesNewPostSupportsDraftAndPublishedWorkflowThroughFrontController(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $token = admin_csrf_token();

        $draftResponse = $this->frontController()->handle(
            $this->request(
                'POST',
                '/admin/pages/new',
                [],
                [
                    'csrf_token' => $token,
                    'slug' => 'page-brouillon',
                    'type' => 'structured_page',
                    'status' => 'draft',
                    'route' => '/page-brouillon',
                    'layout' => 'standard_page',
                    'translations' => [
                        'fr' => [
                            'title' => 'Page brouillon',
                            'regions' => [
                                'hero_html' => '<h1>Page brouillon</h1>',
                            ],
                        ],
                        'en' => [],
                        'de' => [],
                    ],
                ]
            )
        );

        $this->assertSame(302, $draftResponse->status);
        $this->assertSame('/admin/pages/page-brouillon?saved=1', $draftResponse->headers['Location'] ?? null);

        $publishedResponse = $this->frontController()->handle(
            $this->request(
                'POST',
                '/admin/pages/new',
                [],
                [
                    'csrf_token' => $token,
                    'slug' => 'page-publique',
                    'type' => 'structured_page',
                    'status' => 'published',
                    'route' => '/page-publique',
                    'layout' => 'standard_page',
                    'translations' => [
                        'fr' => [
                            'title' => 'Page publiée',
                            'regions' => [
                                'hero_html' => '<h1>Page publiée</h1>',
                            ],
                        ],
                        'en' => [],
                        'de' => [],
                    ],
                ]
            )
        );

        $this->assertSame(302, $publishedResponse->status);
        $this->assertSame('/admin/pages/page-publique?saved=1', $publishedResponse->headers['Location'] ?? null);

        $decoded = json_decode((string) file_get_contents($this->pagesFile), true);
        $this->assertIsArray($decoded);

        $statuses = [];
        foreach ((array) ($decoded['pages'] ?? []) as $page) {
            if (!is_array($page)) {
                continue;
            }

            $statuses[(string) ($page['slug'] ?? '')] = (string) ($page['status'] ?? '');
        }

        $this->assertSame('draft', $statuses['page-brouillon'] ?? null);
        $this->assertSame('published', $statuses['page-publique'] ?? null);
    }

    public function testAdminArticlesSavePostPersistsArticleThroughFrontController(): void
    {
        admin_login('admin@example.com', 'topsecret');

        $response = $this->frontController()->handle(
            $this->jsonRequest(
                'POST',
                '/admin/articles/save',
                [
                    'csrf_token' => admin_csrf_token(),
                    'title' => 'Article front-controller',
                    'slug' => 'article-front-controller',
                    'lang' => 'fr',
                    'status' => 'published',
                    'category' => 'auto-retro',
                    'tags' => ['austin', 'histoire', 'voiture-ancienne'],
                    'content' => '<p>Contenu front-controller.</p>',
                ]
            )
        );

        $this->assertSame(201, $response->status);
        $this->assertSame('application/json; charset=utf-8', $response->headers['Content-Type'] ?? null);
        $this->assertFileExists($this->blogDir . '/article-front-controller.fr.json');
    }

    public function testLegacySaveArticleAliasRejectsUnauthorizedIpEvenWithSession(): void
    {
        global $appConfig;
        $appConfig['admin']['allowed_ips'] = ['192.0.2.10'];

        admin_login('admin@example.com', 'topsecret');

        $response = $this->frontController()->handle(
            $this->jsonRequest(
                'POST',
                '/core/blog/save_article.php',
                [
                    'csrf_token' => admin_csrf_token(),
                    'title' => 'Article alias forbidden',
                    'slug' => 'article-alias-forbidden',
                    'lang' => 'fr',
                    'status' => 'draft',
                    'category' => 'auto-retro',
                    'tags' => ['austin', 'histoire', 'voiture-ancienne'],
                    'content' => '<p>Contenu alias.</p>',
                ],
                '203.0.113.15'
            )
        );

        $this->assertSame(403, $response->status);
        $this->assertStringContainsString('Accès interdit', $response->body);
        $this->assertFileDoesNotExist($this->blogDir . '/article-alias-forbidden.fr.json');
    }

    public function testLegacySaveArticleAliasPersistsArticleThroughFrontController(): void
    {
        admin_login('admin@example.com', 'topsecret');

        $response = $this->frontController()->handle(
            $this->jsonRequest(
                'POST',
                '/core/blog/save_article.php',
                [
                    'csrf_token' => admin_csrf_token(),
                    'title' => 'Article alias legacy',
                    'slug' => 'article-alias-legacy',
                    'lang' => 'fr',
                    'status' => 'draft',
                    'category' => 'auto-retro',
                    'tags' => ['austin', 'histoire', 'voiture-ancienne'],
                    'content' => '<p>Contenu alias.</p>',
                ]
            )
        );

        $this->assertSame(201, $response->status);
        $this->assertSame('application/json; charset=utf-8', $response->headers['Content-Type'] ?? null);
        $this->assertFileExists($this->blogDir . '/article-alias-legacy.fr.json');
    }

    public function testAuthenticatedSettingsRouteRendersThroughFrontController(): void
    {
        admin_login('admin@example.com', 'topsecret');

        $response = $this->frontController()->handle($this->request('GET', '/admin/settings'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Paramètres d’exploitation', $response->body);
        $this->assertStringContainsString('Mot de passe SQL', $response->body);
    }

    public function testAuthenticatedDiscussionsRouteRendersThroughFrontController(): void
    {
        admin_login('admin@example.com', 'topsecret');

        $response = $this->frontController()->handle($this->request('GET', '/admin/discussions'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Discussions du blog', $response->body);
        $this->assertStringContainsString('Messages en attente', $response->body);
    }

    public function testAuthenticatedLogsRouteRendersThroughFrontController(): void
    {
        admin_login('admin@example.com', 'topsecret');

        $response = $this->frontController()->handle($this->request('GET', '/admin/logs'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Journaux système', $response->body);
        $this->assertStringContainsString('Journal SQL', $response->body);
    }

    public function testAuthenticatedMediaRouteRendersThroughFrontController(): void
    {
        admin_login('admin@example.com', 'topsecret');

        $response = $this->frontController()->handle($this->request('GET', '/admin/media'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Bibliotheque medias', $response->body);
        $this->assertStringContainsString('name="media_action" value="upload"', $response->body);
    }

    public function testAuthenticatedPageEditRouteRendersRegisteredPage(): void
    {
        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'association',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/association',
                            'translations' => [
                                'fr' => [
                                    'title' => 'Association',
                                    'regions' => [
                                        'hero' => [
                                            'component' => 'heading',
                                            'title' => 'Association',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        admin_login('admin@example.com', 'topsecret');
        $response = $this->frontController()->handle($this->request('GET', '/admin/pages/association'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Éditer une page', $response->body);
        $this->assertStringContainsString('Plan du template standard', $response->body);
    }

    public function testLegacyLanguageApiPathRedirectsToCanonicalEndpoint(): void
    {
        $response = $this->frontController()->handle($this->request('GET', '/bouger/core/api/lang.php?lang=fr'));

        $this->assertSame(301, $response->status);
        $this->assertSame('/core/api/lang.php?lang=fr', $response->headers['Location'] ?? null);
    }

    public function testLegacyImagePathRedirectsToCanonicalPublishedAsset(): void
    {
        $response = $this->frontController()->handle($this->request('GET', '/images/structure/banniere.jpg'));

        $this->assertSame(301, $response->status);
        $this->assertSame('/assets/images/structure/banniere.jpg', $response->headers['Location'] ?? null);
    }

    public function testMissingLegacyImagePathFallsBackToPlaceholderAsset(): void
    {
        $response = $this->frontController()->handle($this->request('GET', '/images/does-not-exist.jpg'));

        $this->assertSame(302, $response->status);
        $this->assertSame('/assets/images/structure/logo.png', $response->headers['Location'] ?? null);
    }

    public function testLegacySitePathRedirectsToCanonicalStructuredPageRoute(): void
    {
        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'villages',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/bouger/se-promener-dans-les-villages-du-golfe-de-sttropez.php',
                            'translations' => [
                                'fr' => [
                                    'title' => 'Villages',
                                ],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $response = $this->frontController()->handle(
            $this->request('GET', '/bouger/site/bouger/se-promener-dans-les-villages-du-golfe-de-sttropez.php?lang=fr')
        );

        $this->assertSame(301, $response->status);
        $this->assertSame(
            '/bouger/se-promener-dans-les-villages-du-golfe-de-sttropez.php?lang=fr',
            $response->headers['Location'] ?? null
        );
    }

    public function testLegacyMovedPageRouteRedirectsToCanonicalStructuredPage(): void
    {
        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'dyna-z12',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/auto-retro/panhard/la-dyna-modele-z12.php',
                            'translations' => [
                                'fr' => [
                                    'title' => 'La Dyna Z12',
                                ],
                            ],
                        ],
                        [
                            'slug' => 'aronde-history',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/auto-retro/simca/histoire-simca-aronde-icone-francaise.php',
                            'translations' => [
                                'fr' => [
                                    'title' => 'Histoire de l’Aronde',
                                ],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $panhardResponse = $this->frontController()->handle(
            $this->request('GET', '/auto-retro-panhard-la-dyna-modele-z12.php')
        );
        $this->assertSame(301, $panhardResponse->status);
        $this->assertSame(
            '/auto-retro/panhard/la-dyna-modele-z12.php',
            $panhardResponse->headers['Location'] ?? null
        );

        $simcaResponse = $this->frontController()->handle(
            $this->request('GET', '/auto-retro/simca/simca-aronde-icone-francaise.php')
        );
        $this->assertSame(301, $simcaResponse->status);
        $this->assertSame(
            '/auto-retro/simca/histoire-simca-aronde-icone-francaise.php',
            $simcaResponse->headers['Location'] ?? null
        );
    }

    public function testDynamicPageRewritesLegacyBlockLinksAndAvoidsBrokenImages(): void
    {
        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'balade',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/bouger/les-animations-dans-le-golfe-de-sttropez.php',
                            'translations' => [
                                'fr' => [
                                    'title' => 'Animations',
                                    'blocks' => [
                                        'EditRegion3' => '<p><a href="site/bouger/se-promener-dans-les-villages-du-golfe-de-sttropez.php">Villages</a></p>'
                                            . '<img src="/images/structure/banniere.jpg" alt="Banniere">'
                                            . '<img src="/images/does-not-exist.jpg" alt="Manquante">',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'slug' => 'villages',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/bouger/se-promener-dans-les-villages-du-golfe-de-sttropez.php',
                            'translations' => [
                                'fr' => [
                                    'title' => 'Villages',
                                ],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $response = $this->frontController()->handle(
            $this->request('GET', '/bouger/les-animations-dans-le-golfe-de-sttropez.php')
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString(
            'href="/bouger/se-promener-dans-les-villages-du-golfe-de-sttropez.php"',
            $response->body
        );
        $this->assertStringContainsString(
            'src="/assets/images/structure/banniere.jpg"',
            $response->body
        );
        $this->assertStringContainsString(
            'src="/assets/images/structure/logo.png"',
            $response->body
        );
    }

    public function testDynamicPageRendersArticlesAttachedToItsSlugAtBottom(): void
    {
        global $appConfig;
        $appConfig['site']['discussions'] = [
            'enabled' => true,
            'require_account' => false,
            'rate_limit_per_ip' => 6,
            'rate_limit_window' => 600,
            'global_rate_limit_per_ip' => 20,
            'global_rate_limit_window' => 3600,
            'min_form_fill_seconds' => 1,
            'max_form_age_seconds' => 7200,
            'honeypot_field' => 'website',
            'recaptcha' => [
                'enabled' => false,
                'site_key' => '',
                'secret_key' => '',
                'minimum_score' => 0.5,
                'timeout_seconds' => 8,
            ],
        ];

        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'association',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/association',
                            'translations' => [
                                'fr' => [
                                    'title' => 'Association',
                                    'regions' => [
                                        'hero' => [
                                            'component' => 'heading',
                                            'title' => 'Association',
                                        ],
                                        'body' => [
                                            'component' => 'rich_text',
                                            'html' => '<p>Contenu de page.</p>',
                                        ],
                                        'postscript' => [
                                            'component' => 'rich_text',
                                            'html' => '<h2>Sources</h2><p>Source de test.</p>',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $this->writeBlogArticle([
            'title' => 'Article ancien',
            'slug' => 'article-accroche',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-20 10:00:00',
            'content' => '<p>Article visible ancien.</p><p>FULL_OLD_PAYLOAD_SHOULD_NOT_BE_IN_INITIAL_DOM</p>',
            'excerpt' => 'Résumé ancien.',
            'page_slug' => 'association',
            'created_at' => '2026-03-25T10:00:00+00:00',
        ]);
        $this->writeBlogArticle([
            'title' => 'Article récent',
            'slug' => 'article-recent',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-24 10:00:00',
            'content' => '<p>Article visible recent.</p>',
            'page_slug' => 'association',
            'created_at' => '2026-03-19T10:00:00+00:00',
        ]);
        $this->writeBlogArticle([
            'title' => 'Article hors page',
            'slug' => 'article-hors-page',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-20 11:00:00',
            'content' => '<p>Hors page.</p>',
            'page_slug' => 'archives',
            'created_at' => '2026-03-20T11:00:00+00:00',
        ]);
        $this->writeBlogArticle([
            'title' => 'Article brouillon',
            'slug' => 'article-brouillon',
            'lang' => 'fr',
            'status' => 'draft',
            'date' => '2026-03-20 12:00:00',
            'content' => '<p>Brouillon.</p>',
            'page_slug' => 'association',
            'created_at' => '2026-03-20T12:00:00+00:00',
        ]);
        blog_discussion_repository()->submitPending('article-recent', 'fr', [
            'author' => 'Louise',
            'email' => 'louise@example.com',
            'content' => '<p>Message validé.</p>',
            'status' => 'approved',
        ]);

        $response = $this->frontController()->handle($this->request('GET', '/association'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Contenu de page.', $response->body);
        $this->assertStringContainsString('Chronique au fil du temps', $response->body);
        $this->assertStringContainsString('Article ancien', $response->body);
        $this->assertStringContainsString('Article récent', $response->body);
        $this->assertStringContainsString('Résumé ancien.', $response->body);
        $this->assertStringContainsString('article-accroche', $response->body);
        $this->assertStringContainsString('article-recent', $response->body);
        $this->assertStringNotContainsString('FULL_OLD_PAYLOAD_SHOULD_NOT_BE_IN_INITIAL_DOM', $response->body);
        $this->assertStringNotContainsString('Article hors page', $response->body);
        $this->assertStringNotContainsString('Article brouillon', $response->body);
        $this->assertStringContainsString('Sources', $response->body);
        $this->assertGreaterThan(
            strpos($response->body, 'Sources'),
            strpos($response->body, 'Chronique au fil du temps')
        );
        $this->assertMatchesRegularExpression(
            '/<details id="attached-article-article-recent" class="page-attached-article" >/',
            $response->body
        );
        $this->assertMatchesRegularExpression(
            '/<details id="attached-article-article-accroche" class="page-attached-article" >/',
            $response->body
        );
        $this->assertStringNotContainsString('Message validé.', $response->body);
        $this->assertStringNotContainsString('id="discussion-form-article-recent"', $response->body);
        $this->assertSame(0, substr_count($response->body, 'data-discussion-form'));
        $this->assertStringContainsString('open_discussion=1', $response->body);
        $this->assertStringContainsString('open_article=article-recent', $response->body);
        $this->assertStringContainsString('open_article=article-accroche', $response->body);
        $this->assertLessThan(
            strpos($response->body, 'article-accroche'),
            strpos($response->body, 'article-recent')
        );
        $elementCount = preg_match_all('/<[a-z][a-z0-9:-]*(?:\s|>)/i', $response->body, $matches);
        $this->assertIsInt($elementCount);
        $this->assertLessThanOrEqual(1300, $elementCount);
        $attachedSectionLength = $this->sectionLength(
            $response->body,
            '<section class="blog-list page-attached-articles"',
            '</section>'
        );
        $this->assertGreaterThan(0, $attachedSectionLength);
        $this->assertLessThanOrEqual(14000, $attachedSectionLength);

        $responseWithDiscussion = $this->frontController()->handle(
            $this->request('GET', '/association?open_article=article-recent&open_discussion=1')
        );

        $this->assertSame(200, $responseWithDiscussion->status);
        $this->assertStringContainsString('Message validé.', $responseWithDiscussion->body);
        $this->assertStringContainsString('discussion-form-article-recent', $responseWithDiscussion->body);
        $this->assertStringNotContainsString('discussion-form-article-accroche" aria-labelledby=', $responseWithDiscussion->body);
        $this->assertSame(1, substr_count($responseWithDiscussion->body, 'data-discussion-form'));
        $this->assertStringContainsString('name="return_to"', $responseWithDiscussion->body);
        $this->assertStringContainsString('name="article_slug" value="article-recent"', $responseWithDiscussion->body);
        $this->assertStringContainsString('name="article_lang" value="fr"', $responseWithDiscussion->body);
        $this->assertStringContainsString('/core/blog/submit_discussion.php', $responseWithDiscussion->body);
        $this->assertStringContainsString('data-discussion-log-endpoint="/core/blog/log_discussion_client.php"', $responseWithDiscussion->body);
    }

    public function testDiscussionClientLogEndpointAcceptsJsonPayload(): void
    {
        $response = $this->frontController()->handle(
            $this->jsonRequest('POST', '/core/blog/log_discussion_client.php', [
                'article_slug' => 'article-recent',
                'article_lang' => 'fr',
                'stage' => 'recaptcha_client_failure',
                'level' => 'error',
                'mode' => 'v3_score',
                'page' => '/fr/auto-retro/austin/histoire-de-austin.php?open_article=article-recent',
                'details' => [
                    'error_message' => 'recaptcha-execute-timeout',
                    'execute_timeout_ms' => 12000,
                ],
            ])
        );

        $this->assertSame(204, $response->status);
        $this->assertSame('no-store', $response->headers['Cache-Control'] ?? null);
    }

    public function testDiscussionClientLogEndpointIsRateLimitedPerIpThroughFrontController(): void
    {
        global $appConfig;
        $appConfig['site']['discussions']['telemetry_rate_limit_per_ip'] = 1;
        $appConfig['site']['discussions']['telemetry_rate_limit_window'] = 120;
        $appConfig['site']['discussions']['telemetry_sample_divisor'] = 1;

        $clientIp = '198.51.100.77';
        $payload = [
            'article_slug' => 'article-recent',
            'article_lang' => 'fr',
            'stage' => 'recaptcha_client_failure',
            'level' => 'error',
            'mode' => 'v3_score',
            'page' => '/fr/auto-retro/austin/histoire-de-austin.php',
            'details' => ['error_message' => 'recaptcha_error'],
        ];

        $request = new Request(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/core/blog/log_discussion_client.php',
                'REMOTE_ADDR' => $clientIp,
            ],
            [],
            [],
            [],
            ['Host' => '127.0.0.1:8000', 'Content-Type' => 'application/json'],
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''
        );

        $first = $this->frontController()->handle($request);
        $second = $this->frontController()->handle($request);

        $this->assertSame(204, $first->status);
        $this->assertSame('no-store', $first->headers['Cache-Control'] ?? null);
        $this->assertSame(204, $second->status);
        $this->assertSame('no-store', $second->headers['Cache-Control'] ?? null);

        $securityLogPath = $this->logDir . '/security.log';
        $this->assertFileExists($securityLogPath);

        $securityLogContents = (string) file_get_contents($securityLogPath);
        $this->assertStringContainsString('blog.discussion.client_telemetry_rate_limited', $securityLogContents);
    }

    public function testDiscussionPublicRenderingEscapesHtmlInStoredContent(): void
    {
        global $appConfig;
        $appConfig['site']['discussions'] = [
            'enabled' => true,
            'require_account' => false,
            'rate_limit_per_ip' => 6,
            'rate_limit_window' => 600,
            'global_rate_limit_per_ip' => 20,
            'global_rate_limit_window' => 3600,
            'telemetry_rate_limit_per_ip' => 45,
            'telemetry_rate_limit_window' => 120,
            'telemetry_sample_divisor' => 4,
            'min_form_fill_seconds' => 1,
            'max_form_age_seconds' => 7200,
            'honeypot_field' => 'website',
            'recaptcha' => [
                'enabled' => false,
                'site_key' => '',
                'secret_key' => '',
                'minimum_score' => 0.5,
                'timeout_seconds' => 8,
            ],
        ];

        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'association',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/association',
                            'translations' => [
                                'fr' => [
                                    'title' => 'Association',
                                    'regions' => [
                                        'body' => [
                                            'component' => 'rich_text',
                                            'html' => '<p>Contenu de page.</p>',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $this->writeBlogArticle([
            'title' => 'Article sécurisé',
            'slug' => 'article-secure',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-20 10:00:00',
            'content' => '<p>Article visible.</p>',
            'page_slug' => 'association',
            'created_at' => '2026-03-20T10:00:00+00:00',
        ]);

        $message = "<script>alert('x')</script>"
            . "<a href='https://example.com' onclick='steal()'>Lien</a>"
            . "<span class='x'>Texte</span><img src='x' onerror='alert(1)'>"
            . "<div style='color:red'>Bloc</div><style>body{}</style>Final";

        blog_discussion_repository()->submitPending(
            'article-secure',
            'fr',
            [
                'author' => 'Louise',
                'email' => 'louise@example.com',
                'content' => $message,
                'status' => 'approved',
                'created_at' => '2026-03-20T10:30:00+00:00',
                'updated_at' => '2026-03-20T10:30:00+00:00',
            ]
        );

        $response = $this->frontController()->handle(
            $this->request('GET', '/association?open_article=article-secure&open_discussion=1')
        );

        $this->assertSame(200, $response->status);

        $escapedMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $this->assertStringContainsString($escapedMessage, $response->body);

        $this->assertStringNotContainsString("<script>alert('x')</script>", $response->body);
        $this->assertStringNotContainsString("onclick='steal()'", $response->body);
        $this->assertStringNotContainsString("onerror='alert(1)'", $response->body);
        $this->assertStringNotContainsString('style=\'color:red\'', $response->body);
    }

    public function testBlogArticleRenderRewritesLegacyLocalUrls(): void
    {
        $this->writeBlogArticle([
            'title' => 'Article histoire',
            'slug' => 'article-histoire',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-20 10:00:00',
            'content' => '<p><a href="https://www.lescaramagnols.com/auto-retro/austin/histoire-de-austin.php?lang=en">Austin</a></p>'
                . '<img src="/images/structure/banniere.jpg" alt="Banniere">'
                . '<img src="/images/does-not-exist.jpg" alt="Manquante">',
        ]);

        $response = $this->frontController()->handle($this->request('GET', '/blog/article/article-histoire'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString(
            'href="/auto-retro/austin/histoire-de-austin.php?lang=en"',
            $response->body
        );
        $this->assertStringContainsString(
            'src="/assets/images/structure/banniere.jpg"',
            $response->body
        );
        $this->assertStringContainsString(
            'src="/assets/images/structure/logo.png"',
            $response->body
        );
    }

    public function testBlogHubPrefersAttachedParentUrlsAndKeepsFallbackWithoutBlogReturn(): void
    {
        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'association',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/association',
                            'translations' => [
                                'fr' => ['title' => 'Association'],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $this->writeBlogArticle([
            'title' => 'Article attache',
            'slug' => 'article-attache',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-20 10:00:00',
            'content' => '<p>Attache.</p>',
            'page_slug' => 'association',
        ]);
        $this->writeBlogArticle([
            'title' => 'Article solo',
            'slug' => 'article-solo',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-21 10:00:00',
            'content' => '<p>Solo.</p>',
        ]);

        $response = $this->frontController()->handle($this->request('GET', '/blog'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString(
            '/fr/association?open_article=article-attache#attached-article-article-attache',
            $response->body
        );
        $this->assertStringContainsString(
            '/blog/article/article-solo',
            $response->body
        );
        $this->assertStringNotContainsString('blog_return=', $response->body);
    }

    public function testBlogHubRendersEditorialPageCardsAndPagination(): void
    {
        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'association',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/association',
                            'translations' => [
                                'fr' => ['title' => 'Association'],
                            ],
                        ],
                        [
                            'slug' => 'blog',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/blog',
                            'translations' => [
                                'fr' => [
                                    'title' => 'Le Blog Les Caramagnols',
                                    'regions' => [
                                        'hero' => [
                                            'component' => 'heading',
                                            'title' => 'Le Blog Les Caramagnols',
                                        ],
                                        'intro' => [
                                            'component' => 'rich_text',
                                            'html' => '<p>Chaque article complète une page principale du site et permet d’approfondir un sujet précis.</p>',
                                        ],
                                    ],
                                    'meta' => [
                                        'description' => 'Hub éditorial Les Caramagnols.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
        );

        for ($index = 1; $index <= 13; $index++) {
            $this->writeBlogArticle([
                'title' => 'Article ' . $index,
                'slug' => 'article-' . $index,
                'lang' => 'fr',
                'status' => 'published',
                'date' => sprintf('2026-04-%02d 10:00:00', 30 - $index),
                'category' => $index % 2 === 0 ? 'patrimoine' : 'auto-retro',
                'tags' => ['histoire', 'collection', 'modele'],
                'content' => '<p>Contenu ' . $index . '.</p>',
                'excerpt' => 'Extrait ' . $index . '.',
                'page_slug' => $index === 1 ? 'association' : '',
                'featured_image' => [
                    'src' => '/uploads/editorial/article-' . $index . '.jpg',
                    'alt' => 'Visuel ' . $index,
                    'title' => 'Visuel ' . $index,
                    'width' => 1200,
                    'height' => 630,
                ],
            ]);
        }

        $response = $this->frontController()->handle($this->request('GET', '/fr/blog'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Le Blog Les Caramagnols', $response->body);
        $this->assertStringContainsString('Chaque article complète une page principale du site', $response->body);
        $this->assertSame(12, substr_count($response->body, 'class="blog-card blog-hub-card"'));
        $this->assertStringContainsString(
            '/fr/association?open_article=article-1#attached-article-article-1',
            $response->body
        );
        $this->assertStringContainsString('Rattaché à', $response->body);
        $this->assertStringContainsString('Lire l’article', $response->body);
        $this->assertStringContainsString('/fr/blog?page=2', $response->body);
        $this->assertStringContainsString('Page 1 sur 2', $response->body);
        $this->assertStringNotContainsString('Article 13', $response->body);
        $this->assertStringNotContainsString('blog_return=', $response->body);
        $this->assertStringNotContainsString('/tag/', $response->body);

        $pageTwoResponse = $this->frontController()->handle($this->request('GET', '/fr/blog?page=2'));

        $this->assertSame(200, $pageTwoResponse->status);
        $this->assertStringContainsString('Article 13', $pageTwoResponse->body);
        $this->assertStringContainsString('Page 2 sur 2', $pageTwoResponse->body);
        $this->assertStringContainsString('Articles 13 à 13 sur 13', $pageTwoResponse->body);
    }

    /**
     * @runInSeparateProcess
     */
    public function testBlogHubRendersFooterMenuItems(): void
    {
        file_put_contents(
            $this->menusFile,
            json_encode(
                [
                    'menu3' => [
                        [
                            'titre' => 'Mentions légales',
                            'chemin' => '/mentions-legales',
                        ],
                        [
                            'titre' => 'Sommaire',
                            'chemin' => '/plan-du-site',
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        $this->writeBlogArticle([
            'title' => 'Article blog',
            'slug' => 'article-blog',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-04-20 10:00:00',
            'category' => 'auto-retro',
            'tags' => ['histoire', 'collection', 'modele'],
            'content' => '<p>Contenu blog.</p>',
            'excerpt' => 'Extrait blog.',
        ]);

        $response = $this->frontController()->handle($this->request('GET', '/blog'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('id="nav-menu-3"', $response->body);
        $this->assertStringContainsString('Mentions légales', $response->body);
        $this->assertStringContainsString('Sommaire', $response->body);
    }

    public function testBlogArticleRouteRedirectsToAttachedParentUrlWhenPageSlugExists(): void
    {
        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'association',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/association',
                            'translations' => [
                                'fr' => ['title' => 'Association'],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $this->writeBlogArticle([
            'title' => 'Article attache',
            'slug' => 'article-attache',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-20 10:00:00',
            'content' => '<p>Attache.</p>',
            'page_slug' => 'association',
        ]);

        $response = $this->frontController()->handle($this->request('GET', '/blog/article/article-attache'));

        $this->assertSame(301, $response->status);
        $this->assertSame(
            '/fr/association?open_article=article-attache#attached-article-article-attache',
            $response->headers['Location'] ?? null
        );
    }

    public function testDynamicPageUsesAttachedCanonicalWhenOpenArticleIsRequested(): void
    {
        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'association',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/association',
                            'translations' => [
                                'fr' => [
                                    'title' => 'Association',
                                    'regions' => [
                                        'body' => [
                                            'component' => 'rich_text',
                                            'html' => '<p>Contenu de page.</p>',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $this->writeBlogArticle([
            'title' => 'Article attache',
            'slug' => 'article-attache',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-20 10:00:00',
            'content' => '<p>Attache.</p>',
            'page_slug' => 'association',
        ]);

        $response = $this->frontController()->handle(
            $this->request('GET', '/association?open_article=article-attache')
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString(
            '<link rel="canonical" href="http://127.0.0.1:8000/fr/association?open_article=article-attache" />',
            $response->body
        );
        $this->assertStringContainsString(
            'id="attached-article-article-attache"',
            $response->body
        );
    }

    public function testDynamicPageRendersSharedMediaGalleryFromRootMeta(): void
    {
        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'association',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/association',
                            'meta' => [
                                'shared_media' => [
                                    [
                                        'src' => '/uploads/editorial/media/2026/03/simca-front.webp',
                                        'alt' => 'Simca stationnee',
                                        'title' => 'Sortie club',
                                        'caption' => 'Rassemblement printanier',
                                        'width' => 1600,
                                        'height' => 900,
                                    ],
                                ],
                            ],
                            'translations' => [
                                'fr' => [
                                    'title' => 'Association',
                                    'regions' => [
                                        'body' => [
                                            'component' => 'rich_text',
                                            'html' => '<p>Contenu de page.</p>',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $response = $this->frontController()->handle($this->request('GET', '/association'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('page-shared-media', $response->body);
        $this->assertStringContainsString('/uploads/editorial/media/2026/03/simca-front.webp', $response->body);
        $this->assertStringContainsString('Rassemblement printanier', $response->body);
    }

    public function testHomeDynamicPageRendersInstagramFeedFromCacheWhenEnabled(): void
    {
        global $appConfig;
        $accessToken = 'front-controller-instagram-token';
        $appConfig['site']['instagram'] = [
            'enabled' => true,
            'username' => 'paulineetnoel',
            'user_id' => '',
            'access_token' => $accessToken,
            'limit' => 4,
            'rotation_interval_ms' => 5200,
            'cache_ttl_seconds' => 1800,
            'timeout_seconds' => 8,
            'cache_path' => $this->instagramCacheFile,
        ];

        $fingerprint = hash('sha256', $accessToken . '|' . '' . '|' . 'paulineetnoel');
        file_put_contents(
            $this->instagramCacheFile,
            json_encode(
                [
                    'fingerprint' => $fingerprint,
                    'fetched_at' => time(),
                    'username' => 'paulineetnoel',
                    'posts' => [
                        [
                            'id' => '1789',
                            'caption' => 'Post Instagram en cache',
                            'imageUrl' => 'https://cdn.example.test/instagram.webp',
                            'permalink' => 'https://www.instagram.com/p/cache-post',
                            'mediaType' => 'IMAGE',
                            'timestamp' => '2026-03-18T19:00:00+00:00',
                        ],
                    ],
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'home',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/',
                            'translations' => [
                                'fr' => [
                                    'title' => 'Accueil',
                                    'regions' => [
                                        'hero' => [
                                            'component' => 'heading',
                                            'title' => 'Accueil',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'slug' => 'blog',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/blog',
                            'translations' => [
                                'fr' => [
                                    'title' => 'Le Blog Les Caramagnols',
                                ],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $response = $this->frontController()->handle($this->request('GET', '/'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Derniers posts Instagram', $response->body);
        $this->assertStringContainsString('Post Instagram en cache', $response->body);
        $this->assertStringContainsString('https://www.instagram.com/p/cache-post', $response->body);
    }

    /**
     * @runInSeparateProcess
     */
    public function testDynamicHomePageRendersFooterMenuItems(): void
    {
        file_put_contents(
            $this->menusFile,
            json_encode(
                [
                    'menu3' => [
                        [
                            'titre' => 'Mentions légales',
                            'chemin' => '/mentions-legales',
                        ],
                        [
                            'titre' => 'Plan du site',
                            'chemin' => '/plan-du-site',
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'home',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/',
                            'translations' => [
                                'fr' => [
                                    'title' => 'Accueil',
                                    'regions' => [
                                        'hero' => [
                                            'component' => 'heading',
                                            'title' => 'Accueil',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $response = $this->frontController()->handle($this->request('GET', '/'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('id="nav-menu-3"', $response->body);
        $this->assertStringContainsString('Mentions légales', $response->body);
        $this->assertStringContainsString('Plan du site', $response->body);
    }

    public function testHeaderNavigationIncludesBlogEntryFromCanonicalRegistry(): void
    {
        menus_data_set_path_override(null);
        navigation_view_model_cache_clear();

        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'home',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/',
                            'translations' => [
                                'fr' => [
                                    'title' => 'Accueil',
                                    'regions' => [
                                        'hero' => [
                                            'component' => 'heading',
                                            'title' => 'Accueil',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $response = $this->frontController()->handle($this->request('GET', '/'));

        $this->assertSame(200, $response->status);
        $canonicalNavigation = load_navigation_canonical();
        $blogItem = null;

        foreach (($canonicalNavigation['locations']['primary'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (($item['target']['pageSlug'] ?? null) === 'blog') {
                $blogItem = $item;
                break;
            }
        }

        $this->assertIsArray($blogItem);
        $this->assertSame('blog', $blogItem['target']['pageSlug'] ?? null);
        $this->assertMatchesRegularExpression('/>\s*Blog\s*</i', $response->body);
    }

    public function testSitemapRouteReturnsPublishedPagesAndArticlesOnly(): void
    {
        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'association',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/association',
                            'updated_at' => '2026-03-19T12:00:00+00:00',
                            'translations' => [
                                'fr' => [
                                    'title' => 'Association',
                                ],
                            ],
                        ],
                        [
                            'slug' => 'brouillon',
                            'type' => 'structured_page',
                            'status' => 'draft',
                            'layout' => 'standard_page',
                            'route' => '/brouillon',
                            'translations' => [
                                'fr' => [
                                    'title' => 'Brouillon',
                                ],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $this->writeBlogArticle([
            'title' => 'Article publié',
            'slug' => 'article-publie',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-20 10:00:00',
            'content' => '<p>Publié.</p>',
            'updated_at' => '2026-03-20T10:00:00+00:00',
        ]);
        $this->writeBlogArticle([
            'title' => 'Article brouillon',
            'slug' => 'article-brouillon',
            'lang' => 'fr',
            'status' => 'draft',
            'date' => '2026-03-20 11:00:00',
            'content' => '<p>Brouillon.</p>',
        ]);

        $response = $this->frontController()->handle($this->request('GET', '/sitemap.xml'));

        $this->assertSame(200, $response->status);
        $this->assertSame('application/xml; charset=UTF-8', $response->headers['Content-Type'] ?? null);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $response->body);
        $this->assertStringContainsString('<loc>http://127.0.0.1:8000/association</loc>', $response->body);
        $this->assertStringContainsString('<loc>http://127.0.0.1:8000/blog/article/article-publie</loc>', $response->body);
        $this->assertStringNotContainsString('/brouillon</loc>', $response->body);
        $this->assertStringNotContainsString('article-brouillon', $response->body);
    }

    public function testRobotsRouteAdvertisesSitemapAndDisallowsAdminLoginPath(): void
    {
        $response = $this->frontController()->handle($this->request('GET', '/robots.txt'));

        $this->assertSame(200, $response->status);
        $this->assertSame('text/plain; charset=UTF-8', $response->headers['Content-Type'] ?? null);
        $this->assertStringContainsString('User-agent: *', $response->body);
        $this->assertStringContainsString('Disallow: /admin', $response->body);
        $this->assertStringContainsString('Sitemap: http://127.0.0.1:8000/sitemap.xml', $response->body);
    }

    public function testFrontVisitIsLoggedWithVisitorContext(): void
    {
        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'association',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/association',
                            'translations' => [
                                'fr' => [
                                    'title' => 'Association',
                                ],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $response = $this->frontController()->handle(
            $this->request(
                'GET',
                '/association?origine=campagne',
                [],
                [],
                '198.51.100.24',
                [
                    'User-Agent' => 'VisitorTest/2.0',
                    'Referer' => 'https://example.test/news',
                ]
            )
        );

        $this->assertSame(200, $response->status);

        $accessLogPath = $this->logDir . '/access.log';
        $this->assertFileExists($accessLogPath);

        $logContents = (string) file_get_contents($accessLogPath);
        $this->assertStringContainsString('site.visit.page', $logContents);
        $this->assertStringContainsString('198.51.100.24', $logContents);
        $this->assertStringContainsString('VisitorTest/2.0', $logContents);
        $this->assertStringContainsString('/association?origine=campagne', $logContents);
        $this->assertStringContainsString('visitor_id', $logContents);
    }

    public function testRequestIdIsReturnedAndLoggedForTraceability(): void
    {
        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'association',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/association',
                            'translations' => [
                                'fr' => [
                                    'title' => 'Association',
                                ],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $response = $this->frontController()->handle(
            $this->request(
                'GET',
                '/association',
                [],
                [],
                '198.51.100.25',
                [
                    'User-Agent' => 'TraceabilityTest/1.0',
                    'X-Request-Id' => 'req-front-1234',
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertSame('req-front-1234', $response->headers['X-Request-Id'] ?? null);

        $accessLogPath = $this->logDir . '/access.log';
        $this->assertFileExists($accessLogPath);
        $logContents = (string) file_get_contents($accessLogPath);
        $this->assertStringContainsString('"request_id":"req-front-1234"', $logContents);
    }

    public function testUnhandledRenderFailureIsLoggedAndReturnedAsHttp500(): void
    {
        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'association',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/association',
                            'translations' => [
                                'fr' => [
                                    'title' => 'Association',
                                ],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $layoutPath = TEMPLATES_PATH . '/partials/layout.php';
        $originalLayout = (string) file_get_contents($layoutPath);
        file_put_contents($layoutPath, "<?php\nthrow new RuntimeException('Simulated layout failure');\n");

        try {
            $response = $this->frontController()->handle(
                $this->request(
                    'GET',
                    '/association',
                    [],
                    [],
                    '198.51.100.26',
                    [
                        'User-Agent' => 'FailureTest/1.0',
                        'X-Request-Id' => 'req-front-500',
                    ]
                )
            );

            $this->assertSame(500, $response->status);
            $this->assertSame('req-front-500', $response->headers['X-Request-Id'] ?? null);
            $this->assertStringContainsString('Impossible actuellement de traiter cette demande.', $response->body);

            $accessLogPath = $this->logDir . '/access.log';
            $this->assertFileExists($accessLogPath);

            $logContents = (string) file_get_contents($accessLogPath);
            $this->assertStringContainsString('site.request.error', $logContents);
            $this->assertStringContainsString('"request_id":"req-front-500"', $logContents);
            $this->assertStringContainsString('"status":500', $logContents);
            $this->assertStringContainsString('FailureTest/1.0', $logContents);
        } finally {
            file_put_contents($layoutPath, $originalLayout);
        }
    }

    private function sectionLength(string $html, string $startMarker, string $endMarker): int
    {
        $start = strpos($html, $startMarker);
        if (!is_int($start)) {
            return 0;
        }

        $end = strpos($html, $endMarker, $start);
        if (!is_int($end)) {
            return 0;
        }

        $end += strlen($endMarker);

        return max(0, $end - $start);
    }

    private function frontController(): FrontController
    {
        $routeResolver = new AdminRouteResolver('admin');
        $logger = new AppEventLogger(new LoggerFactory($this->logDir, 'test'));

        return new FrontController(
            $routeResolver,
            new AdminController(
                $routeResolver,
                $logger,
                null,
                null,
                new AdminSettingsService($this->databaseOverrideFile, $this->adminOverrideFile, $logger)
            ),
            new BlogApiController(
                new BlogSaveService(blog_repository(), $logger),
                $logger
            ),
            $logger
        );
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, string> $headers
     */
    private function request(
        string $method,
        string $uri,
        array $query = [],
        array $post = [],
        string $remoteAddr = '127.0.0.1',
        array $headers = []
    ): Request {
        return new Request(
            [
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $uri,
                'REMOTE_ADDR' => $remoteAddr,
            ],
            $query,
            $post,
            [],
            array_merge(['Host' => '127.0.0.1:8000'], $headers)
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonRequest(string $method, string $uri, array $payload): Request
    {
        return new Request(
            [
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $uri,
                'REMOTE_ADDR' => '127.0.0.1',
            ],
            [],
            [],
            [],
            ['Host' => '127.0.0.1:8000', 'Content-Type' => 'application/json'],
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''
        );
    }

    /**
     * @param array<string, mixed> $article
     */
    private function writeBlogArticle(array $article): void
    {
        $path = $this->blogDir . '/' . $article['slug'] . '.' . $article['lang'] . '.json';
        file_put_contents($path, json_encode($article, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
