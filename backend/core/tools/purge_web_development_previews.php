<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Caramagnols\PrivateApps\WebDevelopment\Retention\PreviewPurgeService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$options = parse_web_development_preview_purge_options(array_slice($argv ?? [], 1));
if (isset($options['help'])) {
    echo "Usage: php backend/core/tools/purge_web_development_previews.php [--dry-run] [--json] [--quiet] [--batch=500] [--iterations=20]\n";
    echo "Purge les tickets/sessions preview WebDevelopment expirés, consommés ou révoqués.\n";
    exit(0);
}

$batchSize = isset($options['batch']) && is_string($options['batch']) && ctype_digit($options['batch'])
    ? max(50, min(5000, (int) $options['batch']))
    : 500;

$iterations = isset($options['iterations']) && is_string($options['iterations']) && ctype_digit($options['iterations'])
    ? max(1, min(200, (int) $options['iterations']))
    : 20;

$dryRun = isset($options['dry-run']);
$jsonOutput = isset($options['json']);
$quiet = isset($options['quiet']);

$startedAt = date('c');
$startedAtMs = microtime(true);

try {
    app_event_logger()->security('private.web_development.preview_purge.started', [
        'dry_run' => $dryRun,
        'batch_size' => $batchSize,
        'iterations' => $iterations,
    ]);

    $service = new PreviewPurgeService(editorial_database());
    $result = $service->purgeExpired($batchSize, $iterations, $dryRun);

    $durationMs = (int) round((microtime(true) - $startedAtMs) * 1000);
    $payload = [
        'success' => true,
        'started_at' => $startedAt,
        'finished_at' => date('c'),
        'duration_ms' => $durationMs,
        'dry_run' => $dryRun,
        'batch_size' => $batchSize,
        'iterations' => $result['iterations'],
        'tickets_purged' => (int) $result['tickets_purged'],
        'sessions_purged' => (int) $result['sessions_purged'],
    ];

    app_event_logger()->security('private.web_development.preview_purge.completed', $payload);

    if ($jsonOutput) {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit(0);
    }

    if (!$quiet) {
        echo sprintf(
            "Purge preview WebDevelopment%s: %d ticket(s), %d session(s), %d itération(s), %d ms.\n",
            $dryRun ? ' dry-run' : '',
            $payload['tickets_purged'],
            $payload['sessions_purged'],
            $payload['iterations'],
            $durationMs
        );
    }

    exit(0);
} catch (Throwable $exception) {
    $payload = [
        'success' => false,
        'started_at' => $startedAt,
        'finished_at' => date('c'),
        'duration_ms' => (int) round((microtime(true) - $startedAtMs) * 1000),
        'error' => $exception->getMessage(),
        'dry_run' => $dryRun,
        'batch_size' => $batchSize,
        'iterations' => $iterations,
    ];

    app_event_logger()->security('private.web_development.preview_purge.failed', [
        'error' => $exception->getMessage(),
        'dry_run' => $dryRun,
        'batch_size' => $batchSize,
        'iterations' => $iterations,
    ], 'error');

    if ($jsonOutput) {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } elseif (!$quiet) {
        fwrite(STDERR, '[ERROR] ' . $exception->getMessage() . PHP_EOL);
    }

    exit(1);
}

/**
 * @param array<int,string> $arguments
 * @return array<string, string|true>
 */
function parse_web_development_preview_purge_options(array $arguments): array
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
