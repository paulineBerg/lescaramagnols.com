<?php
// ne pas changer l'ordre de chargement
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', realpath(__DIR__ . '/../'));
}

$appConfig = [
    'env' => env('APP_ENV', 'development'),
    'base_url' => env('BASE_URL', '/'),
    'default_lang' => env('DEFAULT_LANG', 'fr'),
    'database' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => (int) env('DB_PORT', 3306),
        'name' => env('DB_NAME', ''),
        'user' => env('DB_USER', ''),
        'password' => env('DB_PASSWORD', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
    ],
    'mail' => [
        'smtp_host' => env('MAIL_SMTP_HOST', 'localhost'),
        'smtp_port' => (int) env('MAIL_SMTP_PORT', 25),
        'smtp_user' => env('MAIL_SMTP_USER', ''),
        'smtp_password' => env('MAIL_SMTP_PASSWORD', ''),
        'smtp_encryption' => env('MAIL_SMTP_ENCRYPTION', ''),
        'from_address' => env('MAIL_FROM_ADDRESS', 'no-reply@example.com'),
        'from_name' => env('MAIL_FROM_NAME', 'Les Caramagnols'),
    ],
];

define('APP_ENV', $appConfig['env']);
define('BASE_URL', $appConfig['base_url']);
define('DEFAULT_LANG', $appConfig['default_lang']);
define('TEMPLATES_PATH', ROOT_PATH . '/templates');
