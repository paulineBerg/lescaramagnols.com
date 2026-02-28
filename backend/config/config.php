<?php
// ne pas changer l'ordre de chargement
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', realpath(__DIR__ . '/../'));
}

$databaseDefaults = [
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', 3306),
    'name' => env('DB_NAME', ''),
    'user' => env('DB_USER', ''),
    'password' => env('DB_PASSWORD', ''),
    'charset' => env('DB_CHARSET', 'utf8mb4'),
];

$databaseOverridePath = ROOT_PATH . '/config/database.override.php';
$databaseOverride = [];

if (file_exists($databaseOverridePath)) {
    $override = include $databaseOverridePath;
    if (is_array($override)) {
        $databaseOverride = array_intersect_key($override, $databaseDefaults);
    }
}

$appConfig = [
    'env' => env('APP_ENV', 'development'),
    'base_url' => env('BASE_URL', '/'),
    'default_lang' => env('DEFAULT_LANG', 'fr'),
    'database' => array_merge($databaseDefaults, $databaseOverride),
    'database_prefix' => env('DB_TABLE_PREFIX', 'car_'),
    'mail' => [
        'smtp_host' => env('MAIL_SMTP_HOST', 'localhost'),
        'smtp_port' => (int) env('MAIL_SMTP_PORT', 25),
        'smtp_user' => env('MAIL_SMTP_USER', ''),
        'smtp_password' => env('MAIL_SMTP_PASSWORD', ''),
        'smtp_encryption' => env('MAIL_SMTP_ENCRYPTION', ''),
        'from_address' => env('MAIL_FROM_ADDRESS', 'no-reply@example.com'),
        'from_name' => env('MAIL_FROM_NAME', 'Les Caramagnols'),
    ],
    'admin' => [
        'login_path' => env('ADMIN_LOGIN_PATH', 'adminFtyhik5642sZ'),
        'email' => env('ADMIN_EMAIL', 'pauline@lescaramagnols.com'),
        'password_hash' => env(
            'ADMIN_PASSWORD_HASH',
            '$2y$10$nGij1lrgL7sdDTzAVt.Rt.UZPw3qF8/TWguRFVASVVrM038294rAS'
        ),
        'session_key' => env('ADMIN_SESSION_KEY', '_admin_user'),
    ],
];

define('APP_ENV', $appConfig['env']);
define('BASE_URL', $appConfig['base_url']);
define('DEFAULT_LANG', $appConfig['default_lang']);
define('DB_TABLE_PREFIX', $appConfig['database_prefix']);
define('TEMPLATES_PATH', ROOT_PATH . '/templates');
