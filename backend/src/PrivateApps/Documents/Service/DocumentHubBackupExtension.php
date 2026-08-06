<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Service;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivatePortal\Operations\PrivateBackupService;

/**
 * Extension de sauvegarde pour le Document Hub.
 * Gère la sauvegarde des tables spécifiques et des fichiers CAS.
 *
 * Ce service est conçu pour être utilisé en complément de PrivateBackupService,
 * en ajoutant la gestion des objets documentaires CAS et des métadonnées.
 */
final class DocumentHubBackupExtension
{
    private const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
    private const MANIFEST_FILENAME = 'document-hub-manifest.json';
    private const CHECKSUMS_FILENAME = 'document-hub-SHA256SUMS';
    private const LOCK_FILENAME = 'document-hub-backup.lock';

    public function __construct(
        private readonly EditorialDatabase $database,
        private readonly DocumentStorageService $storage,
        private readonly \Caramagnols\PrivateApps\Documents\Repository\DocumentHubRepository $repository
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function planDocumentBackup(string $targetDirectory, bool $includeDerivatives = false): array
    {
        $targetDirectory = rtrim(str_replace('\\', '/', trim($targetDirectory)), '/');
        if ($targetDirectory === '') {
            return ['success' => false, 'error' => 'missing_target_directory'];
        }

        $objectsManifest = $this->createObjectsManifest($includeDerivatives);
        $tablesPayload = $this->dumpHubTables();

        return [
            'success' => true,
            'path' => $targetDirectory,
            'objects_count' => $objectsManifest['count'],
            'objects_size_bytes' => $objectsManifest['total_size_bytes'],
            'files_backed_up' => 0,
            'would_backup_files' => $objectsManifest['count'],
            'tables' => $tablesPayload,
            'include_derivatives' => $includeDerivatives,
            'duration_seconds' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createDocumentBackup(string $targetDirectory, bool $includeDerivatives = false): array
    {
        $targetDirectory = rtrim(str_replace('\\', '/', trim($targetDirectory)), '/');
        if ($targetDirectory === '') {
            return ['success' => false, 'error' => 'missing_target_directory'];
        }

        if (!is_dir($targetDirectory) && !@mkdir($targetDirectory, 0700, true) && !is_dir($targetDirectory)) {
            return ['success' => false, 'error' => 'target_unavailable'];
        }

        $this->enforceDirectoryPermissions($targetDirectory);

        // Créer un verrou pour éviter les sauvegardes concurrentes
        $lockPath = $targetDirectory . '/' . self::LOCK_FILENAME;
        if (!$this->acquireLock($lockPath)) {
            return ['success' => false, 'error' => 'backup_locked'];
        }

        try {
            $startTime = time();

            // 1. Sauvegarder les tables spécifiques du hub
            $tablesPayload = $this->dumpHubTables();

            // 2. Créer le manifeste des objets physiques
            $objectsManifest = $this->createObjectsManifest($includeDerivatives);

            // 3. Copier les fichiers physiques
            $filesReport = $this->backupPhysicalFiles($targetDirectory, $includeDerivatives);

            // 4. Générer les checksums
            $checksumsReport = $this->generateChecksums($targetDirectory, $objectsManifest, $includeDerivatives);

            // 5. Sauvegarder le manifeste
            $manifest = [
                'version' => 1,
                'generated_at' => date('c'),
                'timezone' => date_default_timezone_get(),
                'php_version' => PHP_VERSION,
                'app_version' => $this->getAppVersion(),
                'schema_version' => $this->getSchemaVersion(),
                'tables' => $tablesPayload,
                'objects' => $objectsManifest,
                'files' => [
                    'backed_up' => $filesReport['copied'],
                    'skipped' => $filesReport['skipped'],
                    'errors' => $filesReport['errors'],
                ],
                'checksums' => [
                    'generated' => $checksumsReport['generated'],
                    'verified' => $checksumsReport['verified'],
                ],
                'duration_seconds' => time() - $startTime,
            ];

            $manifestPath = $targetDirectory . '/' . self::MANIFEST_FILENAME;
            $encoded = json_encode($manifest, self::JSON_FLAGS);
            if (!is_string($encoded)) {
                return ['success' => false, 'error' => 'json_encode_failed'];
            }

            if (@file_put_contents($manifestPath, $encoded) === false) {
                return ['success' => false, 'error' => 'manifest_write_failed'];
            }
            @chmod($manifestPath, 0600);

            $result = [
                'success' => true,
                'path' => $targetDirectory,
                'manifest_path' => $manifestPath,
                'objects_count' => $manifest['objects']['count'],
                'objects_size_bytes' => $manifest['objects']['total_size_bytes'],
                'files_backed_up' => $filesReport['copied'],
                'duration_seconds' => $manifest['duration_seconds'],
                'manifest_checksum' => hash_file('sha256', $manifestPath) ?: '',
            ];

            if ($checksumsReport['path'] !== null) {
                $result['checksums_path'] = $checksumsReport['path'];
            }

            return $result;
        } finally {
            $this->releaseLock($lockPath);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyDocumentBackup(string $targetDirectory): array
    {
        $manifestPath = $targetDirectory . '/' . self::MANIFEST_FILENAME;
        if (!is_file($manifestPath) || !is_readable($manifestPath)) {
            return ['valid' => false, 'error' => 'manifest_not_found'];
        }

        $content = @file_get_contents($manifestPath);
        if (!is_string($content)) {
            return ['valid' => false, 'error' => 'manifest_unreadable'];
        }

        $manifest = json_decode($content, true);
        if (!is_array($manifest)) {
            return ['valid' => false, 'error' => 'manifest_invalid_json'];
        }

        $requiredKeys = ['version', 'generated_at', 'objects', 'tables'];
        foreach ($requiredKeys as $key) {
            if (!isset($manifest[$key])) {
                return ['valid' => false, 'error' => "missing_manifest_key:{$key}"];
            }
        }

        // Vérifier les checksums
        $checksumsPath = $targetDirectory . '/' . self::CHECKSUMS_FILENAME;
        $checksumsValid = true;
        $checksumsErrors = [];

        if (is_file($checksumsPath)) {
            $checksumsContent = @file_get_contents($checksumsPath);
            if (is_string($checksumsContent)) {
                $lines = explode("\n", trim($checksumsContent));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }

                    $parts = preg_split('/\s+/', $line, 2);
                    if (count($parts) !== 2) {
                        $checksumsErrors[] = "Invalid checksum line: {$line}";
                        continue;
                    }

                    [$expectedHash, $relativePath] = $parts;
                    $fullPath = $targetDirectory . '/' . $relativePath;

                    if (!is_file($fullPath)) {
                        $checksumsErrors[] = "Missing file: {$relativePath}";
                        $checksumsValid = false;
                        continue;
                    }

                    $actualHash = hash_file('sha256', $fullPath);
                    if ($actualHash === false || $actualHash !== $expectedHash) {
                        $checksumsErrors[] = "Hash mismatch: {$relativePath}";
                        $checksumsValid = false;
                    }
                }
            }
        }

        // Vérifier que tous les fichiers référencés dans le manifeste existent
        $objects = is_array($manifest['objects']['items'] ?? null) ? $manifest['objects']['items'] : [];
        $missingFiles = 0;
        foreach ($objects as $object) {
            $storageKey = (string) ($object['storage_key'] ?? '');
            if ($storageKey !== '') {
                $relativePath = $this->storageKeyToRelativePath($storageKey);
                $fullPath = $targetDirectory . '/' . $relativePath;
                if (!is_file($fullPath)) {
                    $missingFiles++;
                }
            }
        }

        return [
            'valid' => $checksumsValid && $missingFiles === 0,
            'manifest_path' => $manifestPath,
            'objects_count' => count($objects),
            'checksums_valid' => $checksumsValid,
            'checksums_errors' => $checksumsErrors,
            'missing_files' => $missingFiles,
            'generated_at' => $manifest['generated_at'] ?? '',
            'app_version' => $manifest['app_version'] ?? '',
            'schema_version' => $manifest['schema_version'] ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function restoreDocumentBackup(string $sourceDirectory, bool $dryRun = true): array
    {
        $manifestPath = $sourceDirectory . '/' . self::MANIFEST_FILENAME;
        if (!is_file($manifestPath)) {
            return ['success' => false, 'dryRun' => $dryRun, 'error' => 'manifest_not_found'];
        }

        $content = @file_get_contents($manifestPath);
        if (!is_string($content)) {
            return ['success' => false, 'dryRun' => $dryRun, 'error' => 'manifest_unreadable'];
        }

        $manifest = json_decode($content, true);
        if (!is_array($manifest)) {
            return ['success' => false, 'dryRun' => $dryRun, 'error' => 'manifest_invalid_json'];
        }

        if ($dryRun) {
            // Mode dry-run : vérifier ce qui serait restauré
            return $this->dryRunRestore($manifest, $sourceDirectory);
        }

        // La restauration réelle nécessite une confirmation explicite
        // et doit être effectuée selon une procédure manuelle
        return [
            'success' => false,
            'dryRun' => false,
            'error' => 'restore_requires_manual_confirmation',
            'requires' => [
                'manual_verification_of_backup_integrity',
                'explicit_user_confirmation',
                'database_backup_before_restore',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dumpHubTables(): array
    {
        $tables = [
            'private_document_objects',
            'private_document_library',
            'private_document_links',
            'private_document_versions',
            'private_document_derivatives',
            'private_document_import_jobs',
            'private_document_taxonomy',
        ];

        $result = [];
        foreach ($tables as $table) {
            try {
                $rows = $this->fetchTableRows($table);
                $result[$table] = [
                    'rows' => count($rows),
                    'sha256' => $this->hashRows($rows),
                ];
            } catch (\Throwable $e) {
                $result[$table] = [
                    'rows' => 0,
                    'sha256' => '',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function createObjectsManifest(bool $includeDerivatives): array
    {
        $manifest = [
            'count' => 0,
            'total_size_bytes' => 0,
            'items' => [],
        ];

        try {
            $objects = $this->repository->allObjects();
            foreach ($objects as $object) {
                $sha256 = (string) ($object['sha256'] ?? '');
                $storageKey = (string) ($object['storage_key'] ?? '');
                $mimeType = (string) ($object['mime_type'] ?? '');
                $originalSize = (int) ($object['original_size'] ?? 0);
                $storedSize = (int) ($object['stored_size'] ?? 0);
                $status = (string) ($object['status'] ?? '');

                // Seuls les objets 'ready' sont sauvegardés
                if ($status !== 'ready') {
                    continue;
                }

                $entry = [
                    'sha256' => $sha256,
                    'storage_key' => $storageKey,
                    'mime_type' => $mimeType,
                    'extension' => (string) ($object['extension'] ?? ''),
                    'original_size' => $originalSize,
                    'stored_size' => $storedSize,
                    'status' => $status,
                    'created_at' => (string) ($object['created_at'] ?? ''),
                ];

                // Vérifier si le fichier existe
                $absolutePath = $this->storage->absolutePathForKey($storageKey);
                $fileExists = $absolutePath !== null && is_file($absolutePath);
                $entry['file_exists'] = $fileExists;
                $entry['file_size'] = $fileExists ? (int) filesize($absolutePath) : 0;

                if ($fileExists) {
                    $entry['file_sha256'] = hash_file('sha256', $absolutePath) ?: '';
                    $entry['file_mtime'] = date('c', (int) filemtime($absolutePath));
                }

                $manifest['items'][] = $entry;
                $manifest['count']++;
                $manifest['total_size_bytes'] += $storedSize;
            }
        } catch (\Throwable $e) {
            // Erreur lors de la récupération des objets
        }

        return $manifest;
    }

    /**
     * @return array{copied: int, skipped: int, errors: array<string, string>}
     */
    private function backupPhysicalFiles(string $targetDirectory, bool $includeDerivatives): array
    {
        $report = ['copied' => 0, 'skipped' => 0, 'errors' => []];

        try {
            // Copier les objets CAS
            $objectsPath = $this->storage->rootPath() . '/objects';
            if (is_dir($objectsPath)) {
                $report = array_merge(
                    $report, $this->copyDirectory(
                        $objectsPath,
                        $targetDirectory . '/objects',
                        ['include_pattern' => '/\.\w+$/']
                    )
                );
            }

            // Copier les dérivés si demandé
            if ($includeDerivatives) {
                $derivativesPath = $this->storage->rootPath() . '/derivatives';
                if (is_dir($derivativesPath)) {
                    $report = array_merge(
                        $report, $this->copyDirectory(
                            $derivativesPath,
                            $targetDirectory . '/derivatives',
                            ['include_pattern' => '/\.(jpg|jpeg|png|webp|gif)$/i']
                        )
                    );
                }
            }
        } catch (\Throwable $e) {
            $report['errors'][] = 'copy_failed: ' . $e->getMessage();
        }

        return $report;
    }

    /**
     * @return array{generated: bool, verified: int, path: ?string}
     */
    private function generateChecksums(string $targetDirectory, array $objectsManifest, bool $includeDerivatives): array
    {
        $checksumsPath = $targetDirectory . '/' . self::CHECKSUMS_FILENAME;
        $lines = [];
        $verified = 0;

        try {
            // Checksum des objets
            foreach ($objectsManifest['items'] ?? [] as $object) {
                $storageKey = (string) ($object['storage_key'] ?? '');
                if ($storageKey === '') {
                    continue;
                }

                $relativePath = $this->storageKeyToRelativePath($storageKey);
                $fullPath = $targetDirectory . '/' . $relativePath;

                if (is_file($fullPath)) {
                    $hash = hash_file('sha256', $fullPath);
                    if ($hash !== false) {
                        $lines[] = $hash . '  ' . $relativePath;
                        $verified++;
                    }
                }
            }

            // Checksum du manifeste
            $manifestPath = $targetDirectory . '/' . self::MANIFEST_FILENAME;
            if (is_file($manifestPath)) {
                $hash = hash_file('sha256', $manifestPath);
                if ($hash !== false) {
                    $lines[] = $hash . '  ' . self::MANIFEST_FILENAME;
                }
            }

            // Sauvegarder les checksums
            $content = implode("\n", $lines) . "\n";
            if (@file_put_contents($checksumsPath, $content) !== false) {
                @chmod($checksumsPath, 0600);
                return ['generated' => true, 'verified' => $verified, 'path' => $checksumsPath];
            }
        } catch (\Throwable $e) {
            // Ignorer les erreurs de génération de checksums
        }

        return ['generated' => false, 'verified' => $verified, 'path' => null];
    }

    /**
     * @return array<string, mixed>
     */
    private function dryRunRestore(array $manifest, string $sourceDirectory): array
    {
        $tables = is_array($manifest['tables'] ?? null) ? $manifest['tables'] : [];
        $objects = is_array($manifest['objects']['items'] ?? null) ? $manifest['objects']['items'] : [];

        $tableCount = count($tables);
        $objectCount = count($objects);

        // Vérifier les fichiers
        $fileCount = 0;
        $missingFiles = 0;

        foreach ($objects as $object) {
            $storageKey = (string) ($object['storage_key'] ?? '');
            if ($storageKey !== '') {
                $relativePath = $this->storageKeyToRelativePath($storageKey);
                $fullPath = $sourceDirectory . '/' . $relativePath;
                if (is_file($fullPath)) {
                    $fileCount++;
                } else {
                    $missingFiles++;
                }
            }
        }

        return [
            'success' => true,
            'dryRun' => true,
            'tableCount' => $tableCount,
            'objectCount' => $objectCount,
            'fileCount' => $fileCount,
            'missingFiles' => $missingFiles,
            'restorable' => $missingFiles === 0,
        ];
    }

    /**
     * Convertit une clé de stockage en chemin relatif.
     */
    private function storageKeyToRelativePath(string $storageKey): string
    {
        // Supprimer le préfixe si présent
        $prefix = 'objects/';
        if (str_starts_with($storageKey, $prefix)) {
            return substr($storageKey, strlen($prefix));
        }

        $prefix = 'derivatives/';
        if (str_starts_with($storageKey, $prefix)) {
            return substr($storageKey, strlen($prefix));
        }

        return $storageKey;
    }

    /**
     * @return array{copied: int, skipped: int, errors: array<string>}
     */
    private function copyDirectory(string $source, string $target, array $options = []): array
    {
        $report = ['copied' => 0, 'skipped' => 0, 'errors' => []];

        if (!is_dir($source)) {
            $report['errors'][] = "Source directory does not exist: {$source}";
            return $report;
        }

        if (!is_dir($target) && !@mkdir($target, 0700, true) && !is_dir($target)) {
            $report['errors'][] = "Cannot create target directory: {$target}";
            return $report;
        }

        $this->enforceDirectoryPermissions($target);

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    $relPath = $iterator->getSubPathname();
                    $targetPath = $target . '/' . $relPath;
                    if (!is_dir($targetPath)) {
                        @mkdir($targetPath, 0700, true);
                        $this->enforceDirectoryPermissions($targetPath);
                    }
                    continue;
                }

                if (!$item->isFile()) {
                    continue;
                }

                $relPath = $iterator->getSubPathname();
                $sourcePath = $item->getPathname();
                $targetPath = $target . '/' . $relPath;

                // Appliquer le filtre si spécifié
                if (isset($options['include_pattern']) && !preg_match($options['include_pattern'], $relPath)) {
                    $report['skipped']++;
                    continue;
                }

                // Copier avec préservation des permissions
                if (@copy($sourcePath, $targetPath)) {
                    @touch($targetPath, $item->getMTime());
                    @chmod($targetPath, 0600);
                    $report['copied']++;
                } else {
                    $report['errors'][] = "Failed to copy: {$relPath}";
                }
            }
        } catch (\Throwable $e) {
            $report['errors'][] = $e->getMessage();
        }

        return $report;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchTableRows(string $table): array
    {
        try {
            $statement = $this->database->pdo()->query(sprintf('SELECT * FROM `%s`', $table));
            return $statement !== false ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function hashRows(array $rows): string
    {
        if ($rows === []) {
            return hash('sha256', '');
        }

        $data = [];
        foreach ($rows as $row) {
            $data[] = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return hash('sha256', implode('', $data));
    }

    private function enforceDirectoryPermissions(string $directory): void
    {
        @chmod($directory, 0700);
    }

    private function acquireLock(string $lockPath, int $timeout = 30): bool
    {
        $lockDir = dirname($lockPath);
        if (!is_dir($lockDir) && !@mkdir($lockDir, 0700, true) && !is_dir($lockDir)) {
            return false;
        }

        $lockFile = fopen($lockPath, 'c');
        if (!is_resource($lockFile)) {
            return false;
        }

        $start = time();
        while (!flock($lockFile, LOCK_EX | LOCK_NB)) {
            if (time() - $start >= $timeout) {
                fclose($lockFile);
                return false;
            }
            usleep(100000); // 100ms
        }

        return true;
    }

    private function releaseLock(string $lockPath): void
    {
        if (is_file($lockPath)) {
            $lockFile = fopen($lockPath, 'c');
            if (is_resource($lockFile)) {
                flock($lockFile, LOCK_UN);
                fclose($lockFile);
            }
            @unlink($lockPath);
        }
    }

    private function getAppVersion(): string
    {
        return (string) (app_config('app.version') ?? app_config('version') ?? 'unknown');
    }

    private function getSchemaVersion(): string
    {
        return '1.0.0';
    }
}
