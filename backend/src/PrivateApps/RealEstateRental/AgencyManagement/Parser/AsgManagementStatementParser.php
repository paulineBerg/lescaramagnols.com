<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Parser;

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyDocumentType;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyImportIssue;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyStatementLineDraft;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import\ClassifiedAgencyDocument;

final class AsgManagementStatementParser extends AbstractStatementParser implements AgencyParserInterface
{
    public function supports(ClassifiedAgencyDocument $document): bool
    {
        return $document->documentType === AgencyDocumentType::ASG_MANAGEMENT_STATEMENT
            || $document->parserProfile === 'asg-releve-gerance-v1';
    }

    public function parse(string $text, array $metadata = []): AgencyParserResult
    {
        $fields = $this->extractFields($text);
        $lines = [];
        $issues = [];
        $currentProperty = null;
        $currentUnit = null;
        $currentTenant = null;
        $currentPage = 1;
        $lotContexts = [];
        $lastExpenseContext = null;
        $inBuildingExpenses = false;
        $statementPeriodStart = is_string($fields['periodStart'] ?? null) ? $fields['periodStart'] : null;
        $statementPeriodEnd = is_string($fields['periodEnd'] ?? null) ? $fields['periodEnd'] : null;

        foreach ($this->lines($text) as $line) {
            $currentPage = $this->sourcePageFromLine($line, $currentPage);
            if (preg_match('/IMMEUBLE\s+-\s+(.+)/iu', $line, $matches) === 1) {
                $currentProperty = $this->propertyLabel($matches[1]);
                $currentUnit = null;
                $currentTenant = null;
                $inBuildingExpenses = false;
                $lastExpenseContext = null;
                continue;
            }

            if (preg_match('/D[eé]penses de l[’\']immeuble/iu', $line) === 1) {
                $currentUnit = null;
                $currentTenant = null;
                $inBuildingExpenses = true;
                $lastExpenseContext = null;
                continue;
            }

            if (preg_match('/\bTotal\s+lot\b/iu', $line) === 1) {
                $context = $this->lotContextFromTotalLine($line, $currentProperty, $currentUnit, $currentTenant);
                if ($context !== null) {
                    $lotContexts[] = $context;
                }
                continue;
            }

            if (preg_match('/\ALot\s+(.+)/iu', $line, $matches) === 1) {
                $currentUnit = trim($matches[1]);
                $currentTenant = null;
                $inBuildingExpenses = false;
                $lastExpenseContext = null;
                continue;
            }

            if ($this->looksLikeTenantLine($line)) {
                $currentTenant = $this->tenantLabel($line);
                continue;
            }

            if (!$this->isFinancialLine($line)) {
                continue;
            }

            $amounts = $this->amounts($line);
            if ($amounts === []) {
                continue;
            }

            $label = $this->labelWithoutAmounts($line);
            $category = $this->categoryGuesser->guess($label);
            $isExpense = $this->isExpenseCategory($category);
            $lineProperty = $currentProperty;
            $lineUnit = $currentUnit;
            $lineTenant = $currentTenant;
            if ($isExpense && $inBuildingExpenses) {
                $expenseContext = $this->expenseContext($line, $category, $lotContexts, $lastExpenseContext);
                $lineProperty = $expenseContext['property'] ?? $currentProperty;
                $lineUnit = $expenseContext['unit'] ?? null;
                $lineTenant = $expenseContext['tenant'] ?? null;
                $lastExpenseContext = $expenseContext ?? null;
            }

            $lines[] = new AgencyStatementLineDraft(
                $label,
                $category,
                end($amounts) !== false ? (float) end($amounts) : null,
                $isExpense ? (float) end($amounts) : null,
                !$isExpense ? (float) end($amounts) : null,
                count($amounts) > 1 ? $amounts[0] : null,
                !$isExpense ? (float) end($amounts) : null,
                $category === 'owner_transfer' ? (float) end($amounts) : null,
                $statementPeriodStart,
                $statementPeriodEnd,
                null,
                $lineProperty,
                $lineUnit,
                $lineTenant,
                $currentPage,
                $category === 'other' ? 'review' : 'suggested',
                $this->lineHash($line)
            );
        }

        if ($lines === []) {
            $issues[] = new AgencyImportIssue('no_statement_lines', AgencyImportIssue::SEVERITY_WARNING, 'Aucune ligne de releve ASG exploitable detectee.');
        }

        return new AgencyParserResult(
            AgencyDocumentType::ASG_MANAGEMENT_STATEMENT,
            'asg-releve-gerance-v1',
            $lines !== [] ? 0.85 : 0.45,
            $fields,
            $lines,
            $issues
        );
    }

    /**
     * @return array<string, string|null>
     */
    private function extractFields(string $text): array
    {
        $fields = [
            'agencyName' => 'ASG IMMOBILIER',
            'ownerAccountReference' => null,
            'ownerLabel' => null,
            'statementDate' => null,
            'periodStart' => null,
            'periodEnd' => null,
        ];

        if (preg_match('/Num[eé]ro de compte\s+([A-Z0-9]+)/iu', $text, $matches) === 1) {
            $fields['ownerAccountReference'] = $matches[1];
        }

        if (preg_match('/Libell[eé]\s+(.+)/iu', $text, $matches) === 1) {
            $fields['ownerLabel'] = trim($matches[1]);
        }

        if (preg_match('/\bLe\s+(\d{2}\/\d{2}\/\d{4})/u', $text, $matches) === 1) {
            $fields['statementDate'] = $this->dateFrToSql($matches[1]);
        }

        if (preg_match('/P[eé]riode du\s+(\d{2}\/\d{2}\/\d{4})\s+au\s+(\d{2}\/\d{2}\/\d{4})/iu', $text, $matches) === 1) {
            $fields['periodStart'] = $this->dateFrToSql($matches[1]);
            $fields['periodEnd'] = $this->dateFrToSql($matches[2]);
        }

        return $fields;
    }

    private function looksLikeTenantLine(string $line): bool
    {
        if (str_contains($line, 'Période') || str_contains($line, 'Total') || str_contains($line, 'Dépenses')) {
            return false;
        }

        $tenantLabel = $this->tenantLabel($line);

        return preg_match('/\A[\p{L} .&\'-]{3,}\z/iu', $tenantLabel) === 1
            && !str_contains($line, 'IMMEUBLE')
            && !str_contains($line, 'Appartement');
    }

    private function tenantLabel(string $line): string
    {
        $line = (string) preg_replace('/\s+(entr[eé]|r[eé]vis[eé]).*/iu', '', $line);
        $line = (string) preg_replace('/\s*\([^)]*\)\s*$/u', '', $line);
        return trim((string) preg_replace('/\s+/u', ' ', $line));
    }

    private function propertyLabel(string $value): string
    {
        $value = (string) preg_replace('/\s+(Quittanc[eé]|Recettes|D[eé]penses)\b.*\z/iu', '', $value);
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function isFinancialLine(string $line): bool
    {
        return preg_match('/(Loyer|Provisions\/Charges|Taxe ordures|Honoraires|TVA sur Honoraires|ASSURANCE|Forfait Foncier|R[eè]glement Virement|Solde pr[eé]c[eé]dent)/iu', $line) === 1;
    }

    private function labelWithoutAmounts(string $line): string
    {
        $money = '-?\d{1,3}(?:[ \x{00A0}]?\d{3})*(?:[,.]\d{2})';
        $line = (string) preg_replace('/\s+' . $money . '(?:\s+' . $money . ')*\s*\z/u', '', $line);
        return trim((string) preg_replace('/\s+/u', ' ', $line));
    }

    private function isExpenseCategory(string $category): bool
    {
        return in_array($category, [
            'agency_fee_vat',
            'agency_management_fee',
            'agency_letting_fee',
            'insurance_unpaid_rent',
            'property_tax_service_fee',
            'works_expense',
            'condominium_current_charge',
            'copro_work_fund',
        ], true);
    }

    /**
     * @return array{property:?string, unit:string, tenant:?string, calledTotal:?float, paidTotal:?float}|null
     */
    private function lotContextFromTotalLine(
        string $line,
        ?string $property,
        ?string $unit,
        ?string $tenant
    ): ?array {
        if ($unit === null || trim($unit) === '') {
            return null;
        }

        $amounts = $this->amounts($line);
        if ($amounts === []) {
            return null;
        }

        $paidTotal = $amounts[count($amounts) - 1] ?? null;
        $calledTotal = count($amounts) > 1 ? $amounts[count($amounts) - 2] : $paidTotal;

        return [
            'property' => $property,
            'unit' => trim($unit),
            'tenant' => $tenant,
            'calledTotal' => $calledTotal,
            'paidTotal' => $paidTotal,
        ];
    }

    /**
     * @param array<int, array{property:?string, unit:string, tenant:?string, calledTotal:?float, paidTotal:?float}> $lotContexts
     * @param array{property:?string, unit:string, tenant:?string, calledTotal:?float, paidTotal:?float}|null $lastExpenseContext
     * @return array{property:?string, unit:string, tenant:?string, calledTotal:?float, paidTotal:?float}|null
     */
    private function expenseContext(
        string $line,
        string $category,
        array $lotContexts,
        ?array $lastExpenseContext
    ): ?array {
        $basis = $this->basisAmount($line);
        if ($basis !== null) {
            return $this->lotContextByBasis($lotContexts, $basis);
        }

        if (
            $category === 'agency_fee_vat'
            && is_array($lastExpenseContext)
            && isset($lastExpenseContext['unit'])
        ) {
            return $lastExpenseContext;
        }

        return null;
    }

    private function basisAmount(string $line): ?float
    {
        if (preg_match('/\(\s*(' . $this->amountPattern() . ')\s*x\s*[-\d\s\x{00A0},.]+%?\s*\)/iu', $line, $matches) !== 1) {
            return null;
        }

        return $this->amount($matches[1]);
    }

    /**
     * @param array<int, array{property:?string, unit:string, tenant:?string, calledTotal:?float, paidTotal:?float}> $lotContexts
     * @return array{property:?string, unit:string, tenant:?string, calledTotal:?float, paidTotal:?float}|null
     */
    private function lotContextByBasis(array $lotContexts, float $basis): ?array
    {
        $matches = [];
        foreach ($lotContexts as $context) {
            $calledTotal = $context['calledTotal'];
            $paidTotal = $context['paidTotal'];
            if (
                ($calledTotal !== null && abs($calledTotal - $basis) < 0.01)
                || ($paidTotal !== null && abs($paidTotal - $basis) < 0.01)
            ) {
                $matches[$context['unit']] = $context;
            }
        }

        if (count($matches) !== 1) {
            return null;
        }

        $match = reset($matches);
        return is_array($match) ? $match : null;
    }

    private function amountPattern(): string
    {
        return '-?\d{1,3}(?:[ \x{00A0}]?\d{3})*(?:[,.]\d+)?';
    }
}
