<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain;

final class AgencyStatement
{
    public function __construct(
        public readonly int $id,
        public readonly int $importedDocumentId,
        public readonly ?int $rentalPropertyId,
        public readonly ?string $agencyName,
        public readonly string $parserProfile,
        public readonly ?string $statementPeriodStart,
        public readonly ?string $statementPeriodEnd,
        public readonly ?string $statementDate,
        public readonly ?string $statementNumber,
        public readonly ?string $ownerAccountReference,
        public readonly string $status,
        public readonly ?string $createdAt = null
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromDatabaseRow(array $row): ?self
    {
        $id = is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0;
        $importedDocumentId = is_numeric($row['imported_document_id'] ?? null)
            ? (int) $row['imported_document_id']
            : 0;
        $parserProfile = trim((string) ($row['parser_profile'] ?? ''));
        $status = trim((string) ($row['status'] ?? ''));

        if ($id <= 0 || $importedDocumentId <= 0 || $parserProfile === '' || $status === '') {
            return null;
        }

        return new self(
            $id,
            $importedDocumentId,
            is_numeric($row['rental_property_id'] ?? null) ? (int) $row['rental_property_id'] : null,
            self::nullableString($row['agency_name'] ?? null),
            $parserProfile,
            self::nullableString($row['statement_period_start'] ?? null),
            self::nullableString($row['statement_period_end'] ?? null),
            self::nullableString($row['statement_date'] ?? null),
            self::nullableString($row['statement_number'] ?? null),
            self::nullableString($row['owner_account_reference'] ?? null),
            $status,
            self::nullableString($row['created_at'] ?? null)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'importedDocumentId' => $this->importedDocumentId,
            'rentalPropertyId' => $this->rentalPropertyId,
            'agencyName' => $this->agencyName,
            'parserProfile' => $this->parserProfile,
            'statementPeriodStart' => $this->statementPeriodStart,
            'statementPeriodEnd' => $this->statementPeriodEnd,
            'statementDate' => $this->statementDate,
            'statementNumber' => $this->statementNumber,
            'ownerAccountReference' => $this->ownerAccountReference,
            'status' => $this->status,
            'createdAt' => $this->createdAt,
        ];
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
