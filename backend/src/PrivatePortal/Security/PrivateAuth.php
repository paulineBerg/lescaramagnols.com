<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Security;

use Caramagnols\Logging\AppEventLogger;

final class PrivateAuth
{
    private const SESSION_IDENTIFIER_KEY = 'identifier';
    private const SESSION_LOGIN_AT_KEY = 'login_at';
    private const SESSION_LAST_ACTIVITY_AT_KEY = 'last_activity_at';
    private const SESSION_LAST_REAUTH_AT_KEY = 'last_reauth_at';

    private readonly int $inactivityTimeoutSeconds;
    private readonly int $loginRateLimitAttempts;
    private readonly int $loginRateLimitWindow;
    private readonly int $accountLockoutAttempts;
    private readonly int $accountLockoutSeconds;
    private readonly int $reauthTimeoutSeconds;
    private readonly bool $trustProxyHeaders;
    private ?string $failureReason = null;

    private readonly string $sessionName;

    public function __construct(
        private readonly PrivateSession $session,
        private readonly ?AppEventLogger $eventLogger = null
    ) {
        $this->sessionName = $this->session->name();
        $this->inactivityTimeoutSeconds = max(300, (int) app_config('private.inactivity_timeout_seconds', 3600));
        $this->loginRateLimitAttempts = max(1, (int) app_config('private.login_rate_limit_attempts', 5));
        $this->loginRateLimitWindow = max(60, (int) app_config('private.login_rate_limit_window', 900));
        $this->accountLockoutAttempts = max(1, (int) app_config('private.account_lockout_attempts', 3));
        $this->accountLockoutSeconds = max(60, (int) app_config('private.account_lockout_seconds', 86400));
        $this->trustProxyHeaders = (bool) app_config('private.trust_proxy_headers', false);
        $this->reauthTimeoutSeconds = max(300, min(86400, (int) app_config('private.reauth_timeout_seconds', 1800)));
    }

    public function authMode(): string
    {
        $mode = strtolower(trim((string) app_config('private.auth_mode', 'local')));

        return in_array($mode, ['local', 'oidc'], true) ? $mode : 'local';
    }

    public function isAuthenticated(): bool
    {
        $this->session->start();
        $context = $this->session->all();

        if (!is_array($context) || !isset($context[self::SESSION_IDENTIFIER_KEY])) {
            return false;
        }

        $identifier = trim((string) $context[self::SESSION_IDENTIFIER_KEY]);
        if ($identifier === '' || $identifier !== $this->configuredIdentifier()) {
            $this->logout('identifier_mismatch');
            return false;
        }

        $loginAt = (int) ($context[self::SESSION_LOGIN_AT_KEY] ?? 0);
        $lastActivityAt = (int) ($context[self::SESSION_LAST_ACTIVITY_AT_KEY] ?? $loginAt);

        if ($loginAt <= 0) {
            $this->logout('invalid_session');
            return false;
        }

        $now = time();
        if (($now - $lastActivityAt) > $this->inactivityTimeoutSeconds) {
            $this->logout('inactivity_timeout');

            $this->log('private.session.expired', ['reason' => 'inactivity_timeout']);

            return false;
        }

        $lastReauthAt = (int) ($context[self::SESSION_LAST_REAUTH_AT_KEY] ?? $loginAt);
        if ($lastReauthAt <= 0) {
            $lastReauthAt = $loginAt;
            $context[self::SESSION_LAST_REAUTH_AT_KEY] = $loginAt;
        }

        if (($now - $lastReauthAt) > $this->reauthTimeoutSeconds) {
            $this->logout('reauth_timeout');

            $this->log('private.session.expired', ['reason' => 'reauth_timeout']);

            return false;
        }

        $context[self::SESSION_LAST_ACTIVITY_AT_KEY] = $now;
        $this->session->setAll($context);

        return true;
    }

    public function isReauthFresh(): bool
    {
        $this->session->start();

        $context = $this->session->all();
        $loginAt = (int) ($context[self::SESSION_LOGIN_AT_KEY] ?? 0);
        $lastReauthAt = (int) ($context[self::SESSION_LAST_REAUTH_AT_KEY] ?? 0);

        if ($loginAt <= 0 || $lastReauthAt <= 0) {
            return false;
        }

        return (time() - $lastReauthAt) <= $this->reauthTimeoutSeconds;
    }

    public function currentIdentifier(): ?string
    {
        $context = $this->session->all();

        $identifier = $context[self::SESSION_IDENTIFIER_KEY] ?? null;

        if (!is_string($identifier)) {
            return null;
        }

        $normalized = trim($identifier);

        return $normalized === '' ? null : $normalized;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    public function clearFailureReason(): void
    {
        $this->failureReason = null;
    }

    public function canAttemptLogin(?string $identifier, ?string $clientIp): bool
    {
        $normalized = $this->normalizeIdentifier($identifier);
        $clientIp = is_string($clientIp) ? trim($clientIp) : '';

        $ipLimiter = $this->ipLimiter($normalized, $clientIp);
        $accountLimiter = $this->accountLimiter($normalized);

        if (!$ipLimiter->allow()) {
            $this->failureReason = 'rate_limited';

            return false;
        }

        if (!$accountLimiter->allow()) {
            $this->failureReason = 'account_locked';

            return false;
        }

        return true;
    }

    public function login(string $identifier, string $password, ?string $clientIp = null): bool
    {
        if (strtolower($this->authMode()) !== 'local') {
            $this->failureReason = 'auth_mode_unsupported';
            return false;
        }

        $identifier = $this->normalizeIdentifier($identifier);
        $password = trim((string) $password);

        if (!$this->canAttemptLogin($identifier, $clientIp)) {
            $this->log('private.login.rejected', [
                'identifier' => $this->maskIdentifier($identifier),
                'reason' => $this->failureReason,
                'ip' => $clientIp ?? '',
            ], 'warning');

            return false;
        }

        $expectedIdentifier = $this->configuredIdentifier();
        $expectedHash = (string) app_config('private.local_user_password_hash', '');

        if (
            $identifier === ''
            || $expectedIdentifier === ''
            || $password === ''
            || $expectedHash === ''
            || !$this->isSupportedPasswordHash($expectedHash)
        ) {
            $this->recordFailedAttempt($identifier, (string) $clientIp);
            $this->failureReason = 'invalid_credentials';

            $this->log('private.login.rejected', [
                'identifier' => $this->maskIdentifier($identifier),
                'reason' => $this->failureReason,
                'ip' => $clientIp ?? '',
            ], 'warning');

            return false;
        }

        if (!hash_equals($expectedIdentifier, $identifier)) {
            $this->recordFailedAttempt($identifier, (string) $clientIp);
            $this->failureReason = 'invalid_credentials';

            $this->log('private.login.rejected', [
                'identifier' => $this->maskIdentifier($identifier),
                'reason' => $this->failureReason,
                'ip' => $clientIp ?? '',
            ], 'warning');

            return false;
        }

        if (!password_verify($password, $expectedHash)) {
            $this->recordFailedAttempt($identifier, (string) $clientIp);
            $this->failureReason = 'invalid_credentials';

            $this->log('private.login.rejected', [
                'identifier' => $this->maskIdentifier($identifier),
                'reason' => $this->failureReason,
                'ip' => $clientIp ?? '',
            ], 'warning');

            return false;
        }

        if (function_exists('session_regenerate_id')) {
            session_regenerate_id(true);
        }

        $now = time();
        $this->session->setAll([
            self::SESSION_IDENTIFIER_KEY => $identifier,
            self::SESSION_LOGIN_AT_KEY => $now,
            self::SESSION_LAST_ACTIVITY_AT_KEY => $now,
            self::SESSION_LAST_REAUTH_AT_KEY => $now,
        ]);

        $this->clearFailedAttempts($identifier, (string) $clientIp);
        $this->failureReason = null;

        $this->log('private.login.success', [
            'identifier' => $this->maskIdentifier($identifier),
            'ip' => $clientIp ?? '',
        ]);

        return true;
    }

    public function logout(?string $reason = null): void
    {
        $reason = is_string($reason) ? trim($reason) : null;
        $identifier = $this->maskIdentifier((string) $this->currentIdentifier());

        $this->session->clear();

        $this->log('private.logout', [
            'identifier' => $identifier,
            'reason' => $reason ?? 'manual',
        ]);
    }

    public function loginRetryAfter(?string $identifier, ?string $clientIp): int
    {
        $normalized = $this->normalizeIdentifier($identifier);
        $clientIp = is_string($clientIp) ? trim($clientIp) : '';

        $ipLimiter = $this->ipLimiter($normalized, $clientIp);
        $accountLimiter = $this->accountLimiter($normalized);

        return max($ipLimiter->retryAfter(), $accountLimiter->retryAfter());
    }

    private function canUseAccount(string $identifier): bool
    {
        return $this->accountLimiter($identifier)->allow();
    }

    private function isSupportedPasswordHash(string $hash): bool
    {
        $hashInfo = is_string($hash) ? password_get_info($hash) : [];

        return is_array($hashInfo) && ((string) ($hashInfo['algoName'] ?? '')) === 'argon2id';
    }

    private function recordFailedAttempt(string $identifier, string $clientIp): void
    {
        $this->ipLimiter($identifier, $clientIp)->hit();
        $this->accountLimiter($identifier)->hit();
    }

    private function clearFailedAttempts(string $identifier, string $clientIp): void
    {
        $this->ipLimiter($identifier, $clientIp)->clear();
        $this->accountLimiter($identifier)->clear();
    }

    private function accountLimiter(string $identifier): \FileRateLimiter
    {
        $this->session->start();

        return new \FileRateLimiter('private_login_account_' . hash('sha256', $identifier), $this->accountLockoutAttempts, $this->accountLockoutSeconds);
    }

    private function ipLimiter(string $identifier, string $clientIp): \FileRateLimiter
    {
        $this->session->start();

        $normalizedIp = $clientIp === '' ? 'unknown' : $clientIp;

        return new \FileRateLimiter('private_login_ip_' . hash('sha256', $normalizedIp . '|' . $identifier), $this->loginRateLimitAttempts, $this->loginRateLimitWindow);
    }

    private function configuredIdentifier(): string
    {
        $configured = (string) app_config('private.local_user_email', '');

        return strtolower(trim($configured));
    }

    private function normalizeIdentifier(?string $identifier): string
    {
        return strtolower(trim((string) $identifier));
    }

    private function log(string $event, array $context, string $level = 'info'): void
    {
        if ($this->eventLogger === null) {
            return;
        }

        $this->eventLogger->security($event, $context, $level);
    }

    private function maskIdentifier(string $identifier): string
    {
        return \Caramagnols\Logging\AppEventLogger::maskIdentifier($identifier);
    }
}
