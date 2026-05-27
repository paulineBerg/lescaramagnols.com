<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Service;

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyImportIssue;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import\ClassifiedAgencyDocument;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Parser\AgencyParserResult;

final class AgencyStatementValidationService
{
    /**
     * @return array<int, AgencyImportIssue>
     */
    public function validate(ClassifiedAgencyDocument $classification, AgencyParserResult $result): array
    {
        $issues = [];

        if (!$classification->isKnown()) {
            $issues[] = new AgencyImportIssue(
                'document_not_classified',
                AgencyImportIssue::SEVERITY_ERROR,
                'Document non classe : aucune ligne ne doit alimenter les syntheses.'
            );

            return $issues;
        }

        foreach ($result->statementLines as $line) {
            if ($this->needsFiscalPeriod($line->mappedCategory) && !$this->hasPeriod($line->periodStart, $line->periodEnd, $line->lineDate)) {
                $issues[] = new AgencyImportIssue(
                    'missing_fiscal_period',
                    AgencyImportIssue::SEVERITY_ERROR,
                    'Ligne fiscale sans periode exploitable.',
                    $line->sourcePage
                );
            }

            if ($line->mappedCategory === 'security_deposit') {
                $issues[] = new AgencyImportIssue(
                    'security_deposit_excluded_from_income',
                    AgencyImportIssue::SEVERITY_WARNING,
                    'Depot de garantie a conserver hors revenus imposables.',
                    $line->sourcePage
                );
            }

            if ($line->mappedCategory === 'agency_balance') {
                $issues[] = new AgencyImportIssue(
                    'agency_balance_not_source_line',
                    AgencyImportIssue::SEVERITY_WARNING,
                    'Solde agence technique : ne pas compter sans lignes sources.',
                    $line->sourcePage
                );
            }
        }

        return $issues;
    }

    private function needsFiscalPeriod(string $category): bool
    {
        return in_array($category, [
            'rent_income',
            'charge_provision_income',
            'recoverable_tax_income',
            'recoverable_charge_adjustment',
            'recoverable_utility_charge',
            'agency_management_fee',
            'agency_fee_vat',
            'agency_letting_fee',
            'insurance_unpaid_rent',
            'property_tax_service_fee',
            'works_expense',
            'copro_work_fund',
            'condominium_current_charge',
        ], true);
    }

    private function hasPeriod(?string $periodStart, ?string $periodEnd, ?string $lineDate): bool
    {
        return $this->isSqlDate($periodStart) || $this->isSqlDate($periodEnd) || $this->isSqlDate($lineDate);
    }

    private function isSqlDate(?string $value): bool
    {
        return is_string($value) && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) === 1;
    }
}
