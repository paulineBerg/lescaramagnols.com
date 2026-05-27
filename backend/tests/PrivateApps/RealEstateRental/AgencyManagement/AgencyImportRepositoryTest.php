<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyDocumentType;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import\AgencyImportPreviewService;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\DocumentTextExtractorInterface;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\ExtractedTextResult;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Repository\AgencyImportRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class AgencyImportRepositoryTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testPersistsAgencyImportPreviewWithStatementLinesAndBlocksDuplicateSha(): void
    {
        $repository = new AgencyImportRepository($this->editorialSqlDatabase());
        $batch = $repository->createBatch(1, 'ASG IMMOBILIER', '/tmp/agence', 1);
        $this->assertNotNull($batch);

        $sourcePath = tempnam(sys_get_temp_dir(), 'agency-import-repo-');
        $this->assertIsString($sourcePath);
        file_put_contents($sourcePath, 'same original pdf bytes');

        try {
            $preview = (new AgencyImportPreviewService($this->textExtractorReturning($this->asgText())))->preview(
                $sourcePath,
                'releve-gerance.pdf',
                'text/plain'
            );

            $document = $repository->persistPreview($batch->id, $preview, 'private-doc-001', 'ASG IMMOBILIER');
            $this->assertNotNull($document);
            $this->assertSame(AgencyDocumentType::ASG_MANAGEMENT_STATEMENT, $document->detectedDocumentType);
            $this->assertSame('asg-releve-gerance-v1', $document->parserProfile);
            $this->assertTrue($document->containsSensitiveData);

            $statement = $repository->findStatementByImportedDocumentId($document->id);
            $this->assertNotNull($statement);
            $this->assertSame('2025-02-01', $statement->statementPeriodStart);
            $this->assertSame('411QUINETJ', $statement->ownerAccountReference);

            $lines = $repository->listStatementLines($statement->id);
            $this->assertCount(2, $lines);
            $this->assertSame(['rent_income', 'owner_transfer'], array_map(
                static fn ($line): string => $line->mappedCategory,
                $lines
            ));

            $this->assertNotNull($repository->findImportedDocumentBySha256((string) $preview->sha256));
            $this->assertNull($repository->persistPreview($batch->id, $preview, 'private-doc-duplicate', 'ASG IMMOBILIER'));

            $this->assertTrue($repository->updateStatementPropertyForDocument(1, $document->id, 42));
            $reviewDocument = $repository->reviewDocumentForUser(1, $document->id);
            $this->assertIsArray($reviewDocument);
            $this->assertSame(42, $reviewDocument['rentalPropertyId'] ?? null);
            $this->assertCount(2, $reviewDocument['lines'] ?? []);

            $corrected = $repository->reviewStatementLine(1, $lines[1]->id, 'correct', [
                'mapped_category' => 'agency_management_fee',
                'period_start' => '2025-02-01',
                'period_end' => '2025-02-28',
                'amount' => '24,50',
                'debit_amount' => '24,50',
                'credit_amount' => '',
            ]);
            $this->assertNotNull($corrected);
            $this->assertSame('agency_management_fee', $corrected->mappedCategory);
            $this->assertSame('review', $corrected->mappingStatus);
            $this->assertSame(24.5, $corrected->debitAmount);

            $validated = $repository->reviewStatementLine(1, $lines[0]->id, 'validate');
            $ignored = $repository->reviewStatementLine(1, $lines[1]->id, 'ignore');
            $this->assertNotNull($validated);
            $this->assertNotNull($ignored);
            $this->assertSame('validated', $validated->mappingStatus);
            $this->assertSame('ignored', $ignored->mappingStatus);

            $fiscalLines = $repository->listValidatedFiscalLines(2025, [42]);
            $this->assertCount(1, $fiscalLines);
            $this->assertSame('rent_income', $fiscalLines[0]['mapped_category'] ?? null);
            $this->assertSame(42, (int) ($fiscalLines[0]['rental_property_id'] ?? 0));

            $reviewedDocument = $repository->reviewDocumentForUser(1, $document->id);
            $this->assertIsArray($reviewedDocument);
            $this->assertSame('validated', $reviewedDocument['reviewStatus'] ?? null);
        } finally {
            @unlink($sourcePath);
        }
    }

    private function textExtractorReturning(string $text): DocumentTextExtractorInterface
    {
        return new class ($text) implements DocumentTextExtractorInterface {
            public function __construct(private readonly string $text)
            {
            }

            public function supports(string $path, string $mimeType): bool
            {
                return is_file($path) && $mimeType === 'text/plain';
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
Code d'accès : QUINETJULIETTE
ASG IMMOBILIER
Période du 01/02/2025 au 28/02/2025 - Fév 2025
IMMEUBLE - Villa CARENA COGOLIN                                                        Quittancé     Recettes      Dépenses
Lot 1 Appartement
AMIROUCHEN Luc
Loyer                                                                               662,87        662,87
Règlement Virement                                                                               548,48
TEXT;
    }
}
