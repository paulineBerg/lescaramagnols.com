<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Operations;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class PrivateBackupService
{
    private const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
    private const DEFAULT_RECOMMENDED_MAX_BYTES = 536870912;

    /** @var array<int, string> */
    private const DEFAULT_TABLES = [
        'private_users',
        'private_user_invites',
        'private_password_resets',
        'private_sessions',
        'private_mfa_backup_codes',
        'private_user_mail_settings',
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
        'rental_rents',
        'rental_payments',
        'rental_payment_requests',
        'rental_expenses',
        'rental_documents',
        'rental_generated_documents',
        'rental_charge_regularizations',
        'rental_export_logs',
        'rental_agency_import_batches',
        'rental_agency_imported_documents',
        'rental_agency_statements',
        'rental_agency_statement_lines',
        'rental_agency_import_issues',
        'rental_agency_unit_mappings',
        'rental_agency_line_mappings',
        'tax_years',
        'tax_income_sources',
        'tax_source_activations',
        'tax_manual_income_entries',
        'tax_annual_summaries',
        'tax_summary_lines',
        'tax_export_logs',
    ];

    private int $recommendedMaxBytes;

    public function __construct(private readonly EditorialDatabase $database, ?int $recommendedMaxBytes = null)
    {
        $this->recommendedMaxBytes = $this->resolveRecommendedMaxBytes($recommendedMaxBytes);
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
        $this->enforceDirectoryPermissions($targetDirectory);

        $files = $this->fileManifest($privateFilesRoot, true);
        $payload = [
            'version' => 1,
            'generatedAt' => date('c'),
            'tables' => $this->tableDump(),
            'files' => $this->publicFileManifest($files),
        ];
        $payload['summary'] = $this->summaryFromPayload($payload);
        $encoded = json_encode($payload, self::JSON_FLAGS);
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

        $archivePath = $this->writeBackupZip($path, $encoded, $files);
        $size = $this->backupSizeSummary($path, $archivePath);
        $result = [
            'success' => true,
            'path' => $path,
            'checksum' => $checksum,
            'tableCount' => count($payload['tables']),
            'fileCount' => count($payload['files']),
            'size' => $size,
            'warnings' => $this->backupWarnings($size),
            'permissions' => $this->permissionsReport($targetDirectory, [$path, $archivePath]),
        ];
        if ($archivePath !== null) {
            $result['archivePath'] = $archivePath;
            $result['archiveChecksum'] = hash_file('sha256', $archivePath) ?: '';
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyBackup(string $path): array
    {
        $loaded = $this->loadBackupPayload($path);
        if (empty($loaded['success'])) {
            return ['valid' => false, 'error' => $loaded['error'] ?? 'backup_invalid'];
        }

        $payload = is_array($loaded['payload'] ?? null) ? $loaded['payload'] : [];
        $structure = $this->validateBackupPayload($payload);
        if (empty($structure['valid'])) {
            return ['valid' => false, 'error' => $structure['error'] ?? 'backup_invalid'];
        }

        $archive = $this->verifyBackupArchive(
            is_string($loaded['archivePath'] ?? null) ? (string) $loaded['archivePath'] : null,
            $payload
        );
        if (empty($archive['valid'])) {
            return ['valid' => false, 'error' => $archive['error'] ?? 'backup_archive_invalid'];
        }

        $resolvedPath = is_string($loaded['path'] ?? null) ? (string) $loaded['path'] : $path;
        $archivePath = is_string($archive['archivePath'] ?? null) ? (string) $archive['archivePath'] : null;
        $size = $this->backupSizeSummary($resolvedPath, $archivePath);

        return [
            'valid' => true,
            'format' => $loaded['format'] ?? 'json',
            'path' => $resolvedPath,
            'checksum' => hash('sha256', (string) ($loaded['content'] ?? '')),
            'payloadChecksum' => hash('sha256', (string) ($loaded['payloadContent'] ?? '')),
            'tableCount' => $structure['tableCount'],
            'rowCount' => $structure['rowCount'],
            'fileCount' => $structure['fileCount'],
            'archiveAvailable' => $archive['archiveAvailable'],
            'archivePath' => $archive['archivePath'],
            'archiveChecksum' => $archive['archiveChecksum'],
            'storedFileCount' => $archive['storedFileCount'],
            'missingArchiveFiles' => $archive['missingArchiveFiles'],
            'hashMismatchFiles' => $archive['hashMismatchFiles'],
            'size' => $size,
            'warnings' => $this->backupWarnings($size),
            'permissions' => $this->permissionsReport(dirname($resolvedPath), [$resolvedPath, $archivePath]),
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
            return [
                'success' => false,
                'dryRun' => false,
                'error' => 'unsafe_restore_requires_manual_runbook',
                'requiredConditions' => $this->realRestoreConditions(),
            ];
        }

        $loaded = $this->loadBackupPayload($path);
        $payload = is_array($loaded['payload'] ?? null) ? $loaded['payload'] : [];
        $analysis = $this->dryRunRestoreAnalysis($payload, $verification);
        $sql = is_array($analysis['sql'] ?? null) ? $analysis['sql'] : [];
        $files = is_array($analysis['files'] ?? null) ? $analysis['files'] : [];
        $canDryRunRestore = ($sql['missingTables'] ?? []) === []
            && ($sql['unknownColumns'] ?? []) === []
            && (bool) ($files['restorable'] ?? false);

        return array_merge([
            'success' => $canDryRunRestore,
            'dryRun' => true,
            'verified' => true,
            'tableCount' => $verification['tableCount'] ?? 0,
            'fileCount' => $verification['fileCount'] ?? 0,
        ], $analysis);
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
    private function fileManifest(string $root, bool $includeAbsolutePath = false): array
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
            $entry = [
                'path' => $relative,
                'archivePath' => 'files/' . $this->backupArchivePath($relative),
                'size' => $file->getSize(),
                'mtime' => $mtime,
                'mtimeIso' => date('c', $mtime),
                'owner' => (string) $file->getOwner(),
                'group' => (string) $file->getGroup(),
                'sha256' => hash_file('sha256', $path) ?: '',
            ];
            if ($includeAbsolutePath) {
                $entry['absolutePath'] = $path;
            }
            $manifest[] = $entry;
        }

        usort(
            $manifest,
            static fn (array $left, array $right): int => strcmp((string) $left['path'], (string) $right['path'])
        );

        return $manifest;
    }

    /**
     * @param array<int, array<string, mixed>> $files
     */
    private function writeBackupZip(string $jsonPath, string $json, array $files): ?string
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

        $publicFiles = $this->publicFileManifest($files);
        $storedFileCount = 0;
        foreach ($files as $file) {
            $absolutePath = is_string($file['absolutePath'] ?? null) ? (string) $file['absolutePath'] : '';
            $archivePath = is_string($file['archivePath'] ?? null) ? (string) $file['archivePath'] : '';
            if ($absolutePath === '' || $archivePath === '' || !is_file($absolutePath) || !is_readable($absolutePath)) {
                continue;
            }

            if ($zip->addFile($absolutePath, $archivePath)) {
                ++$storedFileCount;
            }
        }

        $manifestJson = json_encode(
            [
                'generatedAt' => date('c'),
                'fileCount' => count($publicFiles),
                'storedFileCount' => $storedFileCount,
                'files' => $publicFiles,
            ],
            self::JSON_FLAGS
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
    private function publicFileManifest(array $files): array
    {
        return array_map(
            static function (array $file): array {
                unset($file['absolutePath']);

                return $file;
            },
            $files
        );
    }

    private function resolveRecommendedMaxBytes(?int $configured): int
    {
        if ($configured !== null && $configured > 0) {
            return $configured;
        }

        $appConfigured = function_exists('app_config') ? app_config('private.backup.recommended_max_bytes', null) : null;
        if (is_numeric($appConfigured) && (int) $appConfigured > 0) {
            return (int) $appConfigured;
        }

        return self::DEFAULT_RECOMMENDED_MAX_BYTES;
    }

    private function enforceDirectoryPermissions(string $directory): void
    {
        if ($directory !== '' && is_dir($directory)) {
            @chmod($directory, 0700);
        }
    }

    /**
     * @return array{jsonBytes: int, archiveBytes: int, actualBytes: int, recommendedMaxBytes: int, thresholdExceeded: bool}
     */
    private function backupSizeSummary(string $path, ?string $archivePath): array
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $jsonBytes = $extension === 'json' && is_file($path) ? (int) filesize($path) : 0;
        $archiveBytes = is_string($archivePath) && $archivePath !== '' && is_file($archivePath)
            ? (int) filesize($archivePath)
            : ($extension === 'zip' && is_file($path) ? (int) filesize($path) : 0);
        $actualBytes = $archiveBytes > 0 ? $archiveBytes : $jsonBytes;

        return [
            'jsonBytes' => $jsonBytes,
            'archiveBytes' => $archiveBytes,
            'actualBytes' => $actualBytes,
            'recommendedMaxBytes' => $this->recommendedMaxBytes,
            'thresholdExceeded' => $actualBytes > $this->recommendedMaxBytes,
        ];
    }

    /**
     * @param array<string, mixed> $size
     * @return array<int, array<string, mixed>>
     */
    private function backupWarnings(array $size): array
    {
        if (($size['thresholdExceeded'] ?? false) !== true) {
            return [];
        }

        return [[
            'code' => 'backup_recommended_size_exceeded',
            'level' => 'warning',
            'message' => 'La sauvegarde depasse la taille maximale recommandee; verifier duree de generation, espace disque et retention avant exploitation.',
            'actualBytes' => (int) ($size['actualBytes'] ?? 0),
            'recommendedMaxBytes' => (int) ($size['recommendedMaxBytes'] ?? 0),
        ]];
    }

    /**
     * @param array<int, string|null> $files
     * @return array<string, mixed>
     */
    private function permissionsReport(string $directory, array $files): array
    {
        $directoryMode = $this->permissionMode($directory);
        $fileReports = [];
        $filesOk = true;
        foreach ($files as $file) {
            if (!is_string($file) || $file === '' || !is_file($file)) {
                continue;
            }

            $mode = $this->permissionMode($file);
            $ok = $mode === '0600';
            $filesOk = $filesOk && $ok;
            $fileReports[] = [
                'path' => $file,
                'mode' => $mode,
                'expected' => '0600',
                'ok' => $ok,
            ];
        }

        $directoryOk = $directoryMode === '0700';

        return [
            'ok' => $directoryOk && $filesOk,
            'directories' => [[
                'path' => $directory,
                'mode' => $directoryMode,
                'expected' => '0700',
                'ok' => $directoryOk,
            ]],
            'files' => $fileReports,
        ];
    }

    private function permissionMode(string $path): ?string
    {
        if ($path === '' || !file_exists($path)) {
            return null;
        }

        $permissions = @fileperms($path);
        if (!is_int($permissions)) {
            return null;
        }

        return substr(sprintf('%04o', $permissions & 0777), -4);
    }

    private function backupArchivePath(string $path): string
    {
        $parts = array_filter(explode('/', str_replace('\\', '/', $path)), static fn (string $part): bool => $part !== '');
        $safeParts = array_map(
            static function (string $part): string {
                $part = preg_replace('/[^A-Za-z0-9._-]+/', '-', $part) ?? '';
                $part = trim($part, '.-');

                return $part !== '' ? substr($part, 0, 120) : 'fichier';
            },
            $parts
        );

        return implode('/', $safeParts !== [] ? $safeParts : ['fichier']);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadBackupPayload(string $path): array
    {
        $path = trim($path);
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return ['success' => false, 'error' => 'backup_not_readable'];
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === 'zip') {
            if (!class_exists(\ZipArchive::class)) {
                return ['success' => false, 'error' => 'zip_extension_unavailable'];
            }

            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) {
                return ['success' => false, 'error' => 'zip_unreadable'];
            }
            $content = $zip->getFromName('backup.json');
            $zip->close();
            if (!is_string($content) || trim($content) === '') {
                return ['success' => false, 'error' => 'zip_backup_json_missing'];
            }

            $payload = json_decode($content, true);

            return [
                'success' => is_array($payload),
                'error' => is_array($payload) ? null : 'backup_invalid',
                'format' => 'zip',
                'path' => $path,
                'archivePath' => $path,
                'content' => (string) file_get_contents($path),
                'payloadContent' => $content,
                'payload' => is_array($payload) ? $payload : [],
            ];
        }

        $content = file_get_contents($path);
        if (!is_string($content) || trim($content) === '') {
            return ['success' => false, 'error' => 'backup_empty'];
        }

        $payload = json_decode($content, true);
        $zipPath = preg_replace('/\.json\z/i', '.zip', $path) ?? '';

        return [
            'success' => is_array($payload),
            'error' => is_array($payload) ? null : 'backup_invalid',
            'format' => 'json',
            'path' => $path,
            'archivePath' => $zipPath !== '' && is_file($zipPath) && is_readable($zipPath) ? $zipPath : null,
            'content' => $content,
            'payloadContent' => $content,
            'payload' => is_array($payload) ? $payload : [],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function validateBackupPayload(array $payload): array
    {
        $tables = is_array($payload['tables'] ?? null) ? $payload['tables'] : null;
        $files = is_array($payload['files'] ?? null) ? $payload['files'] : null;
        if ($tables === null || $files === null) {
            return ['valid' => false, 'error' => 'backup_invalid'];
        }

        $rowCount = 0;
        foreach ($tables as $table => $rows) {
            if (!is_string($table) || !is_array($rows)) {
                return ['valid' => false, 'error' => 'backup_tables_invalid'];
            }
            $rowCount += count($rows);
        }

        foreach ($files as $file) {
            if (!is_array($file)) {
                return ['valid' => false, 'error' => 'backup_files_invalid'];
            }
        }

        return [
            'valid' => true,
            'tableCount' => count($tables),
            'rowCount' => $rowCount,
            'fileCount' => count($files),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function verifyBackupArchive(?string $archivePath, array $payload): array
    {
        $result = [
            'valid' => true,
            'archiveAvailable' => false,
            'archivePath' => null,
            'archiveChecksum' => null,
            'storedFileCount' => 0,
            'missingArchiveFiles' => [],
            'hashMismatchFiles' => [],
        ];

        if ($archivePath === null || $archivePath === '' || !is_file($archivePath)) {
            return $result;
        }

        if (!class_exists(\ZipArchive::class)) {
            return ['valid' => false, 'error' => 'zip_extension_unavailable'] + $result;
        }

        $zip = new \ZipArchive();
        if ($zip->open($archivePath) !== true) {
            return ['valid' => false, 'error' => 'zip_unreadable'] + $result;
        }

        if ($zip->locateName('backup.json') === false || $zip->locateName('manifest.json') === false) {
            $zip->close();

            return ['valid' => false, 'error' => 'zip_structure_invalid'] + $result;
        }

        $backupJson = $zip->getFromName('backup.json');
        $backupPayload = is_string($backupJson) ? json_decode($backupJson, true) : null;
        $payloadForComparison = $payload;
        if (isset($payloadForComparison['archive'])) {
            unset($payloadForComparison['archive']);
        }
        if (is_array($backupPayload) && isset($backupPayload['archive'])) {
            unset($backupPayload['archive']);
        }
        $payloadJson = json_encode($payloadForComparison, self::JSON_FLAGS);
        $backupPayloadJson = json_encode($backupPayload, self::JSON_FLAGS);
        if (!is_string($backupPayloadJson) || !is_string($payloadJson) || hash('sha256', $backupPayloadJson) !== hash('sha256', $payloadJson)) {
            $zip->close();

            return ['valid' => false, 'error' => 'zip_backup_json_mismatch'] + $result;
        }

        $files = is_array($payload['files'] ?? null) ? $payload['files'] : [];
        $missing = [];
        $mismatches = [];
        $stored = 0;
        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }

            $archiveFilePath = is_string($file['archivePath'] ?? null) ? (string) $file['archivePath'] : '';
            if ($archiveFilePath === '') {
                continue;
            }

            if ($zip->locateName($archiveFilePath) === false) {
                $missing[] = $archiveFilePath;
                continue;
            }

            ++$stored;
            $expectedHash = is_string($file['sha256'] ?? null) ? (string) $file['sha256'] : '';
            if ($expectedHash === '') {
                continue;
            }

            $content = $zip->getFromName($archiveFilePath);
            if (!is_string($content) || hash('sha256', $content) !== $expectedHash) {
                $mismatches[] = $archiveFilePath;
            }
        }
        $zip->close();

        return [
            'valid' => $missing === [] && $mismatches === [],
            'error' => $missing !== [] ? 'zip_file_missing' : ($mismatches !== [] ? 'zip_file_hash_mismatch' : null),
            'archiveAvailable' => true,
            'archivePath' => $archivePath,
            'archiveChecksum' => hash_file('sha256', $archivePath) ?: '',
            'storedFileCount' => $stored,
            'missingArchiveFiles' => array_slice($missing, 0, 20),
            'hashMismatchFiles' => array_slice($mismatches, 0, 20),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $verification
     * @return array<string, mixed>
     */
    private function dryRunRestoreAnalysis(array $payload, array $verification): array
    {
        $tables = is_array($payload['tables'] ?? null) ? $payload['tables'] : [];
        $missingTables = [];
        $unknownColumns = [];
        $conflicts = [];
        $rowCount = 0;
        $checkedRows = 0;

        $this->database->ensureReady();
        foreach ($tables as $table => $rows) {
            if (!is_string($table) || !is_array($rows)) {
                continue;
            }
            $rowCount += count($rows);
            $columns = $this->tableColumns($table);
            if ($columns === []) {
                if ($rows !== []) {
                    $missingTables[] = $table;
                }
                continue;
            }

            $uniqueIndexes = $this->uniqueIndexes($table);
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                ++$checkedRows;
                $normalizedRow = $this->normalizeRowForTable($row, $columns);
                foreach (array_keys($normalizedRow['unknown']) as $column) {
                    $unknownColumns[$table][$column] = true;
                }

                foreach ($uniqueIndexes as $indexName => $indexColumns) {
                    if (!$this->rowHasIndexValues($normalizedRow['row'], $indexColumns)) {
                        continue;
                    }
                    if (!$this->rowConflictExists($table, $indexColumns, $normalizedRow['row'])) {
                        continue;
                    }

                    $conflicts[] = [
                        'table' => $table,
                        'index' => $indexName,
                        'columns' => $indexColumns,
                    ];
                    break;
                }
            }
        }

        $unknownColumnSummary = [];
        foreach ($unknownColumns as $table => $columns) {
            $unknownColumnSummary[$table] = array_keys($columns);
        }

        $fileCount = (int) ($verification['fileCount'] ?? 0);
        $archiveAvailable = (bool) ($verification['archiveAvailable'] ?? false);
        $missingArchiveFiles = is_array($verification['missingArchiveFiles'] ?? null) ? $verification['missingArchiveFiles'] : [];
        $hashMismatchFiles = is_array($verification['hashMismatchFiles'] ?? null) ? $verification['hashMismatchFiles'] : [];

        return [
            'sql' => [
                'rowCount' => $rowCount,
                'checkedRows' => $checkedRows,
                'missingTables' => $missingTables,
                'unknownColumns' => $unknownColumnSummary,
                'conflictCount' => count($conflicts),
                'conflicts' => array_slice($conflicts, 0, 20),
                'canApplyCleanly' => $missingTables === [] && $conflicts === [] && $unknownColumnSummary === [],
            ],
            'files' => [
                'declaredFiles' => $fileCount,
                'archiveAvailable' => $archiveAvailable,
                'storedFiles' => (int) ($verification['storedFileCount'] ?? 0),
                'missingArchiveFiles' => $missingArchiveFiles,
                'hashMismatchFiles' => $hashMismatchFiles,
                'restorable' => $fileCount === 0 || ($archiveAvailable && $missingArchiveFiles === [] && $hashMismatchFiles === []),
            ],
            'requiredConditions' => $this->realRestoreConditions(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function tableColumns(string $table): array
    {
        try {
            $statement = $this->database->pdo()->query(sprintf('DESCRIBE `%s`', $this->database->table($table)));
            $rows = $statement instanceof \PDOStatement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (\Throwable) {
            return [];
        }

        $columns = [];
        foreach ($rows as $row) {
            if (is_string($row['Field'] ?? null)) {
                $columns[] = (string) $row['Field'];
            }
        }

        return $columns;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function uniqueIndexes(string $table): array
    {
        try {
            $statement = $this->database->pdo()->query(sprintf('SHOW INDEX FROM `%s` WHERE `Non_unique` = 0', $this->database->table($table)));
            $rows = $statement instanceof \PDOStatement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (\Throwable) {
            return [];
        }

        $indexes = [];
        foreach ($rows as $row) {
            $keyName = is_string($row['Key_name'] ?? null) ? (string) $row['Key_name'] : '';
            $columnName = is_string($row['Column_name'] ?? null) ? (string) $row['Column_name'] : '';
            $sequence = is_numeric($row['Seq_in_index'] ?? null) ? (int) $row['Seq_in_index'] : 0;
            if ($keyName === '' || $columnName === '') {
                continue;
            }
            $indexes[$keyName][$sequence] = $columnName;
        }

        foreach ($indexes as $keyName => $columns) {
            ksort($columns);
            $indexes[$keyName] = array_values($columns);
        }

        return $indexes;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $columns
     * @return array{row: array<string, mixed>, unknown: array<string, mixed>}
     */
    private function normalizeRowForTable(array $row, array $columns): array
    {
        $columnMap = array_fill_keys($columns, true);
        $normalized = [];
        $unknown = [];
        foreach ($row as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $column = isset($columnMap[$key]) ? $key : $this->snakeKey($key);
            if (isset($columnMap[$column])) {
                $normalized[$column] = $value;
                continue;
            }
            $unknown[$column] = $value;
        }

        return ['row' => $normalized, 'unknown' => $unknown];
    }

    private function snakeKey(string $key): string
    {
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $key) ?? $key;

        return strtolower($snake);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $indexColumns
     */
    private function rowHasIndexValues(array $row, array $indexColumns): bool
    {
        foreach ($indexColumns as $column) {
            if (!array_key_exists($column, $row) || $row[$column] === null) {
                return false;
            }
        }

        return $indexColumns !== [];
    }

    /**
     * @param array<int, string> $indexColumns
     * @param array<string, mixed> $row
     */
    private function rowConflictExists(string $table, array $indexColumns, array $row): bool
    {
        $where = [];
        $params = [];
        foreach ($indexColumns as $index => $column) {
            $parameter = 'value_' . $index;
            $where[] = '`' . $column . '` = :' . $parameter;
            $params[$parameter] = $row[$column];
        }

        try {
            $statement = $this->database->pdo()->prepare(
                sprintf('SELECT 1 FROM `%s` WHERE %s LIMIT 1', $this->database->table($table), implode(' AND ', $where))
            );
            $statement->execute($params);

            return (bool) $statement->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, string>
     */
    private function realRestoreConditions(): array
    {
        return [
            'Restaurer uniquement depuis une sauvegarde ZIP/JSON verifiee et conservee hors webroot.',
            'Prendre un snapshot SQL et fichiers de la cible avant toute ecriture.',
            'Executer le dry-run et traiter explicitement les tables manquantes, colonnes inconnues et conflits d index.',
            'Restaurer les fichiers prives dans leur stockage hors webroot avant de rouvrir les acces utilisateur.',
            'Journaliser l operateur, le chemin de sauvegarde, le checksum et le resultat de restauration.',
        ];
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
        $encoded = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

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
