<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit être exécutée en CLI.\n");
    exit(1);
}

$options = parse_cli_options(array_slice($argv, 1));
$jsonOutput = isset($options['json']);
$quiet = isset($options['quiet']);
$dryRun = isset($options['dry-run']);
$retentionDays = parse_days_option(
    $options['days'] ?? env('LOG_SQL_RETENTION_DAYS', 90),
    'La rétention standard doit être un entier entre 1 et 3650 jours.',
    $jsonOutput
);
$sensitiveRetentionDays = parse_days_option(
    $options['keep-sensitive-days'] ?? env('LOG_SQL_SENSITIVE_RETENTION_DAYS', 365),
    'La rétention sensible doit être un entier entre 1 et 3650 jours.',
    $jsonOutput
);

if ($retentionDays === null || $sensitiveRetentionDays === null) {
    exit(2);
}

try {
    $store = app_sql_log_store();
    if (!$store->isAvailable()) {
        throw new RuntimeException('Stockage SQL des logs indisponible.');
    }

    $result = $store->purgeOlderThan($retentionDays, $sensitiveRetentionDays, $dryRun, new DateTimeImmutable());
    $payload = array_merge([
        'success' => true,
        'generated_at' => date('c'),
    ], $result);

    app_event_logger()->security($dryRun ? 'logs.sql_purge_dry_run' : 'logs.sql_purged', [
        'retention_days' => $result['retentionDays'],
        'sensitive_retention_days' => $result['sensitiveRetentionDays'],
        'regular_deleted' => $result['regularDeleted'],
        'sensitive_deleted' => $result['sensitiveDeleted'],
        'regular_matched' => $result['regularMatched'],
        'sensitive_matched' => $result['sensitiveMatched'],
        'dry_run' => $dryRun,
    ]);

    if ($jsonOutput) {
        fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        exit(0);
    }

    if (!$quiet) {
        render_purge_result($payload);
    }

    exit(0);
} catch (Throwable $exception) {
    write_purge_error($exception->getMessage(), $jsonOutput);
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

function parse_days_option(mixed $value, string $errorMessage, bool $jsonOutput): ?int
{
    $days = filter_var((string) $value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 3650],
    ]);
    if ($days === false) {
        write_purge_error($errorMessage, $jsonOutput);

        return null;
    }

    return (int) $days;
}

/**
 * @param array<string, mixed> $result
 */
function render_purge_result(array $result): void
{
    fwrite(STDOUT, "Purge logs SQL\n");
    if (($result['dryRun'] ?? false) === true) {
        fwrite(STDOUT, "Mode dry-run: aucune entrée n’a été supprimée.\n");
    }

    fwrite(STDOUT, sprintf(
        "- rétention standard: %d jour(s), avant %s\n",
        (int) ($result['retentionDays'] ?? 0),
        (string) ($result['cutoff'] ?? '')
    ));
    fwrite(STDOUT, sprintf(
        "- rétention sensible: %d jour(s), avant %s\n",
        (int) ($result['sensitiveRetentionDays'] ?? 0),
        (string) ($result['sensitiveCutoff'] ?? '')
    ));
    fwrite(STDOUT, sprintf(
        "- entrées standard candidates: %d, supprimées: %d\n",
        (int) ($result['regularMatched'] ?? 0),
        (int) ($result['regularDeleted'] ?? 0)
    ));
    fwrite(STDOUT, sprintf(
        "- entrées sensibles candidates: %d, supprimées: %d\n",
        (int) ($result['sensitiveMatched'] ?? 0),
        (int) ($result['sensitiveDeleted'] ?? 0)
    ));
    fwrite(STDOUT, sprintf("- total supprimé: %d\n", (int) ($result['deleted'] ?? 0)));
}

function write_purge_error(string $message, bool $jsonOutput): void
{
    if ($jsonOutput) {
        fwrite(STDOUT, json_encode([
            'success' => false,
            'error' => $message,
            'generated_at' => date('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        return;
    }

    fwrite(STDERR, '[ERROR] ' . $message . PHP_EOL);
}
