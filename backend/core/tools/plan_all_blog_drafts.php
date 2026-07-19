<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Caramagnols\Blog\BlogInternalLinksRebuilder;
use Caramagnols\Blog\BlogSchedulePlanner;
use Caramagnols\Content\PageRepository;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit etre executee en CLI.\n");
    exit(1);
}

$options = parse_cli_options(array_slice($argv, 1));
$jsonOutput = isset($options['json']);
$limit = null;
$now = null;

if (is_string($options['limit'] ?? null)) {
    $rawLimit = trim((string) $options['limit']);
    if ($rawLimit === '' || !ctype_digit($rawLimit) || (int) $rawLimit <= 0) {
        fwrite(STDERR, sprintf("Limite invalide: %s\n", $rawLimit));
        exit(1);
    }
    $limit = (int) $rawLimit;
}

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
$scheduledItems = [];
$scheduledVariants = 0;

while ($limit === null || count($scheduledItems) < $limit) {
    $result = $planner->planNextDraft($now, false);
    if (($result['scheduled'] ?? 0) <= 0 || !is_array($result['selected'] ?? null)) {
        break;
    }

    $scheduledVariants += (int) $result['scheduled'];
    $scheduledItems[] = $result['selected'];
}

$linksRebuild = [
    'attempted' => 0,
    'changed' => 0,
    'updated' => 0,
    'skipped' => 0,
    'dry_run' => true,
    'errors' => [],
];

if ($scheduledItems !== []) {
    $rebuilder = new BlogInternalLinksRebuilder(
        blog_repository(),
        new PageRepository(pages_data_path()),
        app_event_logger()
    );
    $linksRebuild = $rebuilder->rebuild($now);
    app_runtime_cache_clear(['pages', 'navigation']);
}

if ($jsonOutput) {
    $jsonOptions = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    $payload = [
        'status' => $scheduledItems !== [] ? 'scheduled' : 'noop',
        'scheduled_slug_count' => count($scheduledItems),
        'scheduled_variant_count' => $scheduledVariants,
        'limit' => $limit,
        'items' => $scheduledItems,
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

if ($scheduledItems === []) {
    fwrite(STDOUT, "Aucun brouillon à planifier.\n");
    exit(0);
}

foreach ($scheduledItems as $item) {
    $languages = array_values(
        array_filter(
            is_array($item['langs'] ?? null) ? $item['langs'] : [],
            static fn ($value): bool => is_string($value) && trim($value) !== ''
        )
    );

    fwrite(
        STDOUT,
        sprintf(
            "%s | %s | %s | %s\n",
            (string) ($item['scheduled_at'] ?? ''),
            (string) ($item['page_slug'] ?? ''),
            (string) ($item['slug'] ?? ''),
            implode(', ', $languages)
        )
    );
}

fwrite(
    STDOUT,
    sprintf(
        "Total planifie: %d slug(s) logique(s), %d variante(s).\n",
        count($scheduledItems),
        $scheduledVariants
    )
);

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
