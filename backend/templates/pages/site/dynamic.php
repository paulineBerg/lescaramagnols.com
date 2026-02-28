<?php
// backend/templates/pages/site/dynamic.php
// Page dynamique rendue depuis backend/data/pages.json

// Récupère la page préchargée par resolve_route (évite une double lecture disque)
$page = $GLOBALS['currentDynamicPage'] ?? null;

// Par sécurité, possibilité de recharger si non préalablement défini
if ($page === null && function_exists('get_page_by_slug')) {
    $slug = $_GET['slug'] ?? null; // non documenté, simple fallback
    $lang = defined('CURRENT_LANG') ? CURRENT_LANG : (defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr');
    if ($slug) {
        $page = get_page_by_slug($slug, $lang);
    }
}

if ($page === null) {
    // Si pas trouvé, on renvoie vers la 404 habituelle
    http_response_code(404);
    include TEMPLATES_PATH . '/pages/404.php';
    return;
}

$pageTitle = $page['title'] ?? 'Les Caramagnols';
$blocks = $page['blocks'] ?? [];

// Balises META optionnelles
if (!empty($page['meta']['description'])) {
    $blocks['EditRegion10'] = '<meta name="description" content="' . htmlspecialchars((string) $page['meta']['description'], ENT_QUOTES, 'UTF-8') . '">';
}

// Rend le layout standard
require TEMPLATES_PATH . '/partials/layout.php';
