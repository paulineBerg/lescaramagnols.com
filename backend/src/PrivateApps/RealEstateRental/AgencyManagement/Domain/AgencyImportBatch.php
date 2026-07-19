<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain;

final class AgencyImportBatch
{
    public function __construct(
        public readonly int $id,
        public readonly int $createdByPrivateUserId,
        public readonly ?string $agencyName,
        public readonly string $status,
        public readonly ?string $sourceDirectory,
        public readonly int $fileCount,
        public readonly int $ignoredFileCount,
        public readonly int $duplicateFileCount,
        public readonly ?string $notes,
        public readonly string $createdAt,
        public readonly string $updatedAt
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromDatabaseRow(array $row): ?self
    {
        $id = is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0;
        $createdBy = is_numeric($row['created_by_private_user_id'] ?? null)
            ? (int) $row['created_by_private_user_id']
            : 0;
        $status = trim((string) ($row['status'] ?? ''));
        $createdAt = trim((string) ($row['created_at'] ?? ''));
        $updatedAt = trim((string) ($row['updated_at'] ?? ''));

        if ($id <= 0 || $createdBy <= 0 || $status === '' || $createdAt === '' || $updatedAt === '') {
            return null;
        }

        return new self(
            $id,
            $createdBy,
            self::nullableString($row['agency_name'] ?? null),
            $status,
            self::nullableString($row['source_directory'] ?? null),
            max(0, (int) ($row['file_count'] ?? 0)),
            max(0, (int) ($row['ignored_file_count'] ?? 0)),
            max(0, (int) ($row['duplicate_file_count'] ?? 0)),
            self::nullableString($row['notes'] ?? null),
            $createdAt,
            $updatedAt
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'createdByPrivateUserId' => $this->createdByPrivateUserId,
            'agencyName' => $this->agencyName,
            'status' => $this->status,
            'sourceDirectory' => $this->sourceDirectory,
            'fileCount' => $this->fileCount,
            'ignoredFileCount' => $this->ignoredFileCount,
            'duplicateFileCount' => $this->duplicateFileCount,
            'notes' => $this->notes,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
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
