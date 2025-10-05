<?php
// Dev router pour php -S : sert les assets statiques puis délègue au routeur applicatif.
$publicDir = realpath(__DIR__);
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

$target = $publicDir . $uri;
if ($uri !== '/' && file_exists($target) && is_file($target)) {
    return false; // laisser le serveur interne servir l'asset
}

require __DIR__ . '/index.php';
