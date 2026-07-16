<?php

declare(strict_types=1);

use Caramagnols\Content\LegacyTileMigrationService;

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit être exécutée en CLI.\n");
    exit(1);
}

$arguments = array_slice($argv, 1);
$apply = in_array('--apply', $arguments, true);
$help = in_array('--help', $arguments, true) || in_array('-h', $arguments, true);

if ($help) {
    fwrite(STDOUT, usage());
    exit(0);
}

$service = new LegacyTileMigrationService(
    page_repository(),
    tile_repository(),
    function_exists('site_available_languages') ? site_available_languages() : ['fr', 'en', 'de']
);

$result = $apply ? $service->apply() : $service->preview();
$counts = is_array($result['counts'] ?? null) ? $result['counts'] : [];
$errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
$pages = is_array($result['pages'] ?? null) ? $result['pages'] : [];

fwrite(STDOUT, sprintf(
    "[legacy-tiles] mode=%s groups=%d pages=%d migrated=%d cleaned_regions=%d placements=%d errors=%d\n",
    $apply ? 'apply' : 'dry-run',
    (int) ($counts['groups'] ?? 0),
    (int) ($counts['pages'] ?? 0),
    (int) ($counts['migratedPages'] ?? 0),
    (int) ($counts['cleanedRegions'] ?? 0),
    (int) ($counts['placements'] ?? 0),
    (int) ($counts['errors'] ?? 0)
));

foreach ($pages as $page) {
    if (!is_array($page)) {
        continue;
    }

    $cleanedRegions = is_array($page['cleanedRegions'] ?? null) ? $page['cleanedRegions'] : [];
    fwrite(STDOUT, sprintf(
        " - %s [%s] group=%s overrides=%d cleaned=%s\n",
        (string) ($page['slug'] ?? ''),
        (string) ($page['status'] ?? 'unknown'),
        (string) ($page['group'] ?? ''),
        max(0, (int) ($page['overrideCount'] ?? 0)),
        $cleanedRegions === [] ? '-' : implode(',', $cleanedRegions)
    ));
}

if ($errors !== []) {
    fwrite(STDERR, "[legacy-tiles] erreurs:\n");
    foreach ($errors as $error) {
        if (!is_string($error) || trim($error) === '') {
            continue;
        }

        fwrite(STDERR, ' - ' . $error . PHP_EOL);
    }

    exit(1);
}

fwrite(
    STDOUT,
    $apply
        ? "[legacy-tiles] migration appliquée.\n"
        : "[legacy-tiles] aperçu terminé. Relancez avec --apply après backup SQL.\n"
);

exit(0);

function usage(): string
{
    return <<<TXT
Usage:
  php core/tools/migrate_legacy_page_tiles.php
  php core/tools/migrate_legacy_page_tiles.php --apply

Description:
  Migre les blocs HTML legacy de tuiles vers le module SQL "Tuiles".
  Par défaut la commande est en dry-run.

TXT;
}
