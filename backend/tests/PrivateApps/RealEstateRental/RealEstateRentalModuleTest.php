<?php

declare(strict_types=1);

use Caramagnols\Http\Request;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
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

        $regularizations = $controller->handle('rental_regularizations', $this->request('GET', '/private/locations/regularisations'));
        $this->assertSame(200, $regularizations->status);
        $this->assertStringContainsString('Regularisations', $regularizations->body);
        $this->assertStringContainsString('Maison A', $regularizations->body);
        $this->assertStringNotContainsString('Maison B', $regularizations->body);
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
        $this->assertNull($unitRepository->create($property->id, 'Lot garage', 18.0, false, 'available', null, $ownerId, 'hangar'));

        $valid = $unitRepository->create(
            $property->id,
            'Lot principal',
            42.0,
            false,
            'available',
            null,
            $ownerId,
            'house',
            '20 rue du Port',
            'A',
            'RDC',
            '1'
        );
        $this->assertNotNull($valid);
        $this->assertSame('house', $valid->unitType);
        $this->assertSame('20 rue du Port', $valid->address);
        $this->assertSame('A', $valid->building);
        $this->assertSame('RDC', $valid->floor);
        $this->assertSame('1', $valid->door);
    }

    public function testLeaseFormDisplaysLeaseTypeChoices(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);

        $ownerId = $this->createPrivateUser($userRepository, 'lease-form@example.com');
        $this->assertTrue($moduleRepository->setUserModules($ownerId, ['real_estate_rental'], 'admin@example.com'));
        $property = $propertyRepository->create($ownerId, 'Maison bail', '8 rue du Bail', 'maison', 'indivision', 'active');
        $this->assertNotNull($property);
        $this->assertNotNull($memberRepository->create($property->id, $ownerId, 'owner', $ownerId));
        $unit = $unitRepository->create($property->id, 'Lot bail', 33.0, true, 'available', null, $ownerId);
        $this->assertNotNull($unit);
        $tenant = $lifecycleRepository->createTenant($property->id, $unit->id, 'Locataire bail', null, null, 'validated', $ownerId, null);
        $this->assertIsArray($tenant);

        $controller = new \Caramagnols\PrivatePortal\Http\PrivatePortalController(
            auth: $this->privateAuth($userRepository, 'lease-form@example.com'),
            privateUserRepository: $userRepository,
            modulePermissionRepository: $moduleRepository,
            rentalPropertyRepository: $propertyRepository,
            rentalPropertyMemberRepository: $memberRepository,
            rentalUnitRepository: $unitRepository,
            rentalLifecycleRepository: $lifecycleRepository
        );

        $response = $controller->handle('rental_leases', $this->request('GET', '/private/leases'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('name="lease_type"', $response->body);
        $this->assertStringContainsString('Habitation vide', $response->body);
        $this->assertStringContainsString('BIC location meublee', $response->body);
        $this->assertStringContainsString('data-rental-lease-start-date', $response->body);
    }

    public function testRentScheduleActionGeneratesExpectedRentOnce(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);

        $ownerId = $this->createPrivateUser($userRepository, 'rent-schedule-ui@example.com');
        $this->assertTrue($moduleRepository->setUserModules($ownerId, ['real_estate_rental'], 'admin@example.com'));
        $property = $propertyRepository->create($ownerId, 'Maison echeancier UI', '14 rue du Loyer', 'maison', 'indivision', 'active');
        $this->assertNotNull($property);
        $this->assertNotNull($memberRepository->create($property->id, $ownerId, 'owner', $ownerId));
        $unit = $unitRepository->create($property->id, 'Lot echeancier', 39.0, false, 'available', null, $ownerId);
        $this->assertNotNull($unit);
        $tenant = $lifecycleRepository->createTenant($property->id, $unit->id, 'Locataire echeancier', null, null, 'validated', $ownerId, null);
        $this->assertIsArray($tenant);
        $lease = $lifecycleRepository->createLease(
            $property->id,
            $unit->id,
            (int) $tenant['id'],
            '2026-01-15',
            '2026-12-31',
            900.0,
            75.0,
            'validated',
            $ownerId,
            null
        );
        $this->assertIsArray($lease);

        $controller = new \Caramagnols\PrivatePortal\Http\PrivatePortalController(
            auth: $this->privateAuth($userRepository, 'rent-schedule-ui@example.com'),
            privateUserRepository: $userRepository,
            modulePermissionRepository: $moduleRepository,
            rentalPropertyRepository: $propertyRepository,
            rentalPropertyMemberRepository: $memberRepository,
            rentalUnitRepository: $unitRepository,
            rentalLifecycleRepository: $lifecycleRepository
        );

        $get = $controller->handle('rental_rents', $this->request('GET', '/private/rents'));
        $this->assertSame(200, $get->status);
        $this->assertStringContainsString('Générer les loyers dus du mois', $get->body);
        $this->assertStringContainsString('value="generate_rent_schedule"', $get->body);
        $this->assertStringContainsString('value="generate_month_schedule"', $get->body);

        $post = $controller->handle(
            'rental_rents',
            $this->request('POST', '/private/rents', [
                'csrf_token' => csrf_token('private_rental'),
                'action' => 'generate_rent_schedule',
                'rental_lease_id' => (string) $lease['id'],
                'period_month_picker' => '2026-01',
            ])
        );

        $this->assertSame(302, $post->status);
        $this->assertSame('/private/rents?notice=rent_schedule_generated', $post->headers['Location'] ?? null);

        $rents = $lifecycleRepository->listRents([$property->id], 2026);
        $this->assertCount(1, $rents);
        $this->assertSame('2026-01-15', $rents[0]['dueDate']);
        $this->assertSame(975.0, (float) $rents[0]['amountDue']);

        $again = $controller->handle(
            'rental_rents',
            $this->request('POST', '/private/rents', [
                'csrf_token' => csrf_token('private_rental'),
                'action' => 'generate_rent_schedule',
                'rental_lease_id' => (string) $lease['id'],
                'period_month_picker' => '2026-01',
            ])
        );

        $this->assertSame(302, $again->status);
        $this->assertSame('/private/rents?notice=rent_schedule_existing', $again->headers['Location'] ?? null);
        $this->assertCount(1, $lifecycleRepository->listRents([$property->id], 2026));
    }

    public function testPaymentActionsUpdateRentStatusAndControlOverpayment(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);

        $ownerId = $this->createPrivateUser($userRepository, 'rent-payment-status-ui@example.com');
        $this->assertTrue($moduleRepository->setUserModules($ownerId, ['real_estate_rental'], 'admin@example.com'));
        $property = $propertyRepository->create($ownerId, 'Maison paiement UI', '15 rue du Paiement', 'maison', 'indivision', 'active');
        $this->assertNotNull($property);
        $this->assertNotNull($memberRepository->create($property->id, $ownerId, 'owner', $ownerId));
        $unit = $unitRepository->create($property->id, 'Lot paiement', 37.0, false, 'available', null, $ownerId);
        $this->assertNotNull($unit);
        $tenant = $lifecycleRepository->createTenant($property->id, $unit->id, 'Locataire paiement', null, null, 'validated', $ownerId, null);
        $this->assertIsArray($tenant);
        $lease = $lifecycleRepository->createLease($property->id, $unit->id, (int) $tenant['id'], '2026-01-01', null, 950.0, 50.0, 'validated', $ownerId, null);
        $this->assertIsArray($lease);
        $rent = $lifecycleRepository->createRent((int) $lease['id'], $property->id, $unit->id, 2026, 12, '2026-12-01', 1000.0, 'pending', $ownerId, null);
        $this->assertIsArray($rent);

        $controller = new \Caramagnols\PrivatePortal\Http\PrivatePortalController(
            auth: $this->privateAuth($userRepository, 'rent-payment-status-ui@example.com'),
            privateUserRepository: $userRepository,
            modulePermissionRepository: $moduleRepository,
            rentalPropertyRepository: $propertyRepository,
            rentalPropertyMemberRepository: $memberRepository,
            rentalUnitRepository: $unitRepository,
            rentalLifecycleRepository: $lifecycleRepository
        );

        $get = $controller->handle('rental_payments', $this->request('GET', '/private/payments'));
        $this->assertSame(200, $get->status);
        $this->assertStringContainsString('name="payment_kind"', $get->body);
        $this->assertStringContainsString('name="payment_method"', $get->body);
        $this->assertStringContainsString('name="payment_reference"', $get->body);

        $overpay = $controller->handle(
            'rental_payments',
            $this->request('POST', '/private/payments', [
                'csrf_token' => csrf_token('private_rental'),
                'action' => 'create_payment',
                'rental_rent_id' => (string) $rent['id'],
                'payment_date' => '2026-12-03',
                'amount_paid' => '1200.00',
                'payment_kind' => 'tenant',
                'payment_method' => 'bank_transfer',
                'payment_reference' => 'VIR-SURPLUS',
                'status' => 'validated',
            ])
        );
        $this->assertSame(200, $overpay->status);
        $this->assertStringContainsString('Surpaiement détecté', $overpay->body);
        $this->assertCount(0, $lifecycleRepository->listPayments([$property->id], 2026));

        $created = $controller->handle(
            'rental_payments',
            $this->request('POST', '/private/payments', [
                'csrf_token' => csrf_token('private_rental'),
                'action' => 'create_payment',
                'rental_rent_id' => (string) $rent['id'],
                'payment_date' => '2026-12-03',
                'amount_paid' => '400.00',
                'payment_kind' => 'tenant',
                'payment_method' => 'bank_transfer',
                'payment_reference' => 'VIR-400',
                'status' => 'validated',
            ])
        );
        $this->assertSame(302, $created->status);
        $this->assertSame('/private/payments?notice=payment_created', $created->headers['Location'] ?? null);
        $rentsAfterPartial = $lifecycleRepository->listRents([$property->id], 2026);
        $this->assertSame('partial', $rentsAfterPartial[0]['status'] ?? null);
        $payments = $lifecycleRepository->listPayments([$property->id], 2026);
        $this->assertCount(1, $payments);
        $paymentId = (int) $payments[0]['id'];
        $this->assertSame('VIR-400', $payments[0]['paymentReference'] ?? null);

        $updated = $controller->handle(
            'rental_payments',
            $this->request('POST', '/private/payments', [
                'csrf_token' => csrf_token('private_rental'),
                'action' => 'update_payment',
                'payment_id' => (string) $paymentId,
                'payment_date' => '2026-12-03',
                'amount_paid' => '1000.00',
                'payment_kind' => 'tenant',
                'payment_method' => 'bank_transfer',
                'payment_reference' => 'VIR-1000',
                'status' => 'validated',
            ])
        );
        $this->assertSame(302, $updated->status);
        $this->assertSame('/private/payments?notice=payment_updated', $updated->headers['Location'] ?? null);
        $rentsAfterPaid = $lifecycleRepository->listRents([$property->id], 2026);
        $this->assertSame('paid', $rentsAfterPaid[0]['status'] ?? null);

        $cancelled = $controller->handle(
            'rental_payments',
            $this->request('POST', '/private/payments', [
                'csrf_token' => csrf_token('private_rental'),
                'action' => 'cancel_payment',
                'payment_id' => (string) $paymentId,
            ])
        );
        $this->assertSame(302, $cancelled->status);
        $this->assertSame('/private/payments?notice=payment_cancelled', $cancelled->headers['Location'] ?? null);
        $rentsAfterCancel = $lifecycleRepository->listRents([$property->id], 2026);
        $this->assertSame('pending', $rentsAfterCancel[0]['status'] ?? null);
    }

    public function testPaymentRequestButtonIsOnlyDisplayedForUnpaidRents(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);

        $ownerId = $this->createPrivateUser($userRepository, 'rent-payment-request-ui@example.com');
        $this->assertTrue($moduleRepository->setUserModules($ownerId, ['real_estate_rental'], 'admin@example.com'));
        $property = $propertyRepository->create($ownerId, 'Maison relance UI', '19 rue du Rappel', 'maison', 'indivision', 'active');
        $this->assertNotNull($property);
        $this->assertNotNull($memberRepository->create($property->id, $ownerId, 'owner', $ownerId));
        $unit = $unitRepository->create($property->id, 'Lot relance UI', 38.0, false, 'available', null, $ownerId);
        $this->assertNotNull($unit);
        $tenant = $lifecycleRepository->createTenant($property->id, $unit->id, 'Locataire relance UI', 'relance-ui@example.com', null, 'validated', $ownerId, null);
        $this->assertIsArray($tenant);
        $lease = $lifecycleRepository->createLease($property->id, $unit->id, (int) $tenant['id'], '2026-01-01', null, 950.0, 50.0, 'validated', $ownerId, null);
        $this->assertIsArray($lease);
        $pendingRent = $lifecycleRepository->createRent((int) $lease['id'], $property->id, $unit->id, 2026, 8, '2026-08-01', 1000.0, 'pending', $ownerId, null);
        $paidRent = $lifecycleRepository->createRent((int) $lease['id'], $property->id, $unit->id, 2026, 9, '2026-09-01', 1000.0, 'paid', $ownerId, null);
        $cancelledRent = $lifecycleRepository->createRent((int) $lease['id'], $property->id, $unit->id, 2026, 10, '2026-10-01', 1000.0, 'cancelled', $ownerId, null);
        $this->assertIsArray($pendingRent);
        $this->assertIsArray($paidRent);
        $this->assertIsArray($cancelledRent);
        $this->assertIsArray($lifecycleRepository->createPayment(
            (int) $lease['id'],
            $property->id,
            $unit->id,
            '2026-09-02',
            2026,
            9,
            0.0,
            1000.0,
            'validated',
            $ownerId,
            null,
            (int) $paidRent['id']
        ));

        $controller = new \Caramagnols\PrivatePortal\Http\PrivatePortalController(
            auth: $this->privateAuth($userRepository, 'rent-payment-request-ui@example.com'),
            privateUserRepository: $userRepository,
            modulePermissionRepository: $moduleRepository,
            rentalPropertyRepository: $propertyRepository,
            rentalPropertyMemberRepository: $memberRepository,
            rentalUnitRepository: $unitRepository,
            rentalLifecycleRepository: $lifecycleRepository
        );

        $response = $controller->handle('rental_rents', $this->request('GET', '/private/rents'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('data-private-dialog-open="rental-payment-request-dialog-' . (int) $pendingRent['id'] . '"', $response->body);
        $this->assertStringContainsString('name="action" value="send_payment_request"', $response->body);
        $this->assertStringContainsString('name="action" value="download_payment_request_pdf"', $response->body);
        $this->assertStringNotContainsString('rental-payment-request-dialog-' . (int) $paidRent['id'], $response->body);
        $this->assertStringNotContainsString('rental-payment-request-dialog-' . (int) $cancelledRent['id'], $response->body);
    }

    public function testCreatingPropertyCanCreateWholeHouseRentalUnit(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);

        $ownerId = $this->createPrivateUser($userRepository, 'whole-house@example.com');
        $this->assertTrue($moduleRepository->setUserModules($ownerId, ['real_estate_rental'], 'admin@example.com'));

        $controller = new \Caramagnols\PrivatePortal\Http\PrivatePortalController(
            auth: $this->privateAuth($userRepository, 'whole-house@example.com'),
            privateUserRepository: $userRepository,
            modulePermissionRepository: $moduleRepository,
            rentalPropertyRepository: $propertyRepository,
            rentalPropertyMemberRepository: $memberRepository,
            rentalUnitRepository: $unitRepository
        );

        $response = $controller->handle(
            'rental_properties',
            $this->request('POST', '/private/rental-properties', [
                'csrf_token' => csrf_token('private_rental'),
                'action' => 'create_property',
                'name' => 'Maison entière test',
                'address' => '11 rue du Jardin',
                'property_type' => 'Maison',
                'ownership_mode' => 'Pleine propriété',
                'status' => 'active',
                'create_default_unit' => '1',
                'default_unit_surface' => '88.50',
                'default_unit_furnished' => '1',
            ])
        );

        $this->assertSame(302, $response->status);
        $propertyIds = $memberRepository->activePropertyIdsForUser($ownerId);
        $this->assertCount(1, $propertyIds);
        $units = $unitRepository->listByPropertyIds($propertyIds);
        $this->assertCount(1, $units);
        $this->assertSame('Maison entière', $units[0]->label);
        $this->assertSame('house', $units[0]->unitType);
        $this->assertSame('11 rue du Jardin', $units[0]->address);
        $this->assertSame(88.50, $units[0]->surface);
        $this->assertTrue($units[0]->furnished);
    }

    public function testLeaseRowsOpenEditDialogAndUpdateLease(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);

        $ownerId = $this->createPrivateUser($userRepository, 'lease-update@example.com');
        $this->assertTrue($moduleRepository->setUserModules($ownerId, ['real_estate_rental'], 'admin@example.com'));
        $property = $propertyRepository->create($ownerId, 'Maison update', '9 rue du Bail', 'maison', 'indivision', 'active');
        $this->assertNotNull($property);
        $this->assertNotNull($memberRepository->create($property->id, $ownerId, 'owner', $ownerId));
        $unit = $unitRepository->create($property->id, 'Lot update', 42.0, true, 'available', null, $ownerId);
        $this->assertNotNull($unit);
        $tenant = $lifecycleRepository->createTenant($property->id, $unit->id, 'Locataire update', null, null, 'validated', $ownerId, null);
        $this->assertIsArray($tenant);
        $lease = $lifecycleRepository->createLease(
            $property->id,
            $unit->id,
            (int) $tenant['id'],
            '2026-01-01',
            '2026-12-31',
            750.0,
            25.0,
            'draft',
            $ownerId,
            null
        );
        $this->assertIsArray($lease);
        $leaseId = (int) $lease['id'];

        $controller = new \Caramagnols\PrivatePortal\Http\PrivatePortalController(
            auth: $this->privateAuth($userRepository, 'lease-update@example.com'),
            privateUserRepository: $userRepository,
            modulePermissionRepository: $moduleRepository,
            rentalPropertyRepository: $propertyRepository,
            rentalPropertyMemberRepository: $memberRepository,
            rentalUnitRepository: $unitRepository,
            rentalLifecycleRepository: $lifecycleRepository
        );

        $response = $controller->handle('rental_leases', $this->request('GET', '/private/leases'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('data-private-dialog-open="rental-lease-dialog-' . $leaseId . '"', $response->body);
        $this->assertStringContainsString('<input type="hidden" name="action" value="update_lease" />', $response->body);
        $this->assertStringContainsString('Supprimer le bail', $response->body);

        $post = $controller->handle(
            'rental_leases',
            $this->request('POST', '/private/leases', [
                'csrf_token' => csrf_token('private_rental'),
                'action' => 'update_lease',
                'lease_id' => (string) $leaseId,
                'rental_property_id' => (string) $property->id,
                'rental_unit_id' => (string) $unit->id,
                'rental_tenant_id' => (string) $tenant['id'],
                'lease_type' => 'residential_furnished',
                'start_date' => '2026-02-01',
                'end_date' => '2027-01-31',
                'monthly_rent' => '820.50',
                'charges_provision' => '45.25',
                'status' => 'validated',
                'notes' => 'Bail modifie depuis la popup.',
            ])
        );

        $this->assertSame(302, $post->status);
        $this->assertSame('/private/leases?notice=lease_updated', $post->headers['Location'] ?? null);

        $updated = $lifecycleRepository->findLeaseById($leaseId);
        $this->assertIsArray($updated);
        $this->assertSame('residential_furnished', $updated['leaseType']);
        $this->assertSame('bic_furnished', $updated['taxCategory']);
        $this->assertSame('2026-02-01', $updated['startDate']);
        $this->assertSame('2027-01-31', $updated['endDate']);
        $this->assertSame('validated', $updated['status']);
        $this->assertSame(820.50, (float) $updated['monthlyRent']);
        $this->assertSame(45.25, (float) $updated['chargesProvision']);
        $this->assertSame('Bail modifie depuis la popup.', $updated['notes']);
    }

    public function testLeaseCreationOnlyOffersAvailableUnitsWithoutActiveLease(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);

        $ownerId = $this->createPrivateUser($userRepository, 'lease-availability@example.com');
        $this->assertTrue($moduleRepository->setUserModules($ownerId, ['real_estate_rental'], 'admin@example.com'));
        $property = $propertyRepository->create($ownerId, 'Maison disponibilite', '12 rue du Bail', 'maison', 'indivision', 'active');
        $this->assertNotNull($property);
        $this->assertNotNull($memberRepository->create($property->id, $ownerId, 'owner', $ownerId));

        $leasedUnit = $unitRepository->create($property->id, 'Lot deja loue', 40.0, false, 'available', null, $ownerId);
        $unavailableUnit = $unitRepository->create($property->id, 'Lot travaux', 38.0, false, 'unavailable', null, $ownerId);
        $freeUnit = $unitRepository->create($property->id, 'Lot libre', 42.0, false, 'available', null, $ownerId);
        $this->assertNotNull($leasedUnit);
        $this->assertNotNull($unavailableUnit);
        $this->assertNotNull($freeUnit);
        $this->assertSame('unavailable', $unavailableUnit->status);

        $leasedTenant = $lifecycleRepository->createTenant($property->id, $leasedUnit->id, 'Locataire bail actif', null, null, 'validated', $ownerId, null);
        $unavailableTenant = $lifecycleRepository->createTenant($property->id, $unavailableUnit->id, 'Locataire travaux', null, null, 'validated', $ownerId, null);
        $freeTenant = $lifecycleRepository->createTenant($property->id, $freeUnit->id, 'Locataire libre', null, null, 'validated', $ownerId, null);
        $this->assertIsArray($leasedTenant);
        $this->assertIsArray($unavailableTenant);
        $this->assertIsArray($freeTenant);

        $activeLease = $lifecycleRepository->createLease(
            $property->id,
            $leasedUnit->id,
            (int) $leasedTenant['id'],
            '2026-01-01',
            '2026-12-31',
            700.0,
            0.0,
            'draft',
            $ownerId,
            null
        );
        $this->assertIsArray($activeLease);

        $controller = new \Caramagnols\PrivatePortal\Http\PrivatePortalController(
            auth: $this->privateAuth($userRepository, 'lease-availability@example.com'),
            privateUserRepository: $userRepository,
            modulePermissionRepository: $moduleRepository,
            rentalPropertyRepository: $propertyRepository,
            rentalPropertyMemberRepository: $memberRepository,
            rentalUnitRepository: $unitRepository,
            rentalLifecycleRepository: $lifecycleRepository
        );

        $unitsResponse = $controller->handle('rental_units', $this->request('GET', '/private/rental-units'));
        $this->assertSame(200, $unitsResponse->status);
        $this->assertStringContainsString('Disponibilité', $unitsResponse->body);
        $this->assertStringContainsString('Indisponible', $unitsResponse->body);
        $this->assertStringNotContainsString('Occupé', $unitsResponse->body);
        $this->assertStringNotContainsString('Maintenance', $unitsResponse->body);

        $leasesResponse = $controller->handle('rental_leases', $this->request('GET', '/private/leases'));
        $this->assertSame(200, $leasesResponse->status);
        $dialogStart = strpos($leasesResponse->body, 'id="rental-lease-create-dialog"');
        $this->assertIsInt($dialogStart);
        $createDialogHtml = substr($leasesResponse->body, $dialogStart);
        $this->assertStringContainsString('Lot libre', $createDialogHtml);
        $this->assertStringContainsString('Locataire libre', $createDialogHtml);
        $this->assertStringNotContainsString('Lot deja loue', $createDialogHtml);
        $this->assertStringNotContainsString('Lot travaux', $createDialogHtml);

        $blockedResponse = $controller->handle(
            'rental_leases',
            $this->request('POST', '/private/leases', [
                'csrf_token' => csrf_token('private_rental'),
                'action' => 'create_lease',
                'rental_property_id' => (string) $property->id,
                'rental_unit_id' => (string) $leasedUnit->id,
                'rental_tenant_id' => (string) $leasedTenant['id'],
                'lease_type' => 'residential_unfurnished',
                'start_date' => '2027-01-01',
                'end_date' => '2027-12-31',
                'monthly_rent' => '720',
                'charges_provision' => '0',
                'status' => 'draft',
            ])
        );
        $this->assertSame(200, $blockedResponse->status);
        $this->assertStringContainsString('Ce bien locatif est indisponible ou possède déjà un bail actif.', $blockedResponse->body);

        $createdResponse = $controller->handle(
            'rental_leases',
            $this->request('POST', '/private/leases', [
                'csrf_token' => csrf_token('private_rental'),
                'action' => 'create_lease',
                'rental_property_id' => (string) $property->id,
                'rental_unit_id' => (string) $freeUnit->id,
                'rental_tenant_id' => (string) $freeTenant['id'],
                'lease_type' => 'residential_unfurnished',
                'start_date' => '2027-01-01',
                'end_date' => '2027-12-31',
                'monthly_rent' => '720',
                'charges_provision' => '0',
                'status' => 'draft',
            ])
        );
        $this->assertSame(302, $createdResponse->status);
        $this->assertSame('/private/leases?notice=lease_created', $createdResponse->headers['Location'] ?? null);
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
