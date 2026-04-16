<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

$languages = function_exists('site_available_languages')
    ? site_available_languages()
    : [defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr'];
$defaultLanguage = defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr';
$repository = page_repository_for_path(pages_data_path());

/**
 * Détermine si un bloc correspond à un menu UI (non pertinent pour la recherche).
 */
function isMenuUIBlock(string $blocContent): bool
{
    return (
        preg_match('/MENU_UI_/i', $blocContent)
        || preg_match('#/assets/images/structure/menu/#i', $blocContent)
        || preg_match('/id=["\']menurectanglewindows/i', $blocContent)
        || preg_match('/id=["\']boutonrectangle/i', $blocContent)
    );
}

/**
 * Convertit un contenu HTML en texte normalisé.
 */
function normalizeText(string $html): string
{
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);

    return trim((string) $text);
}

/**
 * Récupère la première image trouvée dans un bloc HTML.
 */
function extractFirstImage(string $html): ?string
{
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $match)) {
        return (string) $match[1];
    }

    return null;
}

function searchIndexCategory(string $route): string
{
    $normalized = normalize_public_route($route);
    if ($normalized === null || $normalized === '/') {
        return 'accueil';
    }

    $segment = explode('/', trim($normalized, '/'))[0] ?? 'pages';
    $segment = preg_replace('/\.php$/i', '', $segment) ?? $segment;

    return trim((string) $segment) !== '' ? (string) $segment : 'pages';
}

foreach ($languages as $language) {
    if (!is_string($language) || trim($language) === '') {
        continue;
    }

    $language = trim($language);
    echo "--- Génération de l'index pour la langue : {$language} ---\n";

    $outputFileFull = ROOT_PATH . "/data/search_index_{$language}.json";
    $outputFileMin = ROOT_PATH . "/data/search_index_{$language}.min.json";
    $index = [];

    foreach ($repository->published() as $page) {
        if (!is_array($page)) {
            continue;
        }

        $slug = trim((string) ($page['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }

        $rendered = $repository->findPublishedStructuredBySlug($slug, $language, $defaultLanguage);
        if (!is_array($rendered)) {
            continue;
        }

        $title = trim((string) ($rendered['title'] ?? ''));
        $route = normalize_public_route((string) ($rendered['route'] ?? '')) ?? ('/' . $slug);
        $category = searchIndexCategory($route);
        $blocks = is_array($rendered['blocks'] ?? null) ? $rendered['blocks'] : [];
        $textParts = [];
        $image = null;
        $usedBlocks = [];

        foreach ($blocks as $blockId => $blockContent) {
            if (!is_string($blockId) || !is_string($blockContent) || trim($blockContent) === '') {
                continue;
            }

            if ($image === null) {
                $image = extractFirstImage($blockContent);
            }

            if (isMenuUIBlock($blockContent)) {
                continue;
            }

            $text = normalizeText($blockContent);
            if ($text === '') {
                continue;
            }

            $textParts[] = $text;
            $usedBlocks[] = $blockId;
        }

        $body = trim(implode(' ', $textParts));
        if ($title === '' && $body === '') {
            continue;
        }

        if ($title === '') {
            $title = $slug;
        }

        $index[$category][] = [
            'titre' => $title,
            'contenu' => mb_substr($body, 0, 300) . (mb_strlen($body) > 300 ? '…' : ''),
            'url' => $route,
            'image' => $image,
            'blocs_utilises' => array_values(array_unique($usedBlocks)),
        ];
    }

    foreach ($index as &$entries) {
        usort(
            $entries,
            static fn (array $left, array $right): int => strcasecmp((string) $left['titre'], (string) $right['titre'])
        );
    }
    unset($entries);

    $dataPath = dirname($outputFileFull);
    if (!is_dir($dataPath) && !mkdir($dataPath, 0755, true) && !is_dir($dataPath)) {
        echo "❌ Impossible de créer le dossier de sortie '{$dataPath}'.\n";
        continue;
    }

    $jsonPretty = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $jsonMin = json_encode($index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($jsonPretty === false || $jsonMin === false) {
        echo "❌ Impossible d'encoder l'index '{$language}'.\n";
        continue;
    }

    file_put_contents($outputFileFull, $jsonPretty);
    file_put_contents($outputFileMin, $jsonMin);

    if ($language === $defaultLanguage) {
        file_put_contents(ROOT_PATH . '/data/search_index.json', $jsonPretty);
        file_put_contents(ROOT_PATH . '/data/search_index.min.json', $jsonMin);
    }

    $totalEntries = 0;
    foreach ($index as $entries) {
        $totalEntries += count($entries);
    }

    echo "✅ Index pour '{$language}' généré avec succès.\n";
    echo "   - 📁 {$outputFileFull}\n";
    echo "   - 📁 {$outputFileMin}\n";
    echo '   - 📊 Catégories : ' . count($index) . " — Total éléments : {$totalEntries}\n";
}
