<?php
// core/tools/generate_search_index.php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
define('TEMPLATE_PATH', ROOT_PATH . '/templates/pages/site/');

require_once ROOT_PATH . '/core/i18n.php';

// 🌐 Langues à traiter
$languages = ['fr', 'en', 'de'];

// ❌ Fichiers et dossiers exclus de l'index
$excludeFiles = ['404.php', 'search.php', 'index.php', 'test.php'];

/**
 * Détermine si un bloc correspond à un menu UI (non pertinent pour la recherche).
 */
function isMenuUIBlock(string $blocContent): bool {
    return (
        preg_match('/MENU_UI_/i', $blocContent) ||
        preg_match('#/assets/images/structure/menu/#i', $blocContent) ||
        preg_match('/id=["\']menurectanglewindows/i', $blocContent) ||
        preg_match('/id=["\']boutonrectangle/i', $blocContent)
    );
}

/**
 * Convertit un contenu HTML en texte normalisé.
 */
function normalizeText(string $html): string {
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text);
}

/**
 * Récupère la première image trouvée dans un bloc HTML.
 */
function extractFirstImage(string $html): ?string {
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $match)) {
        return $match[1];
    }
    return null;
}

/**
 * Exécute un template afin de récupérer les blocs produits.
 *
 * @return array<string, string>
 */
function renderTemplateBlocks(string $filepath, array $lang): array {
    global $langTranslations;

    $langTranslations = $lang;
    $blocks = [];

    ob_start();
    try {
        include $filepath;
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    ob_end_clean();

    return is_array($blocks) ? $blocks : [];
}

foreach ($languages as $langCode) {
    echo "--- Génération de l'index pour la langue : $langCode ---\n";

    $langFilePath = ROOT_PATH . "/lang/{$langCode}.php";
    if (!file_exists($langFilePath)) {
        echo "❌ Fichier de langue non trouvé pour '$langCode', ignoré.\n";
        continue;
    }

    $lang = require $langFilePath;
    if (!is_array($lang)) {
        echo "❌ Le fichier de langue '$langCode' n'a pas retourné de tableau, ignoré.\n";
        continue;
    }

    $outputFileFull = ROOT_PATH . "/data/search_index_{$langCode}.json";
    $outputFileMin = ROOT_PATH . "/data/search_index_{$langCode}.min.json";

    $index = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(TEMPLATE_PATH));
    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if ($file->isDir() || $file->getExtension() !== 'php') {
            continue;
        }

        $filepath = $file->getPathname();
        $relativePath = str_replace(TEMPLATE_PATH, '', $filepath);
        if (in_array(basename($relativePath), $excludeFiles, true)) {
            continue;
        }

        $url = '/' . str_replace('.php', '', str_replace('\\', '/', 'site/' . $relativePath));
        $parts = explode('/', trim($relativePath, '/'));
        $categorie = $parts[0] ?? 'inconnu';

        try {
            $blocks = renderTemplateBlocks($filepath, $lang);
        } catch (Throwable $exception) {
            echo "⚠️  Impossible d'inclure '$relativePath' : " . $exception->getMessage() . "\n";
            continue;
        }

        if (empty($blocks)) {
            continue;
        }

        $titre = '';
        $texteTotal = '';
        $image = null;
        $blocsUtilises = [];

        foreach ($blocks as $blocId => $blocContent) {
            if (!is_string($blocContent) || trim($blocContent) === '') {
                continue;
            }

            if (!$image) {
                $image = extractFirstImage($blocContent);
            }

            if ($titre === '' && strtolower($blocId) === 'editregion1') {
                $titre = normalizeText($blocContent);
            }

            if (isMenuUIBlock($blocContent)) {
                continue;
            }

            $texteTotal .= ' ' . normalizeText($blocContent);
            $blocsUtilises[] = $blocId;
        }

        $titre = $titre !== '' ? $titre : '(Sans titre)';
        $texteTotal = trim($texteTotal);

        if ($texteTotal === '') {
            continue;
        }

        $index[$categorie][] = [
            'titre' => $titre,
            'contenu' => mb_substr($texteTotal, 0, 300) . (mb_strlen($texteTotal) > 300 ? '…' : ''),
            'url' => $url,
            'image' => $image,
            'blocs_utilises' => array_values(array_unique($blocsUtilises)),
        ];
    }

    // 🔠 Tri alphabétique
    foreach ($index as &$entries) {
        usort($entries, static function ($a, $b) {
            return strcasecmp($a['titre'], $b['titre']);
        });
    }
    unset($entries);

    // 📁 Dossier de sortie
    $dataPath = dirname($outputFileFull);
    if (!is_dir($dataPath) && !mkdir($dataPath, 0755, true) && !is_dir($dataPath)) {
        echo "❌ Impossible de créer le dossier de sortie '$dataPath'.\n";
        continue;
    }

    // 💾 Écriture des fichiers
    $jsonPretty = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $jsonMin = json_encode($index, JSON_UNESCAPED_UNICODE);

    file_put_contents($outputFileFull, $jsonPretty);
    file_put_contents($outputFileMin, $jsonMin);

    // Maintien des anciens noms de fichiers pour la langue par défaut (français)
    if ($langCode === 'fr') {
        file_put_contents(ROOT_PATH . '/data/search_index.json', $jsonPretty);
        file_put_contents(ROOT_PATH . '/data/search_index.min.json', $jsonMin);
    }

    $totalEntries = 0;
    foreach ($index as $entries) {
        $totalEntries += count($entries);
    }

    // ✅ Résumé
    echo "✅ Index pour '$langCode' généré avec succès.\n";
    echo "   - 📁 " . $outputFileFull . "\n";
    echo "   - 📁 " . $outputFileMin . "\n";
    echo "   - 📊 Catégories : " . count($index) . " — Total éléments : " . $totalEntries . "\n";
}
