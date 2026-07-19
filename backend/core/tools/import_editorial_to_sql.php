<?php

declare(strict_types=1);

use Caramagnols\Content\PageRepository;
use Caramagnols\Content\SqlPageStore;
use Caramagnols\Content\StructuredPageRenderer;
use Caramagnols\Editorial\EditorialImportService;
use Caramagnols\Navigation\NavigationRepository;
use Caramagnols\Navigation\SqlNavigationStore;

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit être lancée en CLI.\n");
    exit(1);
}

$options = array_slice($argv, 1);
$importPages = !in_array('--menus-only', $options, true);
$importMenus = !in_array('--pages-only', $options, true);

$pageSource = new PageRepository(ROOT_PATH . '/data/pages.json', new StructuredPageRenderer(), 'json');
$navigationSource = new NavigationRepository(ROOT_PATH . '/data/menus.json', 'json');
$pageRegistry = $pageSource->registry();
$database = editorial_database();
$service = new EditorialImportService(
    new SqlPageStore($database),
    new SqlNavigationStore($database)
);

if (!$importPages || !$importMenus) {
    if ($importPages) {
        $result = (new SqlPageStore($database))->replaceRegistry($pageRegistry);
        fwrite(
            $result ? STDOUT : STDERR,
            $result ? "Pages importées vers SQL.\n" : "Échec de l'import SQL des pages.\n"
        );
        exit($result ? 0 : 1);
    }

    $result = (new SqlNavigationStore($database))->saveCanonical($navigationSource->loadCanonical());
    fwrite(
        $result ? STDOUT : STDERR,
        $result ? "Navigation importée vers SQL.\n" : "Échec de l'import SQL de la navigation.\n"
    );
    exit($result ? 0 : 1);
}

$result = $service->import($pageSource, $navigationSource, [], $pageRegistry);

if (!$result['success']) {
    fwrite(STDERR, ($result['error'] ?? 'Échec de l’import éditorial SQL.') . "\n");
    exit(1);
}

fwrite(
    STDOUT,
    sprintf(
        "Import SQL terminé : %d page(s), %d emplacement(s) de navigation.\n",
        $result['pages'],
        $result['menu_locations']
    )
);
