<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Service;

use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
use DateTimeImmutable;

final class RentScheduleService
{
    private const ACTIVE_LEASE_STATUSES = ['draft', 'validated', 'ended'];

    public function __construct(private readonly RentalLifecycleRepository $repository)
    {
    }

    /**
     * @return array{created:int, existing:int, skipped:int, rents:array<int, array<string, mixed>>, reasons:array<string, int>}
     */
    public function generateForLeasePeriod(int $leaseId, int $periodYear, int $periodMonth, int $actorPrivateUserId): array
    {
        $result = $this->emptyResult();
        if ($actorPrivateUserId <= 0 || !$this->isValidPeriod($periodYear, $periodMonth)) {
            return $this->addSkipped($result, 'invalid_period');
        }

        $lease = $this->repository->findLeaseById($leaseId);
        if (!is_array($lease)) {
            return $this->addSkipped($result, 'lease_missing');
        }

        return $this->generateFromLease($result, $lease, $periodYear, $periodMonth, $actorPrivateUserId);
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array{created:int, existing:int, skipped:int, rents:array<int, array<string, mixed>>, reasons:array<string, int>}
     */
    public function generateForMonth(array $propertyIds, int $periodYear, int $periodMonth, int $actorPrivateUserId): array
    {
        $result = $this->emptyResult();
        $propertyIds = $this->normalizeIds($propertyIds);
        if ($propertyIds === [] || $actorPrivateUserId <= 0 || !$this->isValidPeriod($periodYear, $periodMonth)) {
            return $this->addSkipped($result, 'invalid_period');
        }

        foreach ($this->repository->listLeases($propertyIds, 1000) as $lease) {
            $result = $this->generateFromLease($result, $lease, $periodYear, $periodMonth, $actorPrivateUserId);
        }

        return $result;
    }

    /**
     * @return array{created:int, existing:int, skipped:int, rents:array<int, array<string, mixed>>, reasons:array<string, int>}
     */
    private function emptyResult(): array
    {
        return [
            'created' => 0,
            'existing' => 0,
            'skipped' => 0,
            'rents' => [],
            'reasons' => [],
        ];
    }

    /**
     * @param array{created:int, existing:int, skipped:int, rents:array<int, array<string, mixed>>, reasons:array<string, int>} $result
     * @param array<string, mixed> $lease
     * @return array{created:int, existing:int, skipped:int, rents:array<int, array<string, mixed>>, reasons:array<string, int>}
     */
    private function generateFromLease(array $result, array $lease, int $periodYear, int $periodMonth, int $actorPrivateUserId): array
    {
        $leaseId = is_numeric($lease['id'] ?? null) ? (int) $lease['id'] : 0;
        $propertyId = is_numeric($lease['rentalPropertyId'] ?? null) ? (int) $lease['rentalPropertyId'] : 0;
        $unitId = is_numeric($lease['rentalUnitId'] ?? null) ? (int) $lease['rentalUnitId'] : 0;
        $leaseStatus = is_string($lease['status'] ?? null) ? (string) $lease['status'] : '';
        if ($leaseId <= 0 || $propertyId <= 0 || $unitId <= 0 || !in_array($leaseStatus, self::ACTIVE_LEASE_STATUSES, true)) {
            return $this->addSkipped($result, 'lease_inactive');
        }

        $bounds = $this->periodBounds($periodYear, $periodMonth);
        if ($bounds === null) {
            return $this->addSkipped($result, 'invalid_period');
        }

        [$periodStart, $periodEnd] = $bounds;
        $startDate = is_string($lease['startDate'] ?? null) ? (string) $lease['startDate'] : '';
        $endDate = is_string($lease['endDate'] ?? null) ? (string) $lease['endDate'] : '';
        if ($startDate === '' || $periodEnd < $startDate || ($endDate !== '' && $periodStart > $endDate)) {
            return $this->addSkipped($result, 'lease_outside_period');
        }

        $existing = $this->repository->findRentByLeasePeriod($leaseId, $periodYear, $periodMonth);
        if (is_array($existing)) {
            ++$result['existing'];
            $result['rents'][] = $existing;

            return $result;
        }

        $monthlyRent = is_numeric($lease['monthlyRent'] ?? null) ? round((float) $lease['monthlyRent'], 2) : 0.0;
        $chargesProvision = is_numeric($lease['chargesProvision'] ?? null) ? round((float) $lease['chargesProvision'], 2) : 0.0;
        $amountDue = round($monthlyRent + $chargesProvision, 2);
        if ($amountDue <= 0) {
            return $this->addSkipped($result, 'invalid_amount');
        }

        $dueDate = $this->dueDateForPeriod($periodStart, $startDate);
        $notes = sprintf(
            "Echeancier automatique:\n- Loyer du bail: %.2f EUR\n- Provision charges: %.2f EUR",
            $monthlyRent,
            $chargesProvision
        );

        $rent = $this->repository->createRent(
            $leaseId,
            $propertyId,
            $unitId,
            $periodYear,
            $periodMonth,
            $dueDate,
            $amountDue,
            'pending',
            $actorPrivateUserId,
            $notes
        );
        if (!is_array($rent)) {
            return $this->addSkipped($result, 'create_failed');
        }

        ++$result['created'];
        $result['rents'][] = $rent;

        return $result;
    }

    private function isValidPeriod(int $periodYear, int $periodMonth): bool
    {
        return $periodYear >= 2000 && $periodYear <= 2100 && $periodMonth >= 1 && $periodMonth <= 12;
    }

    /**
     * @return array{0:string, 1:string}|null
     */
    private function periodBounds(int $periodYear, int $periodMonth): ?array
    {
        if (!$this->isValidPeriod($periodYear, $periodMonth)) {
            return null;
        }

        $periodStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $periodYear, $periodMonth));

        return [
            $periodStart->format('Y-m-d'),
            $periodStart->modify('last day of this month')->format('Y-m-d'),
        ];
    }

    private function dueDateForPeriod(string $periodStart, string $leaseStartDate): string
    {
        if (substr($leaseStartDate, 0, 7) === substr($periodStart, 0, 7) && $leaseStartDate > $periodStart) {
            return $leaseStartDate;
        }

        return $periodStart;
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

    /**
     * @param array{created:int, existing:int, skipped:int, rents:array<int, array<string, mixed>>, reasons:array<string, int>} $result
     * @return array{created:int, existing:int, skipped:int, rents:array<int, array<string, mixed>>, reasons:array<string, int>}
     */
    private function addSkipped(array $result, string $reason): array
    {
        ++$result['skipped'];
        $result['reasons'][$reason] = ($result['reasons'][$reason] ?? 0) + 1;

        return $result;
    }
}
