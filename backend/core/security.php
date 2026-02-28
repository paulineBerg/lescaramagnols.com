<?php
// backend/core/security.php

declare(strict_types=1);

function ensure_session_started(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $cookieParams = [
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ];

        session_set_cookie_params($cookieParams);

        if (session_name() === 'PHPSESSID') {
            session_name('caramagnols_session');
        }

        session_start();
    }
}

function csrf_token(string $scope = 'default'): string
{
    ensure_session_started();

    $key = sprintf('_csrf_%s', $scope);
    if (!isset($_SESSION[$key]) || !is_string($_SESSION[$key]) || $_SESSION[$key] === '') {
        $_SESSION[$key] = bin2hex(random_bytes(32));
    }

    return $_SESSION[$key];
}

/**
 * Valide un token CSRF soumis et, optionnellement, le régénère.
 */
function csrf_validate(?string $token, string $scope = 'default', bool $rotate = false): bool
{
    ensure_session_started();

    $key = sprintf('_csrf_%s', $scope);
    $sessionToken = $_SESSION[$key] ?? '';

    $isValid = is_string($token) && is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $token);

    if ($isValid && $rotate) {
        unset($_SESSION[$key]);
    }

    return $isValid;
}

function apply_security_headers(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    if (headers_sent()) {
        return;
    }

    header_remove('X-Powered-By');

    header('X-Frame-Options: SAMEORIGIN', false);
    header('X-Content-Type-Options: nosniff', false);
    header('Referrer-Policy: no-referrer-when-downgrade', false);
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()', false);

    // CSP modernisée avec nonce : injecté dans $GLOBALS['csp_nonce'] côté layout
    $nonce = bin2hex(random_bytes(12));
    $GLOBALS['csp_nonce'] = $nonce;

    $devOrigins = [];
    if (defined('APP_ENV') && APP_ENV !== 'production') {
        // Autorise Vite dev server pendant le développement
        $devOrigins = ["http://127.0.0.1:5173", "http://localhost:5173"];
    }

    $scriptSrc = array_merge(["'self'", "'nonce-{$nonce}'"], $devOrigins);
    $styleSrc  = array_merge(["'self'", "'unsafe-inline'"], $devOrigins);
    $connectSrc = array_merge(["'self'"], $devOrigins);

    $csp = sprintf(
        "default-src 'self'; script-src %s; style-src %s; img-src 'self' data: https: http:; connect-src %s; font-src 'self'; frame-ancestors 'none';",
        implode(' ', $scriptSrc),
        implode(' ', $styleSrc),
        implode(' ', $connectSrc)
    );

    header('Content-Security-Policy: ' . $csp, false);

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if ($secure) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload', false);
    }
}
