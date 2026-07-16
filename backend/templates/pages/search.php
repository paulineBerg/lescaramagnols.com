<?php
// templates/pages/search.php

// 🔧 DEBUG — Affiche toutes les erreurs PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 📁 Définit ROOT_PATH si non défini
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2)); // ← remonte depuis /templates/pages/
}

$lang = defined('CURRENT_LANG') ? CURRENT_LANG : 'fr';
$fallbackIndexFile = ROOT_PATH . '/data/search_index.json';
$indexFile = ROOT_PATH . '/data/search_index_' . $lang . '.json';
if (!file_exists($indexFile)) {
    // Fallback on default index if language-specific file is unavailable.
    $indexFile = $fallbackIndexFile;
}
$query = trim($_GET['q'] ?? '');
$queryEscaped = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');

// 🔧 Initialisation des blocs
$blocks = [];
$blocks['EditRegion1'] = '
<h1>' . t("TXT_TITRERECHERCHE") . '</h1>
<p>' . t("TXT_MOTCLERECHERCHE") . '  <strong>' . $queryEscaped . '</strong></p>
';
$blocks['EditRegion2'] = '';
$blocks['EditRegion4'] = '';
$blocks['EditRegion5'] = '';
$blocks['EditRegion6'] = '';
$blocks['EditRegion7'] = '';
$blocks['EditRegion8'] = '';
$blocks['EditRegion11'] = '';

// 🔍 Lecture et recherche 
$results = [];

if (file_exists($indexFile) && $query !== '') {
    $json = file_get_contents($indexFile);
    $data = json_decode($json, true);

    // If the language-specific index is empty or invalid, fall back to the default index.
    if ((!is_array($data) || empty($data)) && $indexFile !== $fallbackIndexFile && file_exists($fallbackIndexFile)) {
        $json = file_get_contents($fallbackIndexFile);
        $data = json_decode($json, true);
        $indexFile = $fallbackIndexFile;
    }

    if (!is_array($data)) {
        $blocks['EditRegion3'] = '
        <p> ' . t('TXT_ERREURRECHERCHE') . '</p>
        ';
    } else {
        foreach ($data as $categorie => $entries) {
            foreach ($entries as $entry) {
                if (
                    stripos($entry['titre'], $query) !== false ||
                    stripos($entry['contenu'], $query) !== false
                ) {
                    $results[] = $entry;
                }
            }
        }
    }
}

// 🔎 Résultats
if ($query === '') {
    $blocks['EditRegion3'] = '<p>' . t('TXT_ENTRERRECHERCHE') . '</p>';
} elseif (empty($results)) {
    $blocks['EditRegion3'] = '<p>' . t('TXT_RIENRECHERCHE') . ' « ' . $queryEscaped . ' ».</p>';
} else {
    $html = '<ul>';
    foreach ($results as $res) {
        $html .= '<br><li>';
        $html .= '<a href="' . htmlspecialchars($res['url']) . '">';
        $html .= '<strong>' . htmlspecialchars($res['titre']) . '</strong></a><br>';
        if (!empty($res['image'])) {
            $html .= '<img src="' . htmlspecialchars($res['image']) . '" alt="" width="120" height="80" loading="lazy" decoding="async" fetchpriority="low" style="width:120px;height:auto;"><br>';
        }
        $html .= '<small>' . htmlspecialchars($res['contenu']) . '</small>';
        $html .= '</li>';
    }
    $html .= '</ul>';
    $blocks['EditRegion3'] = $html;
}
