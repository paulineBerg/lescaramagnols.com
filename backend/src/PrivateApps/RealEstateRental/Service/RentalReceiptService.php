<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Service;

use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
use Caramagnols\PrivatePortal\Documents\PrivateDocumentStorage;

final class RentalReceiptService
{
    public const DOCUMENT_RECEIPT = 'receipt';
    public const DOCUMENT_PARTIAL_RECEIPT = 'partial_receipt';

    public function __construct(
        private readonly RentalLifecycleRepository $repository,
        private readonly PrivateDocumentStorage $storage
    ) {
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<string, mixed>|null
     */
    public function generateForPayment(
        int $paymentId,
        array $propertyIds,
        int $actorPrivateUserId,
        string $documentType
    ): ?array {
        $documentType = $this->normalizeDocumentType($documentType);
        if ($paymentId <= 0 || $actorPrivateUserId <= 0 || $documentType === '') {
            return null;
        }

        $payment = $this->repository->findPaymentById($paymentId);
        $propertyId = is_array($payment) && is_numeric($payment['rentalPropertyId'] ?? null)
            ? (int) $payment['rentalPropertyId']
            : 0;
        if (!is_array($payment) || !in_array($propertyId, $propertyIds, true) || (string) ($payment['status'] ?? '') !== 'validated') {
            return null;
        }

        $rentId = is_numeric($payment['rentalRentId'] ?? null) ? (int) $payment['rentalRentId'] : 0;
        $rent = $this->repository->findRentDetailsById($rentId, $propertyIds);
        if (!is_array($rent)) {
            return null;
        }

        $amountDue = is_numeric($rent['amountDue'] ?? null) ? (float) $rent['amountDue'] : 0.0;
        $amountPaid = is_numeric($rent['amountPaid'] ?? null) ? (float) $rent['amountPaid'] : 0.0;
        $balance = max(0.0, $amountDue - $amountPaid);
        if ($documentType === self::DOCUMENT_RECEIPT && $balance > 0.001) {
            return null;
        }
        if ($documentType === self::DOCUMENT_PARTIAL_RECEIPT && ($amountPaid <= 0.001 || $balance <= 0.001)) {
            return null;
        }

        $snapshot = $this->snapshot($payment, $rent, $documentType, $amountDue, $amountPaid, $balance);
        $idempotencyKey = $this->idempotencyKey($snapshot);
        $existing = $this->repository->findGeneratedDocumentByIdempotencyKey($idempotencyKey);
        if (is_array($existing)) {
            return $this->storedDocumentIsReadable($existing) ? $existing : null;
        }

        $pdf = $this->pdf($snapshot);
        $documentId = $this->storage->generateDocumentId();
        $stored = $documentId !== ''
            ? $this->storage->storeGeneratedDocument($pdf, $documentId, $this->filename($snapshot))
            : null;
        if (!is_array($stored)) {
            return null;
        }

        return $this->repository->createGeneratedDocument(
            (int) ($snapshot['rentId'] ?? 0),
            (int) ($snapshot['leaseId'] ?? 0),
            $paymentId,
            (int) ($snapshot['propertyId'] ?? 0),
            (int) ($snapshot['unitId'] ?? 0),
            $documentType,
            (string) $stored['documentId'],
            (string) $stored['storagePath'],
            (string) $stored['originalName'],
            (string) $stored['mimeType'],
            (int) $stored['sizeBytes'],
            (string) $stored['sha256Hash'],
            $idempotencyKey,
            $snapshot,
            $actorPrivateUserId
        );
    }

    /**
     * @param array<string, mixed> $document
     */
    public function content(array $document): ?string
    {
        $storagePath = is_string($document['storagePath'] ?? null) ? (string) $document['storagePath'] : '';
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

        return $this->storage->absolutePath($storagePath);
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function pdf(array $snapshot): string
    {
        $title = (string) ($snapshot['title'] ?? 'Document locatif');
        $text = sprintf(
            "%s\nPeriode: %s\nPropriete: %s\nBien locatif: %s\nLocataire: %s\nDate de paiement: %s\nMontant attendu: %s EUR\nMontant encaisse total: %s EUR\nMontant de ce paiement: %s EUR\nSolde restant: %s EUR\nBail du: %s\n\nDocument genere le %s",
            $title,
            (string) ($snapshot['periodLabel'] ?? ''),
            (string) ($snapshot['propertyName'] ?? ''),
            (string) ($snapshot['unitLabel'] ?? ''),
            (string) ($snapshot['tenantName'] ?? ''),
            (string) ($snapshot['paymentDate'] ?? ''),
            (string) ($snapshot['amountDue'] ?? ''),
            (string) ($snapshot['amountPaid'] ?? ''),
            (string) ($snapshot['paymentAmount'] ?? ''),
            (string) ($snapshot['balanceDue'] ?? ''),
            (string) ($snapshot['leaseStartDate'] ?? ''),
            (string) ($snapshot['generatedAt'] ?? date('c'))
        );

        return "%PDF-1.4\n% Caramagnols private rental generated document\n" . $text . "\n%%EOF\n";
    }

    /**
     * @param array<string, mixed> $payment
     * @param array<string, mixed> $rent
     * @return array<string, mixed>
     */
    private function snapshot(array $payment, array $rent, string $documentType, float $amountDue, float $amountPaid, float $balance): array
    {
        $periodLabel = sprintf(
            '%04d-%02d',
            is_numeric($rent['periodYear'] ?? null) ? (int) $rent['periodYear'] : 0,
            is_numeric($rent['periodMonth'] ?? null) ? (int) $rent['periodMonth'] : 0
        );

        return [
            'documentType' => $documentType,
            'title' => $documentType === self::DOCUMENT_RECEIPT ? 'Quittance de loyer' : 'Recu partiel de loyer',
            'rentId' => is_numeric($rent['id'] ?? null) ? (int) $rent['id'] : 0,
            'leaseId' => is_numeric($rent['rentalLeaseId'] ?? null) ? (int) $rent['rentalLeaseId'] : 0,
            'paymentId' => is_numeric($payment['id'] ?? null) ? (int) $payment['id'] : 0,
            'propertyId' => is_numeric($rent['rentalPropertyId'] ?? null) ? (int) $rent['rentalPropertyId'] : 0,
            'unitId' => is_numeric($rent['rentalUnitId'] ?? null) ? (int) $rent['rentalUnitId'] : 0,
            'propertyName' => is_string($rent['propertyName'] ?? null) ? (string) $rent['propertyName'] : '',
            'unitLabel' => is_string($rent['unitLabel'] ?? null) ? (string) $rent['unitLabel'] : '',
            'tenantName' => is_string($rent['tenantName'] ?? null) ? (string) $rent['tenantName'] : '',
            'periodLabel' => $periodLabel,
            'paymentDate' => is_string($payment['paymentDate'] ?? null) ? (string) $payment['paymentDate'] : '',
            'leaseStartDate' => is_string($rent['leaseStartDate'] ?? null) ? (string) $rent['leaseStartDate'] : '',
            'amountDue' => $this->formatAmount($amountDue),
            'amountPaid' => $this->formatAmount($amountPaid),
            'paymentAmount' => $this->formatAmount(is_numeric($payment['amountPaid'] ?? null) ? (float) $payment['amountPaid'] : 0.0),
            'balanceDue' => $this->formatAmount($balance),
            'generatedAt' => date('c'),
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function idempotencyKey(array $snapshot): string
    {
        unset($snapshot['generatedAt']);

        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: serialize($snapshot));
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function filename(array $snapshot): string
    {
        $prefix = (string) ($snapshot['documentType'] ?? '') === self::DOCUMENT_RECEIPT ? 'quittance' : 'recu-partiel';

        return sprintf('%s-%s.pdf', $prefix, preg_replace('/[^0-9-]+/', '-', (string) ($snapshot['periodLabel'] ?? date('Y-m'))) ?: date('Y-m'));
    }

    /**
     * @param array<string, mixed> $document
     */
    private function storedDocumentIsReadable(array $document): bool
    {
        $content = $this->content($document);

        return is_string($content) && $content !== '';
    }

    private function normalizeDocumentType(string $documentType): string
    {
        $documentType = strtolower(trim($documentType));
        if ($documentType === 'auto') {
            return self::DOCUMENT_RECEIPT;
        }

        return in_array($documentType, [self::DOCUMENT_RECEIPT, self::DOCUMENT_PARTIAL_RECEIPT], true) ? $documentType : '';
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
