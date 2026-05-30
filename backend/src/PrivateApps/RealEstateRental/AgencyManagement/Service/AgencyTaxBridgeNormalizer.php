<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Service;

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Repository\AgencyImportRepository;

final class AgencyTaxBridgeNormalizer
{
    private const INCOME_CATEGORIES = [
        'rent_income',
        'charge_provision_income',
        'recoverable_tax_income',
    ];

    private const DEDUCTIBLE_EXPENSE_CATEGORIES = [
        'agency_management_fee',
        'agency_fee_vat',
        'agency_letting_fee',
        'insurance_unpaid_rent',
        'property_tax_service_fee',
        'works_expense',
        'condominium_current_charge',
    ];

    private const RECOVERABLE_EXPENSE_CATEGORIES = [
        'recoverable_utility_charge',
    ];

    public function __construct(private readonly AgencyImportRepository $repository)
    {
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array{rentDue:float,rentPaid:float,unpaidRent:float,validatedPaymentCount:int,partialPaymentCount:int,sourceRows:array<int, array<string, mixed>>}
     */
    public function annualRentalIncome(int $year, array $propertyIds): array
    {
        $rentDue = 0.0;
        $rentPaid = 0.0;
        $partialPaymentCount = 0;
        $sourceRows = [];

        foreach ($this->repository->listValidatedFiscalLines($year, $propertyIds) as $line) {
            $category = (string) ($line['mapped_category'] ?? '');
            if (!in_array($category, self::INCOME_CATEGORIES, true)) {
                continue;
            }

            $calledAmount = $this->amount($line['called_amount'] ?? null);
            $paidAmount = $this->amount($line['paid_amount'] ?? null);
            $creditAmount = $this->amount($line['credit_amount'] ?? null);
            $amount = $this->amount($line['amount'] ?? null);
            $due = $calledAmount > 0.0 ? $calledAmount : $amount;
            $paid = $paidAmount > 0.0 ? $paidAmount : ($creditAmount > 0.0 ? $creditAmount : $amount);

            $rentDue += $due;
            $rentPaid += $paid;
            if ($due > 0.0 && $paid < $due) {
                ++$partialPaymentCount;
            }

            $sourceRows[] = $this->sourceRow($line, $category, $paid);
        }

        return [
            'rentDue' => round($rentDue, 2),
            'rentPaid' => round($rentPaid, 2),
            'unpaidRent' => round(max(0.0, $rentDue - $rentPaid), 2),
            'validatedPaymentCount' => count($sourceRows),
            'partialPaymentCount' => $partialPaymentCount,
            'sourceRows' => $sourceRows,
        ];
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array{recoverableExpenses:float,deductibleCandidateExpenses:float,nonDeductibleExpenses:float,validatedExpenseCount:int,sourceRows:array<int, array<string, mixed>>}
     */
    public function annualDeductibleExpenses(int $year, array $propertyIds): array
    {
        $recoverableExpenses = 0.0;
        $deductibleCandidateExpenses = 0.0;
        $nonDeductibleExpenses = 0.0;
        $sourceRows = [];

        foreach ($this->repository->listValidatedFiscalLines($year, $propertyIds) as $line) {
            $category = (string) ($line['mapped_category'] ?? '');
            if (in_array($category, self::INCOME_CATEGORIES, true) || in_array($category, ['owner_transfer', 'security_deposit', 'agency_balance'], true)) {
                continue;
            }

            $amount = abs($this->amount($line['debit_amount'] ?? null) ?: $this->amount($line['amount'] ?? null));
            if ($amount <= 0.0) {
                continue;
            }

            if (in_array($category, self::RECOVERABLE_EXPENSE_CATEGORIES, true)) {
                $recoverableExpenses += $amount;
            }

            if (in_array($category, self::DEDUCTIBLE_EXPENSE_CATEGORIES, true)) {
                $deductibleCandidateExpenses += $amount;
            } else {
                $nonDeductibleExpenses += $amount;
            }

            $sourceRows[] = $this->sourceRow($line, $category, $amount);
        }

        return [
            'recoverableExpenses' => round($recoverableExpenses, 2),
            'deductibleCandidateExpenses' => round($deductibleCandidateExpenses, 2),
            'nonDeductibleExpenses' => round($nonDeductibleExpenses, 2),
            'validatedExpenseCount' => count($sourceRows),
            'sourceRows' => $sourceRows,
        ];
    }

    /**
     * @param array<string, mixed> $line
     * @return array<string, mixed>
     */
    private function sourceRow(array $line, string $category, float $amount): array
    {
        return [
            'table' => 'rental_agency_statement_lines',
            'id' => $this->integer($line['id'] ?? 0),
            'propertyId' => $this->integer($line['rental_property_id'] ?? 0),
            'unitId' => $this->integer($line['rental_unit_id'] ?? 0),
            'documentId' => $this->integer($line['imported_document_id'] ?? 0),
            'statementId' => $this->integer($line['statement_id'] ?? 0),
            'sourcePage' => $this->integer($line['source_page'] ?? 1),
            'category' => $category,
            'amount' => round($amount, 2),
            'periodStart' => is_scalar($line['period_start'] ?? null) ? (string) $line['period_start'] : '',
            'periodEnd' => is_scalar($line['period_end'] ?? null) ? (string) $line['period_end'] : '',
            'rawLabel' => is_scalar($line['raw_label'] ?? null) ? (string) $line['raw_label'] : '',
        ];
    }

    private function amount(mixed $value): float
    {
        return is_numeric($value) ? round((float) $value, 2) : 0.0;
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
