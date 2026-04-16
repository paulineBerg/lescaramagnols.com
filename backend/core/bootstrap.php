<?php
// file: backend/core/bootstrap.php
// ============================================================================
// Les Caramagnols - Initialisation globale (core/bootstrap.php)
// ============================================================================
/**
 * Ce fichier doit être inclus en tout début de chaque requête PHP.
 * Il prépare les chemins, charge les configurations, la BDD et les fonctions utilitaires.
 */

// 1. Chemin racine du site
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', realpath(__DIR__ . '/../'));
}

// 1bis. Autoload Composer (classes PSR-4, libs externes)
$autoload = ROOT_PATH . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// 2. Chargement du gestionnaire .env
require_once __DIR__ . '/env.php';
load_env(ROOT_PATH . '/.env');

// 2b. Helpers de validation
require_once ROOT_PATH . '/core/validation.php';
require_once ROOT_PATH . '/core/security.php';
enforce_https_redirect_if_needed();

// 3. Chargement de la configuration globale
require_once ROOT_PATH . '/config/config.php';
apply_security_headers();

// 3bis. Verification des variables critiques en production
if (APP_ENV === 'production') {
    $requiredKeys = [
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
        'MAIL_SMTP_HOST',
        'MAIL_FROM_ADDRESS',
    ];

    try {
        require_env($requiredKeys, 'production');
    } catch (RuntimeException $exception) {
        error_log('[bootstrap] ' . $exception->getMessage());

        if (PHP_SAPI === 'cli') {
            throw $exception;
        }

        http_response_code(500);
        exit('Application misconfigured: missing environment variables.');
    }
}

// 4. Chargement de la base de données si le fichier existe
$dbFile = ROOT_PATH . '/config/db.php';
if (file_exists($dbFile)) {
    require_once $dbFile;
}

// 5. Chargement des traductions et de la fonction t()
require_once ROOT_PATH . '/core/i18n.php';
require_once ROOT_PATH . '/core/lang_bootstrap.php';

// 6. Initialisation du mini-routeur
require_once ROOT_PATH . '/core/router.php';
require_once ROOT_PATH . '/core/menu_loader.php';
