<?php

declare(strict_types=1);

use Caramagnols\Blog\BlogRelatedLinksNormalizer;

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

$blogDirectory = dirname(__DIR__, 2) . '/data/blog';
$files = glob($blogDirectory . '/*.json') ?: [];
sort($files);

$normalizer = new BlogRelatedLinksNormalizer();
$scanned = 0;
$changed = 0;
$linksPreserved = 0;

foreach ($files as $file) {
    $payload = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) {
        continue;
    }
    $scanned++;
    $result = $normalizer->normalize(
        (string) ($payload['content'] ?? ''),
        strtolower(trim((string) ($payload['lang'] ?? '')))
    );
    if (!$result['changed']) {
        continue;
    }
    $changed++;
    $linksPreserved += $result['link_count'];
    if (!$apply) {
        continue;
    }

    $payload['content'] = $result['content'];
    $payload['updated_at'] = '2026-08-14T12:30:00+02:00';
    $encoded = json_encode(
        $payload,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
    file_put_contents($file, $encoded . PHP_EOL, LOCK_EX);
}

printf(
    "%s: %d fichier(s) analysé(s), %d modifié(s), %d lien(s) interne(s) préservé(s).\n",
    $apply ? 'APPLY' : 'DRY-RUN',
    $scanned,
    $changed,
    $linksPreserved
);
