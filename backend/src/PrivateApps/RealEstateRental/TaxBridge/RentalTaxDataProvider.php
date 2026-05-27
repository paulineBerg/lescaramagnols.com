<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\TaxBridge;

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Service\AgencyTaxBridgeNormalizer;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalAnnualSummaryService;
use Caramagnols\PrivatePortal\TaxDeclarationHelper\ValueObject\AnnualDeductibleExpenses;
use Caramagnols\PrivatePortal\TaxDeclarationHelper\ValueObject\AnnualRentalIncome;
use Caramagnols\PrivatePortal\TaxDeclarationHelper\ValueObject\MissingTaxDocument;

final class RentalTaxDataProvider implements RentalTaxDataProviderInterface
{
    public function __construct(
        private readonly RentalAnnualSummaryService $summaryService,
        private readonly RentalLifecycleRepository $lifecycleRepository,
        private readonly ?AgencyTaxBridgeNormalizer $agencyTaxBridgeNormalizer = null
    ) {
    }

    public function annualRentalIncome(int $year, array $propertyIds): AnnualRentalIncome
    {
        $propertyIds = $this->normalizeIds($propertyIds);
        $summary = $this->summary($year, $propertyIds);
        $controls = $this->controlsFromSummary($summary);
        if ($controls !== []) {
            return new AnnualRentalIncome($this->yearFromSummary($summary), 0.0, 0.0, 0.0, 0, 0, [], $controls);
        }

        $totals = $this->totalsFromSummary($summary);
        $agencyIncome = $this->agencyTaxBridgeNormalizer?->annualRentalIncome(
            $this->yearFromSummary($summary),
            $propertyIds
        ) ?? [
            'rentDue' => 0.0,
            'rentPaid' => 0.0,
            'unpaidRent' => 0.0,
            'validatedPaymentCount' => 0,
            'partialPaymentCount' => 0,
            'sourceRows' => [],
        ];

        return new AnnualRentalIncome(
            $this->yearFromSummary($summary),
            $this->amount($totals['rentDue'] ?? 0) + $this->amount($agencyIncome['rentDue'] ?? 0),
            $this->amount($totals['rentPaid'] ?? 0) + $this->amount($agencyIncome['rentPaid'] ?? 0),
            $this->amount($totals['unpaidRent'] ?? 0) + $this->amount($agencyIncome['unpaidRent'] ?? 0),
            $this->integer($totals['validatedPayments'] ?? 0)
                + $this->integer($agencyIncome['validatedPaymentCount'] ?? 0),
            $this->integer($totals['partialPayments'] ?? 0)
                + $this->integer($agencyIncome['partialPaymentCount'] ?? 0),
            array_merge($this->paymentSourceRows($summary), $this->sourceRows($agencyIncome['sourceRows'] ?? [])),
            []
        );
    }

    public function annualDeductibleExpenses(int $year, array $propertyIds): AnnualDeductibleExpenses
    {
        $propertyIds = $this->normalizeIds($propertyIds);
        $summary = $this->summary($year, $propertyIds);
        $controls = $this->controlsFromSummary($summary);
        if ($controls !== []) {
            return new AnnualDeductibleExpenses($this->yearFromSummary($summary), 0.0, 0.0, 0.0, 0, [], $controls);
        }

        $totals = $this->totalsFromSummary($summary);
        $agencyExpenses = $this->agencyTaxBridgeNormalizer?->annualDeductibleExpenses(
            $this->yearFromSummary($summary),
            $propertyIds
        ) ?? [
            'recoverableExpenses' => 0.0,
            'deductibleCandidateExpenses' => 0.0,
            'nonDeductibleExpenses' => 0.0,
            'validatedExpenseCount' => 0,
            'sourceRows' => [],
        ];

        return new AnnualDeductibleExpenses(
            $this->yearFromSummary($summary),
            $this->amount($totals['recoverableExpenses'] ?? 0)
                + $this->amount($agencyExpenses['recoverableExpenses'] ?? 0),
            $this->amount($totals['deductibleCandidateExpenses'] ?? 0)
                + $this->amount($agencyExpenses['deductibleCandidateExpenses'] ?? 0),
            $this->amount($totals['nonDeductibleExpenses'] ?? 0)
                + $this->amount($agencyExpenses['nonDeductibleExpenses'] ?? 0),
            $this->integer($totals['validatedExpenses'] ?? 0)
                + $this->integer($agencyExpenses['validatedExpenseCount'] ?? 0),
            array_merge($this->expenseSourceRows($summary), $this->sourceRows($agencyExpenses['sourceRows'] ?? [])),
            []
        );
    }

    public function missingTaxDocuments(int $year, array $propertyIds): array
    {
        $summary = $this->summary($year, $propertyIds);
        if ($this->controlsFromSummary($summary) !== []) {
            return [];
        }

        $totals = $this->totalsFromSummary($summary);
        $hasFiscalRows = $this->amount($totals['rentPaid'] ?? 0) > 0.0
            || $this->amount($totals['deductibleCandidateExpenses'] ?? 0) > 0.0;
        if (!$hasFiscalRows) {
            return [];
        }

        $documents = $this->lifecycleRepository->listDocuments($this->normalizeIds($propertyIds), 1);
        if ($documents !== []) {
            return [];
        }

        return [
            new MissingTaxDocument(
                $this->yearFromSummary($summary),
                'rental_supporting_documents',
                'Aucun justificatif locatif rattache aux biens sources de la synthese annuelle.',
                'warning',
                'rental_documents'
            ),
        ];
    }

    public function controls(int $year, array $propertyIds): array
    {
        return $this->controlsFromSummary($this->summary($year, $propertyIds));
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<string, mixed>
     */
    private function summary(int $year, array $propertyIds): array
    {
        return $this->summaryService->build($this->normalizeYear($year), $this->normalizeIds($propertyIds));
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<int, string>
     */
    private function controlsFromSummary(array $summary): array
    {
        $issues = is_array($summary['issues'] ?? null) ? $summary['issues'] : [];
        if (empty($summary['blocked']) && $issues === []) {
            return [];
        }

        $controls = [];
        foreach ($issues as $issue) {
            if (is_scalar($issue) && trim((string) $issue) !== '') {
                $controls[] = trim((string) $issue);
            }
        }

        if ($controls === []) {
            $controls[] = 'Synthese locative bloquee par les controles source.';
        }

        return array_values(array_unique($controls));
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<string, mixed>
     */
    private function totalsFromSummary(array $summary): array
    {
        return is_array($summary['totals'] ?? null) ? $summary['totals'] : [];
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<int, array<string, mixed>>
     */
    private function paymentSourceRows(array $summary): array
    {
        $payments = is_array($summary['payments'] ?? null) ? $summary['payments'] : [];
        $sourceRows = [];
        foreach ($payments as $payment) {
            if (!is_array($payment) || ($payment['status'] ?? '') !== 'validated') {
                continue;
            }

            $sourceRows[] = [
                'table' => 'rental_payments',
                'id' => $this->integer($payment['id'] ?? 0),
                'propertyId' => $this->integer($payment['rentalPropertyId'] ?? 0),
                'leaseId' => $this->integer($payment['rentalLeaseId'] ?? 0),
                'periodYear' => $this->integer($payment['periodYear'] ?? 0),
                'periodMonth' => $this->integer($payment['periodMonth'] ?? 0),
                'amountDue' => $this->amount($payment['amountDue'] ?? 0),
                'amountPaid' => $this->amount($payment['amountPaid'] ?? 0),
            ];
        }

        return $sourceRows;
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<int, array<string, mixed>>
     */
    private function expenseSourceRows(array $summary): array
    {
        $expenses = is_array($summary['expenses'] ?? null) ? $summary['expenses'] : [];
        $sourceRows = [];
        foreach ($expenses as $expense) {
            if (!is_array($expense) || ($expense['status'] ?? '') !== 'validated') {
                continue;
            }

            $sourceRows[] = [
                'table' => 'rental_expenses',
                'id' => $this->integer($expense['id'] ?? 0),
                'propertyId' => $this->integer($expense['rentalPropertyId'] ?? 0),
                'expenseDate' => is_scalar($expense['expenseDate'] ?? null) ? (string) $expense['expenseDate'] : '',
                'amount' => $this->amount($expense['amount'] ?? 0),
                'recoverable' => $this->integer($expense['isRecoverable'] ?? 0) === 1,
                'deductibleCandidate' => $this->integer($expense['isDeductibleCandidate'] ?? 0) === 1,
            ];
        }

        return $sourceRows;
    }

    /**
     * @param mixed $rows
     * @return array<int, array<string, mixed>>
     */
    private function sourceRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $normalized[] = $row;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function yearFromSummary(array $summary): int
    {
        return is_numeric($summary['year'] ?? null) ? (int) $summary['year'] : (int) date('Y');
    }

    private function amount(mixed $value): float
    {
        return is_numeric($value) ? round((float) $value, 2) : 0.0;
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function normalizeYear(int $year): int
    {
        if ($year < 2000 || $year > 2100) {
            return (int) date('Y');
        }

        return $year;
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, int>
     */
    private function normalizeIds(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $normalized[] = (int) $id;
            }
        }

        return array_values(array_unique($normalized));
    }
}
