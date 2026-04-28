<?php

declare(strict_types=1);

namespace Caramagnols\Backup;

use Caramagnols\Logging\AppEventLogger;
use RuntimeException;

final class ProductionBackupService
{
    private const SITE_PREFIX = 'caramagnols-prod';

    /**
     * @param array{host?: mixed, port?: mixed, name?: mixed, user?: mixed, password?: mixed, charset?: mixed} $database
     */
    public function __construct(
        private readonly string $rootPath,
        private readonly string $backupRoot,
        private readonly array $database,
        private readonly string $databasePrefix,
        private readonly string $tarBinary = 'tar',
        private readonly string $mysqldumpBinary = 'mysqldump',
        private readonly int $retentionDays = 14,
        private readonly ?AppEventLogger $logger = null,
        private readonly ?string $filesDirectory = null,
        private readonly ?string $sqlDirectory = null,
        private readonly ?string $manifestDirectory = null
    ) {
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function fromRuntimeConfig(?AppEventLogger $logger = null, array $overrides = []): self
    {
        $rootPath = defined('ROOT_PATH') ? (string) ROOT_PATH : getcwd();
        $configuredBackup = function_exists('app_config') ? app_config('backup', []) : [];
        $backup = is_array($configuredBackup) ? $configuredBackup : [];
        $configuredDatabase = function_exists('app_config') ? app_config('database', []) : [];
        $database = is_array($configuredDatabase) ? $configuredDatabase : [];
        $databasePrefix = function_exists('app_config') ? (string) app_config('database_prefix', 'car_') : 'car_';

        $backupRoot = trim((string) ($overrides['root_dir'] ?? $backup['root_dir'] ?? dirname($rootPath) . '/backups'));
        if ($backupRoot === '') {
            $backupRoot = dirname($rootPath) . '/backups';
        }

        $retentionDays = (int) ($overrides['retention_days'] ?? $backup['retention_days'] ?? 14);
        $retentionDays = max(1, min(365, $retentionDays));

        return new self(
            $rootPath,
            $backupRoot,
            $database,
            $databasePrefix,
            trim((string) ($overrides['tar_binary'] ?? $backup['tar_binary'] ?? 'tar')) ?: 'tar',
            trim((string) ($overrides['mysqldump_binary'] ?? $backup['mysqldump_binary'] ?? 'mysqldump')) ?: 'mysqldump',
            $retentionDays,
            $logger,
            self::optionalPath($overrides['files_dir'] ?? $backup['files_dir'] ?? null),
            self::optionalPath($overrides['sql_dir'] ?? $backup['sql_dir'] ?? null),
            self::optionalPath($overrides['manifest_dir'] ?? $backup['manifest_dir'] ?? null)
        );
    }

    private static function optionalPath(mixed $value): ?string
    {
        $path = trim((string) $value);

        return $path !== '' ? $path : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        $backupRoot = $this->absolutePath($this->backupRoot);
        $rootPath = $this->absolutePath($this->rootPath);
        $databaseName = trim((string) ($this->database['name'] ?? ''));
        $databaseUser = trim((string) ($this->database['user'] ?? ''));
        $databasePassword = (string) ($this->database['password'] ?? '');

        return [
            'rootPath' => $rootPath,
            'backupRoot' => $backupRoot,
            'filesDirectory' => $this->outputDirectory('files', $backupRoot),
            'sqlDirectory' => $this->outputDirectory('sql', $backupRoot),
            'manifestDirectory' => $this->outputDirectory('manifests', $backupRoot),
            'retentionDays' => $this->retentionDays,
            'tarBinary' => $this->tarBinary,
            'mysqldumpBinary' => $this->mysqldumpBinary,
            'database' => [
                'host' => trim((string) ($this->database['host'] ?? '')),
                'port' => (int) ($this->database['port'] ?? 3306),
                'name' => $databaseName,
                'user' => $databaseUser,
                'prefix' => $this->databasePrefix,
                'passwordConfigured' => $databasePassword !== '',
                'configured' => $databaseName !== '' && $databaseUser !== '',
            ],
            'backupRootOutsideRoot' => $this->backupPathsAreSafe(),
        ];
    }

    /**
     * @param array{scope?: string, dry_run?: bool, retention_days?: int|null} $options
     * @return array<string, mixed>
     */
    public function run(array $options = []): array
    {
        $scope = $this->normalizeScope((string) ($options['scope'] ?? 'all'));
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $retentionDays = isset($options['retention_days']) && is_int($options['retention_days'])
            ? max(1, min(365, $options['retention_days']))
            : $this->retentionDays;

        $description = $this->describe();
        if ($dryRun) {
            $this->assertBackupRootIsSafe();
            if ($scope === 'all' || $scope === 'sql') {
                $this->assertDatabaseConfigured();
            }

            return [
                'success' => true,
                'dry_run' => true,
                'scope' => $scope,
                'generated_at' => date('c'),
                'configuration' => $description,
            ];
        }

        $this->assertBackupRootIsSafe();
        $directories = $this->ensureDirectories();
        $lockHandle = $this->acquireLock($directories['locks'] . '/production-backup.lock');

        try {
            $timestamp = date('Ymd-His');
            $result = [
                'success' => true,
                'dry_run' => false,
                'scope' => $scope,
                'generated_at' => date('c'),
                'configuration' => $description,
                'files' => null,
                'sql' => null,
                'manifest' => null,
                'retention' => [
                    'days' => $retentionDays,
                    'deleted' => [],
                ],
            ];

            if ($scope === 'all' || $scope === 'files') {
                $result['files'] = $this->backupFiles($timestamp, $directories['files']);
            }

            if ($scope === 'all' || $scope === 'sql') {
                $result['sql'] = $this->backupDatabase($timestamp, $directories['sql']);
            }

            $result['retention']['deleted'] = $this->cleanupRetention($directories, $retentionDays);
            $result['manifest'] = $this->writeManifest($timestamp, $directories['manifests'], $result);

            $this->logger?->security('ops.backup.completed', [
                'scope' => $scope,
                'files_path' => is_array($result['files']) ? basename((string) ($result['files']['path'] ?? '')) : null,
                'sql_path' => is_array($result['sql']) ? basename((string) ($result['sql']['path'] ?? '')) : null,
                'manifest_path' => is_array($result['manifest']) ? basename((string) ($result['manifest']['path'] ?? '')) : null,
                'retention_deleted' => count($result['retention']['deleted']),
            ]);

            return $result;
        } catch (\Throwable $exception) {
            $this->logger?->security('ops.backup.failed', [
                'scope' => $scope,
                'error' => $exception->getMessage(),
            ], 'error');

            throw $exception;
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private function normalizeScope(string $scope): string
    {
        $normalized = strtolower(trim($scope));
        if (!in_array($normalized, ['all', 'files', 'sql'], true)) {
            throw new RuntimeException('Scope de backup invalide. Valeurs attendues: all, files, sql.');
        }

        return $normalized;
    }

    private function assertBackupRootIsSafe(): void
    {
        $rootPath = $this->absolutePath($this->rootPath);
        $backupRoot = $this->absolutePath($this->backupRoot);

        if ($backupRoot === $rootPath || $this->isPathInside($backupRoot, $rootPath)) {
            throw new RuntimeException(sprintf(
                'Refus: le dossier de backup ne doit pas être dans le backend ni dans le webroot (%s).',
                $backupRoot
            ));
        }

        foreach ([$this->filesDirectory, $this->sqlDirectory, $this->manifestDirectory] as $directory) {
            if ($directory === null || trim($directory) === '') {
                continue;
            }

            $outputDirectory = $this->absolutePath($directory);
            if ($outputDirectory === $rootPath || $this->isPathInside($outputDirectory, $rootPath)) {
                throw new RuntimeException(sprintf(
                    'Refus: les dossiers de sortie du backup ne doivent pas être dans le backend ni dans le webroot (%s).',
                    $outputDirectory
                ));
            }
        }
    }

    private function assertDatabaseConfigured(): void
    {
        $databaseName = trim((string) ($this->database['name'] ?? ''));
        $databaseUser = trim((string) ($this->database['user'] ?? ''));
        if ($databaseName === '' || $databaseUser === '') {
            throw new RuntimeException('La configuration SQL est incomplète: DB_NAME et DB_USER sont requis.');
        }
    }

    /**
     * @return array{root: string, files: string, sql: string, manifests: string, locks: string, tmp: string}
     */
    private function ensureDirectories(): array
    {
        $root = $this->absolutePath($this->backupRoot);
        $directories = [
            'root' => $root,
            'files' => $this->outputDirectory('files', $root),
            'sql' => $this->outputDirectory('sql', $root),
            'manifests' => $this->outputDirectory('manifests', $root),
            'locks' => $root . '/locks',
            'tmp' => $root . '/tmp',
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Impossible de créer le dossier de backup: %s', $directory));
            }

            @chmod($directory, 0700);
        }

        return $directories;
    }

    /**
     * @return resource
     */
    private function acquireLock(string $lockPath)
    {
        $handle = fopen($lockPath, 'c');
        if (!is_resource($handle)) {
            throw new RuntimeException(sprintf('Impossible d’ouvrir le verrou de backup: %s', $lockPath));
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new RuntimeException('Un backup production est déjà en cours.');
        }

        return $handle;
    }

    /**
     * @return array{path: string, size: int, sha256: string}
     */
    private function backupFiles(string $timestamp, string $targetDirectory): array
    {
        $rootPath = $this->absolutePath($this->rootPath);
        $parent = dirname($rootPath);
        $basename = basename($rootPath);
        $targetPath = sprintf('%s/%s-files-%s.tar.gz', $targetDirectory, self::SITE_PREFIX, $timestamp);
        $excludes = [
            $basename . '/var/cache',
            $basename . '/var/log',
            $basename . '/var/phpstan',
            $basename . '/var/rate-limits',
            $basename . '/data/logs',
            $basename . '/data/snapshots',
            $basename . '/data/backups',
            $basename . '/*.tmp',
            $basename . '/*.bak',
            $basename . '/*.old',
            $basename . '/*.orig',
        ];

        $command = [
            $this->tarBinary,
            '--create',
            '--gzip',
            '--file',
            $targetPath,
            '--directory',
            $parent,
        ];

        foreach ($excludes as $exclude) {
            $command[] = '--exclude=' . $exclude;
        }

        $command[] = $basename;

        try {
            $this->runBufferedCommand($command, $this->absolutePath($this->backupRoot) . '/tmp');
            @chmod($targetPath, 0600);

            return $this->fileReport($targetPath);
        } catch (\Throwable $exception) {
            @unlink($targetPath);
            throw $exception;
        }
    }

    /**
     * @return array{path: string, size: int, sha256: string}
     */
    private function backupDatabase(string $timestamp, string $targetDirectory): array
    {
        $this->assertDatabaseConfigured();
        $databaseName = trim((string) ($this->database['name'] ?? ''));

        $backupRoot = $this->absolutePath($this->backupRoot);
        $tmpDirectory = $backupRoot . '/tmp';
        $defaultsPath = $tmpDirectory . '/mysql-client-' . $timestamp . '-' . bin2hex(random_bytes(6)) . '.cnf';
        $stderrPath = $tmpDirectory . '/mysqldump-' . $timestamp . '-' . bin2hex(random_bytes(6)) . '.stderr';
        $targetPath = sprintf('%s/%s-db-%s.sql.gz', $targetDirectory, self::SITE_PREFIX, $timestamp);

        $defaults = $this->mysqlDefaultsFileContent();
        if (file_put_contents($defaultsPath, $defaults, LOCK_EX) === false) {
            throw new RuntimeException('Impossible de créer le fichier temporaire de connexion MySQL.');
        }
        @chmod($defaultsPath, 0600);

        $command = [
            $this->mysqldumpBinary,
            '--defaults-extra-file=' . $defaultsPath,
            '--single-transaction',
            '--quick',
            '--triggers',
            '--hex-blob',
            '--databases',
            $databaseName,
        ];

        try {
            $this->runStreamingGzipCommand($command, $targetPath, $stderrPath);
            @chmod($targetPath, 0600);

            return $this->fileReport($targetPath);
        } catch (\Throwable $exception) {
            @unlink($targetPath);
            throw $exception;
        } finally {
            @unlink($defaultsPath);
            @unlink($stderrPath);
        }
    }

    private function mysqlDefaultsFileContent(): string
    {
        $host = trim((string) ($this->database['host'] ?? '127.0.0.1'));
        $port = (int) ($this->database['port'] ?? 3306);
        $user = trim((string) ($this->database['user'] ?? ''));
        $password = (string) ($this->database['password'] ?? '');
        $charset = trim((string) ($this->database['charset'] ?? 'utf8mb4')) ?: 'utf8mb4';

        return sprintf(
            "[client]\nhost=%s\nport=%d\nuser=%s\npassword=%s\ndefault-character-set=%s\n",
            $this->mysqlOptionValue($host),
            $port,
            $this->mysqlOptionValue($user),
            $this->mysqlOptionValue($password),
            $this->mysqlOptionValue($charset)
        );
    }

    private function mysqlOptionValue(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    /**
     * @param array<int, string> $command
     */
    private function runBufferedCommand(array $command, string $tmpDirectory): void
    {
        $stdoutPath = $tmpDirectory . '/command-' . bin2hex(random_bytes(8)) . '.stdout';
        $stderrPath = $tmpDirectory . '/command-' . bin2hex(random_bytes(8)) . '.stderr';
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', $stdoutPath, 'w'],
            2 => ['file', $stderrPath, 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $this->rootPath);
        if (!is_resource($process)) {
            throw new RuntimeException(sprintf('Impossible de lancer la commande: %s', $command[0] ?? 'commande'));
        }

        $exitCode = proc_close($process);
        $stderr = $this->readCommandError($stderrPath);
        @unlink($stdoutPath);
        @unlink($stderrPath);

        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                'Commande de backup fichiers en échec (code %d): %s',
                $exitCode,
                $stderr !== '' ? $stderr : 'aucun détail'
            ));
        }
    }

    /**
     * @param array<int, string> $command
     */
    private function runStreamingGzipCommand(array $command, string $targetPath, string $stderrPath): void
    {
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', $stderrPath, 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $this->rootPath);
        if (!is_resource($process)) {
            throw new RuntimeException('Impossible de lancer mysqldump.');
        }

        $gzip = gzopen($targetPath, 'wb9');
        if (!is_resource($gzip)) {
            proc_terminate($process);
            proc_close($process);
            throw new RuntimeException(sprintf('Impossible d’écrire le dump compressé: %s', $targetPath));
        }

        try {
            while (!feof($pipes[1])) {
                $chunk = fread($pipes[1], 1048576);
                if ($chunk === false) {
                    break;
                }

                if ($chunk !== '') {
                    gzwrite($gzip, $chunk);
                }
            }
        } finally {
            fclose($pipes[1]);
            gzclose($gzip);
        }

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                'Dump SQL en échec (code %d): %s',
                $exitCode,
                $this->readCommandError($stderrPath) ?: 'aucun détail'
            ));
        }
    }

    private function readCommandError(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }

        $content = file_get_contents($path);
        if (!is_string($content)) {
            return '';
        }

        $content = trim($content);

        return function_exists('mb_substr') ? mb_substr($content, 0, 2000) : substr($content, 0, 2000);
    }

    /**
     * @return array{path: string, size: int, sha256: string}
     */
    private function fileReport(string $path): array
    {
        $size = filesize($path);
        $hash = hash_file('sha256', $path);
        if ($size === false || !is_string($hash)) {
            throw new RuntimeException(sprintf('Impossible de vérifier le fichier de backup: %s', $path));
        }

        return [
            'path' => $path,
            'size' => $size,
            'sha256' => $hash,
        ];
    }

    /**
     * @param array{files: string, sql: string, manifests: string} $directories
     * @return array<int, string>
     */
    private function cleanupRetention(array $directories, int $retentionDays): array
    {
        $deleted = [];
        $cutoff = time() - ($retentionDays * 86400);

        foreach (['files', 'sql', 'manifests'] as $key) {
            $directory = $directories[$key] ?? '';
            if (!is_string($directory) || !is_dir($directory)) {
                continue;
            }

            $files = glob($directory . '/' . self::SITE_PREFIX . '-*');
            if (!is_array($files)) {
                continue;
            }

            foreach ($files as $file) {
                if (!is_file($file)) {
                    continue;
                }

                $mtime = filemtime($file);
                if ($mtime === false || $mtime >= $cutoff) {
                    continue;
                }

                if (@unlink($file)) {
                    $deleted[] = basename($file);
                }
            }
        }

        return $deleted;
    }

    /**
     * @param array<string, mixed> $result
     * @return array{path: string, size: int, sha256: string}
     */
    private function writeManifest(string $timestamp, string $targetDirectory, array $result): array
    {
        $targetPath = sprintf('%s/%s-manifest-%s.json', $targetDirectory, self::SITE_PREFIX, $timestamp);
        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('Impossible d’encoder le manifeste de backup.');
        }

        if (file_put_contents($targetPath, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException(sprintf('Impossible d’écrire le manifeste de backup: %s', $targetPath));
        }

        @chmod($targetPath, 0600);

        return $this->fileReport($targetPath);
    }

    private function absolutePath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            $trimmed = dirname($this->rootPath) . '/backups';
        }

        if (str_starts_with($trimmed, '/')) {
            return rtrim($this->normalizeSlashes($trimmed), '/');
        }

        return rtrim($this->normalizeSlashes($this->rootPath . '/' . $trimmed), '/');
    }

    private function outputDirectory(string $key, string $backupRoot): string
    {
        $configured = match ($key) {
            'files' => $this->filesDirectory,
            'sql' => $this->sqlDirectory,
            'manifests' => $this->manifestDirectory,
            default => null,
        };

        if ($configured !== null && trim($configured) !== '') {
            return $this->absolutePath($configured);
        }

        return $backupRoot . '/' . $key;
    }

    private function backupPathsAreSafe(): bool
    {
        $rootPath = $this->absolutePath($this->rootPath);
        $backupRoot = $this->absolutePath($this->backupRoot);
        if ($backupRoot === $rootPath || $this->isPathInside($backupRoot, $rootPath)) {
            return false;
        }

        foreach ([$this->filesDirectory, $this->sqlDirectory, $this->manifestDirectory] as $directory) {
            if ($directory === null || trim($directory) === '') {
                continue;
            }

            $outputDirectory = $this->absolutePath($directory);
            if ($outputDirectory === $rootPath || $this->isPathInside($outputDirectory, $rootPath)) {
                return false;
            }
        }

        return true;
    }

    private function normalizeSlashes(string $path): string
    {
        $normalized = preg_replace('#/+#', '/', $path);

        return is_string($normalized) ? $normalized : $path;
    }

    private function isPathInside(string $path, string $parent): bool
    {
        $normalizedPath = rtrim($this->normalizeSlashes($path), '/');
        $normalizedParent = rtrim($this->normalizeSlashes($parent), '/');

        return str_starts_with($normalizedPath . '/', $normalizedParent . '/');
    }
}
