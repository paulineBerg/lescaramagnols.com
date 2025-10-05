<?php
// core/router.php

function resolve_route(string $uri): string {
    // 🔍 Nettoyage de l'URI
    $uri = parse_url($uri, PHP_URL_PATH);
    $uri = trim($uri, '/');
    $segments = explode('/', $uri);

    // 🌐 Détection de la langue via URL ou ?lang=xx
    $lang = $_GET['lang'] ?? (in_array($segments[0], ['fr', 'en', 'de']) ? array_shift($segments) : DEFAULT_LANG);
    $_GET['lang'] = $lang;
    define('CURRENT_LANG', $lang);

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

    // ❌ Page non trouvée
    return 'pages/404.php';
}
