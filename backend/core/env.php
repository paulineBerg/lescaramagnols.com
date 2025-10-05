<?php
// core/env.php

/**
 * Charge un fichier .env simple (cle=valeur) et expose les valeurs.
 */
function load_env(string $filePath): void
{
    static $loaded = [];

    $realPath = realpath($filePath);
    if ($realPath === false || isset($loaded[$realPath])) {
        return;
    }

    if (!is_readable($realPath)) {
        return;
    }

    $lines = file($realPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Nettoyage des guillemets eventuels
        $value = trim($value, "\"'" );

        if ($key === '') {
            continue;
        }

        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
        if (!array_key_exists($key, $_SERVER)) {
            $_SERVER[$key] = $value;
        }
        putenv(sprintf('%s=%s', $key, $value));
    }

    $loaded[$realPath] = true;
}

/**
 * Recupere une valeur d'environnement avec valeur par defaut.
 */
function env(string $key, mixed $default = null): mixed
{
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }
    if (array_key_exists($key, $_SERVER)) {
        return $_SERVER[$key];
    }
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

/**
 * Recupere une configuration applicative depuis $appConfig.
 */
function app_config(?string $path = null, mixed $default = null): mixed
{
    global $appConfig;

    if (!is_array($appConfig)) {
        return $default;
    }

    if ($path === null) {
        return $appConfig;
    }

    $segments = explode('.', $path);
    $cursor = $appConfig;

    foreach ($segments as $segment) {
        if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
            return $default;
        }
        $cursor = $cursor[$segment];
    }

    return $cursor;
}
