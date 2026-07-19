<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain;

final class AgencyStatementLine
{
    public function __construct(
        public readonly int $id,
        public readonly int $statementId,
        public readonly int $importedDocumentId,
        public readonly ?int $rentalPropertyId,
        public readonly ?int $rentalUnitId,
        public readonly string $rawLabel,
        public readonly string $mappedCategory,
        public readonly string $mappingStatus,
        public readonly ?float $amount,
        public readonly ?float $debitAmount,
        public readonly ?float $creditAmount,
        public readonly ?float $calledAmount,
        public readonly ?float $paidAmount,
        public readonly ?float $ownerTransferAmount,
        public readonly ?string $periodStart,
        public readonly ?string $periodEnd,
        public readonly ?string $lineDate,
        public readonly ?string $propertyLabel,
        public readonly ?string $unitLabel,
        public readonly ?string $tenantName,
        public readonly int $sourcePage,
        public readonly string $confidenceStatus,
        public readonly string $sourceLineHash
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromDatabaseRow(array $row): ?self
    {
        $id = is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0;
        $statementId = is_numeric($row['statement_id'] ?? null) ? (int) $row['statement_id'] : 0;
        $importedDocumentId = is_numeric($row['imported_document_id'] ?? null)
            ? (int) $row['imported_document_id']
            : 0;
        $rawLabel = trim((string) ($row['raw_label'] ?? ''));
        $mappedCategory = trim((string) ($row['mapped_category'] ?? ''));
        $mappingStatus = trim((string) ($row['mapping_status'] ?? ''));
        $sourceLineHash = trim((string) ($row['source_line_hash'] ?? ''));

        if (
            $id <= 0
            || $statementId <= 0
            || $importedDocumentId <= 0
            || $rawLabel === ''
            || $mappedCategory === ''
            || $mappingStatus === ''
            || $sourceLineHash === ''
        ) {
            return null;
        }

        return new self(
            $id,
            $statementId,
            $importedDocumentId,
            self::intOrNull($row['rental_property_id'] ?? null),
            self::intOrNull($row['rental_unit_id'] ?? null),
            $rawLabel,
            $mappedCategory,
            $mappingStatus,
            self::floatOrNull($row['amount'] ?? null),
            self::floatOrNull($row['debit_amount'] ?? null),
            self::floatOrNull($row['credit_amount'] ?? null),
            self::floatOrNull($row['called_amount'] ?? null),
            self::floatOrNull($row['paid_amount'] ?? null),
            self::floatOrNull($row['owner_transfer_amount'] ?? null),
            self::nullableString($row['period_start'] ?? null),
            self::nullableString($row['period_end'] ?? null),
            self::nullableString($row['line_date'] ?? null),
            self::nullableString($row['property_label'] ?? null),
            self::nullableString($row['unit_label'] ?? null),
            self::nullableString($row['tenant_name'] ?? null),
            max(1, (int) ($row['source_page'] ?? 1)),
            trim((string) ($row['confidence_status'] ?? 'review')) ?: 'review',
            $sourceLineHash
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'statementId' => $this->statementId,
            'importedDocumentId' => $this->importedDocumentId,
            'rentalPropertyId' => $this->rentalPropertyId,
            'rentalUnitId' => $this->rentalUnitId,
            'rawLabel' => $this->rawLabel,
            'mappedCategory' => $this->mappedCategory,
            'mappingStatus' => $this->mappingStatus,
            'amount' => $this->amount,
            'debitAmount' => $this->debitAmount,
            'creditAmount' => $this->creditAmount,
            'calledAmount' => $this->calledAmount,
            'paidAmount' => $this->paidAmount,
            'ownerTransferAmount' => $this->ownerTransferAmount,
            'periodStart' => $this->periodStart,
            'periodEnd' => $this->periodEnd,
            'lineDate' => $this->lineDate,
            'propertyLabel' => $this->propertyLabel,
            'unitLabel' => $this->unitLabel,
            'tenantName' => $this->tenantName,
            'sourcePage' => $this->sourcePage,
            'confidenceStatus' => $this->confidenceStatus,
            'sourceLineHash' => $this->sourceLineHash,
        ];
    }

    private static function floatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private static function intOrNull(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $value = (int) $value;
        return $value > 0 ? $value : null;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
