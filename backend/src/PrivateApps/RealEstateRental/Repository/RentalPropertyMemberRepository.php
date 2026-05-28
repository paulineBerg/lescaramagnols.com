<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Repository;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivateApps\RealEstateRental\Domain\RentalPropertyMember;
use PDO;

final class RentalPropertyMemberRepository
{
    private const ALLOWED_ROLES = ['owner', 'co_owner', 'occupant', 'manager'];
    private bool $schemaReady = false;

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function table(): string
    {
        return $this->database->table('rental_property_members');
    }

    public function findById(int $memberId): ?RentalPropertyMember
    {
        if ($memberId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT m.*, p.`name` as `property_name`, u.`email` as `private_user_email`
                     FROM `%s` m
                     INNER JOIN `%s` p ON p.`id` = m.`rental_property_id`
                     INNER JOIN `%s` u ON u.`id` = m.`private_user_id`
                     WHERE m.`id` = :id
                     LIMIT 1',
                    $this->table(),
                    $this->database->table('rental_properties'),
                    $this->database->table('private_users')
                )
            );
            $statement->execute(['id' => $memberId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($row)) {
            return null;
        }

        return RentalPropertyMember::fromDatabaseRow($row);
    }

    public function isActiveMember(int $propertyId, int $privateUserId): bool
    {
        if ($propertyId <= 0 || $privateUserId <= 0) {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT 1
                     FROM `%s`
                     WHERE `rental_property_id` = :property_id
                       AND `private_user_id` = :private_user_id
                       AND `is_active` = 1
                       AND `status` = :status
                     LIMIT 1',
                    $this->table()
                )
            );
            $statement->execute([
                'property_id' => $propertyId,
                'private_user_id' => $privateUserId,
                'status' => 'active',
            ]);

            return (bool) $statement->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    public function activeRole(int $propertyId, int $privateUserId): ?string
    {
        if ($propertyId <= 0 || $privateUserId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT `role`
                     FROM `%s`
                     WHERE `rental_property_id` = :property_id
                       AND `private_user_id` = :private_user_id
                       AND `is_active` = 1
                       AND `status` = :status
                     LIMIT 1',
                    $this->table()
                )
            );
            $statement->execute([
                'property_id' => $propertyId,
                'private_user_id' => $privateUserId,
                'status' => 'active',
            ]);
            $value = $statement->fetchColumn();
            $role = is_string($value) ? strtolower(trim($value)) : null;
            if (in_array($role, self::ALLOWED_ROLES, true)) {
                return $role;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    public function canWrite(int $propertyId, int $privateUserId): bool
    {
        $role = $this->activeRole($propertyId, $privateUserId);
        return in_array($role, ['owner', 'co_owner', 'manager'], true);
    }

    public function canDelete(int $propertyId, int $privateUserId): bool
    {
        $role = $this->activeRole($propertyId, $privateUserId);
        return $role === 'owner';
    }

    /**
     * @return array<int, int>
     */
    public function activePropertyIdsForUser(int $privateUserId): array
    {
        if ($privateUserId <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT `rental_property_id`
                     FROM `%s`
                     WHERE `private_user_id` = :private_user_id
                       AND `is_active` = 1
                       AND `status` = :status',
                    $this->table()
                )
            );
            $statement->execute([
                'private_user_id' => $privateUserId,
                'status' => 'active',
            ]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($rows)) {
            return [];
        }

        $propertyIds = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $propertyId = is_numeric($row['rental_property_id'] ?? null) ? (int) $row['rental_property_id'] : 0;
            if ($propertyId > 0) {
                $propertyIds[] = $propertyId;
            }
        }

        return array_values(array_unique($propertyIds));
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<int, array<string, mixed>>
     */
    public function listByPropertyIds(array $propertyIds, int $limit = 200): array
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
                    'SELECT m.*, p.`name` as `property_name`, u.`email` as `private_user_email`
                     FROM `%s` m
                     INNER JOIN `%s` p ON p.`id` = m.`rental_property_id`
                     INNER JOIN `%s` u ON u.`id` = m.`private_user_id`
                     WHERE m.`rental_property_id` IN (%s)
                       AND m.`is_active` = 1
                       AND m.`status` = :status
                     ORDER BY p.`name` ASC, m.`private_user_id` ASC, m.`created_at` DESC
                     LIMIT :limit',
                    $this->table(),
                    $this->database->table('rental_properties'),
                    $this->database->table('private_users'),
                    implode(',', $placeholders)
                )
            );

            foreach ($propertyIds as $index => $propertyId) {
                $statement->bindValue(':property_id' . $index, $propertyId, PDO::PARAM_INT);
            }
            $statement->bindValue(':status', 'active', PDO::PARAM_STR);
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($rows)) {
            return [];
        }

        $members = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $member = RentalPropertyMember::fromDatabaseRow($row);
            if (!$member instanceof RentalPropertyMember) {
                continue;
            }

            $members[] = array_merge(
                $member->toArray(),
                [
                    'propertyName' => is_string($row['property_name'] ?? null) ? trim((string) $row['property_name']) : '',
                    'privateUserEmail' => is_string($row['private_user_email'] ?? null)
                        ? trim((string) $row['private_user_email'])
                        : '',
                ]
            );
        }

        return $members;
    }

    public function create(
        int $propertyId,
        int $privateUserId,
        string $role,
        int $addedByPrivateUserId,
        ?string $notes = null
    ): ?RentalPropertyMember {
        if ($propertyId <= 0 || $privateUserId <= 0 || $addedByPrivateUserId <= 0) {
            return null;
        }

        $normalizedRole = strtolower(trim($role));
        if (!in_array($normalizedRole, self::ALLOWED_ROLES, true)) {
            return null;
        }

        $notes = is_string($notes) ? trim((string) $notes) : null;
        if ($notes !== null && strlen($notes) > 2000) {
            return null;
        }

        $createdAt = date('Y-m-d H:i:s');

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`rental_property_id`, `private_user_id`, `role`, `status`, `is_active`, `notes`, `added_by_private_user_id`, `created_at`, `updated_at`)
                     VALUES
                        (:property_id, :private_user_id, :role, :status, 1, :notes, :added_by, :created_at, :updated_at)
                     ON DUPLICATE KEY UPDATE
                        `role` = VALUES(`role`),
                        `status` = VALUES(`status`),
                        `is_active` = VALUES(`is_active`),
                        `notes` = VALUES(`notes`),
                        `removed_at` = NULL,
                        `removed_by_private_user_id` = NULL,
                        `updated_at` = VALUES(`updated_at`),
                        `added_by_private_user_id` = VALUES(`added_by_private_user_id`)',
                    $this->table()
                )
            );
            $statement->execute([
                'property_id' => $propertyId,
                'private_user_id' => $privateUserId,
                'role' => $normalizedRole,
                'status' => 'active',
                'notes' => $notes,
                'added_by' => $addedByPrivateUserId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $lastInsertId = $this->database->pdo()->lastInsertId();
            $id = is_numeric($lastInsertId) ? (int) $lastInsertId : 0;

            if ($id <= 0) {
                $id = $this->memberIdByUniqueKeys($propertyId, $privateUserId);
                if ($id <= 0) {
                    return null;
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return $this->findById($id);
    }

    public function update(int $memberId, string $role, ?string $notes = null): bool
    {
        if ($memberId <= 0) {
            return false;
        }

        $normalizedRole = strtolower(trim($role));
        if (!in_array($normalizedRole, self::ALLOWED_ROLES, true)) {
            return false;
        }

        $notes = is_string($notes) ? trim((string) $notes) : null;
        if ($notes !== null && strlen($notes) > 2000) {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `role` = :role,
                         `notes` = :notes,
                         `updated_at` = :updated_at
                     WHERE `id` = :id
                       AND `is_active` = 1
                       AND `status` = :status',
                    $this->table()
                )
            );
            $statement->execute([
                'role' => $normalizedRole,
                'notes' => $notes,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $memberId,
                'status' => 'active',
            ]);

            return (bool) $statement->rowCount();
        } catch (\Throwable) {
            return false;
        }
    }

    public function deactivate(int $propertyId, int $privateUserId, int $removedByPrivateUserId): bool
    {
        if ($propertyId <= 0 || $privateUserId <= 0 || $removedByPrivateUserId <= 0) {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `status` = :status,
                         `is_active` = 0,
                         `removed_at` = :removed_at,
                         `removed_by_private_user_id` = :removed_by
                     WHERE `rental_property_id` = :property_id
                       AND `private_user_id` = :private_user_id
                       AND `status` = :current_status',
                    $this->table()
                )
            );
            $statement->execute([
                'status' => 'revoked',
                'removed_at' => date('Y-m-d H:i:s'),
                'removed_by' => $removedByPrivateUserId,
                'property_id' => $propertyId,
                'private_user_id' => $privateUserId,
                'current_status' => 'active',
            ]);

            return (bool) $statement->rowCount();
        } catch (\Throwable) {
            return false;
        }
    }

    private function memberIdByUniqueKeys(int $propertyId, int $privateUserId): int
    {
        try {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT `id`
                     FROM `%s`
                     WHERE `rental_property_id` = :property_id
                       AND `private_user_id` = :private_user_id
                     LIMIT 1',
                    $this->table()
                )
            );
            $statement->execute([
                'property_id' => $propertyId,
                'private_user_id' => $privateUserId,
            ]);

            $value = $statement->fetchColumn();
            $memberId = is_numeric($value) ? (int) $value : 0;
            if ($memberId > 0) {
                return $memberId;
            }
        } catch (\Throwable) {
            return 0;
        }

        return 0;
    }

    private function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        $this->database->ensureReady();
        $propertyConstraint = $this->constraintName('property');
        $userConstraint = $this->constraintName('user');
        $addedByConstraint = $this->constraintName('added_by_user');
        $removedByConstraint = $this->constraintName('removed_by_user');
        $this->database->pdo()->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `rental_property_id` INT NOT NULL,
                    `private_user_id` INT NOT NULL,
                    `role` ENUM("owner", "co_owner", "occupant", "manager") NOT NULL,
                    `status` ENUM("active", "inactive", "revoked", "pending") NOT NULL DEFAULT "active",
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `notes` TEXT NULL,
                    `added_by_private_user_id` INT NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `removed_at` DATETIME NULL,
                    `removed_by_private_user_id` INT NULL,
                    UNIQUE KEY `uq_rental_property_members_property_user` (`rental_property_id`, `private_user_id`),
                    KEY `idx_rental_property_members_property` (`rental_property_id`, `is_active`),
                    KEY `idx_rental_property_members_user` (`private_user_id`, `is_active`),
                    CONSTRAINT `%s`
                        FOREIGN KEY (`rental_property_id`)
                        REFERENCES `%s` (`id`)
                        ON DELETE CASCADE,
                    CONSTRAINT `%s`
                        FOREIGN KEY (`private_user_id`)
                        REFERENCES `%s` (`id`)
                        ON DELETE CASCADE,
                    CONSTRAINT `%s`
                        FOREIGN KEY (`added_by_private_user_id`)
                        REFERENCES `%s` (`id`)
                        ON DELETE CASCADE,
                    CONSTRAINT `%s`
                        FOREIGN KEY (`removed_by_private_user_id`)
                        REFERENCES `%s` (`id`)
                        ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->table(),
                $propertyConstraint,
                $this->database->table('rental_properties'),
                $userConstraint,
                $this->database->table('private_users'),
                $addedByConstraint,
                $this->database->table('private_users'),
                $removedByConstraint,
                $this->database->table('private_users')
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

    private function constraintName(string $name): string
    {
        $base = preg_replace('/[^A-Za-z0-9_]+/', '_', $this->database->table('rental_property_members') . '_' . $name) ?? $name;

        return substr($base, 0, 64);
    }
}
