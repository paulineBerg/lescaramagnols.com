<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Operations;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivateApps\FamilyDiscussion\Attachment\DiscussionAttachmentStorage;
use Caramagnols\PrivatePortal\Documents\PrivateDocumentStorage;
use PDO;

final class PrivateDataProtectionService
{
    public function __construct(
        private readonly EditorialDatabase $database,
        private readonly mixed $mailSender = null
    ) {
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
                ['id', 'private_user_id', 'private_module_id', 'is_active', 'granted_by_admin_email', 'granted_at', 'revoked_at', 'revoked_by_admin_email']
            ),
            'documents' => $this->rows(
                'private_documents',
                '`private_user_id` = :private_user_id',
                ['private_user_id' => $privateUserId],
                ['document_id', 'category_id', 'original_name', 'extension', 'mime_type', 'size_bytes', 'scan_status', 'scanned_at', 'is_active', 'uploaded_at']
            ),
            'documentCategories' => $this->rows(
                'private_document_categories',
                '`private_user_id` = :private_user_id',
                ['private_user_id' => $privateUserId],
                ['id', 'name', 'slug', 'color', 'is_active', 'created_at', 'updated_at']
            ),
            'blocnoteNotes' => $this->rows(
                'private_blocnote_notes',
                '`private_user_id` = :private_user_id',
                ['private_user_id' => $privateUserId],
                ['id', 'private_user_id', 'category_id', 'title', 'content', 'color', 'created_at', 'updated_at']
            ),
            'blocnoteCategories' => $this->rows(
                'private_blocnote_categories',
                '`private_user_id` = :private_user_id',
                ['private_user_id' => $privateUserId],
                ['id', 'private_user_id', 'name', 'slug', 'color', 'is_default', 'created_at', 'updated_at']
            ),
            'rentalMemberships' => $this->rows(
                'rental_property_members',
                '`private_user_id` = :private_user_id',
                ['private_user_id' => $privateUserId],
                ['rental_property_id', 'private_user_id', 'role', 'status', 'is_active', 'created_at', 'updated_at']
            ),
            'rentalPaymentRequests' => $this->rows(
                'rental_payment_requests',
                '`sent_by_private_user_id` = :private_user_id',
                ['private_user_id' => $privateUserId],
                ['rental_rent_id', 'rental_property_id', 'rental_unit_id', 'recipient_email', 'subject', 'channel', 'status', 'sent_at', 'created_at']
            ),
            'rentalGeneratedDocuments' => $this->rows(
                'rental_generated_documents',
                '`generated_by_private_user_id` = :private_user_id',
                ['private_user_id' => $privateUserId],
                ['rental_rent_id', 'rental_lease_id', 'rental_payment_id', 'rental_property_id', 'document_type', 'document_id', 'original_name', 'mime_type', 'size_bytes', 'sha256_hash', 'is_active', 'generated_at']
            ),
            'taxYears' => $this->rows(
                'tax_years',
                '`private_user_id` = :private_user_id',
                ['private_user_id' => $privateUserId],
                ['year', 'status', 'locked_at', 'unlocked_at', 'created_at', 'updated_at']
            ),
            'taxSourceActivations' => $this->rows(
                'tax_source_activations',
                '`private_user_id` = :private_user_id',
                ['private_user_id' => $privateUserId],
                ['year', 'source_code', 'is_enabled', 'enabled_at', 'disabled_at', 'created_at', 'updated_at']
            ),
            'taxManualEntries' => $this->rows(
                'tax_manual_income_entries',
                '`private_user_id` = :private_user_id',
                ['private_user_id' => $privateUserId],
                ['year', 'source_code', 'label', 'amount', 'category', 'status', 'created_at', 'updated_at']
            ),
        ];
    }

    public function redactAccountForDeletion(int $privateUserId, int $actorPrivateUserId, string $reason): bool
    {
        $reason = trim($reason);
        if ($privateUserId <= 0 || $actorPrivateUserId < 0 || $reason === '') {
            return false;
        }

        try {
            $this->database->ensureReady();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();
            $deletedEmail = sprintf('deleted+%d@deleted.invalid', $privateUserId);
            $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_ARGON2ID);
            if (!is_string($hash)) {
                $pdo->rollBack();
                return false;
            }

            $statement = $pdo->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `email` = :email,
                         `full_name` = NULL,
                         `postal_address` = NULL,
                         `phone` = NULL,
                         `password_hash` = :password_hash,
                         `status` = :status,
                         `updated_at` = :updated_at
                     WHERE `id` = :id',
                    $this->database->table('private_users')
                )
            );
            $statement->execute([
                'email' => $deletedEmail,
                'password_hash' => $hash,
                'status' => 'deleted',
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $privateUserId,
            ]);

            $this->safeUpdate(
                'private_user_module_permissions',
                '`is_active` = 0, `revoked_at` = :updated_at, `revoked_by_admin_email` = :actor',
                '`private_user_id` = :private_user_id',
                ['updated_at' => date('Y-m-d H:i:s'), 'actor' => 'gdpr-deleted', 'private_user_id' => $privateUserId]
            );
            $this->safeUpdate(
                'private_documents',
                '`original_name` = :original_name, `scan_error` = \'\', `is_active` = 0',
                '`private_user_id` = :private_user_id',
                ['original_name' => 'document-deleted', 'private_user_id' => $privateUserId]
            );
            $this->safeUpdate(
                'tax_manual_income_entries',
                '`label` = :label, `notes` = NULL, `updated_at` = :updated_at',
                '`private_user_id` = :private_user_id',
                ['label' => 'Ligne supprimee', 'updated_at' => date('Y-m-d H:i:s'), 'private_user_id' => $privateUserId]
            );
            $this->safeUpdate(
                'tax_source_activations',
                '`is_enabled` = 0, `disabled_at` = :disabled_at, `disabled_by_private_user_id` = :actor, `updated_at` = :updated_at',
                '`private_user_id` = :private_user_id',
                [
                    'disabled_at' => date('Y-m-d H:i:s'),
                    'actor' => $actorPrivateUserId > 0 ? $actorPrivateUserId : null,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'private_user_id' => $privateUserId,
                ]
            );
            $this->cascadePrivateData($privateUserId, $actorPrivateUserId);

            $pdo->commit();

            return true;
        } catch (\Throwable) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return false;
        }
    }

    public function anonymizeAccount(int $privateUserId, int $actorPrivateUserId, string $reason): bool
    {
        return $this->redactAccountForDeletion($privateUserId, $actorPrivateUserId, $reason);
    }

    public function purgeDeletedAccount(int $privateUserId, string $reason): bool
    {
        $reason = trim($reason);
        if ($privateUserId <= 0 || $reason === '') {
            return false;
        }

        $user = $this->privateUser($privateUserId);
        if (($user['status'] ?? '') !== 'deleted') {
            return false;
        }

        try {
            $this->database->ensureReady();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();
            $this->cascadePrivateData($privateUserId, 0);
            $this->clearPrivateUserProfile($privateUserId);
            $pdo->commit();

            return true;
        } catch (\Throwable) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return false;
        }
    }

    public function purgeAnonymizedAccount(int $privateUserId, string $reason): bool
    {
        return $this->purgeDeletedAccount($privateUserId, $reason);
    }

    /**
     * @return array{success: bool, backupPath: ?string, deleteAfter: ?string, error: ?string}
     */
    public function deleteSuspendedAccountWithBackup(
        int $privateUserId,
        string $actorIdentifier,
        int $retentionDays = 30
    ): array {
        $actorIdentifier = trim($actorIdentifier);
        $retentionDays = max(1, $retentionDays);
        if ($privateUserId <= 0) {
            return $this->deletionResult(false, null, null, 'Compte privé introuvable.');
        }

        $user = $this->privateUser($privateUserId);
        if (($user['status'] ?? '') !== 'suspended') {
            return $this->deletionResult(false, null, null, 'Seul un compte suspendu peut être supprimé.');
        }

        $this->cleanupExpiredDeletionBackups(false, null, $privateUserId);
        $existingBackup = $this->latestDeletionBackupForUser($privateUserId);
        if (is_array($existingBackup) && is_string($existingBackup['deleteAfter'] ?? null)) {
            return $this->deletionResult(true, (string) ($existingBackup['path'] ?? null), (string) $existingBackup['deleteAfter'], null);
        }

        $deleteAfter = date('c', time() + ($retentionDays * 86400));
        $backupPath = $this->writeDeletionBackup($privateUserId, $actorIdentifier, $retentionDays, $deleteAfter);
        if ($backupPath === null) {
            return $this->deletionResult(false, null, null, 'Impossible de créer la sauvegarde de suppression.');
        }

        try {
            $this->database->ensureReady();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();
            $this->purgeAccountRows($privateUserId);
            $this->clearPrivateUserProfile($privateUserId);
            $statement = $pdo->prepare(
                sprintf(
                    'UPDATE `%s` SET `updated_at` = :updated_at WHERE `id` = :id AND `status` = :status',
                    $this->database->table('private_users')
                )
            );
            $statement->execute([
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $privateUserId,
                'status' => 'suspended',
            ]);
            $pdo->commit();
            $this->purgeBackedUpFiles($backupPath);

            return $this->deletionResult(true, $backupPath, $deleteAfter, null);
        } catch (\Throwable) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return $this->deletionResult(false, $backupPath, $deleteAfter, 'La purge des données du compte suspendu a échoué.');
        }
    }

    /**
     * @return array{path: string, filename: string, generatedAt: ?string, deleteAfter: ?string, warningSentAt: ?string, archivePath: ?string, archiveFilename: ?string}|null
     */
    public function latestDeletionBackupForUser(int $privateUserId): ?array
    {
        $backups = $this->deletionBackupsForUser($privateUserId);
        if ($backups === []) {
            return null;
        }

        usort(
            $backups,
            static fn (array $left, array $right): int => strcmp((string) ($right['generatedAt'] ?? ''), (string) ($left['generatedAt'] ?? ''))
        );

        return $backups[0];
    }

    /**
     * @return array{filename: string, content: string, contentType: string, deleteAfter: ?string}|null
     */
    public function deletionBackupDownloadForUser(int $privateUserId): ?array
    {
        $backup = $this->latestDeletionBackupForUser($privateUserId);
        if (!is_array($backup)) {
            return null;
        }

        $archivePath = is_string($backup['archivePath'] ?? null) ? (string) $backup['archivePath'] : '';
        $path = $archivePath !== '' && is_file($archivePath)
            ? $archivePath
            : (is_string($backup['path'] ?? null) ? (string) $backup['path'] : '');
        if ($path === '' || !is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if (!is_string($content)) {
            return null;
        }

        return [
            'filename' => $archivePath !== '' && is_string($backup['archiveFilename'] ?? null)
                ? (string) $backup['archiveFilename']
                : (is_string($backup['filename'] ?? null) ? (string) $backup['filename'] : basename($path)),
            'content' => $content,
            'contentType' => $archivePath !== '' ? 'application/zip' : 'application/json; charset=UTF-8',
            'deleteAfter' => is_string($backup['deleteAfter'] ?? null) ? (string) $backup['deleteAfter'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function privateUser(int $privateUserId): array
    {
        try {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT `id`, `email`, `full_name`, `postal_address`, `phone`, `status`, `created_at`, `updated_at`, `last_login_at`
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
            [$query, $expandedParams] = $this->expandRepeatedNamedParameters(
                sprintf('SELECT %s FROM `%s` WHERE %s', $quotedColumns, $this->database->table($table), $where),
                $params
            );
            $statement = $this->database->pdo()->prepare($query);
            $statement->execute($expandedParams);
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
            [$query, $expandedParams] = $this->expandRepeatedNamedParameters(
                sprintf('UPDATE `%s` SET %s WHERE %s', $this->database->table($table), $set, $where),
                $params
            );
            $statement = $this->database->pdo()->prepare($query);
            $statement->execute($expandedParams);
        } catch (\Throwable) {
            return;
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function safeDelete(string $table, string $where, array $params): void
    {
        try {
            [$query, $expandedParams] = $this->expandRepeatedNamedParameters(
                sprintf('DELETE FROM `%s` WHERE %s', $this->database->table($table), $where),
                $params
            );
            $statement = $this->database->pdo()->prepare($query);
            $statement->execute($expandedParams);
        } catch (\Throwable) {
            return;
        }
    }

    private function clearPrivateUserProfile(int $privateUserId): void
    {
        if ($privateUserId <= 0) {
            return;
        }

        $this->safeUpdate(
            'private_users',
            '`full_name` = NULL, `postal_address` = NULL, `phone` = NULL, `updated_at` = :updated_at',
            '`id` = :private_user_id',
            ['updated_at' => date('Y-m-d H:i:s'), 'private_user_id' => $privateUserId]
        );
    }

    private function cascadePrivateData(int $privateUserId, int $actorPrivateUserId): void
    {
        $now = date('Y-m-d H:i:s');
        $actor = $actorPrivateUserId > 0 ? $actorPrivateUserId : null;

        $this->safeUpdate(
            'private_document_categories',
            '`name` = :name, `slug` = :slug, `is_active` = 0, `updated_at` = :updated_at',
            '`private_user_id` = :private_user_id',
            ['name' => 'categorie-supprimee', 'slug' => 'categorie-supprimee-' . $privateUserId, 'updated_at' => $now, 'private_user_id' => $privateUserId]
        );

        $this->safeUpdate(
            'discussion_messages',
            '`body` = NULL, `encrypted_payload` = NULL, `encryption_metadata` = NULL, `deleted_at` = :deleted_at, `purge_status` = \'purged\'',
            '`sender_private_user_id` = :private_user_id AND `purge_status` <> \'purged\'',
            ['deleted_at' => $now, 'private_user_id' => $privateUserId]
        );
        $this->safeUpdate(
            'discussion_message_attachments',
            '`original_filename` = :filename, `purge_status` = \'purged\'',
            '`message_id` IN (SELECT `id` FROM `' . $this->database->table('discussion_messages') . '` WHERE `sender_private_user_id` = :private_user_id)',
            ['filename' => 'piece-jointe-supprimee', 'private_user_id' => $privateUserId]
        );
        $this->safeUpdate(
            'discussion_conversation_members',
            '`left_at` = COALESCE(`left_at`, :left_at)',
            '`private_user_id` = :private_user_id',
            ['left_at' => $now, 'private_user_id' => $privateUserId]
        );
        $this->safeUpdate(
            'discussion_crypto_devices',
            '`revoked_at` = COALESCE(`revoked_at`, :revoked_at)',
            '`private_user_id` = :private_user_id',
            ['revoked_at' => $now, 'private_user_id' => $privateUserId]
        );
        $this->safeUpdate(
            'discussion_conversation_keys',
            '`revoked_at` = COALESCE(`revoked_at`, :revoked_at)',
            '`private_user_id` = :private_user_id OR `created_by_private_user_id` = :private_user_id',
            ['revoked_at' => $now, 'private_user_id' => $privateUserId]
        );

        $this->safeUpdate(
            'rental_property_members',
            '`status` = :status, `is_active` = 0, `notes` = NULL, `removed_at` = COALESCE(`removed_at`, :removed_at), `removed_by_private_user_id` = :actor',
            '`private_user_id` = :private_user_id',
            ['status' => 'revoked', 'removed_at' => $now, 'actor' => $actor, 'private_user_id' => $privateUserId]
        );
        $this->safeUpdate(
            'rental_properties',
            '`name` = :name, `address` = :address, `status` = \'archived\', `is_active` = 0, `notes` = NULL, `archived_at` = COALESCE(`archived_at`, :archived_at), `archived_by_private_user_id` = :actor',
            '`created_by_private_user_id` = :private_user_id',
            ['name' => 'Bien supprime ' . $privateUserId, 'address' => 'adresse-supprimee', 'archived_at' => $now, 'actor' => $actor, 'private_user_id' => $privateUserId]
        );
        $this->safeUpdate(
            'rental_units',
            '`label` = :label, `status` = \'archived\', `is_active` = 0, `notes` = NULL, `archived_at` = COALESCE(`archived_at`, :archived_at), `archived_by_private_user_id` = :actor',
            '`created_by_private_user_id` = :private_user_id',
            ['label' => 'Lot supprime', 'archived_at' => $now, 'actor' => $actor, 'private_user_id' => $privateUserId]
        );
        $this->safeUpdate(
            'rental_tenants',
            '`full_name` = :name, `email` = NULL, `phone` = NULL, `notes` = NULL, `status` = \'cancelled\', `is_active` = 0',
            '`created_by_private_user_id` = :private_user_id',
            ['name' => 'Locataire supprime', 'private_user_id' => $privateUserId]
        );
        $this->safeUpdate(
            'rental_documents',
            '`original_name` = :original_name, `is_active` = 0',
            '`uploaded_by_private_user_id` = :private_user_id',
            ['original_name' => 'document-locatif-supprime', 'private_user_id' => $privateUserId]
        );
        $this->safeUpdate(
            'rental_generated_documents',
            '`original_name` = :original_name, `snapshot_payload` = NULL, `is_active` = 0',
            '`generated_by_private_user_id` = :private_user_id',
            ['original_name' => 'document-genere-supprime', 'private_user_id' => $privateUserId]
        );
        $this->safeDelete('rental_payments', '`created_by_private_user_id` = :private_user_id', ['private_user_id' => $privateUserId]);
        $this->safeDelete('rental_payment_requests', '`sent_by_private_user_id` = :private_user_id', ['private_user_id' => $privateUserId]);
        $this->safeDelete('rental_rents', '`created_by_private_user_id` = :private_user_id', ['private_user_id' => $privateUserId]);
        $this->safeDelete('rental_expenses', '`created_by_private_user_id` = :private_user_id', ['private_user_id' => $privateUserId]);
        $this->safeDelete('rental_leases', '`created_by_private_user_id` = :private_user_id', ['private_user_id' => $privateUserId]);
        $this->safeDelete('rental_export_logs', '`private_user_id` = :private_user_id', ['private_user_id' => $privateUserId]);

        $this->safeDelete('tax_export_logs', '`private_user_id` = :private_user_id', ['private_user_id' => $privateUserId]);
        $this->safeDelete('tax_summary_lines', '`tax_annual_summary_id` IN (SELECT `id` FROM `' . $this->database->table('tax_annual_summaries') . '` WHERE `private_user_id` = :private_user_id)', ['private_user_id' => $privateUserId]);
        $this->safeDelete('tax_annual_summaries', '`private_user_id` = :private_user_id', ['private_user_id' => $privateUserId]);
    }

    private function purgeAccountRows(int $privateUserId): void
    {
        $params = ['private_user_id' => $privateUserId];
        foreach ($this->deletionPurgeScopes() as $scope) {
            $this->safeDelete($scope['table'], $scope['where'], $params);
        }
    }

    private function writeDeletionBackup(int $privateUserId, string $actorIdentifier, int $retentionDays, string $deleteAfter): ?string
    {
        $root = $this->deletionBackupRoot();
        $directory = $root . '/' . date('Y/m');
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            return null;
        }
        $this->enforceDeletionBackupDirectoryPermissions($root);
        $this->enforceDeletionBackupDirectoryPermissions(dirname($directory));
        $this->enforceDeletionBackupDirectoryPermissions($directory);

        $path = $directory . '/private-user-' . $privateUserId . '-' . date('Ymd-His') . '.json';
        $tables = $this->deletionBackupRows($privateUserId);
        $files = $this->deletionBackupFileManifest($tables);
        $publicFiles = $this->publicDeletionFileManifest($files);
        $payload = [
            'generatedAt' => date('c'),
            'deleteAfter' => $deleteAfter,
            'retentionDays' => $retentionDays,
            'scope' => 'private_suspended_account_deletion',
            'privateUserId' => $privateUserId,
            'actorIdentifier' => $actorIdentifier,
            'tables' => $tables,
            'files' => $publicFiles,
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || file_put_contents($path, $json, LOCK_EX) === false) {
            return null;
        }

        @chmod($path, 0600);
        $zipPath = $this->writeDeletionBackupZip($path, $json, $files);
        if ($zipPath !== null) {
            $payload['archive'] = [
                'filename' => basename($zipPath),
                'format' => 'zip',
            ];
            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($json)) {
                file_put_contents($path, $json, LOCK_EX);
                @chmod($path, 0600);
            }
        }

        return $path;
    }

    /**
     * @return array<int, array{path: string, filename: string, generatedAt: ?string, deleteAfter: ?string, warningSentAt: ?string, archivePath: ?string, archiveFilename: ?string}>
     */
    private function deletionBackupsForUser(int $privateUserId): array
    {
        if ($privateUserId <= 0) {
            return [];
        }

        $root = $this->deletionBackupRoot();
        if (!is_dir($root)) {
            return [];
        }

        $backups = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'json') {
                continue;
            }

            $payload = $this->readDeletionBackupPayload($file->getPathname());
            if (!is_array($payload) || (int) ($payload['privateUserId'] ?? 0) !== $privateUserId) {
                continue;
            }

            $archivePath = preg_replace('/\.json\z/i', '.zip', $file->getPathname()) ?? '';
            $archivePath = is_file($archivePath) ? $archivePath : null;
            $backups[] = [
                'path' => $file->getPathname(),
                'filename' => $file->getBasename(),
                'generatedAt' => is_string($payload['generatedAt'] ?? null) ? (string) $payload['generatedAt'] : null,
                'deleteAfter' => is_string($payload['deleteAfter'] ?? null) ? (string) $payload['deleteAfter'] : null,
                'warningSentAt' => is_string($payload['warningSentAt'] ?? null) ? (string) $payload['warningSentAt'] : null,
                'archivePath' => $archivePath,
                'archiveFilename' => is_string($archivePath) ? basename($archivePath) : null,
            ];
        }

        return $backups;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $tables
     * @return array<int, array<string, mixed>>
     */
    private function deletionBackupFileManifest(array $tables): array
    {
        $manifest = [];
        $seen = [];
        $privateDocumentStorage = PrivateDocumentStorage::fromAppConfig();
        $discussionAttachmentStorage = DiscussionAttachmentStorage::fromAppConfig();

        foreach ($this->deletionFileScopes($privateDocumentStorage, $discussionAttachmentStorage) as $table => $definition) {
            $sourceTable = $table === 'discussion_message_attachment_previews'
                ? 'discussion_message_attachments'
                : $table;
            $rows = is_array($tables[$sourceTable] ?? null) ? $tables[$sourceTable] : [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $storagePathKey = (string) $definition['storagePath'];
                $storagePath = is_string($row[$storagePathKey] ?? null) ? trim((string) $row[$storagePathKey]) : '';
                if ($storagePath === '') {
                    continue;
                }

                $seenKey = $sourceTable . ':' . $storagePath;
                if (isset($seen[$seenKey])) {
                    continue;
                }
                $seen[$seenKey] = true;

                $resolver = $definition['resolver'];
                $absolutePath = method_exists($resolver, 'absolutePath') ? $resolver->absolutePath($storagePath) : null;
                $originalNameKey = (string) $definition['name'];
                $originalName = is_string($row[$originalNameKey] ?? null) ? trim((string) $row[$originalNameKey]) : '';
                $filename = $this->backupFilename($originalName !== '' ? $originalName : basename($storagePath));
                $rowId = is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0;
                $archiveDirectory = 'files/' . $this->backupFilename($sourceTable) . '/' . ($rowId > 0 ? (string) $rowId : substr(hash('sha256', $storagePath), 0, 12));
                $archivePath = $this->uniqueArchivePath($manifest, $archiveDirectory . '/' . $filename);
                $exists = is_string($absolutePath) && is_file($absolutePath) && is_readable($absolutePath);

                $manifest[] = [
                    'table' => $sourceTable,
                    'rowId' => $rowId > 0 ? $rowId : null,
                    'storagePath' => $storagePath,
                    'originalName' => $originalName !== '' ? $originalName : null,
                    'archivePath' => $archivePath,
                    'exists' => $exists,
                    'sizeBytes' => $exists ? (int) filesize($absolutePath) : null,
                    'sha256' => $exists ? hash_file('sha256', $absolutePath) : null,
                    'absolutePath' => $exists ? $absolutePath : null,
                ];
            }
        }

        return $manifest;
    }

    /**
     * @param array<int, array<string, mixed>> $files
     */
    private function writeDeletionBackupZip(string $jsonPath, string $json, array $files): ?string
    {
        if (!class_exists(\ZipArchive::class)) {
            return null;
        }

        $zipPath = preg_replace('/\.json\z/i', '.zip', $jsonPath) ?? '';
        if ($zipPath === '') {
            return null;
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $manifest = [];
        foreach ($files as $file) {
            $absolutePath = is_string($file['absolutePath'] ?? null) ? (string) $file['absolutePath'] : '';
            $archivePath = is_string($file['archivePath'] ?? null) ? (string) $file['archivePath'] : '';
            $manifestEntry = $file;
            unset($manifestEntry['absolutePath']);
            $manifest[] = $manifestEntry;

            if ($absolutePath !== '' && $archivePath !== '' && is_file($absolutePath) && is_readable($absolutePath)) {
                $zip->addFile($absolutePath, $archivePath);
            }
        }

        $manifestJson = json_encode(
            [
                'generatedAt' => date('c'),
                'fileCount' => count($manifest),
                'storedFileCount' => count(array_filter($manifest, static fn (array $entry): bool => ($entry['exists'] ?? false) === true)),
                'files' => $manifest,
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $zip->addFromString('backup.json', $json);
        $zip->addFromString('manifest.json', is_string($manifestJson) ? $manifestJson : '{"files":[]}');
        $zip->close();

        @chmod($zipPath, 0600);

        return is_file($zipPath) ? $zipPath : null;
    }

    /**
     * @param array<int, array<string, mixed>> $files
     * @return array<int, array<string, mixed>>
     */
    private function publicDeletionFileManifest(array $files): array
    {
        return array_map(
            static function (array $file): array {
                unset($file['absolutePath']);

                return $file;
            },
            $files
        );
    }

    private function purgeBackedUpFiles(string $jsonPath): void
    {
        $payload = $this->readDeletionBackupPayload($jsonPath);
        $files = is_array($payload) && is_array($payload['files'] ?? null) ? $payload['files'] : [];
        $privateDocumentStorage = PrivateDocumentStorage::fromAppConfig();
        $discussionAttachmentStorage = DiscussionAttachmentStorage::fromAppConfig();

        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }

            $table = is_string($file['table'] ?? null) ? (string) $file['table'] : '';
            $storagePath = is_string($file['storagePath'] ?? null) ? (string) $file['storagePath'] : '';
            $resolver = in_array($table, ['private_documents', 'rental_documents', 'rental_agency_imported_documents'], true)
                ? $privateDocumentStorage
                : ($table === 'discussion_message_attachments' ? $discussionAttachmentStorage : null);
            $absolutePath = $resolver !== null && $storagePath !== '' ? $resolver->absolutePath($storagePath) : '';
            if ($absolutePath === '' || !is_file($absolutePath)) {
                continue;
            }

            $this->deletePrivateFileIfSafe($absolutePath, [
                $privateDocumentStorage->uploadsDirectory(),
                $discussionAttachmentStorage->uploadsDirectory(),
            ]);
        }
    }

    /**
     * @param array<int, string> $allowedRoots
     */
    private function deletePrivateFileIfSafe(string $absolutePath, array $allowedRoots = []): void
    {
        $absolutePath = $this->normalizeFilesystemPath($absolutePath);
        $rootPath = defined('ROOT_PATH') ? (string) ROOT_PATH : dirname(__DIR__, 3);
        $allowedRoots[] = rtrim($rootPath, '/\\') . '/private';

        foreach ($allowedRoots as $allowedRoot) {
            $allowedRoot = $this->normalizeFilesystemPath($allowedRoot);
            if ($allowedRoot === '') {
                continue;
            }

            if (str_starts_with($absolutePath, rtrim($allowedRoot, '/') . '/')) {
                @unlink($absolutePath);

                return;
            }
        }
    }

    private function normalizeFilesystemPath(string $path): string
    {
        $realPath = realpath($path);
        if (is_string($realPath) && $realPath !== '') {
            $path = $realPath;
        }

        return rtrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * @param array<int, array<string, mixed>> $manifest
     */
    private function uniqueArchivePath(array $manifest, string $path): string
    {
        $normalized = trim(str_replace('\\', '/', $path), '/');
        $used = [];
        foreach ($manifest as $entry) {
            if (is_string($entry['archivePath'] ?? null)) {
                $used[(string) $entry['archivePath']] = true;
            }
        }

        if (!isset($used[$normalized])) {
            return $normalized;
        }

        $directory = trim((string) pathinfo($normalized, PATHINFO_DIRNAME), '.');
        $filename = (string) pathinfo($normalized, PATHINFO_FILENAME);
        $extension = (string) pathinfo($normalized, PATHINFO_EXTENSION);
        for ($index = 2; $index < 1000; ++$index) {
            $candidate = ($directory !== '' ? $directory . '/' : '')
                . $filename . '-' . $index
                . ($extension !== '' ? '.' . $extension : '');
            if (!isset($used[$candidate])) {
                return $candidate;
            }
        }

        return ($directory !== '' ? $directory . '/' : '') . $filename . '-' . hash('sha256', $normalized) . ($extension !== '' ? '.' . $extension : '');
    }

    private function backupFilename(string $filename): string
    {
        $filename = trim(str_replace('\\', '/', $filename));
        $filename = basename($filename);
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?? '';
        $filename = trim($filename, '.-');

        return $filename !== '' ? substr($filename, 0, 120) : 'fichier';
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function deletionBackupRows(int $privateUserId): array
    {
        $params = ['private_user_id' => $privateUserId];
        $rows = [];
        foreach ($this->deletionBackupScopes() as $table => $where) {
            $rows[$table] = $this->rowsAll($table, $where, $params);
        }

        return $rows;
    }

    /**
     * Registre central des lignes SQL a sauvegarder avant purge.
     * Tout futur module prive contenant des donnees utilisateur doit ajouter ses tables ici.
     *
     * @return array<string, string>
     */
    private function deletionBackupScopes(): array
    {
        $messageIds = '`message_id` IN (SELECT `id` FROM `' . $this->database->table('discussion_messages') . '` WHERE `sender_private_user_id` = :private_user_id)';
        $summaryIds = '`tax_annual_summary_id` IN (SELECT `id` FROM `' . $this->database->table('tax_annual_summaries') . '` WHERE `private_user_id` = :private_user_id OR `generated_by_private_user_id` = :private_user_id)';
        $agencyDocumentIds = '`imported_document_id` IN (
            SELECT d.`id`
            FROM `' . $this->database->table('rental_agency_imported_documents') . '` d
            INNER JOIN `' . $this->database->table('rental_agency_import_batches') . '` b ON b.`id` = d.`batch_id`
            WHERE b.`created_by_private_user_id` = :private_user_id
        )';
        $agencyBatchDocumentIds = '`batch_id` IN (
            SELECT `id`
            FROM `' . $this->database->table('rental_agency_import_batches') . '`
            WHERE `created_by_private_user_id` = :private_user_id
        )';

        return [
            'private_users' => '`id` = :private_user_id',
            'private_user_module_permissions' => '`private_user_id` = :private_user_id',
            'private_user_invites' => '`private_user_id` = :private_user_id',
            'private_password_resets' => '`private_user_id` = :private_user_id',
            'private_mfa_backup_codes' => '`private_user_id` = :private_user_id',
            'private_documents' => '`private_user_id` = :private_user_id OR `uploaded_by_private_user_id` = :private_user_id OR `deleted_by_private_user_id` = :private_user_id',
            'private_document_categories' => '`private_user_id` = :private_user_id',
            'private_blocnote_notes' => '`private_user_id` = :private_user_id',
            'private_blocnote_categories' => '`private_user_id` = :private_user_id',
            'discussion_messages' => '`sender_private_user_id` = :private_user_id',
            'discussion_message_attachments' => $messageIds,
            'discussion_conversation_members' => '`private_user_id` = :private_user_id',
            'discussion_crypto_devices' => '`private_user_id` = :private_user_id',
            'discussion_conversation_keys' => '`private_user_id` = :private_user_id OR `created_by_private_user_id` = :private_user_id',
            'rental_properties' => '`created_by_private_user_id` = :private_user_id OR `archived_by_private_user_id` = :private_user_id',
            'rental_units' => '`created_by_private_user_id` = :private_user_id OR `archived_by_private_user_id` = :private_user_id',
            'rental_property_members' => '`private_user_id` = :private_user_id OR `added_by_private_user_id` = :private_user_id OR `removed_by_private_user_id` = :private_user_id',
            'rental_tenants' => '`created_by_private_user_id` = :private_user_id',
            'rental_leases' => '`created_by_private_user_id` = :private_user_id',
            'rental_rents' => '`created_by_private_user_id` = :private_user_id',
            'rental_payments' => '`created_by_private_user_id` = :private_user_id',
            'rental_payment_requests' => '`sent_by_private_user_id` = :private_user_id',
            'rental_expenses' => '`created_by_private_user_id` = :private_user_id',
            'rental_documents' => '`uploaded_by_private_user_id` = :private_user_id',
            'rental_generated_documents' => '`generated_by_private_user_id` = :private_user_id',
            'rental_export_logs' => '`private_user_id` = :private_user_id',
            'rental_agency_import_batches' => '`created_by_private_user_id` = :private_user_id',
            'rental_agency_imported_documents' => $agencyBatchDocumentIds,
            'rental_agency_statements' => $agencyDocumentIds,
            'rental_agency_statement_lines' => $agencyDocumentIds,
            'rental_agency_import_issues' => $agencyDocumentIds,
            'rental_agency_unit_mappings' => '`created_by_private_user_id` = :private_user_id',
            'tax_years' => '`private_user_id` = :private_user_id OR `locked_by_private_user_id` = :private_user_id OR `unlocked_by_private_user_id` = :private_user_id',
            'tax_source_activations' => '`private_user_id` = :private_user_id OR `enabled_by_private_user_id` = :private_user_id OR `disabled_by_private_user_id` = :private_user_id',
            'tax_manual_income_entries' => '`private_user_id` = :private_user_id OR `created_by_private_user_id` = :private_user_id',
            'tax_annual_summaries' => '`private_user_id` = :private_user_id OR `generated_by_private_user_id` = :private_user_id',
            'tax_summary_lines' => $summaryIds,
            'tax_export_logs' => '`private_user_id` = :private_user_id OR `exported_by_private_user_id` = :private_user_id',
        ];
    }

    /**
     * Registre central des purges SQL. L'ordre est volontaire: enfants avant parents.
     * Tout futur module prive contenant des donnees utilisateur doit ajouter ses purges ici.
     *
     * @return array<int, array{table: string, where: string}>
     */
    private function deletionPurgeScopes(): array
    {
        $backupScopes = $this->deletionBackupScopes();
        $order = [
            'discussion_message_attachments',
            'discussion_messages',
            'discussion_conversation_keys',
            'discussion_crypto_devices',
            'discussion_conversation_members',
            'private_documents',
            'private_document_categories',
            'private_blocnote_notes',
            'private_blocnote_categories',
            'rental_export_logs',
            'rental_documents',
            'rental_generated_documents',
            'rental_payment_requests',
            'rental_agency_unit_mappings',
            'rental_agency_import_issues',
            'rental_agency_statement_lines',
            'rental_agency_statements',
            'rental_agency_imported_documents',
            'rental_agency_import_batches',
            'rental_payments',
            'rental_rents',
            'rental_expenses',
            'rental_leases',
            'rental_tenants',
            'rental_units',
            'rental_property_members',
            'rental_properties',
            'tax_summary_lines',
            'tax_export_logs',
            'tax_annual_summaries',
            'tax_manual_income_entries',
            'tax_source_activations',
            'tax_years',
            'private_user_module_permissions',
            'private_mfa_backup_codes',
            'private_password_resets',
            'private_user_invites',
        ];

        $scopes = [];
        foreach ($order as $table) {
            if (isset($backupScopes[$table])) {
                $scopes[] = ['table' => $table, 'where' => $backupScopes[$table]];
            }
        }

        return $scopes;
    }

    /**
     * Registre central des fichiers physiques a integrer dans le ZIP.
     * Tout futur module prive stockant des fichiers doit ajouter son stockage ici.
     *
     * @return array<string, array{storagePath: string, name: string, resolver: object}>
     */
    private function deletionFileScopes(
        PrivateDocumentStorage $privateDocumentStorage,
        DiscussionAttachmentStorage $discussionAttachmentStorage
    ): array {
        return [
            'private_documents' => ['storagePath' => 'storagePath', 'name' => 'originalName', 'resolver' => $privateDocumentStorage],
            'rental_documents' => ['storagePath' => 'storagePath', 'name' => 'originalName', 'resolver' => $privateDocumentStorage],
            'rental_generated_documents' => ['storagePath' => 'storagePath', 'name' => 'originalName', 'resolver' => $privateDocumentStorage],
            'rental_agency_imported_documents' => ['storagePath' => 'storagePath', 'name' => 'filename', 'resolver' => $privateDocumentStorage],
            'discussion_message_attachments' => ['storagePath' => 'storagePath', 'name' => 'originalFilename', 'resolver' => $discussionAttachmentStorage],
            'discussion_message_attachment_previews' => ['storagePath' => 'previewStoragePath', 'name' => 'originalFilename', 'resolver' => $discussionAttachmentStorage],
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private function rowsAll(string $table, string $where, array $params): array
    {
        try {
            [$query, $expandedParams] = $this->expandRepeatedNamedParameters(
                sprintf('SELECT * FROM `%s` WHERE %s', $this->database->table($table), $where),
                $params
            );
            $statement = $this->database->pdo()->prepare($query);
            $statement->execute($expandedParams);
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
     * PDO/MySQL native prepares do not reliably support reusing the same named
     * placeholder several times. C2 purge scopes intentionally reuse
     * `:private_user_id` across nested predicates, so each occurrence must be
     * expanded before prepare/execute.
     *
     * @param array<string, mixed> $params
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function expandRepeatedNamedParameters(string $query, array $params): array
    {
        $seen = [];
        $expandedParams = [];
        $expandedQuery = preg_replace_callback(
            '/(?<!:):([A-Za-z_][A-Za-z0-9_]*)/',
            static function (array $matches) use (&$seen, &$expandedParams, $params): string {
                $name = (string) $matches[1];
                if (!array_key_exists($name, $params)) {
                    return (string) $matches[0];
                }

                $seen[$name] = (int) ($seen[$name] ?? 0) + 1;
                $expandedName = $seen[$name] === 1 ? $name : $name . '__' . $seen[$name];
                $expandedParams[$expandedName] = $params[$name];

                return ':' . $expandedName;
            },
            $query
        );

        if (!is_string($expandedQuery)) {
            return [$query, $params];
        }

        return [$expandedQuery, $expandedParams];
    }

    public function cleanupExpiredDeletionBackups(bool $dryRun = false, ?int $now = null, ?int $privateUserId = null): array
    {
        $root = $this->deletionBackupRoot();
        $result = [
            'root' => $root,
            'matched' => 0,
            'deleted' => 0,
            'purged' => 0,
            'backup_deleted' => 0,
            'errors' => 0,
            'dry_run' => $dryRun,
            'scope_private_user_id' => $privateUserId !== null && $privateUserId > 0 ? $privateUserId : null,
        ];

        if (!is_dir($root)) {
            return $result;
        }

        $now ??= time();

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'json') {
                continue;
            }

            $payload = $this->readDeletionBackupPayload($file->getPathname());
            if (!is_array($payload)) {
                continue;
            }

            $backupPrivateUserId = is_numeric($payload['privateUserId'] ?? null) ? (int) $payload['privateUserId'] : 0;
            if ($privateUserId !== null && $privateUserId > 0 && $backupPrivateUserId !== $privateUserId) {
                continue;
            }

            $deleteAfter = is_string($payload['deleteAfter'] ?? null)
                ? strtotime($payload['deleteAfter'])
                : false;
            if ($deleteAfter !== false && $deleteAfter <= $now) {
                ++$result['matched'];

                if ($dryRun) {
                    continue;
                }

                if ($backupPrivateUserId <= 0) {
                    ++$result['errors'];

                    continue;
                }

                try {
                    $user = $this->privateUser($backupPrivateUserId);
                    if (($user['status'] ?? '') !== 'suspended') {
                        ++$result['errors'];

                        continue;
                    }

                    $this->database->ensureReady();
                    $pdo = $this->database->pdo();
                    $pdo->beginTransaction();
                    $this->purgeAccountRows($backupPrivateUserId);

                    $statement = $pdo->prepare(
                        sprintf('DELETE FROM `%s` WHERE `id` = :id AND `status` = :status', $this->database->table('private_users'))
                    );
                    $statement->execute([
                        'id' => $backupPrivateUserId,
                        'status' => 'suspended',
                    ]);
                    $pdo->commit();
                    ++$result['purged'];
                } catch (\Throwable) {
                    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    ++$result['errors'];

                    continue;
                }

                $email = is_array($payload) ? $this->backupEmail($payload) : '';
                if ($email !== '') {
                    $this->sendPrivateAccountTemplateEmail(
                        $email,
                        'member_deletion_final_subject',
                        'Suppression définitive de votre compte privé',
                        'member_deletion_final_body',
                        "Bonjour,\n\nVotre compte privé {{site_name}} et les données rattachées ont été supprimés définitivement le {{today}}.\n\nPour toute question, vous pouvez écrire à {{reply_to}}.",
                        $payload
                    );
                }

                $zipPath = preg_replace('/\.json\z/i', '.zip', $file->getPathname()) ?? '';
                if ($zipPath !== '' && is_file($zipPath) && @unlink($zipPath)) {
                    ++$result['deleted'];
                    ++$result['backup_deleted'];
                }

                if (@unlink($file->getPathname())) {
                    ++$result['deleted'];
                    ++$result['backup_deleted'];
                }
            }
        }

        return $result;
    }

    /**
     * @return array{root: string, matched: int, sent: int, errors: int, dry_run: bool, scope_private_user_id: ?int}
     */
    public function sendPendingDeletionWarnings(bool $dryRun = false, ?int $now = null, int $warningAfterDays = 20, ?int $privateUserId = null): array
    {
        $root = $this->deletionBackupRoot();
        $result = [
            'root' => $root,
            'matched' => 0,
            'sent' => 0,
            'errors' => 0,
            'dry_run' => $dryRun,
            'scope_private_user_id' => $privateUserId !== null && $privateUserId > 0 ? $privateUserId : null,
        ];

        if (!is_dir($root)) {
            return $result;
        }

        $now ??= time();
        $warningAfterDays = max(1, $warningAfterDays);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'json') {
                continue;
            }

            $payload = $this->readDeletionBackupPayload($file->getPathname());
            if (!is_array($payload) || is_string($payload['warningSentAt'] ?? null)) {
                continue;
            }

            $backupPrivateUserId = is_numeric($payload['privateUserId'] ?? null) ? (int) $payload['privateUserId'] : 0;
            if ($privateUserId !== null && $privateUserId > 0 && $backupPrivateUserId !== $privateUserId) {
                continue;
            }

            $generatedAt = is_string($payload['generatedAt'] ?? null) ? strtotime((string) $payload['generatedAt']) : false;
            $deleteAfter = is_string($payload['deleteAfter'] ?? null) ? strtotime((string) $payload['deleteAfter']) : false;
            if ($generatedAt === false || $deleteAfter === false || $deleteAfter <= $now) {
                continue;
            }

            if (($generatedAt + ($warningAfterDays * 86400)) > $now) {
                continue;
            }

            ++$result['matched'];
            if ($dryRun) {
                continue;
            }

            $email = $this->backupEmail($payload);
            if ($email === '') {
                ++$result['errors'];

                continue;
            }

            $sent = $this->sendPrivateAccountTemplateEmail(
                $email,
                'member_deletion_warning_subject',
                'Suppression prochaine de votre compte privé',
                'member_deletion_warning_body',
                "Bonjour,\n\nVotre compte privé {{site_name}} est programmé pour suppression définitive le {{delete_after}}.\n\nUne sauvegarde ZIP des données purgées peut encore être récupérée avant cette date par l’administration de l’espace privé. Elle contient les données SQL et les fichiers retrouvés au moment de la sauvegarde. Cette sauvegarde sert uniquement à conserver ou transmettre les données : elle ne permet pas de récupérer ni de réactiver le compte.\n\nPour toute question, vous pouvez écrire à {{reply_to}}.",
                $payload
            );
            if (!$sent) {
                ++$result['errors'];

                continue;
            }

            $payload['warningSentAt'] = date('c', $now);
            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($json) || file_put_contents($file->getPathname(), $json, LOCK_EX) === false) {
                ++$result['errors'];

                continue;
            }
            @chmod($file->getPathname(), 0600);

            ++$result['sent'];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readDeletionBackupPayload(string $path): ?array
    {
        $content = @file_get_contents($path);
        $payload = is_string($content) ? json_decode($content, true) : null;

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function backupEmail(array $payload): string
    {
        $tables = is_array($payload['tables'] ?? null) ? $payload['tables'] : [];
        $users = is_array($tables['private_users'] ?? null) ? $tables['private_users'] : [];
        $user = is_array($users[0] ?? null) ? $users[0] : [];
        $email = is_string($user['email'] ?? null) ? trim((string) $user['email']) : '';

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendPrivateAccountTemplateEmail(
        string $email,
        string $subjectKey,
        string $subjectFallback,
        string $bodyKey,
        string $bodyFallback,
        array $payload
    ): bool {
        $variables = [
            'delete_after' => $this->payloadDeleteAfterLabel($payload),
        ];

        return $this->sendPrivateAccountEmail(
            $email,
            $this->renderPrivateAccountMailTemplate($this->privateMailTemplate($subjectKey, $subjectFallback), $email, $variables),
            $this->renderPrivateAccountMailTemplate($this->privateMailTemplate($bodyKey, $bodyFallback), $email, $variables)
        );
    }

    private function sendPrivateAccountEmail(string $email, string $subject, string $message): bool
    {
        if (is_callable($this->mailSender)) {
            return (bool) call_user_func($this->mailSender, $email, $subject, $message);
        }

        $mailConfig = app_config('private.mail', []);
        if (!is_array($mailConfig) || !($mailConfig['enabled'] ?? false)) {
            return false;
        }

        if (!function_exists('send_private_email')) {
            $mailerPath = ROOT_PATH . '/core/mailer.php';
            if (is_file($mailerPath)) {
                require_once $mailerPath;
            }
        }

        if (!function_exists('send_private_email')) {
            return false;
        }

        $html = '<p>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'), false) . '</p>';

        return send_private_email($email, sanitize_text_field($subject, 180), $html);
    }

    private function privateMailTemplate(string $key, string $fallback): string
    {
        $template = app_config('private.mail.templates.' . $key, $fallback);

        return is_scalar($template) ? (string) $template : $fallback;
    }

    /**
     * @param array<string, string> $variables
     */
    private function renderPrivateAccountMailTemplate(string $template, string $email, array $variables = []): string
    {
        $commonVariables = [
            'email' => $email,
            'today' => date('d/m/Y'),
            'login_url' => app_url(private_portal_url('login')),
            'private_url' => app_url(private_portal_url('login')),
            'site_name' => (string) app_config('site.name', 'Les Caramagnols'),
            'reply_to' => (string) app_config('private.mail.reply_to', 'private@lescaramagnols.com'),
        ];
        $replacements = [];
        foreach (array_merge($commonVariables, $variables) as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string) $value;
        }

        return strtr($template, $replacements);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function payloadDeleteAfterLabel(array $payload): string
    {
        $deleteAfter = is_string($payload['deleteAfter'] ?? null) ? strtotime((string) $payload['deleteAfter']) : false;

        return $deleteAfter !== false ? date('d/m/Y', $deleteAfter) : '';
    }

    private function deletionBackupRoot(): string
    {
        $backendRoot = defined('ROOT_PATH') ? (string) ROOT_PATH : dirname(__DIR__, 3);

        return rtrim($backendRoot, '/\\') . '/var/private-account-deletion-backups';
    }

    private function enforceDeletionBackupDirectoryPermissions(string $directory): void
    {
        if ($directory !== '' && is_dir($directory)) {
            @chmod($directory, 0700);
        }
    }

    /**
     * @return array{success: bool, backupPath: ?string, deleteAfter: ?string, error: ?string}
     */
    private function deletionResult(bool $success, ?string $backupPath, ?string $deleteAfter, ?string $error): array
    {
        return [
            'success' => $success,
            'backupPath' => $backupPath,
            'deleteAfter' => $deleteAfter,
            'error' => $error,
        ];
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
