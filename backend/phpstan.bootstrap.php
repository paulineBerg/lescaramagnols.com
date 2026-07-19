<?php
// Bootstrap pour PHPStan : définit ROOT_PATH et charge l'autoload Composer.
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}

$phpstanRuntimeDirectory = __DIR__ . '/var/phpstan';
if (!is_dir($phpstanRuntimeDirectory)) {
    @mkdir($phpstanRuntimeDirectory, 0775, true);
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

require_once __DIR__ . '/core/env.php';
