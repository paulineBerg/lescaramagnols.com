<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Domain;

final class RentalPropertyMember
{
    public function __construct(
        public readonly int $id,
        public readonly int $rentalPropertyId,
        public readonly int $privateUserId,
        public readonly string $role,
        public readonly string $status,
        public readonly bool $isActive,
        public readonly int $addedByPrivateUserId,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?string $notes = null,
        public readonly ?string $removedAt = null,
        public readonly ?int $removedByPrivateUserId = null
    ) {
    }

    public static function fromDatabaseRow(array $row): ?self
    {
        $id = is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0;
        $rentalPropertyId = is_numeric($row['rental_property_id'] ?? null)
            ? (int) $row['rental_property_id']
            : 0;
        $privateUserId = is_numeric($row['private_user_id'] ?? null) ? (int) $row['private_user_id'] : 0;
        $addedBy = is_numeric($row['added_by_private_user_id'] ?? null)
            ? (int) $row['added_by_private_user_id']
            : 0;

        if ($id <= 0 || $rentalPropertyId <= 0 || $privateUserId <= 0 || $addedBy <= 0) {
            return null;
        }

        $role = trim((string) ($row['role'] ?? ''));
        $status = trim((string) ($row['status'] ?? ''));
        $createdAt = trim((string) ($row['created_at'] ?? ''));
        $updatedAt = trim((string) ($row['updated_at'] ?? ''));

        if ($role === '' || $status === '' || $createdAt === '' || $updatedAt === '') {
            return null;
        }

        $isActive = is_scalar($row['is_active'] ?? null) ? ((int) $row['is_active'] === 1) : true;

        $removedAt = is_string($row['removed_at'] ?? null) ? trim((string) $row['removed_at']) : null;
        if ($removedAt === '') {
            $removedAt = null;
        }

        $removedBy = is_numeric($row['removed_by_private_user_id'] ?? null)
            ? (int) $row['removed_by_private_user_id']
            : null;

        $notes = is_string($row['notes'] ?? null) ? trim((string) $row['notes']) : null;

        return new self(
            $id,
            $rentalPropertyId,
            $privateUserId,
            $role,
            $status,
            $isActive,
            $addedBy,
            $createdAt,
            $updatedAt,
            $notes === '' ? null : $notes,
            $removedAt,
            $removedBy
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'rentalPropertyId' => $this->rentalPropertyId,
            'privateUserId' => $this->privateUserId,
            'role' => $this->role,
            'status' => $this->status,
            'isActive' => $this->isActive,
            'notes' => $this->notes,
            'addedByPrivateUserId' => $this->addedByPrivateUserId,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'removedAt' => $this->removedAt,
            'removedByPrivateUserId' => $this->removedByPrivateUserId,
        ];
    }
}
