<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyDocumentType;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyStatementLineDraft;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import\ClassifiedAgencyDocument;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Parser\AgencyParserResult;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Service\AgencyStatementValidationService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class AgencyStatementValidationServiceTest extends TestCase
{
    public function testRefusesFiscalLineWithoutPeriodAndFlagsNonFiscalMovements(): void
    {
        $result = new AgencyParserResult(
            AgencyDocumentType::ASG_MANAGEMENT_STATEMENT,
            'asg-releve-gerance-v1',
            0.9,
            [],
            [
                new AgencyStatementLineDraft('Loyer', 'rent_income', 600.0, null, 600.0),
                new AgencyStatementLineDraft('Depot garantie', 'security_deposit', 600.0, null, 600.0),
                new AgencyStatementLineDraft('Solde crediteur', 'agency_balance', 50.0, null, 50.0),
                new AgencyStatementLineDraft('Ligne inconnue', 'other', 12.0, null, 12.0),
            ]
        );

        $issues = (new AgencyStatementValidationService())->validate(
            new ClassifiedAgencyDocument(AgencyDocumentType::ASG_MANAGEMENT_STATEMENT, 'asg-releve-gerance-v1', 1.0),
            $result
        );
        $types = array_map(static fn ($issue): string => $issue->type, $issues);

        $this->assertContains('missing_fiscal_period', $types);
        $this->assertContains('security_deposit_excluded_from_income', $types);
        $this->assertContains('agency_balance_not_source_line', $types);
        $this->assertContains('unclassified_statement_line', $types);
    }

    public function testAcceptsFiscalLineWithStatementPeriod(): void
    {
        $result = new AgencyParserResult(
            AgencyDocumentType::ASG_MANAGEMENT_STATEMENT,
            'asg-releve-gerance-v1',
            0.9,
            [],
            [
                new AgencyStatementLineDraft(
                    'Loyer',
                    'rent_income',
                    600.0,
                    null,
                    600.0,
                    null,
                    600.0,
                    null,
                    '2026-02-01',
                    '2026-02-28'
                ),
            ]
        );

        $issues = (new AgencyStatementValidationService())->validate(
            new ClassifiedAgencyDocument(AgencyDocumentType::ASG_MANAGEMENT_STATEMENT, 'asg-releve-gerance-v1', 1.0),
            $result
        );

        $this->assertSame([], $issues);
    }
}
