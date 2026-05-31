<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyMemberRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalUnitRepository;
use Caramagnols\PrivateApps\RealEstateRental\Service\ChargeRegularizationService;
use Caramagnols\PrivatePortal\Documents\PrivateDocumentStorage;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class ChargeRegularizationServiceTest extends TestCase
{
    use EditorialSqlTestTrait;

    private string $tempDir = '';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/caramagnols-rental-regularizations-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
        $this->removeDirectory($this->tempDir);
    }

    public function testRegularizationCalculatesTenantDueAndStoresVerifiableSnapshot(): void
    {
        [$repository, $ownerId, $propertyId, $unitId, $leaseId] = $this->rentalContext('regularization-due@example.com', 100.0);
        $this->createRent($repository, $leaseId, $propertyId, $unitId, 2026, 1, $ownerId);
        $this->createRent($repository, $leaseId, $propertyId, $unitId, 2026, 2, $ownerId);
        $expense = $repository->createExpense($propertyId, $unitId, '2026-03-10', 'Eau recuperable', 260.0, true, false, 'validated', $ownerId, null, 'eau', 2026);
        $this->assertIsArray($expense);
        $this->assertNotNull($repository->createExpense($propertyId, $unitId, '2026-03-11', 'Mobilier prive', 50.0, false, false, 'validated', $ownerId, null, 'mobilier', 2026));
        $this->attachSupportingDocument($repository, $propertyId, $unitId, (int) $expense['id'], $ownerId);

        $service = $this->service($repository);
        $preview = $service->preview($propertyId, $unitId, 2026, 100.0, [$propertyId]);
        $this->assertIsArray($preview);
        $this->assertSame('200.00', $preview['provisionsAmount']);
        $this->assertSame('260.00', $preview['recoverableExpensesAmount']);
        $this->assertSame('260.00', $preview['tenantRecoverableAmount']);
        $this->assertSame('60.00', $preview['balanceAmount']);
        $this->assertSame('tenant_due', $preview['balanceDirection']);
        $expenseRows = is_array($preview['expenseRows'] ?? null) ? $preview['expenseRows'] : [];
        $waterExpense = array_values(array_filter(
            $expenseRows,
            static fn (array $row): bool => ($row['label'] ?? '') === 'Eau recuperable'
        ))[0] ?? [];
        $supportingDocuments = is_array($waterExpense['supportingDocuments'] ?? null) ? $waterExpense['supportingDocuments'] : [];
        $this->assertCount(1, $supportingDocuments);

        $document = $service->generate($propertyId, $unitId, 2026, 100.0, [$propertyId], $ownerId);
        $this->assertIsArray($document);
        $this->assertSame('60.00', (string) $document['balanceAmount']);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', (string) $document['sha256Hash']);

        $absolutePath = $service->absolutePath($document);
        $this->assertIsString($absolutePath);
        $this->assertFileExists($absolutePath);
        $this->assertStringStartsWith($this->tempDir, $absolutePath);
        $this->assertStringContainsString('Regularisation annuelle des charges', (string) $service->content($document));
        $this->assertStringContainsString('Snapshot verifiable', (string) $service->content($document));

        $again = $service->generate($propertyId, $unitId, 2026, 100.0, [$propertyId], $ownerId);
        $this->assertIsArray($again);
        $this->assertSame($document['documentId'], $again['documentId']);
    }

    public function testRegularizationCalculatesRefundWhenProvisionsExceedRecoverableCharges(): void
    {
        [$repository, $ownerId, $propertyId, $unitId, $leaseId] = $this->rentalContext('regularization-refund@example.com', 100.0);
        $this->createRent($repository, $leaseId, $propertyId, $unitId, 2026, 1, $ownerId);
        $this->createRent($repository, $leaseId, $propertyId, $unitId, 2026, 2, $ownerId);
        $this->assertNotNull($repository->createExpense($propertyId, $unitId, '2026-04-10', 'Entretien recuperable', 120.0, true, true, 'validated', $ownerId, null, 'entretien', 2026));

        $preview = $this->service($repository)->preview($propertyId, $unitId, 2026, 100.0, [$propertyId]);
        $this->assertIsArray($preview);
        $this->assertSame('-80.00', $preview['balanceAmount']);
        $this->assertSame('tenant_refund', $preview['balanceDirection']);
    }

    public function testRegularizationIsForbiddenWithoutPropertyRight(): void
    {
        $context = $this->rentalContext('regularization-forbidden@example.com', 80.0);
        $repository = $context[0];
        $propertyId = $context[2];
        $unitId = $context[3];

        $this->assertNull($this->service($repository)->preview($propertyId, $unitId, 2026, 100.0, [99999]));
    }

    /**
     * @return array{0:RentalLifecycleRepository, 1:int, 2:int, 3:int, 4:int}
     */
    private function rentalContext(string $email, float $chargesProvision): array
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);
        $ownerId = $this->createPrivateUser($userRepository, $email);

        $property = $propertyRepository->create($ownerId, 'Maison regularisation', '12 rue des Charges', 'maison', 'indivision', 'active');
        $this->assertNotNull($property);
        $this->assertNotNull($memberRepository->create($property->id, $ownerId, 'owner', $ownerId));
        $unit = $unitRepository->create($property->id, 'Lot regularisation', 42.0, false, 'available', null, $ownerId);
        $this->assertNotNull($unit);
        $tenant = $lifecycleRepository->createTenant($property->id, $unit->id, 'Locataire charges', 'charges@example.com', null, 'validated', $ownerId, null);
        $this->assertIsArray($tenant);
        $lease = $lifecycleRepository->createLease($property->id, $unit->id, (int) $tenant['id'], '2026-01-01', null, 700.0, $chargesProvision, 'validated', $ownerId, null);
        $this->assertIsArray($lease);

        return [$lifecycleRepository, $ownerId, $property->id, $unit->id, (int) $lease['id']];
    }

    private function createRent(RentalLifecycleRepository $repository, int $leaseId, int $propertyId, int $unitId, int $year, int $month, int $ownerId): void
    {
        $rent = $repository->createRent($leaseId, $propertyId, $unitId, $year, $month, sprintf('%04d-%02d-01', $year, $month), 800.0, 'pending', $ownerId, null);
        $this->assertIsArray($rent);
    }

    private function attachSupportingDocument(RentalLifecycleRepository $repository, int $propertyId, int $unitId, int $expenseId, int $ownerId): void
    {
        $sourcePath = $this->tempDir . '/facture-eau.txt';
        file_put_contents($sourcePath, 'facture eau');
        $storage = $this->storage();
        $metadata = $storage->validateUploadedFile([
            'name' => 'facture-eau.txt',
            'tmp_name' => $sourcePath,
            'size' => filesize($sourcePath) ?: 1,
            'error' => UPLOAD_ERR_OK,
            'type' => 'text/plain',
        ]);
        $this->assertIsArray($metadata);
        $documentId = $storage->generateDocumentId();
        $stored = $storage->storeUploadedFile($metadata, $documentId);
        $this->assertIsArray($stored);
        $document = $repository->createDocument(
            $propertyId,
            $unitId,
            null,
            (string) $stored['documentId'],
            (string) $stored['storagePath'],
            (string) $stored['originalName'],
            (string) $stored['extension'],
            (string) $stored['mimeType'],
            (int) $stored['sizeBytes'],
            $ownerId,
            $expenseId
        );
        $this->assertIsArray($document);
    }

    private function service(RentalLifecycleRepository $repository): ChargeRegularizationService
    {
        return new ChargeRegularizationService($repository, $this->storage());
    }

    private function storage(): PrivateDocumentStorage
    {
        return new PrivateDocumentStorage($this->tempDir, 'storage', 'uploads', 'exports');
    }

    private function createPrivateUser(PrivateUserRepository $repository, string $email): int
    {
        $hash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($hash);
        $userId = $repository->create($email, $hash, 'active');
        $this->assertIsInt($userId);

        return $userId;
    }

    private function removeDirectory(string $path): void
    {
        if ($path === '' || !is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($path);
    }
}
