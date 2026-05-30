<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
use Caramagnols\PrivateApps\RealEstateRental\Domain\RentalLeaseTypeCatalog;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyMemberRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalUnitRepository;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalAnnualSummaryService;
use Caramagnols\PrivatePortal\Documents\PrivateDocumentStorage;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class RentalLifecycleTest extends TestCase
{
    use EditorialSqlTestTrait;

    private string $tempStorageRoot = '';

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
        $this->removeTempStorage();
    }

    public function testAnnualSummaryUsesAuthorizedPropertiesAndValidatedSourceRows(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);
        $summaryService = new RentalAnnualSummaryService($lifecycleRepository);
        $ownerId = $this->createPrivateUser($userRepository, 'owner-phase6@example.com');

        [$propertyId, $unitId, $leaseId] = $this->createRentalSourceSet(
            $propertyRepository,
            $memberRepository,
            $unitRepository,
            $lifecycleRepository,
            $ownerId,
            'Maison visible',
            'ended'
        );
        [$hiddenPropertyId, $hiddenUnitId, $hiddenLeaseId] = $this->createRentalSourceSet(
            $propertyRepository,
            $memberRepository,
            $unitRepository,
            $lifecycleRepository,
            $ownerId,
            'Maison masquee',
            'validated'
        );

        $this->assertNotNull($lifecycleRepository->createPayment(
            $leaseId,
            $propertyId,
            $unitId,
            '2026-03-05',
            2026,
            3,
            1000.0,
            600.0,
            'validated',
            $ownerId,
            null
        ));
        $this->assertNotNull($lifecycleRepository->createPayment(
            $hiddenLeaseId,
            $hiddenPropertyId,
            $hiddenUnitId,
            '2026-03-05',
            2026,
            3,
            2000.0,
            2000.0,
            'validated',
            $ownerId,
            null
        ));
        $this->assertNotNull($lifecycleRepository->createExpense(
            $propertyId,
            $unitId,
            '2026-04-10',
            'Entretien chaudiere',
            120.0,
            true,
            true,
            'validated',
            $ownerId,
            null
        ));
        $this->assertNotNull($lifecycleRepository->createExpense(
            $propertyId,
            null,
            '2026-05-10',
            'Decoration non deductible',
            80.0,
            false,
            false,
            'validated',
            $ownerId,
            null
        ));

        $summary = $summaryService->build(2026, [$propertyId]);
        $totals = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];

        $this->assertFalse((bool) ($summary['blocked'] ?? true));
        $this->assertSame(1000.0, $totals['rentDue'] ?? null);
        $this->assertSame(600.0, $totals['rentPaid'] ?? null);
        $this->assertSame(400.0, $totals['unpaidRent'] ?? null);
        $this->assertSame(120.0, $totals['recoverableExpenses'] ?? null);
        $this->assertSame(120.0, $totals['deductibleCandidateExpenses'] ?? null);
        $this->assertSame(80.0, $totals['nonDeductibleExpenses'] ?? null);
        $this->assertSame(1, $totals['partialPayments'] ?? null);
        $this->assertSame(1, $totals['endedLeases'] ?? null);
    }

    public function testAnnualSummaryRejectsDraftFiscalSourceRows(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);
        $summaryService = new RentalAnnualSummaryService($lifecycleRepository);
        $ownerId = $this->createPrivateUser($userRepository, 'owner-draft-phase6@example.com');

        [$propertyId, $unitId, $leaseId] = $this->createRentalSourceSet(
            $propertyRepository,
            $memberRepository,
            $unitRepository,
            $lifecycleRepository,
            $ownerId,
            'Maison brouillon',
            'draft'
        );
        $this->assertNotNull($lifecycleRepository->createPayment(
            $leaseId,
            $propertyId,
            $unitId,
            '2026-01-05',
            2026,
            1,
            900.0,
            900.0,
            'draft',
            $ownerId,
            null
        ));
        $this->assertNotNull($lifecycleRepository->createExpense(
            $propertyId,
            $unitId,
            '2026-02-10',
            'Charge brouillon',
            50.0,
            true,
            true,
            'draft',
            $ownerId,
            null
        ));

        $summary = $summaryService->build(2026, [$propertyId]);
        $issues = is_array($summary['issues'] ?? null) ? $summary['issues'] : [];

        $this->assertTrue((bool) ($summary['blocked'] ?? false));
        $this->assertGreaterThanOrEqual(3, count($issues));
    }

    public function testSeveralPaymentsCanSettleOneRent(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);
        $summaryService = new RentalAnnualSummaryService($lifecycleRepository);
        $ownerId = $this->createPrivateUser($userRepository, 'owner-rent-split@example.com');

        [$propertyId, $unitId, $leaseId] = $this->createRentalSourceSet(
            $propertyRepository,
            $memberRepository,
            $unitRepository,
            $lifecycleRepository,
            $ownerId,
            'Maison paiements multiples',
            'validated'
        );

        $rent = $lifecycleRepository->createRent(
            $leaseId,
            $propertyId,
            $unitId,
            2026,
            4,
            '2026-04-01',
            1000.0,
            'validated',
            $ownerId,
            null
        );
        $this->assertIsArray($rent);
        $rentId = (int) $rent['id'];
        $this->assertNotNull($lifecycleRepository->createPayment(
            $leaseId,
            $propertyId,
            $unitId,
            '2026-04-05',
            2026,
            4,
            0.0,
            400.0,
            'validated',
            $ownerId,
            null,
            $rentId
        ));
        $this->assertNotNull($lifecycleRepository->createPayment(
            $leaseId,
            $propertyId,
            $unitId,
            '2026-04-20',
            2026,
            4,
            0.0,
            600.0,
            'validated',
            $ownerId,
            null,
            $rentId
        ));

        $rents = $lifecycleRepository->listRents([$propertyId], 2026);
        $this->assertSame(1, count($rents));
        $this->assertSame(1000.0, (float) ($rents[0]['amountPaid'] ?? 0));
        $this->assertSame(2, (int) ($rents[0]['paymentCount'] ?? 0));

        $summary = $summaryService->build(2026, [$propertyId]);
        $totals = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];

        $this->assertSame(1000.0, $totals['rentDue'] ?? null);
        $this->assertSame(1000.0, $totals['rentPaid'] ?? null);
        $this->assertSame(0.0, $totals['unpaidRent'] ?? null);
        $this->assertSame(2, $totals['validatedPayments'] ?? null);
        $this->assertSame(0, $totals['partialPayments'] ?? null);
    }

    public function testLeaseTypeComputesDefaultEndDateAndTaxCategory(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);
        $ownerId = $this->createPrivateUser($userRepository, 'owner-lease-type@example.com');

        $property = $propertyRepository->create($ownerId, 'Maison type bail', '5 rue du Bail', 'maison', 'indivision', 'active');
        $this->assertNotNull($property);
        $this->assertNotNull($memberRepository->create($property->id, $ownerId, 'owner', $ownerId));
        $unit = $unitRepository->create($property->id, 'Lot type bail', 36.0, true, 'available', null, $ownerId);
        $this->assertNotNull($unit);
        $tenant = $lifecycleRepository->createTenant($property->id, $unit->id, 'Locataire type bail', null, null, 'validated', $ownerId, null);
        $this->assertIsArray($tenant);

        $lease = $lifecycleRepository->createLease(
            $property->id,
            $unit->id,
            (int) $tenant['id'],
            '2026-03-05',
            null,
            850.0,
            70.0,
            'validated',
            $ownerId,
            null,
            'residential_furnished'
        );
        $this->assertIsArray($lease);
        $this->assertSame('residential_furnished', $lease['leaseType'] ?? null);
        $this->assertSame('bic_furnished', $lease['taxCategory'] ?? null);
        $this->assertSame('2027-03-04', $lease['endDate'] ?? null);

        $summary = (new RentalAnnualSummaryService($lifecycleRepository))->build(2026, [$property->id]);
        $taxCategories = is_array($summary['leaseTaxCategories'] ?? null) ? $summary['leaseTaxCategories'] : [];
        $this->assertSame(1, $taxCategories['bic_furnished']['count'] ?? null);
        $this->assertSame('2026-12-04', RentalLeaseTypeCatalog::defaultEndDate('student_furnished', '2026-03-05'));
    }

    public function testDocumentsStayOutsideWebrootAndExportsAreLogged(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);
        $summaryService = new RentalAnnualSummaryService($lifecycleRepository);
        $ownerId = $this->createPrivateUser($userRepository, 'owner-document-phase6@example.com');

        [$propertyId, $unitId, $leaseId] = $this->createRentalSourceSet(
            $propertyRepository,
            $memberRepository,
            $unitRepository,
            $lifecycleRepository,
            $ownerId,
            'Maison document',
            'validated'
        );

        $storage = $this->documentStorage();
        $tmpFile = tempnam(sys_get_temp_dir(), 'rental-doc-');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, 'bail de test');
        $metadata = $storage->validateUploadedFile([
            'name' => 'bail.txt',
            'tmp_name' => $tmpFile,
            'size' => filesize($tmpFile) ?: 0,
            'error' => UPLOAD_ERR_OK,
            'type' => 'text/plain',
        ]);
        $this->assertIsArray($metadata);
        $documentId = $storage->generateDocumentId();
        $stored = $storage->storeUploadedFile($metadata, $documentId);
        $this->assertIsArray($stored);
        $document = $lifecycleRepository->createDocument(
            $propertyId,
            $unitId,
            $leaseId,
            (string) $stored['documentId'],
            (string) $stored['storagePath'],
            (string) $stored['originalName'],
            (string) $stored['extension'],
            (string) $stored['mimeType'],
            (int) $stored['sizeBytes'],
            $ownerId
        );
        $this->assertIsArray($document);

        $absolutePath = $storage->absolutePath((string) $stored['storagePath']);
        $this->assertIsString($absolutePath);
        $webroot = realpath(ROOT_PATH . '/public');
        $storedRoot = realpath($absolutePath);
        $this->assertIsString($webroot);
        $this->assertIsString($storedRoot);
        $this->assertStringStartsNotWith($webroot, $storedRoot);

        $summary = $summaryService->build(2026, [$propertyId]);
        $this->assertTrue($lifecycleRepository->createExportLog($ownerId, 2026, 'csv', $summary));
        $this->assertSame(1, $lifecycleRepository->countExportLogs($ownerId, 2026, 'csv'));
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

    private function documentStorage(): PrivateDocumentStorage
    {
        $this->tempStorageRoot = sys_get_temp_dir() . '/caramagnols-rental-docs-' . bin2hex(random_bytes(6));

        return new PrivateDocumentStorage($this->tempStorageRoot, 'storage', 'uploads', 'exports');
    }

    private function removeTempStorage(): void
    {
        if ($this->tempStorageRoot === '' || !is_dir($this->tempStorageRoot)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempStorageRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($this->tempStorageRoot);
        $this->tempStorageRoot = '';
    }
}
