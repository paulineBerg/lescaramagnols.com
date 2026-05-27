<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyDocumentType;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import\AgencyImportPreviewService;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\DocumentTextExtractorInterface;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\ExtractedTextResult;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class AgencyImportPreviewServiceTest extends TestCase
{
    public function testBuildsPreviewFromExtractedAgencyStatementWithoutLeakingSensitiveData(): void
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'agency-preview-');
        $this->assertIsString($sourcePath);
        file_put_contents($sourcePath, 'original document bytes');

        $text = <<<'TEXT'
Relevé de gérance
Numéro de compte       411QUINETJ
Code d'accès : QUINETJULIETTE
IBAN : FR76 1910 6000 1800 0000 0000 087
ASG IMMOBILIER
Période du 01/02/2025 au 28/02/2025 - Fév 2025
IMMEUBLE - Villa CARENA COGOLIN                                                        Quittancé     Recettes      Dépenses
Lot 1 Appartement
AMIROUCHEN Luc
Loyer                                                                               662,87        662,87
Règlement Virement                                                                               548,48
TEXT;

        try {
            $service = new AgencyImportPreviewService(
                $this->textExtractorReturning($text),
                null,
                null,
                null
            );

            $preview = $service->preview($sourcePath, 'releve-gerance-asg.pdf', 'text/plain');

            $this->assertSame(AgencyDocumentType::ASG_MANAGEMENT_STATEMENT, $preview->classification->documentType);
            $this->assertSame('asg-releve-gerance-v1', $preview->classification->parserProfile);
            $this->assertNotNull($preview->parserResult);
            $this->assertSame(hash_file('sha256', $sourcePath), $preview->sha256);
            $this->assertStringContainsString("Code d'accès : [masque]", $preview->maskedTextPreview);
            $this->assertStringContainsString('[iban masque]', $preview->maskedTextPreview);
            $this->assertStringNotContainsString('QUINETJULIETTE', $preview->maskedTextPreview);
            $this->assertCount(2, $preview->parserResult->statementLines);
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
}
