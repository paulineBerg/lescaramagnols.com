<?php
// backend/core/security.php

declare(strict_types=1);

function ensure_session_started(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $secure = request_is_secure();

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.cookie_secure', $secure ? '1' : '0');

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

        session_cache_limiter('nocache');
        session_start();
    }
}

function request_is_secure(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }

    if (!request_trust_proxy_headers()) {
        return false;
    }

    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));

    return $forwardedProto === 'https';
}

function request_trust_proxy_headers(): bool
{
    $rawHost = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '')));
    if ($rawHost !== '' && preg_match('/^\[(.+)\](?::\d+)?$/', $rawHost, $matches) === 1) {
        $rawHost = strtolower(trim((string) $matches[1]));
    } elseif ($rawHost !== '' && substr_count($rawHost, ':') === 1) {
        [$candidateHost, $candidatePort] = array_pad(explode(':', $rawHost, 2), 2, '');
        if ($candidateHost !== '' && ctype_digit($candidatePort)) {
            $rawHost = strtolower(trim($candidateHost));
        }
    }

    $defaultLocalTrust = in_array($rawHost, ['localhost', '127.0.0.1', '::1'], true)
        && env_bool('FORCE_HTTPS_ON_LOCALHOST', false);

    return env_bool(
        'TRUST_PROXY_HEADERS',
        env_bool('ADMIN_TRUST_PROXY_HEADERS', $defaultLocalTrust)
    );
}

function request_host_value(): string
{
    if (request_trust_proxy_headers()) {
        $forwardedHost = trim((string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''));
        if ($forwardedHost !== '') {
            $parts = preg_split('/\s*,\s*/', $forwardedHost, -1, PREG_SPLIT_NO_EMPTY);
            $candidate = is_array($parts) && isset($parts[0]) ? trim((string) $parts[0]) : '';

            if ($candidate !== '' && preg_match('/[\r\n]/', $candidate) !== 1) {
                return $candidate;
            }
        }
    }

    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    if ($host === '' || preg_match('/[\r\n]/', $host) === 1) {
        return '';
    }

    return $host;
}

function request_host_without_port(): string
{
    $host = request_host_value();
    if ($host === '') {
        return '';
    }

    if (preg_match('/^\[(.+)\](?::\d+)?$/', $host, $matches) === 1) {
        return strtolower(trim($matches[1]));
    }

    if (substr_count($host, ':') === 1) {
        [$candidateHost, $candidatePort] = array_pad(explode(':', $host, 2), 2, '');
        if ($candidateHost !== '' && ctype_digit($candidatePort)) {
            return strtolower(trim($candidateHost));
        }
    }

    return strtolower($host);
}

function request_is_localhost_host(): bool
{
    $host = request_host_without_port();

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function env_bool(string $key, bool $default = false): bool
{
    $value = env($key, $default ? '1' : '0');
    if (is_bool($value)) {
        return $value;
    }

    $normalized = strtolower(trim((string) $value));
    if ($normalized === '') {
        return $default;
    }

    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function enforce_https_redirect_if_needed(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    if (headers_sent() || request_is_secure()) {
        return;
    }

    $forceHttps = env_bool('FORCE_HTTPS', strtolower((string) env('APP_ENV', 'development')) === 'production');
    if (!$forceHttps) {
        return;
    }

    if (request_is_localhost_host() && !env_bool('FORCE_HTTPS_ON_LOCALHOST', false)) {
        return;
    }

    $excludedHosts = preg_split('/[\s,;]+/', (string) env('FORCE_HTTPS_EXCLUDED_HOSTS', ''), -1, PREG_SPLIT_NO_EMPTY);
    $excludedHosts = is_array($excludedHosts)
        ? array_map(static fn (string $host): string => strtolower(trim($host)), $excludedHosts)
        : [];
    $currentHost = request_host_without_port();
    if ($currentHost !== '' && in_array($currentHost, $excludedHosts, true)) {
        return;
    }

    $host = request_host_value();
    if ($host === '') {
        return;
    }

    $forcedPortRaw = trim((string) env('FORCE_HTTPS_PORT', ''));
    if ($forcedPortRaw !== '') {
        $forcedPort = filter_var($forcedPortRaw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);
        if ($forcedPort !== false) {
            $hostWithoutPort = request_host_without_port();
            if ($hostWithoutPort !== '') {
                $isIpv6Host = str_contains($hostWithoutPort, ':');
                $host = $isIpv6Host ? '[' . $hostWithoutPort . ']' : $hostWithoutPort;
                if ((int) $forcedPort !== 443) {
                    $host .= ':' . (int) $forcedPort;
                }
            }
        }
    }

    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $requestUri = $requestUri !== '' ? $requestUri : '/';

    header('Location: https://' . $host . $requestUri, true, 308);
    exit;
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
    header('Referrer-Policy: strict-origin-when-cross-origin', false);
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()', false);
    header('Cross-Origin-Opener-Policy: same-origin', false);
    header('Cross-Origin-Resource-Policy: same-site', false);
    header('Origin-Agent-Cluster: ?1', false);

    // CSP modernisée avec nonce : injecté dans $GLOBALS['csp_nonce'] côté layout
    $nonce = bin2hex(random_bytes(12));
    $GLOBALS['csp_nonce'] = $nonce;

    $devOrigins = [];
    if (defined('APP_ENV') && APP_ENV !== 'production') {
        // Autorise Vite dev server pendant le développement
        $devOrigins = ["http://127.0.0.1:5173", "http://localhost:5173"];
    }

    $scriptThirdParty = [];
    $connectThirdParty = [];
    $configuredConsentServices = app_config('site.tarteaucitron.services', []);
    $configuredConsentServices = is_array($configuredConsentServices) ? $configuredConsentServices : [];
    $normalizedConsentServices = array_map(
        static fn ($service): string => strtolower(trim((string) $service)),
        $configuredConsentServices
    );

    if (in_array('googletagmanager', $normalizedConsentServices, true)) {
        // Autorise le loader GTM/gtag une fois le consentement donné via tarteaucitron.
        $scriptThirdParty[] = 'https://www.googletagmanager.com';
        $connectThirdParty[] = 'https://www.googletagmanager.com';
        $connectThirdParty[] = 'https://www.google-analytics.com';
        $connectThirdParty[] = 'https://region1.google-analytics.com';
        $connectThirdParty[] = 'https://www.googleadservices.com';
    }

    $scriptSrc = array_values(array_unique(array_merge(["'self'", "'nonce-{$nonce}'"], $devOrigins, $scriptThirdParty)));
    $styleSrc  = array_values(array_unique(array_merge(["'self'", "'unsafe-inline'"], $devOrigins)));
    $connectSrc = array_values(array_unique(array_merge(["'self'"], $devOrigins, $connectThirdParty)));

    $csp = sprintf(
        "default-src 'self'; script-src %s; style-src %s; img-src 'self' data: https: http:; connect-src %s; font-src 'self'; frame-src 'self' https://www.youtube-nocookie.com; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none';",
        implode(' ', $scriptSrc),
        implode(' ', $styleSrc),
        implode(' ', $connectSrc)
    );

    header('Content-Security-Policy: ' . $csp, false);

    $secure = request_is_secure();
    if ($secure) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload', false);
    }
}
