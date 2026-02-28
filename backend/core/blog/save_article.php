<?php
// backend/core/blog/save_article.php
// Point d'entrée JSON pour enregistrer un article depuis l'espace admin.

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/rate_limiter.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée. Utilisez POST.']);
    exit;
}

$limiter = new SessionRateLimiter('comments:save_article', 10, 120);
if (!$limiter->allow()) {
    http_response_code(429);
    echo json_encode([
        'error' => 'Trop de requêtes, merci de patienter ' . $limiter->retryAfter() . ' secondes.',
    ]);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload JSON invalide.']);
    exit;
}

$errors = [];

$title = sanitize_text_field((string) ($payload['title'] ?? ''), 180, ['strong', 'em']);
if ($title === '') {
    $errors[] = 'Le titre est obligatoire.';
}

$slug = sanitize_text_field((string) ($payload['slug'] ?? ''), 180);
$slug = strtolower(preg_replace('/[^a-z0-9-]+/i', '-', $slug));
$slug = trim($slug, '-');
if ($slug === '') {
    $errors[] = 'Le slug est obligatoire.';
}

$content = sanitize_text_field((string) ($payload['content'] ?? ''), 20000, ['strong', 'em', 'p', 'ul', 'ol', 'li', 'a', 'blockquote', 'br']);
if ($content === '') {
    $errors[] = 'Le contenu est obligatoire.';
}

[$tags, $tagErrors] = sanitize_tags($payload['tags'] ?? []);
$errors = array_merge($errors, $tagErrors);

$translations = $payload['translations'] ?? [];
if (!is_array($translations)) {
    $errors[] = 'Le bloc "translations" doit être un objet.';
    $translations = [];
}
$translations = sanitize_translation_array($translations);

$comments = [];
if (isset($payload['comments']) && is_array($payload['comments'])) {
    foreach ($payload['comments'] as $commentPayload) {
        $result = sanitize_comment_payload(is_array($commentPayload) ? $commentPayload : []);
        if ($result['errors'] !== []) {
            $errors = array_merge($errors, $result['errors']);
            continue;
        }
        $comments[] = $result['data'];
    }
}

if ($errors !== []) {
    http_response_code(422);
    echo json_encode(['errors' => $errors]);
    exit;
}

$limiter->hit();

$article = [
    'title' => $title,
    'slug' => $slug,
    'content' => $content,
    'tags' => $tags,
    'translations' => $translations,
    'comments' => $comments,
    'updated_at' => date('c'),
];

// Dans une implémentation complète, on persisterait l'article ici (BDD/JSON).
echo json_encode(['data' => $article]);
exit;
