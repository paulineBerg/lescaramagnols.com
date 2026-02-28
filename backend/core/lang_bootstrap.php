<?php
// Fichier : core/lang_bootstrap.php

// Liste des langues supportées
$availableLangs = ['fr', 'en', 'de'];

/**
 * Détecte la langue à utiliser :
 * 1. ?lang dans l'URL
 * 1.5. Langue dans l'URL (ex: /en/page)
 * 2. cookie 'lang'
 * 3. HTTP_ACCEPT_LANGUAGE
 * 4. fallback 'fr'
 */
function detectLang(array $available): string {
    // 1. Paramètre GET
    if (isset($_GET['lang']) && in_array($_GET['lang'], $available, true)) {
        // Créer ou mettre à jour le cookie
        setcookie('lang', $_GET['lang'], [
            'expires' => time() + 365 * 24 * 3600,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
        return $_GET['lang'];
    }

    // 1.5. Langue dans l'URL
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments = explode('/', trim($uri, '/'));
    if (isset($segments[0]) && in_array($segments[0], $available, true)) {
        $lang = $segments[0];
        setcookie('lang', $lang, [
            'expires' => time() + 365 * 24 * 3600,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
        return $lang;
    }

    // 2. Cookie
    if (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], $available, true)) {
        return $_COOKIE['lang'];
    }

    // 3. Langue navigateur
    $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    foreach (explode(',', $acceptLang) as $lang) {
        $code = substr(trim($lang), 0, 2);
        if (in_array($code, $available, true)) {
            return $code;
        }
    }

    // 4. Fallback
    return 'fr';
}

// Appliquer la langue
$lang = detectLang($availableLangs);
if (!defined('CURRENT_LANG')) {
    define('CURRENT_LANG', $lang);
}

// Charger les traductions
$langTranslations = load_translations_cached($lang);
