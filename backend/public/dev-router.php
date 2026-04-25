<?php
// Dev router pour php -S : sert les assets statiques puis délègue au routeur applicatif.
define('CARAMAGNOLS_LOCAL_DEV_ROUTER', true);

$publicDir = realpath(__DIR__);
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

$target = $publicDir . $uri;
if ($uri !== '/' && file_exists($target) && is_file($target)) {
    return false; // laisser le serveur interne servir l'asset
}

// Si on pointe vers un dossier contenant un index.php (ex: espace admin), on le sert directement.
if ($uri !== '/' && file_exists($target) && is_dir($target)) {
    $indexFile = rtrim($target, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.php';
    if (file_exists($indexFile)) {
        require $indexFile;
        return true;
    }
}

require __DIR__ . '/index.php';
