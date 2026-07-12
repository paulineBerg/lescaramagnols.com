<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyMemberRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalUnitRepository;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalAnnualSummaryService;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalDashboardService;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class RentalDashboardServiceTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testDashboardAggregatesMonthlyRentsUnitsDocumentsAndAnnualBalances(): void
    {
        [$repository, $unitRepository, $ownerId, $propertyId, $leasedUnitId, $leaseId] = $this->context('dashboard@example.com', '2026-07-15', 'validated');
        $vacantUnit = $unitRepository->create($propertyId, 'Lot vacant', 20.0, false, 'available', null, $ownerId);
        $unavailableUnit = $unitRepository->create($propertyId, 'Lot indisponible', 12.0, false, 'unavailable', null, $ownerId);
        $this->assertNotNull($vacantUnit);
        $this->assertNotNull($unavailableUnit);

        $rent = $repository->createRent($leaseId, $propertyId, $leasedUnitId, 2026, 5, '2026-05-01', 1000.0, 'partial', $ownerId, null);
        $this->assertIsArray($rent);
        $this->assertNotNull($repository->createPayment($leaseId, $propertyId, $leasedUnitId, '2026-05-05', 2026, 5, 0.0, 400.0, 'validated', $ownerId, null, (int) $rent['id']));
        $this->assertNotNull($repository->createExpense($propertyId, $leasedUnitId, '2026-05-10', 'Assurance', 100.0, false, true, 'validated', $ownerId, null, 'assurance_pno', 2026));
        $this->assertNotNull($repository->createExpense($propertyId, $leasedUnitId, '2026-05-11', 'Decoration', 30.0, false, false, 'validated', $ownerId, null, 'mobilier', 2026));

        $dashboard = $this->service($repository, $unitRepository)->build(2026, 5, [$propertyId], '2026-05-31');

        $this->assertFalse((bool) ($dashboard['summaryBlocked'] ?? true));
        $this->assertSame(1, $dashboard['leasedUnitCount'] ?? null);
        $this->assertSame(1, $dashboard['vacantUnitCount'] ?? null);
        $this->assertSame(1, $dashboard['unavailableUnitCount'] ?? null);
        $this->assertSame(1000.0, $dashboard['monthlyRentDue'] ?? null);
        $this->assertSame(400.0, $dashboard['monthlyRentPaid'] ?? null);
        $this->assertSame(1, $dashboard['monthlyPartialRentCount'] ?? null);
        $this->assertSame(1, $dashboard['monthlyLateRentCount'] ?? null);
        $this->assertSame(1, $dashboard['leasesEndingSoonCount'] ?? null);
        $this->assertGreaterThanOrEqual(2, (int) ($dashboard['missingDocumentCount'] ?? 0));

        $balances = is_array($dashboard['annualBalances'] ?? null) ? $dashboard['annualBalances'] : [];
        $this->assertSame(400.0, $balances[0]['rentPaid'] ?? null);
        $this->assertSame(130.0, $balances[0]['expenses'] ?? null);
        $this->assertSame(100.0, $balances[0]['deductibleCandidateExpenses'] ?? null);
        $this->assertSame(270.0, $balances[0]['balance'] ?? null);
    }

    public function testDashboardSurfacesDraftRowsAsUncertainInsteadOfMergingTotals(): void
    {
        [$repository, $unitRepository, $ownerId, $propertyId, $unitId, $leaseId] = $this->context('dashboard-draft@example.com', null, 'draft');
        $this->assertNotNull($repository->createPayment($leaseId, $propertyId, $unitId, '2026-01-05', 2026, 1, 900.0, 900.0, 'draft', $ownerId, null));

        $dashboard = $this->service($repository, $unitRepository)->build(2026, 1, [$propertyId], '2026-01-31');

        $this->assertTrue((bool) ($dashboard['summaryBlocked'] ?? false));
        $this->assertGreaterThanOrEqual(1, (int) ($dashboard['issueCount'] ?? 0));
        $this->assertSame(0.0, $dashboard['rentPaid'] ?? null);
    }

    /**
     * @return array{0:RentalLifecycleRepository,1:RentalUnitRepository,2:int,3:int,4:int,5:int}
     */
    private function context(string $email, ?string $leaseEndDate, string $leaseStatus): array
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);
        $ownerId = $this->createPrivateUser($userRepository, $email);

        $property = $propertyRepository->create($ownerId, 'Maison dashboard', '9 rue du Dashboard', 'maison', 'indivision', 'active');
        $this->assertNotNull($property);
        $this->assertNotNull($memberRepository->create($property->id, $ownerId, 'owner', $ownerId));
        $unit = $unitRepository->create($property->id, 'Lot loue', 45.0, false, 'available', null, $ownerId);
        $this->assertNotNull($unit);
        $tenant = $lifecycleRepository->createTenant($property->id, $unit->id, 'Locataire dashboard', null, null, 'validated', $ownerId, null);
        $this->assertIsArray($tenant);
        $lease = $lifecycleRepository->createLease($property->id, $unit->id, (int) $tenant['id'], '2026-01-01', $leaseEndDate, 900.0, 100.0, $leaseStatus, $ownerId, null);
        $this->assertIsArray($lease);

        return [$lifecycleRepository, $unitRepository, $ownerId, $property->id, $unit->id, (int) $lease['id']];
    }

    private function service(RentalLifecycleRepository $repository, RentalUnitRepository $unitRepository): RentalDashboardService
    {
        return new RentalDashboardService($repository, $unitRepository, new RentalAnnualSummaryService($repository));
    }

    private function createPrivateUser(PrivateUserRepository $repository, string $email): int
    {
        $hash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($hash);
        $userId = $repository->create($email, $hash, 'active');
        $this->assertIsInt($userId);

        return $userId;
    }
}
