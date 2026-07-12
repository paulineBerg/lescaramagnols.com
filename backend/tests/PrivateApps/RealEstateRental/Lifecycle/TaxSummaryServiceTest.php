<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyMemberRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalUnitRepository;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalAnnualSummaryService;
use Caramagnols\PrivateApps\RealEstateRental\TaxBridge\RentalTaxDataProvider;
use Caramagnols\PrivateApps\RealEstateRental\TaxBridge\RentalTaxDataSource;
use Caramagnols\PrivateApps\TaxDeclarationHelper\Repository\TaxDeclarationRepository;
use Caramagnols\PrivateApps\TaxDeclarationHelper\Service\TaxDeclarationSummaryService;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class TaxSummaryServiceTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testTaxSummaryKeepsRentalSourceAndExcludesNonDeductibleCharges(): void
    {
        $context = $this->context('tax-summary@example.com', 'validated');
        $repository = $context['lifecycleRepository'];
        $taxRepository = $context['taxRepository'];
        $this->assertNotNull($taxRepository->setSourceActivation($context['ownerId'], 2026, 'real_estate_rental', true, $context['ownerId']));

        $this->assertNotNull($repository->createPayment($context['leaseId'], $context['propertyId'], $context['unitId'], '2026-03-05', 2026, 3, 1000.0, 1000.0, 'validated', $context['ownerId'], null));
        $this->assertNotNull($repository->createExpense($context['propertyId'], $context['unitId'], '2026-03-10', 'Assurance deductible', 100.0, false, true, 'validated', $context['ownerId'], null, 'assurance_pno', 2026));
        $this->assertNotNull($repository->createExpense($context['propertyId'], $context['unitId'], '2026-03-11', 'Mobilier non deductible', 50.0, false, false, 'validated', $context['ownerId'], null, 'mobilier', 2026));

        $summary = $context['service']->build($context['ownerId'], 2026, [$context['propertyId']]);
        $totals = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];
        $lines = is_array($summary['lines'] ?? null) ? $summary['lines'] : [];

        $this->assertSame(1000.0, $totals['rentalIncome'] ?? null);
        $this->assertSame(100.0, $totals['deductibleExpenses'] ?? null);
        $this->assertContains('real_estate_rental', array_column($lines, 'sourceCode'));
        $expenseLines = array_values(array_filter(
            $lines,
            static fn (array $line): bool => ($line['lineType'] ?? '') === 'expense'
        ));
        $this->assertSame(100.0, $expenseLines[0]['amount'] ?? null);
    }

    public function testTaxSummaryKeepsDraftRentalRowsAsBlockingControls(): void
    {
        $context = $this->context('tax-summary-draft@example.com', 'draft');
        $repository = $context['lifecycleRepository'];
        $taxRepository = $context['taxRepository'];
        $this->assertNotNull($taxRepository->setSourceActivation($context['ownerId'], 2026, 'real_estate_rental', true, $context['ownerId']));

        $this->assertNotNull($repository->createPayment($context['leaseId'], $context['propertyId'], $context['unitId'], '2026-01-05', 2026, 1, 900.0, 900.0, 'draft', $context['ownerId'], null));

        $summary = $context['service']->build($context['ownerId'], 2026, [$context['propertyId']]);
        $totals = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];

        $this->assertGreaterThanOrEqual(1, (int) ($totals['controlsCount'] ?? 0));
        $this->assertSame(0.0, $totals['rentalIncome'] ?? null);
        $this->assertSame(0.0, $totals['deductibleExpenses'] ?? null);
    }

    /**
     * @return array{
     *     ownerId:int,
     *     propertyId:int,
     *     unitId:int,
     *     leaseId:int,
     *     lifecycleRepository:RentalLifecycleRepository,
     *     taxRepository:TaxDeclarationRepository,
     *     service:TaxDeclarationSummaryService
     * }
     */
    private function context(string $email, string $leaseStatus): array
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);
        $taxRepository = new TaxDeclarationRepository($database);
        $ownerId = $this->createPrivateUser($userRepository, $email);

        $property = $propertyRepository->create($ownerId, 'Maison tax summary', '17 rue fiscale', 'maison', 'indivision', 'active');
        $this->assertNotNull($property);
        $this->assertNotNull($memberRepository->create($property->id, $ownerId, 'owner', $ownerId));
        $unit = $unitRepository->create($property->id, 'Lot tax summary', 35.0, false, 'available', null, $ownerId);
        $this->assertNotNull($unit);
        $tenant = $lifecycleRepository->createTenant($property->id, $unit->id, 'Locataire tax summary', null, null, 'validated', $ownerId, null);
        $this->assertIsArray($tenant);
        $lease = $lifecycleRepository->createLease($property->id, $unit->id, (int) $tenant['id'], '2026-01-01', null, 900.0, 60.0, $leaseStatus, $ownerId, null);
        $this->assertIsArray($lease);

        $service = new TaxDeclarationSummaryService($taxRepository, [
            new RentalTaxDataSource(new RentalTaxDataProvider(
                new RentalAnnualSummaryService($lifecycleRepository),
                $lifecycleRepository
            )),
        ]);

        return [
            'ownerId' => $ownerId,
            'propertyId' => $property->id,
            'unitId' => $unit->id,
            'leaseId' => (int) $lease['id'],
            'lifecycleRepository' => $lifecycleRepository,
            'taxRepository' => $taxRepository,
            'service' => $service,
        ];
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
