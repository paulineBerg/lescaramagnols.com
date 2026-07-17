<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Repository;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivateApps\RealEstateRental\Domain\RentalProperty;
use PDO;

final class RentalPropertyRepository
{
    private const ALLOWED_STATUSES = ['draft', 'active', 'archived'];
    private const TEXT_LENGTH_NAME = 160;
    private const TEXT_LENGTH_ADDRESS = 255;
    private const TEXT_LENGTH_TYPE = 64;
    private const ALLOWED_NAME_PATTERN = '/^[\p{L}0-9][\p{L}0-9 _\-.,\/\'#’()]{1,158}$/u';
    private const ALLOWED_ADDRESS_PATTERN = '/^[\p{L}0-9][\p{L}0-9 _\-.,\/\'#’()]{1,253}$/u';
    private const ALLOWED_TYPE_PATTERN = '/^[\p{L}0-9][\p{L}0-9 _\-.,\/\'#’()]{1,62}$/u';
    private bool $schemaReady = false;

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function table(): string
    {
        return $this->database->table('rental_properties');
    }

    public function findById(int $propertyId): ?RentalProperty
    {
        if ($propertyId <= 0) {
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
            $statement->execute(['id' => $propertyId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($row)) {
            return null;
        }

        return RentalProperty::fromDatabaseRow($row);
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<int, RentalProperty>
     */
    public function listByIds(array $propertyIds, int $limit = 100): array
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
                $placeholders[] = ':id' . $index;
            }

            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s`
                     WHERE `id` IN (%s)
                       AND `is_active` = 1
                     ORDER BY `name` ASC, `id` ASC
                     LIMIT :limit',
                    $this->table(),
                    implode(',', $placeholders)
                )
            );
            foreach ($propertyIds as $index => $propertyId) {
                $statement->bindValue(':id' . $index, $propertyId, PDO::PARAM_INT);
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

        $properties = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $property = RentalProperty::fromDatabaseRow($row);
            if ($property instanceof RentalProperty) {
                $properties[] = $property;
            }
        }

        return $properties;
    }

    public function create(
        int $createdByPrivateUserId,
        string $name,
        string $address,
        string $propertyType,
        string $ownershipMode,
        string $status,
        ?string $notes = null,
        ?int $rentalLessorId = null,
    ): ?RentalProperty {
        $name = sanitize_text_field($name, 160);
        $address = sanitize_text_field($address, 255);
        $propertyType = sanitize_text_field($propertyType, 64);
        $ownershipMode = sanitize_text_field($ownershipMode, 64);
        $status = trim($status);
        $notes = is_string($notes) ? trim($notes) : null;
        $rentalLessorId = $rentalLessorId !== null && $rentalLessorId > 0 ? $rentalLessorId : null;

        if (
            $createdByPrivateUserId <= 0
            || $name === ''
            || $address === ''
            || $propertyType === ''
            || $ownershipMode === ''
            || !in_array($status, self::ALLOWED_STATUSES, true)
            || strlen($name) > self::TEXT_LENGTH_NAME
            || strlen($address) > self::TEXT_LENGTH_ADDRESS
            || strlen($propertyType) > self::TEXT_LENGTH_TYPE
            || strlen($ownershipMode) > self::TEXT_LENGTH_TYPE
            || preg_match(self::ALLOWED_NAME_PATTERN, $name) !== 1
            || preg_match(self::ALLOWED_ADDRESS_PATTERN, $address) !== 1
            || preg_match(self::ALLOWED_TYPE_PATTERN, $propertyType) !== 1
            || preg_match(self::ALLOWED_TYPE_PATTERN, $ownershipMode) !== 1
        ) {
            return null;
        }

        if ($notes !== null && strlen($notes) > 2000) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`created_by_private_user_id`, `rental_lessor_id`, `name`, `address`, `property_type`, `ownership_mode`, `status`, `notes`)
                     VALUES
                        (:created_by_private_user_id, :rental_lessor_id, :name, :address, :property_type, :ownership_mode, :status, :notes)',
                    $this->table()
                )
            );
            $statement->execute([
                'created_by_private_user_id' => $createdByPrivateUserId,
                'rental_lessor_id' => $rentalLessorId,
                'name' => $name,
                'address' => $address,
                'property_type' => $propertyType,
                'ownership_mode' => $ownershipMode,
                'status' => $status,
                'notes' => $notes,
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

    public function update(
        int $propertyId,
        int $updatedByPrivateUserId,
        string $name,
        string $address,
        string $propertyType,
        string $ownershipMode,
        string $status,
        ?string $notes = null,
        ?int $rentalLessorId = null,
    ): ?RentalProperty {
        if ($propertyId <= 0 || $updatedByPrivateUserId <= 0) {
            return null;
        }

        $name = sanitize_text_field($name, 160);
        $address = sanitize_text_field($address, 255);
        $propertyType = sanitize_text_field($propertyType, 64);
        $ownershipMode = sanitize_text_field($ownershipMode, 64);
        $status = trim($status);
        $notes = is_string($notes) ? trim($notes) : null;
        $rentalLessorId = $rentalLessorId !== null && $rentalLessorId > 0 ? $rentalLessorId : null;

        if (
            $name === ''
            || $address === ''
            || $propertyType === ''
            || $ownershipMode === ''
            || !in_array($status, self::ALLOWED_STATUSES, true)
            || strlen($name) > self::TEXT_LENGTH_NAME
            || strlen($address) > self::TEXT_LENGTH_ADDRESS
            || strlen($propertyType) > self::TEXT_LENGTH_TYPE
            || strlen($ownershipMode) > self::TEXT_LENGTH_TYPE
            || ($notes !== null && strlen($notes) > 2000)
            || preg_match(self::ALLOWED_NAME_PATTERN, $name) !== 1
            || preg_match(self::ALLOWED_ADDRESS_PATTERN, $address) !== 1
            || preg_match(self::ALLOWED_TYPE_PATTERN, $propertyType) !== 1
            || preg_match(self::ALLOWED_TYPE_PATTERN, $ownershipMode) !== 1
        ) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `rental_lessor_id` = :rental_lessor_id,
                         `name` = :name,
                         `address` = :address,
                         `property_type` = :property_type,
                         `ownership_mode` = :ownership_mode,
                         `status` = :status,
                         `notes` = :notes,
                         `updated_at` = :updated_at
                     WHERE `id` = :id
                       AND `is_active` = 1',
                    $this->table()
                )
            );

            $statement->execute([
                'rental_lessor_id' => $rentalLessorId,
                'name' => $name,
                'address' => $address,
                'property_type' => $propertyType,
                'ownership_mode' => $ownershipMode,
                'status' => $status,
                'notes' => $notes,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $propertyId,
            ]);

            if ($statement->rowCount() === 0) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return $this->findById($propertyId);
    }

    public function archive(int $propertyId, int $actorUserId): bool
    {
        if ($propertyId <= 0 || $actorUserId <= 0) {
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
                'actor' => $actorUserId,
                'id' => $propertyId,
            ]);
        } catch (\Throwable) {
            return false;
        }

        return $statement->rowCount() > 0;
    }

    private function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        $this->database->ensureReady();
        $this->database->pdo()->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `created_by_private_user_id` INT NOT NULL,
                    `rental_lessor_id` INT NULL,
                    `name` VARCHAR(160) NOT NULL,
                    `address` VARCHAR(255) NOT NULL,
                    `property_type` VARCHAR(64) NOT NULL,
                    `ownership_mode` VARCHAR(64) NOT NULL,
                    `status` ENUM("draft", "active", "archived") NOT NULL DEFAULT "active",
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `notes` TEXT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `archived_at` DATETIME NULL,
                    `archived_by_private_user_id` INT NULL,
                    UNIQUE KEY `uq_rental_properties_name_owner` (`name`, `created_by_private_user_id`),
                    KEY `idx_rental_properties_active` (`is_active`),
                    KEY `idx_rental_properties_status` (`status`),
                    KEY `idx_rental_properties_lessor` (`rental_lessor_id`),
                    KEY `idx_rental_properties_created` (`created_by_private_user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->table()
            )
        );
        $pdo = $this->database->pdo();
        $this->ensureColumn($pdo, $this->table(), 'rental_lessor_id', '`rental_lessor_id` INT NULL AFTER `created_by_private_user_id`');
        $this->ensureIndex($pdo, $this->table(), 'idx_rental_properties_lessor', '`rental_lessor_id`');

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

    private function ensureIndex(PDO $pdo, string $table, string $index, string $columns): void
    {
        try {
            $statement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND INDEX_NAME = :index'
            );
            $statement->execute(['table' => $table, 'index' => $index]);
            if ((int) $statement->fetchColumn() > 0) {
                return;
            }

            $pdo->exec(sprintf('ALTER TABLE `%s` ADD INDEX `%s` (%s)', $table, $index, $columns));
        } catch (\Throwable) {
            return;
        }
    }
}
