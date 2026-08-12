<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PrivateApps\PbGestion;

use Caramagnols\Http\Request;
use Caramagnols\PbGestion\Persistence\PbGestionRepository;
use Caramagnols\PrivatePortal\Http\PrivatePortalController;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivateSession;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

final class PbGestionControllerTest extends TestCase
{
    use EditorialSqlTestTrait;

    private array $previousPrivateConfig = [];
    private string $sessionName = '';

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 3) . '/core/bootstrap.php';
    }

    protected function setUp(): void
    {
        ensure_session_started();
        $_SESSION = [];

        $this->sessionName = '_private_pbgestion_' . bin2hex(random_bytes(4));

        global $appConfig;
        $this->previousPrivateConfig = is_array($appConfig['private'] ?? null) ? $appConfig['private'] : [];
        $appConfig['private']['enabled'] = true;
        $appConfig['private']['base_path'] = 'private';
        $appConfig['private']['session_name'] = $this->sessionName;
        $appConfig['private']['login_rate_limit_attempts'] = 5;
        $appConfig['private']['login_rate_limit_window'] = 900;
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $this->cleanupEditorialSqlDatabase();

        global $appConfig;
        if ($this->previousPrivateConfig !== []) {
            $appConfig['private'] = $this->previousPrivateConfig;
        } else {
            unset($appConfig['private']);
        }
    }

    public function testPbGestionRouteRendersDedicatedTopNavigationWithoutGlobalTopNavigation(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $userId = $this->createPrivateUser($userRepository, 'pbgestion@example.com');
        $this->assertTrue($moduleRepository->setUserModules($userId, ['pbgestion', 'documents'], 'admin@example.com'));

        $auth = $this->privateAuth($userRepository, 'pbgestion@example.com');
        $_SESSION['private_user']['last_reauth_at'] = 0;
        $this->assertFalse($auth->isReauthFresh());

        $controller = new PrivatePortalController(
            $auth,
            null,
            null,
            $userRepository,
            $moduleRepository,
            pbGestionRepository: new PbGestionRepository($database)
        );

        $response = $controller->handle('pbgestion_dashboard', $this->request('GET', '/private/pbgestion'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('PB Gestion', $response->body);
        $this->assertStringNotContainsString('class="private-top-nav"', $response->body);
        $this->assertStringContainsString('class="private-module-nav"', $response->body);
        $this->assertStringContainsString('Vue d’ensemble</a>', $response->body);
        $this->assertStringContainsString('Agents et installation</a>', $response->body);
        $this->assertStringContainsString('Sauvegardes</a>', $response->body);

        $writeResponse = $controller->handle('pbgestion_dashboard', $this->request('POST', '/private/pbgestion'));
        $this->assertSame(302, $writeResponse->status);
        $this->assertSame('/private/login', $writeResponse->headers['Location'] ?? null);
    }

    public function testPbGestionRouteRedirectsWithoutModuleAssignment(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $userId = $this->createPrivateUser($userRepository, 'no-pbgestion@example.com');
        $this->assertTrue($moduleRepository->setUserModules($userId, ['documents'], 'admin@example.com'));

        $controller = new PrivatePortalController(
            $this->privateAuth($userRepository, 'no-pbgestion@example.com'),
            null,
            null,
            $userRepository,
            $moduleRepository,
            pbGestionRepository: new PbGestionRepository($database)
        );

        $response = $controller->handle('pbgestion_dashboard', $this->request('GET', '/private/pbgestion'));

        $this->assertSame(302, $response->status);
        $this->assertSame('/private/login', $response->headers['Location'] ?? null);
    }

    private function createPrivateUser(PrivateUserRepository $repository, string $email): int
    {
        $hash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($hash);
        $userId = $repository->create($email, $hash, 'active');
        $this->assertIsInt($userId);

        return $userId;
    }

    private function privateAuth(PrivateUserRepository $userRepository, string $email): PrivateAuth
    {
        $session = new PrivateSession($this->sessionName);
        $auth = new PrivateAuth($session, null, $userRepository);
        $this->assertTrue($auth->login($email, 'StrongPassword1!', '127.0.0.1'));
        $this->assertTrue($auth->isAuthenticated());

        return $auth;
    }

    private function request(string $method, string $uri): Request
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
            ['Host' => '127.0.0.1:8000']
        );
    }
}
