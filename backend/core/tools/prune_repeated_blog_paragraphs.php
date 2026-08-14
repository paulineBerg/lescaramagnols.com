<?php

declare(strict_types=1);

use Caramagnols\Blog\BlogRepeatedParagraphPruner;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$apply = in_array('--apply', $argv, true);
$unknownOptions = array_values(array_filter(
    array_slice($argv, 1),
    static fn (string $argument): bool => $argument !== '--apply'
));
if ($unknownOptions !== []) {
    fwrite(STDERR, 'Option inconnue : ' . implode(', ', $unknownOptions) . PHP_EOL);
    exit(2);
}

$files = glob(dirname(__DIR__, 2) . '/data/blog/*.json') ?: [];
sort($files);
$articles = [];
foreach ($files as $file) {
    $payload = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    if (is_array($payload)) {
        $articles[$file] = $payload;
    }
}

$pruner = new BlogRepeatedParagraphPruner();
$repeatedIndex = $pruner->repeatedSentenceIndex(array_values($articles));
$changed = 0;
$paragraphsRemoved = 0;
$headingsRemoved = 0;

foreach ($articles as $file => $payload) {
    $language = strtolower(trim((string) ($payload['lang'] ?? '')));
    $result = $pruner->prune(
        (string) ($payload['content'] ?? ''),
        $repeatedIndex[$language] ?? []
    );
    if (!$result['changed']) {
        continue;
    }
    $changed++;
    $paragraphsRemoved += $result['paragraph_count'];
    $headingsRemoved += $result['heading_count'];
    if (!$apply) {
        continue;
    }

    $payload['content'] = $result['content'];
    $payload['updated_at'] = '2026-08-14T12:40:00+02:00';
    $encoded = json_encode(
        $payload,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
    file_put_contents($file, $encoded . PHP_EOL, LOCK_EX);
}

$indexedSentenceCount = array_sum(array_map('count', $repeatedIndex));
printf(
    "%s: %d fichier(s), %d paragraphe(s) gabarit et %d intertitre(s) vide(s) supprimé(s), %d phrase(s) répétée(s) indexée(s).\n",
    $apply ? 'APPLY' : 'DRY-RUN',
    $changed,
    $paragraphsRemoved,
    $headingsRemoved,
    $indexedSentenceCount
);
