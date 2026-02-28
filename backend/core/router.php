<?php
// core/router.php

require_once ROOT_PATH . '/core/content/pages_loader.php';

function resolve_route(string $uri): string {
    // On s'assure de ne pas réutiliser un contexte de page précédent
    unset($GLOBALS['currentDynamicPage']);

    // 🔍 Nettoyage de l'URI
    $uri = parse_url($uri, PHP_URL_PATH);
    $uri = trim($uri, '/');
    $segments = explode('/', $uri);

    // 🌐 Si la langue est dans l'URL, on la retire pour le routing.
    // La détection complète (GET, URL, cookie, navigateur) est déjà faite dans lang_bootstrap.php.
    if (isset($segments[0]) && in_array($segments[0], ['fr', 'en', 'de'])) {
        array_shift($segments);
    }

    // 🧭 Route nettoyée sans le préfixe de langue
    $route = implode('/', $segments);

    // 🏠 Page d'accueil
    if ($route === '' || $route === 'index.php') {
        return 'pages/site/accueil/bienvenue-aux-caramagnols.php';
    }

    // 🔍 Page de recherche
    if (in_array($route, ['search', 'site/search', 'search.php'])) {
        return 'pages/search.php';
    }

    // 📄 Fichier brut sans extension
    $file = TEMPLATES_PATH . '/pages/' . $route;
    if (file_exists($file)) {
        return 'pages/' . $route;
    }

    // 📄 Fichier avec .php
    $filePhp = TEMPLATES_PATH . '/pages/' . $route . '.php';
    if (file_exists($filePhp)) {
        return 'pages/' . $route . '.php';
    }

    // 🌱 Page dynamique depuis pages.json (pattern /site/{slug})
    if (preg_match('#^site/([^/]+)$#', $route, $matches)) {
        $slug = $matches[1];
        $lang = defined('CURRENT_LANG') ? CURRENT_LANG : (defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr');
        // Utilise le chemin override éventuel défini par pages_data_set_path_override() (tests)
        $page = get_page_by_slug($slug, $lang, null, pages_data_path());

        if ($page !== null) {
            // Stocke la page pour le template dynamique afin d'éviter un 2e chargement disque.
            $GLOBALS['currentDynamicPage'] = $page;
            return 'pages/site/dynamic.php';
        }
    }

    // ❌ Page non trouvée
    return 'pages/404.php';
}
