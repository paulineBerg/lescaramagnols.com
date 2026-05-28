<?php

declare(strict_types=1);

use Caramagnols\Http\Request;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyMemberRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalUnitRepository;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivateSession;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../core/bootstrap.php';

final class RealEstateRentalModuleTest extends TestCase
{
    use EditorialSqlTestTrait;

    private array $previousPrivateConfig = [];
    private string $sessionName = '';

    protected function setUp(): void
    {
        ensure_session_started();
        $_SESSION = [];

        $this->sessionName = '_private_rental_' . bin2hex(random_bytes(4));

        global $appConfig;
        $this->previousPrivateConfig = is_array($appConfig['private'] ?? null) ? $appConfig['private'] : [];
        $appConfig['private']['enabled'] = true;
        $appConfig['private']['base_path'] = 'private';
        $appConfig['private']['session_name'] = $this->sessionName;
        $appConfig['private']['login_rate_limit_attempts'] = 5;
        $appConfig['private']['login_rate_limit_window'] = 900;
        $appConfig['private']['account_lockout_attempts'] = 3;
        $appConfig['private']['account_lockout_seconds'] = 86400;
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

    public function testUserSeesOnlyAuthorizedRentalProperties(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);

        $ownerId = $this->createPrivateUser($userRepository, 'owner@example.com');
        $guestId = $this->createPrivateUser($userRepository, 'guest@example.com');
        $this->assertTrue($moduleRepository->setUserModules($guestId, ['real_estate_rental'], 'admin@example.com'));

        $visible = $propertyRepository->create($ownerId, 'Maison A', '12 rue du Port', 'maison', 'indivision', 'active');
        $hidden = $propertyRepository->create($ownerId, 'Maison B', '14 rue du Port', 'maison', 'indivision', 'active');
        $this->assertNotNull($visible);
        $this->assertNotNull($hidden);
        $this->assertNotNull($memberRepository->create($visible->id, $guestId, 'occupant', $ownerId));

        $auth = $this->privateAuth($userRepository, 'guest@example.com');
        $controller = new \Caramagnols\PrivatePortal\Http\PrivatePortalController(
            $auth,
            null,
            null,
            $userRepository,
            $moduleRepository,
            null,
            null,
            $propertyRepository,
            $memberRepository,
            $unitRepository
        );

        $response = $controller->handle('rental_properties', $this->request('GET', '/private/rental-properties'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Maison A', $response->body);
        $this->assertStringNotContainsString('Maison B', $response->body);

        $dashboard = $controller->handle('rental_dashboard', $this->request('GET', '/private/locations'));
        $this->assertSame(200, $dashboard->status);
        $this->assertStringContainsString('Tableau de bord locatif', $dashboard->body);
        $this->assertStringContainsString('Biens et locations', $dashboard->body);
        $this->assertStringContainsString('Documents agence', $dashboard->body);
        $this->assertStringContainsString('Rapports', $dashboard->body);
        $this->assertStringContainsString('Maison A', $dashboard->body);
        $this->assertStringNotContainsString('Maison B', $dashboard->body);
    }

    public function testInvalidRentalUnitWritesAreRejectedServerSide(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $ownerId = $this->createPrivateUser($userRepository, 'owner@example.com');

        $property = $propertyRepository->create($ownerId, 'Maison Test', '20 rue du Port', 'maison', 'indivision', 'active');
        $this->assertNotNull($property);
        $this->assertNotNull($memberRepository->create($property->id, $ownerId, 'owner', $ownerId));

        $this->assertNull($unitRepository->create($property->id, 'A', 42.0, false, 'available', null, $ownerId));
        $this->assertNull($unitRepository->create($property->id, 'Lot principal', 0.1, false, 'available', null, $ownerId));
        $this->assertNull($unitRepository->create($property->id, 'Lot principal', 42.0, false, 'unknown', null, $ownerId));

        $valid = $unitRepository->create($property->id, 'Lot principal', 42.0, false, 'available', null, $ownerId);
        $this->assertNotNull($valid);
    }

    public function testArchivingPropertyKeepsHistoricalRowsInactive(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $ownerId = $this->createPrivateUser($userRepository, 'owner@example.com');

        $property = $propertyRepository->create($ownerId, 'Maison Archive', '30 rue du Port', 'maison', 'indivision', 'active');
        $this->assertNotNull($property);
        $this->assertNotNull($memberRepository->create($property->id, $ownerId, 'owner', $ownerId));
        $unit = $unitRepository->create($property->id, 'Lot archive', 38.0, false, 'available', null, $ownerId);
        $this->assertNotNull($unit);

        $this->assertTrue($propertyRepository->archive($property->id, $ownerId));
        $unitRepository->archiveByPropertyId($property->id, $ownerId);

        $archivedProperty = $propertyRepository->findById($property->id);
        $archivedUnit = $unitRepository->findById($unit->id);
        $this->assertNotNull($archivedProperty);
        $this->assertNotNull($archivedUnit);
        $this->assertSame('archived', $archivedProperty->status);
        $this->assertFalse($archivedProperty->isActive);
        $this->assertSame('archived', $archivedUnit->status);
        $this->assertFalse($archivedUnit->isActive);
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
