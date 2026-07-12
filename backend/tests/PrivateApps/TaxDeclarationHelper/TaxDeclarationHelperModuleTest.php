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

require_once __DIR__ . '/../../../core/bootstrap.php';

final class TaxDeclarationHelperModuleTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testAnnualSummaryDistinguishesRentalAndManualSources(): void
    {
        $context = $this->createContext();
        $taxRepository = $context['taxRepository'];
        $service = $context['service'];

        $this->assertNotNull($taxRepository->createManualEntry(
            $context['ownerId'],
            2026,
            'Revenu manuel valide',
            500.0,
            'autre',
            'validated',
            $context['ownerId'],
            null
        ));
        $this->assertNotNull($context['lifecycleRepository']->createPayment(
            $context['leaseId'],
            $context['propertyId'],
            $context['unitId'],
            '2026-02-05',
            2026,
            2,
            1000.0,
            1000.0,
            'validated',
            $context['ownerId'],
            null
        ));
        $this->assertNotNull($context['lifecycleRepository']->createExpense(
            $context['propertyId'],
            $context['unitId'],
            '2026-02-15',
            'Assurance deductible',
            120.0,
            false,
            true,
            'validated',
            $context['ownerId'],
            null
        ));

        $summary = $service->build($context['ownerId'], 2026, [$context['propertyId']]);
        $totals = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];
        $lines = is_array($summary['lines'] ?? null) ? $summary['lines'] : [];

        $this->assertSame(500.0, $totals['manualIncome'] ?? null);
        $this->assertSame(0.0, $totals['rentalIncome'] ?? null);
        $this->assertNotContains('real_estate_rental', array_column($lines, 'sourceCode'));
        $this->assertNull($taxRepository->setSourceActivation(
            $context['ownerId'],
            2026,
            'unknown_source',
            true,
            $context['ownerId']
        ));

        $this->assertNotNull($taxRepository->setSourceActivation(
            $context['ownerId'],
            2026,
            'real_estate_rental',
            true,
            $context['ownerId']
        ));

        $summary = $service->build($context['ownerId'], 2026, [$context['propertyId']]);
        $totals = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];
        $lines = is_array($summary['lines'] ?? null) ? $summary['lines'] : [];

        $this->assertSame(1000.0, $totals['rentalIncome'] ?? null);
        $this->assertSame(1500.0, $totals['grossIncome'] ?? null);
        $this->assertSame(120.0, $totals['deductibleExpenses'] ?? null);
        $this->assertContains('manual', array_column($lines, 'sourceCode'));
        $this->assertContains('real_estate_rental', array_column($lines, 'sourceCode'));
        $this->assertContains('rental_payments', array_column($lines, 'sourceReference'));
        $this->assertStringContainsString('Aide non officielle', (string) ($summary['officialDisclaimer'] ?? ''));
    }

    public function testGenerationPersistsLinesAndExportsAreLogged(): void
    {
        $context = $this->createContext();
        $taxRepository = $context['taxRepository'];
        $service = $context['service'];

        $this->assertNotNull($taxRepository->createManualEntry(
            $context['ownerId'],
            2026,
            'Prime ponctuelle',
            250.0,
            'manuel',
            'validated',
            $context['ownerId'],
            null
        ));

        $summary = $service->generate($context['ownerId'], 2026, [$context['propertyId']], $context['ownerId']);
        $this->assertIsArray($summary);
        $saved = $taxRepository->findSummary($context['ownerId'], 2026);
        $this->assertIsArray($saved);
        $summaryId = is_numeric($saved['id'] ?? null) ? (int) $saved['id'] : 0;
        $this->assertGreaterThan(0, $summaryId);
        $this->assertNotSame([], $taxRepository->listSummaryLines($summaryId));
        $this->assertTrue($taxRepository->createExportLog($context['ownerId'], 2026, 'csv', $summary, $context['ownerId']));
        $this->assertSame(1, $taxRepository->countExportLogs($context['ownerId'], 2026, 'csv'));
    }

    public function testLockedYearRejectsMemberWritesUntilAuditedUnlock(): void
    {
        $context = $this->createContext();
        $taxRepository = $context['taxRepository'];
        $service = $context['service'];

        $this->assertTrue($taxRepository->lockYear($context['ownerId'], 2026, $context['ownerId']));
        $this->assertNull($taxRepository->setSourceActivation(
            $context['ownerId'],
            2026,
            'real_estate_rental',
            true,
            $context['ownerId']
        ));
        $this->assertNull($taxRepository->createManualEntry(
            $context['ownerId'],
            2026,
            'Refuse apres verrouillage',
            10.0,
            'manuel',
            'validated',
            $context['ownerId'],
            null
        ));
        $this->assertNull($service->generate($context['ownerId'], 2026, [$context['propertyId']], $context['ownerId']));
        $this->assertTrue($taxRepository->unlockYear($context['ownerId'], 2026, $context['ownerId']));
        $this->assertNotNull($taxRepository->createManualEntry(
            $context['ownerId'],
            2026,
            'Accepte apres deverrouillage',
            10.0,
            'manuel',
            'validated',
            $context['ownerId'],
            null
        ));
        $this->assertNotNull($taxRepository->setSourceActivation(
            $context['ownerId'],
            2026,
            'real_estate_rental',
            true,
            $context['ownerId']
        ));
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
    private function createContext(): array
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);
        $taxRepository = new TaxDeclarationRepository($database);
        $ownerId = $this->createPrivateUser($userRepository, 'tax-helper-' . bin2hex(random_bytes(3)) . '@example.com');

        $property = $propertyRepository->create($ownerId, 'Maison Impots ' . bin2hex(random_bytes(2)), '22 rue fiscale', 'maison', 'indivision', 'active');
        $this->assertNotNull($property);
        $this->assertNotNull($memberRepository->create($property->id, $ownerId, 'owner', $ownerId));
        $unit = $unitRepository->create($property->id, 'Lot fiscal ' . bin2hex(random_bytes(2)), 40.0, false, 'available', null, $ownerId);
        $this->assertNotNull($unit);
        $tenant = $lifecycleRepository->createTenant($property->id, $unit->id, 'Locataire fiscal', null, null, 'validated', $ownerId, null);
        $this->assertIsArray($tenant);
        $lease = $lifecycleRepository->createLease($property->id, $unit->id, (int) $tenant['id'], '2026-01-01', null, 1000.0, 50.0, 'validated', $ownerId, null);
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
