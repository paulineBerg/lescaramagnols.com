<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import\AgencyImportPreviewService;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import\AgencyImportService;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyDocumentType;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\DocumentTextExtractorInterface;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\ExtractedTextResult;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Repository\AgencyImportRepository;
use Caramagnols\PrivatePortal\Documents\PrivateDocumentScanner;
use Caramagnols\PrivatePortal\Documents\PrivateDocumentStorage;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class AgencyImportServiceTest extends TestCase
{
    use EditorialSqlTestTrait;

    private string $storageRoot = '';

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
        $this->removeStorageRoot();
    }

    public function testImportsUploadedAgencyDocumentAndRejectsDuplicateSha(): void
    {
        $repository = new AgencyImportRepository($this->editorialSqlDatabase());
        $service = new AgencyImportService(
            $repository,
            $this->storage(),
            new AgencyImportPreviewService($this->textExtractorReturning($this->asgText()))
        );

        $uploaded = $this->uploadedFile('releve-agence.txt', 'original agency statement bytes');
        $first = $service->importUploadedFile(1, $uploaded, 'ASG IMMOBILIER');
        $this->assertTrue($first->isImported());
        $this->assertNotNull($first->document);
        $this->assertSame('review', $first->document->reviewStatus);
        $this->assertNotNull($first->document->storagePath);
        $this->assertFileExists((string) $this->storage()->absolutePath((string) $first->document->storagePath));
        $this->assertNotNull($repository->findImportedDocumentBySha256($first->document->sha256));

        $duplicateFile = $this->uploadedFile('releve-agence-copie.txt', 'original agency statement bytes');
        $duplicate = $service->importUploadedFile(1, $duplicateFile, 'ASG IMMOBILIER');
        $this->assertSame('duplicate', $duplicate->status);
        $this->assertNotNull($duplicate->batch);
        $this->assertSame(1, $duplicate->batch->duplicateFileCount);

        @unlink((string) $uploaded['tmp_name']);
        @unlink((string) $duplicateFile['tmp_name']);
    }

    public function testIgnoresWindowsZoneIdentifierSidecar(): void
    {
        $service = new AgencyImportService(
            new AgencyImportRepository($this->editorialSqlDatabase()),
            $this->storage(),
            new AgencyImportPreviewService($this->textExtractorReturning('ignored'))
        );

        $uploaded = $this->uploadedFile('releve.pdf:Zone.Identifier', '[ZoneTransfer]');
        $result = $service->importUploadedFile(1, $uploaded, 'ASG IMMOBILIER');

        $this->assertSame('ignored', $result->status);
        $this->assertNotNull($result->batch);
        $this->assertSame(1, $result->batch->ignoredFileCount);

        @unlink((string) $uploaded['tmp_name']);
    }

    public function testManualDocumentTypeChoiceIsStoredOnImport(): void
    {
        $repository = new AgencyImportRepository($this->editorialSqlDatabase());
        $service = new AgencyImportService(
            $repository,
            $this->storage(),
            new AgencyImportPreviewService($this->textExtractorReturning($this->asgText()))
        );

        $uploaded = $this->uploadedFile('quittance-agence.txt', 'manual rent receipt bytes');
        $result = $service->importUploadedFile(
            1,
            $uploaded,
            'ASG IMMOBILIER',
            null,
            AgencyDocumentType::RENT_RECEIPT
        );

        $this->assertTrue($result->isImported());
        $this->assertNotNull($result->document);
        $this->assertSame(AgencyDocumentType::RENT_RECEIPT, $result->document->detectedDocumentType);

        @unlink((string) $uploaded['tmp_name']);
    }

    public function testBlocksAgencyImportWhenDocumentScannerRefusesFile(): void
    {
        $repository = new AgencyImportRepository($this->editorialSqlDatabase());
        $storage = $this->storage(new PrivateDocumentScanner(PHP_BINARY . ' -r exit(1); {file}', 5));
        $service = new AgencyImportService(
            $repository,
            $storage,
            new AgencyImportPreviewService($this->textExtractorReturning($this->asgText()))
        );

        $uploaded = $this->uploadedFile('releve-agence.txt', 'infected agency statement bytes');
        $sha256 = hash('sha256', 'infected agency statement bytes');

        $result = $service->importUploadedFile(1, $uploaded, 'ASG IMMOBILIER');

        $this->assertSame('failed', $result->status);
        $this->assertSame('scan_infected', $result->error);
        $this->assertNotNull($result->batch);
        $this->assertNull($repository->findImportedDocumentBySha256($sha256));
        $this->assertSame([], $repository->listRecentDocumentsForUser(1, 10));
        $this->assertSame(0, $this->countStoredFiles());

        @unlink((string) $uploaded['tmp_name']);
    }

    /**
     * @return array{name:string,tmp_name:string,size:int,error:int,type:string}
     */
    private function uploadedFile(string $name, string $content): array
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'agency-upload-');
        $this->assertIsString($tmpPath);
        file_put_contents($tmpPath, $content);

        return [
            'name' => $name,
            'tmp_name' => $tmpPath,
            'size' => strlen($content),
            'error' => UPLOAD_ERR_OK,
            'type' => 'text/plain',
        ];
    }

    private function storage(?PrivateDocumentScanner $scanner = null): PrivateDocumentStorage
    {
        if ($this->storageRoot === '') {
            $this->storageRoot = sys_get_temp_dir() . '/agency-import-storage-' . bin2hex(random_bytes(6));
        }

        return new PrivateDocumentStorage(
            $this->storageRoot,
            'storage',
            'uploads',
            'exports',
            1024 * 1024,
            ['txt', 'pdf'],
            ['text/plain', 'application/pdf'],
            null,
            0700,
            0600,
            $scanner
        );
    }

    private function textExtractorReturning(string $text): DocumentTextExtractorInterface
    {
        return new class ($text) implements DocumentTextExtractorInterface {
            public function __construct(private readonly string $text)
            {
            }

            public function supports(string $path, string $mimeType): bool
            {
                return is_file($path) && in_array($mimeType, ['text/plain', 'application/pdf'], true);
            }

            public function extract(string $path): ExtractedTextResult
            {
                return new ExtractedTextResult(ExtractedTextResult::STATUS_EXTRACTED, $this->text, 0, '');
            }
        };
    }

    private function asgText(): string
    {
        return <<<'TEXT'
Relevé de gérance
Numéro de compte       411QUINETJ
ASG IMMOBILIER
Période du 01/02/2025 au 28/02/2025 - Fév 2025
IMMEUBLE - Villa CARENA COGOLIN                                                        Quittancé     Recettes      Dépenses
Lot 1 Appartement
AMIROUCHEN Luc
Loyer                                                                               662,87        662,87
TEXT;
    }

    private function removeStorageRoot(): void
    {
        if ($this->storageRoot === '' || !is_dir($this->storageRoot)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->storageRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $path) {
            if ($path->isDir()) {
                @rmdir($path->getPathname());
            } else {
                @unlink($path->getPathname());
            }
        }
        @rmdir($this->storageRoot);
        $this->storageRoot = '';
    }

    private function countStoredFiles(): int
    {
        if ($this->storageRoot === '' || !is_dir($this->storageRoot)) {
            return 0;
        }

        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->storageRoot, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $path) {
            if ($path->isFile()) {
                ++$count;
            }
        }

        return $count;
    }
}
