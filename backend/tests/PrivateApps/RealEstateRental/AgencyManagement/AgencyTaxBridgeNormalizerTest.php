<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import\AgencyImportPreviewService;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\DocumentTextExtractorInterface;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\ExtractedTextResult;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Repository\AgencyImportRepository;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Service\AgencyTaxBridgeNormalizer;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class AgencyTaxBridgeNormalizerTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testOnlyValidatedAgencyLinesFeedTaxBridge(): void
    {
        $repository = new AgencyImportRepository($this->editorialSqlDatabase());
        $batch = $repository->createBatch(7, 'ASG IMMOBILIER', '/tmp/agence', 1);
        $this->assertNotNull($batch);

        $sourcePath = tempnam(sys_get_temp_dir(), 'agency-tax-bridge-');
        $this->assertIsString($sourcePath);
        file_put_contents($sourcePath, 'agency tax bridge bytes');

        try {
            $preview = (new AgencyImportPreviewService($this->textExtractorReturning($this->asgText())))->preview(
                $sourcePath,
                'releve-gerance-fiscal.pdf',
                'text/plain'
            );
            $document = $repository->persistPreview($batch->id, $preview, 'private-doc-tax', 'ASG IMMOBILIER');
            $this->assertNotNull($document);
            $this->assertTrue($repository->updateStatementPropertyForDocument(7, $document->id, 9001));

            $statement = $repository->findStatementByImportedDocumentId($document->id);
            $this->assertNotNull($statement);
            $lines = $repository->listStatementLines($statement->id);
            $this->assertCount(3, $lines);

            $this->assertNotNull($repository->reviewStatementLine(7, $lines[0]->id, 'validate'));
            $this->assertNotNull($repository->reviewStatementLine(7, $lines[1]->id, 'validate'));
            $this->assertNotNull($repository->reviewStatementLine(7, $lines[2]->id, 'ignore'));

            $normalizer = new AgencyTaxBridgeNormalizer($repository);
            $income = $normalizer->annualRentalIncome(2026, [9001]);
            $expenses = $normalizer->annualDeductibleExpenses(2026, [9001]);

            $this->assertSame(700.0, $income['rentDue']);
            $this->assertSame(700.0, $income['rentPaid']);
            $this->assertSame(0.0, $income['unpaidRent']);
            $this->assertSame(1, $income['validatedPaymentCount']);
            $this->assertSame('rental_agency_statement_lines', $income['sourceRows'][0]['table'] ?? null);

            $this->assertSame(0.0, $expenses['recoverableExpenses']);
            $this->assertSame(80.0, $expenses['deductibleCandidateExpenses']);
            $this->assertSame(0.0, $expenses['nonDeductibleExpenses']);
            $this->assertSame(1, $expenses['validatedExpenseCount']);
            $this->assertSame('agency_management_fee', $expenses['sourceRows'][0]['category'] ?? null);
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
Période du 01/03/2026 au 31/03/2026 - Mar 2026
IMMEUBLE - Villa CARENA COGOLIN                                                        Quittancé     Recettes      Dépenses
Lot 1 Appartement
AMIROUCHEN Luc
Loyer                                                                               700,00        700,00
Honoraires                                                                          80,00
Règlement Virement                                                                               620,00
TEXT;
    }
}
