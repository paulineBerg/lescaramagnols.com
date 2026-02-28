<?php
// backend/core/api/lang.php
// Endpoint JSON pour fournir les traductions au frontend

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// Liste restreinte des langues supportees par le site
$availableLangs = ['fr', 'en', 'de'];

$lang = $_GET['lang'] ?? DEFAULT_LANG;
$lang = strtolower($lang);

// Nettoyage et validation de la langue demandee
if (!in_array($lang, $availableLangs, true)) {
    $lang = DEFAULT_LANG;
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

exit;