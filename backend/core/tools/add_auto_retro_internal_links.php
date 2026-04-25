<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit etre executee en CLI.\n");
    exit(1);
}

$backendRoot = dirname(__DIR__, 2);
$pagesPath = $backendRoot . '/data/pages.json';
$dryRun = false;
$storageMode = 'json';
/** @var Caramagnols\Content\PageRepository|null $repository */
$repository = null;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--dry-run') {
        $dryRun = true;
        continue;
    }

    if (str_starts_with($argument, '--path=')) {
        $pagesPath = trim((string) substr($argument, 7));
        continue;
    }

    if (str_starts_with($argument, '--storage=')) {
        $storageMode = trim((string) substr($argument, 10));
        if (!in_array($storageMode, ['json', 'active'], true)) {
            fwrite(STDERR, "Stockage inconnu: {$storageMode}\n");
            fwrite(STDERR, usage());
            exit(1);
        }
        continue;
    }

    fwrite(STDERR, "Argument inconnu: {$argument}\n");
    fwrite(STDERR, usage());
    exit(1);
}

if ($storageMode === 'active') {
    require_once $backendRoot . '/core/bootstrap.php';
    $repository = page_repository();
    $registry = [
        'meta' => ['version' => 2],
        'pages' => $repository->all(),
    ];
} else {
    if (!is_file($pagesPath)) {
        fwrite(STDERR, "Fichier pages introuvable: {$pagesPath}\n");
        exit(1);
    }

    try {
        $registry = json_decode((string) file_get_contents($pagesPath), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        fwrite(STDERR, "JSON invalide: {$exception->getMessage()}\n");
        exit(1);
    }

    if (!is_array($registry) || !is_array($registry['pages'] ?? null)) {
        fwrite(STDERR, "Structure pages.json invalide.\n");
        exit(1);
    }
}

$pages =& $registry['pages'];
$autoRetroPages = collectAutoRetroPages($pages);
$routeIndex = [];

foreach ($autoRetroPages as $index => $pageInfo) {
    $routeIndex[$pageInfo['route']] = $index;
}

$updatedPages = 0;
$updatedTranslations = 0;
$updatedPageIndexes = [];
$plans = [];

foreach ($autoRetroPages as $pageInfo) {
    $relatedPages = selectRelatedPages($pageInfo, $autoRetroPages);
    if ($relatedPages === []) {
        continue;
    }

    $pageIndex = $pageInfo['pageIndex'];
    if (!isset($pages[$pageIndex]) || !is_array($pages[$pageIndex])) {
        continue;
    }

    $pageUpdated = false;

    foreach (['fr', 'en', 'de'] as $language) {
        if (!isset($pages[$pageIndex]['translations'][$language]) || !is_array($pages[$pageIndex]['translations'][$language])) {
            continue;
        }

        $paragraph = buildParagraph($pageInfo, $relatedPages, $language);
        if ($paragraph === '') {
            continue;
        }

        $linkedRoutes = array_values(array_map(
            static fn (array $relatedPage): string => (string) $relatedPage['route'],
            $relatedPages
        ));
        $translation =& $pages[$pageIndex]['translations'][$language];
        if (!isset($translation['regions']) || !is_array($translation['regions'])) {
            $translation['regions'] = [];
        }

        if (!appendParagraphToAfterBody($translation['regions']['after_body'], $paragraph, $linkedRoutes)) {
            unset($translation);
            continue;
        }

        unset($translation);
        $pageUpdated = true;
        $updatedTranslations++;
        $plans[] = sprintf(
            "%s\t%s\t%s -> %s",
            $language,
            $pageInfo['route'],
            implode(
                ', ',
                array_map(static fn (array $relatedPage): string => $relatedPage['route'], $relatedPages)
            ),
            strip_tags($paragraph)
        );
    }

    if ($pageUpdated) {
        $updatedPages++;
        $updatedPageIndexes[$pageIndex] = true;
    }
}

if ($dryRun) {
    echo "Dry run ({$storageMode}): {$updatedPages} page(s), {$updatedTranslations} traduction(s) seraient mises a jour.\n";
    foreach ($plans as $plan) {
        echo $plan . PHP_EOL;
    }
    exit(0);
}

if ($storageMode === 'active') {
    foreach (array_keys($updatedPageIndexes) as $pageIndex) {
        if (!isset($pages[$pageIndex]) || !is_array($pages[$pageIndex])) {
            continue;
        }

        $slug = trim((string) ($pages[$pageIndex]['slug'] ?? ''));
        if ($slug === '') {
            fwrite(STDERR, "Page sans slug a l'index {$pageIndex}.\n");
            exit(1);
        }

        if ($repository === null || !$repository->savePage($pages[$pageIndex], $slug)) {
            fwrite(STDERR, "Impossible de sauvegarder la page {$slug}.\n");
            exit(1);
        }
    }

    if ($updatedTranslations > 0) {
        pages_cache_clear();
        app_runtime_cache_clear(['pages', 'navigation', 'translations']);
    }
} elseif ($updatedTranslations > 0) {
    $json = json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        fwrite(STDERR, "Impossible d'encoder pages.json.\n");
        exit(1);
    }

    file_put_contents($pagesPath, $json . PHP_EOL);
}

echo "{$updatedPages} page(s), {$updatedTranslations} traduction(s) mises a jour.\n";

function usage(): string
{
    return <<<TXT
Usage:
  php backend/core/tools/add_auto_retro_internal_links.php [--dry-run] [--storage=json] [--path=backend/data/pages.json]
  php backend/core/tools/add_auto_retro_internal_links.php --storage=active [--dry-run]

TXT;
}

/**
 * @param array<int, mixed> $pages
 * @return array<int, array<string, mixed>>
 */
function collectAutoRetroPages(array $pages): array
{
    $autoRetroPages = [];

    foreach ($pages as $pageIndex => $page) {
        if (!is_array($page)) {
            continue;
        }

        $route = normalizeRoute((string) ($page['route'] ?? ''));
        if (!str_starts_with($route, '/auto-retro/')) {
            continue;
        }

        if (($page['type'] ?? '') !== 'structured_page' || ($page['status'] ?? '') !== 'published') {
            continue;
        }

        $routeParts = explode('/', trim($route, '/'));
        $brand = strtolower((string) ($routeParts[1] ?? ''));
        if ($brand === '') {
            continue;
        }

        $slug = (string) ($page['slug'] ?? '');
        $title = (string) (($page['translations']['fr']['title'] ?? null) ?: ($page['title'] ?? $slug));
        $searchText = mb_strtolower($slug . ' ' . $route . ' ' . $title);
        $model = inferModel($searchText);

        $autoRetroPages[] = [
            'pageIndex' => $pageIndex,
            'order' => count($autoRetroPages),
            'slug' => $slug,
            'route' => $route,
            'brand' => $brand,
            'model' => $model,
            'category' => inferCategory($searchText, $brand, $model),
            'titles' => [
                'fr' => (string) (($page['translations']['fr']['title'] ?? null) ?: ($page['title'] ?? $slug)),
                'en' => (string) (($page['translations']['en']['title'] ?? null) ?: ($page['title'] ?? $slug)),
                'de' => (string) (($page['translations']['de']['title'] ?? null) ?: ($page['title'] ?? $slug)),
            ],
        ];
    }

    return $autoRetroPages;
}

function normalizeRoute(string $route): string
{
    $route = trim($route);
    if ($route === '') {
        return '';
    }

    return '/' . ltrim($route, '/');
}

function inferModel(string $searchText): ?string
{
    $tokens = preg_split('/[^a-z0-9]+/u', $searchText) ?: [];
    $tokens = array_values(array_filter(array_map('strval', $tokens), static fn (string $token): bool => $token !== ''));

    foreach (['2cv', 'p60', 'aronde', 'mini', 'slk', 'twingo'] as $model) {
        if (in_array($model, $tokens, true)) {
            return $model;
        }
    }

    foreach ($tokens as $token) {
        if ($token === 'dyna' || str_starts_with($token, 'dynaz')) {
            return 'dyna_z';
        }
    }

    return null;
}

function inferCategory(string $searchText, string $brand, ?string $model): string
{
    if (str_contains($searchText, 'restauration') || str_contains($searchText, 'sava')) {
        return 'restoration';
    }

    if (str_contains($searchText, 'dans-le-golfe') || str_contains($searchText, 'notre ')) {
        return 'experience';
    }

    if (str_contains($searchText, 'histoire-de-' . $brand) && $model === null) {
        return 'brand_history';
    }

    if (str_contains($searchText, 'histoire') && $model !== null) {
        return 'model_history';
    }

    if ($model !== null) {
        return 'model_overview';
    }

    return 'brand_context';
}

/**
 * @param array<string, mixed> $currentPage
 * @param array<int, array<string, mixed>> $pages
 * @return array<int, array<string, mixed>>
 */
function selectRelatedPages(array $currentPage, array $pages): array
{
    $candidates = [];

    foreach ($pages as $candidate) {
        if ($candidate['route'] === $currentPage['route'] || $candidate['brand'] !== $currentPage['brand']) {
            continue;
        }

        $score = relatedScore($currentPage, $candidate);
        if ($score >= 1000) {
            continue;
        }

        $candidates[] = ['score' => $score, 'page' => $candidate];
    }

    usort(
        $candidates,
        static fn (array $left, array $right): int => [$left['score'], $left['page']['order']]
            <=> [$right['score'], $right['page']['order']]
    );

    return array_values(array_map(
        static fn (array $candidate): array => $candidate['page'],
        array_slice($candidates, 0, 2)
    ));
}

/**
 * @param array<string, mixed> $currentPage
 * @param array<string, mixed> $candidate
 */
function relatedScore(array $currentPage, array $candidate): int
{
    $sameModel = $currentPage['model'] !== null && $currentPage['model'] === $candidate['model'];
    $score = $sameModel ? 0 : 80;
    $candidateCategory = (string) $candidate['category'];

    if ($currentPage['category'] === 'brand_history') {
        return $score + match ($candidateCategory) {
            'model_history' => 10,
            'model_overview' => 20,
            'experience' => 30,
            'restoration' => 40,
            default => 90,
        };
    }

    if ($currentPage['category'] === 'restoration') {
        return $score + match ($candidateCategory) {
            'experience' => 10,
            'model_overview' => 20,
            'model_history' => 30,
            'brand_history' => 50,
            default => 90,
        };
    }

    if ($currentPage['category'] === 'experience') {
        return $score + match ($candidateCategory) {
            'model_overview' => 10,
            'model_history' => 20,
            'restoration' => 30,
            'brand_history' => 50,
            default => 90,
        };
    }

    if (in_array($currentPage['category'], ['model_history', 'model_overview'], true)) {
        return $score + match ($candidateCategory) {
            'experience' => 10,
            'restoration' => 20,
            'model_history', 'model_overview' => 30,
            'brand_history' => 50,
            default => 90,
        };
    }

    return match ($candidateCategory) {
        'brand_history' => 30,
        'model_history', 'model_overview' => 40,
        'experience', 'restoration' => 50,
        default => 1000,
    };
}

/**
 * @param array<string, mixed> $currentPage
 * @param array<int, array<string, mixed>> $relatedPages
 */
function buildParagraph(array $currentPage, array $relatedPages, string $language): string
{
    if ($relatedPages === []) {
        return '';
    }

    $firstLink = linkHtml($relatedPages[0], $language);
    $secondLink = isset($relatedPages[1]) ? secondLinkHtml($relatedPages[1], $language) : '';

    if ($currentPage['category'] === 'brand_history') {
        $brand = brandLabel((string) $currentPage['brand'], $language);

        return match ($language) {
            'en' => "<p>This <strong>{$brand}</strong> history also comes into focus through {$firstLink}{$secondLink}.</p>",
            'de' => "<p>Mehr Kontext zu <strong>{$brand}</strong> bieten {$firstLink}{$secondLink}.</p>",
            default => "<p>Cette histoire de la marque <strong>{$brand}</strong> se lit aussi à travers {$firstLink}{$secondLink}.</p>",
        };
    }

    $model = modelLabel(is_string($currentPage['model']) ? $currentPage['model'] : null, $language)
        ?? brandLabel((string) $currentPage['brand'], $language);

    if ($currentPage['category'] === 'restoration') {
        return match ($language) {
            'en' => "<p>To place this restoration within the story of <strong>{$model}</strong>, you can also read {$firstLink}{$secondLink}.</p>",
            'de' => "<p>Zum Hintergrund dieser Restaurierung bieten {$firstLink}{$secondLink} weiteren Kontext.</p>",
            default => "<p>Pour replacer cette restauration dans le parcours de <strong>{$model}</strong>, vous pouvez aussi lire {$firstLink}{$secondLink}.</p>",
        };
    }

    if ($currentPage['category'] === 'experience') {
        return match ($language) {
            'en' => "<p>This car belongs to a wider story that continues in {$firstLink}{$secondLink}.</p>",
            'de' => "<p>Dieses Auto gehört zu einer größeren Geschichte. Weitere Zusammenhänge bieten {$firstLink}{$secondLink}.</p>",
            default => "<p>Cette voiture s'inscrit dans une histoire plus large que vous pouvez retrouver dans {$firstLink}{$secondLink}.</p>",
        };
    }

    return match ($language) {
        'en' => "<p>To place <strong>{$model}</strong> in context, you can also read {$firstLink}{$secondLink}.</p>",
        'de' => "<p>Um <strong>{$model}</strong> besser einzuordnen, bieten {$firstLink}{$secondLink} weiteren Kontext.</p>",
        default => "<p>Pour mieux comprendre <strong>{$model}</strong>, vous pouvez aussi découvrir {$firstLink}{$secondLink}.</p>",
    };
}

/**
 * @param array<string, mixed> $page
 */
function linkHtml(array $page, string $language): string
{
    $route = htmlspecialchars((string) $page['route'], ENT_QUOTES, 'UTF-8');
    $label = htmlspecialchars(anchorLabel((string) $page['route'], $language, $page), ENT_QUOTES, 'UTF-8');

    return "<a href=\"{$route}\">{$label}</a>";
}

/**
 * @param array<string, mixed> $page
 */
function secondLinkHtml(array $page, string $language): string
{
    return match ($language) {
        'en' => ', along with ' . linkHtml($page, $language),
        'de' => ' sowie ' . linkHtml($page, $language),
        default => ', ainsi que ' . linkHtml($page, $language),
    };
}

/**
 * @param array<string, mixed> $page
 */
function anchorLabel(string $route, string $language, array $page): string
{
    $labels = [
        '/auto-retro/austin/histoire-de-austin.php' => [
            'fr' => "l'histoire d'Austin",
            'en' => 'the history of Austin',
            'de' => 'die Geschichte von Austin',
        ],
        '/auto-retro/austin/aventure-mini-austin.php' => [
            'fr' => "l'histoire de la Mini chez Austin et Morris",
            'en' => 'the Austin and Morris Mini story',
            'de' => 'die Geschichte des Mini bei Austin und Morris',
        ],
        '/auto-retro/austin/la-mini-mayfair.php' => [
            'fr' => 'la Mini Mayfair',
            'en' => 'the Mini Mayfair',
            'de' => 'der Mini Mayfair',
        ],
        '/auto-retro/austin/une-mini-dans-le-golfe-de-sttropez.php' => [
            'fr' => 'notre Mini Mayfair dans le golfe de Saint-Tropez',
            'en' => 'our Mini Mayfair in the Gulf of Saint-Tropez',
            'de' => 'unser Mini Mayfair im Golf von Saint-Tropez',
        ],
        '/auto-retro/citroen/histoire-de-citroen.php' => [
            'fr' => "l'histoire de Citroën",
            'en' => 'the history of Citroën',
            'de' => 'die Geschichte von Citroën',
        ],
        '/auto-retro/citroen/la-2cv-une-passion-francaise.php' => [
            'fr' => "l'histoire de la Citroën 2CV",
            'en' => 'the Citroën 2CV story',
            'de' => 'die Geschichte des Citroën 2CV',
        ],
        '/auto-retro/citroen/la-2cv-az-1954-1956.php' => [
            'fr' => 'la Citroën 2CV AZ',
            'en' => 'the Citroën 2CV AZ',
            'de' => 'der Citroën 2CV AZ',
        ],
        '/auto-retro/citroen/la-2cv-aza.php' => [
            'fr' => 'la Citroën 2CV AZA',
            'en' => 'the Citroën 2CV AZA',
            'de' => 'der Citroën 2CV AZA',
        ],
        '/auto-retro/mercedes/histoire-de-mercedes.php' => [
            'fr' => "l'histoire de Mercedes-Benz",
            'en' => 'the history of Mercedes-Benz',
            'de' => 'die Geschichte von Mercedes-Benz',
        ],
        '/auto-retro/mercedes/la-slk-une-voiture-compacte-sportive.php' => [
            'fr' => "l'histoire de la Mercedes-Benz SLK",
            'en' => 'the Mercedes-Benz SLK story',
            'de' => 'die Geschichte des Mercedes-Benz SLK',
        ],
        '/auto-retro/mercedes/la-slk-r170.php' => [
            'fr' => 'la Mercedes-Benz SLK R170',
            'en' => 'the Mercedes-Benz SLK R170',
            'de' => 'der Mercedes-Benz SLK R170',
        ],
        '/auto-retro/mercedes/une-slk-dans-le-golfe-de-sttropez.php' => [
            'fr' => 'notre Mercedes SLK dans le golfe de Saint-Tropez',
            'en' => 'our Mercedes SLK in the Gulf of Saint-Tropez',
            'de' => 'unser Mercedes SLK im Golf von Saint-Tropez',
        ],
        '/auto-retro/panhard/histoire-de-panhard.php' => [
            'fr' => "l'histoire de Panhard",
            'en' => 'the history of Panhard',
            'de' => 'die Geschichte von Panhard',
        ],
        '/auto-retro/panhard/la-dyna-z-voiture-de-collection.php' => [
            'fr' => 'la Panhard Dyna Z de collection',
            'en' => 'the collectible Panhard Dyna Z',
            'de' => 'der Panhard Dyna Z als Sammlerfahrzeug',
        ],
        '/auto-retro/panhard/la-dyna-modele-z12.php' => [
            'fr' => 'la Panhard Dyna Z12',
            'en' => 'the Panhard Dyna Z12',
            'de' => 'der Panhard Dyna Z12',
        ],
        '/auto-retro/panhard/une-dyna-icone-automobile.php' => [
            'fr' => 'la Dyna de chez Panhard',
            'en' => 'the Dyna from Panhard',
            'de' => 'der Dyna von Panhard',
        ],
        '/auto-retro/panhard/une-dynaz12-dans-le-golfe-de-sttropez.php' => [
            'fr' => 'notre Dyna Z12 dans le golfe de Saint-Tropez',
            'en' => 'our Dyna Z12 in the Gulf of Saint-Tropez',
            'de' => 'unser Dyna Z12 im Golf von Saint-Tropez',
        ],
        '/auto-retro/renault/histoire-de-renault.php' => [
            'fr' => "l'histoire de Renault",
            'en' => 'the history of Renault',
            'de' => 'die Geschichte von Renault',
        ],
        '/auto-retro/renault/la-twingo-une-voiture-a-succes.php' => [
            'fr' => "l'histoire de la Renault Twingo",
            'en' => 'the Renault Twingo story',
            'de' => 'die Geschichte des Renault Twingo',
        ],
        '/auto-retro/renault/une-twingo-dans-le-golfe-de-sttropez.php' => [
            'fr' => 'notre Twingo Hélios dans le golfe de Saint-Tropez',
            'en' => 'our Twingo Hélios in the Gulf of Saint-Tropez',
            'de' => 'unser Twingo Hélios im Golf von Saint-Tropez',
        ],
        '/auto-retro/simca/histoire-de-simca.php' => [
            'fr' => "l'histoire de Simca",
            'en' => 'the history of Simca',
            'de' => 'die Geschichte von Simca',
        ],
        '/auto-retro/simca/histoire-simca-aronde-icone-francaise.php' => [
            'fr' => "l'histoire de la Simca Aronde",
            'en' => 'the Simca Aronde story',
            'de' => 'die Geschichte der Simca Aronde',
        ],
        '/auto-retro/simca/la-simca-9-aronde-voiture-de-collection.php' => [
            'fr' => 'la Simca 9 Aronde',
            'en' => 'the Simca 9 Aronde',
            'de' => 'die Simca 9 Aronde',
        ],
        '/auto-retro/simca/la-simca-P60-voiture-de-collection.php' => [
            'fr' => 'la Simca P60',
            'en' => 'the Simca P60',
            'de' => 'der Simca P60',
        ],
        '/auto-retro/simca/la-simca-aronde-1300-voiture-de-collection.php' => [
            'fr' => "la Simca Aronde 1300",
            'en' => 'the Simca Aronde 1300',
            'de' => 'die Simca Aronde 1300',
        ],
        '/auto-retro/simca/une-aronde-dans-le-golfe-de-sttropez.php' => [
            'fr' => 'notre Simca Aronde dans le golfe de Saint-Tropez',
            'en' => 'our Simca Aronde in the Gulf of Saint-Tropez',
            'de' => 'unsere Simca Aronde im Golf von Saint-Tropez',
        ],
        '/auto-retro/simca/une-simca-aronde-en-restauration-chez-sava-rioz.php' => [
            'fr' => "la restauration de notre Simca Aronde chez SAVA",
            'en' => 'the restoration of our Simca Aronde at SAVA',
            'de' => 'die Restaurierung unserer Simca Aronde bei SAVA',
        ],
    ];

    return $labels[$route][$language] ?? (string) ($page['titles'][$language] ?? $page['slug'] ?? $route);
}

function brandLabel(string $brand, string $language): string
{
    $labels = [
        'austin' => ['fr' => 'Austin', 'en' => 'Austin', 'de' => 'Austin'],
        'citroen' => ['fr' => 'Citroën', 'en' => 'Citroën', 'de' => 'Citroën'],
        'mercedes' => ['fr' => 'Mercedes-Benz', 'en' => 'Mercedes-Benz', 'de' => 'Mercedes-Benz'],
        'panhard' => ['fr' => 'Panhard', 'en' => 'Panhard', 'de' => 'Panhard'],
        'renault' => ['fr' => 'Renault', 'en' => 'Renault', 'de' => 'Renault'],
        'simca' => ['fr' => 'Simca', 'en' => 'Simca', 'de' => 'Simca'],
    ];

    return $labels[$brand][$language] ?? ucfirst($brand);
}

function modelLabel(?string $model, string $language): ?string
{
    if ($model === null) {
        return null;
    }

    $labels = [
        '2cv' => ['fr' => 'la 2CV', 'en' => 'the 2CV', 'de' => 'den 2CV'],
        'mini' => ['fr' => 'la Mini', 'en' => 'the Mini', 'de' => 'den Mini'],
        'slk' => ['fr' => 'la SLK', 'en' => 'the SLK', 'de' => 'den SLK'],
        'dyna_z' => ['fr' => 'la Dyna Z', 'en' => 'the Dyna Z', 'de' => 'die Dyna Z'],
        'twingo' => ['fr' => 'la Twingo', 'en' => 'the Twingo', 'de' => 'den Twingo'],
        'aronde' => ['fr' => "l'Aronde", 'en' => 'the Aronde', 'de' => 'die Aronde'],
        'p60' => ['fr' => 'la Simca P60', 'en' => 'the Simca P60', 'de' => 'den Simca P60'],
    ];

    return $labels[$model][$language] ?? null;
}

/**
 * @param array<int, string> $linkedRoutes
 */
function appendParagraphToAfterBody(mixed &$region, string $paragraph, array $linkedRoutes): bool
{
    if (is_string($region)) {
        if (containsParagraphOrLinkedRoute($region, $paragraph, $linkedRoutes)) {
            return false;
        }

        $region = appendHtml($region, $paragraph);
        return true;
    }

    if ($region === null || $region === []) {
        $region = [
            'component' => 'rich_text',
            'html' => $paragraph,
        ];
        return true;
    }

    if (!is_array($region)) {
        return false;
    }

    if (array_is_list($region)) {
        foreach ($region as $item) {
            if (
                is_array($item)
                && ($item['component'] ?? null) === 'rich_text'
                && containsParagraphOrLinkedRoute((string) ($item['html'] ?? ''), $paragraph, $linkedRoutes)
            ) {
                return false;
            }
        }

        $lastIndex = array_key_last($region);
        if (is_int($lastIndex) && is_array($region[$lastIndex]) && ($region[$lastIndex]['component'] ?? null) === 'rich_text') {
            $region[$lastIndex]['html'] = appendHtml((string) ($region[$lastIndex]['html'] ?? ''), $paragraph);
            return true;
        }

        $region[] = [
            'component' => 'rich_text',
            'html' => $paragraph,
        ];
        return true;
    }

    if (($region['component'] ?? null) === 'rich_text') {
        $html = (string) ($region['html'] ?? '');
        if (containsParagraphOrLinkedRoute($html, $paragraph, $linkedRoutes)) {
            return false;
        }

        $region['html'] = appendHtml($html, $paragraph);
        return true;
    }

    return false;
}

/**
 * @param array<int, string> $linkedRoutes
 */
function containsParagraphOrLinkedRoute(string $html, string $paragraph, array $linkedRoutes): bool
{
    if (str_contains($html, $paragraph)) {
        return true;
    }

    foreach ($linkedRoutes as $route) {
        $escapedRoute = htmlspecialchars($route, ENT_QUOTES, 'UTF-8');
        if (
            str_contains($html, 'href="' . $escapedRoute . '"')
            || str_contains($html, "href='" . $escapedRoute . "'")
        ) {
            return true;
        }
    }

    return false;
}

function appendHtml(string $html, string $paragraph): string
{
    $html = rtrim($html);

    return $html === '' ? $paragraph : $html . "\n" . $paragraph;
}
