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

$runtimeDirectories = [
    ROOT_PATH . '/var',
    ROOT_PATH . '/var/cache',
    ROOT_PATH . '/var/log',
    ROOT_PATH . '/var/phpstan',
    ROOT_PATH . '/var/rate-limits',
];

foreach ($runtimeDirectories as $runtimeDirectory) {
    if (is_dir($runtimeDirectory)) {
        continue;
    }

    @mkdir($runtimeDirectory, 0775, true);
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
require_once ROOT_PATH . '/core/rate_limiter.php';
enforce_https_redirect_if_needed();

// 3. Chargement de la configuration globale
require_once ROOT_PATH . '/config/config.php';
apply_security_headers();

if (PHP_SAPI !== 'cli' && !defined('CARAMAGNOLS_BOOTSTRAP_ERROR_HANDLERS_REGISTERED')) {
    define('CARAMAGNOLS_BOOTSTRAP_ERROR_HANDLERS_REGISTERED', true);

    $bootstrapRequestId = static function (): string {
        static $requestId = null;

        if (is_string($requestId) && $requestId !== '') {
            return $requestId;
        }

        foreach (['HTTP_X_REQUEST_ID', 'HTTP_X_CORRELATION_ID'] as $headerName) {
            $candidate = trim((string) ($_SERVER[$headerName] ?? ''));
            if ($candidate !== '') {
                $requestId = $candidate;

                return $requestId;
            }
        }

        $requestId = bin2hex(random_bytes(12));

        return $requestId;
    };

    $bootstrapAccessContext = static function () use ($bootstrapRequestId): array {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

        return [
            'request_id' => function_exists('app_request_id') && app_request_id() !== '' ? app_request_id() : $bootstrapRequestId(),
            'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            'uri' => $uri,
            'path' => parse_url($uri, PHP_URL_PATH) ?: '/',
            'client_ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            'user_agent' => trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
            'referer' => trim((string) ($_SERVER['HTTP_REFERER'] ?? '')),
        ];
    };

    $logBootstrapFailure = static function (string $event, array $context = []) use ($bootstrapAccessContext): void {
        if (($GLOBALS['caramagnolsBootstrapFailureLogged'] ?? false) === true) {
            return;
        }

        $GLOBALS['caramagnolsBootstrapFailureLogged'] = true;
        $payload = array_merge($bootstrapAccessContext(), $context);

        try {
            if (function_exists('app_event_logger')) {
                app_event_logger()->access($event, $payload, 'error');

                return;
            }
        } catch (\Throwable) {
            // Fallback sur error_log ci-dessous.
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        error_log('[caramagnols] ' . $event . ' ' . ($json !== false ? $json : '{}'));
    };

    set_exception_handler(static function (Throwable $exception) use ($logBootstrapFailure): void {
        $logBootstrapFailure('site.request.fatal', [
            'exception' => $exception::class,
            'error' => trim($exception->getMessage()),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
        }

        echo 'Internal Server Error';
    });

    register_shutdown_function(static function () use ($logBootstrapFailure): void {
        if (($GLOBALS['caramagnolsBootstrapFailureLogged'] ?? false) === true) {
            return;
        }

        $error = error_get_last();
        if (!is_array($error)) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int) $error['type'], $fatalTypes, true)) {
            return;
        }

        $logBootstrapFailure('site.request.fatal', [
            'fatal_type' => (int) $error['type'],
            'error' => trim((string) $error['message']),
            'file' => (string) $error['file'],
            'line' => (int) $error['line'],
        ]);
    });
}

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
