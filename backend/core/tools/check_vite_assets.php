<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit etre executee en CLI.\n");
    exit(1);
}

$options = parse_cli_options(array_slice($argv, 1));

if (isset($options['help']) || isset($options['h'])) {
    fwrite(STDOUT, usage());
    exit(0);
}

$backendRoot = dirname(__DIR__, 2);
$publicRoot = resolve_path((string) ($options['public-root'] ?? 'public'), $backendRoot);
$manifestPath = isset($options['manifest'])
    ? resolve_path((string) $options['manifest'], $backendRoot)
    : $publicRoot . '/.vite/manifest.json';

if (!is_dir($publicRoot)) {
    fwrite(STDERR, sprintf("[vite-assets] Dossier public introuvable: %s\n", $publicRoot));
    exit(2);
}

if (!is_file($manifestPath)) {
    fwrite(STDERR, sprintf("[vite-assets] Manifest Vite introuvable: %s\n", $manifestPath));
    exit(2);
}

$manifestJson = file_get_contents($manifestPath);
if ($manifestJson === false) {
    fwrite(STDERR, sprintf("[vite-assets] Lecture impossible: %s\n", $manifestPath));
    exit(2);
}

try {
    $manifest = json_decode($manifestJson, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    fwrite(STDERR, sprintf("[vite-assets] Manifest JSON invalide: %s\n", $exception->getMessage()));
    exit(2);
}

if (!is_array($manifest)) {
    fwrite(STDERR, "[vite-assets] Le manifest Vite doit contenir un objet JSON.\n");
    exit(2);
}

$issues = [];
$assetPaths = collect_manifest_asset_paths($manifest, $issues);
$missing = [];

foreach ($assetPaths as $assetPath) {
    if (!is_safe_public_asset_path($assetPath)) {
        $issues[] = sprintf("Chemin invalide dans le manifest: %s", $assetPath);
        continue;
    }

    $absolutePath = $publicRoot . '/' . $assetPath;
    if (!is_file($absolutePath)) {
        $missing[] = $assetPath;
    }
}

if ($issues !== [] || $missing !== []) {
    fwrite(STDERR, "[vite-assets] Verification echouee.\n");

    foreach ($issues as $issue) {
        fwrite(STDERR, sprintf("  - %s\n", $issue));
    }

    if ($missing !== []) {
        fwrite(STDERR, "  Fichiers manquants:\n");
        foreach ($missing as $assetPath) {
            fwrite(STDERR, sprintf("  - public/%s\n", $assetPath));
        }
    }

    exit(1);
}

fwrite(
    STDOUT,
    sprintf("[vite-assets] OK: %d fichier(s) reference(s) par le manifest sont presents.\n", count($assetPaths))
);

exit(0);

function usage(): string
{
    return <<<USAGE
Usage:
  php core/tools/check_vite_assets.php [--public-root=public] [--manifest=public/.vite/manifest.json]

Description:
  Verifie que tous les fichiers references par le manifest Vite existent dans le dossier public.

USAGE;
}

/**
 * @param array<int, string> $arguments
 * @return array<string, string|true>
 */
function parse_cli_options(array $arguments): array
{
    $options = [];

    foreach ($arguments as $argument) {
        if (!str_starts_with($argument, '--')) {
            continue;
        }

        $parts = explode('=', substr($argument, 2), 2);
        if (!isset($parts[1])) {
            $options[$parts[0]] = true;
            continue;
        }

        $options[$parts[0]] = $parts[1];
    }

    return $options;
}

function resolve_path(string $path, string $backendRoot): string
{
    $path = trim($path);
    if ($path === '') {
        return $backendRoot;
    }

    if (str_starts_with($path, '/')) {
        return normalize_filesystem_path($path);
    }

    $cwd = getcwd();
    if (is_string($cwd)) {
        $cwdCandidate = normalize_filesystem_path($cwd . '/' . $path);
        if (file_exists($cwdCandidate)) {
            return $cwdCandidate;
        }
    }

    return normalize_filesystem_path($backendRoot . '/' . $path);
}

function normalize_filesystem_path(string $path): string
{
    return rtrim(str_replace('\\', '/', $path), '/');
}

/**
 * @param array<mixed> $manifest
 * @param array<int, string> $issues
 * @return array<int, string>
 */
function collect_manifest_asset_paths(array $manifest, array &$issues): array
{
    $paths = [];

    foreach ($manifest as $entryKey => $entry) {
        if (!is_array($entry)) {
            $issues[] = sprintf("Entree manifest invalide pour %s", (string) $entryKey);
            continue;
        }

        collect_string_field($entry, 'file', (string) $entryKey, $paths, $issues);
        collect_string_list_field($entry, 'css', (string) $entryKey, $paths, $issues);
        collect_string_list_field($entry, 'assets', (string) $entryKey, $paths, $issues);
    }

    $paths = array_values(array_unique($paths));
    sort($paths);

    return $paths;
}

/**
 * @param array<mixed> $entry
 * @param array<int, string> $paths
 * @param array<int, string> $issues
 */
function collect_string_field(array $entry, string $field, string $entryKey, array &$paths, array &$issues): void
{
    if (!array_key_exists($field, $entry)) {
        return;
    }

    if (!is_string($entry[$field]) || trim($entry[$field]) === '') {
        $issues[] = sprintf("Champ `%s` invalide pour %s", $field, $entryKey);
        return;
    }

    $paths[] = normalize_manifest_asset_path($entry[$field]);
}

/**
 * @param array<mixed> $entry
 * @param array<int, string> $paths
 * @param array<int, string> $issues
 */
function collect_string_list_field(array $entry, string $field, string $entryKey, array &$paths, array &$issues): void
{
    if (!array_key_exists($field, $entry)) {
        return;
    }

    if (!is_array($entry[$field])) {
        $issues[] = sprintf("Champ `%s` invalide pour %s", $field, $entryKey);
        return;
    }

    foreach ($entry[$field] as $assetPath) {
        if (!is_string($assetPath) || trim($assetPath) === '') {
            $issues[] = sprintf("Valeur `%s` invalide pour %s", $field, $entryKey);
            continue;
        }

        $paths[] = normalize_manifest_asset_path($assetPath);
    }
}

function normalize_manifest_asset_path(string $path): string
{
    return trim(str_replace('\\', '/', $path));
}

function is_safe_public_asset_path(string $path): bool
{
    if ($path === '' || str_starts_with($path, '/') || preg_match('#^[a-z][a-z0-9+.-]*:#i', $path) === 1) {
        return false;
    }

    $segments = explode('/', $path);
    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return false;
        }
    }

    return true;
}
