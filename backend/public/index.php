<?php
// public/index.php

// ⚙️ Chargement du bootstrap complet, incluant config, langue, routeur, helpers
require_once __DIR__ . '/../core/bootstrap.php';

// 🔀 Résolution de la route demandée
$pageFile = resolve_route($_SERVER['REQUEST_URI']);
$pagePath = TEMPLATES_PATH . '/' . $pageFile;

// 🧠 Détermination du titre de page
if (!file_exists($pagePath)) {
    $pagePath = TEMPLATES_PATH . '/pages/404.php';
    $pageTitle = 'Page introuvable';
} else {
    // Titre par défaut (peut être écrasé dans chaque page avec $pageTitle)
    $pageTitle = ucfirst(str_replace('-', ' ', basename($pageFile, '.php')));
}

// 📦 Rendu du contenu dans une variable
ob_start();
include $pagePath;
$content = ob_get_clean();

// 📄 Affichage via layout principal (qui inclut la langue)
require_once TEMPLATES_PATH . '/partials/layout.php';
