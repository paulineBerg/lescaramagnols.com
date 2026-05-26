<?php

declare(strict_types=1);

use Caramagnols\Blog\BlogDiscussionApiController;
use Caramagnols\Blog\BlogPublicUrlResolver;
use Caramagnols\Blog\JsonBlogDiscussionRepository;
use Caramagnols\Blog\JsonBlogRepository;
use Caramagnols\Content\PageRepository;
use Caramagnols\Http\Request;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\Logging\LoggerFactory;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class BlogDiscussionApiControllerTest extends TestCase
{
    private string $blogDir;
    private string $discussionDir;
    private string $logDir;
    private string $rateLimitDir;
    private string $pagesFile;

    protected function setUp(): void
    {
        ensure_session_started();
        $_SESSION = [];

        $this->blogDir = sys_get_temp_dir() . '/caramagnols-blog-discussion-api-blog-' . bin2hex(random_bytes(6));
        $this->discussionDir = sys_get_temp_dir() . '/caramagnols-blog-discussion-api-discussions-' . bin2hex(random_bytes(6));
        $this->logDir = sys_get_temp_dir() . '/caramagnols-blog-discussion-api-logs-' . bin2hex(random_bytes(6));
        $this->rateLimitDir = sys_get_temp_dir() . '/caramagnols-blog-discussion-api-ratelimits-' . bin2hex(random_bytes(6));
        $this->pagesFile = ROOT_PATH . '/var/blog-discussion-pages-' . bin2hex(random_bytes(6)) . '.json';
        mkdir($this->blogDir, 0777, true);
        mkdir($this->discussionDir, 0777, true);
        mkdir($this->logDir, 0777, true);
        mkdir($this->rateLimitDir, 0777, true);

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
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        file_put_contents(
            $this->blogDir . '/bonjour.fr.json',
            json_encode(
                [
                    'title' => 'Bonjour',
                    'slug' => 'bonjour',
                    'lang' => 'fr',
                    'status' => 'published',
                    'date' => '2026-03-18 10:00:00',
                    'content' => '<p>Contenu.</p>',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        global $appConfig;
        $appConfig['blog']['data_dir'] = $this->blogDir;
        $appConfig['blog']['discussions_data_dir'] = $this->discussionDir;
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
        $appConfig['admin']['trust_proxy_headers'] = false;
        $appConfig['security']['rate_limit_dir'] = $this->rateLimitDir;
    }

    protected function tearDown(): void
    {
        $_SESSION = [];

        foreach ([$this->blogDir, $this->discussionDir] as $dir) {
            $files = glob($dir . '/*');
            if (is_array($files)) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }

            @rmdir($dir);
        }

        foreach ([$this->logDir, $this->rateLimitDir] as $dir) {
            $files = glob($dir . '/*');
            if (is_array($files)) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }

            @rmdir($dir);
        }

        if (file_exists($this->pagesFile)) {
            @unlink($this->pagesFile);
        }
    }

    private function logger(): AppEventLogger
    {
        return new AppEventLogger(new LoggerFactory($this->logDir, 'test'));
    }

    public function testLogClientEventWritesTelemetryAndReturnsNoContent(): void
    {
        $controller = new BlogDiscussionApiController(
            new JsonBlogRepository($this->blogDir),
            new JsonBlogDiscussionRepository($this->discussionDir),
            null,
            $this->logger(),
            new BlogPublicUrlResolver(
                new JsonBlogRepository($this->blogDir),
                new PageRepository((string) ($this->pagesFile ?: '')),
                'fr'
            )
        );

        $response = $controller->logClientEvent(
            new Request(
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => '/core/blog/log_discussion_client.php',
                    'REMOTE_ADDR' => '127.0.0.1',
                ],
                [],
                [],
                [],
                ['Host' => 'example.test', 'Content-Type' => 'application/json'],
                json_encode(
                    [
                        'article_slug' => 'bonjour',
                        'article_lang' => 'fr',
                        'stage' => 'recaptcha_client_failure',
                        'level' => 'error',
                        'mode' => 'v3_score',
                        'page' => '/fr/auto-retro/austin/histoire-de-austin.php',
                        'details' => [
                            'error_message' => 'recaptcha_error',
                            'execute_timeout_ms' => 12000,
                        ],
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ) ?: ''
            )
        );

        $this->assertSame(204, $response->status);
        $this->assertSame('no-store', $response->headers['Cache-Control'] ?? null);

        $contentLogPath = $this->logDir . '/content.log';
        $this->assertFileExists($contentLogPath);

        $logContents = (string) file_get_contents($contentLogPath);
        $this->assertStringContainsString('blog.discussion.client_telemetry', $logContents);
        $this->assertStringContainsString('recaptcha_client_failure', $logContents);
    }

    public function testLogClientEventSanitizesAndLimitsTelemetryDetailsPayload(): void
    {
        $controller = new BlogDiscussionApiController(
            new JsonBlogRepository($this->blogDir),
            new JsonBlogDiscussionRepository($this->discussionDir),
            null,
            $this->logger(),
            null
        );

        $longText = str_repeat('A', 1200);
        $payload = json_encode(
            [
                'article_slug' => 'bonjour',
                'article_lang' => 'fr',
                'stage' => 'recaptcha_client_failure',
                'level' => 'error',
                'mode' => 'v3_score',
                'page' => '/fr/auto-retro/austin/histoire-de-austin.php',
                'details' => [
                    'long_text' => $longText,
                    'error_message' => '<script>alert(1)</script>',
                    'long_array' => array_fill(0, 40, 'valeur-avec-texte-tres-long'),
                ],
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: '';

        $response = $controller->logClientEvent(
            new Request(
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => '/core/blog/log_discussion_client.php',
                    'REMOTE_ADDR' => '127.0.0.1',
                ],
                [],
                [],
                [],
                ['Host' => 'example.test', 'Content-Type' => 'application/json'],
                $payload
            )
        );

        $this->assertSame(204, $response->status);
        $this->assertSame('no-store', $response->headers['Cache-Control'] ?? null);

        $contentLogPath = $this->logDir . '/content.log';
        $this->assertFileExists($contentLogPath);

        $logContents = (string) file_get_contents($contentLogPath);
        $this->assertStringContainsString('blog.discussion.client_telemetry', $logContents);
        $this->assertStringNotContainsString(substr($longText, 320), $logContents);
        $this->assertStringContainsString(substr($longText, 0, 16), $logContents);
    }

    public function testLogClientEventIsRateLimitedPerIp(): void
    {
        global $appConfig;
        $appConfig['site']['discussions']['telemetry_rate_limit_per_ip'] = 1;
        $appConfig['site']['discussions']['telemetry_rate_limit_window'] = 120;
        $appConfig['site']['discussions']['telemetry_sample_divisor'] = 1;

        $controller = new BlogDiscussionApiController(
            new JsonBlogRepository($this->blogDir),
            new JsonBlogDiscussionRepository($this->discussionDir),
            null,
            $this->logger(),
            null
        );
        $payload = json_encode(
            [
                'article_slug' => 'bonjour',
                'article_lang' => 'fr',
                'stage' => 'recaptcha_client_failure',
                'level' => 'error',
                'mode' => 'v3_score',
                'page' => '/fr/auto-retro/austin/histoire-de-austin.php',
                'details' => ['error_message' => 'timeout'],
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: '';

        $request = new Request(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/core/blog/log_discussion_client.php',
                'REMOTE_ADDR' => '127.0.0.1',
            ],
            [],
            [],
            [],
            ['Host' => 'example.test', 'Content-Type' => 'application/json'],
            $payload
        );

        $first = $controller->logClientEvent($request);
        $second = $controller->logClientEvent($request);

        $this->assertSame(204, $first->status);
        $this->assertSame('no-store', $first->headers['Cache-Control'] ?? null);
        $this->assertSame(204, $second->status);
        $this->assertSame('no-store', $second->headers['Cache-Control'] ?? null);

        $securityLogPath = $this->logDir . '/security.log';
        $this->assertFileExists($securityLogPath);

        $securityLogContents = (string) file_get_contents($securityLogPath);
        $this->assertStringContainsString('blog.discussion.client_telemetry_rate_limited', $securityLogContents);
    }

    public function testLogClientEventRateLimitIsScopedByEndpoint(): void
    {
        global $appConfig;
        $appConfig['site']['discussions']['telemetry_rate_limit_per_ip'] = 1;
        $appConfig['site']['discussions']['telemetry_rate_limit_window'] = 120;
        $appConfig['site']['discussions']['telemetry_sample_divisor'] = 1;

        $controller = new BlogDiscussionApiController(
            new JsonBlogRepository($this->blogDir),
            new JsonBlogDiscussionRepository($this->discussionDir),
            null,
            $this->logger(),
            null
        );

        $payload = json_encode(
            [
                'article_slug' => 'bonjour',
                'article_lang' => 'fr',
                'stage' => 'recaptcha_client_failure',
                'level' => 'error',
                'mode' => 'v3_score',
                'page' => '/fr/auto-retro/austin/histoire-de-austin.php',
                'details' => ['error_message' => 'timeout'],
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: '';

        $first = $controller->logClientEvent(
            new Request(
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => '/core/blog/log_discussion_client.php',
                    'REMOTE_ADDR' => '198.51.100.99',
                ],
                [],
                [],
                [],
                ['Host' => 'example.test', 'Content-Type' => 'application/json'],
                $payload
            )
        );

        $second = $controller->logClientEvent(
            new Request(
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => '/core/blog/submit_discussion.php',
                    'REMOTE_ADDR' => '198.51.100.99',
                ],
                [],
                [],
                [],
                ['Host' => 'example.test', 'Content-Type' => 'application/json'],
                $payload
            )
        );

        $this->assertSame(204, $first->status);
        $this->assertSame(204, $second->status);

        $securityLogPath = $this->logDir . '/security.log';
        $this->assertFileDoesNotExist($securityLogPath);
    }

    public function testSubmitCreatesPendingDiscussionAndRedirectsToArticle(): void
    {
        $scope = 'blog_discussion_' . hash('sha256', 'fr:bonjour');
        $token = csrf_token($scope);
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['_blog_discussion_form_nonces'][$nonce] = [
            'scope' => $scope,
            'issued_at' => time() - 3,
        ];

        $controller = new BlogDiscussionApiController(
            new JsonBlogRepository($this->blogDir),
            new JsonBlogDiscussionRepository($this->discussionDir)
        );

        $response = $controller->submit(
            new Request(
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => '/core/blog/submit_discussion.php',
                    'REMOTE_ADDR' => '127.0.0.1',
                ],
                [],
                [
                    'article_slug' => 'bonjour',
                    'article_lang' => 'fr',
                    'csrf_token' => $token,
                    'form_nonce' => $nonce,
                    'website' => '',
                    'author' => 'Pauline',
                    'email' => 'pauline@example.com',
                    'content' => 'Bonjour à tous',
                ],
                [],
                ['Host' => 'example.test']
            )
        );

        $this->assertSame(303, $response->status);
        $this->assertStringContainsString('/blog/article/bonjour#discussion-form', (string) ($response->headers['Location'] ?? ''));

        $repository = new JsonBlogDiscussionRepository($this->discussionDir);
        $rows = $repository->all();
        $this->assertCount(1, $rows);
        $this->assertSame('pending', $rows[0]['status']);
        $this->assertSame('Pauline', $rows[0]['author']);
    }

    public function testSubmitReturnsJsonSuccessForAjaxRequests(): void
    {
        $scope = 'blog_discussion_' . hash('sha256', 'fr:bonjour');
        $token = csrf_token($scope);
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['_blog_discussion_form_nonces'][$nonce] = [
            'scope' => $scope,
            'issued_at' => time() - 3,
        ];

        $controller = new BlogDiscussionApiController(
            new JsonBlogRepository($this->blogDir),
            new JsonBlogDiscussionRepository($this->discussionDir)
        );

        $response = $controller->submit(
            new Request(
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => '/core/blog/submit_discussion.php',
                    'REMOTE_ADDR' => '127.0.0.1',
                ],
                [],
                [
                    'article_slug' => 'bonjour',
                    'article_lang' => 'fr',
                    'csrf_token' => $token,
                    'form_nonce' => $nonce,
                    'website' => '',
                    'author' => 'Pauline',
                    'email' => 'pauline@example.com',
                    'content' => 'Bonjour à tous',
                ],
                [],
                [
                    'Host' => 'example.test',
                    'Accept' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            )
        );

        $this->assertSame(201, $response->status);
        $this->assertSame('application/json; charset=utf-8', $response->headers['Content-Type'] ?? null);

        $payload = json_decode($response->body, true);
        $this->assertIsArray($payload);
        $this->assertTrue((bool) ($payload['ok'] ?? false));
        $this->assertSame('Merci. Votre message est enregistré et en attente de validation par l’équipe.', (string) ($payload['message'] ?? ''));
        $this->assertNotSame('', (string) ($payload['form']['csrf_token'] ?? ''));
        $this->assertNotSame('', (string) ($payload['form']['form_nonce'] ?? ''));
    }

    public function testSubmitReturnsJsonErrorForAjaxRequestsAndRefreshesFormState(): void
    {
        $scope = 'blog_discussion_' . hash('sha256', 'fr:bonjour');
        $token = csrf_token($scope);
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['_blog_discussion_form_nonces'][$nonce] = [
            'scope' => $scope,
            'issued_at' => time() - 3,
        ];

        $controller = new BlogDiscussionApiController(
            new JsonBlogRepository($this->blogDir),
            new JsonBlogDiscussionRepository($this->discussionDir)
        );

        $response = $controller->submit(
            new Request(
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => '/core/blog/submit_discussion.php',
                    'REMOTE_ADDR' => '127.0.0.1',
                ],
                [],
                [
                    'article_slug' => 'bonjour',
                    'article_lang' => 'fr',
                    'csrf_token' => $token,
                    'form_nonce' => $nonce,
                    'website' => '',
                    'author' => 'Pauline',
                    'email' => 'pauline@example.com',
                    'content' => '',
                ],
                [],
                [
                    'Host' => 'example.test',
                    'Accept' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            )
        );

        $this->assertSame(422, $response->status);
        $payload = json_decode($response->body, true);
        $this->assertIsArray($payload);
        $this->assertFalse((bool) ($payload['ok'] ?? true));
        $this->assertStringContainsString('vide', (string) ($payload['message'] ?? ''));
        $this->assertNotSame('', (string) ($payload['form']['csrf_token'] ?? ''));
        $this->assertNotSame('', (string) ($payload['form']['form_nonce'] ?? ''));
        $this->assertNotSame($nonce, (string) ($payload['form']['form_nonce'] ?? ''));
    }


    public function testSubmitRejectsFilledHoneypotField(): void
    {
        $scope = 'blog_discussion_' . hash('sha256', 'fr:bonjour');
        $token = csrf_token($scope);
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['_blog_discussion_form_nonces'][$nonce] = [
            'scope' => $scope,
            'issued_at' => time() - 3,
        ];

        $controller = new BlogDiscussionApiController(
            new JsonBlogRepository($this->blogDir),
            new JsonBlogDiscussionRepository($this->discussionDir)
        );

        $response = $controller->submit(
            new Request(
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => '/core/blog/submit_discussion.php',
                    'REMOTE_ADDR' => '127.0.0.1',
                ],
                [],
                [
                    'article_slug' => 'bonjour',
                    'article_lang' => 'fr',
                    'csrf_token' => $token,
                    'form_nonce' => $nonce,
                    'website' => 'spam-bot',
                    'author' => 'Pauline',
                    'email' => 'pauline@example.com',
                    'content' => 'Bonjour à tous',
                ],
                [],
                ['Host' => 'example.test']
            )
        );

        $this->assertSame(303, $response->status);

        $repository = new JsonBlogDiscussionRepository($this->discussionDir);
        $this->assertSame([], $repository->all());
    }

    public function testSubmitRedirectsBackToProvidedPublicPageWhenReturnToIsValid(): void
    {
        $scope = 'blog_discussion_' . hash('sha256', 'fr:bonjour');
        $token = csrf_token($scope);
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['_blog_discussion_form_nonces'][$nonce] = [
            'scope' => $scope,
            'issued_at' => time() - 3,
        ];

        $controller = new BlogDiscussionApiController(
            new JsonBlogRepository($this->blogDir),
            new JsonBlogDiscussionRepository($this->discussionDir)
        );

        $response = $controller->submit(
            new Request(
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => '/core/blog/submit_discussion.php',
                    'REMOTE_ADDR' => '127.0.0.1',
                ],
                [],
                [
                    'article_slug' => 'bonjour',
                    'article_lang' => 'fr',
                    'csrf_token' => $token,
                    'form_nonce' => $nonce,
                    'return_to' => '/auto-retro/austin/histoire-de-austin.php?lang=fr&open_article=bonjour#discussion-form-bonjour',
                    'website' => '',
                    'author' => 'Pauline',
                    'email' => 'pauline@example.com',
                    'content' => 'Bonjour à tous',
                ],
                [],
                ['Host' => 'example.test']
            )
        );

        $this->assertSame(303, $response->status);
        $this->assertStringContainsString(
            '/auto-retro/austin/histoire-de-austin.php?lang=fr&open_article=bonjour#discussion-form-bonjour',
            (string) ($response->headers['Location'] ?? '')
        );
    }

    public function testSubmitIsBlockedWhenDiscussionsAreDisabled(): void
    {
        global $appConfig;
        $appConfig['site']['discussions']['enabled'] = false;

        $scope = 'blog_discussion_' . hash('sha256', 'fr:bonjour');
        $token = csrf_token($scope);
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['_blog_discussion_form_nonces'][$nonce] = [
            'scope' => $scope,
            'issued_at' => time() - 3,
        ];

        $controller = new BlogDiscussionApiController(
            new JsonBlogRepository($this->blogDir),
            new JsonBlogDiscussionRepository($this->discussionDir)
        );

        $response = $controller->submit(
            new Request(
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => '/core/blog/submit_discussion.php',
                    'REMOTE_ADDR' => '127.0.0.1',
                ],
                [],
                [
                    'article_slug' => 'bonjour',
                    'article_lang' => 'fr',
                    'csrf_token' => $token,
                    'form_nonce' => $nonce,
                    'website' => '',
                    'author' => 'Pauline',
                    'email' => 'pauline@example.com',
                    'content' => 'Bonjour à tous',
                ],
                [],
                ['Host' => 'example.test']
            )
        );

        $this->assertSame(303, $response->status);
        $this->assertStringContainsString('/blog/article/bonjour#discussion-form', (string) ($response->headers['Location'] ?? ''));

        $repository = new JsonBlogDiscussionRepository($this->discussionDir);
        $this->assertSame([], $repository->all());
    }

    public function testSubmitIsBlockedWhenAccountIsRequired(): void
    {
        global $appConfig;
        $appConfig['site']['discussions']['require_account'] = true;

        $scope = 'blog_discussion_' . hash('sha256', 'fr:bonjour');
        $token = csrf_token($scope);
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['_blog_discussion_form_nonces'][$nonce] = [
            'scope' => $scope,
            'issued_at' => time() - 3,
        ];

        $controller = new BlogDiscussionApiController(
            new JsonBlogRepository($this->blogDir),
            new JsonBlogDiscussionRepository($this->discussionDir)
        );

        $response = $controller->submit(
            new Request(
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => '/core/blog/submit_discussion.php',
                    'REMOTE_ADDR' => '127.0.0.1',
                ],
                [],
                [
                    'article_slug' => 'bonjour',
                    'article_lang' => 'fr',
                    'csrf_token' => $token,
                    'form_nonce' => $nonce,
                    'website' => '',
                    'author' => 'Pauline',
                    'email' => 'pauline@example.com',
                    'content' => 'Bonjour à tous',
                ],
                [],
                ['Host' => 'example.test']
            )
        );

        $this->assertSame(303, $response->status);
        $this->assertStringContainsString('/blog/article/bonjour#discussion-form', (string) ($response->headers['Location'] ?? ''));

        $repository = new JsonBlogDiscussionRepository($this->discussionDir);
        $this->assertSame([], $repository->all());
    }

    public function testSubmitFallsBackToAttachedParentUrlWhenArticleHasPublishedPage(): void
    {
        file_put_contents(
            $this->blogDir . '/bonjour-parent.fr.json',
            json_encode(
                [
                    'title' => 'Bonjour parent',
                    'slug' => 'bonjour-parent',
                    'lang' => 'fr',
                    'status' => 'published',
                    'date' => '2026-03-18 10:00:00',
                    'page_slug' => 'association',
                    'content' => '<p>Contenu.</p>',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        $scope = 'blog_discussion_' . hash('sha256', 'fr:bonjour-parent');
        $token = csrf_token($scope);
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['_blog_discussion_form_nonces'][$nonce] = [
            'scope' => $scope,
            'issued_at' => time() - 3,
        ];

        $repository = new JsonBlogRepository($this->blogDir);
        $controller = new BlogDiscussionApiController(
            $repository,
            new JsonBlogDiscussionRepository($this->discussionDir),
            null,
            null,
            new BlogPublicUrlResolver($repository, new PageRepository($this->pagesFile), 'fr')
        );

        $response = $controller->submit(
            new Request(
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => '/core/blog/submit_discussion.php',
                    'REMOTE_ADDR' => '127.0.0.1',
                ],
                [],
                [
                    'article_slug' => 'bonjour-parent',
                    'article_lang' => 'fr',
                    'csrf_token' => $token,
                    'form_nonce' => $nonce,
                    'website' => '',
                    'author' => 'Pauline',
                    'email' => 'pauline@example.com',
                    'content' => 'Bonjour a tous',
                ],
                [],
                ['Host' => 'example.test']
            )
        );

        $this->assertSame(303, $response->status);
        $this->assertStringContainsString(
            '/fr/association?open_article=bonjour-parent#discussion-form-bonjour-parent',
            (string) ($response->headers['Location'] ?? '')
        );
    }
}
