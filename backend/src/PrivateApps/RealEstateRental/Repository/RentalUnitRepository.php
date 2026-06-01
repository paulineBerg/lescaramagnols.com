<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Repository;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivateApps\RealEstateRental\Domain\RentalUnit;
use PDO;

final class RentalUnitRepository
{
    private const TEXT_LENGTH_LABEL = 160;
    private const TEXT_LENGTH_ADDRESS = 255;
    private const TEXT_LENGTH_BUILDING = 120;
    private const TEXT_LENGTH_SHORT = 64;
    private const TEXT_LENGTH_MEDIUM = 160;
    private const TEXT_LENGTH_NOTES = 2000;
    private const ALLOWED_LABEL_PATTERN = '/^[\p{L}0-9][\p{L}0-9 _\-.,#()]{1,158}$/u';
    private const ALLOWED_UNIT_TYPES = [
        'apartment',
        'house',
        'garage',
        'parking',
        'commercial_space',
        'room',
        'storage',
        'other',
    ];
    private const ALLOWED_STATUSES = ['available', 'unavailable'];
    private const MAX_SURFACE = 10000.0;
    private const MIN_SURFACE = 0.5;
    private bool $schemaReady = false;

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function table(): string
    {
        return $this->database->table('rental_units');
    }

    public function findById(int $unitId): ?RentalUnit
    {
        if ($unitId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s` WHERE `id` = :id LIMIT 1',
                    $this->table()
                )
            );
            $statement->execute(['id' => $unitId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($row)) {
            return null;
        }

        return RentalUnit::fromDatabaseRow($row);
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<int, RentalUnit>
     */
    public function listByPropertyIds(array $propertyIds, int $limit = 300): array
    {
        $propertyIds = $this->normalizeIds($propertyIds);
        $limit = $this->normalizeLimit($limit);
        if ($propertyIds === [] || $limit <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $placeholders = [];
            foreach (array_keys($propertyIds) as $index) {
                $placeholders[] = ':property_id' . $index;
            }

            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s`
                     WHERE `rental_property_id` IN (%s)
                       AND `is_active` = 1
                     ORDER BY `rental_property_id` ASC, `label` ASC
                     LIMIT :limit',
                    $this->table(),
                    implode(',', $placeholders)
                )
            );
            foreach ($propertyIds as $index => $propertyId) {
                $statement->bindValue(':property_id' . $index, $propertyId, PDO::PARAM_INT);
            }

            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($rows)) {
            return [];
        }

        $units = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $unit = RentalUnit::fromDatabaseRow($row);
            if ($unit instanceof RentalUnit) {
                $units[] = $unit;
            }
        }

        return $units;
    }

    public function create(
        int $rentalPropertyId,
        string $label,
        float $surface,
        bool $furnished,
        string $status,
        ?string $notes,
        int $createdByPrivateUserId,
        string $unitType = 'other',
        ?string $address = null,
        ?string $building = null,
        ?string $floor = null,
        ?string $door = null,
        array $details = [],
    ): ?RentalUnit {
        $label = sanitize_text_field($label, 160);
        $unitType = $this->normalizeUnitType($unitType);
        $address = $this->normalizeNullableText($address, self::TEXT_LENGTH_ADDRESS);
        $building = $this->normalizeNullableText($building, self::TEXT_LENGTH_BUILDING);
        $floor = $this->normalizeNullableText($floor, self::TEXT_LENGTH_SHORT);
        $door = $this->normalizeNullableText($door, self::TEXT_LENGTH_SHORT);
        $details = $this->normalizeDetails($details);
        $notes = $this->normalizeNullableText($notes, self::TEXT_LENGTH_NOTES);
        $status = $this->normalizeStatus($status);

        if (
            $rentalPropertyId <= 0
            || $label === ''
            || $unitType === ''
            || $surface <= 0
            || strlen($label) > self::TEXT_LENGTH_LABEL
            || ($notes !== null && strlen($notes) > self::TEXT_LENGTH_NOTES)
            || $status === ''
            || $createdByPrivateUserId <= 0
        ) {
            return null;
        }

        if (preg_match(self::ALLOWED_LABEL_PATTERN, $label) !== 1) {
            return null;
        }

        $surface = round($surface, 2);

        if ($surface > self::MAX_SURFACE || $surface < self::MIN_SURFACE) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`rental_property_id`, `label`, `unit_type`, `address`, `building`, `floor`, `door`,
                         `tax_identifier`, `room_count`, `designation`, `other_details`, `equipment_elements`,
                         `heating_production_mode`, `hot_water_production_mode`, `sanitation`,
                         `surface`, `furnished`, `status`, `is_active`, `notes`, `created_by_private_user_id`)
                     VALUES
                        (:rental_property_id, :label, :unit_type, :address, :building, :floor, :door,
                         :tax_identifier, :room_count, :designation, :other_details, :equipment_elements,
                         :heating_production_mode, :hot_water_production_mode, :sanitation,
                         :surface, :furnished, :status, 1, :notes, :created_by)',
                    $this->table()
                )
            );
            $statement->execute([
                'rental_property_id' => $rentalPropertyId,
                'label' => $label,
                'unit_type' => $unitType,
                'address' => $address,
                'building' => $building,
                'floor' => $floor,
                'door' => $door,
                'tax_identifier' => $details['tax_identifier'],
                'room_count' => $details['room_count'],
                'designation' => $details['designation'],
                'other_details' => $details['other_details'],
                'equipment_elements' => $details['equipment_elements'],
                'heating_production_mode' => $details['heating_production_mode'],
                'hot_water_production_mode' => $details['hot_water_production_mode'],
                'sanitation' => $details['sanitation'],
                'surface' => $surface,
                'furnished' => $furnished ? 1 : 0,
                'status' => $status,
                'notes' => $notes,
                'created_by' => $createdByPrivateUserId,
            ]);
            $id = (int) $this->database->pdo()->lastInsertId();
            if ($id <= 0) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return $this->findById($id);
    }

    public function archive(int $unitId, int $actorPrivateUserId): bool
    {
        if ($unitId <= 0 || $actorPrivateUserId <= 0) {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `status` = :status,
                         `is_active` = 0,
                         `archived_at` = :archived_at,
                         `archived_by_private_user_id` = :actor
                     WHERE `id` = :id
                       AND `is_active` = 1',
                    $this->table()
                )
            );
            $statement->execute([
                'status' => 'archived',
                'archived_at' => date('Y-m-d H:i:s'),
                'actor' => $actorPrivateUserId,
                'id' => $unitId,
            ]);

            return $statement->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function archiveByPropertyId(int $propertyId, int $actorPrivateUserId): void
    {
        if ($propertyId <= 0 || $actorPrivateUserId <= 0) {
            return;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `status` = :status,
                         `is_active` = 0,
                         `archived_at` = :archived_at,
                         `archived_by_private_user_id` = :actor
                     WHERE `rental_property_id` = :property_id
                       AND `is_active` = 1',
                    $this->table()
                )
            );
            $statement->execute([
                'status' => 'archived',
                'archived_at' => date('Y-m-d H:i:s'),
                'actor' => $actorPrivateUserId,
                'property_id' => $propertyId,
            ]);
        } catch (\Throwable) {
            return;
        }
    }

    public function update(
        int $unitId,
        int $actorPrivateUserId,
        int $propertyId,
        string $label,
        float $surface,
        bool $furnished,
        string $status,
        ?string $notes,
        string $unitType = 'other',
        ?string $address = null,
        ?string $building = null,
        ?string $floor = null,
        ?string $door = null,
        array $details = [],
    ): ?RentalUnit {
        if ($unitId <= 0 || $actorPrivateUserId <= 0 || $propertyId <= 0) {
            return null;
        }

        $label = sanitize_text_field($label, 160);
        $unitType = $this->normalizeUnitType($unitType);
        $address = $this->normalizeNullableText($address, self::TEXT_LENGTH_ADDRESS);
        $building = $this->normalizeNullableText($building, self::TEXT_LENGTH_BUILDING);
        $floor = $this->normalizeNullableText($floor, self::TEXT_LENGTH_SHORT);
        $door = $this->normalizeNullableText($door, self::TEXT_LENGTH_SHORT);
        $details = $this->normalizeDetails($details);
        $notes = $this->normalizeNullableText($notes, self::TEXT_LENGTH_NOTES);
        $status = $this->normalizeStatus($status);

        if (
            $label === ''
            || $unitType === ''
            || $surface <= 0
            || $status === ''
            || strlen($label) > self::TEXT_LENGTH_LABEL
            || ($notes !== null && strlen($notes) > self::TEXT_LENGTH_NOTES)
            || !preg_match(self::ALLOWED_LABEL_PATTERN, $label)
            || $surface > self::MAX_SURFACE || $surface < self::MIN_SURFACE
        ) {
            return null;
        }

        $surface = round($surface, 2);

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `rental_property_id` = :property_id,
                         `label` = :label,
                         `unit_type` = :unit_type,
                         `address` = :address,
                         `building` = :building,
                         `floor` = :floor,
                         `door` = :door,
                         `tax_identifier` = :tax_identifier,
                         `room_count` = :room_count,
                         `designation` = :designation,
                         `other_details` = :other_details,
                         `equipment_elements` = :equipment_elements,
                         `heating_production_mode` = :heating_production_mode,
                         `hot_water_production_mode` = :hot_water_production_mode,
                         `sanitation` = :sanitation,
                         `surface` = :surface,
                         `furnished` = :furnished,
                         `status` = :status,
                         `notes` = :notes,
                         `updated_at` = :updated_at
                     WHERE `id` = :id
                       AND `is_active` = 1',
                    $this->table()
                )
            );
            $statement->execute([
                'property_id' => $propertyId,
                'label' => $label,
                'unit_type' => $unitType,
                'address' => $address,
                'building' => $building,
                'floor' => $floor,
                'door' => $door,
                'tax_identifier' => $details['tax_identifier'],
                'room_count' => $details['room_count'],
                'designation' => $details['designation'],
                'other_details' => $details['other_details'],
                'equipment_elements' => $details['equipment_elements'],
                'heating_production_mode' => $details['heating_production_mode'],
                'hot_water_production_mode' => $details['hot_water_production_mode'],
                'sanitation' => $details['sanitation'],
                'surface' => $surface,
                'furnished' => $furnished ? 1 : 0,
                'status' => $status,
                'notes' => $notes,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $unitId,
            ]);

            if ($statement->rowCount() === 0) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return $this->findById($unitId);
    }

    public function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        $this->database->ensureReady();
        $pdo = $this->database->pdo();
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `rental_property_id` INT NOT NULL,
                    `label` VARCHAR(160) NOT NULL,
                    `unit_type` VARCHAR(64) NOT NULL DEFAULT "other",
                    `address` VARCHAR(255) NULL,
                    `building` VARCHAR(120) NULL,
                    `floor` VARCHAR(64) NULL,
                    `door` VARCHAR(64) NULL,
                    `surface` DECIMAL(8,2) NOT NULL,
                    `furnished` TINYINT(1) NOT NULL DEFAULT 0,
                    `status` ENUM("available", "unavailable", "archived") NOT NULL DEFAULT "available",
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `notes` TEXT NULL,
                    `created_by_private_user_id` INT NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `archived_at` DATETIME NULL,
                    `archived_by_private_user_id` INT NULL,
                    UNIQUE KEY `uq_rental_units_property_label` (`rental_property_id`, `label`),
                    KEY `idx_rental_units_property_active` (`rental_property_id`, `is_active`),
                    KEY `idx_rental_units_type` (`unit_type`),
                    KEY `idx_rental_units_status` (`status`),
                    KEY `idx_rental_units_active` (`is_active`),
                    KEY `idx_rental_units_created` (`created_by_private_user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->table()
            )
        );
        $this->ensureColumn($pdo, $this->table(), 'unit_type', '`unit_type` VARCHAR(64) NOT NULL DEFAULT "other" AFTER `label`');
        $this->ensureColumn($pdo, $this->table(), 'address', '`address` VARCHAR(255) NULL AFTER `unit_type`');
        $this->ensureColumn($pdo, $this->table(), 'building', '`building` VARCHAR(120) NULL AFTER `address`');
        $this->ensureColumn($pdo, $this->table(), 'floor', '`floor` VARCHAR(64) NULL AFTER `building`');
        $this->ensureColumn($pdo, $this->table(), 'door', '`door` VARCHAR(64) NULL AFTER `floor`');
        $this->ensureColumn($pdo, $this->table(), 'tax_identifier', '`tax_identifier` VARCHAR(80) NULL AFTER `door`');
        $this->ensureColumn($pdo, $this->table(), 'room_count', '`room_count` SMALLINT UNSIGNED NULL AFTER `tax_identifier`');
        $this->ensureColumn($pdo, $this->table(), 'designation', '`designation` VARCHAR(160) NULL AFTER `room_count`');
        $this->ensureColumn($pdo, $this->table(), 'other_details', '`other_details` TEXT NULL AFTER `designation`');
        $this->ensureColumn($pdo, $this->table(), 'equipment_elements', '`equipment_elements` TEXT NULL AFTER `other_details`');
        $this->ensureColumn($pdo, $this->table(), 'heating_production_mode', '`heating_production_mode` VARCHAR(160) NULL AFTER `equipment_elements`');
        $this->ensureColumn($pdo, $this->table(), 'hot_water_production_mode', '`hot_water_production_mode` VARCHAR(160) NULL AFTER `heating_production_mode`');
        $this->ensureColumn($pdo, $this->table(), 'sanitation', '`sanitation` VARCHAR(160) NULL AFTER `hot_water_production_mode`');
        $this->ensureAvailabilityStatusSchema($pdo);
        $this->ensureIndex($pdo, $this->table(), 'idx_rental_units_type', '`unit_type`');

        $this->schemaReady = true;
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<int, int>
     */
    private function normalizeIds(array $propertyIds): array
    {
        $normalized = [];
        foreach ($propertyIds as $propertyId) {
            if (!is_numeric($propertyId) || (int) $propertyId <= 0) {
                continue;
            }

            $normalized[] = (int) $propertyId;
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeLimit(int $limit): int
    {
        if ($limit <= 0) {
            return 0;
        }

        return min($limit, 500);
    }

    private function normalizeUnitType(string $unitType): string
    {
        $unitType = strtolower(trim($unitType));
        return in_array($unitType, self::ALLOWED_UNIT_TYPES, true) ? $unitType : '';
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if (in_array($status, self::ALLOWED_STATUSES, true)) {
            return $status;
        }

        return match ($status) {
            'occupied', 'maintenance' => 'unavailable',
            default => '',
        };
    }

    private function normalizeNullableText(?string $value, int $maxLength): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = sanitize_text_field($value, $maxLength);
        return $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function normalizeDetails(array $details): array
    {
        return [
            'tax_identifier' => $this->normalizeNullableText(
                $this->stringDetail($details, 'tax_identifier', 'taxIdentifier'),
                80
            ),
            'room_count' => $this->normalizeNullableRoomCount($details['room_count'] ?? $details['roomCount'] ?? null),
            'designation' => $this->normalizeNullableText(
                $this->stringDetail($details, 'designation'),
                self::TEXT_LENGTH_MEDIUM
            ),
            'other_details' => $this->normalizeNullableText(
                $this->stringDetail($details, 'other_details', 'otherDetails'),
                self::TEXT_LENGTH_NOTES
            ),
            'equipment_elements' => $this->normalizeNullableText(
                $this->stringDetail($details, 'equipment_elements', 'equipmentElements'),
                self::TEXT_LENGTH_NOTES
            ),
            'heating_production_mode' => $this->normalizeNullableText(
                $this->stringDetail($details, 'heating_production_mode', 'heatingProductionMode'),
                self::TEXT_LENGTH_MEDIUM
            ),
            'hot_water_production_mode' => $this->normalizeNullableText(
                $this->stringDetail($details, 'hot_water_production_mode', 'hotWaterProductionMode'),
                self::TEXT_LENGTH_MEDIUM
            ),
            'sanitation' => $this->normalizeNullableText(
                $this->stringDetail($details, 'sanitation'),
                self::TEXT_LENGTH_MEDIUM
            ),
        ];
    }

    private function stringDetail(array $details, string $primaryKey, ?string $fallbackKey = null): ?string
    {
        $value = $details[$primaryKey] ?? ($fallbackKey !== null ? ($details[$fallbackKey] ?? null) : null);

        return is_scalar($value) ? (string) $value : null;
    }

    private function normalizeNullableRoomCount(mixed $value): ?int
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $roomCount = (int) $value;
        return $roomCount >= 0 && $roomCount <= 99 ? $roomCount : null;
    }

    private function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        try {
            $statement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND COLUMN_NAME = :column'
            );
            $statement->execute(['table' => $table, 'column' => $column]);
            if ((int) $statement->fetchColumn() > 0) {
                return;
            }

            $pdo->exec(sprintf('ALTER TABLE `%s` ADD COLUMN %s', $table, $definition));
        } catch (\Throwable) {
            return;
        }
    }

    private function ensureAvailabilityStatusSchema(PDO $pdo): void
    {
        try {
            $table = $this->table();
            $pdo->exec(sprintf(
                'ALTER TABLE `%s` MODIFY COLUMN `status` ENUM("available", "unavailable", "occupied", "maintenance", "archived") NOT NULL DEFAULT "available"',
                $table
            ));
            $pdo->exec(sprintf(
                'UPDATE `%s` SET `status` = "unavailable" WHERE `status` IN ("occupied", "maintenance")',
                $table
            ));
            $pdo->exec(sprintf(
                'ALTER TABLE `%s` MODIFY COLUMN `status` ENUM("available", "unavailable", "archived") NOT NULL DEFAULT "available"',
                $table
            ));
        } catch (\Throwable) {
            return;
        }
    }

    private function ensureIndex(PDO $pdo, string $table, string $index, string $columns): void
    {
        try {
            $statement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND INDEX_NAME = :index_name'
            );
            $statement->execute(['table' => $table, 'index_name' => $index]);
            if ((int) $statement->fetchColumn() > 0) {
                return;
            }

            $pdo->exec(sprintf('ALTER TABLE `%s` ADD KEY `%s` (%s)', $table, $index, $columns));
        } catch (\Throwable) {
            return;
        }
    }
}
