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
            'csrf_token' => $this->privateCsrfToken($session, 'private'),
        ]));
        $this->assertSame(302, $login->status);

        $dashboard = $controller->handle('dashboard', $this->request('GET', '/private/dashboard'));
        $this->assertSame(200, $dashboard->status);
        $this->assertStringContainsString('Documents', $dashboard->body);
        $this->assertStringNotContainsString('Discussions', $dashboard->body);
    }

    public function testSettingsNavigationOpensMemberSettingsPage(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $passwordHash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);

        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $this->assertTrue($moduleRepository->setUserModules($userId, ['dashboard'], 'admin@example.com'));

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
        preg_match_all(
            '/<a[^>]+href="([^"]+)"[^>]*>\s*<span[^>]*>[^<]*<\/span>\s*<span>Paramètres<\/span>/u',
            $dashboard->body,
            $settingsLinks
        );
        $this->assertSame(['/private/parametres', '/private/parametres'], $settingsLinks[1] ?? []);
        $this->assertStringNotContainsString('href="/private/dashboard"><span class="private-nav-icon" aria-hidden="true">⚙</span>', $dashboard->body);

        $settings = $controller->handle('member_settings', $this->request('GET', '/private/parametres'));
        $this->assertSame(200, $settings->status);
        $this->assertStringContainsString('Paramètres membre', $settings->body);
        $this->assertStringNotContainsString('Tableau de bord privé', $settings->body);
        preg_match_all(
            '/<a[^>]+href="([^"]+)"[^>]*>\s*<span[^>]*>[^<]*<\/span>\s*<span>Paramètres<\/span>/u',
            $settings->body,
            $settingsPageLinks
        );
        $this->assertSame(['/private/parametres', '/private/parametres'], $settingsPageLinks[1] ?? []);
        $this->assertSame(2, substr_count($settings->body, 'href="/private/parametres" aria-current="page"'));
        $this->assertStringNotContainsString('class="active" href="/private/dashboard"', $settings->body);
        $this->assertStringNotContainsString('href="/private/dashboard"><span class="private-nav-icon" aria-hidden="true">⚙</span>', $settings->body);
    }

    public function testMemberSmtpFormKeepsSubmittedTestRecipientOnSaveError(): void
    {
        global $appConfig;

        $appConfig['private']['mail']['user_settings_encryption_key'] = 'short-key-disabled-for-test';

        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $passwordHash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);

        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $this->assertTrue($moduleRepository->setUserModules($userId, ['dashboard'], 'admin@example.com'));

        $session = new PrivateSession('_private_dashboard_test');
        $auth = new PrivateAuth($session, null, $userRepository);
        $controller = new PrivatePortalController($auth, null, null, $userRepository, $moduleRepository);

        $login = $controller->handle('login', $this->request('POST', '/private/login', [
            'identifier' => 'family@example.com',
            'password' => 'StrongPassword1!',
            'csrf_token' => $this->privateCsrfToken($session, 'private'),
        ]));
        $this->assertSame(302, $login->status);

        $response = $controller->handle('member_settings', $this->request('POST', '/private/parametres?tab=smtp', [
            'csrf_token' => $this->privateCsrfToken($session, 'private_member_settings'),
            'action' => 'smtp_settings',
            'enabled' => '1',
            'smtp_host' => 'ssl0.ovh.net',
            'smtp_port' => '587',
            'smtp_user' => 'pauline@lescaramagnols.com',
            'smtp_password' => 'smtp-secret',
            'smtp_encryption' => 'tls',
            'from_address' => 'pauline@lescaramagnols.com',
            'from_name' => 'Les Caramagnols',
            'reply_to' => 'pauline@lescaramagnols.com',
            'test_recipient' => 'alt-test@example.com',
            'send_test' => '1',
        ]));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('La clé de chiffrement SMTP privée est manquante.', $response->body);
        $this->assertStringContainsString('name="test_recipient" maxlength="254" value="alt-test@example.com"', $response->body);
        $this->assertStringNotContainsString('name="test_recipient" maxlength="254" value="family@example.com"', $response->body);
    }

    public function testMemberSmtpTestKeepsSubmittedRecipientWhenUserSmtpIsIncomplete(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $passwordHash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);

        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $this->assertTrue($moduleRepository->setUserModules($userId, ['dashboard'], 'admin@example.com'));

        $session = new PrivateSession('_private_dashboard_test');
        $auth = new PrivateAuth($session, null, $userRepository);
        $controller = new PrivatePortalController($auth, null, null, $userRepository, $moduleRepository);

        $login = $controller->handle('login', $this->request('POST', '/private/login', [
            'identifier' => 'family@example.com',
            'password' => 'StrongPassword1!',
            'csrf_token' => $this->privateCsrfToken($session, 'private'),
        ]));
        $this->assertSame(302, $login->status);

        $response = $controller->handle('member_settings', $this->request('POST', '/private/parametres?tab=smtp', [
            'csrf_token' => $this->privateCsrfToken($session, 'private_member_settings'),
            'action' => 'smtp_settings',
            'enabled' => '1',
            'smtp_host' => 'ssl0.ovh.net',
            'smtp_port' => '587',
            'smtp_user' => 'pauline@lescaramagnols.com',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'from_address' => 'pauline@lescaramagnols.com',
            'from_name' => 'Les Caramagnols',
            'reply_to' => 'pauline@lescaramagnols.com',
            'test_recipient' => 'alt-test@example.com',
            'send_test' => '1',
        ]));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Veuillez remplir vos paramètres SMTP avant d’envoyer un email.', $response->body);
        $this->assertStringContainsString('name="test_recipient" maxlength="254" value="alt-test@example.com"', $response->body);
        $this->assertStringNotContainsString('Le test SMTP a échoué. Vérifiez vos paramètres.', $response->body);
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
