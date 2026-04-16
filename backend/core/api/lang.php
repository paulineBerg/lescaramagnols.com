<?php
// backend/core/api/lang.php
// Endpoint JSON pour fournir les traductions au frontend

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use Caramagnols\Http\Response;

/**
 * Construit la réponse HTTP JSON de traduction.
 */
function lang_api_response(?string $requestedLang = null, ?string $ifNoneMatch = null): Response
{
    $availableLangs = ['fr', 'en', 'de'];

    $lang = strtolower((string) ($requestedLang ?? DEFAULT_LANG));
    if (!in_array($lang, $availableLangs, true)) {
        $lang = DEFAULT_LANG;
    }

    $langFile = translation_file_path($lang);
    $etag = 'W/"' . (filemtime($langFile) ?: time()) . '"';
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

        $json = json_encode(
            $translations,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

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
    $response = lang_api_response(
        is_string($_GET['lang'] ?? null) ? $_GET['lang'] : null,
        is_string($_SERVER['HTTP_IF_NONE_MATCH'] ?? null) ? $_SERVER['HTTP_IF_NONE_MATCH'] : null
    );

    $response->send();
}

if (!defined('LANG_API_AS_FUNCTION')) {
    handle_lang_api();
    exit;
}
