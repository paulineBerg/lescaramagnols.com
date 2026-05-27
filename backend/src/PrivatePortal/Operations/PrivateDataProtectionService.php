<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Operations;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class PrivateDataProtectionService
{
    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function exportAccount(int $privateUserId): array
    {
        if ($privateUserId <= 0) {
            return [];
        }

        $this->database->ensureReady();

        return [
            'generatedAt' => date('c'),
            'scope' => 'private_family_account',
            'privateUser' => $this->privateUser($privateUserId),
            'modulePermissions' => $this->rows(
                'private_user_module_permissions',
                '`private_user_id` = :private_user_id',
                ['private_user_id' => $privateUserId],
                ['id', 'private_user_id', 'private_module_id', 'is_active', 'granted_by_identifier', 'created_at', 'updated_at']
            ),
            'documents' => $this->rows(
                'private_documents',
                '`private_user_id` = :private_user_id',
                ['private_user_id' => $privateUserId],
                ['document_id', 'category_id', 'original_name', 'extension', 'mime_type', 'size_bytes', 'is_active', 'uploaded_at']
            ),
            'documentCategories' => $this->rows(
                'private_document_categories',
                '`private_user_id` = :private_user_id',
                ['private_user_id' => $privateUserId],
                ['id', 'name', 'slug', 'color', 'is_active', 'created_at', 'updated_at']
            ),
            'rentalMemberships' => $this->rows(
                'rental_property_members',
                '`private_user_id` = :private_user_id',
                ['private_user_id' => $privateUserId],
                ['rental_property_id', 'private_user_id', 'role', 'status', 'is_active', 'created_at', 'updated_at']
            ),
            'taxYears' => $this->rows(
                'tax_years',
                '`private_user_id` = :private_user_id',
                ['private_user_id' => $privateUserId],
                ['year', 'status', 'locked_at', 'unlocked_at', 'created_at', 'updated_at']
            ),
            'taxManualEntries' => $this->rows(
                'tax_manual_income_entries',
                '`private_user_id` = :private_user_id',
                ['private_user_id' => $privateUserId],
                ['year', 'source_code', 'label', 'amount', 'category', 'status', 'created_at', 'updated_at']
            ),
        ];
    }

    public function anonymizeAccount(int $privateUserId, int $actorPrivateUserId, string $reason): bool
    {
        $reason = trim($reason);
        if ($privateUserId <= 0 || $actorPrivateUserId <= 0 || $reason === '') {
            return false;
        }

        try {
            $this->database->ensureReady();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();
            $anonymousEmail = sprintf('deleted+%d@anonymous.invalid', $privateUserId);
            $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_ARGON2ID);
            if (!is_string($hash)) {
                $pdo->rollBack();
                return false;
            }

            $statement = $pdo->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `email` = :email,
                         `password_hash` = :password_hash,
                         `status` = :status,
                         `updated_at` = :updated_at
                     WHERE `id` = :id',
                    $this->database->table('private_users')
                )
            );
            $statement->execute([
                'email' => $anonymousEmail,
                'password_hash' => $hash,
                'status' => 'deleted',
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $privateUserId,
            ]);

            $this->safeUpdate(
                'private_user_module_permissions',
                '`is_active` = 0, `updated_at` = :updated_at, `granted_by_identifier` = :actor',
                '`private_user_id` = :private_user_id',
                ['updated_at' => date('Y-m-d H:i:s'), 'actor' => 'gdpr-anonymized', 'private_user_id' => $privateUserId]
            );
            $this->safeUpdate(
                'private_documents',
                '`original_name` = :original_name, `is_active` = 0',
                '`private_user_id` = :private_user_id',
                ['original_name' => 'document-anonymized', 'private_user_id' => $privateUserId]
            );
            $this->safeUpdate(
                'tax_manual_income_entries',
                '`label` = :label, `notes` = NULL, `updated_at` = :updated_at',
                '`private_user_id` = :private_user_id',
                ['label' => 'Ligne anonymisee', 'updated_at' => date('Y-m-d H:i:s'), 'private_user_id' => $privateUserId]
            );

            $pdo->commit();

            return true;
        } catch (\Throwable) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function privateUser(int $privateUserId): array
    {
        try {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT `id`, `email`, `status`, `created_at`, `updated_at`, `last_login_at`
                     FROM `%s` WHERE `id` = :id LIMIT 1',
                    $this->database->table('private_users')
                )
            );
            $statement->execute(['id' => $privateUserId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return is_array($row) ? $this->camelRow($row) : [];
    }

    /**
     * @param array<string, mixed> $params
     * @param array<int, string> $columns
     * @return array<int, array<string, mixed>>
     */
    private function rows(string $table, string $where, array $params, array $columns): array
    {
        try {
            $quotedColumns = implode(', ', array_map(static fn (string $column): string => '`' . $column . '`', $columns));
            $statement = $this->database->pdo()->prepare(
                sprintf('SELECT %s FROM `%s` WHERE %s', $quotedColumns, $this->database->table($table), $where)
            );
            $statement->execute($params);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_map(fn (array $row): array => $this->camelRow($row), $rows));
    }

    /**
     * @param array<string, mixed> $params
     */
    private function safeUpdate(string $table, string $set, string $where, array $params): void
    {
        try {
            $statement = $this->database->pdo()->prepare(
                sprintf('UPDATE `%s` SET %s WHERE %s', $this->database->table($table), $set, $where)
            );
            $statement->execute($params);
        } catch (\Throwable) {
            return;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function camelRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $normalized[$this->camelKey($key)] = $value;
        }

        return $normalized;
    }

    private function camelKey(string $key): string
    {
        return preg_replace_callback(
            '/_([a-z0-9])/',
            static fn (array $matches): string => strtoupper((string) $matches[1]),
            strtolower(trim($key))
        ) ?? $key;
    }
}
