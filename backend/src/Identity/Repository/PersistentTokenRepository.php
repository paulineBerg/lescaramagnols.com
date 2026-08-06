<?php

declare(strict_types=1);

namespace Caramagnols\Identity\Repository;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class PersistentTokenRepository
{
    private bool $schemaReady = false;

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function table(): string
    {
        return $this->database->table('persistent_session_tokens');
    }

    /**
     * @return array{id: int, selector: string, secret: string, family: string, expires_at: string}
     */
    public function create(int $trustedDeviceId, string $scope, int $ttlSeconds, ?string $family = null): array
    {
        $this->ensureSchema();
        $selector = bin2hex(random_bytes(16));
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $family = $family !== null && $family !== '' ? $family : bin2hex(random_bytes(16));
        $now = $this->now();
        $expiresAt = gmdate('Y-m-d H:i:s', time() + max(300, $ttlSeconds));

        $statement = $this->database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                 (`trusted_device_id`, `selector`, `token_hash`, `scope`, `created_at`, `expires_at`, `token_family_id`)
                 VALUES (:trusted_device_id, :selector, :token_hash, :scope, :created_at, :expires_at, :token_family_id)',
                $this->table()
            )
        );
        $statement->execute([
            'trusted_device_id' => $trustedDeviceId,
            'selector' => $selector,
            'token_hash' => $this->hashSecret($secret),
            'scope' => $scope,
            'created_at' => $now,
            'expires_at' => $expiresAt,
            'token_family_id' => $family,
        ]);

        return [
            'id' => (int) $this->database->pdo()->lastInsertId(),
            'selector' => $selector,
            'secret' => $secret,
            'family' => $family,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySelector(string $selector): ?array
    {
        $this->ensureSchema();
        $statement = $this->database->pdo()->prepare(
            sprintf('SELECT * FROM `%s` WHERE `selector` = :selector LIMIT 1', $this->table())
        );
        $statement->execute(['selector' => $selector]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{id: int, selector: string, secret: string, family: string, expires_at: string}
     */
    public function rotate(array $oldToken, int $ttlSeconds): array
    {
        $oldId = (int) ($oldToken['id'] ?? 0);
        $trustedDeviceId = (int) ($oldToken['trusted_device_id'] ?? 0);
        $scope = (string) ($oldToken['scope'] ?? '');
        $family = (string) ($oldToken['token_family_id'] ?? '');
        $new = $this->create($trustedDeviceId, $scope, $ttlSeconds, $family);

        $statement = $this->database->pdo()->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `last_used_at` = :last_used_at,
                     `rotated_at` = :rotated_at,
                     `revoked_at` = :revoked_at,
                     `replaced_by_token_id` = :replaced_by_token_id
                 WHERE `id` = :id',
                $this->table()
            )
        );
        $now = $this->now();
        $statement->execute([
            'last_used_at' => $now,
            'rotated_at' => $now,
            'revoked_at' => $now,
            'replaced_by_token_id' => $new['id'],
            'id' => $oldId,
        ]);

        return $new;
    }

    public function hashSecret(string $secret): string
    {
        return hash('sha256', $secret);
    }

    public function secretMatches(array $token, string $secret): bool
    {
        $hash = is_string($token['token_hash'] ?? null) ? (string) $token['token_hash'] : '';

        return $hash !== '' && hash_equals($hash, $this->hashSecret($secret));
    }

    public function revoke(int $id, string $reason = 'revoked'): bool
    {
        $this->ensureSchema();
        $statement = $this->database->pdo()->prepare(
            sprintf('UPDATE `%s` SET `revoked_at` = COALESCE(`revoked_at`, :revoked_at), `revoked_reason` = :reason WHERE `id` = :id', $this->table())
        );
        $statement->execute(['revoked_at' => $this->now(), 'reason' => mb_substr($reason, 0, 120), 'id' => $id]);

        return $statement->rowCount() > 0;
    }

    public function revokeDeviceTokens(int $trustedDeviceId, string $reason = 'device_revoked'): int
    {
        $this->ensureSchema();
        $statement = $this->database->pdo()->prepare(
            sprintf('UPDATE `%s` SET `revoked_at` = COALESCE(`revoked_at`, :revoked_at), `revoked_reason` = :reason WHERE `trusted_device_id` = :trusted_device_id', $this->table())
        );
        $statement->execute(['revoked_at' => $this->now(), 'reason' => mb_substr($reason, 0, 120), 'trusted_device_id' => $trustedDeviceId]);

        return $statement->rowCount();
    }

    public function revokeFamily(string $family, string $reason = 'reuse_detected'): int
    {
        if ($family === '') {
            return 0;
        }

        $this->ensureSchema();
        $statement = $this->database->pdo()->prepare(
            sprintf('UPDATE `%s` SET `revoked_at` = COALESCE(`revoked_at`, :revoked_at), `revoked_reason` = :reason WHERE `token_family_id` = :family', $this->table())
        );
        $statement->execute(['revoked_at' => $this->now(), 'reason' => mb_substr($reason, 0, 120), 'family' => $family]);

        return $statement->rowCount();
    }

    /**
     * @return array<int, string>
     */
    public function activeScopesByDevice(int $trustedDeviceId): array
    {
        $this->ensureSchema();
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'SELECT DISTINCT `scope` FROM `%s`
                 WHERE `trusted_device_id` = :trusted_device_id
                   AND `revoked_at` IS NULL
                   AND `expires_at` > :now
                 ORDER BY `scope`',
                $this->table()
            )
        );
        $statement->execute(['trusted_device_id' => $trustedDeviceId, 'now' => $this->now()]);

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN) ?: []));
    }

    public function purge(int $expiredRetentionSeconds, int $revokedRetentionSeconds): array
    {
        $this->ensureSchema();
        $now = time();
        $expiredBefore = gmdate('Y-m-d H:i:s', $now - max(86400, $expiredRetentionSeconds));
        $revokedBefore = gmdate('Y-m-d H:i:s', $now - max(86400, $revokedRetentionSeconds));

        $statement = $this->database->pdo()->prepare(
            sprintf(
                'DELETE FROM `%s`
                 WHERE (`expires_at` < :expired_before)
                    OR (`revoked_at` IS NOT NULL AND `revoked_at` < :revoked_before)',
                $this->table()
            )
        );
        $statement->execute(['expired_before' => $expiredBefore, 'revoked_before' => $revokedBefore]);

        return ['deleted_tokens' => $statement->rowCount()];
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
                    `trusted_device_id` BIGINT UNSIGNED NOT NULL,
                    `selector` CHAR(32) NOT NULL,
                    `token_hash` CHAR(64) NOT NULL,
                    `scope` VARCHAR(16) NOT NULL,
                    `created_at` DATETIME NOT NULL,
                    `last_used_at` DATETIME NULL,
                    `expires_at` DATETIME NOT NULL,
                    `rotated_at` DATETIME NULL,
                    `revoked_at` DATETIME NULL,
                    `revoked_reason` VARCHAR(120) NULL,
                    `replaced_by_token_id` BIGINT UNSIGNED NULL,
                    `token_family_id` CHAR(32) NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `%s_selector` (`selector`),
                    KEY `%s_device_scope` (`trusted_device_id`, `scope`),
                    KEY `%s_family` (`token_family_id`),
                    KEY `%s_expires` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                $this->table(),
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
