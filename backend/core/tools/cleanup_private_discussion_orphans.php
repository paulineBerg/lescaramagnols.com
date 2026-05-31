<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Caramagnols\PrivateApps\FamilyDiscussion\Attachment\DiscussionAttachmentStorage;
use Caramagnols\PrivateApps\FamilyDiscussion\Repository\DiscussionRepository;
use Caramagnols\PrivateApps\FamilyDiscussion\Service\DiscussionMediaService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$options = parse_private_discussion_orphan_options(array_slice($argv ?? [], 1));
if (isset($options['help'])) {
    echo "Usage: php backend/core/tools/cleanup_private_discussion_orphans.php [--dry-run] [--json] [--quiet] [--limit=5000]\n";
    echo "Liste ou supprime les fichiers FamilyDiscussion hors base.\n";
    exit(0);
}

$limit = 5000;
$dryRun = isset($options['dry-run']);
$jsonOutput = isset($options['json']);
$quiet = isset($options['quiet']);
$startedAt = microtime(true);
$startedAtIso = date('c');

if (isset($options['limit']) && is_string($options['limit']) && ctype_digit($options['limit'])) {
    $limit = max(1, min(50000, (int) $options['limit']));
}

try {
    $service = new DiscussionMediaService(
        new DiscussionRepository(editorial_database()),
        DiscussionAttachmentStorage::fromAppConfig(),
        function_exists('app_event_logger') ? app_event_logger() : null
    );
    $result = $service->cleanupOrphans($limit, $dryRun);
    $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
    $payload = [
        'success' => true,
        'ok' => true,
        'started_at' => $startedAtIso,
        'finished_at' => date('c'),
        'duration_ms' => $durationMs,
        'dry_run' => $dryRun,
        'limit' => $limit,
        'scanned' => $result['scanned'],
        'orphans' => $result['orphans'],
        'deleted' => $result['deleted'],
        'items' => $result['items'],
        'errors' => 0,
    ];

    if ($jsonOutput) {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } elseif (!$quiet) {
        echo sprintf(
            "FamilyDiscussion orphelins%s: %d fichier(s), %d supprime(s), %d ms.\n",
            $dryRun ? ' dry-run' : '',
            $payload['orphans'],
            $payload['deleted'],
            $durationMs
        );
    }

    exit(0);
} catch (Throwable $exception) {
    $payload = [
        'success' => false,
        'ok' => false,
        'started_at' => $startedAtIso,
        'finished_at' => date('c'),
        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        'dry_run' => $dryRun,
        'limit' => $limit,
        'errors' => 1,
        'error' => $exception->getMessage(),
    ];

    if ($jsonOutput) {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } elseif (!$quiet) {
        fwrite(STDERR, 'Erreur cleanup FamilyDiscussion: ' . $exception->getMessage() . PHP_EOL);
    }

    exit(1);
}

/**
 * @param array<int, string> $arguments
 * @return array<string, string|true>
 */
function parse_private_discussion_orphan_options(array $arguments): array
{
    $options = [];

    foreach ($arguments as $argument) {
        if (!is_string($argument) || !str_starts_with($argument, '--')) {
            continue;
        }

        $parts = explode('=', substr($argument, 2), 2);
        $options[$parts[0]] = $parts[1] ?? true;
    }

    return $options;
}
