<?php
// core/tools/generate_search_index.php

define('ROOT_PATH', dirname(__DIR__, 2));
define('LANG_PATH', ROOT_PATH . '/lang/fr.php');
define('TEMPLATE_PATH', ROOT_PATH . '/templates/pages/');
define('OUTPUT_FILE_FULL', ROOT_PATH . '/data/search_index.json');
define('OUTPUT_FILE_MIN', ROOT_PATH . '/data/search_index.min.json');

$lang = require LANG_PATH;
$index = [];

// ❌ Exclure ces fichiers
$excludeFiles = ['404.php', 'search.php', 'index.php', 'test.php'];

// ✅ Fonction pour exclure les blocs "menu UI"
function isMenuUIBlock(string $blocContent): bool {
    return (
        preg_match('/t\(["\']MENU_UI_/i', $blocContent) ||
        preg_match('#/assets/images/structure/menu/#i', $blocContent) ||
        preg_match('/id=["\']menurectanglewindows/i', $blocContent) ||
        preg_match('/id=["\']boutonrectangle/i', $blocContent)
    );
}

// 🔁 Parcours récursif des fichiers
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(TEMPLATE_PATH));

foreach ($rii as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') continue;

    $filepath = $file->getPathname();
    $relativePath = str_replace(TEMPLATE_PATH, '', $filepath);
    if (in_array(basename($relativePath), $excludeFiles)) continue;

    $url = '/' . str_replace('.php', '', str_replace('\\', '/', $relativePath));
    $parts = explode('/', trim($relativePath, '/'));
    $categorie = $parts[0] ?? 'inconnu';

    $content = file_get_contents($filepath);

    // 🔍 Extraire tous les blocs de type $blocks['EditRegionX'] = '...';
    preg_match_all('/\$blocks\[\'(EditRegion\d{1,2})\'\]\s*=\s*\'(.*?)\';/s', $content, $matches, PREG_SET_ORDER);

    $titre = '';
    $texteTotal = '';
    $image = null;
    $blocs_utilises = [];

    foreach ($matches as $match) {
        $blocId = $match[1];
        $blocContent = $match[2];

        // ⛔ Ignore les blocs de type menu UI
        if (isMenuUIBlock($blocContent)) continue;

        $blocs_utilises[] = $blocId;

        // 🔍 Cherche les traductions t("...")
        if (preg_match_all('/t\([\'"]([^\'"]+)[\'"]\)/', $blocContent, $tMatches)) {
            foreach ($tMatches[1] as $langKey) {
                if (!isset($lang[$langKey])) continue;

                $value = strip_tags($lang[$langKey]);

                if (stripos($langKey, 'TITRE') !== false && !$titre) {
                    $titre = $value;
                } else {
                    $texteTotal .= ' ' . $value;
                }
            }
        }

        // 📷 Cherche une image directe dans le bloc
        if (!$image && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $blocContent, $imgMatch)) {
            $image = $imgMatch[1];
        }
    }

    if ($titre || $texteTotal) {
        $index[$categorie][] = [
            'titre' => $titre ?: '(Sans titre)',
            'contenu' => mb_substr(trim($texteTotal), 0, 300) . '...',
            'url' => $url,
            'image' => $image ?? null,
            'blocs_utilises' => array_unique($blocs_utilises),
        ];
    }
}

// 🔠 Tri alphabétique
foreach ($index as &$entries) {
    usort($entries, fn($a, $b) => strcasecmp($a['titre'], $b['titre']));
}

// 📁 Dossier de sortie
$dataPath = dirname(OUTPUT_FILE_FULL);
if (!is_dir($dataPath)) mkdir($dataPath, 0755, true);

// 💾 Écriture des fichiers
file_put_contents(OUTPUT_FILE_FULL, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents(OUTPUT_FILE_MIN, json_encode($index, JSON_UNESCAPED_UNICODE));

// ✅ Résumé
echo "✅ Index généré avec exclusion des menus UI\n";
echo "📁 " . OUTPUT_FILE_FULL . "\n";
echo "📁 " . OUTPUT_FILE_MIN . "\n";
echo "📊 Catégories : " . count($index) . " — Total éléments : " . count($index, COUNT_RECURSIVE) . "\n";
