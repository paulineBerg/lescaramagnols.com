<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PrivateApps\Documents;

use Caramagnols\Http\Request;
use Caramagnols\PrivatePortal\Http\PrivatePortalController;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivateSession;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

final class DocumentsControllerTest extends TestCase
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

        $this->sessionName = '_private_documents_' . bin2hex(random_bytes(4));

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

    public function testDocumentsRouteRendersForAssignedUser(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $userId = $this->createPrivateUser($userRepository, 'documents@example.com');
        $this->assertTrue($moduleRepository->setUserModules($userId, ['documents'], 'admin@example.com'));

        $controller = new PrivatePortalController(
            $this->privateAuth($userRepository, 'documents@example.com'),
            null,
            null,
            $userRepository,
            $moduleRepository
        );

        $response = $controller->handle('documents', $this->request('GET', '/private/documents'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Documents', $response->body);
        $this->assertStringNotContainsString('class="private-top-nav"', $response->body);
        $this->assertStringContainsString('class="private-module-nav"', $response->body);
        $this->assertStringContainsString('href="#private-documents"', $response->body);
        $this->assertStringContainsString('href="/private/documents/bibliotheque"', $response->body);
    }

    public function testDocumentHubShowsDedicatedNavigationWithoutTopNavigation(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $userId = $this->createPrivateUser($userRepository, 'documents-hub@example.com');
        $this->assertTrue($moduleRepository->setUserModules($userId, ['documents'], 'admin@example.com'));

        $controller = new PrivatePortalController(
            $this->privateAuth($userRepository, 'documents-hub@example.com'),
            null,
            null,
            $userRepository,
            $moduleRepository
        );

        $response = $controller->handle('documents_hub', $this->request('GET', '/private/documents/bibliotheque'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Bibliothèque de documents', $response->body);
        $this->assertStringNotContainsString('class="private-top-nav"', $response->body);
        $this->assertStringContainsString('class="private-module-nav"', $response->body);
        $this->assertStringContainsString('href="/private/documents/bibliotheque"', $response->body);
    }

    public function testDocumentsRouteRedirectsWithoutModuleAssignment(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $userId = $this->createPrivateUser($userRepository, 'no-documents@example.com');
        $this->assertTrue($moduleRepository->setUserModules($userId, ['dashboard'], 'admin@example.com'));

        $controller = new PrivatePortalController(
            $this->privateAuth($userRepository, 'no-documents@example.com'),
            null,
            null,
            $userRepository,
            $moduleRepository
        );

        $response = $controller->handle('documents', $this->request('GET', '/private/documents'));

        $this->assertSame(403, $response->status);
    }

    public function testFilesUploadRouteRejectsWithoutModuleAssignment(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $userId = $this->createPrivateUser($userRepository, 'no-upload@example.com');
        $this->assertTrue($moduleRepository->setUserModules($userId, ['dashboard'], 'admin@example.com'));

        $controller = new PrivatePortalController(
            $this->privateAuth($userRepository, 'no-upload@example.com'),
            null,
            null,
            $userRepository,
            $moduleRepository
        );

        $response = $controller->handle('files_upload', $this->request('POST', '/private/files/upload'));

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
}
