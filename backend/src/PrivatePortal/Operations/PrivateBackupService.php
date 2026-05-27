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
        'private_user_module_permissions',
        'private_documents',
        'rental_properties',
        'rental_units',
        'rental_tenants',
        'rental_leases',
        'rental_payments',
        'rental_expenses',
        'tax_years',
        'tax_manual_income_entries',
        'tax_annual_summaries',
        'tax_summary_lines',
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
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function tableDump(): array
    {
        $this->database->ensureReady();
        $dump = [];
        foreach (self::DEFAULT_TABLES as $table) {
            try {
                $statement = $this->database->pdo()->query(sprintf('SELECT * FROM `%s`', $this->database->table($table)));
                $rows = $statement instanceof \PDOStatement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
                $dump[$table] = is_array($rows) ? $rows : [];
            } catch (\Throwable) {
                $dump[$table] = [];
            }
        }

        return $dump;
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
            $manifest[] = [
                'path' => $relative,
                'size' => $file->getSize(),
                'sha256' => hash_file('sha256', $path) ?: '',
            ];
        }

        return $manifest;
    }
}
