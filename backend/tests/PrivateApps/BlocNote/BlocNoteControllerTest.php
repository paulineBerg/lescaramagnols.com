<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PrivateApps\BlocNote;

use Caramagnols\Http\Request;
use Caramagnols\PrivatePortal\Http\PrivatePortalController;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivateSession;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

final class BlocNoteControllerTest extends TestCase
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

        $this->sessionName = '_private_blocnote_' . bin2hex(random_bytes(4));

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

    public function testBlocNoteRouteRendersForAssignedUser(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $userId = $this->createPrivateUser($userRepository, 'blocnote@example.com');
        $this->assertTrue($moduleRepository->setUserModules($userId, ['blocnote'], 'admin@example.com'));

        $controller = new PrivatePortalController(
            $this->privateAuth($userRepository, 'blocnote@example.com'),
            null,
            null,
            $userRepository,
            $moduleRepository
        );

        $response = $controller->handle('blocnote', $this->request('GET', '/private/blocnote'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Bloc-note', $response->body);
        $this->assertStringContainsString('name="action" value="save_note"', $response->body);
    }

    public function testBlocNoteRouteUsesDedicatedTopNavigationWithMultipleModules(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $userId = $this->createPrivateUser($userRepository, 'blocnote-nav@example.com');
        $this->assertTrue($moduleRepository->setUserModules(
            $userId,
            ['blocnote', 'documents', 'discussions'],
            'admin@example.com'
        ));

        $controller = new PrivatePortalController(
            $this->privateAuth($userRepository, 'blocnote-nav@example.com'),
            null,
            null,
            $userRepository,
            $moduleRepository
        );

        $response = $controller->handle('blocnote', $this->request('GET', '/private/blocnote?view=notes'));
        $topNav = $this->topNavigationHtml($response->body);

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Pages du bloc-note', $topNav);
        $this->assertStringContainsString('Mes notes', $topNav);
        $this->assertStringContainsString('Catégories', $topNav);
        $this->assertStringContainsString('Aide', $topNav);
        $this->assertStringNotContainsString('Documents', $topNav);
        $this->assertStringNotContainsString('Discussions', $topNav);
        $this->assertStringContainsString('>Documents</span>', $response->body);
        $this->assertStringContainsString('>Discussions</span>', $response->body);
    }

    public function testBlocNoteRouteRedirectsWithoutModuleAssignment(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $this->createPrivateUser($userRepository, 'no-blocnote@example.com');

        $controller = new PrivatePortalController(
            $this->privateAuth($userRepository, 'no-blocnote@example.com'),
            null,
            null,
            $userRepository,
            $moduleRepository
        );

        $response = $controller->handle('blocnote', $this->request('GET', '/private/blocnote'));

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

    /**
     * @param array<string, mixed> $body
     */
    private function request(string $method, string $uri, array $body = []): Request
    {
        return new Request(
            [
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $uri,
                'REMOTE_ADDR' => '127.0.0.1',
            ],
            [],
            $body,
            [],
            ['Host' => '127.0.0.1:8000']
        );
    }

    private function topNavigationHtml(string $html): string
    {
        $start = strpos($html, '<nav class="private-top-nav"');
        $this->assertIsInt($start);
        $end = strpos($html, '</nav>', $start);
        $this->assertIsInt($end);

        return substr($html, $start, $end - $start + 6);
    }
}
