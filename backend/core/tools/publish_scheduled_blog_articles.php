<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Caramagnols\Blog\ScheduledBlogPublisher;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit etre executee en CLI.\n");
    exit(1);
}

$options = parse_cli_options(array_slice($argv, 1));
$dryRun = isset($options['dry-run']);
$jsonOutput = isset($options['json']);

$publisher = new ScheduledBlogPublisher(blog_repository(), app_event_logger());
$result = $publisher->publishDueArticles($dryRun);

if (!$dryRun && (int) ($result['published'] ?? 0) > 0) {
    app_runtime_cache_clear(['pages', 'navigation']);
}

if ($jsonOutput) {
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
}

fwrite(STDOUT, sprintf("Articles verifies : %d\n", (int) ($result['checked'] ?? 0)));
fwrite(STDOUT, sprintf("Articles arrives a echeance : %d\n", (int) ($result['due'] ?? 0)));
fwrite(
    STDOUT,
    sprintf(
        "Articles %s : %d\n",
        $dryRun ? 'qui seraient publies' : 'publies',
        (int) ($result['published'] ?? 0)
    )
);

$articles = is_array($result['articles'] ?? null) ? $result['articles'] : [];
foreach ($articles as $article) {
    if (!is_array($article)) {
        continue;
    }

    $slug = trim((string) ($article['slug'] ?? ''));
    $language = trim((string) ($article['lang'] ?? 'fr'));
    $date = trim((string) ($article['date'] ?? ''));
    if ($slug === '') {
        continue;
    }

    fwrite(
        STDOUT,
        sprintf("- %s (%s) @ %s\n", $slug, $language !== '' ? $language : 'fr', $date !== '' ? $date : '-')
    );
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
