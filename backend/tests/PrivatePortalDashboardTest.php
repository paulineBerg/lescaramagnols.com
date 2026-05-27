<?php

declare(strict_types=1);

use Caramagnols\Http\Request;
use Caramagnols\PrivatePortal\Documents\PrivateDocumentRepository;
use Caramagnols\PrivatePortal\Http\PrivatePortalController;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivateSession;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class PrivatePortalDashboardTest extends TestCase
{
    use EditorialSqlTestTrait;

    private array $previousPrivateConfig = [];

    protected function setUp(): void
    {
        ensure_session_started();
        $_SESSION = [];

        global $appConfig;
        $this->previousPrivateConfig = is_array($appConfig['private'] ?? null) ? $appConfig['private'] : [];
        $appConfig['private']['enabled'] = true;
        $appConfig['private']['session_name'] = '_private_dashboard_test';
        $appConfig['private']['base_path'] = 'private';
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

    public function testDashboardShowsOnlyAssignedModules(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $passwordHash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);
        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $this->assertTrue($moduleRepository->setUserModules($userId, ['documents'], 'admin@example.com'));

        $session = new PrivateSession('_private_dashboard_test');
        $auth = new PrivateAuth($session, null, $userRepository);
        $controller = new PrivatePortalController($auth, null, null, $userRepository, $moduleRepository);

        $login = $controller->handle('login', $this->request('POST', '/private/login', [
            'identifier' => 'family@example.com',
            'password' => 'StrongPassword1!',
            'csrf_token' => csrf_token('private'),
        ]));
        $this->assertSame(302, $login->status);

        $dashboard = $controller->handle('dashboard', $this->request('GET', '/private/dashboard'));
        $this->assertSame(200, $dashboard->status);
        $this->assertStringContainsString('Documents', $dashboard->body);
        $this->assertStringNotContainsString('Discussions', $dashboard->body);
    }

    public function testDashboardShowsDocumentManagementForDocumentsModule(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $documentRepository = new PrivateDocumentRepository($database);
        $passwordHash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);

        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $this->assertTrue($moduleRepository->setUserModules($userId, ['documents'], 'admin@example.com'));

        $documentId = substr((string) bin2hex(random_bytes(16)), 0, 32);
        $document = $documentRepository->create(
            $userId,
            $documentId,
            'uploads/ab/cd/' . $documentId . '.txt',
            'compte-rendu.doc',
            'txt',
            'text/plain',
            42,
            $userId
        );
        $this->assertIsArray($document);

        $session = new PrivateSession('_private_dashboard_test');
        $auth = new PrivateAuth($session, null, $userRepository);
        $controller = new PrivatePortalController($auth, null, null, $userRepository, $moduleRepository, $documentRepository);

        $login = $controller->handle('login', $this->request('POST', '/private/login', [
            'identifier' => 'family@example.com',
            'password' => 'StrongPassword1!',
            'csrf_token' => csrf_token('private'),
        ]));
        $this->assertSame(302, $login->status);

        $dashboard = $controller->handle('dashboard', $this->request('GET', '/private/dashboard'));
        $this->assertSame(200, $dashboard->status);
        $this->assertStringContainsString('name="document_file"', $dashboard->body);
        $this->assertStringContainsString('compte-rendu.doc', $dashboard->body);
        $this->assertStringContainsString('/private/files/' . $documentId, $dashboard->body);
    }

    public function testDashboardHidesDocumentUploadWithoutDocumentsModule(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $documentRepository = new PrivateDocumentRepository($database);
        $passwordHash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);

        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $this->assertTrue($moduleRepository->setUserModules($userId, ['dashboard'], 'admin@example.com'));
        $documentId = substr((string) bin2hex(random_bytes(16)), 0, 32);
        $this->assertIsArray($documentRepository->create(
            $userId,
            $documentId,
            'uploads/ab/cd/' . $documentId . '.txt',
            'document-cache.txt',
            'txt',
            'text/plain',
            18,
            $userId
        ));

        $session = new PrivateSession('_private_dashboard_test');
        $auth = new PrivateAuth($session, null, $userRepository);
        $controller = new PrivatePortalController($auth, null, null, $userRepository, $moduleRepository, $documentRepository);

        $login = $controller->handle('login', $this->request('POST', '/private/login', [
            'identifier' => 'family@example.com',
            'password' => 'StrongPassword1!',
            'csrf_token' => csrf_token('private'),
        ]));
        $this->assertSame(302, $login->status);

        $dashboard = $controller->handle('dashboard', $this->request('GET', '/private/dashboard'));
        $this->assertSame(200, $dashboard->status);
        $this->assertStringNotContainsString('name="document_file"', $dashboard->body);
        $this->assertStringNotContainsString('document-cache.txt', $dashboard->body);
        $this->assertStringNotContainsString('/private/files/' . $documentId, $dashboard->body);
        $this->assertStringContainsString('Le module documents n’est pas activé pour votre compte.', $dashboard->body);
    }

    public function testPasswordForgotUsesNeutralResponseForKnownAndUnknownAccount(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $passwordHash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);
        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);

        $session = new PrivateSession('_private_dashboard_test');
        $controller = new PrivatePortalController(
            new PrivateAuth($session, null, $userRepository),
            null,
            null,
            $userRepository,
            $moduleRepository
        );
        $csrfToken = csrf_token('private_password');

        $knownResponse = $controller->handle('password_forgot', $this->request('POST', '/private/password/forgot', [
            'identifier' => 'family@example.com',
            'csrf_token' => $csrfToken,
        ]));
        $unknownResponse = $controller->handle('password_forgot', $this->request('POST', '/private/password/forgot', [
            'identifier' => 'unknown@example.com',
            'csrf_token' => $csrfToken,
        ]));

        $this->assertSame(200, $knownResponse->status);
        $this->assertSame(200, $unknownResponse->status);
        $this->assertStringContainsString('Si le compte existe', $knownResponse->body);
        $this->assertSame($knownResponse->body, $unknownResponse->body);
    }

    /**
     * @param array<string, mixed> $post
     */
    private function request(string $method, string $uri, array $post = []): Request
    {
        return new Request(
            [
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $uri,
                'REMOTE_ADDR' => '127.0.0.1',
            ],
            [],
            $post,
            [],
            ['Host' => '127.0.0.1:8000']
        );
    }
}
