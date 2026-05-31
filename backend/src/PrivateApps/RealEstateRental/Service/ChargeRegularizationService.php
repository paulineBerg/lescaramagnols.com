<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Service;

use Caramagnols\PrivateApps\RealEstateRental\Domain\RentalExpenseCategoryCatalog;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
use Caramagnols\PrivatePortal\Documents\PrivateDocumentStorage;

final class ChargeRegularizationService
{
    public function __construct(
        private readonly RentalLifecycleRepository $repository,
        private readonly PrivateDocumentStorage $storage
    ) {
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<string, mixed>|null
     */
    public function preview(int $propertyId, ?int $unitId, int $year, float $tenantSharePercent, array $propertyIds): ?array
    {
        $propertyIds = $this->normalizeIds($propertyIds);
        $unitId = $unitId !== null && $unitId > 0 ? $unitId : null;
        $year = $this->normalizeYear($year);
        $tenantSharePercent = $this->normalizeShare($tenantSharePercent);
        if ($propertyId <= 0 || !in_array($propertyId, $propertyIds, true) || $year <= 0) {
            return null;
        }

        $rents = $this->filterRowsByUnit($this->repository->listRents([$propertyId], $year, 1000), $unitId);
        $expenses = $this->filterRowsByUnit($this->repository->listExpenses([$propertyId], $year, 1000), $unitId, true);
        $documents = $this->documentsByExpenseId($this->repository->listDocuments([$propertyId], 1000));

        $provisionsAmount = 0.0;
        $rentRows = [];
        foreach ($rents as $rent) {
            if ((string) ($rent['status'] ?? '') === 'cancelled') {
                continue;
            }

            $chargesProvision = $this->amount($rent['leaseChargesProvision'] ?? 0);
            $provisionsAmount += $chargesProvision;
            $rentRows[] = [
                'id' => $this->integer($rent['id'] ?? 0),
                'periodYear' => $this->integer($rent['periodYear'] ?? 0),
                'periodMonth' => $this->integer($rent['periodMonth'] ?? 0),
                'chargesProvision' => $this->money($chargesProvision),
                'status' => (string) ($rent['status'] ?? ''),
            ];
        }

        $recoverableExpensesAmount = 0.0;
        $deductibleCandidateAmount = 0.0;
        $nonDeductibleAmount = 0.0;
        $expenseRows = [];
        foreach ($expenses as $expense) {
            if ((string) ($expense['status'] ?? '') !== 'validated') {
                continue;
            }

            $amount = $this->amount($expense['amount'] ?? 0);
            $isRecoverable = $this->integer($expense['isRecoverable'] ?? 0) === 1;
            $isDeductibleCandidate = $this->integer($expense['isDeductibleCandidate'] ?? 0) === 1;
            if ($isRecoverable) {
                $recoverableExpensesAmount += $amount;
            }
            if ($isDeductibleCandidate) {
                $deductibleCandidateAmount += $amount;
            } else {
                $nonDeductibleAmount += $amount;
            }

            $expenseId = $this->integer($expense['id'] ?? 0);
            $category = RentalExpenseCategoryCatalog::normalize((string) ($expense['expenseCategory'] ?? ''));
            $expenseRows[] = [
                'id' => $expenseId,
                'date' => (string) ($expense['expenseDate'] ?? ''),
                'taxYear' => $this->integer($expense['taxYear'] ?? 0),
                'category' => $category,
                'categoryLabel' => RentalExpenseCategoryCatalog::label($category),
                'label' => (string) ($expense['label'] ?? ''),
                'amount' => $this->money($amount),
                'recoverable' => $isRecoverable,
                'deductibleCandidate' => $isDeductibleCandidate,
                'supportingDocuments' => $documents[$expenseId] ?? [],
            ];
        }

        $tenantRecoverableAmount = round($recoverableExpensesAmount * ($tenantSharePercent / 100), 2);
        $balanceAmount = round($tenantRecoverableAmount - $provisionsAmount, 2);

        return [
            'propertyId' => $propertyId,
            'unitId' => $unitId,
            'year' => $year,
            'periodStart' => sprintf('%04d-01-01', $year),
            'periodEnd' => sprintf('%04d-12-31', $year),
            'tenantSharePercent' => $this->money($tenantSharePercent),
            'provisionsAmount' => $this->money($provisionsAmount),
            'recoverableExpensesAmount' => $this->money($recoverableExpensesAmount),
            'tenantRecoverableAmount' => $this->money($tenantRecoverableAmount),
            'balanceAmount' => $this->money($balanceAmount),
            'balanceDirection' => $this->balanceDirection($balanceAmount),
            'deductibleCandidateAmount' => $this->money($deductibleCandidateAmount),
            'nonDeductibleAmount' => $this->money($nonDeductibleAmount),
            'rentRows' => $rentRows,
            'expenseRows' => $expenseRows,
        ];
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<string, mixed>|null
     */
    public function generate(int $propertyId, ?int $unitId, int $year, float $tenantSharePercent, array $propertyIds, int $actorPrivateUserId): ?array
    {
        if ($actorPrivateUserId <= 0) {
            return null;
        }

        $snapshot = $this->preview($propertyId, $unitId, $year, $tenantSharePercent, $propertyIds);
        if (!is_array($snapshot)) {
            return null;
        }

        $idempotencyKey = $this->idempotencyKey($snapshot);
        $existing = $this->repository->findChargeRegularizationByIdempotencyKey($idempotencyKey);
        if (is_array($existing) && $this->content($existing) !== null) {
            return $existing;
        }

        $storedSnapshot = $snapshot;
        $storedSnapshot['generatedAt'] = date('c');
        $pdf = $this->pdf($storedSnapshot);
        $documentId = $this->storage->generateDocumentId();
        $stored = $documentId !== ''
            ? $this->storage->storeGeneratedDocument($pdf, $documentId, $this->filename($snapshot))
            : null;
        if (!is_array($stored)) {
            return null;
        }

        return $this->repository->createChargeRegularization(
            $propertyId,
            is_int($snapshot['unitId'] ?? null) ? (int) $snapshot['unitId'] : null,
            (int) $snapshot['year'],
            (string) $snapshot['periodStart'],
            (string) $snapshot['periodEnd'],
            (float) $snapshot['provisionsAmount'],
            (float) $snapshot['recoverableExpensesAmount'],
            (float) $snapshot['tenantSharePercent'],
            (float) $snapshot['tenantRecoverableAmount'],
            (float) $snapshot['balanceAmount'],
            (string) $stored['documentId'],
            (string) $stored['storagePath'],
            (string) $stored['originalName'],
            (string) $stored['mimeType'],
            (int) $stored['sizeBytes'],
            (string) $stored['sha256Hash'],
            $idempotencyKey,
            $storedSnapshot,
            $actorPrivateUserId
        );
    }

    /**
     * @param array<string, mixed> $document
     */
    public function content(array $document): ?string
    {
        $storagePath = is_string($document['storagePath'] ?? null) ? (string) $document['storagePath'] : '';
        if ($storagePath === '') {
            return null;
        }

        $absolutePath = $this->storage->absolutePath($storagePath);
        if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }

        $content = file_get_contents($absolutePath);
        if (!is_string($content)) {
            return null;
        }

        $hash = is_string($document['sha256Hash'] ?? null) ? strtolower((string) $document['sha256Hash']) : '';
        if ($hash !== '' && hash('sha256', $content) !== $hash) {
            return null;
        }

        return $content;
    }

    /**
     * @param array<string, mixed> $document
     */
    public function absolutePath(array $document): ?string
    {
        $storagePath = is_string($document['storagePath'] ?? null) ? (string) $document['storagePath'] : '';
        if ($storagePath === '') {
            return null;
        }

        return $this->storage->absolutePath($storagePath);
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function pdf(array $snapshot): string
    {
        $lines = [
            'Regularisation annuelle des charges',
            'Annee: ' . (string) ($snapshot['year'] ?? ''),
            'Provisions demandees: ' . $this->money($snapshot['provisionsAmount'] ?? 0) . ' EUR',
            'Charges recuperables reelles: ' . $this->money($snapshot['recoverableExpensesAmount'] ?? 0) . ' EUR',
            'Part locataire: ' . $this->money($snapshot['tenantSharePercent'] ?? 0) . ' %',
            'Part recuperable locataire: ' . $this->money($snapshot['tenantRecoverableAmount'] ?? 0) . ' EUR',
            'Solde: ' . $this->money($snapshot['balanceAmount'] ?? 0) . ' EUR',
            'Sens: ' . (string) ($snapshot['balanceDirection'] ?? ''),
            '',
            'Snapshot verifiable:',
            json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        ];

        return "%PDF-1.4\n% Caramagnols private rental charge regularization\n"
            . implode("\n", $lines)
            . "\n%%EOF\n";
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function filename(array $snapshot): string
    {
        $year = is_numeric($snapshot['year'] ?? null) ? (int) $snapshot['year'] : (int) date('Y');
        $propertyId = is_numeric($snapshot['propertyId'] ?? null) ? (int) $snapshot['propertyId'] : 0;
        $unitId = is_numeric($snapshot['unitId'] ?? null) ? (int) $snapshot['unitId'] : 0;
        $suffix = $unitId > 0 ? 'lot-' . $unitId : 'propriete';

        return sprintf('regularisation-charges-%d-bien-%d-%s.pdf', $year, $propertyId, $suffix);
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function idempotencyKey(array $snapshot): string
    {
        $payload = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', is_string($payload) ? $payload : '{}');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function filterRowsByUnit(array $rows, ?int $unitId, bool $includePropertyWide = false): array
    {
        if ($unitId === null) {
            return $rows;
        }

        $filtered = [];
        foreach ($rows as $row) {
            $rowUnitId = is_numeric($row['rentalUnitId'] ?? null) ? (int) $row['rentalUnitId'] : null;
            if ($rowUnitId === $unitId || ($includePropertyWide && $rowUnitId === null)) {
                $filtered[] = $row;
            }
        }

        return $filtered;
    }

    /**
     * @param array<int, array<string, mixed>> $documents
     * @return array<int, array<int, array{documentId:string,originalName:string,sha256Hash:string|null}>>
     */
    private function documentsByExpenseId(array $documents): array
    {
        $indexed = [];
        foreach ($documents as $document) {
            $expenseId = is_numeric($document['rentalExpenseId'] ?? null) ? (int) $document['rentalExpenseId'] : 0;
            $documentId = is_string($document['documentId'] ?? null) ? (string) $document['documentId'] : '';
            if ($expenseId <= 0 || $documentId === '') {
                continue;
            }

            $indexed[$expenseId][] = [
                'documentId' => $documentId,
                'originalName' => is_string($document['originalName'] ?? null) ? (string) $document['originalName'] : $documentId,
                'sha256Hash' => is_string($document['sha256Hash'] ?? null) ? (string) $document['sha256Hash'] : null,
            ];
        }

        return $indexed;
    }

    private function balanceDirection(float $balanceAmount): string
    {
        if ($balanceAmount > 0.001) {
            return 'tenant_due';
        }
        if ($balanceAmount < -0.001) {
            return 'tenant_refund';
        }

        return 'settled';
    }

    private function normalizeYear(int $year): int
    {
        return ($year >= 2000 && $year <= 2100) ? $year : 0;
    }

    private function normalizeShare(float $share): float
    {
        if ($share < 0.0) {
            return 0.0;
        }
        if ($share > 100.0) {
            return 100.0;
        }

        return round($share, 2);
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

    private function money(mixed $value): string
    {
        return number_format($this->amount($value), 2, '.', '');
    }
}
