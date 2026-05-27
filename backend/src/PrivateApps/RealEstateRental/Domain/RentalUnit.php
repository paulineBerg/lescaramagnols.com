<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Domain;

final class RentalUnit
{
    public function __construct(
        public readonly int $id,
        public readonly int $rentalPropertyId,
        public readonly string $label,
        public readonly float $surface,
        public readonly bool $furnished,
        public readonly string $status,
        public readonly bool $isActive,
        public readonly ?string $notes,
        public readonly int $createdByPrivateUserId,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?string $archivedAt = null,
        public readonly ?int $archivedByPrivateUserId = null
    ) {
    }

    public static function fromDatabaseRow(array $row): ?self
    {
        $id = is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0;
        if ($id <= 0) {
            return null;
        }

        $propertyId = is_numeric($row['rental_property_id'] ?? null) ? (int) $row['rental_property_id'] : 0;
        if ($propertyId <= 0) {
            return null;
        }

        $createdBy = is_numeric($row['created_by_private_user_id'] ?? null)
            ? (int) $row['created_by_private_user_id']
            : 0;
        if ($createdBy <= 0) {
            return null;
        }

        $label = trim((string) ($row['label'] ?? ''));
        $status = trim((string) ($row['status'] ?? ''));
        $createdAt = trim((string) ($row['created_at'] ?? ''));
        $updatedAt = trim((string) ($row['updated_at'] ?? ''));
        $surface = is_numeric($row['surface'] ?? null) ? (float) $row['surface'] : 0.0;

        if ($label === '' || $status === '' || $createdAt === '' || $updatedAt === '') {
            return null;
        }

        $isActive = is_scalar($row['is_active'] ?? null) ? (bool) ((int) $row['is_active'] === 1) : true;
        $archivedAt = is_string($row['archived_at'] ?? null) ? trim((string) $row['archived_at']) : null;
        $archivedBy = is_numeric($row['archived_by_private_user_id'] ?? null)
            ? (int) $row['archived_by_private_user_id']
            : null;

        $notes = is_string($row['notes'] ?? null)
            ? trim((string) $row['notes'])
            : null;

        return new self(
            $id,
            $propertyId,
            $label,
            $surface,
            ((int) ($row['furnished'] ?? 0)) === 1,
            $status,
            $isActive,
            $notes === '' ? null : $notes,
            $createdBy,
            $createdAt,
            $updatedAt,
            $archivedAt === '' ? null : $archivedAt,
            $archivedBy
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'rentalPropertyId' => $this->rentalPropertyId,
            'label' => $this->label,
            'surface' => $this->surface,
            'furnished' => $this->furnished,
            'status' => $this->status,
            'isActive' => $this->isActive,
            'notes' => $this->notes,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'archivedAt' => $this->archivedAt,
            'createdByPrivateUserId' => $this->createdByPrivateUserId,
            'archivedByPrivateUserId' => $this->archivedByPrivateUserId,
        ];
    }
}
