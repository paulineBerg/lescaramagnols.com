<?php
// backend/core/api/lang.php
// Endpoint JSON pour fournir les traductions au frontend

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use Caramagnols\Http\Response;

/**
 * Construit la réponse HTTP JSON de traduction.
 */
function lang_api_response(?string $requestedLang = null, ?string $ifNoneMatch = null, ?array $requestedKeys = null): Response
{
    $availableLangs = ['fr', 'en', 'de'];

    $lang = strtolower((string) ($requestedLang ?? DEFAULT_LANG));
    if (!in_array($lang, $availableLangs, true)) {
        $lang = DEFAULT_LANG;
    }

    $langFile = translation_file_path($lang);
    $normalizedRequestedKeys = normalize_requested_translation_keys($requestedKeys);
    $etag = build_lang_api_etag($langFile, $normalizedRequestedKeys);
    $headers = [
        'ETag' => $etag,
        'Cache-Control' => 'public, max-age=300',
        'Content-Type' => 'application/json; charset=utf-8',
    ];

    if (is_string($ifNoneMatch) && trim($ifNoneMatch) === $etag) {
        return new Response(304, $headers, '');
    }

    try {
        $translations = load_translations_cached($lang);

        if (!is_array($translations)) {
            throw new RuntimeException('Traductions introuvables');
        }

        if ($normalizedRequestedKeys !== []) {
            $translations = filter_translations_by_keys($translations, $normalizedRequestedKeys);
        }

        $json = json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new Response(200, $headers, is_string($json) ? $json : '{}');
    } catch (Throwable $exception) {
        error_log('[lang.php] ' . $exception->getMessage());

        $json = json_encode(['error' => 'Unable to load translations.']);

        return new Response(500, $headers, is_string($json) ? $json : '{"error":"Unable to load translations."}');
    }
}

/**
 * Traite la requête de traduction et écrit la réponse.
 * Séparée pour être testable en CLI (définir LANG_API_AS_FUNCTION).
 */
function handle_lang_api(): void
{
    $requestedKeys = null;
    if (is_string($_GET['keys'] ?? null)) {
        $requestedKeys = parse_requested_translation_keys((string) $_GET['keys']);
    }

    $response = lang_api_response(
        is_string($_GET['lang'] ?? null) ? $_GET['lang'] : null,
        is_string($_SERVER['HTTP_IF_NONE_MATCH'] ?? null) ? $_SERVER['HTTP_IF_NONE_MATCH'] : null,
        $requestedKeys
    );

    $response->send();
}

/**
 * @return array<int, string>
 */
function parse_requested_translation_keys(string $raw): array
{
    if (trim($raw) === '') {
        return [];
    }

    $parts = preg_split('/\s*,\s*/', $raw) ?: [];

    return normalize_requested_translation_keys($parts);
}

/**
 * @param array<int, string>|null $keys
 * @return array<int, string>
 */
function normalize_requested_translation_keys(?array $keys): array
{
    if ($keys === null) {
        return [];
    }

    $normalized = [];
    foreach ($keys as $key) {
        $clean = trim((string) $key);
        if ($clean === '') {
            continue;
        }

        if (preg_match('/^[A-Za-z0-9_.:-]{1,120}$/', $clean) !== 1) {
            continue;
        }

        $normalized[$clean] = $clean;
        if (count($normalized) >= 250) {
            break;
        }
    }

    return array_values($normalized);
}

/**
 * @param array<string, mixed> $translations
 * @param array<int, string> $keys
 * @return array<string, string>
 */
function filter_translations_by_keys(array $translations, array $keys): array
{
    $filtered = [];
    foreach ($keys as $key) {
        if (!array_key_exists($key, $translations) || !is_string($translations[$key])) {
            continue;
        }

        $filtered[$key] = $translations[$key];
    }

    return $filtered;
}

/**
 * @param array<int, string> $requestedKeys
 */
function build_lang_api_etag(string $langFile, array $requestedKeys): string
{
    $timestamp = (string) ((int) (filemtime($langFile) ?: time()));
    if ($requestedKeys === []) {
        return 'W/"' . $timestamp . '"';
    }

    $variantHash = substr(sha1(implode('|', $requestedKeys)), 0, 12);

    return 'W/"' . $timestamp . '-' . $variantHash . '"';
}

if (!defined('LANG_API_AS_FUNCTION')) {
    handle_lang_api();
    exit;
}
