<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Operations;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use PDO;

final class PrivateMigrationService
{
    public const STATUS_PHP_SOURCE = 'php_source';
    public const STATUS_MIGRATING = 'migrating';
    public const STATUS_NEW_SOURCE = 'new_source';
    public const STATUS_RETIRED = 'retired';

    /** @var array<string, array<int, string>> */
    private const MODULE_TABLES = [
        'dashboard' => [
            'private_users',
            'private_user_invites',
            'private_password_resets',
            'private_sessions',
            'private_mfa_backup_codes',
            'private_modules',
            'private_user_module_permissions',
        ],
        'documents' => [
            'private_document_categories',
            'private_documents',
        ],
        'blocnote' => [
            'private_blocnote_categories',
            'private_blocnote_notes',
        ],
        'discussions' => [
            'discussion_conversations',
            'discussion_conversation_members',
            'discussion_messages',
            'discussion_message_reads',
            'discussion_message_attachments',
            'discussion_conversation_keys',
            'discussion_crypto_devices',
            'discussion_retention_runs',
        ],
        'real_estate_rental' => [
            'rental_properties',
            'rental_units',
            'rental_property_members',
            'rental_tenants',
            'rental_leases',
            'rental_rents',
            'rental_payments',
            'rental_expenses',
            'rental_documents',
            'rental_export_logs',
            'rental_agency_import_batches',
            'rental_agency_imported_documents',
            'rental_agency_statements',
            'rental_agency_statement_lines',
            'rental_agency_import_issues',
            'rental_agency_unit_mappings',
            'rental_agency_line_mappings',
        ],
        'tax_declaration_helper' => [
            'tax_years',
            'tax_income_sources',
            'tax_source_activations',
            'tax_manual_income_entries',
            'tax_annual_summaries',
            'tax_summary_lines',
            'tax_export_logs',
        ],
    ];

    public function __construct(
        private readonly EditorialDatabase $database,
        private readonly PrivateModuleRegistry $moduleRegistry
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function allowedStatuses(): array
    {
        return [
            self::STATUS_PHP_SOURCE,
            self::STATUS_MIGRATING,
            self::STATUS_NEW_SOURCE,
            self::STATUS_RETIRED,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function moduleStatuses(): array
    {
        $this->ensureStatusTable();
        $stored = $this->storedStatuses();
        $statuses = [];

        foreach ($this->moduleRegistry->allModules() as $module) {
            $code = (string) ($module['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $statuses[$code] = array_merge(
                [
                    'module' => $code,
                    'name' => (string) ($module['name'] ?? $code),
                    'status' => self::STATUS_PHP_SOURCE,
                    'canDoubleWrite' => false,
                    'sourceChecksum' => null,
                    'targetChecksum' => null,
                    'lastReconciledAt' => null,
                    'updatedBy' => null,
                    'notes' => null,
                    'updatedAt' => null,
                ],
                $stored[$code] ?? []
            );
            $statuses[$code]['canDoubleWrite'] = ($statuses[$code]['status'] ?? '') === self::STATUS_MIGRATING;
        }

        return $statuses;
    }

    /**
     * @return array<string, mixed>
     */
    public function moduleStatus(string $moduleCode): array
    {
        $normalized = $this->normalizeModuleCode($moduleCode);
        if ($normalized === null) {
            return [
                'success' => false,
                'error' => 'unknown_module',
            ];
        }

        $statuses = $this->moduleStatuses();

        return [
            'success' => true,
        ] + ($statuses[$normalized] ?? [
            'module' => $normalized,
            'status' => self::STATUS_PHP_SOURCE,
            'canDoubleWrite' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function setModuleStatus(
        string $moduleCode,
        string $status,
        string $actorIdentifier = '',
        string $notes = ''
    ): array {
        $normalized = $this->normalizeModuleCode($moduleCode);
        if ($normalized === null) {
            return [
                'success' => false,
                'error' => 'unknown_module',
            ];
        }

        $status = strtolower(trim($status));
        if (!in_array($status, $this->allowedStatuses(), true)) {
            return [
                'success' => false,
                'error' => 'invalid_status',
            ];
        }

        $this->ensureStatusTable();
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s` (`module_code`, `status`, `updated_by`, `notes`)
                 VALUES (:module_code, :status, :updated_by, :notes)
                 ON DUPLICATE KEY UPDATE
                    `status` = VALUES(`status`),
                    `updated_by` = VALUES(`updated_by`),
                    `notes` = VALUES(`notes`),
                    `updated_at` = CURRENT_TIMESTAMP',
                $this->statusTable()
            )
        );
        $statement->execute([
            'module_code' => $normalized,
            'status' => $status,
            'updated_by' => $actorIdentifier !== '' ? mb_substr($actorIdentifier, 0, 190) : null,
            'notes' => $notes !== '' ? $notes : null,
        ]);

        return $this->moduleStatus($normalized);
    }

    public function canDoubleWrite(string $moduleCode): bool
    {
        $status = $this->moduleStatus($moduleCode);

        return ($status['success'] ?? false) === true && ($status['status'] ?? '') === self::STATUS_MIGRATING;
    }

    /**
     * @return array<int, string>
     */
    public function tablesForModule(string $moduleCode): array
    {
        $normalized = $this->normalizeModuleCode($moduleCode);
        if ($normalized === null) {
            return [];
        }

        return self::MODULE_TABLES[$normalized] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function readLegacyModel(string $moduleCode): array
    {
        $normalized = $this->normalizeModuleCode($moduleCode);
        if ($normalized === null) {
            return [
                'success' => false,
                'error' => 'unknown_module',
            ];
        }

        $this->database->ensureReady();
        $tables = [];
        foreach ($this->tablesForModule($normalized) as $table) {
            $rows = $this->fetchRows($table);
            $tables[$table] = [
                'rows' => count($rows),
                'sha256' => $this->hashRows($rows),
            ];
        }

        return [
            'success' => true,
            'source' => 'php_sql',
            'module' => $normalized,
            'tables' => $tables,
            'sha256' => $this->hashRows($tables),
        ];
    }

    /**
     * @param array<string, mixed> $backupPayload
     *
     * @return array<string, mixed>
     */
    public function importBackupModule(string $moduleCode, array $backupPayload, bool $dryRun = true): array
    {
        $normalized = $this->normalizeModuleCode($moduleCode);
        if ($normalized === null) {
            return [
                'success' => false,
                'dryRun' => $dryRun,
                'error' => 'unknown_module',
            ];
        }

        if (!$dryRun && !$this->canDoubleWrite($normalized)) {
            return [
                'success' => false,
                'dryRun' => false,
                'error' => 'module_not_in_migrating_status',
            ];
        }

        $backupTables = is_array($backupPayload['tables'] ?? null) ? $backupPayload['tables'] : [];
        $summary = [];
        $imported = 0;
        $pdo = $this->database->pdo();
        $this->database->ensureReady();

        if (!$dryRun) {
            $pdo->beginTransaction();
        }

        try {
            foreach ($this->tablesForModule($normalized) as $table) {
                $rows = is_array($backupTables[$table] ?? null) ? $backupTables[$table] : [];
                $tableImported = 0;
                if (!$dryRun) {
                    foreach ($rows as $row) {
                        if (!is_array($row) || $row === []) {
                            continue;
                        }
                        $this->upsertRow($table, $row);
                        ++$tableImported;
                    }
                }

                $imported += $tableImported;
                $summary[] = [
                    'table' => $table,
                    'rows' => count($rows),
                    'imported' => $tableImported,
                    'sha256' => $this->hashRows($rows),
                ];
            }

            if (!$dryRun) {
                $pdo->commit();
            }
        } catch (\Throwable $exception) {
            if (!$dryRun && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'success' => false,
                'dryRun' => $dryRun,
                'error' => 'import_failed',
                'message' => $exception->getMessage(),
            ];
        }

        return [
            'success' => true,
            'dryRun' => $dryRun,
            'module' => $normalized,
            'tables' => $summary,
            'rows' => array_sum(array_map(static fn (array $table): int => (int) $table['rows'], $summary)),
            'imported' => $imported,
        ];
    }

    private function ensureStatusTable(): void
    {
        $this->database->ensureReady();
        $this->database->pdo()->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `module_code` VARCHAR(80) NOT NULL,
                    `status` VARCHAR(32) NOT NULL DEFAULT "php_source",
                    `source_checksum` CHAR(64) NULL,
                    `target_checksum` CHAR(64) NULL,
                    `last_reconciled_at` DATETIME NULL,
                    `updated_by` VARCHAR(190) NULL,
                    `notes` TEXT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_private_module_migrations_module` (`module_code`),
                    KEY `idx_private_module_migrations_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                $this->statusTable()
            )
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function storedStatuses(): array
    {
        $statement = $this->database->pdo()->query(
            sprintf(
                'SELECT `module_code`, `status`, `source_checksum`, `target_checksum`, `last_reconciled_at`,
                        `updated_by`, `notes`, `updated_at`
                 FROM `%s`',
                $this->statusTable()
            )
        );
        $rows = $statement instanceof \PDOStatement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
        $statuses = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $module = (string) ($row['module_code'] ?? '');
            if ($module === '') {
                continue;
            }
            $statuses[$module] = [
                'module' => $module,
                'status' => (string) ($row['status'] ?? self::STATUS_PHP_SOURCE),
                'sourceChecksum' => $row['source_checksum'] ?? null,
                'targetChecksum' => $row['target_checksum'] ?? null,
                'lastReconciledAt' => $row['last_reconciled_at'] ?? null,
                'updatedBy' => $row['updated_by'] ?? null,
                'notes' => $row['notes'] ?? null,
                'updatedAt' => $row['updated_at'] ?? null,
            ];
        }

        return $statuses;
    }

    private function normalizeModuleCode(string $moduleCode): ?string
    {
        $normalized = strtolower(trim($moduleCode));
        if ($normalized === '' || $this->moduleRegistry->moduleCode($normalized) === null) {
            return null;
        }

        return $normalized;
    }

    private function statusTable(): string
    {
        return $this->database->table('private_module_migrations');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRows(string $table): array
    {
        try {
            $statement = $this->database->pdo()->query(sprintf('SELECT * FROM `%s`', $this->database->table($table)));
            $rows = $statement instanceof \PDOStatement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];

            return is_array($rows) ? $rows : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function upsertRow(string $table, array $row): void
    {
        $columns = array_values(
            array_filter(
                array_keys($row),
                static fn (string $column): bool => preg_match('/^[a-zA-Z0-9_]+$/', $column) === 1
            )
        );
        if ($columns === []) {
            return;
        }

        $quotedColumns = array_map(static fn (string $column): string => sprintf('`%s`', $column), $columns);
        $placeholders = array_map(static fn (int $index): string => ':v' . $index, array_keys($columns));
        $updates = array_map(
            static fn (string $column): string => sprintf('`%s` = VALUES(`%s`)', $column, $column),
            $columns
        );
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
                $this->database->table($table),
                implode(', ', $quotedColumns),
                implode(', ', $placeholders),
                implode(', ', $updates)
            )
        );

        $params = [];
        foreach ($columns as $index => $column) {
            $params['v' . $index] = $row[$column];
        }
        $statement->execute($params);
    }

    /**
     * @param array<mixed> $rows
     */
    private function hashRows(array $rows): string
    {
        $encoded = json_encode($this->normalizeValue($rows), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', is_string($encoded) ? $encoded : '');
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    private function normalizeValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalizeValue($item);
        }

        if (array_is_list($normalized)) {
            usort(
                $normalized,
                static function (mixed $left, mixed $right): int {
                    $leftJson = json_encode($left, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $rightJson = json_encode($right, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                    return strcmp(is_string($leftJson) ? $leftJson : '', is_string($rightJson) ? $rightJson : '');
                }
            );

            return $normalized;
        }

        ksort($normalized);

        return $normalized;
    }
}
