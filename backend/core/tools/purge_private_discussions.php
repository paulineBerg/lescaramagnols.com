<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Caramagnols\PrivateApps\FamilyDiscussion\Attachment\DiscussionAttachmentStorage;
use Caramagnols\PrivateApps\FamilyDiscussion\Repository\DiscussionRepository;
use Caramagnols\PrivateApps\FamilyDiscussion\Retention\DiscussionRetentionService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$options = parse_private_discussion_purge_options(array_slice($argv ?? [], 1));
if (isset($options['help'])) {
    echo "Usage: php backend/core/tools/purge_private_discussions.php [--dry-run] [--json] [--quiet] [--limit=1000]\n";
    echo "Purge les messages et fichiers FamilyDiscussion expires par retention.\n";
    exit(0);
}

$limit = 1000;
$dryRun = isset($options['dry-run']);
$jsonOutput = isset($options['json']);
$quiet = isset($options['quiet']);
$startedAt = microtime(true);
$startedAtIso = date('c');

if (isset($options['limit']) && is_string($options['limit']) && ctype_digit($options['limit'])) {
    $limit = max(1, min(10000, (int) $options['limit']));
}

try {
    app_event_logger()->security('private.discussion.retention.started', [
        'scope' => 'scheduled',
        'dry_run' => $dryRun,
        'limit' => $limit,
    ]);

    $service = new DiscussionRetentionService(
        new DiscussionRepository(editorial_database()),
        DiscussionAttachmentStorage::fromAppConfig(),
        function_exists('app_event_logger') ? app_event_logger() : null
    );

    $result = $service->purgeExpiredScheduled($limit, $dryRun);
    $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
    $payload = [
        'success' => true,
        'ok' => true,
        'started_at' => $startedAtIso,
        'finished_at' => date('c'),
        'duration_ms' => $durationMs,
        'dry_run' => $dryRun,
        'limit' => $limit,
        'messages' => (int) ($result['messages'] ?? 0),
        'attachments' => (int) ($result['attachments'] ?? 0),
        'errors' => 0,
    ];

    app_event_logger()->security('private.discussion.retention.completed', [
        'scope' => 'scheduled',
        'dry_run' => $dryRun,
        'limit' => $limit,
        'duration_ms' => $durationMs,
        'messages' => $payload['messages'],
        'attachments' => $payload['attachments'],
    ]);

    if ($jsonOutput) {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } elseif (!$quiet) {
        echo sprintf(
            "FamilyDiscussion purge%s: %d message(s), %d piece(s) jointe(s), %d ms.\n",
            $dryRun ? ' dry-run' : '',
            $payload['messages'],
            $payload['attachments'],
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

    app_event_logger()->security('private.discussion.retention.failed', [
        'scope' => 'scheduled',
        'dry_run' => $dryRun,
        'limit' => $limit,
        'duration_ms' => $payload['duration_ms'],
        'error' => $exception->getMessage(),
    ], 'error');

    if ($jsonOutput) {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } elseif (!$quiet) {
        fwrite(STDERR, 'Erreur purge FamilyDiscussion: ' . $exception->getMessage() . PHP_EOL);
    }

    exit(1);
}

/**
 * @param array<int, string> $arguments
 * @return array<string, string|true>
 */
function parse_private_discussion_purge_options(array $arguments): array
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
