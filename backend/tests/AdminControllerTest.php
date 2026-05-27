<?php

declare(strict_types=1);

use Caramagnols\Admin\AdminController;
use Caramagnols\Admin\AdminCronCenterService;
use Caramagnols\Admin\AdminRouteResolver;
use Caramagnols\Admin\AdminSettingsService;
use Caramagnols\Cron\CronJobRepository;
use Caramagnols\Http\Request;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\Logging\LoggerFactory;
use Caramagnols\Social\InstagramFeedService;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/auth/admin.php';
require_once ROOT_PATH . '/core/menu_loader.php';

final class AdminControllerTest extends TestCase
{
    use EditorialSqlTestTrait;

    private static ?array $baselineDatabaseConfig = null;
    private static ?string $baselineDatabasePrefix = null;

    private string $logDir;
    private string $rateLimitDir;
    private string $blogDir;
    private string $pagesFile;
    private string $menusFile;
    private string $databaseOverrideFile;
    private string $adminOverrideFile;
    private string $siteOverrideFile;
    private ?string $previousBlogDataDir = null;
    /** @var array<int, string> */
    private array $uploadedRuntimeFiles = [];

    protected function setUp(): void
    {
        ensure_session_started();
        $_SESSION = [];

        global $appConfig;
        if (self::$baselineDatabaseConfig === null) {
            self::$baselineDatabaseConfig = is_array($appConfig['database'] ?? null) ? $appConfig['database'] : [];
            self::$baselineDatabasePrefix = (string) ($appConfig['database_prefix'] ?? 'car_');
        }

        $appConfig['database'] = self::$baselineDatabaseConfig;
        $appConfig['database_prefix'] = self::$baselineDatabasePrefix ?? 'car_';
        $appConfig['admin']['email'] = 'admin@example.com';
        $appConfig['admin']['language'] = 'fr';
        $appConfig['admin']['password_hash'] = password_hash('topsecret', PASSWORD_DEFAULT);
        $appConfig['admin']['session_key'] = '_admin_controller_test';
        $appConfig['admin']['login_path'] = 'admin';
        $appConfig['admin']['allowed_ips'] = [];
        $appConfig['admin']['trust_proxy_headers'] = false;
        $appConfig['admin']['login_rate_limit_attempts'] = 5;
        $appConfig['admin']['login_rate_limit_window'] = 900;
        $appConfig['admin']['inactivity_timeout_seconds'] = 1200;
        $appConfig['admin']['reauth_timeout_seconds'] = 600;
        $appConfig['admin']['totp_enabled'] = false;
        $appConfig['admin']['totp_secret'] = '';
        $appConfig['admin']['totp_skip_localhost'] = true;
        $GLOBALS['langTranslations'] = load_translations_cached('fr');
        $appConfig['site']['head_metadata_html'] = '';
        $appConfig['site']['url'] = [
            'domain' => '',
            'ssl_domain' => '',
            'base_path' => '/',
        ];
        $appConfig['site']['tarteaucitron'] = [
            'enabled' => true,
            'privacy_url' => '/',
            'orientation' => 'bottom',
            'icon_position' => 'BottomRight',
            'show_icon' => true,
            'show_alert_small' => true,
            'high_privacy' => true,
            'accept_all_cta' => true,
            'deny_all_cta' => true,
            'mandatory' => true,
            'google_consent_mode' => true,
            'bing_consent_mode' => true,
            'user_config_json' => '{}',
            'services' => [],
        ];

        $this->logDir = sys_get_temp_dir() . '/caramagnols-admin-logs-' . bin2hex(random_bytes(6));
        mkdir($this->logDir, 0777, true);
        $this->rateLimitDir = sys_get_temp_dir() . '/caramagnols-admin-rate-limits-' . bin2hex(random_bytes(6));
        mkdir($this->rateLimitDir, 0777, true);
        $appConfig['security']['rate_limit_dir'] = $this->rateLimitDir;
        $this->blogDir = sys_get_temp_dir() . '/caramagnols-admin-blog-' . bin2hex(random_bytes(6));
        mkdir($this->blogDir, 0777, true);
        $this->previousBlogDataDir = is_string($appConfig['blog']['data_dir'] ?? null) ? $appConfig['blog']['data_dir'] : null;
        $appConfig['blog']['data_dir'] = $this->blogDir;

        $this->pagesFile = ROOT_PATH . '/var/admin-pages-' . uniqid() . '.json';
        $this->menusFile = ROOT_PATH . '/var/admin-menus-' . uniqid() . '.json';
        $this->databaseOverrideFile = ROOT_PATH . '/var/admin-database-override-' . uniqid() . '.php';
        $this->adminOverrideFile = ROOT_PATH . '/var/admin-credentials-override-' . uniqid() . '.php';
        $this->siteOverrideFile = ROOT_PATH . '/var/admin-site-override-' . uniqid() . '.php';
        pages_data_set_path_override($this->pagesFile);
        menus_data_set_path_override($this->menusFile);
        pages_cache_clear();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        global $appConfig;
        $appConfig['database'] = self::$baselineDatabaseConfig ?? [];
        $appConfig['database_prefix'] = self::$baselineDatabasePrefix ?? 'car_';
        $appConfig['site']['head_metadata_html'] = '';
        $appConfig['site']['tarteaucitron'] = [];

        $this->removeDirectoryRecursively($this->logDir);
        $this->removeDirectoryRecursively($this->rateLimitDir);
        $this->removeDirectoryRecursively($this->blogDir);
        if ($this->previousBlogDataDir !== null) {
            $appConfig['blog']['data_dir'] = $this->previousBlogDataDir;
        } else {
            unset($appConfig['blog']['data_dir']);
        }

        if (file_exists($this->pagesFile)) {
            unlink($this->pagesFile);
        }

        if (file_exists($this->pagesFile . '.bak')) {
            unlink($this->pagesFile . '.bak');
        }

        if (file_exists($this->menusFile)) {
            unlink($this->menusFile);
        }

        if (file_exists($this->menusFile . '.bak')) {
            unlink($this->menusFile . '.bak');
        }

        foreach ([$this->databaseOverrideFile, $this->adminOverrideFile, $this->siteOverrideFile] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        foreach ($this->uploadedRuntimeFiles as $uploadedRuntimeFile) {
            if (is_string($uploadedRuntimeFile) && $uploadedRuntimeFile !== '' && file_exists($uploadedRuntimeFile)) {
                @unlink($uploadedRuntimeFile);
            }
        }
        $this->uploadedRuntimeFiles = [];

        $this->cleanupEditorialSqlDatabase();
        pages_data_set_path_override(null);
        menus_data_set_path_override(null);
        pages_cache_clear();
    }

    public function testLoginPageRenders(): void
    {
        $controller = $this->controller();

        $response = $controller->handle('login', $this->request('GET', '/admin'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Connexion Admin', $response->body);
    }

    public function testDashboardRedirectsWhenUnauthenticated(): void
    {
        $controller = $this->controller();

        $response = $controller->handle('dashboard', $this->request('GET', '/admin/dashboard'));

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin', $response->headers['Location']);
    }

    public function testAdminPageReturnsForbiddenWhenIpIsNotAllowlisted(): void
    {
        global $appConfig;
        $appConfig['admin']['allowed_ips'] = ['192.168.1.0/24'];

        $controller = $this->controller();
        $response = $controller->handle(
            'dashboard',
            $this->request('GET', '/admin/dashboard', [], [], '10.0.0.5')
        );

        $this->assertSame(403, $response->status);
        $this->assertStringContainsString('Accès admin interdit', $response->body);
    }

    public function testTilesPageRendersWhenAuthenticated(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->handle('tiles', $this->request('GET', '/admin/tiles'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Groupes de tuiles', $response->body);
    }

    public function testTilesPageRendersDuplicateSuccessFlashMessage(): void
    {
        admin_login('admin@example.com', 'topsecret');
        admin_set_flash_message(
            'success',
            'Duplication réussie : le groupe #7 a été recopié dans le groupe #8 sous le titre "Austin - copie" avec 6 tuile(s) copiée(s).'
        );
        $controller = $this->controller();

        $response = $controller->handle('tiles', $this->request('GET', '/admin/tiles'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString(
            '<div class="notice notice-success">Duplication réussie : le groupe #7 a été recopié dans le groupe #8 sous le titre &quot;Austin - copie&quot; avec 6 tuile(s) copiée(s).</div>',
            $response->body
        );
        $this->assertNull(admin_pop_flash_message());
    }

    public function testTilesPageRendersDuplicateErrorFlashMessage(): void
    {
        admin_login('admin@example.com', 'topsecret');
        admin_set_flash_message(
            'error',
            'Duplication impossible pour le groupe #7 : Groupe de tuiles introuvable.'
        );
        $controller = $this->controller();

        $response = $controller->handle('tiles', $this->request('GET', '/admin/tiles'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString(
            '<div class="notice notice-error">Duplication impossible pour le groupe #7 : Groupe de tuiles introuvable.</div>',
            $response->body
        );
        $this->assertNull(admin_pop_flash_message());
    }

    public function testDashboardRendersLiveEditorialCounts(): void
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
                                'en' => ['title' => 'Association'],
                            ],
                        ],
                        [
                            'slug' => 'archives',
                            'type' => 'structured_page',
                            'status' => 'draft',
                            'route' => '/archives',
                            'translations' => [
                                'fr' => ['title' => 'Archives'],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );
        file_put_contents(
            $this->menusFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'locations' => [
                        'banner' => ['headline' => ['text' => 'Bienvenue']],
                        'remonter' => ['label' => 'Top'],
                        'utility' => [
                            [
                                'id' => 'utility-facebook',
                                'kind' => 'external',
                                'label' => ['text' => 'Facebook'],
                                'target' => ['url' => 'https://facebook.example'],
                                'children' => [],
                            ],
                        ],
                        'primary' => [
                            [
                                'id' => 'primary-club',
                                'kind' => 'group',
                                'label' => ['text' => 'Club'],
                                'target' => [],
                                'children' => [
                                    [
                                        'id' => 'primary-association',
                                        'kind' => 'page',
                                        'label' => ['text' => 'Association'],
                                        'target' => ['pageSlug' => 'association'],
                                        'children' => [],
                                    ],
                                ],
                            ],
                        ],
                        'footer' => [],
                        'sideLeft' => [
                            [
                                'id' => 'side-left-card',
                                'kind' => 'content_card',
                                'label' => ['text' => 'Carte club'],
                                'target' => [],
                                'children' => [],
                            ],
                        ],
                        'sideRight' => [],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );
        $this->writeBlogArticle([
            'title' => 'Sortie du mois',
            'slug' => 'sortie-du-mois',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-17 10:00:00',
            'content' => '<p>Sortie.</p>',
            'category' => 'Sorties',
            'tags' => ['Club'],
        ]);
        $this->writeBlogArticle([
            'title' => 'Monthly outing',
            'slug' => 'sortie-du-mois',
            'lang' => 'en',
            'status' => 'published',
            'date' => '2026-03-17 10:00:00',
            'content' => '<p>Outing.</p>',
            'category' => 'Sorties',
            'tags' => ['Club'],
        ]);
        $this->writeBlogArticle([
            'title' => 'Monatsausfahrt',
            'slug' => 'sortie-du-mois',
            'lang' => 'de',
            'status' => 'published',
            'date' => '2026-03-17 10:00:00',
            'content' => '<p>Ausfahrt.</p>',
            'category' => 'Sorties',
            'tags' => ['Club'],
        ]);
        $this->writeBlogArticle([
            'title' => 'Infos parking',
            'slug' => 'infos-parking',
            'lang' => 'fr',
            'status' => 'draft',
            'date' => '2026-03-18 10:00:00',
            'content' => '<p>Parking.</p>',
            'parent_slug' => 'sortie-du-mois',
            'parent_lang' => 'fr',
            'child_sort_order' => 1,
            'category' => 'Sorties',
            'tags' => ['Club', 'Infos'],
        ]);
        $this->writeBlogArticle([
            'title' => 'News anglaise',
            'slug' => 'english-news',
            'lang' => 'en',
            'status' => 'published',
            'date' => '2026-03-19 10:00:00',
            'content' => '<p>News.</p>',
            'category' => 'News',
            'tags' => ['International'],
        ]);

        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->handle('dashboard', $this->request('GET', '/admin/dashboard'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Aucun message en attente. La modération est à jour.', $response->body);
        $this->assertStringContainsString('Pages : 2 (1 publiées / 1 brouillons).', $response->body);
        $this->assertStringContainsString('Articles : 3 (2 publiés / 1 brouillons).', $response->body);
        $this->assertStringContainsString('Brouillons à traiter : 2.', $response->body);
        $this->assertStringContainsString('Menus : 4 entrées.', $response->body);
    }

    public function testLoginPostRedirectsToDashboardWhenCredentialsAreValid(): void
    {
        $controller = $this->controller();
        $token = admin_csrf_token();

        $response = $controller->handle(
            'login',
            $this->request(
                'POST',
                '/admin',
                [],
                [
                    'csrf_token' => $token,
                    'email' => 'admin@example.com',
                    'password' => 'topsecret',
                ]
            )
        );

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/dashboard', $response->headers['Location']);
        $this->assertTrue(admin_is_authenticated());
    }

    public function testLoginSuccessWritesConnectionContextToSecurityLog(): void
    {
        $controller = $this->controller();
        $token = admin_csrf_token();

        $response = $controller->handle(
            'login',
            $this->request(
                'POST',
                '/admin',
                [],
                [
                    'csrf_token' => $token,
                    'identifier' => 'admin@example.com',
                    'password' => 'topsecret',
                ],
                '203.0.113.42',
                [
                    'User-Agent' => 'AdminAgent/5.0',
                    'Referer' => 'https://example.test/admin',
                ]
            )
        );

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/dashboard', $response->headers['Location']);

        $securityLogPath = $this->logDir . '/security.log';
        $this->assertFileExists($securityLogPath);

        $logContents = (string) file_get_contents($securityLogPath);
        $this->assertStringContainsString('admin.login.connected', $logContents);
        $this->assertStringContainsString('203.0.113.42', $logContents);
        $this->assertStringContainsString('AdminAgent/5.0', $logContents);
        $this->assertStringContainsString('/admin', $logContents);
    }

    public function testLoginPostUnknownIdentifierReturnsGenericErrorAndLogsSpecificFailureReason(): void
    {
        global $appConfig;
        $appConfig['admin']['totp_enabled'] = false;
        $appConfig['admin']['totp_secret'] = '';
        $appConfig['admin']['totp_skip_localhost'] = true;

        $controller = $this->controller();
        $token = admin_csrf_token();

        $response = $controller->handle(
            'login',
            $this->request(
                'POST',
                '/admin',
                [],
                [
                    'csrf_token' => $token,
                    'identifier' => 'unknown@example.com',
                    'password' => 'topsecret',
                ],
                '203.0.113.50'
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Identifiants invalides.', $response->body);
        $this->assertStringNotContainsString('inconnu', $response->body);

        $securityLogPath = $this->logDir . '/security.log';
        $this->assertFileExists($securityLogPath);
        $logContents = (string) file_get_contents($securityLogPath);

        $this->assertStringContainsString('admin.login.failed', $logContents);
        $this->assertStringContainsString('identifier_mismatch', $logContents);
    }

    public function testLoginPostWrongPasswordReturnsGenericErrorAndLogsSpecificFailureReason(): void
    {
        global $appConfig;
        $appConfig['admin']['totp_enabled'] = false;
        $appConfig['admin']['totp_secret'] = '';
        $appConfig['admin']['totp_skip_localhost'] = true;

        $controller = $this->controller();
        $token = admin_csrf_token();

        $response = $controller->handle(
            'login',
            $this->request(
                'POST',
                '/admin',
                [],
                [
                    'csrf_token' => $token,
                    'identifier' => 'admin@example.com',
                    'password' => 'wrong-password',
                ],
                '203.0.113.50'
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Identifiants invalides.', $response->body);

        $securityLogPath = $this->logDir . '/security.log';
        $this->assertFileExists($securityLogPath);
        $logContents = (string) file_get_contents($securityLogPath);

        $this->assertStringContainsString('admin.login.failed', $logContents);
        $this->assertStringContainsString('password_mismatch', $logContents);
    }

    public function testLoginPostMissingTotpCodeReturnsGenericErrorAndLogsSpecificFailureReason(): void
    {
        global $appConfig;
        $appConfig['admin']['totp_enabled'] = true;
        $appConfig['admin']['totp_secret'] = 'JBSWY3DPEHPK3PXP';
        $appConfig['admin']['totp_skip_localhost'] = false;

        $controller = $this->controller();
        $token = admin_csrf_token();

        $response = $controller->handle(
            'login',
            $this->request(
                'POST',
                '/admin',
                [],
                [
                    'csrf_token' => $token,
                    'identifier' => 'admin@example.com',
                    'password' => 'topsecret',
                ],
                '198.51.100.23'
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Identifiants invalides.', $response->body);

        $securityLogPath = $this->logDir . '/security.log';
        $this->assertFileExists($securityLogPath);
        $logContents = (string) file_get_contents($securityLogPath);

        $this->assertStringContainsString('admin.login.failed', $logContents);
        $this->assertStringContainsString('totp_required', $logContents);
    }

    public function testLoginPostInvalidTotpCodeReturnsGenericErrorAndLogsSpecificFailureReason(): void
    {
        global $appConfig;
        $appConfig['admin']['totp_enabled'] = true;
        $appConfig['admin']['totp_secret'] = 'JBSWY3DPEHPK3PXP';
        $appConfig['admin']['totp_skip_localhost'] = false;

        $controller = $this->controller();
        $token = admin_csrf_token();

        $response = $controller->handle(
            'login',
            $this->request(
                'POST',
                '/admin',
                [],
                [
                    'csrf_token' => $token,
                    'identifier' => 'admin@example.com',
                    'password' => 'topsecret',
                    'totp_code' => '000000',
                ],
                '198.51.100.23'
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Identifiants invalides.', $response->body);

        $securityLogPath = $this->logDir . '/security.log';
        $this->assertFileExists($securityLogPath);
        $logContents = (string) file_get_contents($securityLogPath);

        $this->assertStringContainsString('admin.login.failed', $logContents);
        $this->assertStringContainsString('totp_invalid', $logContents);
    }

    public function testLoginPostIsRateLimitedAfterConfiguredAttempts(): void
    {
        global $appConfig;
        $appConfig['admin']['login_rate_limit_attempts'] = 1;
        $appConfig['admin']['login_rate_limit_window'] = 900;

        $controller = $this->controller();

        $first = $controller->handle(
            'login',
            $this->request(
                'POST',
                '/admin',
                [],
                [
                    'csrf_token' => admin_csrf_token(),
                    'identifier' => 'admin@example.com',
                    'password' => 'wrong-password',
                ]
            )
        );
        $this->assertSame(200, $first->status);

        $second = $controller->handle(
            'login',
            $this->request(
                'POST',
                '/admin',
                [],
                [
                    'csrf_token' => admin_csrf_token(),
                    'identifier' => 'admin@example.com',
                    'password' => 'wrong-password',
                ]
            )
        );

        $this->assertSame(429, $second->status);
        $this->assertStringContainsString('Trop de tentatives de connexion', $second->body);
    }

    public function testLogoutRedirectsBackToLogin(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->handle('logout', $this->request('GET', '/admin/logout'));

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin', $response->headers['Location']);
        $this->assertFalse(admin_is_authenticated());
    }

    public function testPagesIndexRendersRegisteredPages(): void
    {
        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
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
        $controller = $this->controller();
        $response = $controller->handle('pages', $this->request('GET', '/admin/pages'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Pages du site', $response->body);
        $this->assertStringContainsString('association', $response->body);
        $this->assertStringContainsString('Publié', $response->body);
        $this->assertStringContainsString('/admin/pages/association', $response->body);
        $this->assertStringContainsString('name="page_action" value="delete"', $response->body);
        $this->assertStringContainsString('name="confirm_delete" value="1"', $response->body);
        $this->assertStringContainsString('data-delete-warning="ATTENTION : suppression definitive de la page', $response->body);
        $this->assertStringContainsString('function confirmPageDelete(form)', $response->body);
        $this->assertStringNotContainsString('Tapez SUPPRIMER pour confirmer', $response->body);
        $this->assertStringContainsString('>Supprimer<', $response->body);
    }

    public function testPagesCreatePersistsStructuredPageAndRedirectsToEditScreen(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();
        $token = admin_csrf_token();

        $response = $controller->handle(
            'pages_new',
            $this->request(
                'POST',
                '/admin/pages/new',
                [],
                [
                    'csrf_token' => $token,
                    'slug' => 'nouvelle-balade',
                    'type' => 'structured_page',
                    'status' => 'published',
                    'route' => '/nouvelle-balade',
                    'layout' => 'standard_page',
                    'translations' => [
                        'fr' => [
                            'title' => 'Nouvelle balade',
                            'meta_description' => 'Balade de printemps',
                            'regions' => [
                                'hero_html' => '<h1>Nouvelle balade</h1>',
                                'intro_html' => '<p>Intro</p>',
                            ],
                        ],
                        'en' => [],
                        'de' => [],
                    ],
                ]
            )
        );

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/pages/nouvelle-balade?saved=1', $response->headers['Location']);

        $decoded = json_decode((string) file_get_contents($this->pagesFile), true);

        $this->assertIsArray($decoded);
        $this->assertSame('nouvelle-balade', $decoded['pages'][0]['slug'] ?? null);
        $this->assertSame('published', $decoded['pages'][0]['status'] ?? null);
        $this->assertSame('Nouvelle balade', $decoded['pages'][0]['translations']['fr']['title'] ?? null);
    }

    public function testPagesCreatePersistsStructuredPageFromJsonEditorState(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();
        $token = admin_csrf_token();
        $editorState = [
            'slug' => 'nouvelle-balade-json',
            'status' => 'published',
            'route' => '/nouvelle-balade-json',
            'layout' => 'standard_page',
            'translations' => [
                'fr' => [
                    'title' => 'Nouvelle balade JSON',
                    'meta_description' => 'Balade sérialisée',
                    'regions' => [
                        'hero_html' => '<h1>Balade JSON</h1>',
                        'intro_html' => '<p>Intro JSON</p>',
                    ],
                ],
                'en' => [],
                'de' => [],
            ],
        ];

        $response = $controller->handle(
            'pages_new',
            $this->request(
                'POST',
                '/admin/pages/new',
                [],
                [
                    'csrf_token' => $token,
                    'page_action' => 'save',
                    'page_state_json' => json_encode($editorState, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]
            )
        );

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/pages/nouvelle-balade-json?saved=1', $response->headers['Location']);

        $decoded = json_decode((string) file_get_contents($this->pagesFile), true);

        $this->assertIsArray($decoded);
        $this->assertSame('nouvelle-balade-json', $decoded['pages'][0]['slug'] ?? null);
        $this->assertSame('published', $decoded['pages'][0]['status'] ?? null);
        $this->assertSame('Nouvelle balade JSON', $decoded['pages'][0]['translations']['fr']['title'] ?? null);
        $this->assertSame(
            'Balade sérialisée',
            $decoded['pages'][0]['translations']['fr']['meta']['description'] ?? null
        );
    }

    public function testPagesCreateUploadsSharedMediaAsWebpAndStoresItAtRootMeta(): void
    {
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            $this->markTestSkipped('Extension GD + WebP requise pour ce test.');
        }

        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();
        $token = admin_csrf_token();
        $temporaryImage = $this->createTemporaryJpeg(2600, 1400);

        $response = $controller->handle(
            'pages_new',
            $this->request(
                'POST',
                '/admin/pages/new',
                [],
                [
                    'csrf_token' => $token,
                    'slug' => 'galerie-partagee',
                    'status' => 'published',
                    'route' => '/galerie-partagee',
                    'layout' => 'standard_page',
                    'translations' => [
                        'fr' => [
                            'title' => 'Galerie partagee',
                            'regions' => [
                                'hero_html' => '<h1>Galerie partagee</h1>',
                            ],
                        ],
                        'en' => [],
                        'de' => [],
                    ],
                ],
                '127.0.0.1',
                [],
                [
                    'page_shared_media_files' => [
                        'name' => ['simca-test.jpg'],
                        'type' => ['image/jpeg'],
                        'tmp_name' => [$temporaryImage],
                        'error' => [UPLOAD_ERR_OK],
                        'size' => [filesize($temporaryImage)],
                    ],
                ]
            )
        );

        @unlink($temporaryImage);

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/pages/galerie-partagee?saved=1', $response->headers['Location']);

        $decoded = json_decode((string) file_get_contents($this->pagesFile), true);
        $this->assertIsArray($decoded);

        $sharedMedia = $decoded['pages'][0]['meta']['shared_media'][0] ?? null;
        $this->assertIsArray($sharedMedia);
        $sharedMediaSrc = (string) ($sharedMedia['src'] ?? '');
        $this->assertStringStartsWith('/uploads/editorial/media/', $sharedMediaSrc);
        $this->assertStringEndsWith('.webp', $sharedMediaSrc);

        $absoluteSharedMediaPath = ROOT_PATH . '/public' . $sharedMediaSrc;
        $this->uploadedRuntimeFiles[] = $absoluteSharedMediaPath;

        $this->assertFileExists($absoluteSharedMediaPath);
        $dimensions = @getimagesize($absoluteSharedMediaPath);
        $this->assertIsArray($dimensions);
        $this->assertLessThanOrEqual(2048, (int) $dimensions[0]);
        $this->assertLessThanOrEqual(2048, (int) $dimensions[1]);
    }

    public function testPagesEditShowsLayoutPlanForStructuredPages(): void
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
        $controller = $this->controller();

        $response = $controller->handle(
            'pages_edit',
            $this->request('GET', '/admin/pages/association'),
            ['slug' => 'association']
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('role="tablist"', $response->body);
        $this->assertStringContainsString('data-translation-tabs', $response->body);
        $this->assertStringContainsString('data-translation-tab="fr"', $response->body);
        $this->assertStringContainsString('data-translation-tab="en"', $response->body);
        $this->assertStringContainsString('data-translation-tab="de"', $response->body);
        $this->assertStringContainsString('data-translation-panel="fr"', $response->body);
        $this->assertStringContainsString('data-translation-panel="en"', $response->body);
        $this->assertStringContainsString('data-translation-save="fr"', $response->body);
        $this->assertStringContainsString('data-translation-save="en"', $response->body);
        $this->assertStringContainsString('data-translation-save="de"', $response->body);
        $this->assertStringContainsString('Plan du template standard', $response->body);
        $this->assertStringContainsString('data-region-modal-open="region-modal-fr-hero"', $response->body);
        $this->assertStringContainsString('<dialog class="region-modal" id="region-modal-fr-hero"', $response->body);
        $this->assertStringContainsString('name="page_state_json"', $response->body);
        $this->assertStringContainsString('EditRegion8', $response->body);
        $this->assertStringContainsString('Intro', $response->body);
        $this->assertStringContainsString('petite image d appel ou texte court uniquement', $response->body);
        $this->assertStringContainsString('Ne pas y mettre un second corps d article, un long developpement ou une grande image.', $response->body);
        $this->assertStringContainsString('EditRegion9', $response->body);
        $this->assertStringContainsString('Footer editorial', $response->body);
        $this->assertStringContainsString('Post-scriptum', $response->body);
        $this->assertStringContainsString('data-image-check-run', $response->body);
        $this->assertStringContainsString('data-image-check-results', $response->body);
        $this->assertStringContainsString('Medias partages (toutes langues)', $response->body);
        $this->assertStringContainsString('name="page_shared_media_files[]"', $response->body);
        $this->assertStringContainsString('data-shared-media-editor', $response->body);
        $this->assertStringContainsString('data-content-media-open="page-media-insert-dialog"', $response->body);
        $this->assertStringContainsString('id="page-media-insert-dialog"', $response->body);
        $this->assertStringContainsString('Inserer un media (image / video)', $response->body);
        $this->assertStringContainsString('data-content-media-folder', $response->body);
        $this->assertStringContainsString('data-content-media-preset', $response->body);
        $this->assertStringContainsString('data-content-media-governance-strict', $response->body);
        $this->assertStringContainsString('data-content-media-audit', $response->body);
        $this->assertStringContainsString('data-region-callout-root', $response->body);
        $this->assertStringContainsString('Ajouter la bordure rose autour de l encart texte', $response->body);
        $this->assertStringNotContainsString('Mode d’édition', $response->body);
        $this->assertStringNotContainsString('Blocs legacy EditRegion*', $response->body);
        $this->assertStringContainsString('Êtes-vous sûr de vouloir supprimer cette page ?', $response->body);
        $this->assertStringContainsString('Oui, supprimer définitivement', $response->body);
        $this->assertStringContainsString('>Non<', $response->body);
    }

    public function testPagesEditPrefillsStructuredPlanFromLegacyBlocks(): void
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
                                    'blocks' => [
                                        'EditRegion1' => '<h1>Association</h1>',
                                        'EditRegion2' => '<p>Encart</p>',
                                        'EditRegion8' => '<p>Intro</p>',
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
        $controller = $this->controller();

        $response = $controller->handle(
            'pages_edit',
            $this->request('GET', '/admin/pages/association'),
            ['slug' => 'association']
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('name="translations[fr][regions][hero_html]"', $response->body);
        $this->assertStringContainsString('&lt;h1&gt;Association&lt;/h1&gt;', $response->body);
        $this->assertStringContainsString('name="translations[fr][regions][intro_html]"', $response->body);
        $this->assertStringContainsString('&lt;p&gt;Encart&lt;/p&gt;', $response->body);
        $this->assertStringContainsString('name="translations[fr][regions][aside_html]"', $response->body);
        $this->assertStringContainsString('&lt;p&gt;Intro&lt;/p&gt;', $response->body);
    }

    public function testPagesEditKeepsTrustedIframeInStructuredRegions(): void
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
                                            'html' => '<div class="video-container"><iframe src="https://www.youtube.com/embed/jHO4WgBiHGQ" title="SIMCA ARONDE" allowfullscreen></iframe></div>',
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
        $controller = $this->controller();

        $response = $controller->handle(
            'pages_edit',
            $this->request('GET', '/admin/pages/association'),
            ['slug' => 'association']
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('name="translations[fr][regions][body_html]"', $response->body);
        $this->assertStringContainsString('youtube-nocookie.com/embed/jHO4WgBiHGQ', $response->body);
    }

    public function testPagesDeleteRemovesPageAndRedirectsToList(): void
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
                                'de' => ['title' => 'Verein'],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->handle(
            'pages_edit',
            $this->request(
                'POST',
                '/admin/pages/association',
                [],
                [
                    'csrf_token' => admin_csrf_token(),
                    'page_action' => 'delete',
                    'confirm_delete' => '1',
                ]
            ),
            ['slug' => 'association']
        );

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/pages?deleted=association', $response->headers['Location']);

        $decoded = json_decode((string) file_get_contents($this->pagesFile), true);
        $this->assertIsArray($decoded);
        $this->assertSame([], $decoded['pages'] ?? []);
    }

    public function testPagesDeletePreservesActiveListFiltersOnRedirect(): void
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
        $controller = $this->controller();

        $response = $controller->handle(
            'pages_edit',
            $this->request(
                'POST',
                '/admin/pages/association',
                [],
                [
                    'csrf_token' => admin_csrf_token(),
                    'page_action' => 'delete',
                    'confirm_delete' => '1',
                    'return_status' => 'draft',
                    'return_lang' => 'fr',
                    'return_q' => 'simca aronde',
                ]
            ),
            ['slug' => 'association']
        );

        $this->assertSame(302, $response->status);
        $this->assertSame(
            '/admin/pages?deleted=association&status=draft&lang=fr&q=simca%20aronde',
            $response->headers['Location']
        );
    }

    public function testPagesListRemembersFiltersUntilReset(): void
    {
        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'simca-aronde',
                            'type' => 'structured_page',
                            'status' => 'draft',
                            'route' => '/auto-retro/simca/histoire-simca-aronde',
                            'translations' => [
                                'fr' => ['title' => 'Simca Aronde'],
                                'en' => ['title' => 'Simca Aronde'],
                            ],
                        ],
                        [
                            'slug' => 'mercedes-slk',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'route' => '/auto-retro/mercedes/histoire-de-mercedes',
                            'translations' => [
                                'fr' => ['title' => 'Mercedes SLK'],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $filteredResponse = $controller->handle(
            'pages',
            $this->request(
                'GET',
                '/admin/pages',
                [
                    'status' => 'draft',
                    'lang' => 'fr',
                    'q' => 'simca',
                ]
            )
        );

        $this->assertSame(200, $filteredResponse->status);
        $this->assertStringContainsString('value="simca"', $filteredResponse->body);
        $this->assertStringContainsString('option value="draft" selected', $filteredResponse->body);
        $this->assertStringContainsString('option value="fr" selected', $filteredResponse->body);
        $this->assertStringContainsString('/admin/pages?reset_filters=1', $filteredResponse->body);
        $this->assertStringContainsString('simca-aronde', $filteredResponse->body);
        $this->assertStringNotContainsString('mercedes-slk', $filteredResponse->body);

        $rememberedResponse = $controller->handle('pages', $this->request('GET', '/admin/pages'));

        $this->assertSame(200, $rememberedResponse->status);
        $this->assertStringContainsString('value="simca"', $rememberedResponse->body);
        $this->assertStringContainsString('option value="draft" selected', $rememberedResponse->body);
        $this->assertStringContainsString('option value="fr" selected', $rememberedResponse->body);
        $this->assertStringContainsString('simca-aronde', $rememberedResponse->body);
        $this->assertStringNotContainsString('mercedes-slk', $rememberedResponse->body);

        $resetResponse = $controller->handle(
            'pages',
            $this->request(
                'GET',
                '/admin/pages',
                ['reset_filters' => '1']
            )
        );

        $this->assertSame(302, $resetResponse->status);
        $this->assertSame('/admin/pages', $resetResponse->headers['Location']);

        $clearedResponse = $controller->handle('pages', $this->request('GET', '/admin/pages'));

        $this->assertSame(200, $clearedResponse->status);
        $this->assertStringNotContainsString('value="simca"', $clearedResponse->body);
        $this->assertStringNotContainsString('option value="draft" selected', $clearedResponse->body);
        $this->assertStringContainsString('option value="fr" selected', $clearedResponse->body);
        $this->assertStringContainsString('simca-aronde', $clearedResponse->body);
        $this->assertStringContainsString('mercedes-slk', $clearedResponse->body);
    }

    public function testPagesDeleteIsBlockedWhenPageIsUsedInNavigation(): void
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
        file_put_contents(
            $this->menusFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'locations' => [
                        'utility' => [],
                        'primary' => [
                            [
                                'id' => 'primary-association',
                                'kind' => 'page',
                                'label' => ['text' => 'Association'],
                                'target' => ['pageSlug' => 'association'],
                                'children' => [],
                            ],
                        ],
                        'footer' => [],
                        'sideRight' => [],
                        'sideLeft' => [],
                        'banner' => [],
                        'remonter' => [],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->handle(
            'pages_edit',
            $this->request(
                'POST',
                '/admin/pages/association',
                [],
                [
                    'csrf_token' => admin_csrf_token(),
                    'page_action' => 'delete',
                    'confirm_delete' => '1',
                ]
            ),
            ['slug' => 'association']
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Suppression impossible', $response->body);
        $this->assertStringContainsString('Menu principal', $response->body);
        $this->assertStringContainsString('Association', $response->body);

        $decoded = json_decode((string) file_get_contents($this->pagesFile), true);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded['pages'] ?? []);
    }

    public function testMenusPageRendersVisualBuilderInsteadOfJsonTextarea(): void
    {
        file_put_contents(
            $this->menusFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'locations' => [
                        'remonter' => [],
                        'banner' => [],
                        'utility' => [],
                        'primary' => [
                            [
                                'id' => 'primary-club',
                                'kind' => 'group',
                                'label' => ['text' => 'Club'],
                                'target' => ['pageSlug' => null, 'route' => null, 'url' => null, 'openInNewTab' => false],
                                'media' => [],
                                'content' => [],
                                'accessibility' => [],
                                'presentation' => [
                                    'displayMode' => 'dropdown',
                                ],
                                'children' => [],
                            ],
                        ],
                        'footer' => [],
                        'sideRight' => [],
                        'sideLeft' => [],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->handle('menus', $this->request('GET', '/admin/menus'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Builder des menus', $response->body);
        $this->assertStringContainsString('Mode expert · JSON canonique', $response->body);
        $this->assertStringContainsString('Aperçu simplifié · header desktop', $response->body);
        $this->assertStringContainsString('data-region-modal-open="menu-system-banner-dialog"', $response->body);
        $this->assertStringContainsString('data-region-modal-open="menu-system-backtotop-dialog"', $response->body);
        $this->assertStringContainsString('id="menu-system-banner-dialog"', $response->body);
        $this->assertStringContainsString('id="menu-system-backtotop-dialog"', $response->body);
        $this->assertStringNotContainsString('name="menus_json"', $response->body);
        $this->assertStringContainsString('class="menu-item-card menu-item-card-kind-group', $response->body);
    }

    public function testLogsPageRendersSystemJournalScreen(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->handle('logs', $this->request('GET', '/admin/logs'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Journaux système', $response->body);
        $this->assertStringContainsString('Journal SQL', $response->body);
        $this->assertStringContainsString('Lecture rapide', $response->body);
        $this->assertStringContainsString('Debug', $response->body);
        $this->assertStringContainsString('Canal', $response->body);
        $this->assertStringContainsString('Nettoyage', $response->body);
        $this->assertStringContainsString('class="card dashboard-kpi-card"', $response->body);
        $this->assertStringContainsString('data-log-select-all', $response->body);
        $this->assertStringContainsString('data-log-delete-selected', $response->body);
        $this->assertStringContainsString('Tout sélectionner', $response->body);
    }

    public function testMediaPageRendersLibraryManagementScreen(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->handle('media', $this->request('GET', '/admin/media'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Bibliotheque medias', $response->body);
        $this->assertStringContainsString('name="media_action" value="upload"', $response->body);
        $this->assertStringContainsString('name="media_action" value="import_zip"', $response->body);
        $this->assertStringContainsString('name="media_action" value="export_folder"', $response->body);
    }

    public function testMediaCreateFolderActionCreatesExpectedDirectory(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();
        $targetDirectory = ROOT_PATH . '/public/uploads/editorial/library/photos-2026';

        if (is_dir($targetDirectory)) {
            @rmdir($targetDirectory);
        }

        $response = $controller->handle(
            'media',
            $this->request(
                'POST',
                '/admin/media',
                [],
                [
                    'csrf_token' => admin_csrf_token(),
                    'media_action' => 'create_folder',
                    'folder' => '',
                    'new_folder_name' => 'Photos 2026',
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Dossier créé.', $response->body);
        $this->assertDirectoryExists($targetDirectory);

        @rmdir($targetDirectory);
    }

    public function testMediaRenameFileActionRenamesFile(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();
        $token = 'media-' . bin2hex(random_bytes(4));
        $sourceRelativePath = $token . '-source.jpg';
        $renamedFilename = $token . '-renamed.jpg';
        $sourceAbsolutePath = ROOT_PATH . '/public/uploads/editorial/library/' . $sourceRelativePath;
        $renamedAbsolutePath = ROOT_PATH . '/public/uploads/editorial/library/' . $renamedFilename;

        @unlink($sourceAbsolutePath);
        @unlink($renamedAbsolutePath);
        file_put_contents($sourceAbsolutePath, 'media-test');

        $response = $controller->handle(
            'media',
            $this->request(
                'POST',
                '/admin/media',
                [],
                [
                    'csrf_token' => admin_csrf_token(),
                    'media_action' => 'rename_file',
                    'folder' => '',
                    'target_file' => $sourceRelativePath,
                    'new_file_name' => $renamedFilename,
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Fichier renommé.', $response->body);
        $this->assertFileDoesNotExist($sourceAbsolutePath);
        $this->assertFileExists($renamedAbsolutePath);

        @unlink($sourceAbsolutePath);
        @unlink($renamedAbsolutePath);
    }

    public function testMediaMoveFolderActionMovesDirectoryToDestination(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();
        $sourceFolder = 'media-' . bin2hex(random_bytes(4)) . '-source';
        $destinationFolder = 'media-' . bin2hex(random_bytes(4)) . '-archive';
        $absoluteRoot = ROOT_PATH . '/public/uploads/editorial/library';
        $sourceAbsolutePath = $absoluteRoot . '/' . $sourceFolder;
        $destinationAbsolutePath = $absoluteRoot . '/' . $destinationFolder;
        $movedAbsolutePath = $destinationAbsolutePath . '/' . $sourceFolder;

        $this->removeDirectoryRecursively($sourceAbsolutePath);
        $this->removeDirectoryRecursively($destinationAbsolutePath);
        mkdir($sourceAbsolutePath, 0777, true);
        mkdir($destinationAbsolutePath, 0777, true);
        file_put_contents($sourceAbsolutePath . '/sample.txt', 'media-folder-test');

        $response = $controller->handle(
            'media',
            $this->request(
                'POST',
                '/admin/media',
                [],
                [
                    'csrf_token' => admin_csrf_token(),
                    'media_action' => 'move_folder',
                    'folder' => '',
                    'target_folder' => $sourceFolder,
                    'destination_folder' => $destinationFolder,
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Dossier déplacé.', $response->body);
        $this->assertDirectoryDoesNotExist($sourceAbsolutePath);
        $this->assertDirectoryExists($movedAbsolutePath);
        $this->assertFileExists($movedAbsolutePath . '/sample.txt');

        $this->removeDirectoryRecursively($sourceAbsolutePath);
        $this->removeDirectoryRecursively($destinationAbsolutePath);
    }

    public function testMediaFiltersAreRenderedFromQueryParameters(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->handle(
            'media',
            $this->request(
                'GET',
                '/admin/media',
                [
                    'q' => 'photo',
                    'type' => 'image',
                    'min_size_kb' => '10',
                    'max_size_kb' => '2048',
                    'date_from' => '2026-01-01',
                    'date_to' => '2026-12-31',
                    'sort' => 'size_desc',
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('name="q" type="text" value="photo"', $response->body);
        $this->assertStringContainsString('<option value="image" selected>Images</option>', $response->body);
        $this->assertStringContainsString('name="min_size_kb" type="number" min="0" step="1" value="10"', $response->body);
        $this->assertStringContainsString('name="max_size_kb" type="number" min="0" step="1" value="2048"', $response->body);
        $this->assertStringContainsString('name="date_from" type="date" value="2026-01-01"', $response->body);
        $this->assertStringContainsString('name="date_to" type="date" value="2026-12-31"', $response->body);
        $this->assertStringContainsString('<option value="size_desc" selected>', $response->body);
        $this->assertStringContainsString('(filtres actifs)', $response->body);
    }

    public function testArticlesPageUsesDashboardStyleSummaryCards(): void
    {
        $this->writeBlogArticle([
            'title' => 'Sortie du mois',
            'slug' => 'sortie-du-mois',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-17 10:00:00',
            'content' => '<p>Sortie.</p>',
            'category' => 'Sorties',
            'tags' => ['Club'],
        ]);
        $this->writeBlogArticle([
            'title' => 'Monthly outing',
            'slug' => 'sortie-du-mois',
            'lang' => 'en',
            'status' => 'published',
            'date' => '2026-03-17 10:00:00',
            'content' => '<p>Outing.</p>',
            'category' => 'Sorties',
            'tags' => ['Club'],
        ]);
        $this->writeBlogArticle([
            'title' => 'Monatsausfahrt',
            'slug' => 'sortie-du-mois',
            'lang' => 'de',
            'status' => 'published',
            'date' => '2026-03-17 10:00:00',
            'content' => '<p>Ausfahrt.</p>',
            'category' => 'Sorties',
            'tags' => ['Club'],
        ]);

        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->handle('articles', $this->request('GET', '/admin/articles'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Articles visibles', $response->body);
        $this->assertStringContainsString('Articles publiés', $response->body);
        $this->assertMatchesRegularExpression(
            '/<strong class="dashboard-kpi-value">1<\/strong>\s*<p class="dashboard-kpi-label">Articles visibles<\/p>/',
            $response->body
        );
        $this->assertMatchesRegularExpression(
            '/<strong class="dashboard-kpi-value">1<\/strong>\s*<p class="dashboard-kpi-label">Articles publiés<\/p>/',
            $response->body
        );
        $this->assertStringContainsString('class="card dashboard-kpi-card"', $response->body);
        $this->assertStringContainsString('name="scheduled_date" type="date"', $response->body);
        $this->assertStringNotContainsString('id="articles-lang"', $response->body);
        $this->assertStringContainsString('article_action', $response->body);
        $this->assertStringContainsString('Supprimer', $response->body);
    }

    public function testArticlesPageCanFilterScheduledArticlesByPlannedDate(): void
    {
        $this->writeBlogArticle([
            'title' => 'Publication du 30 avril',
            'slug' => 'publication-30-avril',
            'lang' => 'fr',
            'status' => 'scheduled',
            'date' => '2026-04-30 13:34:02',
            'content' => '<p>Publication planifiée.</p>',
            'category' => 'Sorties',
            'tags' => ['Club'],
        ]);
        $this->writeBlogArticle([
            'title' => 'Publication du 1er mai',
            'slug' => 'publication-1er-mai',
            'lang' => 'fr',
            'status' => 'scheduled',
            'date' => '2026-05-01 09:15:00',
            'content' => '<p>Publication planifiée.</p>',
            'category' => 'Sorties',
            'tags' => ['Club'],
        ]);
        $this->writeBlogArticle([
            'title' => 'Publication déjà en ligne',
            'slug' => 'publication-deja-en-ligne',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-04-30 08:00:00',
            'content' => '<p>Publication en ligne.</p>',
            'category' => 'Sorties',
            'tags' => ['Club'],
        ]);

        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->handle(
            'articles',
            $this->request('GET', '/admin/articles', ['scheduled_date' => '2026-04-30'])
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('name="scheduled_date" type="date" value="2026-04-30"', $response->body);
        $this->assertStringContainsString('publication-30-avril', $response->body);
        $this->assertStringNotContainsString('publication-1er-mai', $response->body);
        $this->assertStringNotContainsString('publication-deja-en-ligne', $response->body);
        $this->assertMatchesRegularExpression(
            '/<strong class="dashboard-kpi-value">1<\/strong>\s*<p class="dashboard-kpi-label">Articles visibles<\/p>/',
            $response->body
        );
    }

    public function testArticleEditorRendersPageAttachmentSelectorWithAvailablePages(): void
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

        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->handle('articles_new', $this->request('GET', '/admin/articles/new'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Page parent de publication', $response->body);
        $this->assertStringContainsString('Choisir une page parent (obligatoire)', $response->body);
        $this->assertStringContainsString('Association', $response->body);
        $this->assertStringContainsString('/association', $response->body);
        $this->assertStringContainsString('name="article[scheduled_publish_at]"', $response->body);
        $this->assertStringContainsString('Planifié', $response->body);
        $this->assertStringContainsString('name="article[active_language]"', $response->body);
        $this->assertStringContainsString('data-article-translation-tabs', $response->body);
        $this->assertStringContainsString('name="translations[fr][title]"', $response->body);
        $this->assertStringContainsString('name="translations[en][title]"', $response->body);
        $this->assertStringContainsString('name="translations[de][content]"', $response->body);
        $this->assertStringNotContainsString('name="article[lang]"', $response->body);
        $this->assertStringContainsString('data-content-media-open="article-media-insert-dialog"', $response->body);
        $this->assertStringContainsString('id="article-media-insert-dialog"', $response->body);
        $this->assertStringContainsString('Inserer un media (image / video)', $response->body);
        $this->assertStringContainsString('data-content-media-folder', $response->body);
        $this->assertStringContainsString('data-content-media-preset', $response->body);
        $this->assertStringContainsString('data-content-media-governance-strict', $response->body);
        $this->assertStringContainsString('data-content-media-audit', $response->body);
    }

    public function testArticleDeleteRemovesAttachedDiscussionsAndRedirectsToList(): void
    {
        $this->writeBlogArticle([
            'title' => 'Article à supprimer',
            'slug' => 'article-a-supprimer',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-18 10:00:00',
            'content' => '<p>Contenu.</p>',
        ]);

        $discussionRepository = blog_discussion_repository();
        $discussionRepository->submitPending('article-a-supprimer', 'fr', [
            'author' => 'Lecteur',
            'email' => 'lecteur@example.com',
            'content' => 'Message à supprimer',
        ]);

        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->handle(
            'articles_edit',
            $this->request(
                'POST',
                '/admin/articles/article-a-supprimer/fr',
                [],
                [
                    'csrf_token' => admin_csrf_token(),
                    'article_action' => 'delete',
                    'confirm_delete' => '1',
                ]
            ),
            ['slug' => 'article-a-supprimer', 'lang' => 'fr']
        );

        $this->assertSame(302, $response->status);
        $this->assertSame(
            '/admin/articles?deleted=article-a-supprimer&deleted_lang=fr&deleted_discussions=1&detached_children=0',
            $response->headers['Location'] ?? null
        );
        $this->assertFileDoesNotExist($this->blogDir . '/article-a-supprimer.fr.json');
        $this->assertSame([], $discussionRepository->all());
    }

    public function testMenusPostPersistsVisualBuilderPayload(): void
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
        $controller = $this->controller();
        $token = admin_csrf_token();

        $response = $controller->handle(
            'menus',
            $this->request(
                'POST',
                '/admin/menus',
                [],
                [
                    'csrf_token' => $token,
                    'active_location' => 'primary',
                    'selected_item' => 'primary|0',
                    'builder_action' => 'save',
                    'banner' => [
                        'image' => '/assets/images/structure/banniere.jpg',
                        'headline' => 'Voyage dans le golfe',
                        'headline_default_language' => 'fr',
                        'headline_translations' => [
                            'fr' => 'Voyage dans le golfe',
                            'de' => 'Reise durch den Golf',
                            'en' => 'Journey through the gulf',
                        ],
                        'alt' => 'Voyage dans le golfe',
                        'title' => 'Voyage dans le golfe',
                    ],
                    'remonter' => [
                        'label' => 'Top',
                        'label_default_language' => 'fr',
                        'label_translations' => [
                            'fr' => 'Remonter',
                            'de' => 'Nach oben',
                        ],
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
        $this->assertSame('fr', $decoded['locations']['banner']['headline']['defaultLanguage'] ?? null);
        $this->assertSame('Reise durch den Golf', $decoded['locations']['banner']['headline']['translations']['de'] ?? null);
        $this->assertSame('Journey through the gulf', $decoded['locations']['banner']['headline']['translations']['en'] ?? null);
        $this->assertSame('fr', $decoded['locations']['remonter']['label']['defaultLanguage'] ?? null);
        $this->assertSame('Remonter', $decoded['locations']['remonter']['label']['translations']['fr'] ?? null);
        $this->assertSame('Nach oben', $decoded['locations']['remonter']['label']['translations']['de'] ?? null);
    }

    public function testMenusSelectActionMarksPopupForAutoOpen(): void
    {
        file_put_contents(
            $this->menusFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'locations' => [
                        'remonter' => [],
                        'banner' => [],
                        'utility' => [],
                        'primary' => [
                            [
                                'id' => 'primary-home',
                                'kind' => 'route',
                                'label' => ['text' => 'Accueil'],
                                'target' => ['pageSlug' => null, 'route' => '/accueil', 'url' => null, 'openInNewTab' => false],
                                'media' => [],
                                'content' => [],
                                'accessibility' => [],
                                'presentation' => [],
                                'children' => [],
                            ],
                        ],
                        'footer' => [],
                        'sideRight' => [],
                        'sideLeft' => [],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->handle(
            'menus',
            $this->request(
                'POST',
                '/admin/menus',
                [],
                [
                    'csrf_token' => admin_csrf_token(),
                    'active_location' => 'primary',
                    'selected_item' => 'primary|0',
                    'builder_action' => 'select@primary|0',
                    'banner' => [],
                    'remonter' => [],
                    'locations' => [
                        'utility' => [],
                        'primary' => [
                            [
                                'id' => 'primary-home',
                                'kind' => 'route',
                                'label_text' => 'Accueil',
                                'target_mode' => 'route',
                                'target_page_slug' => '',
                                'target_route' => '/accueil',
                                'target_url' => '',
                                'image' => '',
                                'content_text' => '',
                                'alt' => '',
                                'title' => '',
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
        $this->assertStringContainsString('data-region-modal-autostart="true"', $response->body);
        $this->assertStringContainsString('id="menu-editor-dialog"', $response->body);
    }

    public function testMenusSaveFailureRendersErrorInsidePopup(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->handle(
            'menus',
            $this->request(
                'POST',
                '/admin/menus',
                [],
                [
                    'csrf_token' => admin_csrf_token(),
                    'active_location' => 'primary',
                    'selected_item' => 'primary|0',
                    'builder_action' => 'save',
                    'banner' => [],
                    'remonter' => [],
                    'locations' => [
                        'utility' => [],
                        'primary' => [
                            [
                                'id' => 'primary-home',
                                'kind' => 'route',
                                'label_text' => 'Accueil',
                                'target_mode' => 'route',
                                'target_page_slug' => '',
                                'target_route' => '',
                                'target_url' => '',
                                'image' => '',
                                'content_text' => '',
                                'alt' => '',
                                'title' => '',
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
        $this->assertStringContainsString('data-region-modal-autostart="true"', $response->body);
        $this->assertStringContainsString(
            '<div class="notice notice-error" role="alert">Menu principal &gt; Accueil : la route interne est obligatoire.</div>',
            $response->body
        );
        $this->assertStringContainsString(
            'La sauvegarde vérifie tout le menu courant.',
            $response->body
        );
    }

    public function testNestedMenuSelectionDoesNotDuplicatePopupFields(): void
    {
        file_put_contents(
            $this->menusFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'locations' => [
                        'remonter' => [],
                        'banner' => [],
                        'utility' => [],
                        'primary' => [
                            [
                                'id' => 'primary-group',
                                'kind' => 'group',
                                'label' => ['text' => 'Club'],
                                'target' => ['pageSlug' => null, 'route' => null, 'url' => null, 'openInNewTab' => false],
                                'media' => [],
                                'content' => [],
                                'accessibility' => [],
                                'presentation' => [],
                                'children' => [
                                    [
                                        'id' => 'primary-child',
                                        'kind' => 'external',
                                        'label' => ['text' => 'Forum'],
                                        'target' => ['pageSlug' => null, 'route' => null, 'url' => 'https://forum.example', 'openInNewTab' => true],
                                        'media' => [],
                                        'content' => [],
                                        'accessibility' => [],
                                        'presentation' => [],
                                        'children' => [],
                                    ],
                                ],
                            ],
                        ],
                        'footer' => [],
                        'sideRight' => [],
                        'sideLeft' => [],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->handle(
            'menus',
            $this->request(
                'GET',
                '/admin/menus',
                [
                    'location' => 'primary',
                    'selection' => 'primary|0|0',
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertSame(1, substr_count($response->body, 'name="locations[primary][0][children][0][label_text]"'));
        $this->assertSame(1, substr_count($response->body, 'name="locations[primary][0][children][0][target_url]"'));
        $this->assertStringContainsString('id="menu-builder-form"', $response->body);
        $this->assertStringContainsString('form="menu-builder-form"', $response->body);
        $this->assertStringContainsString('Sauvegarder et fermer', $response->body);
        $this->assertStringContainsString('id="selected_label_text"', $response->body);
        $this->assertStringContainsString('id="banner_image"', $response->body);
        $this->assertStringContainsString('id="back_to_top_label"', $response->body);
        $this->assertStringContainsString('name="locations[primary][0][id]"', $response->body);
    }

    public function testMenusBuilderRendersTranslatedGroupLabelsAndPreservesTranslationKeys(): void
    {
        file_put_contents(
            $this->menusFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'locations' => [
                        'remonter' => [
                            'label' => ['text' => null, 'translationKey' => 'REMONTER_TOP'],
                            'accessibility' => ['alt' => 'Top', 'title' => 'Top'],
                        ],
                        'banner' => [
                            'image' => '/assets/images/structure/banniere.jpg',
                            'headline' => ['text' => null, 'translationKey' => 'TXT_BANNIERE'],
                            'accessibility' => ['alt' => null, 'title' => null],
                        ],
                        'utility' => [],
                        'primary' => [
                            [
                                'id' => 'primary-home',
                                'kind' => 'route',
                                'label' => ['text' => null, 'translationKey' => 'MENU_ACCUEIL'],
                                'target' => ['pageSlug' => null, 'route' => '/accueil', 'url' => null, 'openInNewTab' => false],
                                'media' => [],
                                'content' => [],
                                'accessibility' => [],
                                'presentation' => [],
                                'children' => [],
                            ],
                            [
                                'id' => 'primary-group',
                                'kind' => 'group',
                                'label' => ['text' => null, 'translationKey' => 'MENU_AUTORETRO'],
                                'target' => ['pageSlug' => null, 'route' => null, 'url' => null, 'openInNewTab' => false],
                                'media' => [],
                                'content' => [],
                                'accessibility' => [],
                                'presentation' => ['displayMode' => 'mega', 'columnCount' => 3, 'menuTemplate' => 'brands'],
                                'children' => [],
                            ],
                        ],
                        'footer' => [],
                        'sideRight' => [],
                        'sideLeft' => [],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
        );

        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->handle(
            'menus',
            $this->request(
                'GET',
                '/admin/menus',
                [
                    'location' => 'primary',
                    'selection' => 'primary|1',
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('AUTO-RETRO', $response->body);
        $this->assertStringNotContainsString('Item sans libellé', $response->body);
        $this->assertStringContainsString('name="locations[primary][1][label_translation_key]"', $response->body);
        $this->assertStringContainsString('name="locations[primary][1][label_default_language]"', $response->body);
        $this->assertStringContainsString('name="locations[primary][1][label_translations][fr]"', $response->body);
        $this->assertStringContainsString('name="banner[headline_translation_key]"', $response->body);
        $this->assertStringContainsString('name="banner[headline_default_language]"', $response->body);
        $this->assertStringContainsString('name="banner[headline_translations][fr]"', $response->body);
        $this->assertStringContainsString('name="remonter[label_translation_key]"', $response->body);
        $this->assertStringContainsString('name="remonter[label_default_language]"', $response->body);
        $this->assertStringContainsString('name="remonter[label_translations][fr]"', $response->body);
        $this->assertStringContainsString('name="remonter[label_translations][de]"', $response->body);
        $this->assertStringContainsString('value="AUTO-RETRO"', $response->body);
        $this->assertStringContainsString('value="Remonter"', $response->body);
    }

    public function testSelectedGroupKeepsChildrenHiddenFieldsInPopupForm(): void
    {
        file_put_contents(
            $this->menusFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'locations' => [
                        'remonter' => [],
                        'banner' => [],
                        'utility' => [],
                        'primary' => [
                            [
                                'id' => 'primary-group',
                                'kind' => 'group',
                                'label' => ['text' => 'Club'],
                                'target' => ['pageSlug' => null, 'route' => null, 'url' => null, 'openInNewTab' => false],
                                'media' => [],
                                'content' => [],
                                'accessibility' => [],
                                'presentation' => [
                                    'displayMode' => 'dropdown',
                                ],
                                'children' => [
                                    [
                                        'id' => 'primary-child',
                                        'kind' => 'route',
                                        'label' => ['text' => 'Sorties'],
                                        'target' => ['pageSlug' => null, 'route' => '/sorties', 'url' => null, 'openInNewTab' => false],
                                        'media' => [],
                                        'content' => [],
                                        'accessibility' => [],
                                        'presentation' => [],
                                        'children' => [],
                                    ],
                                ],
                            ],
                        ],
                        'footer' => [],
                        'sideRight' => [],
                        'sideLeft' => [],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->handle(
            'menus',
            $this->request(
                'GET',
                '/admin/menus',
                [
                    'location' => 'primary',
                    'selection' => 'primary|0',
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertSame(0, substr_count($response->body, 'name="locations[primary][0][label_text]" type="hidden"'));
        $this->assertSame(1, substr_count($response->body, 'name="locations[primary][0][children][0][label_text]"'));
        $this->assertSame(1, substr_count($response->body, 'name="locations[primary][0][children][0][target_route]"'));
    }

    public function testJsonBuilderStateSelectsNestedGroupWithoutFallingBackToParent(): void
    {
        file_put_contents(
            $this->menusFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'locations' => [
                        'remonter' => [],
                        'banner' => [],
                        'utility' => [],
                        'primary' => [
                            [
                                'id' => 'primary-group',
                                'kind' => 'group',
                                'label' => ['text' => 'Auto-Retro'],
                                'target' => ['pageSlug' => null, 'route' => null, 'url' => null, 'openInNewTab' => false],
                                'media' => [],
                                'content' => [],
                                'accessibility' => [],
                                'presentation' => ['displayMode' => 'mega', 'columnCount' => 4, 'menuTemplate' => 'brands'],
                                'children' => [
                                    [
                                        'id' => 'primary-austin',
                                        'kind' => 'group',
                                        'label' => ['text' => 'Austin'],
                                        'target' => ['pageSlug' => null, 'route' => null, 'url' => null, 'openInNewTab' => false],
                                        'media' => [],
                                        'content' => [],
                                        'accessibility' => [],
                                        'presentation' => ['displayMode' => 'dropdown'],
                                        'children' => [
                                            [
                                                'id' => 'primary-austin-link',
                                                'kind' => 'route',
                                                'label' => ['text' => 'Mini'],
                                                'target' => ['pageSlug' => null, 'route' => '/mini', 'url' => null, 'openInNewTab' => false],
                                                'media' => [],
                                                'content' => [],
                                                'accessibility' => [],
                                                'presentation' => [],
                                                'children' => [],
                                            ],
                                        ],
                                    ],
                                    [
                                        'id' => 'primary-mercedes',
                                        'kind' => 'group',
                                        'label' => ['text' => 'Mercedes'],
                                        'target' => ['pageSlug' => null, 'route' => null, 'url' => null, 'openInNewTab' => false],
                                        'media' => [],
                                        'content' => [],
                                        'accessibility' => [],
                                        'presentation' => ['displayMode' => 'dropdown'],
                                        'children' => [
                                            [
                                                'id' => 'primary-mercedes-link',
                                                'kind' => 'route',
                                                'label' => ['text' => 'SLK'],
                                                'target' => ['pageSlug' => null, 'route' => '/slk', 'url' => null, 'openInNewTab' => false],
                                                'media' => [],
                                                'content' => [],
                                                'accessibility' => [],
                                                'presentation' => [],
                                                'children' => [],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'id' => 'primary-bouger',
                                'kind' => 'group',
                                'label' => ['text' => 'Bouger'],
                                'target' => ['pageSlug' => null, 'route' => null, 'url' => null, 'openInNewTab' => false],
                                'media' => [],
                                'content' => [],
                                'accessibility' => [],
                                'presentation' => ['displayMode' => 'mega', 'columnCount' => 3, 'menuTemplate' => 'editorial'],
                                'children' => [
                                    [
                                        'id' => 'primary-golfe',
                                        'kind' => 'route',
                                        'label' => ['text' => 'Le Golfe'],
                                        'target' => ['pageSlug' => null, 'route' => '/le-golfe', 'url' => null, 'openInNewTab' => false],
                                        'media' => [],
                                        'content' => [],
                                        'accessibility' => [],
                                        'presentation' => [],
                                        'children' => [],
                                    ],
                                ],
                            ],
                        ],
                        'footer' => [],
                        'sideRight' => [],
                        'sideLeft' => [],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
        );

        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $builderState = [
            'active_location' => 'primary',
            'selected_item' => 'primary|0',
            'banner' => [],
            'remonter' => [],
            'locations' => [
                'utility' => new stdClass(),
                'primary' => [
                    [
                        'id' => 'primary-group',
                        'kind' => 'group',
                        'label_text' => 'Auto-Retro',
                        'label_translation_key' => '',
                        'target_mode' => 'group',
                        'target_page_slug' => '',
                        'target_route' => '',
                        'target_url' => '',
                        'image' => '',
                        'content_text' => '',
                        'alt' => '',
                        'title' => '',
                        'display_mode' => 'mega',
                        'column_count' => '4',
                        'menu_template' => 'brands',
                        'children' => [
                            [
                                'id' => 'primary-austin',
                                'kind' => 'group',
                                'label_text' => 'Austin',
                                'label_translation_key' => '',
                                'target_mode' => 'group',
                                'target_page_slug' => '',
                                'target_route' => '',
                                'target_url' => '',
                                'image' => '',
                                'content_text' => '',
                                'alt' => '',
                                'title' => '',
                                'display_mode' => 'dropdown',
                                'column_count' => '',
                                'menu_template' => '',
                                'children' => [
                                    [
                                        'id' => 'primary-austin-link',
                                        'kind' => 'route',
                                        'label_text' => 'Mini',
                                        'label_translation_key' => '',
                                        'target_mode' => 'route',
                                        'target_page_slug' => '',
                                        'target_route' => '/mini',
                                        'target_url' => '',
                                        'image' => '',
                                        'content_text' => '',
                                        'alt' => '',
                                        'title' => '',
                                    ],
                                ],
                            ],
                            [
                                'id' => 'primary-mercedes',
                                'kind' => 'group',
                                'label_text' => 'Mercedes',
                                'label_translation_key' => '',
                                'target_mode' => 'group',
                                'target_page_slug' => '',
                                'target_route' => '',
                                'target_url' => '',
                                'image' => '',
                                'content_text' => '',
                                'alt' => '',
                                'title' => '',
                                'display_mode' => 'dropdown',
                                'column_count' => '',
                                'menu_template' => '',
                                'children' => [
                                    [
                                        'id' => 'primary-mercedes-link',
                                        'kind' => 'route',
                                        'label_text' => 'SLK',
                                        'label_translation_key' => '',
                                        'target_mode' => 'route',
                                        'target_page_slug' => '',
                                        'target_route' => '/slk',
                                        'target_url' => '',
                                        'image' => '',
                                        'content_text' => '',
                                        'alt' => '',
                                        'title' => '',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => 'primary-bouger',
                        'kind' => 'group',
                        'label_text' => 'Bouger',
                        'label_translation_key' => '',
                        'target_mode' => 'group',
                        'target_page_slug' => '',
                        'target_route' => '',
                        'target_url' => '',
                        'image' => '',
                        'content_text' => '',
                        'alt' => '',
                        'title' => '',
                        'display_mode' => 'mega',
                        'column_count' => '3',
                        'menu_template' => 'editorial',
                        'children' => [
                            [
                                'id' => 'primary-golfe',
                                'kind' => 'route',
                                'label_text' => 'Le Golfe',
                                'label_translation_key' => '',
                                'target_mode' => 'route',
                                'target_page_slug' => '',
                                'target_route' => '/le-golfe',
                                'target_url' => '',
                                'image' => '',
                                'content_text' => '',
                                'alt' => '',
                                'title' => '',
                            ],
                        ],
                    ],
                ],
                'footer' => new stdClass(),
                'sideRight' => new stdClass(),
                'sideLeft' => new stdClass(),
            ],
        ];

        $response = $controller->handle(
            'menus',
            $this->request(
                'POST',
                '/admin/menus',
                [],
                [
                    'csrf_token' => admin_csrf_token(),
                    'builder_action' => 'select@primary|0|0',
                    'builder_state_json' => json_encode($builderState, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('name="selected_item"', $response->body);
        $this->assertStringContainsString('value="primary|0|0"', $response->body);
        $this->assertStringContainsString('value="Austin"', $response->body);
        $this->assertStringContainsString('data-region-modal-autostart="true"', $response->body);
        $this->assertStringNotContainsString('value="Le Golfe"', $response->body);
    }

    public function testSettingsPageRendersOperationSettingsForm(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->handle('settings', $this->request('GET', '/admin/settings'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Paramètres d’exploitation', $response->body);
        $this->assertStringContainsString('data-region-modal-open="settings-dialog-database"', $response->body);
        $this->assertStringContainsString('data-region-modal-open="settings-dialog-admin"', $response->body);
        $this->assertStringContainsString('data-region-modal-open="settings-dialog-url"', $response->body);
        $this->assertStringContainsString('data-region-modal-open="settings-dialog-head"', $response->body);
        $this->assertStringContainsString('data-region-modal-open="settings-dialog-tarteaucitron"', $response->body);
        $this->assertStringContainsString('data-region-modal-open="settings-dialog-instagram"', $response->body);
        $this->assertStringContainsString('data-region-modal-open="settings-dialog-observability"', $response->body);
        $this->assertStringContainsString('data-region-modal-open="settings-dialog-backup"', $response->body);
        $this->assertStringContainsString('data-region-modal-open="settings-dialog-cron"', $response->body);
        $this->assertStringContainsString('data-region-modal-open="settings-dialog-translations"', $response->body);
        $this->assertStringContainsString('id="settings-dialog-security"', $response->body);
        $this->assertStringContainsString('name="tarteaucitron[privacy_url]" type="text" value="/" placeholder="/"', $response->body);
        $this->assertStringContainsString('name="tarteaucitron[user_config_json]"', $response->body);
        $this->assertStringContainsString('name="url[base_path]" type="text" value="/" placeholder="/"', $response->body);
        $this->assertStringContainsString('name="admin[allowed_ips]" type="text"', $response->body);
        $this->assertStringContainsString('name="instagram[username]" type="text"', $response->body);
        $this->assertStringContainsString('name="log_alerts[notify_on]"', $response->body);
        $this->assertStringContainsString('id="blog-publish-php-binary"', $response->body);
        $this->assertStringContainsString('publish_scheduled_blog_articles.php', $response->body);
        $this->assertStringContainsString('id="backup-cron-command"', $response->body);
        $this->assertStringContainsString('backup_production.php', $response->body);
        $this->assertStringContainsString('name="backup[root_dir]"', $response->body);
        $this->assertStringContainsString('name="backup[retention_days]"', $response->body);
        $this->assertStringContainsString('name="backup[files_dir]"', $response->body);
        $this->assertStringContainsString('name="backup[sql_dir]"', $response->body);
        $this->assertStringContainsString('name="backup[manifest_dir]"', $response->body);
        $this->assertStringContainsString('name="backup[php_binary]"', $response->body);
        $this->assertStringContainsString('name="backup[tar_binary]"', $response->body);
        $this->assertStringContainsString('name="backup[mysqldump_binary]"', $response->body);
        $this->assertStringContainsString('name="backup[database_host]"', $response->body);
        $this->assertStringContainsString('name="backup[database_port]"', $response->body);
        $this->assertStringContainsString('name="backup[database_name]"', $response->body);
        $this->assertStringContainsString('name="backup[database_user]"', $response->body);
        $this->assertStringContainsString('name="backup[database_password]"', $response->body);
        $this->assertStringContainsString('Connexion SQL utilisee par le site et par le dump mysqldump', $response->body);
        $this->assertStringContainsString('id="cron-center-ovh-command"', $response->body);
        $this->assertStringContainsString('id="cron-center-history"', $response->body);
        $this->assertStringContainsString('run_cron_center.php', $response->body);
        $this->assertStringContainsString('name="cron_job[code]"', $response->body);
        $this->assertStringContainsString('Job', $response->body);
        $this->assertStringContainsString('Planification', $response->body);
        $this->assertStringContainsString('Derniere execution', $response->body);
        $this->assertStringContainsString('Prochaine execution', $response->body);
        $this->assertStringContainsString('name="translations[fr]"', $response->body);
        $this->assertStringContainsString('Dictionnaire existant FR', $response->body);
        $this->assertStringContainsString('name="settings_action" value="cache_clear"', $response->body);
        $this->assertStringContainsString('Vider le cache', $response->body);
    }

    public function testSettingsPageKeepsAdminLanguageWhenPublicTranslationsAreDifferent(): void
    {
        global $appConfig;

        $appConfig['admin']['language'] = 'fr';
        $GLOBALS['langTranslations'] = load_translations_cached('de');

        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->handle('settings', $this->request('GET', '/admin/settings'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('<html lang="fr">', $response->body);
        $cardStart = strpos($response->body, 'data-settings-section-card="observability"');
        $this->assertIsInt($cardStart);
        $cardEnd = strpos($response->body, '</button>', $cardStart);
        $this->assertIsInt($cardEnd);

        $observabilityCard = substr($response->body, $cardStart, $cardEnd - $cardStart);
        $this->assertStringContainsString('Observabilite ops', $observabilityCard);
        $this->assertStringContainsString('Canal logs', $observabilityCard);
        $this->assertStringNotContainsString('Ops-Observabilitaet', $observabilityCard);
        $this->assertStringNotContainsString('Log-Kanal', $observabilityCard);
        $this->assertStringNotContainsString('Nur bei Alarm', $observabilityCard);

        $consentCardStart = strpos($response->body, 'data-settings-section-card="tarteaucitron"');
        $this->assertIsInt($consentCardStart);
        $consentCardEnd = strpos($response->body, '</button>', $consentCardStart);
        $this->assertIsInt($consentCardEnd);

        $consentCard = substr($response->body, $consentCardStart, $consentCardEnd - $consentCardStart);
        $this->assertStringContainsString('bannière en bas', $consentCard);
        $this->assertStringContainsString('Icône en bas à droite', $consentCard);
        $this->assertStringNotContainsString('bannière bottom', $consentCard);
        $this->assertStringNotContainsString('Icône BottomRight', $consentCard);

        $backupCardStart = strpos($response->body, 'data-settings-section-card="backup"');
        $this->assertIsInt($backupCardStart);
        $backupCardEnd = strpos($response->body, '</button>', $backupCardStart);
        $this->assertIsInt($backupCardEnd);

        $backupCard = substr($response->body, $backupCardStart, $backupCardEnd - $backupCardStart);
        $this->assertStringContainsString('Sauvegardes', $backupCard);
        $this->assertStringContainsString('Dossier production + dump SQL', $backupCard);
        $this->assertStringNotContainsString('Produktionsordner', $backupCard);

        $cronCardStart = strpos($response->body, 'data-settings-section-card="cron"');
        $this->assertIsInt($cronCardStart);
        $cronCardEnd = strpos($response->body, '</button>', $cronCardStart);
        $this->assertIsInt($cronCardEnd);

        $cronCard = substr($response->body, $cronCardStart, $cronCardEnd - $cronCardStart);
        $this->assertStringContainsString('Cron Center', $cronCard);
        $this->assertStringContainsString('Coordination scheduler + jobs PHP locaux', $cronCard);
        $this->assertStringNotContainsString('Scheduler-Koordination', $cronCard);
    }

    public function testSettingsCronManualTestTargetsRecentRunsSection(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $logger = new AppEventLogger(new LoggerFactory($this->logDir, 'test'));
        $repository = new CronJobRepository($this->editorialSqlDatabase());
        $repository->saveJob([
            'code' => 'manual_check',
            'name' => 'Manual check',
            'description' => 'Safe manual test fixture.',
            'script_path' => 'core/tools/check_vite_assets.php',
            'arguments' => ['args' => ['--help']],
            'schedule_expression' => '* * * * *',
            'status' => 'active',
            'timeout_seconds' => 30,
        ]);

        $controller = $this->controller(
            null,
            $logger,
            new AdminCronCenterService($repository, $logger)
        );

        $response = $controller->handle(
            'settings',
            $this->request(
                'POST',
                '/admin/settings',
                [],
                [
                    'csrf_token' => admin_csrf_token(),
                    'settings_section' => 'cron',
                    'settings_action' => 'cron_test',
                    'cron_job_code' => 'manual_check',
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Test manuel exécuté pour le job cron manual_check.', $response->body);
        $this->assertStringContainsString('data-region-modal-autostart="true"', $response->body);
        $this->assertStringContainsString('data-region-modal-scroll-target="cron-center-history"', $response->body);
        $this->assertStringContainsString('id="cron-center-history"', $response->body);
    }

    public function testSettingsBackupSectionSavesEditableFields(): void
    {
        $backupRoot = sys_get_temp_dir() . '/caramagnols-admin-backup-root-' . bin2hex(random_bytes(4));

        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->handle(
            'settings',
            $this->request(
                'POST',
                '/admin/settings',
                [],
                [
                    'csrf_token' => admin_csrf_token(),
                    'settings_section' => 'backup',
                    'settings_action' => 'backup_save',
                    'backup' => [
                        'root_dir' => $backupRoot,
                        'retention_days' => '21',
                        'files_dir' => $backupRoot . '/archives',
                        'sql_dir' => $backupRoot . '/dumps',
                        'manifest_dir' => $backupRoot . '/manifestes',
                        'php_binary' => 'php',
                        'tar_binary' => 'tar',
                        'mysqldump_binary' => 'mysqldump',
                        'database_host' => 'db123.example-host.tld',
                        'database_port' => '35987',
                        'database_name' => 'CarBDbase',
                        'database_user' => 'db_user_example',
                        'database_password' => 'sql-backup-secret',
                    ],
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Paramètres de backup sauvegardés.', $response->body);
        $this->assertStringNotContainsString('sql-backup-secret', $response->body);
        $this->assertStringContainsString('data-region-modal-autostart="true"', $response->body);
        $this->assertFileExists($this->siteOverrideFile);
        $this->assertFileExists($this->databaseOverrideFile);

        $siteOverride = require $this->siteOverrideFile;
        $databaseOverride = require $this->databaseOverrideFile;
        $this->assertIsArray($siteOverride);
        $this->assertIsArray($databaseOverride);
        $this->assertSame($backupRoot, $siteOverride['backup']['root_dir'] ?? null);
        $this->assertSame(21, $siteOverride['backup']['retention_days'] ?? null);
        $this->assertSame($backupRoot . '/archives', $siteOverride['backup']['files_dir'] ?? null);
        $this->assertSame($backupRoot . '/dumps', $siteOverride['backup']['sql_dir'] ?? null);
        $this->assertSame($backupRoot . '/manifestes', $siteOverride['backup']['manifest_dir'] ?? null);
        $this->assertSame('php', $siteOverride['backup']['php_binary'] ?? null);
        $this->assertSame('db123.example-host.tld', $databaseOverride['host'] ?? null);
        $this->assertSame(35987, $databaseOverride['port'] ?? null);
        $this->assertSame('CarBDbase', $databaseOverride['name'] ?? null);
        $this->assertSame('db_user_example', $databaseOverride['user'] ?? null);
        $this->assertSame('sql-backup-secret', $databaseOverride['password'] ?? null);
    }

    public function testSettingsBackupSectionRejectsUnwritableRoot(): void
    {
        $restrictedParent = sys_get_temp_dir() . '/caramagnols-admin-backup-restricted-' . bin2hex(random_bytes(4));
        $backupRoot = $restrictedParent . '/backups';
        mkdir($restrictedParent, 0777, true);
        chmod($restrictedParent, 0555);

        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        try {
            $response = $controller->handle(
                'settings',
                $this->request(
                    'POST',
                    '/admin/settings',
                    [],
                    [
                        'csrf_token' => admin_csrf_token(),
                        'settings_section' => 'backup',
                        'settings_action' => 'backup_save',
                        'backup' => [
                            'root_dir' => $backupRoot,
                            'retention_days' => '21',
                            'files_dir' => $backupRoot . '/archives',
                            'sql_dir' => $backupRoot . '/dumps',
                            'manifest_dir' => $backupRoot . '/manifestes',
                            'php_binary' => 'php',
                            'tar_binary' => 'tar',
                            'mysqldump_binary' => 'mysqldump',
                            'database_host' => 'db123.example-host.tld',
                            'database_port' => '35987',
                            'database_name' => 'CarBDbase',
                            'database_user' => 'db_user_example',
                            'database_password' => 'sql-backup-secret',
                        ],
                    ]
                )
            );

            $this->assertSame(200, $response->status);
            $this->assertStringContainsString('Le dossier de backup doit être accessible en écriture par PHP', $response->body);
            $this->assertFileDoesNotExist($this->siteOverrideFile);
            $this->assertFileDoesNotExist($this->databaseOverrideFile);
        } finally {
            chmod($restrictedParent, 0755);
        }
    }

    public function testSettingsCacheClearActionDeletesInstagramCacheFile(): void
    {
        global $appConfig;

        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();
        $token = admin_csrf_token();
        $instagramCacheFile = ROOT_PATH . '/var/admin-instagram-cache-clear-' . uniqid('', true) . '.json';
        $previousInstagramConfig = $appConfig['site']['instagram'] ?? null;

        $instagramConfig = is_array($previousInstagramConfig) ? $previousInstagramConfig : [];
        $instagramConfig['cache_path'] = $instagramCacheFile;
        $appConfig['site']['instagram'] = $instagramConfig;

        file_put_contents(
            $instagramCacheFile,
            json_encode(
                [
                    'fingerprint' => 'test',
                    'fetched_at' => time(),
                    'username' => 'test',
                    'posts' => [],
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        try {
            $response = $controller->handle(
                'settings',
                $this->request(
                    'POST',
                    '/admin/settings',
                    [],
                    [
                        'csrf_token' => $token,
                        'settings_section' => 'security',
                        'settings_action' => 'cache_clear',
                    ]
                )
            );

            $this->assertSame(200, $response->status);
            $this->assertStringContainsString('Caches applicatifs vidés', $response->body);
            $this->assertFileDoesNotExist($instagramCacheFile);
        } finally {
            if (file_exists($instagramCacheFile)) {
                unlink($instagramCacheFile);
            }

            if ($previousInstagramConfig === null) {
                unset($appConfig['site']['instagram']);
            } else {
                $appConfig['site']['instagram'] = $previousInstagramConfig;
            }
        }
    }

    public function testSettingsPostDoesNotForceReauthenticationWhenWindowExpired(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $sessionKey = admin_session_key();
        $_SESSION[$sessionKey]['last_reauth_at'] = time() - 700;

        $controller = $this->controller();
        $token = admin_csrf_token();

        $response = $controller->handle(
            'settings',
            $this->request(
                'POST',
                '/admin/settings',
                [],
                [
                    'csrf_token' => $token,
                    'settings_section' => 'tarteaucitron',
                    'tarteaucitron' => [
                        'privacy_url' => '',
                        'orientation' => 'diagonal',
                    ],
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertTrue(admin_is_authenticated());
        $this->assertStringContainsString('tarteaucitron', $response->body);
    }

    public function testSettingsPostPersistsOverrideFilesAndHashesAdminPassword(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();
        $token = admin_csrf_token();

        $response = $controller->handle(
            'settings',
            $this->request(
                'POST',
                '/admin/settings',
                [],
                [
                    'csrf_token' => $token,
                    'database' => [
                        'host' => '127.0.0.1',
                        'port' => '3307',
                        'name' => 'caramagnols',
                        'user' => 'cara_user',
                        'password' => 'sql-top-secret',
                        'prefix' => 'cara_',
                    ],
                    'admin' => [
                        'identifier' => 'nouvel-admin@example.com',
                        'password' => 'ultra-secret',
                        'allowed_ips' => '203.0.113.20, 2001:db8::42, 198.51.100.0/24',
                    ],
                    'url' => [
                        'domain' => 'www.example.com',
                        'ssl_domain' => 'secure.example.com',
                        'base_path' => '\\catalogue',
                    ],
                    'head' => [
                        'metadata_html' => '<meta name="robots" content="index,follow">
<link rel="canonical" href="https://www.example.com/auto-retro">
<script>alert("x")</script>
<script type="application/ld+json">{"@context":"https://schema.org","@type":"WebSite"}</script>',
                    ],
                    'tarteaucitron' => [
                        'enabled' => '1',
                        'privacy_url' => '\\mentions',
                        'orientation' => 'top',
                        'icon_position' => 'TopLeft',
                        'show_icon' => '0',
                        'show_alert_small' => '0',
                        'high_privacy' => '0',
                        'accept_all_cta' => '0',
                        'deny_all_cta' => '0',
                        'mandatory' => '0',
                        'google_consent_mode' => '0',
                        'bing_consent_mode' => '0',
                        'user_config_json' => '{"googletagmanagerId":"GTM-MKG2FFBZ","googleadsId":"AW-123456789"}',
                        'services' => ['youtube', ' vimeo ', '', 'YouTube'],
                    ],
                    'discussions' => [
                        'recaptcha_enabled' => '1',
                        'recaptcha_mode' => 'v3_score',
                        'recaptcha_site_key' => 'site-key-123',
                        'recaptcha_secret_key' => 'secret-key-123',
                        'recaptcha_minimum_score' => '0.7',
                        'recaptcha_timeout_seconds' => '11',
                    ],
                    'instagram' => [
                        'enabled' => '1',
                        'username' => '@paulineetnoel',
                        'user_id' => '17841400011122233',
                        'access_token' => 'ig-access-token-example',
                        'limit' => '5',
                        'rotation_interval_ms' => '6200',
                        'cache_ttl_seconds' => '2400',
                        'timeout_seconds' => '9',
                    ],
                    'log_alerts' => [
                        'notify_on' => 'always',
                    ],
                    'translations' => [
                        'fr' => 'TXT_BLOG_SITE_TITLE=Blog des Caramagnols (admin)' . PHP_EOL . 'TXT_CONTACT_SUBMIT=Envoyer maintenant',
                        'en' => 'TXT_BLOG_SITE_TITLE=Caramagnols Blog',
                        'de' => '',
                    ],
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Paramètres d’exploitation sauvegardés.', $response->body);
        $this->assertStringNotContainsString('sql-top-secret', $response->body);
        $this->assertFileExists($this->databaseOverrideFile);
        $this->assertFileExists($this->adminOverrideFile);
        $this->assertFileExists($this->siteOverrideFile);

        $databaseOverride = require $this->databaseOverrideFile;
        $adminOverride = require $this->adminOverrideFile;
        $siteOverride = require $this->siteOverrideFile;

        $this->assertSame('127.0.0.1', $databaseOverride['host'] ?? null);
        $this->assertSame(3307, $databaseOverride['port'] ?? null);
        $this->assertSame('cara_', $databaseOverride['prefix'] ?? null);
        $this->assertSame('sql-top-secret', $databaseOverride['password'] ?? null);
        $this->assertSame('nouvel-admin@example.com', $adminOverride['identifier'] ?? null);
        $this->assertNotSame('ultra-secret', $adminOverride['password_hash'] ?? null);
        $this->assertTrue(password_verify('ultra-secret', (string) ($adminOverride['password_hash'] ?? '')));
        $this->assertSame(['203.0.113.20', '2001:db8::42', '198.51.100.0/24'], $adminOverride['allowed_ips'] ?? null);
        $this->assertSame('www.example.com', $siteOverride['url']['domain'] ?? null);
        $this->assertSame('secure.example.com', $siteOverride['url']['ssl_domain'] ?? null);
        $this->assertSame('/catalogue', $siteOverride['url']['base_path'] ?? null);
        $this->assertStringContainsString('<meta name="robots" content="index,follow" />', (string) ($siteOverride['head_metadata_html'] ?? ''));
        $this->assertStringContainsString('<link rel="canonical" href="https://www.example.com/auto-retro" />', (string) ($siteOverride['head_metadata_html'] ?? ''));
        $this->assertStringContainsString('<script type="application/ld+json">{"@context":"https://schema.org","@type":"WebSite"}</script>', (string) ($siteOverride['head_metadata_html'] ?? ''));
        $this->assertStringNotContainsString('alert("x")', (string) ($siteOverride['head_metadata_html'] ?? ''));
        $this->assertTrue((bool) ($siteOverride['tarteaucitron']['enabled'] ?? false));
        $this->assertSame('/mentions', $siteOverride['tarteaucitron']['privacy_url'] ?? null);
        $this->assertSame('top', $siteOverride['tarteaucitron']['orientation'] ?? null);
        $this->assertSame('TopLeft', $siteOverride['tarteaucitron']['icon_position'] ?? null);
        $this->assertFalse((bool) ($siteOverride['tarteaucitron']['show_icon'] ?? true));
        $this->assertFalse((bool) ($siteOverride['tarteaucitron']['show_alert_small'] ?? true));
        $this->assertFalse((bool) ($siteOverride['tarteaucitron']['high_privacy'] ?? true));
        $this->assertFalse((bool) ($siteOverride['tarteaucitron']['accept_all_cta'] ?? true));
        $this->assertFalse((bool) ($siteOverride['tarteaucitron']['deny_all_cta'] ?? true));
        $this->assertFalse((bool) ($siteOverride['tarteaucitron']['mandatory'] ?? true));
        $this->assertFalse((bool) ($siteOverride['tarteaucitron']['google_consent_mode'] ?? true));
        $this->assertFalse((bool) ($siteOverride['tarteaucitron']['bing_consent_mode'] ?? true));
        $this->assertSame('{"googleadsId":"AW-123456789","googletagmanagerId":"GTM-MKG2FFBZ"}', $siteOverride['tarteaucitron']['user_config_json'] ?? null);
        $this->assertSame(['youtube', 'vimeo'], $siteOverride['tarteaucitron']['services'] ?? null);
        $this->assertTrue((bool) ($siteOverride['discussions']['recaptcha']['enabled'] ?? false));
        $this->assertSame('v3_score', $siteOverride['discussions']['recaptcha']['mode'] ?? null);
        $this->assertSame('site-key-123', $siteOverride['discussions']['recaptcha']['site_key'] ?? null);
        $this->assertSame('secret-key-123', $siteOverride['discussions']['recaptcha']['secret_key'] ?? null);
        $this->assertSame(0.7, $siteOverride['discussions']['recaptcha']['minimum_score'] ?? null);
        $this->assertSame(11, $siteOverride['discussions']['recaptcha']['timeout_seconds'] ?? null);
        $this->assertTrue((bool) ($siteOverride['instagram']['enabled'] ?? false));
        $this->assertSame('paulineetnoel', $siteOverride['instagram']['username'] ?? null);
        $this->assertSame('17841400011122233', $siteOverride['instagram']['user_id'] ?? null);
        $this->assertSame('ig-access-token-example', $siteOverride['instagram']['access_token'] ?? null);
        $this->assertSame(5, $siteOverride['instagram']['limit'] ?? null);
        $this->assertSame(6200, $siteOverride['instagram']['rotation_interval_ms'] ?? null);
        $this->assertSame(2400, $siteOverride['instagram']['cache_ttl_seconds'] ?? null);
        $this->assertSame(9, $siteOverride['instagram']['timeout_seconds'] ?? null);
        $this->assertSame('always', $siteOverride['log_alerts']['notify_on'] ?? null);
        $this->assertSame('Blog des Caramagnols (admin)', $siteOverride['i18n_overrides']['fr']['TXT_BLOG_SITE_TITLE'] ?? null);
        $this->assertSame('Envoyer maintenant', $siteOverride['i18n_overrides']['fr']['TXT_CONTACT_SUBMIT'] ?? null);
        $this->assertSame('Caramagnols Blog', $siteOverride['i18n_overrides']['en']['TXT_BLOG_SITE_TITLE'] ?? null);
    }

    public function testSettingsValidationErrorReopensSubmittedPopup(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();
        $token = admin_csrf_token();

        $response = $controller->handle(
            'settings',
            $this->request(
                'POST',
                '/admin/settings',
                [],
                [
                    'csrf_token' => $token,
                    'settings_section' => 'tarteaucitron',
                    'tarteaucitron' => [
                        'privacy_url' => '',
                        'orientation' => 'diagonal',
                    ],
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('tarteaucitron', $response->body);
        $this->assertStringContainsString('data-settings-section-card="tarteaucitron"', $response->body);
        $this->assertStringContainsString('data-region-modal-autostart="true"', $response->body);
    }

    public function testSettingsRejectsInvalidLogAlertsMode(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();
        $token = admin_csrf_token();

        $response = $controller->handle(
            'settings',
            $this->request(
                'POST',
                '/admin/settings',
                [],
                [
                    'csrf_token' => $token,
                    'settings_section' => 'observability',
                    'database' => [
                        'host' => '127.0.0.1',
                        'port' => '3306',
                        'name' => 'caramagnols',
                        'user' => 'cara_user',
                        'password' => '',
                        'prefix' => 'cara_',
                    ],
                    'admin' => [
                        'identifier' => 'admin@example.com',
                        'password' => '',
                    ],
                    'log_alerts' => [
                        'notify_on' => 'burst',
                    ],
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Le mode de notification des alertes logs est invalide.', $response->body);
        $this->assertStringContainsString('data-settings-section-card="observability"', $response->body);
        $this->assertStringContainsString('data-region-modal-autostart="true"', $response->body);
    }

    public function testSettingsRejectsInvalidAdminAllowedIp(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();
        $token = admin_csrf_token();

        $response = $controller->handle(
            'settings',
            $this->request(
                'POST',
                '/admin/settings',
                [],
                [
                    'csrf_token' => $token,
                    'settings_section' => 'admin',
                    'admin' => [
                        'identifier' => 'admin@example.com',
                        'password' => '',
                        'allowed_ips' => '203.0.113.12, not-an-ip',
                    ],
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('IP autorisée', $response->body);
        $this->assertStringContainsString('not-an-ip', $response->body);
        $this->assertStringContainsString('data-region-modal-autostart="true"', $response->body);
    }

    public function testSettingsRejectsInvalidTarteaucitronServiceIdentifier(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();
        $token = admin_csrf_token();

        $response = $controller->handle(
            'settings',
            $this->request(
                'POST',
                '/admin/settings',
                [],
                [
                    'csrf_token' => $token,
                    'settings_section' => 'tarteaucitron',
                    'database' => [
                        'host' => '127.0.0.1',
                        'port' => '3306',
                        'name' => 'caramagnols',
                        'user' => 'cara_user',
                        'password' => '',
                        'prefix' => 'cara_',
                    ],
                    'admin' => [
                        'identifier' => 'admin@example.com',
                        'password' => '',
                    ],
                    'tarteaucitron' => [
                        'privacy_url' => '/mentions',
                        'orientation' => 'bottom',
                        'icon_position' => 'BottomRight',
                        'services' => ['Google Maps'],
                    ],
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Google Maps', $response->body);
        $this->assertStringContainsString('identifiant technique du service', $response->body);
        $this->assertStringContainsString('data-region-modal-autostart="true"', $response->body);
    }

    public function testSettingsRejectsInvalidTarteaucitronUserConfigJson(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();
        $token = admin_csrf_token();

        $response = $controller->handle(
            'settings',
            $this->request(
                'POST',
                '/admin/settings',
                [],
                [
                    'csrf_token' => $token,
                    'settings_section' => 'tarteaucitron',
                    'database' => [
                        'host' => '127.0.0.1',
                        'port' => '3306',
                        'name' => 'caramagnols',
                        'user' => 'cara_user',
                        'password' => '',
                        'prefix' => 'cara_',
                    ],
                    'admin' => [
                        'identifier' => 'admin@example.com',
                        'password' => '',
                    ],
                    'tarteaucitron' => [
                        'privacy_url' => '/mentions',
                        'orientation' => 'bottom',
                        'icon_position' => 'BottomRight',
                        'user_config_json' => '{"googletagmanagerId":"GTM-XXXX",}',
                        'services' => ['googletagmanager'],
                    ],
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('variables services tarteaucitron est invalide', $response->body);
        $this->assertStringContainsString('data-region-modal-autostart="true"', $response->body);
    }

    public function testSettingsUrlSectionAllowsEmptyObjectTarteaucitronUserConfig(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();
        $token = admin_csrf_token();

        $response = $controller->handle(
            'settings',
            $this->request(
                'POST',
                '/admin/settings',
                [],
                [
                    'csrf_token' => $token,
                    'settings_section' => 'url',
                    'database' => [
                        'host' => '127.0.0.1',
                        'port' => '3306',
                        'name' => 'caramagnols',
                        'user' => 'cara_user',
                        'password' => '',
                        'prefix' => 'cara_',
                    ],
                    'admin' => [
                        'identifier' => 'admin@example.com',
                        'password' => '',
                    ],
                    'url' => [
                        'domain' => 'www.example.com',
                        'ssl_domain' => 'secure.example.com',
                        'base_path' => '/',
                    ],
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Paramètres d’exploitation sauvegardés.', $response->body);
        $this->assertStringNotContainsString('Les variables services tarteaucitron doivent être un objet JSON (pas une liste).', $response->body);
    }

    public function testSettingsUrlSectionPreservesTarteaucitronFalseFlagsWhenConfiguredAsStrings(): void
    {
        global $appConfig;
        $appConfig['site']['tarteaucitron'] = [
            'enabled' => 'false',
            'privacy_url' => '/',
            'orientation' => 'bottom',
            'icon_position' => 'BottomRight',
            'show_icon' => 'false',
            'show_alert_small' => 'false',
            'high_privacy' => 'false',
            'accept_all_cta' => 'false',
            'deny_all_cta' => 'false',
            'mandatory' => 'false',
            'google_consent_mode' => 'false',
            'bing_consent_mode' => 'false',
            'user_config_json' => '{}',
            'services' => ['youtube'],
        ];

        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();
        $token = admin_csrf_token();

        $response = $controller->handle(
            'settings',
            $this->request(
                'POST',
                '/admin/settings',
                [],
                [
                    'csrf_token' => $token,
                    'settings_section' => 'url',
                    'database' => [
                        'host' => '127.0.0.1',
                        'port' => '3306',
                        'name' => 'caramagnols',
                        'user' => 'cara_user',
                        'password' => '',
                        'prefix' => 'cara_',
                    ],
                    'admin' => [
                        'identifier' => 'admin@example.com',
                        'password' => '',
                    ],
                    'url' => [
                        'domain' => 'www.example.com',
                        'ssl_domain' => 'secure.example.com',
                        'base_path' => '/',
                    ],
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Paramètres d’exploitation sauvegardés.', $response->body);
        $this->assertMatchesRegularExpression('/name="tarteaucitron\\[enabled\\]" value="1"(?! checked)/', $response->body);
        $this->assertMatchesRegularExpression('/name="tarteaucitron\\[show_icon\\]" value="1"(?! checked)/', $response->body);
        $this->assertMatchesRegularExpression('/name="tarteaucitron\\[show_alert_small\\]" value="1"(?! checked)/', $response->body);

        $siteOverride = require $this->siteOverrideFile;
        $this->assertFalse((bool) ($siteOverride['tarteaucitron']['enabled'] ?? true));
        $this->assertFalse((bool) ($siteOverride['tarteaucitron']['show_icon'] ?? true));
        $this->assertFalse((bool) ($siteOverride['tarteaucitron']['show_alert_small'] ?? true));
        $this->assertFalse((bool) ($siteOverride['tarteaucitron']['high_privacy'] ?? true));
        $this->assertFalse((bool) ($siteOverride['tarteaucitron']['accept_all_cta'] ?? true));
        $this->assertFalse((bool) ($siteOverride['tarteaucitron']['deny_all_cta'] ?? true));
        $this->assertFalse((bool) ($siteOverride['tarteaucitron']['mandatory'] ?? true));
        $this->assertFalse((bool) ($siteOverride['tarteaucitron']['google_consent_mode'] ?? true));
        $this->assertFalse((bool) ($siteOverride['tarteaucitron']['bing_consent_mode'] ?? true));
    }

    public function testSettingsRejectsTarteaucitronUserConfigJsonListSyntax(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();
        $token = admin_csrf_token();

        $response = $controller->handle(
            'settings',
            $this->request(
                'POST',
                '/admin/settings',
                [],
                [
                    'csrf_token' => $token,
                    'settings_section' => 'tarteaucitron',
                    'database' => [
                        'host' => '127.0.0.1',
                        'port' => '3306',
                        'name' => 'caramagnols',
                        'user' => 'cara_user',
                        'password' => '',
                        'prefix' => 'cara_',
                    ],
                    'admin' => [
                        'identifier' => 'admin@example.com',
                        'password' => '',
                    ],
                    'tarteaucitron' => [
                        'privacy_url' => '/mentions',
                        'orientation' => 'bottom',
                        'icon_position' => 'BottomRight',
                        'user_config_json' => '[]',
                        'services' => ['youtube'],
                    ],
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('objet JSON (pas une liste)', $response->body);
        $this->assertStringContainsString('data-region-modal-autostart="true"', $response->body);
    }

    public function testSettingsInstagramTestChecksApiWithoutSavingOverrides(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $logger = new AppEventLogger(new LoggerFactory($this->logDir, 'test'));
        $instagramCacheFile = ROOT_PATH . '/var/admin-instagram-test-cache-' . uniqid('', true) . '.json';
        $instagramService = new InstagramFeedService(
            $instagramCacheFile,
            static fn (string $url, int $timeout): array => [
                'status' => 200,
                'body' => json_encode(
                    [
                        'data' => [
                            [
                                'id' => 'post-1',
                                'caption' => 'Post de test',
                                'media_type' => 'IMAGE',
                                'media_url' => 'https://cdn.example.test/post.webp',
                                'thumbnail_url' => '',
                                'permalink' => 'https://www.instagram.com/p/post-1',
                                'timestamp' => '2026-03-18T20:10:00+00:00',
                                'username' => 'paulineetnoel',
                            ],
                        ],
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ],
            static fn (): int => 1700000600
        );
        $settingsService = new AdminSettingsService(
            $this->databaseOverrideFile,
            $this->adminOverrideFile,
            $logger,
            $this->siteOverrideFile,
            $instagramService
        );
        $controller = $this->controller($settingsService, $logger);
        $token = admin_csrf_token();

        $response = $controller->handle(
            'settings',
            $this->request(
                'POST',
                '/admin/settings',
                [],
                [
                    'csrf_token' => $token,
                    'settings_section' => 'instagram',
                    'settings_action' => 'instagram_test',
                    'instagram' => [
                        'enabled' => '1',
                        'username' => 'paulineetnoel',
                        'access_token' => 'test-token',
                        'limit' => '4',
                        'rotation_interval_ms' => '5500',
                        'cache_ttl_seconds' => '1800',
                        'timeout_seconds' => '8',
                    ],
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Connexion Instagram validée', $response->body);
        $this->assertStringContainsString('data-region-modal-autostart="true"', $response->body);
        $this->assertFileDoesNotExist($this->siteOverrideFile);

        if (file_exists($instagramCacheFile)) {
            unlink($instagramCacheFile);
        }
    }

    private function controller(
        ?AdminSettingsService $settingsService = null,
        ?AppEventLogger $logger = null,
        ?AdminCronCenterService $cronCenterService = null
    ): AdminController {
        $logger = $logger ?? new AppEventLogger(new LoggerFactory($this->logDir, 'test'));
        $settingsService = $settingsService ?? new AdminSettingsService(
            $this->databaseOverrideFile,
            $this->adminOverrideFile,
            $logger,
            $this->siteOverrideFile
        );

        return new AdminController(
            new AdminRouteResolver('admin'),
            $logger,
            null,
            null,
            $settingsService,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $cronCenterService
        );
    }

    private function removeDirectoryRecursively(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (glob(rtrim($directory, '/') . '/*') ?: [] as $path) {
            if (is_dir($path)) {
                $this->removeDirectoryRecursively($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
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
        array $headers = [],
        array $files = []
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
            array_merge(['Host' => '127.0.0.1:8000'], $headers),
            '',
            $files
        );
    }

    private function createTemporaryJpeg(int $width, int $height): string
    {
        $width = max(1, $width);
        $height = max(1, $height);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'cara-page-media-');
        if (!is_string($temporaryPath)) {
            self::fail('Impossible de creer un fichier temporaire image.');
        }

        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            self::fail('Impossible de creer une image de test.');
        }

        $background = imagecolorallocate($image, 24, 68, 124);
        imagefilledrectangle($image, 0, 0, $width, $height, $background);
        $textColor = imagecolorallocate($image, 255, 255, 255);
        imagestring($image, 5, 20, 20, 'Caramagnols', $textColor);
        imagejpeg($image, $temporaryPath, 90);
        imagedestroy($image);

        return $temporaryPath;
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
