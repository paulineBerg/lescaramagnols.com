<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Domain;

final class RentalProperty
{
    public function __construct(
        public readonly int $id,
        public readonly int $createdByPrivateUserId,
        public readonly ?int $rentalLessorId,
        public readonly string $name,
        public readonly string $address,
        public readonly string $propertyType,
        public readonly string $ownershipMode,
        public readonly string $status,
        public readonly bool $isActive,
        public readonly ?string $notes,
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

        $createdBy = is_numeric($row['created_by_private_user_id'] ?? null)
            ? (int) $row['created_by_private_user_id']
            : 0;
        if ($createdBy <= 0) {
            return null;
        }

        $name = trim((string) ($row['name'] ?? ''));
        $rentalLessorId = is_numeric($row['rental_lessor_id'] ?? null)
            ? (int) $row['rental_lessor_id']
            : null;
        $address = trim((string) ($row['address'] ?? ''));
        $propertyType = trim((string) ($row['property_type'] ?? ''));
        $ownershipMode = trim((string) ($row['ownership_mode'] ?? ''));
        $status = trim((string) ($row['status'] ?? ''));
        $createdAt = trim((string) ($row['created_at'] ?? ''));
        $updatedAt = trim((string) ($row['updated_at'] ?? ''));

        if ($name === '' || $address === '' || $propertyType === '' || $ownershipMode === '' || $status === '') {
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
            $createdBy,
            $rentalLessorId !== null && $rentalLessorId > 0 ? $rentalLessorId : null,
            $name,
            $address,
            $propertyType,
            $ownershipMode,
            $status,
            $isActive,
            $notes === '' ? null : $notes,
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
            'rentalLessorId' => $this->rentalLessorId,
            'name' => $this->name,
            'address' => $this->address,
            'propertyType' => $this->propertyType,
            'ownershipMode' => $this->ownershipMode,
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
