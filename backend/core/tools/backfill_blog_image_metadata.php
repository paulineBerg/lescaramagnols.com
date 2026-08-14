<?php

declare(strict_types=1);

use Caramagnols\Blog\BlogImageMetadataBackfiller;

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
$backfiller = new BlogImageMetadataBackfiller();
$changed = 0;
$images = 0;

foreach ($files as $file) {
    $payload = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) {
        continue;
    }
    $result = $backfiller->addMissingTitles((string) ($payload['content'] ?? ''));
    if (!$result['changed']) {
        continue;
    }
    $changed++;
    $images += $result['image_count'];
    if (!$apply) {
        continue;
    }
    $payload['content'] = $result['content'];
    $payload['updated_at'] = '2026-08-14T13:00:00+02:00';
    $encoded = json_encode(
        $payload,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
    file_put_contents($file, $encoded . PHP_EOL, LOCK_EX);
}

printf(
    "%s: %d fichier(s), %d image(s) complétée(s).\n",
    $apply ? 'APPLY' : 'DRY-RUN',
    $changed,
    $images
);
