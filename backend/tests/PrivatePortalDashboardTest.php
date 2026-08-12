<?php

declare(strict_types=1);

use Caramagnols\Http\Request;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\Logging\LoggerFactory;
use Caramagnols\PrivateApps\Documents\PrivateDocumentRepository;
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
            'csrf_token' => $this->privateCsrfToken($session, 'private'),
        ]));
        $this->assertSame(302, $login->status);

        $dashboard = $controller->handle('dashboard', $this->request('GET', '/private/dashboard'));
        $this->assertSame(200, $dashboard->status);
        $this->assertStringContainsString('Documents', $dashboard->body);
        $this->assertStringNotContainsString('Discussions', $dashboard->body);
    }

    public function testDashboardLeftMenuLinksAssignedWebappsToDedicatedRoutes(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $passwordHash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);
        $userId = $userRepository->create('left-menu@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $this->assertTrue($moduleRepository->setUserModules(
            $userId,
            ['blocnote', 'documents', 'real_estate_rental', 'pbgestion'],
            'admin@example.com'
        ));

        $session = new PrivateSession('_private_dashboard_test');
        $auth = new PrivateAuth($session, null, $userRepository);
        $controller = new PrivatePortalController($auth, null, null, $userRepository, $moduleRepository);

        $login = $controller->handle('login', $this->request('POST', '/private/login', [
            'identifier' => 'left-menu@example.com',
            'password' => 'StrongPassword1!',
            'csrf_token' => $this->privateCsrfToken($session, 'private'),
        ]));
        $this->assertSame(302, $login->status);

        $dashboard = $controller->handle('dashboard', $this->request('GET', '/private/dashboard'));
        $this->assertSame(200, $dashboard->status);
        $this->assertStringContainsString('href="/private/blocnote"', $dashboard->body);
        $this->assertStringContainsString('href="/private/documents"', $dashboard->body);
        $this->assertStringContainsString('href="/private/locations"', $dashboard->body);
        $this->assertStringContainsString('href="/private/pbgestion"', $dashboard->body);
        $this->assertStringNotContainsString('class="private-top-nav"', $dashboard->body);
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
        $category = $documentRepository->createCategory($userId, 'Assurances habitation', '#2563eb');
        $this->assertIsArray($category);

        $documentId = substr((string) bin2hex(random_bytes(16)), 0, 32);
        $document = $documentRepository->create(
            $userId,
            $documentId,
            'uploads/ab/cd/' . $documentId . '.txt',
            'compte-rendu.doc',
            'txt',
            'text/plain',
            42,
            $userId,
            (int) $category['id']
        );
        $this->assertIsArray($document);

        $session = new PrivateSession('_private_dashboard_test');
        $auth = new PrivateAuth($session, null, $userRepository);
        $controller = new PrivatePortalController($auth, null, null, $userRepository, $moduleRepository, $documentRepository);

        $login = $controller->handle('login', $this->request('POST', '/private/login', [
            'identifier' => 'family@example.com',
            'password' => 'StrongPassword1!',
            'csrf_token' => $this->privateCsrfToken($session, 'private'),
        ]));
        $this->assertSame(302, $login->status);

        $dashboard = $controller->handle('dashboard', $this->request('GET', '/private/dashboard'));
        $this->assertSame(200, $dashboard->status);
        $this->assertStringContainsString('/private/documents', $dashboard->body);
        $this->assertStringNotContainsString('name="document_file"', $dashboard->body);

        $documents = $controller->handle('documents', $this->request('GET', '/private/documents'));
        $this->assertSame(200, $documents->status);
        $this->assertStringContainsString('name="document_file"', $documents->body);
        $this->assertStringContainsString('name="category_name"', $documents->body);
        $this->assertStringContainsString('Assurances habitation', $documents->body);
        $this->assertStringContainsString('compte-rendu.doc', $documents->body);
        $this->assertStringContainsString('/private/files/' . $documentId, $documents->body);
    }

    public function testDashboardShowsDiscussionCardForDiscussionModule(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $passwordHash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);

        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $this->assertTrue($moduleRepository->setUserModules($userId, ['discussions'], 'admin@example.com'));

        $session = new PrivateSession('_private_dashboard_test');
        $auth = new PrivateAuth($session, null, $userRepository);
        $controller = new PrivatePortalController($auth, null, null, $userRepository, $moduleRepository);

        $login = $controller->handle('login', $this->request('POST', '/private/login', [
            'identifier' => 'family@example.com',
            'password' => 'StrongPassword1!',
            'csrf_token' => $this->privateCsrfToken($session, 'private'),
        ]));
        $this->assertSame(302, $login->status);

        $dashboard = $controller->handle('dashboard', $this->request('GET', '/private/dashboard'));
        $this->assertSame(200, $dashboard->status);
        $this->assertStringContainsString('Discussions famille', $dashboard->body);
        $this->assertStringContainsString('/private/discussions', $dashboard->body);
        $this->assertStringNotContainsString('name="document_file"', $dashboard->body);
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
            'csrf_token' => $this->privateCsrfToken($session, 'private'),
        ]));
        $this->assertSame(302, $login->status);

        $dashboard = $controller->handle('dashboard', $this->request('GET', '/private/dashboard'));
        $this->assertSame(200, $dashboard->status);
        $this->assertStringNotContainsString('name="document_file"', $dashboard->body);
        $this->assertStringNotContainsString('document-cache.txt', $dashboard->body);
        $this->assertStringNotContainsString('/private/files/' . $documentId, $dashboard->body);
        $this->assertStringNotContainsString('/private/documents', $dashboard->body);

        $documents = $controller->handle('documents', $this->request('GET', '/private/documents'));
        $this->assertSame(302, $documents->status);
        $this->assertSame('/private/login', $documents->headers['Location'] ?? null);
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
        $csrfToken = $this->privateCsrfToken($session, 'private_password');

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

    public function testPasswordForgotRenewsActivationInsteadOfResetForInvitedAccount(): void
    {
        global $appConfig;

        $appConfig['private']['mail'] = [];
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $passwordHash = password_hash('Temporary1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);
        $userId = $userRepository->create('pending@example.com', $passwordHash, 'invited');
        $this->assertIsInt($userId);

        $session = new PrivateSession('_private_dashboard_test');
        $controller = new PrivatePortalController(
            new PrivateAuth($session, null, $userRepository),
            null,
            null,
            $userRepository,
            $moduleRepository
        );
        $response = $controller->handle('password_forgot', $this->request('POST', '/private/password/forgot', [
            'identifier' => 'pending@example.com',
            'csrf_token' => $this->privateCsrfToken($session, 'private_password'),
        ]));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Si le compte existe', $response->body);
        $resetCount = $database->pdo()
            ->query(sprintf('SELECT COUNT(*) FROM `%s`', $database->table('private_password_resets')))
            ->fetchColumn();
        $invite = $database->pdo()
            ->query(sprintf('SELECT `status` FROM `%s` ORDER BY `id` DESC LIMIT 1', $database->table('private_user_invites')))
            ->fetchColumn();
        $this->assertSame(0, (int) $resetCount);
        $this->assertSame('pending', $invite);
    }

    public function testActivationOpensDashboardEvenAfterPreviousFailedAttempt(): void
    {
        global $appConfig;

        $appConfig['private']['account_lockout_attempts'] = 1;
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $passwordHash = password_hash('Temporary1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);
        $userId = $userRepository->create('pending@example.com', $passwordHash, 'invited');
        $this->assertIsInt($userId);
        $inviteToken = $userRepository->createInviteToken($userId, 'pending@example.com');
        $this->assertIsString($inviteToken);

        $session = new PrivateSession('_private_dashboard_test');
        $auth = new PrivateAuth($session, null, $userRepository);
        $controller = new PrivatePortalController($auth, null, null, $userRepository, $moduleRepository);
        $password = 'FreshPassword1!&';
        $this->assertFalse($auth->login('pending@example.com', 'wrong-password', '127.0.0.1'));
        $this->assertSame('invalid_credentials', $auth->failureReason());

        $response = $controller->handle(
            'activate',
            $this->request('POST', '/private/activate/' . $inviteToken, [
                'password' => $password,
                'password_confirm' => $password,
                'csrf_token' => $this->privateCsrfToken($session, 'private_activate'),
            ]),
            ['token' => $inviteToken]
        );

        $this->assertSame(302, $response->status);
        $this->assertSame('/private/dashboard', $response->headers['Location'] ?? null);
        $this->assertStringNotContainsString($inviteToken, $response->body);
        $this->assertTrue($auth->isAuthenticated());
        $this->assertSame('pending@example.com', $auth->currentIdentifier());

        $activated = $userRepository->findById($userId);
        $this->assertIsArray($activated);
        $this->assertSame('active', $activated['status'] ?? null);
        $this->assertTrue(password_verify($password, (string) ($activated['password_hash'] ?? '')));
    }

    public function testPasswordResetOpensDashboardEvenAfterPreviousFailedAttempt(): void
    {
        global $appConfig;

        $appConfig['private']['account_lockout_attempts'] = 1;
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $passwordHash = password_hash('Temporary1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);
        $userId = $userRepository->create('reset@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $resetToken = $userRepository->createPasswordResetToken($userId, '127.0.0.1', 'phpunit');
        $this->assertIsString($resetToken);

        $session = new PrivateSession('_private_dashboard_test');
        $auth = new PrivateAuth($session, null, $userRepository);
        $controller = new PrivatePortalController($auth, null, null, $userRepository, $moduleRepository);
        $this->assertFalse($auth->login('reset@example.com', 'wrong-password', '127.0.0.1'));
        $this->assertSame('invalid_credentials', $auth->failureReason());

        $password = 'ResetPassword1!&';
        $response = $controller->handle(
            'password_reset',
            $this->request('POST', '/private/password/reset/' . $resetToken, [
                'password' => $password,
                'password_confirm' => $password,
                'csrf_token' => $this->privateCsrfToken($session, 'private_password'),
            ]),
            ['token' => $resetToken]
        );

        $this->assertSame(302, $response->status);
        $this->assertSame('/private/dashboard', $response->headers['Location'] ?? null);
        $this->assertStringNotContainsString($resetToken, $response->body);
        $this->assertTrue($auth->isAuthenticated());
        $this->assertSame('reset@example.com', $auth->currentIdentifier());

        $updated = $userRepository->findById($userId);
        $this->assertIsArray($updated);
        $this->assertSame('active', $updated['status'] ?? null);
        $this->assertTrue(password_verify($password, (string) ($updated['password_hash'] ?? '')));
    }

    public function testFailedPasswordResetLogsDetailedNonSensitiveDiagnostic(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $passwordHash = password_hash('Temporary1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);
        $userId = $userRepository->create('diagnostic@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $resetToken = $userRepository->createPasswordResetToken($userId, '127.0.0.1', 'phpunit');
        $this->assertIsString($resetToken);
        $this->assertTrue($userRepository->updateStatus($userId, 'invited'));

        $logDir = sys_get_temp_dir() . '/caramagnols-private-reset-' . bin2hex(random_bytes(8));
        $session = new PrivateSession('_private_dashboard_test');
        $controller = new PrivatePortalController(
            new PrivateAuth($session, null, $userRepository),
            null,
            new AppEventLogger(new LoggerFactory($logDir, 'test')),
            $userRepository,
            $moduleRepository
        );
        $response = $controller->handle(
            'password_reset',
            $this->request('POST', '/private/password/reset/' . $resetToken, [
                'password' => 'ReplacementPwd1!',
                'password_confirm' => 'ReplacementPwd1!',
                'csrf_token' => $this->privateCsrfToken($session, 'private_password'),
            ]),
            ['token' => $resetToken]
        );

        try {
            $this->assertSame(200, $response->status);
            $log = (string) file_get_contents($logDir . '/security.log');
            $this->assertStringContainsString('private.password_reset.completed_failed', $log);
            $this->assertStringContainsString('account_not_resettable', $log);
            $this->assertStringContainsString('"account_status":"invited"', $log);
            $this->assertStringContainsString(substr(hash('sha256', $resetToken), 0, 16), $log);
            $this->assertStringNotContainsString($resetToken, $log);
        } finally {
            foreach (glob($logDir . '/*') ?: [] as $path) {
                @unlink($path);
            }
            @rmdir($logDir);
        }
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

    private function privateCsrfToken(PrivateSession $session, string $scope): string
    {
        $session->start();

        return csrf_token($scope);
    }
}
