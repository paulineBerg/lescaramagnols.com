<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Caramagnols\Blog\BlogSchedulePlanner;
use Caramagnols\Blog\BlogInternalLinksRebuilder;
use Caramagnols\Content\PageRepository;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit etre executee en CLI.\n");
    exit(1);
}

$options = parse_cli_options(array_slice($argv, 1));
$dryRun = isset($options['dry-run']);
$jsonOutput = isset($options['json']);
$now = null;

if (is_string($options['now'] ?? null)) {
    $rawNow = trim((string) $options['now']);
    $parsedNow = strtotime($rawNow);
    if (!is_int($parsedNow)) {
        fwrite(STDERR, sprintf("Date de reference invalide: %s\n", $rawNow));
        exit(1);
    }
    $now = $parsedNow;
}

$planner = new BlogSchedulePlanner(blog_repository(), app_event_logger());
$result = $planner->planNextDraft($now, $dryRun);
$linksRebuild = [
    'attempted' => 0,
    'changed' => 0,
    'updated' => 0,
    'skipped' => 0,
    'dry_run' => true,
    'errors' => [],
];

if (!$dryRun && $result['scheduled'] > 0) {
    $rebuilder = new BlogInternalLinksRebuilder(
        blog_repository(),
        new PageRepository(pages_data_path()),
        app_event_logger()
    );
    $linksRebuild = $rebuilder->rebuild($now);
}

if ($result['scheduled'] > 0 || !$linksRebuild['dry_run']) {
    app_runtime_cache_clear(['pages', 'navigation']);
}

if (!$dryRun && $result['scheduled'] > 0) {
    $message = sprintf(
        "Article planifie: %s (%s) sur page %s en %s\n",
        (string) ($result['selected']['slug'] ?? ''),
        (string) ($result['selected']['lang'] ?? ''),
        (string) ($result['selected']['page_slug'] ?? ''),
        (string) ($result['selected']['scheduled_at'] ?? '')
    );
    fwrite(STDOUT, $message);
}

if ($jsonOutput) {
    $jsonOptions = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    $payload = [
        'status' => $result['scheduled'] > 0 ? 'scheduled' : 'noop',
        'dry_run' => (bool) $result['dry_run'],
        'scheduled_count' => $result['scheduled'],
        'reason' => (string) $result['reason'],
        'selected' => $result['selected'],
        'links_rebuild' => [
            'attempted' => (int) $linksRebuild['attempted'],
            'changed' => (int) $linksRebuild['changed'],
            'updated' => (int) $linksRebuild['updated'],
            'skipped' => (int) $linksRebuild['skipped'],
            'dry_run' => (bool) $linksRebuild['dry_run'],
            'errors' => (array) $linksRebuild['errors'],
        ],
    ];
    fwrite(STDOUT, json_encode($payload, $jsonOptions) . PHP_EOL);
    exit(0);
}

if ($result['scheduled'] === 0) {
    $message = "Aucun brouillon à planifier.\n";
    if ($result['reason'] === 'no_draft_available') {
        fwrite(STDOUT, $message);
        exit(0);
    }

    fwrite(STDOUT, $message);
    exit(1);
}

exit(0);

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
