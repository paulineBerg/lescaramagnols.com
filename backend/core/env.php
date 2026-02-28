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

        if ($key === '') {
            continue;
        }

        // Refuse les clefs avec caracteres non supportes (evite l injection)
        if (!preg_match('/^[A-Z0-9_]+$/', $key)) {
            continue;
        }

        // Nettoyage des guillemets et des retours a la ligne
        $value = trim($value, "\"' ");
        $value = preg_replace("/[\r\n]+/", '', $value);

        // Normalisation basique des booleens
        if (in_array(strtolower($value), ['true', 'false'], true)) {
            $value = strtolower($value) === 'true';
        }

        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
        if (!array_key_exists($key, $_SERVER)) {
            $_SERVER[$key] = $value;
        }
        if (!is_bool($value)) {
            putenv(sprintf('%s=%s', $key, $value));
        }
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
 * Valide la presence de variables d'environnement critiques.
 *
 * @throws RuntimeException si une ou plusieurs variables manquent.
 */
function require_env(array $keys, string $context = 'application'): void
{
    $missing = [];

    foreach ($keys as $key) {
        $value = env($key, null);
        if ($value === null || $value === '') {
            $missing[] = $key;
        }
    }

    if ($missing !== []) {
        $message = sprintf(
            'Missing required environment variables (%s) for %s.',
            implode(', ', $missing),
            $context
        );

        throw new RuntimeException($message);
    }
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
