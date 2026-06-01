<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Domain;

final class RentalUnit
{
    public function __construct(
        public readonly int $id,
        public readonly int $rentalPropertyId,
        public readonly string $label,
        public readonly string $unitType,
        public readonly ?string $address,
        public readonly ?string $building,
        public readonly ?string $floor,
        public readonly ?string $door,
        public readonly float $surface,
        public readonly bool $furnished,
        public readonly string $status,
        public readonly ?string $unavailableUntil,
        public readonly bool $isActive,
        public readonly ?string $notes,
        public readonly int $createdByPrivateUserId,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?string $archivedAt = null,
        public readonly ?int $archivedByPrivateUserId = null,
        public readonly ?string $taxIdentifier = null,
        public readonly ?int $roomCount = null,
        public readonly ?string $designation = null,
        public readonly ?string $otherDetails = null,
        public readonly ?string $equipmentElements = null,
        public readonly ?string $heatingProductionMode = null,
        public readonly ?string $hotWaterProductionMode = null,
        public readonly ?string $sanitation = null
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
        $status = self::normalizeStatus(trim((string) ($row['status'] ?? '')));
        $unavailableUntil = self::normalizeDate($row['unavailable_until'] ?? null);
        if ($status === 'unavailable' && $unavailableUntil !== null && $unavailableUntil < date('Y-m-d')) {
            $status = 'available';
        }
        $createdAt = trim((string) ($row['created_at'] ?? ''));
        $updatedAt = trim((string) ($row['updated_at'] ?? ''));
        $surface = is_numeric($row['surface'] ?? null) ? (float) $row['surface'] : 0.0;
        $unitType = trim((string) ($row['unit_type'] ?? 'other'));

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
            $unitType !== '' ? $unitType : 'other',
            self::nullableString($row['address'] ?? null),
            self::nullableString($row['building'] ?? null),
            self::nullableString($row['floor'] ?? null),
            self::nullableString($row['door'] ?? null),
            $surface,
            ((int) ($row['furnished'] ?? 0)) === 1,
            $status,
            $unavailableUntil,
            $isActive,
            $notes === '' ? null : $notes,
            $createdBy,
            $createdAt,
            $updatedAt,
            $archivedAt === '' ? null : $archivedAt,
            $archivedBy,
            self::nullableString($row['tax_identifier'] ?? null),
            self::nullableInteger($row['room_count'] ?? null),
            self::nullableString($row['designation'] ?? null),
            self::nullableString($row['other_details'] ?? null),
            self::nullableString($row['equipment_elements'] ?? null),
            self::nullableString($row['heating_production_mode'] ?? null),
            self::nullableString($row['hot_water_production_mode'] ?? null),
            self::nullableString($row['sanitation'] ?? null)
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'rentalPropertyId' => $this->rentalPropertyId,
            'label' => $this->label,
            'unitType' => $this->unitType,
            'address' => $this->address,
            'building' => $this->building,
            'floor' => $this->floor,
            'door' => $this->door,
            'surface' => $this->surface,
            'furnished' => $this->furnished,
            'status' => $this->status,
            'unavailableUntil' => $this->unavailableUntil,
            'isActive' => $this->isActive,
            'notes' => $this->notes,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'archivedAt' => $this->archivedAt,
            'createdByPrivateUserId' => $this->createdByPrivateUserId,
            'archivedByPrivateUserId' => $this->archivedByPrivateUserId,
            'taxIdentifier' => $this->taxIdentifier,
            'roomCount' => $this->roomCount,
            'designation' => $this->designation,
            'otherDetails' => $this->otherDetails,
            'equipmentElements' => $this->equipmentElements,
            'heatingProductionMode' => $this->heatingProductionMode,
            'hotWaterProductionMode' => $this->hotWaterProductionMode,
            'sanitation' => $this->sanitation,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        return $value !== '' ? $value : null;
    }

    private static function nullableInteger(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $value = (int) $value;
        return $value >= 0 ? $value : null;
    }

    private static function normalizeStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'available' => 'available',
            'unavailable', 'occupied', 'maintenance' => 'unavailable',
            'archived' => 'archived',
            default => '',
        };
    }

    private static function normalizeDate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
