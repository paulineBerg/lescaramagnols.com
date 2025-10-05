<?php
// ============================================================================
// Les Caramagnols - Initialisation globale (core/bootstrap.php)
// ============================================================================
/**
 * Ce fichier doit être inclus en tout début de chaque requête PHP.
 * Il prépare les chemins, charge les configurations, la BDD et les fonctions utilitaires.
 */

// 1. Chemin racine du site
define('ROOT_PATH', realpath(__DIR__ . '/../'));

// 2. Chargement du gestionnaire .env
require_once __DIR__ . '/env.php';
load_env(ROOT_PATH . '/.env');

// 3. Chargement de la configuration globale
require_once ROOT_PATH . '/config/config.php';

// 4. Chargement de la base de données si le fichier existe
$dbFile = ROOT_PATH . '/config/db.php';
if (file_exists($dbFile)) {
    require_once $dbFile;
}

// 5. Chargement des traductions et de la fonction t()
require_once ROOT_PATH . '/core/i18n.php';

// 6. Initialisation du mini-routeur
require_once ROOT_PATH . '/core/router.php';
