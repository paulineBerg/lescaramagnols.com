<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\LocalAgentPlatform;

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

final class LocalAgentPortalControllerTest extends TestCase
{
    use EditorialSqlTestTrait;

    private array $previousPrivateConfig = [];
    private string $sessionName = '';

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/core/bootstrap.php';
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
        $pbGestionRepository = new PbGestionRepository($database);
        $userId = $this->createPrivateUser($userRepository, 'pbgestion@example.com');
        $this->assertTrue($moduleRepository->setUserModules($userId, ['network_security', 'photo_geo_renamer', 'documents'], 'admin@example.com'));
        $this->createClaimedAgent($pbGestionRepository, $userId);

        $auth = $this->privateAuth($userRepository, 'pbgestion@example.com');
        $_SESSION['private_user']['last_reauth_at'] = 0;
        $this->assertFalse($auth->isReauthFresh());

        $controller = new PrivatePortalController(
            $auth,
            null,
            null,
            $userRepository,
            $moduleRepository,
            pbGestionRepository: $pbGestionRepository
        );

        $response = $controller->handle('network_security_dashboard', $this->request('GET', '/private/securite-reseau'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Sécurité réseau', $response->body);
        $this->assertStringNotContainsString('class="private-top-nav"', $response->body);
        $this->assertStringContainsString('class="private-module-nav"', $response->body);
        $this->assertStringContainsString('Vue d’ensemble</a>', $response->body);
        $this->assertStringContainsString('Agents et installation</a>', $response->body);
        $this->assertStringContainsString('Sauvegardes</a>', $response->body);
        $this->assertStringNotContainsString('Photos locales</a>', $response->body);
        $this->assertStringNotContainsString('Renommage</a>', $response->body);

        $photoResponse = $controller->handle('photo_geo_renamer_dashboard', $this->request('GET', '/private/photo-rename'));
        $this->assertSame(200, $photoResponse->status);
        $this->assertStringContainsString('Photo rename', $photoResponse->body);
        $this->assertStringContainsString('Renommage</a>', $photoResponse->body);
        $this->assertStringNotContainsString('Vue d’ensemble</a>', $photoResponse->body);
        $this->assertStringContainsString('photo.rename.preview', $photoResponse->body);
        $this->assertStringContainsString('Les originaux restent sur l’ordinateur de l’agent', $photoResponse->body);

        $writeResponse = $controller->handle('network_security_dashboard', $this->request('POST', '/private/securite-reseau'));
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

        $response = $controller->handle('network_security_dashboard', $this->request('GET', '/private/securite-reseau'));

        $this->assertSame(302, $response->status);
        $this->assertSame('/private/login', $response->headers['Location'] ?? null);
    }

    public function testAgentInstallerRequiresExplicitConsentAndDownloadsLocalScript(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $pbGestionRepository = new PbGestionRepository($database);
        $userId = $this->createPrivateUser($userRepository, 'installer@example.com');
        $this->assertTrue($moduleRepository->setUserModules($userId, ['network_security'], 'admin@example.com'));

        $controller = new PrivatePortalController(
            $this->privateAuth($userRepository, 'installer@example.com'),
            null,
            null,
            $userRepository,
            $moduleRepository,
            pbGestionRepository: $pbGestionRepository
        );

        $agentsResponse = $controller->handle('network_security_agents', $this->request('GET', '/private/securite-reseau/agents-installation'));
        $this->assertSame(200, $agentsResponse->status);
        $this->assertStringContainsString('Installer l’agent local PbGestion', $agentsResponse->body);
        $this->assertStringContainsString('Je comprends que l’agent local s’installe', $agentsResponse->body);
        $this->assertStringContainsString('mode restreint sans accès fichiers', $agentsResponse->body);

        $csrfToken = csrf_token('private_pbgestion');
        $refusedResponse = $controller->handle('network_security_agents', $this->request('POST', '/private/securite-reseau/agents-installation', [
            'csrf_token' => $csrfToken,
            'action' => 'download_agent_installer',
            'installer_confirmation' => 'INSTALLER',
        ]));
        $this->assertSame(200, $refusedResponse->status);
        $this->assertArrayNotHasKey('Content-Disposition', $refusedResponse->headers);
        $this->assertStringContainsString('confirmez explicitement l’installation locale', $refusedResponse->body);

        $downloadResponse = $controller->handle('network_security_agents', $this->request('POST', '/private/securite-reseau/agents-installation', [
            'csrf_token' => $csrfToken,
            'action' => 'download_agent_installer',
            'location_label' => 'PC photos',
            'installer_consent' => '1',
            'installer_confirmation' => 'INSTALLER',
        ]));

        $this->assertSame(200, $downloadResponse->status);
        $this->assertSame('application/x-powershell; charset=utf-8', $downloadResponse->headers['Content-Type'] ?? null);
        $this->assertSame('attachment; filename="pbgestion-agent-install.ps1"', $downloadResponse->headers['Content-Disposition'] ?? null);
        $this->assertStringContainsString('no-store', $downloadResponse->headers['Cache-Control'] ?? '');
        $this->assertStringContainsString('INSTALLATION LOCALE PB GESTION', $downloadResponse->body);
        $this->assertStringContainsString('Tapez OUI pour confirmer l installation locale', $downloadResponse->body);
        $this->assertStringContainsString('%LOCALAPPDATA%\\pbgestion\\agent', $downloadResponse->body);
        $this->assertStringContainsString("Join-Path \$env:LOCALAPPDATA 'pbgestion'", $downloadResponse->body);
        $this->assertStringContainsString('pbgestion_agent.py', $downloadResponse->body);
        $this->assertStringContainsString('pynacl', $downloadResponse->body);
        $this->assertStringContainsString('/api/pbgestion/v1/enrollment/claim', $downloadResponse->body);
    }

    public function testPhotoRestrictedModeRunsWithoutClaimedAgent(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $pbGestionRepository = new PbGestionRepository($database);
        $userId = $this->createPrivateUser($userRepository, 'restricted@example.com');
        $this->assertTrue($moduleRepository->setUserModules($userId, ['photo_geo_renamer'], 'admin@example.com'));

        $controller = new PrivatePortalController(
            $this->privateAuth($userRepository, 'restricted@example.com'),
            null,
            null,
            $userRepository,
            $moduleRepository,
            pbGestionRepository: $pbGestionRepository
        );

        $photosResponse = $controller->handle('photo_geo_renamer_dashboard', $this->request('GET', '/private/photo-rename'));
        $this->assertSame(200, $photosResponse->status);
        $this->assertStringContainsString('Mode restreint sans agent', $photosResponse->body);
        $this->assertStringContainsString('Aucun agent appairé: seules les fonctions restreintes', $photosResponse->body);

        $previewResponse = $controller->handle('photo_geo_renamer_dashboard', $this->request('POST', '/private/photo-rename', [
            'csrf_token' => csrf_token('private_pbgestion'),
            'action' => 'photo_restricted_preview',
            'restricted_items' => "IMG_0001.jpg;Cogolin;2026-08-13 12:00:00\nIMG_0002.jpg;Cogolin;2026-08-13 12:05:00",
            'text_before' => 'Vacances',
            'separator' => '_',
            'counter_digits' => '3',
            'sort_order' => 'manual',
        ]));

        $this->assertSame(200, $previewResponse->status);
        $this->assertStringContainsString('Aperçu restreint généré', $previewResponse->body);
        $this->assertStringContainsString('Vacances_Cogolin_2026-08-13_001.jpg', $previewResponse->body);
        $this->assertStringContainsString('Vacances_Cogolin_2026-08-13_002.jpg', $previewResponse->body);
        $this->assertStringContainsString('Aucun fichier local n’a été lu ou renommé', $previewResponse->body);
    }

    public function testInvalidCsrfKeepsPhotoApplicationContext(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $userId = $this->createPrivateUser($userRepository, 'photo-csrf@example.com');
        $this->assertTrue($moduleRepository->setUserModules($userId, ['photo_geo_renamer'], 'admin@example.com'));

        $controller = new PrivatePortalController(
            $this->privateAuth($userRepository, 'photo-csrf@example.com'),
            null,
            null,
            $userRepository,
            $moduleRepository,
            pbGestionRepository: new PbGestionRepository($database)
        );

        $response = $controller->handle('photo_geo_renamer_dashboard', $this->request('POST', '/private/photo-rename', [
            'csrf_token' => 'invalid',
            'action' => 'photo_restricted_preview',
        ]));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Photo rename', $response->body);
        $this->assertStringContainsString('Requête invalide.', $response->body);
        $this->assertStringNotContainsString('Navigation Sécurité réseau', $response->body);
    }

    public function testActionsCannotCrossApplicationBoundary(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $userId = $this->createPrivateUser($userRepository, 'module-boundary@example.com');
        $this->assertTrue($moduleRepository->setUserModules($userId, ['network_security', 'photo_geo_renamer'], 'admin@example.com'));

        $controller = new PrivatePortalController(
            $this->privateAuth($userRepository, 'module-boundary@example.com'),
            null,
            null,
            $userRepository,
            $moduleRepository,
            pbGestionRepository: new PbGestionRepository($database)
        );
        $csrfToken = csrf_token('private_pbgestion');

        $networkResponse = $controller->handle('network_security_dashboard', $this->request('POST', '/private/securite-reseau', [
            'csrf_token' => $csrfToken,
            'action' => 'photo_restricted_preview',
            'restricted_items' => 'IMG_0001.jpg;Cogolin;2026-08-13 12:00:00',
        ]));
        $this->assertSame(200, $networkResponse->status);
        $this->assertStringContainsString('Sécurité réseau', $networkResponse->body);
        $this->assertStringContainsString('Requête invalide.', $networkResponse->body);
        $this->assertStringNotContainsString('Aperçu restreint généré', $networkResponse->body);

        $photoResponse = $controller->handle('photo_geo_renamer_dashboard', $this->request('POST', '/private/photo-rename', [
            'csrf_token' => $csrfToken,
            'action' => 'purge_details',
        ]));
        $this->assertSame(200, $photoResponse->status);
        $this->assertStringContainsString('Photo rename', $photoResponse->body);
        $this->assertStringContainsString('Requête invalide.', $photoResponse->body);
        $this->assertStringNotContainsString('Détails temporaires purgés', $photoResponse->body);
    }

    private function createPrivateUser(PrivateUserRepository $repository, string $email): int
    {
        $hash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($hash);
        $userId = $repository->create($email, $hash, 'active');
        $this->assertIsInt($userId);

        return $userId;
    }

    private function createClaimedAgent(PbGestionRepository $repository, int $ownerId): void
    {
        $token = $repository->createEnrollmentToken($ownerId, 'PC photos');
        $publicKeyLength = defined('SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES') ? SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES : 32;
        $claim = $repository->claimEnrollment([
            'code' => $token['code'],
            'public_key_base64' => base64_encode(random_bytes($publicKeyLength)),
            'display_name' => 'Agent photos',
            'os_family' => 'windows',
            'os_version' => '11',
            'agent_version' => '0.2.0',
            'capabilities' => ['photos'],
        ]);

        $this->assertTrue($claim['ok']);
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
