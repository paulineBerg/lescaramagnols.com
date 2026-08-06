<?php

declare(strict_types=1);

namespace Caramagnols\Identity\Repository;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class TrustedDeviceRepository
{
    private bool $schemaReady = false;

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function table(): string
    {
        return $this->database->table('trusted_devices');
    }

    public function upsert(
        string $userScope,
        ?int $userId,
        string $identifierHash,
        string $name,
        string $deviceType,
        string $userAgentHash,
        string $ipHash,
        string $trustedUntil
    ): int {
        $this->ensureSchema();
        $existing = $this->findReusable($userScope, $userId, $identifierHash, $userAgentHash);
        $now = $this->now();

        if (is_array($existing) && (int) ($existing['id'] ?? 0) > 0) {
            $id = (int) $existing['id'];
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `last_seen_at` = :last_seen_at,
                         `last_ip_hash` = :last_ip_hash,
                         `trusted_until` = :trusted_until
                     WHERE `id` = :id',
                    $this->table()
                )
            );
            $statement->execute([
                'last_seen_at' => $now,
                'last_ip_hash' => $ipHash,
                'trusted_until' => $trustedUntil,
                'id' => $id,
            ]);

            return $id;
        }

        $publicId = bin2hex(random_bytes(16));
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                 (`user_scope`, `user_id`, `user_identifier_hash`, `public_id`, `name`, `device_type`,
                  `user_agent_hash`, `created_at`, `last_seen_at`, `last_ip_hash`, `trusted_until`)
                 VALUES
                 (:user_scope, :user_id, :user_identifier_hash, :public_id, :name, :device_type,
                  :user_agent_hash, :created_at, :last_seen_at, :last_ip_hash, :trusted_until)',
                $this->table()
            )
        );
        $statement->execute([
            'user_scope' => $userScope,
            'user_id' => $userId,
            'user_identifier_hash' => $identifierHash,
            'public_id' => $publicId,
            'name' => $name,
            'device_type' => $deviceType,
            'user_agent_hash' => $userAgentHash,
            'created_at' => $now,
            'last_seen_at' => $now,
            'last_ip_hash' => $ipHash,
            'trusted_until' => $trustedUntil,
        ]);

        return (int) $this->database->pdo()->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $this->ensureSchema();
        $statement = $this->database->pdo()->prepare(sprintf('SELECT * FROM `%s` WHERE `id` = :id LIMIT 1', $this->table()));
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(string $userScope, ?int $userId, string $identifierHash): array
    {
        $this->ensureSchema();
        $where = '`user_scope` = :user_scope AND `user_identifier_hash` = :identifier_hash';
        $params = ['user_scope' => $userScope, 'identifier_hash' => $identifierHash];
        if ($userId !== null && $userId > 0) {
            $where .= ' AND `user_id` = :user_id';
            $params['user_id'] = $userId;
        }

        $statement = $this->database->pdo()->prepare(
            sprintf('SELECT * FROM `%s` WHERE %s ORDER BY `last_seen_at` DESC, `created_at` DESC', $this->table(), $where)
        );
        $statement->execute($params);

        return array_values(array_filter($statement->fetchAll(PDO::FETCH_ASSOC) ?: [], 'is_array'));
    }

    public function touch(int $id, string $ipHash, string $trustedUntil): void
    {
        if ($id <= 0) {
            return;
        }

        $this->ensureSchema();
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `last_seen_at` = :last_seen_at,
                     `last_ip_hash` = :last_ip_hash,
                     `trusted_until` = :trusted_until
                 WHERE `id` = :id AND `revoked_at` IS NULL',
                $this->table()
            )
        );
        $statement->execute([
            'last_seen_at' => $this->now(),
            'last_ip_hash' => $ipHash,
            'trusted_until' => $trustedUntil,
            'id' => $id,
        ]);
    }

    public function rename(int $id, string $name): bool
    {
        $this->ensureSchema();
        $statement = $this->database->pdo()->prepare(
            sprintf('UPDATE `%s` SET `name` = :name WHERE `id` = :id AND `revoked_at` IS NULL', $this->table())
        );
        $statement->execute(['name' => mb_substr(trim($name), 0, 120), 'id' => $id]);

        return $statement->rowCount() > 0;
    }

    public function revoke(int $id, string $reason): bool
    {
        $this->ensureSchema();
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'UPDATE `%s` SET `revoked_at` = COALESCE(`revoked_at`, :revoked_at), `revoked_reason` = :reason WHERE `id` = :id',
                $this->table()
            )
        );
        $statement->execute(['revoked_at' => $this->now(), 'reason' => mb_substr($reason, 0, 120), 'id' => $id]);

        return $statement->rowCount() > 0;
    }

    public function revokeForUser(string $userScope, ?int $userId, string $identifierHash, string $reason, ?int $exceptDeviceId = null): int
    {
        $this->ensureSchema();
        $where = '`user_scope` = :user_scope AND `user_identifier_hash` = :identifier_hash AND `revoked_at` IS NULL';
        $params = [
            'user_scope' => $userScope,
            'identifier_hash' => $identifierHash,
            'revoked_at' => $this->now(),
            'reason' => mb_substr($reason, 0, 120),
        ];
        if ($userId !== null && $userId > 0) {
            $where .= ' AND `user_id` = :user_id';
            $params['user_id'] = $userId;
        }
        if ($exceptDeviceId !== null && $exceptDeviceId > 0) {
            $where .= ' AND `id` <> :except_device_id';
            $params['except_device_id'] = $exceptDeviceId;
        }

        $statement = $this->database->pdo()->prepare(
            sprintf('UPDATE `%s` SET `revoked_at` = :revoked_at, `revoked_reason` = :reason WHERE %s', $this->table(), $where)
        );
        $statement->execute($params);

        return $statement->rowCount();
    }

    public function purgeRevoked(int $retentionSeconds): int
    {
        $this->ensureSchema();
        $before = gmdate('Y-m-d H:i:s', time() - max(86400, $retentionSeconds));
        $statement = $this->database->pdo()->prepare(
            sprintf('DELETE FROM `%s` WHERE `revoked_at` IS NOT NULL AND `revoked_at` < :before', $this->table())
        );
        $statement->execute(['before' => $before]);

        return $statement->rowCount();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findReusable(string $userScope, ?int $userId, string $identifierHash, string $userAgentHash): ?array
    {
        $where = '`user_scope` = :user_scope AND `user_identifier_hash` = :identifier_hash AND `user_agent_hash` = :user_agent_hash AND `revoked_at` IS NULL';
        $params = [
            'user_scope' => $userScope,
            'identifier_hash' => $identifierHash,
            'user_agent_hash' => $userAgentHash,
        ];
        if ($userId !== null && $userId > 0) {
            $where .= ' AND `user_id` = :user_id';
            $params['user_id'] = $userId;
        }

        $statement = $this->database->pdo()->prepare(sprintf('SELECT * FROM `%s` WHERE %s LIMIT 1', $this->table(), $where));
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        $this->database->pdo()->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `user_scope` VARCHAR(16) NOT NULL,
                    `user_id` BIGINT UNSIGNED NULL,
                    `user_identifier_hash` CHAR(64) NOT NULL,
                    `public_id` CHAR(32) NOT NULL,
                    `name` VARCHAR(120) NOT NULL,
                    `device_type` VARCHAR(32) NOT NULL,
                    `user_agent_hash` CHAR(64) NOT NULL,
                    `created_at` DATETIME NOT NULL,
                    `last_seen_at` DATETIME NULL,
                    `last_ip_hash` CHAR(64) NOT NULL DEFAULT \'\',
                    `trusted_until` DATETIME NOT NULL,
                    `revoked_at` DATETIME NULL,
                    `revoked_reason` VARCHAR(120) NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `%s_public_id` (`public_id`),
                    KEY `%s_user_lookup` (`user_scope`, `user_identifier_hash`, `user_id`),
                    KEY `%s_trusted_until` (`trusted_until`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                $this->table(),
                $this->table(),
                $this->table(),
                $this->table()
            )
        );
        $this->schemaReady = true;
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
