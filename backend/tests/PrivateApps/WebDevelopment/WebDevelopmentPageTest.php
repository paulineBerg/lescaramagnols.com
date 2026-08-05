<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PrivateApps\WebDevelopment;

use Caramagnols\Http\Request;
use Caramagnols\PrivateApps\WebDevelopment\Repository\WebDevelopmentProjectRepositoryInterface;
use Caramagnols\PrivatePortal\Http\PrivatePortalController;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivateSession;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

final class WebDevelopmentPageTest extends TestCase
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
        $this->sessionName = '_private_web_development_' . bin2hex(random_bytes(4));

        global $appConfig;
        $this->previousPrivateConfig = is_array($appConfig['private'] ?? null) ? $appConfig['private'] : [];
        $appConfig['private']['enabled'] = true;
        $appConfig['private']['base_path'] = 'private';
        $appConfig['private']['session_name'] = $this->sessionName;
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

    public function testAssignedMemberCanListAndOpenSharedProject(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $userId = $this->createPrivateUser($userRepository, 'web-preview@example.com');
        self::assertTrue($moduleRepository->setUserModules($userId, ['web_development'], 'admin@example.com'));

        $controller = new PrivatePortalController(
            auth: $this->privateAuth($userRepository, 'web-preview@example.com'),
            privateUserRepository: $userRepository,
            modulePermissionRepository: $moduleRepository,
            webDevelopmentProjectRepository: new WebDevelopmentPageFakeProjectRepository()
        );

        $response = $controller->handle('web_development', $this->request('/private/web-development'));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Lor de la Roche', $response->body);
        self::assertStringContainsString('/private/web-development/preview/lordelaroche', $response->body);
        self::assertStringContainsString('target="_blank"', $response->body);
        self::assertStringContainsString('>🌐</span>', $response->body);
        self::assertStringContainsString('>Projets web</span>', $response->body);
        self::assertStringNotContainsString('>WEB</span>', $response->body);
        self::assertStringContainsString('name="csrf_token"', $response->body);
        self::assertSame('noindex, nofollow, noarchive', $response->headers['X-Robots-Tag'] ?? null);
    }

    public function testMemberWithoutModuleIsRedirected(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $this->createPrivateUser($userRepository, 'no-web-preview@example.com');

        $controller = new PrivatePortalController(
            auth: $this->privateAuth($userRepository, 'no-web-preview@example.com'),
            privateUserRepository: $userRepository,
            modulePermissionRepository: $moduleRepository,
            webDevelopmentProjectRepository: new WebDevelopmentPageFakeProjectRepository()
        );

        $response = $controller->handle('web_development', $this->request('/private/web-development'));

        self::assertSame(302, $response->status);
        self::assertSame('/private/login', $response->headers['Location'] ?? null);
    }

    private function createPrivateUser(PrivateUserRepository $repository, string $email): int
    {
        $hash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        self::assertIsString($hash);
        $userId = $repository->create($email, $hash, 'active');
        self::assertIsInt($userId);

        return $userId;
    }

    private function privateAuth(PrivateUserRepository $repository, string $email): PrivateAuth
    {
        $auth = new PrivateAuth(new PrivateSession($this->sessionName), null, $repository);
        self::assertTrue($auth->login($email, 'StrongPassword1!', '127.0.0.1'));

        return $auth;
    }

    private function request(string $uri): Request
    {
        return new Request(
            [
                'REQUEST_METHOD' => 'GET',
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

final class WebDevelopmentPageFakeProjectRepository implements WebDevelopmentProjectRepositoryInterface
{
    public function findPreviewProjectsForUser(int $privateUserId): array
    {
        return $privateUserId > 0
            ? [[
                'id' => 1,
                'projectKey' => 'lordelaroche',
                'displayName' => 'Lor de la Roche',
                'description' => 'Site en cours de validation.',
            ]]
            : [];
    }

    public function findPreviewProjectById(int $projectId): ?array
    {
        return null;
    }

    public function findPreviewProjectByKey(string $projectKey): ?array
    {
        return null;
    }

    public function findPreviewProjectByKeyForUser(int $privateUserId, string $projectKey): ?array
    {
        return null;
    }
}
