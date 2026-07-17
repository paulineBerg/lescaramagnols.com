<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyMemberRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalUnitRepository;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalAnnualSummaryService;
use Caramagnols\PrivateApps\RealEstateRental\TaxBridge\RentalTaxDataProvider;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivateApps\TaxDeclarationHelper\Source\RentalTaxDataSource;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class PrivatePortalTaxBridgeTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testRentalTaxProviderAggregatesAnnualAmountsWithSourceRows(): void
    {
        $context = $this->createContext('bridge-owner@example.com', 'Maison bridge', 'validated');
        $repository = $context['lifecycleRepository'];
        $provider = $context['provider'];

        $this->assertNotNull($repository->createPayment(
            $context['leaseId'],
            $context['propertyId'],
            $context['unitId'],
            '2026-03-05',
            2026,
            3,
            1200.0,
            900.0,
            'validated',
            $context['ownerId'],
            null
        ));
        $this->assertNotNull($repository->createExpense(
            $context['propertyId'],
            $context['unitId'],
            '2026-03-20',
            'Assurance locative',
            180.0,
            false,
            true,
            'validated',
            $context['ownerId'],
            null
        ));

        $income = $provider->annualRentalIncome(2026, [$context['propertyId']]);
        $expenses = $provider->annualDeductibleExpenses(2026, [$context['propertyId']]);

        $this->assertFalse($income->isBlocked());
        $this->assertSame(1200.0, $income->rentDue);
        $this->assertSame(900.0, $income->rentPaid);
        $this->assertSame(300.0, $income->unpaidRent);
        $this->assertSame(1, $income->validatedPaymentCount);
        $this->assertSame('rental_payments', $income->sourceRows()[0]['table'] ?? null);
        $this->assertSame($context['propertyId'], $income->sourceRows()[0]['propertyId'] ?? null);

        $this->assertFalse($expenses->isBlocked());
        $this->assertSame(0.0, $expenses->recoverableExpenses);
        $this->assertSame(180.0, $expenses->deductibleCandidateExpenses);
        $this->assertSame(0.0, $expenses->nonDeductibleExpenses);
        $this->assertSame('rental_expenses', $expenses->sourceRows()[0]['table'] ?? null);
    }

    public function testRentalTaxProviderReportsDraftRowsAsBlockingControls(): void
    {
        $context = $this->createContext('bridge-draft@example.com', 'Maison draft bridge', 'draft');
        $repository = $context['lifecycleRepository'];
        $provider = $context['provider'];

        $this->assertNotNull($repository->createPayment(
            $context['leaseId'],
            $context['propertyId'],
            $context['unitId'],
            '2026-01-05',
            2026,
            1,
            900.0,
            900.0,
            'draft',
            $context['ownerId'],
            null
        ));

        $income = $provider->annualRentalIncome(2026, [$context['propertyId']]);
        $controls = $provider->controls(2026, [$context['propertyId']]);

        $this->assertTrue($income->isBlocked());
        $this->assertSame(0.0, $income->rentPaid);
        $this->assertNotSame([], $income->blockingControls());
        $this->assertNotSame([], $controls);
    }

    public function testRentalTaxDataSourceExposesStableContractAndMissingDocuments(): void
    {
        $context = $this->createContext('bridge-source@example.com', 'Maison source bridge', 'validated');
        $repository = $context['lifecycleRepository'];
        $provider = $context['provider'];
        $source = new RentalTaxDataSource($provider);

        $this->assertNotNull($repository->createPayment(
            $context['leaseId'],
            $context['propertyId'],
            $context['unitId'],
            '2026-06-05',
            2026,
            6,
            700.0,
            700.0,
            'validated',
            $context['ownerId'],
            null
        ));

        $missingDocuments = $source->missingDocuments(2026, [$context['propertyId']]);

        $this->assertSame('real_estate_rental', $source->code());
        $this->assertSame('Locations immobilieres', $source->label());
        $this->assertSame(700.0, $source->annualRentalIncome(2026, [$context['propertyId']])->rentPaid);
        $this->assertCount(1, $missingDocuments);
        $this->assertSame('rental_supporting_documents', $missingDocuments[0]->documentType);
        $this->assertFalse($missingDocuments[0]->isBlocking());
    }

    /**
     * @return array{
     *     ownerId:int,
     *     propertyId:int,
     *     unitId:int,
     *     leaseId:int,
     *     lifecycleRepository:RentalLifecycleRepository,
     *     provider:RentalTaxDataProvider
     * }
     */
    private function createContext(string $email, string $propertyName, string $leaseStatus): array
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);
        $ownerId = $this->createPrivateUser($userRepository, $email);

        $property = $propertyRepository->create(
            $ownerId,
            $propertyName,
            '15 rue du Pont',
            'maison',
            'indivision',
            'active'
        );
        $this->assertNotNull($property);
        $this->assertNotNull($memberRepository->create($property->id, $ownerId, 'owner', $ownerId));
        $unit = $unitRepository->create($property->id, 'Lot bridge ' . substr(md5($propertyName), 0, 4), 38.0, false, 'available', null, $ownerId);
        $this->assertNotNull($unit);
        $tenant = $lifecycleRepository->createTenant(
            $property->id,
            $unit->id,
            'Locataire bridge ' . substr(md5($propertyName), 0, 4),
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
            null,
            900.0,
            60.0,
            $leaseStatus,
            $ownerId,
            null
        );
        $this->assertIsArray($lease);

        return [
            'ownerId' => $ownerId,
            'propertyId' => $property->id,
            'unitId' => $unit->id,
            'leaseId' => (int) $lease['id'],
            'lifecycleRepository' => $lifecycleRepository,
            'provider' => new RentalTaxDataProvider(
                new RentalAnnualSummaryService($lifecycleRepository),
                $lifecycleRepository
            ),
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
