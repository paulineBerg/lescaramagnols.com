<?php
// core/i18n.php

/**
 * Charge un fichier de traduction et le met en cache (en memoire par requete)
 * en surveillant sa date de modification pour invalider automatiquement
 * la lecture si le fichier change.
 */
function load_translations_cached(string $lang): array {
    static $cache = [];

    $fallback = ROOT_PATH . '/lang/fr.php';
    $target = ROOT_PATH . '/lang/' . $lang . '.php';
    if (!file_exists($target)) {
        $target = $fallback;
    }

    $mtime = @filemtime($target) ?: null;

    if (isset($cache[$target]) && $cache[$target]['mtime'] === $mtime) {
        return $cache[$target]['data'];
    }

    $data = require $target;
    $cache[$target] = [
        'mtime' => $mtime,
        'data'  => is_array($data) ? $data : []
    ];

    return $cache[$target]['data'];
}

// Charge les traductions si non encore chargees
if (!isset($langTranslations)) {
    $lang = defined('CURRENT_LANG') ? CURRENT_LANG : (defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr');
    $langTranslations = load_translations_cached($lang);
}

// Fonction de traduction PHP
function t(string $key): string {
    global $langTranslations;
    return $langTranslations[$key] ?? '[[' . $key . ']]';
}
