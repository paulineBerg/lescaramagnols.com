<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyMemberRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalUnitRepository;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentScheduleService;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class RentScheduleServiceTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testScheduleGeneratesExpectedRentFromValidatedLeaseAndIsIdempotent(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);
        $service = new RentScheduleService($lifecycleRepository);
        $ownerId = $this->createPrivateUser($userRepository, 'schedule-owner@example.com');

        [$propertyId, $unitId, $leaseId] = $this->createRentalSourceSet(
            $propertyRepository,
            $memberRepository,
            $unitRepository,
            $lifecycleRepository,
            $ownerId,
            'Maison echeancier',
            'validated'
        );

        $created = $service->generateForLeasePeriod($leaseId, 2026, 2, $ownerId);
        $this->assertSame(1, $created['created']);
        $this->assertSame(0, $created['existing']);
        $this->assertSame(0, $created['skipped']);

        $rents = $lifecycleRepository->listRents([$propertyId], 2026);
        $this->assertCount(1, $rents);
        $this->assertSame($leaseId, (int) $rents[0]['rentalLeaseId']);
        $this->assertSame($unitId, (int) $rents[0]['rentalUnitId']);
        $this->assertSame(2026, (int) $rents[0]['periodYear']);
        $this->assertSame(2, (int) $rents[0]['periodMonth']);
        $this->assertSame('2026-02-01', $rents[0]['dueDate']);
        $this->assertSame(1080.0, (float) $rents[0]['amountDue']);
        $this->assertSame('pending', $rents[0]['status']);
        $this->assertStringContainsString('Echeancier automatique', (string) $rents[0]['notes']);

        $existing = $service->generateForLeasePeriod($leaseId, 2026, 2, $ownerId);
        $this->assertSame(0, $existing['created']);
        $this->assertSame(1, $existing['existing']);
        $this->assertSame(0, $existing['skipped']);
        $this->assertCount(1, $lifecycleRepository->listRents([$propertyId], 2026));
    }

    public function testMonthlyDryRunDescribesRentsWithoutWritingRows(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);
        $service = new RentScheduleService($lifecycleRepository);
        $ownerId = $this->createPrivateUser($userRepository, 'schedule-dry-run@example.com');

        [$propertyId, $unitId, $leaseId] = $this->createRentalSourceSet(
            $propertyRepository,
            $memberRepository,
            $unitRepository,
            $lifecycleRepository,
            $ownerId,
            'Maison dry-run',
            'validated'
        );

        $dryRun = $service->dryRunForMonth([$propertyId], 2026, 5, $ownerId);

        $this->assertTrue($dryRun['dryRun']);
        $this->assertSame(0, $dryRun['created']);
        $this->assertSame(1, $dryRun['wouldCreate']);
        $this->assertSame(0, $dryRun['existing']);
        $this->assertSame(0, $dryRun['skipped']);
        $this->assertCount(1, $dryRun['rents']);
        $this->assertSame($leaseId, (int) ($dryRun['rents'][0]['rentalLeaseId'] ?? 0));
        $this->assertSame($unitId, (int) ($dryRun['rents'][0]['rentalUnitId'] ?? 0));
        $this->assertSame('2026-05-01', $dryRun['rents'][0]['dueDate'] ?? null);
        $this->assertSame(1080.0, (float) ($dryRun['rents'][0]['amountDue'] ?? 0));
        $this->assertSame([], $lifecycleRepository->listRents([$propertyId], 2026));

        $generated = $service->generateForMonth([$propertyId], 2026, 5, $ownerId);
        $this->assertSame(1, $generated['created']);
        $this->assertCount(1, $lifecycleRepository->listRents([$propertyId], 2026));
    }

    public function testDuplicateRentForLeasePeriodIsRejectedAndPaymentLinksExistingRent(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);
        $ownerId = $this->createPrivateUser($userRepository, 'schedule-duplicate@example.com');

        [$propertyId, $unitId, $leaseId] = $this->createRentalSourceSet(
            $propertyRepository,
            $memberRepository,
            $unitRepository,
            $lifecycleRepository,
            $ownerId,
            'Maison doublon',
            'validated'
        );

        $rent = $lifecycleRepository->createRent(
            $leaseId,
            $propertyId,
            $unitId,
            2026,
            3,
            '2026-03-01',
            1080.0,
            'validated',
            $ownerId,
            null
        );
        $this->assertIsArray($rent);
        $this->assertNull($lifecycleRepository->createRent(
            $leaseId,
            $propertyId,
            $unitId,
            2026,
            3,
            '2026-03-01',
            1080.0,
            'validated',
            $ownerId,
            null
        ));

        $payment = $lifecycleRepository->createPayment(
            $leaseId,
            $propertyId,
            $unitId,
            '2026-03-05',
            2026,
            3,
            1080.0,
            1080.0,
            'validated',
            $ownerId,
            null,
            null
        );

        $this->assertIsArray($payment);
        $this->assertSame((int) $rent['id'], (int) $payment['rentalRentId']);
        $this->assertCount(1, $lifecycleRepository->listRents([$propertyId], 2026));
    }

    public function testScheduleRejectsCancelledLeaseAndEndedLeaseOutsidePeriod(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);
        $service = new RentScheduleService($lifecycleRepository);
        $ownerId = $this->createPrivateUser($userRepository, 'schedule-reject@example.com');

        [$cancelledPropertyId, , $cancelledLeaseId] = $this->createRentalSourceSet(
            $propertyRepository,
            $memberRepository,
            $unitRepository,
            $lifecycleRepository,
            $ownerId,
            'Maison bail annule',
            'cancelled'
        );
        [$endedPropertyId, , $endedLeaseId] = $this->createRentalSourceSet(
            $propertyRepository,
            $memberRepository,
            $unitRepository,
            $lifecycleRepository,
            $ownerId,
            'Maison bail termine',
            'ended'
        );

        $cancelled = $service->generateForLeasePeriod($cancelledLeaseId, 2026, 4, $ownerId);
        $this->assertSame(0, $cancelled['created']);
        $this->assertSame(1, $cancelled['skipped']);
        $this->assertSame(1, $cancelled['reasons']['lease_inactive'] ?? 0);

        $endedInside = $service->generateForLeasePeriod($endedLeaseId, 2026, 8, $ownerId);
        $this->assertSame(1, $endedInside['created']);

        $endedOutside = $service->generateForLeasePeriod($endedLeaseId, 2026, 9, $ownerId);
        $this->assertSame(0, $endedOutside['created']);
        $this->assertSame(1, $endedOutside['skipped']);
        $this->assertSame(1, $endedOutside['reasons']['lease_outside_period'] ?? 0);
        $this->assertCount(0, $lifecycleRepository->listRents([$cancelledPropertyId], 2026));
        $this->assertCount(1, $lifecycleRepository->listRents([$endedPropertyId], 2026));
    }

    public function testRentLeasePeriodUniqueIndexExistsInSchema(): void
    {
        $database = $this->editorialSqlDatabase();
        $repository = new RentalLifecycleRepository($database);
        $repository->ensureSchema();

        $statement = $database->pdo()->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND INDEX_NAME = :index_name
               AND NON_UNIQUE = 0'
        );
        $statement->execute([
            'table' => $repository->rentsTable(),
            'index_name' => 'uq_rental_rents_lease_period',
        ]);

        $this->assertGreaterThan(0, (int) $statement->fetchColumn());
    }

    private function createPrivateUser(PrivateUserRepository $repository, string $email): int
    {
        $hash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($hash);
        $userId = $repository->create($email, $hash, 'active');
        $this->assertIsInt($userId);

        return $userId;
    }

    /**
     * @return array{0:int, 1:int, 2:int}
     */
    private function createRentalSourceSet(
        RentalPropertyRepository $propertyRepository,
        RentalPropertyMemberRepository $memberRepository,
        RentalUnitRepository $unitRepository,
        RentalLifecycleRepository $lifecycleRepository,
        int $ownerId,
        string $propertyName,
        string $leaseStatus
    ): array {
        $property = $propertyRepository->create(
            $ownerId,
            $propertyName,
            '12 rue de la Phase',
            'maison',
            'indivision',
            'active'
        );
        $this->assertNotNull($property);
        $this->assertNotNull($memberRepository->create($property->id, $ownerId, 'owner', $ownerId));
        $unit = $unitRepository->create($property->id, 'Lot principal ' . substr(md5($propertyName), 0, 4), 42.0, false, 'available', null, $ownerId);
        $this->assertNotNull($unit);
        $tenant = $lifecycleRepository->createTenant(
            $property->id,
            $unit->id,
            'Locataire ' . substr(md5($propertyName), 0, 6),
            null,
            null,
            'validated',
            $ownerId,
            null
        );
        $this->assertIsArray($tenant);
        $lease = $lifecycleRepository->createLease(
            $property->id,
            $unit->id,
            (int) $tenant['id'],
            '2026-01-01',
            $leaseStatus === 'ended' ? '2026-08-31' : null,
            1000.0,
            80.0,
            $leaseStatus,
            $ownerId,
            null
        );
        $this->assertIsArray($lease);

        return [$property->id, $unit->id, (int) $lease['id']];
    }
}
