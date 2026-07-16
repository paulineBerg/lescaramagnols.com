<?php

declare(strict_types=1);

use Caramagnols\Http\FrontController;
use Caramagnols\Http\Request;

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit être exécutée en CLI.\n");
    exit(1);
}

$options = parse_cli_options(array_slice($argv, 1));
$iterations = max(5, min(200, (int) ($options['iterations'] ?? 30)));
$warmup = max(1, min(20, (int) ($options['warmup'] ?? 3)));
$jsonOutput = isset($options['json']);
$storageOverride = parse_storage_option($options['storage'] ?? null);

if ($storageOverride !== null) {
    apply_storage_override($storageOverride);
}

$routes = parse_routes_option($options['routes'] ?? null);
if ($routes === []) {
    $routes = default_routes();
}

$frontController = FrontController::boot();
$results = [];

foreach ($routes as $route) {
    foreach (range(1, $warmup) as $unusedIndex) {
        $frontController->handle(build_request($route));
    }

    $durations = [];
    $statuses = [];

    foreach (range(1, $iterations) as $unusedIndex) {
        $startedAt = microtime(true);
        $response = $frontController->handle(build_request($route));
        $durations[] = (microtime(true) - $startedAt) * 1000;
        $status = (int) $response->status;
        $statuses[(string) $status] = (int) ($statuses[(string) $status] ?? 0) + 1;
    }

    sort($durations);
    $results[] = [
        'route' => $route,
        'iterations' => $iterations,
        'status' => $statuses,
        'min_ms' => round((float) ($durations[0] ?? 0.0), 2),
        'avg_ms' => round(array_sum($durations) / max(1, count($durations)), 2),
        'p95_ms' => round(percentile($durations, 0.95), 2),
        'max_ms' => round((float) ($durations[count($durations) - 1] ?? 0.0), 2),
    ];
}

$payload = [
    'generated_at' => date('c'),
    'iterations' => $iterations,
    'warmup' => $warmup,
    'results' => $results,
];

if ($jsonOutput) {
    fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
}

fwrite(STDOUT, sprintf("Benchmark routes front (iterations=%d, warmup=%d)\n", $iterations, $warmup));
foreach ($results as $result) {
    fwrite(
        STDOUT,
        sprintf(
            "- %s | avg=%sms | p95=%sms | min=%sms | max=%sms | status=%s\n",
            (string) $result['route'],
            number_format((float) $result['avg_ms'], 2, '.', ''),
            number_format((float) $result['p95_ms'], 2, '.', ''),
            number_format((float) $result['min_ms'], 2, '.', ''),
            number_format((float) $result['max_ms'], 2, '.', ''),
            json_encode($result['status'], JSON_UNESCAPED_SLASHES)
        )
    );
}

exit(0);

/**
 * @param array<int, float> $values
 */
function percentile(array $values, float $ratio): float
{
    if ($values === []) {
        return 0.0;
    }

    $index = (int) ceil($ratio * count($values)) - 1;
    $index = max(0, min(count($values) - 1, $index));

    return (float) $values[$index];
}

/**
 * @param mixed $raw
 * @return array<int, string>
 */
function parse_routes_option(mixed $raw): array
{
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $routes = [];
    foreach (explode(',', $raw) as $candidate) {
        $candidate = trim($candidate);
        if ($candidate === '') {
            continue;
        }

        if (!str_starts_with($candidate, '/')) {
            $candidate = '/' . ltrim($candidate, '/');
        }

        $routes[] = $candidate;
    }

    return array_values(array_unique($routes));
}

/**
 * @return array<int, string>
 */
function default_routes(): array
{
    $defaultLang = (string) app_config('default_lang', 'fr');
    $routes = ['/', '/blog'];
    $firstArticle = blog_repository()->publishedArticles($defaultLang)[0] ?? null;

    if (is_array($firstArticle)) {
        $slug = trim((string) ($firstArticle['slug'] ?? ''));
        if ($slug !== '') {
            $routes[] = '/blog/article/' . rawurlencode($slug);
        }
    }

    return $routes;
}

function build_request(string $route): Request
{
    return new Request(
        [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => $route,
            'REMOTE_ADDR' => '127.0.0.1',
        ],
        [],
        [],
        [],
        ['Host' => '127.0.0.1:8000']
    );
}

/**
 * @param array<int, string> $arguments
 * @return array<string, string|true>
 */
function parse_cli_options(array $arguments): array
{
    $options = [];

    foreach ($arguments as $argument) {
        if (!is_string($argument) || !str_starts_with($argument, '--')) {
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

function parse_storage_option(mixed $raw): ?string
{
    if (!is_string($raw)) {
        return null;
    }

    $value = strtolower(trim($raw));
    if ($value === '') {
        return null;
    }

    if (!in_array($value, ['json', 'sql', 'dual-write'], true)) {
        fwrite(STDERR, sprintf("Valeur --storage invalide: %s (attendu: json|sql|dual-write).\n", $value));
        exit(1);
    }

    return $value;
}

function apply_storage_override(string $mode): void
{
    global $appConfig;

    if (!is_array($appConfig)) {
        return;
    }

    if (!isset($appConfig['editorial']) || !is_array($appConfig['editorial'])) {
        $appConfig['editorial'] = [];
    }
    if (!isset($appConfig['blog']) || !is_array($appConfig['blog'])) {
        $appConfig['blog'] = [];
    }

    $appConfig['editorial']['storage'] = $mode;
    $appConfig['blog']['storage'] = $mode;
}
