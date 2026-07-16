<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Service;

use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
use DateTimeImmutable;

final class RentPaymentStatusService
{
    private const PAYING_KINDS = ['tenant', 'caf', 'adjustment'];

    public function __construct(private readonly RentalLifecycleRepository $repository)
    {
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array{refreshed:int, changed:int}
     */
    public function refreshPropertyRents(array $propertyIds, ?DateTimeImmutable $today = null): array
    {
        $result = ['refreshed' => 0, 'changed' => 0];
        foreach ($this->repository->listRents($propertyIds, null, 1000) as $rent) {
            $rentId = is_numeric($rent['id'] ?? null) ? (int) $rent['id'] : 0;
            if ($rentId <= 0) {
                continue;
            }

            $before = is_string($rent['status'] ?? null) ? (string) $rent['status'] : '';
            $refreshed = $this->refreshRentStatus($rentId, $today);
            if (!is_array($refreshed)) {
                continue;
            }

            ++$result['refreshed'];
            $after = is_string($refreshed['status'] ?? null) ? (string) $refreshed['status'] : '';
            if ($after !== $before) {
                ++$result['changed'];
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function refreshRentStatus(int $rentId, ?DateTimeImmutable $today = null): ?array
    {
        $rent = $this->repository->findRentById($rentId);
        if (!is_array($rent)) {
            return null;
        }

        $status = $this->statusForRent($rent, $this->repository->listPaymentsForRent($rentId), $today);
        return $this->repository->updateRentStatus($rentId, $status);
    }

    /**
     * @param array<string, mixed> $rent
     * @param array<int, array<string, mixed>> $payments
     */
    public function statusForRent(array $rent, array $payments, ?DateTimeImmutable $today = null): string
    {
        if (($rent['status'] ?? '') === 'cancelled') {
            return 'cancelled';
        }

        $amountDue = $this->amount($rent['amountDue'] ?? 0);
        $amountPaid = $this->effectivePaidAmount($payments);
        if ($amountDue <= 0) {
            return 'pending';
        }
        if ($amountPaid + 0.009 >= $amountDue) {
            return 'paid';
        }
        if ($this->isLate($rent, $today)) {
            return 'late';
        }
        if ($amountPaid > 0) {
            return 'partial';
        }

        return 'pending';
    }

    public function wouldOverpay(int $rentId, float $amount, string $paymentKind, ?int $ignoredPaymentId = null): bool
    {
        $rent = $this->repository->findRentById($rentId);
        if (!is_array($rent) || ($rent['status'] ?? '') === 'cancelled') {
            return false;
        }

        $amountDue = $this->amount($rent['amountDue'] ?? 0);
        if ($amountDue <= 0 || $this->effectiveAmount($amount, $paymentKind) <= 0) {
            return false;
        }

        $currentPaid = $this->effectivePaidAmount($this->repository->listPaymentsForRent($rentId), $ignoredPaymentId);

        return $currentPaid + $this->effectiveAmount($amount, $paymentKind) > $amountDue + 0.009;
    }

    /**
     * @param array<int, array<string, mixed>> $payments
     */
    public function effectivePaidAmount(array $payments, ?int $ignoredPaymentId = null): float
    {
        $amount = 0.0;
        foreach ($payments as $payment) {
            $paymentId = is_numeric($payment['id'] ?? null) ? (int) $payment['id'] : 0;
            if ($ignoredPaymentId !== null && $paymentId === $ignoredPaymentId) {
                continue;
            }
            if (($payment['status'] ?? '') !== 'validated') {
                continue;
            }

            $amount += $this->effectiveAmount($this->amount($payment['amountPaid'] ?? 0), (string) ($payment['paymentKind'] ?? 'tenant'));
        }

        return round($amount, 2);
    }

    private function effectiveAmount(float $amount, string $paymentKind): float
    {
        $paymentKind = strtolower(trim($paymentKind));
        if ($paymentKind === 'refund') {
            return -round($amount, 2);
        }
        if (!in_array($paymentKind, self::PAYING_KINDS, true)) {
            return 0.0;
        }

        return round($amount, 2);
    }

    /**
     * @param array<string, mixed> $rent
     */
    private function isLate(array $rent, ?DateTimeImmutable $today = null): bool
    {
        $dueDate = is_string($rent['dueDate'] ?? null) ? trim((string) $rent['dueDate']) : '';
        if ($dueDate === '' || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $dueDate) !== 1) {
            return false;
        }

        $today ??= new DateTimeImmutable('today');

        return $dueDate < $today->format('Y-m-d');
    }

    private function amount(mixed $value): float
    {
        return is_numeric($value) ? round((float) $value, 2) : 0.0;
    }
}
