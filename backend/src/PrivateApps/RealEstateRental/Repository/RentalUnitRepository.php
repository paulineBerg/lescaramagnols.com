<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Repository;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivateApps\RealEstateRental\Domain\RentalUnit;
use PDO;

final class RentalUnitRepository
{
    private const TEXT_LENGTH_LABEL = 160;
    private const TEXT_LENGTH_NOTES = 2000;
    private const ALLOWED_LABEL_PATTERN = '/^[\p{L}0-9][\p{L}0-9 _\-.,#()]{1,158}$/u';
    private const ALLOWED_STATUSES = ['available', 'occupied', 'maintenance', 'archived'];
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
    ): ?RentalUnit {
        $label = sanitize_text_field($label, 160);
        $notes = is_string($notes) ? sanitize_text_field($notes, 2000) : null;
        $status = strtolower(trim($status));

        if (
            $rentalPropertyId <= 0
            || $label === ''
            || $surface <= 0
            || strlen($label) > self::TEXT_LENGTH_LABEL
            || ($notes !== null && strlen($notes) > self::TEXT_LENGTH_NOTES)
            || !in_array($status, self::ALLOWED_STATUSES, true)
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
                        (`rental_property_id`, `label`, `surface`, `furnished`, `status`, `is_active`, `notes`, `created_by_private_user_id`)
                     VALUES
                        (:rental_property_id, :label, :surface, :furnished, :status, 1, :notes, :created_by)',
                    $this->table()
                )
            );
            $statement->execute([
                'rental_property_id' => $rentalPropertyId,
                'label' => $label,
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
    ): ?RentalUnit {
        if ($unitId <= 0 || $actorPrivateUserId <= 0 || $propertyId <= 0) {
            return null;
        }

        $label = sanitize_text_field($label, 160);
        $notes = is_string($notes) ? sanitize_text_field($notes, 2000) : null;
        $status = strtolower(trim($status));

        if (
            $label === ''
            || $surface <= 0
            || !in_array($status, self::ALLOWED_STATUSES, true)
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
                    `surface` DECIMAL(8,2) NOT NULL,
                    `furnished` TINYINT(1) NOT NULL DEFAULT 0,
                    `status` ENUM("available", "occupied", "maintenance", "archived") NOT NULL DEFAULT "available",
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `notes` TEXT NULL,
                    `created_by_private_user_id` INT NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `archived_at` DATETIME NULL,
                    `archived_by_private_user_id` INT NULL,
                    UNIQUE KEY `uq_rental_units_property_label` (`rental_property_id`, `label`),
                    KEY `idx_rental_units_property_active` (`rental_property_id`, `is_active`),
                    KEY `idx_rental_units_status` (`status`),
                    KEY `idx_rental_units_active` (`is_active`),
                    KEY `idx_rental_units_created` (`created_by_private_user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->table()
            )
        );

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
}
