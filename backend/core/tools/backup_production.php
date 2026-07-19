<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Caramagnols\Backup\ProductionBackupService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit être exécutée en CLI.\n");
    exit(1);
}

$options = parse_cli_options(array_slice($argv, 1));
$jsonOutput = isset($options['json']);
$quiet = isset($options['quiet']);
$dryRun = isset($options['dry-run']);
$scope = strtolower(trim((string) ($options['scope'] ?? 'all')));
$overrides = [];

if (isset($options['backup-root']) && is_string($options['backup-root'])) {
    $overrides['root_dir'] = $options['backup-root'];
}

if (isset($options['retention-days'])) {
    $retentionDays = filter_var((string) $options['retention-days'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 365],
    ]);
    if ($retentionDays === false) {
        write_backup_error('La rétention doit être un entier entre 1 et 365 jours.', $jsonOutput);
        exit(2);
    }

    $overrides['retention_days'] = (int) $retentionDays;
}

try {
    $service = ProductionBackupService::fromRuntimeConfig(app_event_logger(), $overrides);
    $result = $service->run([
        'scope' => $scope,
        'dry_run' => $dryRun,
        'retention_days' => is_int($overrides['retention_days'] ?? null) ? $overrides['retention_days'] : null,
    ]);

    if ($jsonOutput) {
        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        exit(0);
    }

    if ($quiet) {
        exit(0);
    }

    render_backup_result($result);
    exit(0);
} catch (Throwable $exception) {
    write_backup_error($exception->getMessage(), $jsonOutput);
    exit(1);
}

/**
 * @param array<int, string> $arguments
 * @return array<string, string|true>
 */
function parse_cli_options(array $arguments): array
{
    $options = [];

    foreach ($arguments as $argument) {
        if (!is_string($argument) || !str_starts_with($argument, '--')) {
            continue;
        }

        $parts = explode('=', substr($argument, 2), 2);
        if (!isset($parts[1])) {
            $options[$parts[0]] = true;
            continue;
        }

        $options[$parts[0]] = $parts[1];
    }

    return $options;
}

/**
 * @param array<string, mixed> $result
 */
function render_backup_result(array $result): void
{
    fwrite(STDOUT, sprintf("Backup production (%s)\n", (string) ($result['scope'] ?? 'all')));

    if (($result['dry_run'] ?? false) === true) {
        $configuration = is_array($result['configuration'] ?? null) ? $result['configuration'] : [];
        fwrite(STDOUT, "Mode dry-run: aucune archive n’a été créée.\n");
        fwrite(STDOUT, sprintf("- backend: %s\n", (string) ($configuration['rootPath'] ?? '')));
        fwrite(STDOUT, sprintf("- backups: %s\n", (string) ($configuration['backupRoot'] ?? '')));
        fwrite(STDOUT, sprintf("- rétention: %d jour(s)\n", (int) ($configuration['retentionDays'] ?? 0)));
        fwrite(STDOUT, sprintf("- tar: %s\n", (string) ($configuration['tarBinary'] ?? 'tar')));
        fwrite(STDOUT, sprintf("- mysqldump: %s\n", (string) ($configuration['mysqldumpBinary'] ?? 'mysqldump')));

        return;
    }

    if (is_array($result['files'] ?? null)) {
        render_backup_file_line('Archive fichiers', $result['files']);
    }

    if (is_array($result['sql'] ?? null)) {
        render_backup_file_line('Dump SQL', $result['sql']);
    }

    if (is_array($result['manifest'] ?? null)) {
        render_backup_file_line('Manifeste', $result['manifest']);
    }

    $retention = is_array($result['retention'] ?? null) ? $result['retention'] : [];
    $deleted = is_array($retention['deleted'] ?? null) ? $retention['deleted'] : [];
    fwrite(STDOUT, sprintf("Rétention: %d fichier(s) supprimé(s).\n", count($deleted)));
}

/**
 * @param array<string, mixed> $file
 */
function render_backup_file_line(string $label, array $file): void
{
    fwrite(
        STDOUT,
        sprintf(
            "%s: %s (%d octets, sha256=%s)\n",
            $label,
            (string) ($file['path'] ?? ''),
            (int) ($file['size'] ?? 0),
            (string) ($file['sha256'] ?? '')
        )
    );
}

function write_backup_error(string $message, bool $jsonOutput): void
{
    if ($jsonOutput) {
        fwrite(STDOUT, json_encode([
            'success' => false,
            'error' => $message,
            'generated_at' => date('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        return;
    }

    fwrite(STDERR, "[ERROR] " . $message . PHP_EOL);
}
