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

require_once __DIR__ . '/../../core/bootstrap.php';

final class PrivatePortalSecurityPhase9Test extends TestCase
{
    private string $logDir = '';
    private string $rateLimitDir = '';
    private array $previousPrivateConfig = [];
    private ?string $previousRateLimitDir = null;

    protected function setUp(): void
    {
        ensure_session_started();
        $_SESSION = [];
        $this->logDir = sys_get_temp_dir() . '/caramagnols-phase9-security-' . bin2hex(random_bytes(6));
        $this->rateLimitDir = sys_get_temp_dir() . '/caramagnols-phase9-security-rate-' . bin2hex(random_bytes(6));
        mkdir($this->logDir, 0777, true);
        mkdir($this->rateLimitDir, 0777, true);

        global $appConfig;
        $this->previousPrivateConfig = is_array($appConfig['private'] ?? null) ? $appConfig['private'] : [];
        $this->previousRateLimitDir = is_string($appConfig['security']['rate_limit_dir'] ?? null)
            ? $appConfig['security']['rate_limit_dir']
            : null;
        $appConfig['security']['rate_limit_dir'] = $this->rateLimitDir;
        $appConfig['private']['enabled'] = true;
        $appConfig['private']['base_path'] = 'private';
        $appConfig['private']['session_name'] = '_private_phase9_security';
        $appConfig['private']['login_rate_limit_attempts'] = 5;
        $appConfig['private']['login_rate_limit_window'] = 900;
        $appConfig['private']['account_lockout_attempts'] = 3;
        $appConfig['private']['account_lockout_seconds'] = 86400;
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $this->removeDirectory($this->logDir);
        $this->removeDirectory($this->rateLimitDir);

        global $appConfig;
        if ($this->previousPrivateConfig !== []) {
            $appConfig['private'] = $this->previousPrivateConfig;
        } else {
            unset($appConfig['private']);
        }
        if ($this->previousRateLimitDir !== null) {
            $appConfig['security']['rate_limit_dir'] = $this->previousRateLimitDir;
        } else {
            unset($appConfig['security']['rate_limit_dir']);
        }
    }

    public function testPrivatePhase9RoutesKeepNoIndexHeadersWhenUnauthenticated(): void
    {
        foreach (['/private/privacy/export', '/private/ops/backup'] as $route) {
            $response = $this->frontController()->handle($this->request('GET', $route));
            $this->assertSame(403, $response->status);
            $this->assertSame('noindex, nofollow, noarchive', $response->headers['X-Robots-Tag'] ?? null);
            $this->assertSame('private, no-store, no-cache, must-revalidate', $response->headers['Cache-Control'] ?? null);
            $this->assertSame('DENY', $response->headers['X-Frame-Options'] ?? null);
            $this->assertStringContainsString("frame-ancestors 'none'", $response->headers['Content-Security-Policy'] ?? '');
        }
    }

    public function testEventLoggerRedactsSecrets(): void
    {
        $logger = new AppEventLogger(new LoggerFactory($this->logDir, 'test'));
        $logger->security('private.phase9.secret_test', [
            'password' => 'SuperSecret1!',
            'csrf_token' => 'csrf-secret',
            'nested' => ['apiToken' => 'token-secret'],
            'safe' => 'visible',
        ]);

        $content = (string) file_get_contents($this->logDir . '/security.log');
        $this->assertStringContainsString('[redacted]', $content);
        $this->assertStringContainsString('visible', $content);
        $this->assertStringNotContainsString('SuperSecret1!', $content);
        $this->assertStringNotContainsString('csrf-secret', $content);
        $this->assertStringNotContainsString('token-secret', $content);
    }

    private function frontController(): FrontController
    {
        $routeResolver = new AdminRouteResolver('admin');
        $logger = new AppEventLogger(new LoggerFactory($this->logDir, 'test'));

        return new FrontController(
            $routeResolver,
            new AdminController($routeResolver),
            new BlogApiController(new BlogSaveService(blog_repository(), $logger), $logger),
            $logger
        );
    }

    private function request(string $method, string $uri): Request
    {
        return new Request(
            ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'REMOTE_ADDR' => '127.0.0.1'],
            [],
            [],
            [],
            ['Host' => '127.0.0.1:8000']
        );
    }

    private function removeDirectory(string $directory): void
    {
        if ($directory === '' || !is_dir($directory)) {
            return;
        }
        foreach (glob($directory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($directory);
    }
}
