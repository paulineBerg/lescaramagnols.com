<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit etre executee en CLI.\n");
    exit(1);
}

$options = parse_admin_runtime_snapshot_options(array_slice($argv, 1));

try {
    $snapshot = build_admin_runtime_settings_snapshot();
    $json = encode_admin_runtime_settings_snapshot($snapshot);

    if (is_string($options['output'] ?? null) && trim($options['output']) !== '') {
        write_admin_runtime_settings_snapshot((string) $options['output'], $json);
        fwrite(STDOUT, sprintf("Snapshot reglages admin cree : %s\n", (string) $options['output']));
        exit(0);
    }

    fwrite(STDOUT, $json . PHP_EOL);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * @param array<int, string> $arguments
 * @return array{output?: string}
 */
function parse_admin_runtime_snapshot_options(array $arguments): array
{
    $options = [];

    foreach ($arguments as $argument) {
        if (!is_string($argument) || !str_starts_with($argument, '--')) {
            continue;
        }

        if ($argument === '--help' || $argument === '-h') {
            fwrite(STDOUT, "Usage: php core/tools/export_admin_runtime_settings.php [--output=var/backups/admin-runtime.json]\n");
            exit(0);
        }

        if (str_starts_with($argument, '--output=')) {
            $options['output'] = trim((string) substr($argument, 9));
        }
    }

    return $options;
}

/**
 * @return array<string, mixed>
 */
function build_admin_runtime_settings_snapshot(): array
{
    return [
        'schema_version' => 1,
        'backup' => [
            'root_dir' => (string) app_config('backup.root_dir', ''),
            'retention_days' => max(1, min(365, (int) app_config('backup.retention_days', 14))),
            'files_dir' => (string) app_config('backup.files_dir', ''),
            'sql_dir' => (string) app_config('backup.sql_dir', ''),
            'manifest_dir' => (string) app_config('backup.manifest_dir', ''),
            'php_binary' => (string) app_config('backup.php_binary', 'php'),
            'tar_binary' => (string) app_config('backup.tar_binary', 'tar'),
            'mysqldump_binary' => (string) app_config('backup.mysqldump_binary', 'mysqldump'),
            'database' => [
                'host' => (string) app_config('database.host', ''),
                'port' => (int) app_config('database.port', 3306),
                'name' => (string) app_config('database.name', ''),
                'user' => (string) app_config('database.user', ''),
                'charset' => (string) app_config('database.charset', 'utf8mb4'),
                'prefix' => (string) app_config('database_prefix', ''),
                'password_sha256' => hash_config_secret((string) app_config('database.password', '')),
            ],
        ],
        'cron' => [
            'jobs' => export_admin_runtime_cron_jobs(),
        ],
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function export_admin_runtime_cron_jobs(): array
{
    $database = editorial_database();
    $database->ensureReady();
    $pdo = $database->pdo();

    $statement = $pdo->query(sprintf(
        'SELECT `code`, `name`, `description`, `script_path`, `arguments_json`,
                `schedule_expression`, `status`, `timeout_seconds`
         FROM %s
         ORDER BY `code` ASC',
        quote_admin_runtime_identifier($database->table('cron_jobs'))
    ));

    if ($statement === false) {
        return [];
    }

    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($rows)) {
        return [];
    }

    return array_map(static function (array $row): array {
        return [
            'code' => (string) ($row['code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'script_path' => (string) ($row['script_path'] ?? ''),
            'arguments' => normalize_admin_runtime_json($row['arguments_json'] ?? null),
            'schedule_expression' => (string) ($row['schedule_expression'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'timeout_seconds' => (int) ($row['timeout_seconds'] ?? 0),
        ];
    }, $rows);
}

function hash_config_secret(string $secret): string
{
    return $secret !== '' ? hash('sha256', $secret) : '';
}

function quote_admin_runtime_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

/**
 * @return mixed
 */
function normalize_admin_runtime_json(mixed $json): mixed
{
    if (!is_string($json) || trim($json) === '') {
        return null;
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return trim($json);
    }

    return sort_admin_runtime_json_value($decoded);
}

/**
 * @return mixed
 */
function sort_admin_runtime_json_value(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    if (!array_is_list($value)) {
        ksort($value);
    }

    foreach ($value as $key => $child) {
        $value[$key] = sort_admin_runtime_json_value($child);
    }

    return $value;
}

/**
 * @param array<string, mixed> $snapshot
 */
function encode_admin_runtime_settings_snapshot(array $snapshot): string
{
    $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        throw new RuntimeException('Impossible d encoder le snapshot des reglages admin.');
    }

    return $json;
}

function write_admin_runtime_settings_snapshot(string $outputPath, string $json): void
{
    $outputPath = trim($outputPath);
    if ($outputPath === '') {
        throw new RuntimeException('Chemin de snapshot manquant.');
    }

    $directory = dirname($outputPath);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException(sprintf('Impossible de creer le dossier de snapshot: %s', $directory));
    }

    if (file_put_contents($outputPath, $json) === false) {
        throw new RuntimeException(sprintf('Impossible d ecrire le snapshot: %s', $outputPath));
    }

    chmod($outputPath, 0600);
}
