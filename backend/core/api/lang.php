<?php
// backend/core/api/lang.php
// Endpoint JSON pour fournir les traductions au frontend

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * Traite la requête de traduction et écrit la réponse.
 * Séparée pour être testable en CLI (définir LANG_API_AS_FUNCTION).
 */
function handle_lang_api(): void
{
    // Liste restreinte des langues supportées par le site
    $availableLangs = ['fr', 'en', 'de'];

    $lang = $_GET['lang'] ?? DEFAULT_LANG;
    $lang = strtolower($lang);
    if (!in_array($lang, $availableLangs, true)) {
        $lang = DEFAULT_LANG;
    }

    $langFile = ROOT_PATH . '/lang/' . $lang . '.php';
    if (!file_exists($langFile)) {
        $langFile = ROOT_PATH . '/lang/' . DEFAULT_LANG . '.php';
    }

    // ETag basé sur le mtime du fichier de langue courant
    $etag = 'W/"' . (filemtime($langFile) ?: time()) . '"';
    header('ETag: ' . $etag);
    header('Cache-Control: public, max-age=300');
    header('Content-Type: application/json; charset=utf-8');

    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        http_response_code(304);
        return;
    }

    try {
        $translations = load_translations_cached($lang);

        if (!is_array($translations)) {
            throw new RuntimeException('Traductions introuvables');
        }

        echo json_encode(
            $translations,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    } catch (Throwable $exception) {
        http_response_code(500);

        error_log('[lang.php] ' . $exception->getMessage());

        echo json_encode([
            'error' => 'Unable to load translations.'
        ]);
    }
}

if (!defined('LANG_API_AS_FUNCTION')) {
    handle_lang_api();
    exit;
}
