<?php
// core/env.php

/**
 * Charge un fichier .env simple (cle=valeur) et expose les valeurs.
 */
function load_env(string $filePath): void
{
    static $cache = [];

    $realPath = realpath($filePath);
    if ($realPath === false || !is_readable($realPath)) {
        return;
    }

    $mtime = @filemtime($realPath) ?: null;
    $cached = $cache[$realPath] ?? null;

    if ($cached !== null && $mtime !== null && $cached['mtime'] >= $mtime) {
        return;
    }

    $lines = file($realPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    $loadedKeys = [];

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

        if (!preg_match('/^[A-Z0-9_]+$/', $key)) {
            continue;
        }

        $value = trim($value, "\"' ");
        $value = preg_replace("/[\r\n]+/", '', $value);

        if (in_array(strtolower($value), ['true', 'false'], true)) {
            $value = strtolower($value) === 'true';
        }

        $loadedKeys[] = $key;

        $shouldOverride = true;
        if ($cached !== null && !in_array($key, $cached['keys'], true)) {
            $shouldOverride = !array_key_exists($key, $_ENV);
        }

        if ($shouldOverride) {
            $_ENV[$key] = $value;
        }

        if ($shouldOverride || !array_key_exists($key, $_SERVER)) {
            $_SERVER[$key] = $value;
        }

        if (!is_bool($value)) {
            putenv(sprintf('%s=%s', $key, $value));
        }
    }

    $cache[$realPath] = [
        'mtime' => $mtime ?? time(),
        'keys' => $loadedKeys,
    ];
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
