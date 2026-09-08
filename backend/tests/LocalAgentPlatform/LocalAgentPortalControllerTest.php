<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\LocalAgentPlatform;

use Caramagnols\Http\Request;
use Caramagnols\LocalAgentPlatform\Http\LocalAgentPortalController;
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
        $this->assertStringContainsString('Mode navigateur Android, iOS et desktop', $photoResponse->body);
        $this->assertStringContainsString('data-photo-browser-renamer', $photoResponse->body);
        $this->assertStringContainsString('Télécharger les copies', $photoResponse->body);

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
        $this->assertStringContainsString('aucun texte n’est à recopier', $agentsResponse->body);
        $this->assertStringNotContainsString('name="installer_confirmation"', $agentsResponse->body);
        $this->assertStringContainsString('Supprimer l’agent local', $agentsResponse->body);
        $this->assertStringContainsString('Créer un code 30 minutes', $agentsResponse->body);

        $csrfToken = csrf_token('private_pbgestion');
        $refusedResponse = $controller->handle('network_security_agents', $this->request('POST', '/private/securite-reseau/agents-installation', [
            'csrf_token' => $csrfToken,
            'action' => 'download_agent_installer',
        ]));
        $this->assertSame(200, $refusedResponse->status);
        $this->assertArrayNotHasKey('Content-Disposition', $refusedResponse->headers);
        $this->assertStringContainsString('confirmez explicitement l’installation locale', $refusedResponse->body);

        $downloadResponse = $controller->handle('network_security_agents', $this->request('POST', '/private/securite-reseau/agents-installation', [
            'csrf_token' => $csrfToken,
            'action' => 'download_agent_installer',
            'location_label' => 'PC photos',
            'installer_consent' => '1',
            'installer_platform' => 'windows',
        ]));

        $this->assertSame(200, $downloadResponse->status);
        $this->assertSame('application/x-powershell; charset=utf-8', $downloadResponse->headers['Content-Type'] ?? null);
        $this->assertSame('attachment; filename="pbgestion-agent-install-windows.ps1"', $downloadResponse->headers['Content-Disposition'] ?? null);
        $this->assertStringContainsString('no-store', $downloadResponse->headers['Cache-Control'] ?? '');
        $this->assertStringContainsString('INSTALLATION LOCALE PB GESTION', $downloadResponse->body);
        $this->assertStringNotContainsString('Tapez OUI pour confirmer l installation locale', $downloadResponse->body);
        $this->assertStringContainsString('%LOCALAPPDATA%\\pbgestion\\agent', $downloadResponse->body);
        $this->assertStringContainsString("Join-Path \$env:LOCALAPPDATA 'pbgestion'", $downloadResponse->body);
        $this->assertStringContainsString('pbgestion_agent.py', $downloadResponse->body);
        $this->assertStringContainsString('pynacl', $downloadResponse->body);
        $this->assertStringContainsString('/api/pbgestion/v1/enrollment/claim', $downloadResponse->body);

        $linuxResponse = $controller->handle('network_security_agents', $this->request('POST', '/private/securite-reseau/agents-installation', [
            'csrf_token' => $csrfToken,
            'action' => 'download_agent_installer',
            'location_label' => 'PC Linux',
            'installer_consent' => '1',
            'installer_platform' => 'linux',
        ]));
        $this->assertSame(200, $linuxResponse->status);
        $this->assertSame('text/x-shellscript; charset=utf-8', $linuxResponse->headers['Content-Type'] ?? null);
        $this->assertSame('attachment; filename="pbgestion-agent-install-linux.sh"', $linuxResponse->headers['Content-Disposition'] ?? null);
        $this->assertStringContainsString('#!/usr/bin/env bash', $linuxResponse->body);
        $this->assertStringContainsString('systemctl --user enable --now pbgestion-agent.timer', $linuxResponse->body);

        $uninstallResponse = $controller->handle('network_security_agents', $this->request('POST', '/private/securite-reseau/agents-installation', [
            'csrf_token' => $csrfToken,
            'action' => 'download_agent_uninstaller',
            'installer_platform' => 'windows',
        ]));
        $this->assertSame(200, $uninstallResponse->status);
        $this->assertSame('attachment; filename="pbgestion-agent-uninstall-windows.ps1"', $uninstallResponse->headers['Content-Disposition'] ?? null);
        $this->assertStringContainsString('SUPPRESSION LOCALE PB GESTION', $uninstallResponse->body);
    }

    public function testPhotoBrowserModeRunsWithoutClaimedAgent(): void
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
        $this->assertStringContainsString('Mode navigateur Android, iOS et desktop', $photosResponse->body);
        $this->assertStringContainsString('Aucun agent local détecté.', $photosResponse->body);
        $this->assertStringContainsString('data-photo-browser-renamer', $photosResponse->body);
        $this->assertStringContainsString('data-photo-browser-geocode-url', $photosResponse->body);
        $this->assertStringContainsString('Commune de secours', $photosResponse->body);
        $this->assertStringContainsString('coordonnées GPS EXIF', $photosResponse->body);
        $this->assertStringContainsString('<option value="taken" selected>date de prise de vue</option>', $photosResponse->body);
        $this->assertStringNotContainsString('Mode manuel sans agent', $photosResponse->body);
    }

    public function testPhotoReverseGeocodeRejectsInvalidCoordinatesBeforeProviderCall(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $userId = $this->createPrivateUser($userRepository, 'photo-geocode@example.com');
        $this->assertTrue($moduleRepository->setUserModules($userId, ['photo_geo_renamer'], 'admin@example.com'));

        $controller = new PrivatePortalController(
            $this->privateAuth($userRepository, 'photo-geocode@example.com'),
            null,
            null,
            $userRepository,
            $moduleRepository,
            pbGestionRepository: new PbGestionRepository($database)
        );

        $response = $controller->handle('photo_geo_renamer_dashboard', $this->request('POST', '/private/photo-rename', [
            'csrf_token' => csrf_token('private_pbgestion'),
            'action' => 'photo_reverse_geocode',
            'latitude' => '999',
            'longitude' => '6.632808',
        ]));

        $this->assertSame(422, $response->status);
        $this->assertSame('application/json; charset=utf-8', $response->headers['Content-Type'] ?? null);
        $this->assertStringContainsString('invalid_coordinates', $response->body);
    }

    public function testPhotoReverseGeocodeFallbackResolvesGolfeDeSaintTropezCommunes(): void
    {
        $controller = (new \ReflectionClass(LocalAgentPortalController::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(LocalAgentPortalController::class, 'fallbackCommuneFromCoordinates');
        $method->setAccessible(true);

        $this->assertSame('Saint-Tropez', $method->invoke($controller, 43.272611, 6.632808));
        $this->assertSame('Cogolin', $method->invoke($controller, 43.251639, 6.534650));
        $this->assertNull($method->invoke($controller, 48.856614, 2.352222));
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
        $this->assertStringNotContainsString('Aperçu manuel généré', $networkResponse->body);

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
