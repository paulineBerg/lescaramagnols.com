<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit etre executee en CLI.\n");
    exit(1);
}

$options = parse_cli_options(array_slice($argv, 1));

if (isset($options['help']) || isset($options['h'])) {
    fwrite(STDOUT, prod_tree_usage());
    exit(0);
}

$backendRoot = resolve_cli_path((string) ($options['root'] ?? dirname(__DIR__, 2)));
$clean = isset($options['clean']);
$dryRun = isset($options['dry-run']);

if (!is_backend_root($backendRoot)) {
    fwrite(STDERR, sprintf("[prod-tree] Racine backend invalide: %s\n", $backendRoot));
    exit(2);
}

$nonProdPaths = collect_non_prod_paths($backendRoot);

if ($nonProdPaths === []) {
    fwrite(STDOUT, "[prod-tree] OK: aucun fichier non-prod detecte.\n");
    exit(0);
}

if (!$clean) {
    report_paths("[prod-tree] Fichiers non-prod detectes:", $nonProdPaths, STDERR);
    exit(1);
}

if ($dryRun) {
    report_paths("[prod-tree] Fichiers non-prod qui seraient supprimes:", $nonProdPaths, STDOUT);
    exit(1);
}

$removed = 0;
foreach ($nonProdPaths as $relativePath) {
    $absolutePath = $backendRoot . '/' . $relativePath;
    if (remove_path($absolutePath)) {
        ++$removed;
    }
}

$remainingPaths = collect_non_prod_paths($backendRoot);
if ($remainingPaths !== []) {
    report_paths("[prod-tree] Nettoyage incomplet:", $remainingPaths, STDERR);
    exit(1);
}

fwrite(STDOUT, sprintf("[prod-tree] OK: %d chemin(s) non-prod nettoye(s).\n", $removed));
exit(0);

function prod_tree_usage(): string
{
    return <<<USAGE
Usage:
  php core/tools/check_prod_tree.php [--root=.]
  php core/tools/check_prod_tree.php [--root=.] --clean

Description:
  Detecte les fichiers de developpement, test, documentation, backup et temporaire
  qui ne doivent pas rester dans le backend de production.

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

function resolve_cli_path(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        $path = '.';
    }

    if (!str_starts_with($path, '/')) {
        $cwd = getcwd();
        $path = (is_string($cwd) ? $cwd : '.') . '/' . $path;
    }

    return normalize_filesystem_path($path);
}

function normalize_filesystem_path(string $path): string
{
    $segments = [];
    foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }

        if ($segment === '..') {
            array_pop($segments);
            continue;
        }

        $segments[] = $segment;
    }

    return '/' . implode('/', $segments);
}

function is_backend_root(string $backendRoot): bool
{
    return is_dir($backendRoot)
        && is_dir($backendRoot . '/core')
        && is_dir($backendRoot . '/public')
        && is_file($backendRoot . '/public/index.php');
}

/**
 * @return array<int, string>
 */
function collect_non_prod_paths(string $backendRoot): array
{
    $exactPaths = [
        '.env.example',
        '.env.production',
        'tests',
        'docs',
        'phpunit.xml',
        'phpstan.bootstrap.php',
        'phpcs.xml',
        'package.json',
        'package-lock.json',
        'npm-shrinkwrap.json',
        'replace_image_paths.php',
        'public/dev-router.php',
    ];

    $globPatterns = [
        '.env.bak.*',
        'README*',
        'phpstan.neon*',
        'data/*.bak',
        '*.bak',
        '*.old',
        '*.orig',
        '*.tmp',
        '*~',
        '.DS_Store',
        'Thumbs.db',
    ];

    $paths = [];

    foreach ($exactPaths as $relativePath) {
        if (file_exists($backendRoot . '/' . $relativePath)) {
            $paths[] = $relativePath;
        }
    }

    foreach ($globPatterns as $pattern) {
        $matches = glob($backendRoot . '/' . $pattern, GLOB_NOSORT);
        if ($matches === false) {
            continue;
        }

        foreach ($matches as $match) {
            $paths[] = normalize_relative_path($match, $backendRoot);
        }
    }

    $paths = array_values(array_unique(array_filter($paths, static fn (string $path): bool => $path !== '')));
    sort($paths);

    return $paths;
}

function normalize_relative_path(string $absolutePath, string $backendRoot): string
{
    $absolutePath = normalize_filesystem_path($absolutePath);
    $backendRoot = rtrim(normalize_filesystem_path($backendRoot), '/');

    if ($absolutePath === $backendRoot) {
        return '';
    }

    if (!str_starts_with($absolutePath, $backendRoot . '/')) {
        return '';
    }

    return substr($absolutePath, strlen($backendRoot) + 1);
}

/**
 * @param array<int, string> $paths
 * @param resource $stream
 */
function report_paths(string $title, array $paths, $stream): void
{
    fwrite($stream, $title . "\n");
    foreach ($paths as $path) {
        fwrite($stream, sprintf("  - %s\n", $path));
    }
}

function remove_path(string $path): bool
{
    if (!file_exists($path) && !is_link($path)) {
        return false;
    }

    if (is_dir($path) && !is_link($path)) {
        remove_directory($path);
        return true;
    }

    if (!unlink($path)) {
        throw new RuntimeException(sprintf('Suppression impossible: %s', $path));
    }

    return true;
}

function remove_directory(string $directory): void
{
    $items = scandir($directory);
    if ($items === false) {
        throw new RuntimeException(sprintf('Lecture impossible: %s', $directory));
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        remove_path($directory . '/' . $item);
    }

    if (!rmdir($directory)) {
        throw new RuntimeException(sprintf('Suppression impossible: %s', $directory));
    }
}
