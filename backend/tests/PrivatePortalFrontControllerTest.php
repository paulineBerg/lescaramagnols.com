<?php

declare(strict_types=1);

use Caramagnols\Admin\AdminController;
use Caramagnols\Admin\AdminRouteResolver;
use Caramagnols\Blog\BlogApiController;
use Caramagnols\Blog\BlogSaveService;
use Caramagnols\Http\FrontController;
use Caramagnols\Http\Request;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\Logging\LoggerFactory;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class PrivatePortalFrontControllerTest extends TestCase
{
    private string $logDir;
    private string $rateLimitDir;
    private ?string $previousRateLimitDir = null;
    private array $previousPrivateConfig = [];

    protected function setUp(): void
    {
        ensure_session_started();
        $_SESSION = [];

        global $appConfig;
        $this->previousPrivateConfig = is_array($appConfig['private'] ?? null) ? $appConfig['private'] : [];
        $this->previousRateLimitDir = is_string($appConfig['security']['rate_limit_dir'] ?? null)
            ? $appConfig['security']['rate_limit_dir']
            : null;

        $this->logDir = sys_get_temp_dir() . '/caramagnols-private-front-controller-' . bin2hex(random_bytes(6));
        mkdir($this->logDir, 0777, true);
        $this->rateLimitDir = sys_get_temp_dir() . '/caramagnols-private-rate-limits-' . bin2hex(random_bytes(6));
        mkdir($this->rateLimitDir, 0777, true);
        $appConfig['security']['rate_limit_dir'] = $this->rateLimitDir;

        $appConfig['private']['enabled'] = true;
        $appConfig['private']['base_path'] = 'private';
        $appConfig['private']['session_name'] = '_private_front_controller_test';
        $appConfig['private']['local_user_email'] = 'family@example.com';
        $appConfig['private']['local_user_password_hash'] = password_hash('secret123', PASSWORD_ARGON2ID);
        $appConfig['private']['login_rate_limit_attempts'] = 5;
        $appConfig['private']['login_rate_limit_window'] = 900;
        $appConfig['private']['account_lockout_attempts'] = 3;
        $appConfig['private']['account_lockout_seconds'] = 86400;
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

        global $appConfig;
        if ($this->previousRateLimitDir !== null) {
            $appConfig['security']['rate_limit_dir'] = $this->previousRateLimitDir;
        } else {
            unset($appConfig['security']['rate_limit_dir']);
        }

        if ($this->previousPrivateConfig !== []) {
            $appConfig['private'] = $this->previousPrivateConfig;
        } else {
            unset($appConfig['private']);
        }
    }

    public function testPrivateLoginRouteIsServedByFrontController(): void
    {
        $response = $this->frontController()->handle($this->request('GET', '/private/login'));

        $this->assertSame(200, $response->status);
        $this->assertSame('noindex, nofollow, noarchive', $response->headers['X-Robots-Tag'] ?? null);
        $this->assertStringContainsString('Se connecter', $response->body);
        $this->assertStringContainsString('csrf_token', $response->body);
        $this->assertStringContainsString('<meta name="robots" content="noindex,nofollow,noarchive" />', $response->body);
        $this->assertStringNotContainsString('/tarteaucitron/', $response->body);
    }

    public function testPrivateLoginAliasAndDashboardAliasAreServed(): void
    {
        $loginAliasResponse = $this->frontController()->handle($this->request('GET', '/private/login/index.php'));
        $dashboardAliasResponse = $this->frontController()->handle($this->request('GET', '/private/dashboard.php'));

        $this->assertSame(200, $loginAliasResponse->status);
        $this->assertStringContainsString('Se connecter', $loginAliasResponse->body);
        $this->assertSame(301, $dashboardAliasResponse->status);
        $this->assertSame('/private/dashboard', $dashboardAliasResponse->headers['Location'] ?? null);
    }

    public function testPrivateRootRedirectsToLoginWhenNotAuthenticated(): void
    {
        $response = $this->frontController()->handle($this->request('GET', '/private'));

        $this->assertSame(302, $response->status);
        $this->assertSame('/private/login', $response->headers['Location'] ?? null);
    }

    public function testPrivateDashboardAccessIsProtectedWhenNotAuthenticated(): void
    {
        $response = $this->frontController()->handle($this->request('GET', '/private/dashboard'));

        $this->assertSame(302, $response->status);
        $this->assertSame('/private/login', $response->headers['Location'] ?? null);
        $securityLog = $this->privateSecurityLogContent();
        $this->assertNotSame('', $securityLog);
        $this->assertStringContainsString('private.access.denied', $securityLog);
    }

    public function testPrivateFileAccessIsProtectedWhenNotAuthenticated(): void
    {
        $response = $this->frontController()->handle($this->request('GET', '/private/files/document-1'));

        $this->assertSame(403, $response->status);
        $this->assertSame('Forbidden', $response->body);
    }

    public function testPrivateLoginIsEffectiveAndGrantsDashboardAccess(): void
    {
        $identifier = 'family@example.com';
        $password = 'secret123';
        $csrfToken = csrf_token('private');

        $response = $this->frontController()->handle(
            $this->request(
                'POST',
                '/private/login',
                [],
                [
                    'identifier' => $identifier,
                    'password' => $password,
                    'csrf_token' => $csrfToken,
                ]
            )
        );

        $this->assertSame(302, $response->status);
        $this->assertSame('/private/dashboard', $response->headers['Location'] ?? null);

        $dashboard = $this->frontController()->handle($this->request('GET', '/private/dashboard'));
        $this->assertSame(200, $dashboard->status);
        $this->assertStringContainsString('Tableau de bord', $dashboard->body);
    }

    public function testPrivateRobotsTxtDoesNotExposePrivatePathWhenPrivateEnabled(): void
    {
        $response = $this->frontController()->handle($this->request('GET', '/robots.txt'));

        $this->assertSame(200, $response->status);
        $this->assertSame('text/plain; charset=UTF-8', $response->headers['Content-Type'] ?? null);
        $this->assertStringNotContainsString('Disallow: /private', $response->body);
        $this->assertStringContainsString('Sitemap: http://127.0.0.1:8000/sitemap.xml', $response->body);
    }

    public function testPrivateFileAccessRequiresDocumentModulePermission(): void
    {
        $csrfToken = csrf_token('private');
        $this->frontController()->handle(
            $this->request(
                'POST',
                '/private/login',
                [],
                [
                    'identifier' => 'family@example.com',
                    'password' => 'secret123',
                    'csrf_token' => $csrfToken,
                ]
            )
        );

        $response = $this->frontController()->handle($this->request('GET', '/private/files/document-1'));

        $this->assertSame(403, $response->status);
        $this->assertSame('Forbidden', $response->body);
    }

    public function testPrivateDashboardStillDeniedAfterInvalidCsrfOnLogin(): void
    {
        $response = $this->frontController()->handle(
            $this->request(
                'POST',
                '/private/login',
                [],
                [
                    'identifier' => 'family@example.com',
                    'password' => 'secret123',
                    'csrf_token' => 'invalid-csrf',
                ]
            )
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('error', $response->body);
    }

    public function testPrivateLogoutRouteRedirectsToLogin(): void
    {
        $csrfToken = csrf_token('private');
        $this->frontController()->handle(
            $this->request(
                'POST',
                '/private/login',
                [],
                [
                    'identifier' => 'family@example.com',
                    'password' => 'secret123',
                    'csrf_token' => $csrfToken,
                ]
            )
        );

        $logoutResponse = $this->frontController()->handle(
            $this->request(
                'POST',
                '/private/logout',
                [],
                ['csrf_token' => csrf_token('private_logout')]
            )
        );

        $this->assertSame(302, $logoutResponse->status);
        $this->assertSame('/private/login', $logoutResponse->headers['Location'] ?? null);
        $dashboard = $this->frontController()->handle($this->request('GET', '/private/dashboard'));
        $this->assertSame(302, $dashboard->status);
        $this->assertSame('/private/login', $dashboard->headers['Location'] ?? null);
    }

    private function privateSecurityLogContent(): string
    {
        $paths = [
            $this->logDir . '/security.log',
            ROOT_PATH . '/data/logs/security.log',
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $contents = file_get_contents($path);
                if (is_string($contents)) {
                    return $contents;
                }
            }
        }

        return '';
    }

    private function frontController(): FrontController
    {
        $routeResolver = new AdminRouteResolver('admin');
        $logger = new AppEventLogger(new LoggerFactory($this->logDir, 'test'));

        return new FrontController(
            $routeResolver,
            new AdminController($routeResolver),
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
        array $headers = []
    ): Request {
        return new Request(
            [
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $uri,
                'REMOTE_ADDR' => '127.0.0.1',
            ],
            $query,
            $post,
            [],
            array_merge(['Host' => '127.0.0.1:8000'], $headers)
        );
    }
}
