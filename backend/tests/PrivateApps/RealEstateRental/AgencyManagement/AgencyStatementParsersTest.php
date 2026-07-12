<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyDocumentType;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import\ClassifiedAgencyDocument;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Parser\AsgManagementStatementParser;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Parser\IcsManagementReportParser;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class AgencyStatementParsersTest extends TestCase
{
    public function testAsgParserExtractsStatementFieldsAndLines(): void
    {
        $text = <<<'TEXT'
Relevé de gérance
Numéro de compte       411QUINETJ
Libellé                QUINET Juliette
Le 04/03/2025
ASG IMMOBILIER
Période du 01/02/2025 au 28/02/2025 - Fév 2025
IMMEUBLE - Villa CARENA COGOLIN                                                        Quittancé     Recettes      Dépenses
Lot 1 Appartement
AMIROUCHEN Luc
Période Fév 2025
Loyer                                                                               662,87        662,87
Taxe ordures ménagères                                                             -114,39       -114,39
Total lot                                                                          548,48       662,87
Dépenses de l'immeuble
Honoraires de gestion Fév 2025 (2132,19 x 6%)                                                                127,93
TVA sur Honoraires de gestion Fév 2025                                                                        25,59
ASSURANCE INSURED Fév 2025 (1469,32 x 3,5%)                                                                   51,43
Règlement Virement                                                                               1 639,24
TEXT;

        $parser = new AsgManagementStatementParser();
        $result = $parser->parse($text);
        $lines = array_map(static fn ($line): array => $line->toArray(), $result->statementLines);
        $categories = array_column($lines, 'mappedCategory');

        $this->assertSame(AgencyDocumentType::ASG_MANAGEMENT_STATEMENT, $result->documentType);
        $this->assertSame('411QUINETJ', $result->extractedFields['ownerAccountReference'] ?? null);
        $this->assertSame('2025-02-01', $result->extractedFields['periodStart'] ?? null);
        $this->assertContains('rent_income', $categories);
        $this->assertContains('recoverable_tax_income', $categories);
        $this->assertContains('agency_management_fee', $categories);
        $this->assertContains('agency_fee_vat', $categories);
        $this->assertContains('insurance_unpaid_rent', $categories);
        $this->assertContains('owner_transfer', $categories);
        $this->assertContains('Villa CARENA COGOLIN', array_column($lines, 'propertyLabel'));
    }

    public function testAsgParserKeepsTenantContextAndSplitsBuildingExpensesByLotTotals(): void
    {
        $text = <<<'TEXT'
Relevé de gérance
Numéro de compte       411BERGONP
Libellé                BERGON Gérard
Le 05/02/2026
ASG IMMOBILIER
Période du 01/01/2026 au 31/01/2026 - Jan 2026
IMMEUBLE - Les Caramagnols COGOLIN                                                  Quittancé      Recettes      Dépenses
Lot 1 Appartement
EVE Hervé (Solde débiteur : 14,82)
Période Jan 2026
Loyer                                                                         857,41        842,59
Total lot        857,41        850,00
Lot 3 Appartement
FOURNAJOUX Delphine
Période Jan 2026
Loyer                                                                            900,00        900,00
Total lot        900,00        900,00
Dépenses de l'immeuble
Honoraires de gestion Jan 2026 (850 x 7%)                                                                   59,50
TVA sur Honoraires de gestion Jan 2026                                                                      11,90
Honoraires de gestion Jan 2026 (900 x 7%)                                                                   63,00
TVA sur Honoraires de gestion Jan 2026                                                                      12,60
ASSURANCE MILA Jan 2026 (857,41 x 3,5%)                                                                     30,01
TEXT;

        $parser = new AsgManagementStatementParser();
        $result = $parser->parse($text);
        $lines = array_map(static fn ($line): array => $line->toArray(), $result->statementLines);

        $this->assertCount(7, $lines);
        $this->assertSame('EVE Hervé', $lines[0]['tenantName'] ?? null);
        $this->assertSame('1 Appartement', $lines[0]['unitLabel'] ?? null);
        $this->assertSame('FOURNAJOUX Delphine', $lines[1]['tenantName'] ?? null);
        $this->assertSame('3 Appartement', $lines[1]['unitLabel'] ?? null);

        $this->assertSame('Honoraires de gestion Jan 2026 (850 x 7%)', $lines[2]['rawLabel'] ?? null);
        $this->assertSame('1 Appartement', $lines[2]['unitLabel'] ?? null);
        $this->assertSame('EVE Hervé', $lines[2]['tenantName'] ?? null);
        $this->assertSame('1 Appartement', $lines[3]['unitLabel'] ?? null);
        $this->assertSame('EVE Hervé', $lines[3]['tenantName'] ?? null);
        $this->assertSame('3 Appartement', $lines[4]['unitLabel'] ?? null);
        $this->assertSame('FOURNAJOUX Delphine', $lines[4]['tenantName'] ?? null);
        $this->assertSame('3 Appartement', $lines[5]['unitLabel'] ?? null);
        $this->assertSame('FOURNAJOUX Delphine', $lines[5]['tenantName'] ?? null);
        $this->assertSame('1 Appartement', $lines[6]['unitLabel'] ?? null);
        $this->assertSame('EVE Hervé', $lines[6]['tenantName'] ?? null);
    }

    public function testIcsParserExtractsManagementReportLines(): void
    {
        $text = <<<'TEXT'
COMPTE PERSONNEL 62530000
COMPTE RENDU DE GESTION
A AUBAGNE, le 27/02/2026
MON PARTENAIRE GESTION
Powered by ICS
COMPTE IMMEUBLE 62535697
18, rue Cavaillon
83170 BRIGNOLES
SITUATION ET LIBELLES          NOMS/PERIODE                     APPELE          REGLE           SOMMES DUES      REGLEMENTS
Lot 0001 Appart. T2
COULMIER Catherine
LOYER                        Du 01.02.26 Au 28.02.26                    445.00          445.00
PROVISIONS                   Du 01.02.26 Au 28.02.26                     25.00           25.00
TOTAL DES REGLEMENTS LOCATAIRES                                                               470.00
Prime GLI Février 2026                                                       14.34
Honoraires H.T.                                                               23.50
TVA/Honoraires ( 20.00 % )                                                     4.70
Solde créditeur en Euros au 27.02.2026                                      423.56
TEXT;

        $classified = new ClassifiedAgencyDocument(
            AgencyDocumentType::ICS_MANAGEMENT_REPORT,
            'ics-compte-rendu-gestion-v1',
            1.0
        );
        $parser = new IcsManagementReportParser();

        $this->assertTrue($parser->supports($classified));
        $result = $parser->parse($text);
        $lines = array_map(static fn ($line): array => $line->toArray(), $result->statementLines);
        $categories = array_column($lines, 'mappedCategory');

        $this->assertSame('62530000', $result->extractedFields['personalAccountReference'] ?? null);
        $this->assertSame('62535697', $result->extractedFields['propertyAccountReference'] ?? null);
        $this->assertSame('2026-02-27', $result->extractedFields['statementDate'] ?? null);
        $this->assertContains('rent_income', $categories);
        $this->assertContains('charge_provision_income', $categories);
        $this->assertContains('insurance_unpaid_rent', $categories);
        $this->assertContains('agency_management_fee', $categories);
        $this->assertContains('agency_fee_vat', $categories);
        $this->assertContains('agency_balance', $categories);
    }
}
