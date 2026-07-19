<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyMemberRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalUnitRepository;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalReceiptService;
use Caramagnols\PrivateApps\Documents\PrivateDocumentStorage;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class RentalReceiptServiceTest extends TestCase
{
    use EditorialSqlTestTrait;

    private string $tempDir = '';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/caramagnols-rental-receipts-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
        $this->removeDirectory($this->tempDir);
    }

    public function testReceiptIsForbiddenForPartialRentAndPartialReceiptIsStoredOutsideWebroot(): void
    {
        [$repository, $ownerId, $propertyId, $unitId, $leaseId] = $this->rentalContext('receipt-partial@example.com');
        $rent = $this->createRent($repository, $leaseId, $propertyId, $unitId, $ownerId, '2026-02-01', 1000.0);
        $payment = $repository->createPayment($leaseId, $propertyId, $unitId, '2026-02-03', 2026, 2, 0.0, 400.0, 'validated', $ownerId, null, (int) $rent['id']);
        $this->assertIsArray($payment);
        $service = $this->service($repository);

        $this->assertNull($service->generateForPayment((int) $payment['id'], [$propertyId], $ownerId, RentalReceiptService::DOCUMENT_RECEIPT));

        $document = $service->generateForPayment((int) $payment['id'], [$propertyId], $ownerId, RentalReceiptService::DOCUMENT_PARTIAL_RECEIPT);
        $this->assertIsArray($document);
        $this->assertSame('partial_receipt', $document['documentType']);
        $this->assertSame('application/pdf', $document['mimeType']);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', (string) $document['sha256Hash']);
        $this->assertGreaterThan(0, (int) $document['sizeBytes']);

        $absolutePath = $service->absolutePath($document);
        $this->assertIsString($absolutePath);
        $this->assertFileExists($absolutePath);
        $this->assertStringStartsWith($this->tempDir, $absolutePath);
        $this->assertFalse(str_starts_with($absolutePath, ROOT_PATH . '/public'));
        $this->assertStringContainsString('Recu partiel de loyer', (string) $service->content($document));
    }

    public function testFullReceiptIsStoredWithImmutableSnapshotAndIsIdempotent(): void
    {
        [$repository, $ownerId, $propertyId, $unitId, $leaseId] = $this->rentalContext('receipt-full@example.com');
        $rent = $this->createRent($repository, $leaseId, $propertyId, $unitId, $ownerId, '2026-03-01', 950.0);
        $payment = $repository->createPayment($leaseId, $propertyId, $unitId, '2026-03-02', 2026, 3, 0.0, 950.0, 'validated', $ownerId, null, (int) $rent['id']);
        $this->assertIsArray($payment);
        $service = $this->service($repository);

        $document = $service->generateForPayment((int) $payment['id'], [$propertyId], $ownerId, RentalReceiptService::DOCUMENT_RECEIPT);
        $this->assertIsArray($document);
        $this->assertSame('receipt', $document['documentType']);
        $this->assertStringContainsString('Quittance de loyer', (string) $service->content($document));
        $this->assertMatchesRegularExpression('/"templateVersion":\s*1/', (string) ($document['snapshotPayload'] ?? ''));
        $this->assertStringContainsString('"balanceDue": "0.00"', (string) ($document['snapshotPayload'] ?? ''));

        $again = $service->generateForPayment((int) $payment['id'], [$propertyId], $ownerId, RentalReceiptService::DOCUMENT_RECEIPT);
        $this->assertIsArray($again);
        $this->assertSame($document['documentId'], $again['documentId']);
        $this->assertSame($document['sha256Hash'], $again['sha256Hash']);
    }

    public function testGeneratedDocumentIsRefusedWithoutPropertyRight(): void
    {
        [$repository, $ownerId, $propertyId, $unitId, $leaseId] = $this->rentalContext('receipt-forbidden@example.com');
        $rent = $this->createRent($repository, $leaseId, $propertyId, $unitId, $ownerId, '2026-04-01', 700.0);
        $payment = $repository->createPayment($leaseId, $propertyId, $unitId, '2026-04-02', 2026, 4, 0.0, 700.0, 'validated', $ownerId, null, (int) $rent['id']);
        $this->assertIsArray($payment);

        $this->assertNull($this->service($repository)->generateForPayment((int) $payment['id'], [99999], $ownerId, RentalReceiptService::DOCUMENT_RECEIPT));
    }

    /**
     * @return array{0:RentalLifecycleRepository, 1:int, 2:int, 3:int, 4:int}
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

        $property = $propertyRepository->create($ownerId, 'Maison quittance', '22 rue du Recu', 'maison', 'indivision', 'active');
        $this->assertNotNull($property);
        $this->assertNotNull($memberRepository->create($property->id, $ownerId, 'owner', $ownerId));
        $unit = $unitRepository->create($property->id, 'Lot quittance', 41.0, false, 'available', null, $ownerId);
        $this->assertNotNull($unit);
        $tenant = $lifecycleRepository->createTenant($property->id, $unit->id, 'Locataire quittance', 'quittance@example.com', null, 'validated', $ownerId, null);
        $this->assertIsArray($tenant);
        $lease = $lifecycleRepository->createLease($property->id, $unit->id, (int) $tenant['id'], '2026-01-01', null, 650.0, 50.0, 'validated', $ownerId, null);
        $this->assertIsArray($lease);

        return [$lifecycleRepository, $ownerId, $property->id, $unit->id, (int) $lease['id']];
    }

    /**
     * @return array<string, mixed>
     */
    private function createRent(
        RentalLifecycleRepository $repository,
        int $leaseId,
        int $propertyId,
        int $unitId,
        int $ownerId,
        string $dueDate,
        float $amount
    ): array {
        $rent = $repository->createRent($leaseId, $propertyId, $unitId, (int) substr($dueDate, 0, 4), (int) substr($dueDate, 5, 2), $dueDate, $amount, 'pending', $ownerId, null);
        $this->assertIsArray($rent);

        return $rent;
    }

    private function service(RentalLifecycleRepository $repository): RentalReceiptService
    {
        return new RentalReceiptService(
            $repository,
            new PrivateDocumentStorage($this->tempDir, 'storage', 'uploads', 'exports')
        );
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
            if ($file instanceof SplFileInfo && $file->isDir()) {
                @rmdir($file->getPathname());
                continue;
            }
            if ($file instanceof SplFileInfo) {
                @unlink($file->getPathname());
            }
        }
        @rmdir($path);
    }
}
