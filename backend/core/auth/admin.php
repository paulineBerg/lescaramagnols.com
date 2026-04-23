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
    $key = app_config('admin.session_key', 'caramagnols_admin');

    if (!is_string($key) || $key === '') {
        return 'caramagnols_admin';
    }

    return $key;
}

function admin_notice_session_key(): string
{
    return admin_session_key() . '_notice';
}

function admin_flash_session_key(): string
{
    return admin_session_key() . '_flash';
}

function admin_login_failure_session_key(): string
{
    return admin_session_key() . '_auth_failure';
}

function admin_set_notice_code(?string $noticeCode): void
{
    $key = admin_notice_session_key();

    if (!is_string($noticeCode) || trim($noticeCode) === '') {
        unset($_SESSION[$key]);
        return;
    }

    $_SESSION[$key] = trim($noticeCode);
}

function admin_pop_notice_code(): ?string
{
    $key = admin_notice_session_key();
    $noticeCode = $_SESSION[$key] ?? null;
    unset($_SESSION[$key]);

    return is_string($noticeCode) && trim($noticeCode) !== '' ? trim($noticeCode) : null;
}

/**
 * @param 'success'|'error'|null $type
 */
function admin_set_flash_message(?string $type, ?string $message): void
{
    $key = admin_flash_session_key();
    $normalizedType = is_string($type) ? trim($type) : '';
    $normalizedMessage = is_string($message) ? trim($message) : '';

    if (
        $normalizedMessage === ''
        || !in_array($normalizedType, ['success', 'error'], true)
    ) {
        unset($_SESSION[$key]);
        return;
    }

    $_SESSION[$key] = [
        'type' => $normalizedType,
        'message' => $normalizedMessage,
    ];
}

/**
 * @return array{type: 'success'|'error', message: string}|null
 */
function admin_pop_flash_message(): ?array
{
    $key = admin_flash_session_key();
    $flash = $_SESSION[$key] ?? null;
    unset($_SESSION[$key]);

    if (!is_array($flash)) {
        return null;
    }

    $type = is_string($flash['type'] ?? null) ? trim((string) $flash['type']) : '';
    $message = is_string($flash['message'] ?? null) ? trim((string) $flash['message']) : '';

    if ($message === '' || !in_array($type, ['success', 'error'], true)) {
        return null;
    }

    return [
        'type' => $type,
        'message' => $message,
    ];
}

function admin_set_login_failure_reason(?string $reason): void
{
    $key = admin_login_failure_session_key();

    if (!is_string($reason) || trim($reason) === '') {
        unset($_SESSION[$key]);
        return;
    }

    $_SESSION[$key] = trim($reason);
}

function admin_pop_login_failure_reason(): ?string
{
    $key = admin_login_failure_session_key();
    $reason = $_SESSION[$key] ?? null;
    unset($_SESSION[$key]);

    return is_string($reason) && trim($reason) !== '' ? trim($reason) : null;
}

function admin_expected_identifier(): string
{
    $configured = admin_configured_identifier();

    return admin_normalize_identifier($configured);
}

function admin_normalize_identifier(string $identifier): string
{
    $identifier = trim($identifier);
    if ($identifier === '' || filter_var($identifier, FILTER_VALIDATE_EMAIL) === false) {
        return '';
    }

    return function_exists('mb_strtolower') ? mb_strtolower($identifier) : strtolower($identifier);
}

function admin_configured_identifier(): string
{
    $email = trim((string) app_config('admin.email', ''));
    if ($email !== '') {
        return $email;
    }

    $identifier = trim((string) app_config('admin.identifier', ''));
    if ($identifier !== '') {
        return $identifier;
    }

    return '';
}

function admin_current_identifier(): ?string
{
    $user = $_SESSION[admin_session_key()] ?? null;
    if (!is_array($user)) {
        return null;
    }

    $identifier = $user['identifier'] ?? $user['email'] ?? null;

    return is_string($identifier) && trim($identifier) !== '' ? trim($identifier) : null;
}

function admin_current_masked_identifier(): string
{
    return \Caramagnols\Logging\AppEventLogger::maskIdentifier(admin_current_identifier());
}

function admin_inactivity_timeout_seconds(): int
{
    $timeout = (int) app_config('admin.inactivity_timeout_seconds', 7200);
    $timeout = max(60, min(86400, $timeout));

    return $timeout;
}

function admin_reauth_timeout_seconds(): int
{
    $timeout = (int) app_config('admin.reauth_timeout_seconds', 7200);
    $timeout = max(60, min(86400, $timeout));

    $inactivityTimeout = admin_inactivity_timeout_seconds();
    if ($timeout > $inactivityTimeout) {
        $timeout = $inactivityTimeout;
    }

    return $timeout;
}

function admin_mark_activity(): void
{
    $key = admin_session_key();

    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        return;
    }

    $_SESSION[$key]['last_activity_at'] = time();
}

function admin_mark_reauthenticated(): void
{
    $key = admin_session_key();

    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        return;
    }

    $_SESSION[$key]['last_reauth_at'] = time();
}

function admin_reauth_is_fresh(): bool
{
    $key = admin_session_key();
    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        return false;
    }

    $now = time();
    $loginAt = (int) ($_SESSION[$key]['login_at'] ?? 0);
    $lastReauthAt = (int) ($_SESSION[$key]['last_reauth_at'] ?? $loginAt);

    if ($lastReauthAt <= 0) {
        return false;
    }

    return ($now - $lastReauthAt) <= admin_reauth_timeout_seconds();
}

function admin_verify_password(string $password): bool
{
    $password = trim($password);
    $hash = app_config('admin.password_hash', null);

    if ($password === '' || !is_string($hash) || $hash === '') {
        return false;
    }

    return password_verify($password, $hash);
}

function admin_normalize_totp_secret(string $secret): string
{
    $secret = strtoupper(trim($secret));
    $secret = preg_replace('/[\s\-=]+/', '', $secret) ?? $secret;

    return $secret;
}

function admin_totp_secret(): string
{
    return admin_normalize_totp_secret((string) app_config('admin.totp_secret', ''));
}

function admin_totp_enabled(): bool
{
    $raw = app_config('admin.totp_enabled', false);
    if (is_bool($raw)) {
        return $raw;
    }

    $normalized = strtolower(trim((string) $raw));

    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function admin_totp_skip_localhost(): bool
{
    $raw = app_config('admin.totp_skip_localhost', true);
    if (is_bool($raw)) {
        return $raw;
    }

    $normalized = strtolower(trim((string) $raw));

    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function admin_is_local_request(): bool
{
    if (function_exists('request_is_localhost_host') && request_is_localhost_host()) {
        return true;
    }

    $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if (in_array($remoteAddr, ['127.0.0.1', '::1'], true)) {
        return true;
    }

    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    if ($host === '') {
        return false;
    }

    $host = strtolower($host);
    $host = preg_replace('/^\[(.*)\](?::\d+)?$/', '$1', $host) ?? $host;
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function admin_totp_should_challenge(): bool
{
    if (!admin_totp_enabled()) {
        return false;
    }

    if (admin_totp_skip_localhost() && admin_is_local_request()) {
        return false;
    }

    $secret = admin_totp_secret();

    return $secret !== '' && preg_match('/^[A-Z2-7]+$/', $secret) === 1;
}

function admin_totp_base32_decode(string $encoded): ?string
{
    $encoded = admin_normalize_totp_secret($encoded);
    if ($encoded === '' || preg_match('/^[A-Z2-7]+$/', $encoded) !== 1) {
        return null;
    }

    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $buffer = 0;
    $bitsLeft = 0;
    $decoded = '';
    $length = strlen($encoded);

    for ($index = 0; $index < $length; $index++) {
        $char = $encoded[$index];
        $value = strpos($alphabet, $char);
        if ($value === false) {
            return null;
        }

        $buffer = ($buffer << 5) | $value;
        $bitsLeft += 5;

        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $decoded .= chr(($buffer >> $bitsLeft) & 0xff);
        }
    }

    return $decoded !== '' ? $decoded : null;
}

function admin_totp_generate_code_for_counter(int $counter, int $digits = 6): ?string
{
    $secretBinary = admin_totp_base32_decode(admin_totp_secret());
    if ($secretBinary === null || $digits < 6 || $digits > 8) {
        return null;
    }

    $high = intdiv($counter, 0x100000000);
    $low = $counter % 0x100000000;
    $binaryCounter = pack('N2', $high, $low);
    $hash = hash_hmac('sha1', $binaryCounter, $secretBinary, true);
    if (strlen($hash) < 20) {
        return null;
    }

    $offset = ord(substr($hash, -1)) & 0x0f;
    $binaryCode = (
        ((ord($hash[$offset]) & 0x7f) << 24)
        | (ord($hash[$offset + 1]) << 16)
        | (ord($hash[$offset + 2]) << 8)
        | ord($hash[$offset + 3])
    );

    $modulo = 10 ** $digits;
    $code = (string) ($binaryCode % $modulo);

    return str_pad($code, $digits, '0', STR_PAD_LEFT);
}

function admin_totp_code_at_timestamp(int $timestamp): ?string
{
    if ($timestamp < 0) {
        return null;
    }

    $period = max(30, (int) app_config('admin.totp_period_seconds', 30));
    $counter = intdiv($timestamp, $period);

    return admin_totp_generate_code_for_counter($counter, 6);
}

function admin_totp_verify_code(?string $code): bool
{
    if (!admin_totp_should_challenge()) {
        return true;
    }

    $code = is_string($code) ? trim($code) : '';
    if (preg_match('/^[0-9]{6}$/', $code) !== 1) {
        return false;
    }

    $period = max(30, (int) app_config('admin.totp_period_seconds', 30));
    $maxSkew = max(0, min(3, (int) app_config('admin.totp_allowed_drift_steps', 1)));
    $counter = intdiv(time(), $period);

    for ($drift = -$maxSkew; $drift <= $maxSkew; $drift++) {
        $expectedCode = admin_totp_generate_code_for_counter($counter + $drift, 6);
        if (is_string($expectedCode) && hash_equals($expectedCode, $code)) {
            return true;
        }
    }

    return false;
}

function admin_update_authenticated_identifier(string $identifier): void
{
    $key = admin_session_key();

    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        return;
    }

    $normalized = trim($identifier);

    $_SESSION[$key]['identifier'] = $normalized;
    $_SESSION[$key]['email'] = $normalized;
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

    $sessionIdentifier = $_SESSION[$key]['identifier'] ?? $_SESSION[$key]['email'] ?? null;
    $expectedIdentifier = admin_expected_identifier();

    if (
        !is_string($sessionIdentifier)
        || $expectedIdentifier === ''
        || !hash_equals($expectedIdentifier, admin_normalize_identifier($sessionIdentifier))
    ) {
        return false;
    }

    $now = time();
    $loginAt = (int) ($_SESSION[$key]['login_at'] ?? 0);
    $lastActivityAt = (int) ($_SESSION[$key]['last_activity_at'] ?? $loginAt);
    if ($lastActivityAt <= 0) {
        $lastActivityAt = $now;
    }

    if (($now - $lastActivityAt) > admin_inactivity_timeout_seconds()) {
        if (function_exists('app_event_logger')) {
            app_event_logger()->security(
                'admin.session.expired_inactivity',
                ['identifier' => \Caramagnols\Logging\AppEventLogger::maskIdentifier((string) $sessionIdentifier)],
                'warning'
            );
        }

        admin_logout('inactive_timeout');
        return false;
    }

    admin_mark_activity();

    return true;
}

/**
 * Renvoie les informations de session admin courante.
 */
function admin_current_user(): ?array
{
    return admin_is_authenticated() ? $_SESSION[admin_session_key()] : null;
}

/**
 * Authentifie l'admin a partir d'un email/mot de passe (+ TOTP si actif).
 */
function admin_login(string $identifier, string $password, ?string $totpCode = null): bool
{
    $identifier = trim($identifier);
    $expectedIdentifier = admin_expected_identifier();
    $hash = app_config('admin.password_hash', null);
    $maskedIdentifier = \Caramagnols\Logging\AppEventLogger::maskIdentifier($identifier);
    admin_set_login_failure_reason(null);

    if ($identifier === '' || $password === '' || $expectedIdentifier === '' || !is_string($hash) || $hash === '') {
        admin_set_login_failure_reason('configuration_or_payload_incomplete');
        if (function_exists('app_event_logger')) {
            app_event_logger()->security(
                'admin.login.rejected',
                ['identifier' => $maskedIdentifier, 'reason' => 'configuration_or_payload_incomplete'],
                'warning'
            );
        }

        return false;
    }

    if (!hash_equals($expectedIdentifier, admin_normalize_identifier($identifier))) {
        admin_set_login_failure_reason('identifier_mismatch');
        if (function_exists('app_event_logger')) {
            app_event_logger()->security(
                'admin.login.rejected',
                ['identifier' => $maskedIdentifier, 'reason' => 'identifier_mismatch'],
                'warning'
            );
        }

        return false;
    }

    if (!password_verify($password, $hash)) {
        admin_set_login_failure_reason('password_mismatch');
        if (function_exists('app_event_logger')) {
            app_event_logger()->security(
                'admin.login.rejected',
                ['identifier' => $maskedIdentifier, 'reason' => 'password_mismatch'],
                'warning'
            );
        }

        return false;
    }

    if (admin_totp_should_challenge()) {
        $totpCode = is_string($totpCode) ? trim($totpCode) : '';
        if ($totpCode === '') {
            admin_set_login_failure_reason('totp_required');
            if (function_exists('app_event_logger')) {
                app_event_logger()->security(
                    'admin.login.rejected',
                    ['identifier' => $maskedIdentifier, 'reason' => 'totp_required'],
                    'warning'
                );
            }

            return false;
        }

        if (!admin_totp_verify_code($totpCode)) {
            admin_set_login_failure_reason('totp_invalid');
            if (function_exists('app_event_logger')) {
                app_event_logger()->security(
                    'admin.login.rejected',
                    ['identifier' => $maskedIdentifier, 'reason' => 'totp_invalid'],
                    'warning'
                );
            }

            return false;
        }
    }

    if (function_exists('session_regenerate_id')) {
        session_regenerate_id(true);
    }

    $now = time();
    $configuredIdentifier = admin_configured_identifier();
    $authenticatedIdentifier = $configuredIdentifier !== '' ? $configuredIdentifier : trim($expectedIdentifier);
    $_SESSION[admin_session_key()] = [
        'identifier' => $authenticatedIdentifier,
        'email' => $authenticatedIdentifier,
        'login_at' => $now,
        'last_activity_at' => $now,
        'last_reauth_at' => $now,
    ];
    admin_set_notice_code(null);
    admin_set_flash_message(null, null);
    admin_set_login_failure_reason(null);

    if (function_exists('app_event_logger')) {
        app_event_logger()->security(
            'admin.login.success',
            ['identifier' => \Caramagnols\Logging\AppEventLogger::maskIdentifier((string) ($_SESSION[admin_session_key()]['identifier'] ?? ''))]
        );
    }

    return true;
}

/**
 * Deconnecte l'admin courant.
 */
function admin_logout(?string $noticeCode = null): void
{
    $key = admin_session_key();
    $currentUser = $_SESSION[$key] ?? null;

    unset($_SESSION[$key]);
    admin_set_login_failure_reason(null);
    admin_set_notice_code($noticeCode);
    admin_set_flash_message(null, null);

    if (function_exists('session_regenerate_id')) {
        session_regenerate_id(true);
    }

    if (function_exists('app_event_logger')) {
        $identifier = '';
        if (is_array($currentUser)) {
            $identifier = (string) ($currentUser['identifier'] ?? $currentUser['email'] ?? '');
        }

        app_event_logger()->security(
            'admin.logout.success',
            ['identifier' => \Caramagnols\Logging\AppEventLogger::maskIdentifier($identifier)]
        );
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
