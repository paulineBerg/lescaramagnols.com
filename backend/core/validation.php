<?php
// backend/core/validation.php

declare(strict_types=1);

/**
 * Normalise les espaces (multi espaces, retours chariot).
 */
function normalize_whitespace(string $value): string
{
    $value = preg_replace('/[ \t]+/', ' ', $value);
    $value = preg_replace('/\R+/', "\n", $value);
    return trim($value);
}

/**
 * Nettoie une chaîne destinée à un champ texte (commentaire, tag, etc.).
 *
 * @param string   $value      Valeur à nettoyer.
 * @param int      $maxLength  Longueur maximale autorisée.
 * @param string[] $allowTags  Liste des balises HTML autorisées.
 */
function sanitize_text_field(string $value, ?int $maxLength = 2000, array $allowTags = []): string
{
    $value = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $value);
    $value = preg_replace('#on[a-z]+="[^"]*"#i', '', $value);
    $value = preg_replace("#on[a-z]+='[^']*'#i", '', $value);
    $value = normalize_whitespace($value);
    if ($maxLength !== null) {
        $value = mb_substr($value, 0, $maxLength);
    }

    if ($allowTags === []) {
        $value = strip_tags($value);
    } else {
        $value = strip_tags($value, '<' . implode('><', $allowTags) . '>');
    }

    return trim($value);
}

/**
 * Valide et nettoie une adresse email.
 */
function sanitize_email(string $email): ?string
{
    $email = strtolower(trim($email));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ?: null;
}

/**
 * Nettoie une liste de tags (ex: ["Simca", "Panhard"]).
 *
 * @return array{0: string[], 1: string[]} Tableau contenant [tags, erreurs].
 */
function sanitize_tags(array $tags, int $maxTags = 25, int $maxLength = 60): array
{
    $clean = [];
    $errors = [];

    foreach ($tags as $raw) {
        $label = sanitize_text_field((string) $raw, $maxLength);
        if ($label === '') {
            continue;
        }
        if (in_array(strtolower($label), array_map('strtolower', $clean), true)) {
            continue;
        }
        $clean[] = $label;
        if (count($clean) === $maxTags) {
            $errors[] = sprintf('Nombre maximal de tags atteint (%d).', $maxTags);
            break;
        }
    }

    return [$clean, $errors];
}

/**
 * Nettoie un commentaire utilisateur.
 *
 * @return array{data: array<string, string|null>, errors: string[]}
 */
function sanitize_comment_payload(array $payload): array
{
    $errors = [];

    $name = sanitize_text_field((string) ($payload['author'] ?? ''), 120);
    $email = sanitize_email((string) ($payload['email'] ?? ''));
    $content = sanitize_text_field((string) ($payload['content'] ?? ''), 2000, ['strong', 'em', 'br']);

    if ($name === '') {
        $errors[] = 'Le nom est obligatoire.';
    }
    if ($email === null) {
        $errors[] = 'Adresse e-mail invalide.';
    }
    if ($content === '') {
        $errors[] = 'Le commentaire est vide.';
    }

    return [
        'data' => [
            'author' => $name,
            'email' => $email,
            'content' => $content,
        ],
        'errors' => $errors,
    ];
}

/**
 * Applique une sanitation récursive sur un tableau de traductions.
 *
 * @param array<string, mixed> $translations
 * @return array<string, mixed>
 */
function sanitize_translation_array(
    array $translations,
    array $allowedTags = [
        'strong',
        'em',
        'b',
        'i',
        'br',
        'ul',
        'ol',
        'li',
        'p',
        'div',
        'span',
        'h1',
        'h2',
        'h3',
        'h4',
        'img',
        'figure',
        'figcaption',
        'table',
        'thead',
        'tbody',
        'tr',
        'td',
        'th',
        'a',
        'blockquote'
    ]
): array
{
    $sanitized = [];

    foreach ($translations as $key => $value) {
        if (is_array($value)) {
            $sanitized[$key] = sanitize_translation_array($value, $allowedTags);
            continue;
        }

        if (is_numeric($value)) {
            $sanitized[$key] = $value;
            continue;
        }

        if (!is_string($value)) {
            $value = (string) $value;
        }

        $clean = sanitize_text_field($value, null, $allowedTags);
        $sanitized[$key] = $clean;
    }

    return $sanitized;
}
