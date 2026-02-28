<?php

// backend/core/auth/admin.php

require_once __DIR__ . '/session.php';

if (!function_exists('app_config')) {
    require_once dirname(__DIR__) . '/env.php';
}

/**
 * Retourne la clef de session utilisee pour l'admin.
 */
function admin_session_key(): string
{
    $key = app_config('admin.session_key', '_admin_user');

    if (!is_string($key) || $key === '') {
        return '_admin_user';
    }

    return $key;
}

/**
 * Indique si un administrateur est authentifie.
 */
function admin_is_authenticated(): bool
{
    $key = admin_session_key();

    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        return false;
    }

    $sessionEmail = $_SESSION[$key]['email'] ?? null;
    $expectedEmail = strtolower((string) app_config('admin.email', ''));

    return is_string($sessionEmail) && hash_equals($expectedEmail, $sessionEmail);
}

/**
 * Renvoie les informations de session admin courante.
 */
function admin_current_user(): ?array
{
    return admin_is_authenticated() ? $_SESSION[admin_session_key()] : null;
}

/**
 * Authentifie l'admin a partir d'un email/mot de passe.
 */
function admin_login(string $email, string $password): bool
{
    $email = sanitize_email($email) ?? '';
    $expectedEmail = sanitize_email((string) app_config('admin.email', '')) ?? '';
    $hash = app_config('admin.password_hash', null);

    if ($email === '' || $password === '' || $expectedEmail === '' || !is_string($hash) || $hash === '') {
        return false;
    }

    if (!hash_equals($expectedEmail, $email)) {
        return false;
    }

    if (!password_verify($password, $hash)) {
        return false;
    }

    if (function_exists('session_regenerate_id')) {
        session_regenerate_id(true);
    }

    $_SESSION[admin_session_key()] = [
        'email' => $expectedEmail,
        'login_at' => time(),
    ];

    return true;
}

/**
 * Deconnecte l'admin courant.
 */
function admin_logout(): void
{
    $key = admin_session_key();

    unset($_SESSION[$key]);

    if (function_exists('session_regenerate_id')) {
        session_regenerate_id(true);
    }
}

/**
 * Redirige vers la page de connexion si l'admin n'est pas connecte.
 */
function admin_require_login(string $loginPath = 'index.php'): void
{
    if (admin_is_authenticated()) {
        return;
    }

    header('Location: ' . $loginPath);
    exit;
}

/**
 * Token CSRF simple pour l'espace admin.
 */
function admin_csrf_token(): string
{
    $key = admin_session_key() . '_csrf';

    if (!isset($_SESSION[$key]) || !is_string($_SESSION[$key]) || $_SESSION[$key] === '') {
        $_SESSION[$key] = bin2hex(random_bytes(32));
    }

    return $_SESSION[$key];
}

/**
 * Valide un token CSRF soumis.
 */
function admin_validate_csrf_token(?string $token): bool
{
    $key = admin_session_key() . '_csrf';
    $sessionToken = $_SESSION[$key] ?? '';

    return is_string($token) && is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $token);
}
