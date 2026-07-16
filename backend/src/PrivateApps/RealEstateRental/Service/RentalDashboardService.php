<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Service;

use Caramagnols\PrivateApps\RealEstateRental\Domain\RentalUnit;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalUnitRepository;

final class RentalDashboardService
{
    public function __construct(
        private readonly RentalLifecycleRepository $lifecycleRepository,
        private readonly RentalUnitRepository $unitRepository,
        private readonly RentalAnnualSummaryService $summaryService
    ) {
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<string, mixed>
     */
    public function build(int $year, int $month, array $propertyIds, ?string $today = null): array
    {
        $propertyIds = $this->normalizeIds($propertyIds);
        $year = $this->normalizeYear($year);
        $month = $month >= 1 && $month <= 12 ? $month : (int) date('n');
        $today = $this->normalizeDate($today ?? date('Y-m-d'));

        $units = $this->unitRepository->listByPropertyIds($propertyIds, 1000);
        $leases = $this->lifecycleRepository->listLeases($propertyIds, 1000);
        $rents = $this->lifecycleRepository->listRents($propertyIds, $year, 1000);
        $payments = $this->lifecycleRepository->listPayments($propertyIds, $year, 1000);
        $expenses = $this->lifecycleRepository->listExpenses($propertyIds, $year, 1000);
        $documents = $this->lifecycleRepository->listDocuments($propertyIds, 1000);
        $summary = $this->summaryService->build($year, $propertyIds);

        $activeLeaseUnitIds = [];
        $leasesEndingSoon = [];
        foreach ($leases as $lease) {
            if (!is_array($lease)) {
                continue;
            }
            $status = (string) ($lease['status'] ?? '');
            $unitId = $this->integer($lease['rentalUnitId'] ?? 0);
            if (in_array($status, ['draft', 'validated'], true) && $unitId > 0) {
                $activeLeaseUnitIds[$unitId] = true;
            }
            if ($status === 'validated' && $this->dateWithinDays((string) ($lease['endDate'] ?? ''), $today, 90)) {
                $leasesEndingSoon[] = [
                    'id' => $this->integer($lease['id'] ?? 0),
                    'propertyId' => $this->integer($lease['rentalPropertyId'] ?? 0),
                    'unitId' => $unitId,
                    'unitLabel' => (string) ($lease['unitLabel'] ?? ''),
                    'tenantName' => (string) ($lease['tenantName'] ?? ''),
                    'endDate' => (string) ($lease['endDate'] ?? ''),
                ];
            }
        }

        $unitStats = $this->unitStats($units, $activeLeaseUnitIds);
        $monthly = $this->monthlyRentStats($rents, $payments, $year, $month, $today);
        $annualBalances = $this->annualBalances($propertyIds, $payments, $expenses);
        $missingDocuments = $this->missingDocuments($propertyIds, $leases, $rents, $expenses, $documents, $year);
        $totals = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];
        $issues = is_array($summary['issues'] ?? null) ? $summary['issues'] : [];

        return [
            'year' => $year,
            'month' => $month,
            'summaryBlocked' => !empty($summary['blocked']),
            'issues' => $issues,
            'issueCount' => count($issues),
            'rentDue' => $this->amount($totals['rentDue'] ?? 0),
            'rentPaid' => $this->amount($totals['rentPaid'] ?? 0),
            'unpaidRent' => $this->amount($totals['unpaidRent'] ?? 0),
            'monthlyRentDue' => $monthly['due'],
            'monthlyRentPaid' => $monthly['paid'],
            'monthlyPartialRentCount' => $monthly['partialCount'],
            'monthlyLateRentCount' => $monthly['lateCount'],
            'leasedUnitCount' => count($activeLeaseUnitIds),
            'vacantUnitCount' => $unitStats['vacant'],
            'unavailableUnitCount' => $unitStats['unavailable'],
            'leasesEndingSoonCount' => count($leasesEndingSoon),
            'leasesEndingSoon' => $leasesEndingSoon,
            'missingDocumentCount' => count($missingDocuments),
            'missingDocuments' => $missingDocuments,
            'annualBalances' => array_values($annualBalances),
            'taxBridgeEnabled' => true,
        ];
    }

    /**
     * @param array<int, RentalUnit> $units
     * @param array<int, bool> $activeLeaseUnitIds
     * @return array{vacant:int, unavailable:int}
     */
    private function unitStats(array $units, array $activeLeaseUnitIds): array
    {
        $vacant = 0;
        $unavailable = 0;
        foreach ($units as $unit) {
            if (!$unit instanceof RentalUnit) {
                continue;
            }
            if ($unit->status === 'unavailable') {
                ++$unavailable;
                continue;
            }
            if ($unit->status === 'available' && !isset($activeLeaseUnitIds[$unit->id])) {
                ++$vacant;
            }
        }

        return ['vacant' => $vacant, 'unavailable' => $unavailable];
    }

    /**
     * @param array<int, array<string, mixed>> $rents
     * @param array<int, array<string, mixed>> $payments
     * @return array{due:float, paid:float, partialCount:int, lateCount:int}
     */
    private function monthlyRentStats(array $rents, array $payments, int $year, int $month, string $today): array
    {
        $due = 0.0;
        $paid = 0.0;
        $partialCount = 0;
        $lateCount = 0;

        foreach ($rents as $rent) {
            if (!is_array($rent) || $this->integer($rent['periodYear'] ?? 0) !== $year || $this->integer($rent['periodMonth'] ?? 0) !== $month) {
                continue;
            }
            if ((string) ($rent['status'] ?? '') === 'cancelled') {
                continue;
            }

            $amountDue = $this->amount($rent['amountDue'] ?? 0);
            $amountPaid = $this->amount($rent['amountPaid'] ?? 0);
            $due += $amountDue;
            if ($amountPaid > 0.001 && $amountPaid < $amountDue) {
                ++$partialCount;
            }
            $dueDate = is_scalar($rent['dueDate'] ?? null) ? (string) $rent['dueDate'] : '';
            if ((string) ($rent['status'] ?? '') === 'late' || ($dueDate !== '' && $dueDate < $today && $amountPaid < $amountDue)) {
                ++$lateCount;
            }
        }

        foreach ($payments as $payment) {
            if (
                !is_array($payment)
                || (string) ($payment['status'] ?? '') !== 'validated'
                || $this->integer($payment['periodYear'] ?? 0) !== $year
                || $this->integer($payment['periodMonth'] ?? 0) !== $month
            ) {
                continue;
            }

            $amount = $this->amount($payment['amountPaid'] ?? 0);
            $paid += (string) ($payment['paymentKind'] ?? '') === 'refund' ? -$amount : $amount;
        }

        return [
            'due' => round($due, 2),
            'paid' => round($paid, 2),
            'partialCount' => $partialCount,
            'lateCount' => $lateCount,
        ];
    }

    /**
     * @param array<int, int> $propertyIds
     * @param array<int, array<string, mixed>> $payments
     * @param array<int, array<string, mixed>> $expenses
     * @return array<int, array<string, mixed>>
     */
    private function annualBalances(array $propertyIds, array $payments, array $expenses): array
    {
        $balances = [];
        foreach ($propertyIds as $propertyId) {
            $balances[$propertyId] = [
                'propertyId' => $propertyId,
                'rentPaid' => 0.0,
                'expenses' => 0.0,
                'deductibleCandidateExpenses' => 0.0,
                'balance' => 0.0,
            ];
        }

        foreach ($payments as $payment) {
            if (!is_array($payment) || (string) ($payment['status'] ?? '') !== 'validated') {
                continue;
            }
            $propertyId = $this->integer($payment['rentalPropertyId'] ?? 0);
            if (!isset($balances[$propertyId])) {
                continue;
            }
            $amount = $this->amount($payment['amountPaid'] ?? 0);
            $balances[$propertyId]['rentPaid'] += (string) ($payment['paymentKind'] ?? '') === 'refund' ? -$amount : $amount;
        }

        foreach ($expenses as $expense) {
            if (!is_array($expense) || (string) ($expense['status'] ?? '') !== 'validated') {
                continue;
            }
            $propertyId = $this->integer($expense['rentalPropertyId'] ?? 0);
            if (!isset($balances[$propertyId])) {
                continue;
            }
            $amount = $this->amount($expense['amount'] ?? 0);
            $balances[$propertyId]['expenses'] += $amount;
            if ($this->integer($expense['isDeductibleCandidate'] ?? 0) === 1) {
                $balances[$propertyId]['deductibleCandidateExpenses'] += $amount;
            }
        }

        foreach ($balances as $propertyId => $balance) {
            $balances[$propertyId]['rentPaid'] = round((float) $balance['rentPaid'], 2);
            $balances[$propertyId]['expenses'] = round((float) $balance['expenses'], 2);
            $balances[$propertyId]['deductibleCandidateExpenses'] = round((float) $balance['deductibleCandidateExpenses'], 2);
            $balances[$propertyId]['balance'] = round((float) $balance['rentPaid'] - (float) $balance['expenses'], 2);
        }

        return $balances;
    }

    /**
     * @param array<int, int> $propertyIds
     * @param array<int, array<string, mixed>> $leases
     * @param array<int, array<string, mixed>> $rents
     * @param array<int, array<string, mixed>> $expenses
     * @param array<int, array<string, mixed>> $documents
     * @return array<int, array<string, mixed>>
     */
    private function missingDocuments(array $propertyIds, array $leases, array $rents, array $expenses, array $documents, int $year): array
    {
        $documentByLease = [];
        $documentByProperty = [];
        foreach ($documents as $document) {
            if (!is_array($document)) {
                continue;
            }
            $propertyId = $this->integer($document['rentalPropertyId'] ?? 0);
            $leaseId = $this->integer($document['rentalLeaseId'] ?? 0);
            if ($propertyId > 0) {
                $documentByProperty[$propertyId] = true;
            }
            if ($leaseId > 0) {
                $documentByLease[$leaseId] = true;
            }
        }

        $missing = [];
        foreach ($leases as $lease) {
            if (!is_array($lease) || !$this->leaseTouchesYear($lease, $year)) {
                continue;
            }
            $status = (string) ($lease['status'] ?? '');
            $leaseId = $this->integer($lease['id'] ?? 0);
            if (in_array($status, ['validated', 'ended'], true) && $leaseId > 0 && !isset($documentByLease[$leaseId])) {
                $missing[] = [
                    'type' => 'lease',
                    'propertyId' => $this->integer($lease['rentalPropertyId'] ?? 0),
                    'leaseId' => $leaseId,
                    'label' => 'Bail sans document: ' . (string) ($lease['tenantName'] ?? ('#' . $leaseId)),
                ];
            }
        }

        $propertiesWithFiscalRows = [];
        foreach (array_merge($rents, $expenses) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $propertyId = $this->integer($row['rentalPropertyId'] ?? 0);
            if ($propertyId > 0) {
                $propertiesWithFiscalRows[$propertyId] = true;
            }
        }
        foreach ($propertyIds as $propertyId) {
            if (isset($propertiesWithFiscalRows[$propertyId]) && !isset($documentByProperty[$propertyId])) {
                $missing[] = [
                    'type' => 'property',
                    'propertyId' => $propertyId,
                    'leaseId' => 0,
                    'label' => 'Bien sans justificatif locatif annuel #' . $propertyId,
                ];
            }
        }

        return $missing;
    }

    /**
     * @param array<string, mixed> $lease
     */
    private function leaseTouchesYear(array $lease, int $year): bool
    {
        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);
        $startDate = is_scalar($lease['startDate'] ?? null) ? (string) $lease['startDate'] : '';
        $endDate = is_scalar($lease['endDate'] ?? null) ? (string) $lease['endDate'] : '';

        return $startDate !== '' && $startDate <= $yearEnd && ($endDate === '' || $endDate >= $yearStart);
    }

    private function dateWithinDays(string $date, string $today, int $days): bool
    {
        if ($date === '' || $date < $today) {
            return false;
        }

        try {
            $start = new \DateTimeImmutable($today);
            $end = new \DateTimeImmutable($date);
        } catch (\Throwable) {
            return false;
        }

        return (int) $start->diff($end)->format('%a') <= $days;
    }

    private function normalizeDate(string $date): string
    {
        return preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) === 1 ? $date : date('Y-m-d');
    }

    private function normalizeYear(int $year): int
    {
        return ($year >= 2000 && $year <= 2100) ? $year : (int) date('Y');
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

    private function amount(mixed $value): float
    {
        return is_numeric($value) ? round((float) $value, 2) : 0.0;
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
