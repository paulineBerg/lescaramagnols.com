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

final class PrivatePortalPhaseCoverageTest extends TestCase
{
    private string $logDir = '';
    private string $rateLimitDir = '';
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

        $this->logDir = sys_get_temp_dir() . '/caramagnols-private-phase-coverage-' . bin2hex(random_bytes(6));
        $this->rateLimitDir = sys_get_temp_dir() . '/caramagnols-private-phase-coverage-rate-limits-' . bin2hex(random_bytes(6));
        mkdir($this->logDir, 0777, true);
        mkdir($this->rateLimitDir, 0777, true);
        $appConfig['security']['rate_limit_dir'] = $this->rateLimitDir;

        $appConfig['private']['enabled'] = true;
        $appConfig['private']['base_path'] = 'private';
        $appConfig['private']['session_name'] = '_private_phase_coverage';
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

    public function testPhase5RoutesAreExposedBehindPrivateGuard(): void
    {
        $frontController = $this->frontController();
        $phase5Routes = [
            '/private/rental-properties',
            '/private/rental-units',
            '/private/rental-property-members',
        ];

        foreach ($phase5Routes as $route) {
            $response = $frontController->handle($this->request('GET', $route));
            $this->assertSame(403, $response->status, 'Phase 5 routes must be private-guarded when unauthenticated.');
            $this->assertSame('noindex, nofollow, noarchive', $response->headers['X-Robots-Tag'] ?? null);
        }
    }

    public function testPhase6RoutesAreExposedBehindPrivateGuard(): void
    {
        $frontController = $this->frontController();
        $phase6Routes = [
            '/private/locations',
            '/private/locations/documents',
            '/private/locations/agence/imports',
            '/private/locations/agence/documents-a-classer',
            '/private/rents',
            '/private/leases',
            '/private/payments',
            '/private/charges',
        ];

        foreach ($phase6Routes as $route) {
            $response = $frontController->handle($this->request('GET', $route));
            $this->assertSame(403, $response->status, 'Phase 6 routes must be private-guarded when unauthenticated.');
            $this->assertSame('noindex, nofollow, noarchive', $response->headers['X-Robots-Tag'] ?? null);
        }
    }

    public function testPhase7TaxBridgeContractsAreImplemented(): void
    {
        $expectedClasses = [
            'Caramagnols\\PrivatePortal\\RealEstateRental\\TaxBridge\\RentalTaxDataProviderInterface',
            'Caramagnols\\PrivateApps\\RealEstateRental\\TaxBridge\\RentalTaxDataProviderInterface',
            'Caramagnols\\PrivatePortal\\RealEstateRental\\TaxBridge\\RentalTaxDataProvider',
            'Caramagnols\\PrivateApps\\RealEstateRental\\TaxBridge\\RentalTaxDataProvider',
            'Caramagnols\\PrivatePortal\\TaxDeclarationHelper\\Source\\TaxDataSourceInterface',
            'Caramagnols\\PrivatePortal\\TaxDeclarationHelper\\Source\\RentalTaxDataSource',
            'Caramagnols\\PrivatePortal\\TaxDeclarationHelper\\ValueObject\\AnnualRentalIncome',
            'Caramagnols\\PrivatePortal\\TaxDeclarationHelper\\ValueObject\\AnnualDeductibleExpenses',
            'Caramagnols\\PrivatePortal\\TaxDeclarationHelper\\ValueObject\\MissingTaxDocument',
        ];

        foreach ($expectedClasses as $expectedClass) {
            $exists = class_exists($expectedClass) || interface_exists($expectedClass);
            $this->assertTrue($exists, sprintf('Contract %s must exist after phase 7.', $expectedClass));
        }
    }

    public function testPhase8TaxDeclarationRoutesAreExposedBehindPrivateGuard(): void
    {
        $frontController = $this->frontController();
        $phase8Routes = [
            '/private/impots',
            '/private/impots/2026',
            '/private/impots/2026/revenus-manuels',
            '/private/impots/2026/controle',
            '/private/impots/2026/documents',
            '/private/impots/2026/export',
        ];

        foreach ($phase8Routes as $route) {
            $response = $frontController->handle($this->request('GET', $route));
            $this->assertSame(403, $response->status, 'Phase 8 routes must be private-guarded when unauthenticated.');
            $this->assertSame('noindex, nofollow, noarchive', $response->headers['X-Robots-Tag'] ?? null);
        }
    }

    public function testPhase10DiscussionRoutesAreExposedBehindPrivateGuard(): void
    {
        $frontController = $this->frontController();
        $routes = [
            ['GET', '/private/discussions'],
            ['GET', '/private/discussions/new'],
            ['GET', '/private/discussions/1'],
            ['GET', '/private/discussions/api/conversations'],
            ['POST', '/private/discussions/api/conversations'],
            ['GET', '/private/discussions/api/conversations/1/messages'],
            ['POST', '/private/discussions/api/conversations/1/messages'],
            ['POST', '/private/discussions/api/conversations/1/members'],
            ['POST', '/private/discussions/api/conversations/1/leave'],
            ['POST', '/private/discussions/api/conversations/1/read'],
            ['GET', '/private/discussions/files/demo'],
            ['GET', '/private/discussions/files/demo/preview'],
        ];

        foreach ($routes as [$method, $route]) {
            $response = $frontController->handle($this->request($method, $route));
            $this->assertSame(403, $response->status, 'Phase 10 discussion routes must be private-guarded when unauthenticated.');
            $this->assertSame('noindex, nofollow, noarchive', $response->headers['X-Robots-Tag'] ?? null);
        }
    }

    public function testPhase9PrivateHeadersAreAppliedOnProtectedEntryPoints(): void
    {
        $frontController = $this->frontController();
        $response = $frontController->handle($this->request('GET', '/private/login'));

        $this->assertSame(200, $response->status);
        $this->assertSame('noindex, nofollow, noarchive', $response->headers['X-Robots-Tag'] ?? null);

        foreach (['/private/privacy/export', '/private/ops/backup'] as $route) {
            $privateResponse = $frontController->handle($this->request('GET', $route));
            $this->assertSame(403, $privateResponse->status);
            $this->assertSame('noindex, nofollow, noarchive', $privateResponse->headers['X-Robots-Tag'] ?? null);
        }
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     */
    private function request(
        string $method,
        string $uri,
        array $query = [],
        array $post = []
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
            ['Host' => '127.0.0.1:8000']
        );
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
}
