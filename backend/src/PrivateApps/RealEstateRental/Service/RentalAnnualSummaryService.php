<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Service;

use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;

final class RentalAnnualSummaryService
{
    public function __construct(private readonly RentalLifecycleRepository $repository)
    {
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<string, mixed>
     */
    public function build(int $year, array $propertyIds): array
    {
        $year = $this->normalizeYear($year);
        $propertyIds = $this->normalizeIds($propertyIds);
        $issues = $this->repository->draftIssues($propertyIds, $year);
        $blocked = $issues !== [];

        $summary = [
            'year' => $year,
            'blocked' => $blocked,
            'issues' => $issues,
            'totals' => [
                'rentDue' => 0.0,
                'rentPaid' => 0.0,
                'unpaidRent' => 0.0,
                'recoverableExpenses' => 0.0,
                'deductibleCandidateExpenses' => 0.0,
                'nonDeductibleExpenses' => 0.0,
                'validatedPayments' => 0,
                'partialPayments' => 0,
                'validatedExpenses' => 0,
                'validatedLeases' => 0,
                'endedLeases' => 0,
            ],
            'payments' => [],
            'expenses' => [],
            'leases' => [],
        ];

        if ($blocked) {
            return $summary;
        }

        $payments = $this->repository->listPayments($propertyIds, $year, 1000);
        $expenses = $this->repository->listExpenses($propertyIds, $year, 1000);
        $leases = $this->leasesForYear($this->repository->listLeases($propertyIds, 1000), $year);

        foreach ($payments as $payment) {
            if (($payment['status'] ?? '') !== 'validated') {
                continue;
            }

            $amountDue = $this->amount($payment['amountDue'] ?? 0);
            $amountPaid = $this->amount($payment['amountPaid'] ?? 0);
            $summary['totals']['rentDue'] += $amountDue;
            $summary['totals']['rentPaid'] += $amountPaid;
            $summary['totals']['unpaidRent'] += max(0.0, $amountDue - $amountPaid);
            ++$summary['totals']['validatedPayments'];
            if ($amountPaid < $amountDue) {
                ++$summary['totals']['partialPayments'];
            }
            $summary['payments'][] = $payment;
        }

        foreach ($expenses as $expense) {
            if (($expense['status'] ?? '') !== 'validated') {
                continue;
            }

            $amount = $this->amount($expense['amount'] ?? 0);
            if ((int) ($expense['isRecoverable'] ?? 0) === 1) {
                $summary['totals']['recoverableExpenses'] += $amount;
            }
            if ((int) ($expense['isDeductibleCandidate'] ?? 0) === 1) {
                $summary['totals']['deductibleCandidateExpenses'] += $amount;
            } else {
                $summary['totals']['nonDeductibleExpenses'] += $amount;
            }
            ++$summary['totals']['validatedExpenses'];
            $summary['expenses'][] = $expense;
        }

        foreach ($leases as $lease) {
            $status = (string) ($lease['status'] ?? '');
            if (!in_array($status, ['validated', 'ended'], true)) {
                continue;
            }
            ++$summary['totals']['validatedLeases'];
            if ($status === 'ended') {
                ++$summary['totals']['endedLeases'];
            }
            $summary['leases'][] = $lease;
        }

        foreach (['rentDue', 'rentPaid', 'unpaidRent', 'recoverableExpenses', 'deductibleCandidateExpenses', 'nonDeductibleExpenses'] as $key) {
            $summary['totals'][$key] = round((float) $summary['totals'][$key], 2);
        }

        return $summary;
    }

    /**
     * @param array<int, array<string, mixed>> $leases
     * @return array<int, array<string, mixed>>
     */
    private function leasesForYear(array $leases, int $year): array
    {
        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);
        $filtered = [];

        foreach ($leases as $lease) {
            $startDate = is_string($lease['startDate'] ?? null) ? (string) $lease['startDate'] : '';
            $endDate = is_string($lease['endDate'] ?? null) ? (string) $lease['endDate'] : '';
            if ($startDate === '' || $startDate > $yearEnd) {
                continue;
            }
            if ($endDate !== '' && $endDate < $yearStart) {
                continue;
            }
            $filtered[] = $lease;
        }

        return $filtered;
    }

    private function amount(mixed $value): float
    {
        return is_numeric($value) ? round((float) $value, 2) : 0.0;
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
