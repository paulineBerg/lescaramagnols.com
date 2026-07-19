<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyMemberRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalUnitRepository;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalAnnualSummaryService;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalExportService;
use Caramagnols\PrivateApps\Documents\PrivateDocumentStorage;
use Caramagnols\PrivatePortal\Operations\PrivateBackupService;
use Caramagnols\PrivatePortal\Operations\PrivateDataProtectionService;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class RentalExportServiceTest extends TestCase
{
    use EditorialSqlTestTrait;

    private string $tempDir = '';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/caramagnols-rental-exports-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
        $this->removeDirectory($this->tempDir);
    }

    public function testCsvAndPdfExportsAreStoredTemporarilyOutsideWebrootAndLogged(): void
    {
        [$repository, $ownerId, $propertyId, $unitId, $leaseId, $tenantId] = $this->rentalContext('export-main@example.com');
        $this->createValidatedRentPaymentAndExpense($repository, $ownerId, $propertyId, $unitId, $leaseId);
        $service = $this->service($repository);

        $rentsCsv = $service->create($ownerId, 2026, RentalExportService::KIND_RENTS, [$propertyId]);
        $this->assertSuccessfulExport($rentsCsv, 'csv');
        $this->assertStringContainsString('loyer_attendu', (string) $rentsCsv['content']);
        $this->assertStringContainsString('Locataire export', (string) $rentsCsv['content']);

        $expensesCsv = $service->create($ownerId, 2026, RentalExportService::KIND_EXPENSES, [$propertyId]);
        $this->assertSuccessfulExport($expensesCsv, 'csv');
        $this->assertStringContainsString('candidate_deductible', (string) $expensesCsv['content']);
        $this->assertStringContainsString('Entretien export', (string) $expensesCsv['content']);

        $propertyPdf = $service->create($ownerId, 2026, RentalExportService::KIND_PROPERTY_ANNUAL, [$propertyId], $propertyId);
        $this->assertSuccessfulExport($propertyPdf, 'pdf');
        $this->assertStringContainsString('Synthese annuelle par bien 2026', (string) $propertyPdf['content']);

        $tenantPdf = $service->create($ownerId, 2026, RentalExportService::KIND_TENANT_RECAP, [$propertyId], null, $tenantId);
        $this->assertSuccessfulExport($tenantPdf, 'pdf');
        $this->assertStringContainsString('Recapitulatif locataire 2026', (string) $tenantPdf['content']);

        $this->assertSame(1, $repository->countExportLogs($ownerId, 2026, 'csv', RentalExportService::KIND_RENTS));
        $this->assertSame(1, $repository->countExportLogs($ownerId, 2026, 'csv', RentalExportService::KIND_EXPENSES));
        $this->assertSame(1, $repository->countExportLogs($ownerId, 2026, 'pdf', RentalExportService::KIND_PROPERTY_ANNUAL));
        $this->assertSame(1, $repository->countExportLogs($ownerId, 2026, 'pdf', RentalExportService::KIND_TENANT_RECAP));

        $exportAccount = (new PrivateDataProtectionService($this->editorialSqlDatabase()))->exportAccount($ownerId);
        $this->assertCount(4, $exportAccount['rentalExportLogs'] ?? []);
    }

    public function testDocumentsZipDoesNotExposeServerPathsAndBackupContainsRentalTablesAndFiles(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('Extension ZipArchive requise pour valider les exports ZIP.');
        }

        [$repository, $ownerId, $propertyId, $unitId] = $this->rentalContext('export-zip@example.com');
        $this->attachRentalDocument($repository, $propertyId, $unitId, $ownerId);
        $service = $this->service($repository);

        $zipExport = $service->create($ownerId, 2026, RentalExportService::KIND_PROPERTY_DOCUMENTS, [$propertyId], $propertyId);
        $this->assertSuccessfulExport($zipExport, 'zip');

        $zip = new ZipArchive();
        $this->assertTrue($zip->open((string) $zipExport['path']));
        $this->assertNotFalse($zip->locateName('manifest.json'));
        $manifest = (string) $zip->getFromName('manifest.json');
        $this->assertStringContainsString('bail-export.txt', $manifest);
        $this->assertStringNotContainsString($this->tempDir, $manifest);
        $this->assertStringNotContainsString(ROOT_PATH, $manifest);
        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $name = (string) $zip->getNameIndex($index);
            $this->assertStringNotContainsString($this->tempDir, $name);
            $this->assertStringNotContainsString('uploads/', $name);
        }
        $zip->close();

        $this->assertSame(1, $repository->countExportLogs($ownerId, 2026, 'zip', RentalExportService::KIND_PROPERTY_DOCUMENTS));

        $backup = (new PrivateBackupService($this->editorialSqlDatabase()))->createBackup($this->tempDir . '/backup', $this->tempDir);
        $this->assertTrue((bool) ($backup['success'] ?? false));
        $payload = json_decode((string) file_get_contents((string) $backup['path']), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('rental_export_logs', $payload['tables'] ?? []);
        $this->assertArrayHasKey('rental_documents', $payload['tables'] ?? []);
        $files = is_array($payload['files'] ?? null) ? $payload['files'] : [];
        $this->assertNotEmpty($files);
        $this->assertIsString($backup['archivePath'] ?? null);
        $backupZip = new ZipArchive();
        $this->assertTrue($backupZip->open((string) $backup['archivePath']));
        $this->assertNotFalse($backupZip->locateName('backup.json'));
        $this->assertGreaterThanOrEqual(1, $backupZip->numFiles);
        $backupZip->close();
    }

    public function testExportIsRefusedWithoutPropertyPermissionAndPurgeRemovesLogs(): void
    {
        [$repository, $ownerId, $propertyId, $unitId, $leaseId] = $this->rentalContext('export-forbidden@example.com');
        $this->createValidatedRentPaymentAndExpense($repository, $ownerId, $propertyId, $unitId, $leaseId);
        $service = $this->service($repository);

        $forbidden = $service->create($ownerId, 2026, RentalExportService::KIND_PROPERTY_ANNUAL, [99999], $propertyId);
        $this->assertFalse((bool) ($forbidden['success'] ?? true));
        $this->assertSame('forbidden', $forbidden['error'] ?? null);
        $this->assertSame(0, $repository->countExportLogs($ownerId, 2026, 'pdf', RentalExportService::KIND_PROPERTY_ANNUAL));

        $created = $service->create($ownerId, 2026, RentalExportService::KIND_RENTS, [$propertyId]);
        $this->assertTrue((bool) ($created['success'] ?? false));
        $this->assertSame(1, $repository->countExportLogs($ownerId, 2026, 'csv', RentalExportService::KIND_RENTS));

        $protection = new PrivateDataProtectionService($this->editorialSqlDatabase());
        $this->assertTrue($protection->redactAccountForDeletion($ownerId, $ownerId, 'phpunit export purge'));
        $this->assertSame(0, $repository->countExportLogs($ownerId, 2026, 'csv', RentalExportService::KIND_RENTS));
    }

    /**
     * @return array{0:RentalLifecycleRepository, 1:int, 2:int, 3:int, 4:int, 5:int}
     */
    private function rentalContext(string $email): array
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);
        $ownerId = $this->createPrivateUser($userRepository, $email);

        $property = $propertyRepository->create($ownerId, 'Maison export', '7 rue Export', 'maison', 'indivision', 'active');
        $this->assertNotNull($property);
        $this->assertNotNull($memberRepository->create($property->id, $ownerId, 'owner', $ownerId));
        $unit = $unitRepository->create($property->id, 'Lot export', 42.0, false, 'available', null, $ownerId);
        $this->assertNotNull($unit);
        $tenant = $lifecycleRepository->createTenant($property->id, $unit->id, 'Locataire export', 'export@example.com', null, 'validated', $ownerId, null);
        $this->assertIsArray($tenant);
        $lease = $lifecycleRepository->createLease($property->id, $unit->id, (int) $tenant['id'], '2026-01-01', null, 900.0, 80.0, 'validated', $ownerId, null);
        $this->assertIsArray($lease);

        return [$lifecycleRepository, $ownerId, $property->id, $unit->id, (int) $lease['id'], (int) $tenant['id']];
    }

    private function createValidatedRentPaymentAndExpense(
        RentalLifecycleRepository $repository,
        int $ownerId,
        int $propertyId,
        int $unitId,
        int $leaseId
    ): void {
        $rent = $repository->createRent($leaseId, $propertyId, $unitId, 2026, 1, '2026-01-01', 980.0, 'paid', $ownerId, null);
        $this->assertIsArray($rent);
        $this->assertNotNull($repository->createPayment($leaseId, $propertyId, $unitId, '2026-01-05', 2026, 1, 0.0, 980.0, 'validated', $ownerId, null, (int) $rent['id']));
        $this->assertNotNull($repository->createExpense($propertyId, $unitId, '2026-02-10', 'Entretien export', 120.0, true, true, 'validated', $ownerId, null, 'entretien', 2026));
    }

    private function attachRentalDocument(RentalLifecycleRepository $repository, int $propertyId, int $unitId, int $ownerId): void
    {
        $sourcePath = $this->tempDir . '/bail-export.txt';
        file_put_contents($sourcePath, 'document locatif export');
        $storage = $this->storage();
        $metadata = $storage->validateUploadedFile([
            'name' => 'bail-export.txt',
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
            $ownerId
        );
        $this->assertIsArray($document);
    }

    private function assertSuccessfulExport(array $export, string $format): void
    {
        $this->assertTrue((bool) ($export['success'] ?? false), (string) ($export['error'] ?? ''));
        $this->assertSame($format, $export['format'] ?? null);
        $this->assertIsString($export['path'] ?? null);
        $this->assertFileExists((string) $export['path']);
        $this->assertStringStartsWith($this->tempDir, (string) $export['path']);
        $this->assertFalse(str_starts_with((string) $export['path'], ROOT_PATH . '/public'));
        $this->assertGreaterThan(0, (int) ($export['sizeBytes'] ?? 0));
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', (string) ($export['sha256'] ?? ''));
    }

    private function service(RentalLifecycleRepository $repository): RentalExportService
    {
        return new RentalExportService(
            $repository,
            new RentalAnnualSummaryService($repository),
            $this->storage()
        );
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
            if (!$file instanceof SplFileInfo) {
                continue;
            }
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($path);
    }
}
