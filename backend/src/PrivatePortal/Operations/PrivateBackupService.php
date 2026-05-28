<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Operations;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class PrivateBackupService
{
    /** @var array<int, string> */
    private const DEFAULT_TABLES = [
        'private_users',
        'private_user_invites',
        'private_password_resets',
        'private_sessions',
        'private_mfa_backup_codes',
        'private_modules',
        'private_user_module_permissions',
        'private_document_categories',
        'private_documents',
        'private_blocnote_categories',
        'private_blocnote_notes',
        'private_module_migrations',
        'discussion_conversations',
        'discussion_conversation_members',
        'discussion_messages',
        'discussion_message_reads',
        'discussion_message_attachments',
        'discussion_conversation_keys',
        'discussion_crypto_devices',
        'discussion_retention_runs',
        'rental_properties',
        'rental_units',
        'rental_property_members',
        'rental_tenants',
        'rental_leases',
        'rental_payments',
        'rental_expenses',
        'rental_documents',
        'rental_export_logs',
        'rental_agency_import_batches',
        'rental_agency_imported_documents',
        'rental_agency_statements',
        'rental_agency_statement_lines',
        'rental_agency_import_issues',
        'rental_agency_line_mappings',
        'tax_years',
        'tax_income_sources',
        'tax_source_activations',
        'tax_manual_income_entries',
        'tax_annual_summaries',
        'tax_summary_lines',
        'tax_export_logs',
    ];

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function createBackup(string $targetDirectory, string $privateFilesRoot = ''): array
    {
        $targetDirectory = rtrim(str_replace('\\', '/', trim($targetDirectory)), '/');
        if ($targetDirectory === '') {
            return ['success' => false, 'error' => 'missing_target_directory'];
        }
        if (!is_dir($targetDirectory) && !@mkdir($targetDirectory, 0700, true) && !is_dir($targetDirectory)) {
            return ['success' => false, 'error' => 'target_unavailable'];
        }

        $payload = [
            'version' => 1,
            'generatedAt' => date('c'),
            'tables' => $this->tableDump(),
            'files' => $this->fileManifest($privateFilesRoot),
        ];
        $payload['summary'] = $this->summaryFromPayload($payload);
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            return ['success' => false, 'error' => 'json_failed'];
        }

        $checksum = hash('sha256', $encoded);
        $path = $targetDirectory . '/private-backup-' . date('Ymd-His') . '-' . substr($checksum, 0, 12) . '.json';
        $written = @file_put_contents($path, $encoded);
        if (!is_int($written) || $written <= 0) {
            return ['success' => false, 'error' => 'write_failed'];
        }
        @chmod($path, 0600);

        return [
            'success' => true,
            'path' => $path,
            'checksum' => $checksum,
            'tableCount' => count($payload['tables']),
            'fileCount' => count($payload['files']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyBackup(string $path): array
    {
        $path = trim($path);
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return ['valid' => false, 'error' => 'backup_not_readable'];
        }

        $content = file_get_contents($path);
        if (!is_string($content) || trim($content) === '') {
            return ['valid' => false, 'error' => 'backup_empty'];
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded) || !is_array($decoded['tables'] ?? null) || !is_array($decoded['files'] ?? null)) {
            return ['valid' => false, 'error' => 'backup_invalid'];
        }

        return [
            'valid' => true,
            'checksum' => hash('sha256', $content),
            'tableCount' => count($decoded['tables']),
            'fileCount' => count($decoded['files']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function restoreBackup(string $path, bool $dryRun = true): array
    {
        $verification = $this->verifyBackup($path);
        if (empty($verification['valid'])) {
            return ['success' => false, 'dryRun' => $dryRun, 'error' => $verification['error'] ?? 'invalid_backup'];
        }

        if (!$dryRun) {
            return ['success' => false, 'dryRun' => false, 'error' => 'unsafe_restore_requires_manual_runbook'];
        }

        return [
            'success' => true,
            'dryRun' => true,
            'verified' => true,
            'tableCount' => $verification['tableCount'] ?? 0,
            'fileCount' => $verification['fileCount'] ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reconciliationSnapshot(string $privateFilesRoot = ''): array
    {
        $this->database->ensureReady();

        $tables = [];
        foreach (self::DEFAULT_TABLES as $table) {
            $rows = $this->fetchRows($table);
            $tables[$table] = [
                'rows' => count($rows),
                'sha256' => $this->hashRows($rows),
            ];
        }

        $files = $this->fileManifest($privateFilesRoot);
        $sizeBytes = 0;
        foreach ($files as $file) {
            $sizeBytes += (int) ($file['size'] ?? 0);
        }

        return [
            'version' => 1,
            'generatedAt' => date('c'),
            'tables' => $tables,
            'files' => [
                'count' => count($files),
                'sizeBytes' => $sizeBytes,
                'sha256' => $this->hashRows($files),
                'items' => $files,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     *
     * @return array{success: bool, equal: bool, differences: array<string, mixed>}
     */
    public function compareSnapshots(array $left, array $right): array
    {
        $differences = [];
        $leftTables = is_array($left['tables'] ?? null) ? $left['tables'] : [];
        $rightTables = is_array($right['tables'] ?? null) ? $right['tables'] : [];
        $tableNames = array_values(array_unique(array_merge(array_keys($leftTables), array_keys($rightTables))));
        sort($tableNames);

        foreach ($tableNames as $table) {
            $leftTable = is_array($leftTables[$table] ?? null) ? $leftTables[$table] : [];
            $rightTable = is_array($rightTables[$table] ?? null) ? $rightTables[$table] : [];
            if (
                ($leftTable['rows'] ?? null) === ($rightTable['rows'] ?? null)
                && ($leftTable['sha256'] ?? null) === ($rightTable['sha256'] ?? null)
            ) {
                continue;
            }

            $differences['tables'][$table] = [
                'leftRows' => $leftTable['rows'] ?? null,
                'rightRows' => $rightTable['rows'] ?? null,
                'leftSha256' => $leftTable['sha256'] ?? null,
                'rightSha256' => $rightTable['sha256'] ?? null,
            ];
        }

        $leftFiles = is_array($left['files'] ?? null) ? $left['files'] : [];
        $rightFiles = is_array($right['files'] ?? null) ? $right['files'] : [];
        foreach (['count', 'sizeBytes', 'sha256'] as $key) {
            if (($leftFiles[$key] ?? null) === ($rightFiles[$key] ?? null)) {
                continue;
            }
            $differences['files'][$key] = [
                'left' => $leftFiles[$key] ?? null,
                'right' => $rightFiles[$key] ?? null,
            ];
        }

        return [
            'success' => true,
            'equal' => $differences === [],
            'differences' => $differences,
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function tableDump(): array
    {
        $this->database->ensureReady();
        $dump = [];
        foreach (self::DEFAULT_TABLES as $table) {
            $dump[$table] = $this->fetchRows($table);
        }

        return $dump;
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
     * @return array<int, array<string, mixed>>
     */
    private function fileManifest(string $root): array
    {
        $root = rtrim(str_replace('\\', '/', trim($root)), '/');
        if ($root === '' || !is_dir($root)) {
            return [];
        }

        $manifest = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            $relative = ltrim(substr($path, strlen($root)), '/');
            $mtime = $file->getMTime();
            $manifest[] = [
                'path' => $relative,
                'size' => $file->getSize(),
                'mtime' => $mtime,
                'mtimeIso' => date('c', $mtime),
                'owner' => (string) $file->getOwner(),
                'group' => (string) $file->getGroup(),
                'sha256' => hash_file('sha256', $path) ?: '',
            ];
        }

        usort(
            $manifest,
            static fn (array $left, array $right): int => strcmp((string) $left['path'], (string) $right['path'])
        );

        return $manifest;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function summaryFromPayload(array $payload): array
    {
        $tables = is_array($payload['tables'] ?? null) ? $payload['tables'] : [];
        $files = is_array($payload['files'] ?? null) ? $payload['files'] : [];
        $rows = 0;
        foreach ($tables as $tableRows) {
            $rows += is_array($tableRows) ? count($tableRows) : 0;
        }
        $sizeBytes = 0;
        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }
            $sizeBytes += (int) ($file['size'] ?? 0);
        }

        return [
            'tableCount' => count($tables),
            'rowCount' => $rows,
            'fileCount' => count($files),
            'fileSizeBytes' => $sizeBytes,
            'tablesSha256' => $this->hashRows($tables),
            'filesSha256' => $this->hashRows($files),
        ];
    }

    /**
     * @param array<mixed> $rows
     */
    private function hashRows(array $rows): string
    {
        $normalized = $this->normalizeValue($rows);
        $encoded = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

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
