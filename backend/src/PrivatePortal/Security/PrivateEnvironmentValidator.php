<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Security;

use Caramagnols\PrivatePortal\Http\PrivateRouteResolver;

final class PrivateEnvironmentValidator
{
    public function __construct(private readonly PrivateRouteResolver $routeResolver)
    {
    }

    /**
     * @return array<int, string>
     */
    public function issues(): array
    {
        if (!\private_portal_enabled()) {
            return [];
        }

        $issues = [];
        $basePath = $this->routeResolver->basePath();
        if ($basePath === '/' || strlen($basePath) < 4 || strlen($basePath) > 80) {
            $issues[] = 'private_base_path_invalid';
        }

        $sessionName = strtolower(trim((string) \app_config('private.session_name', 'caramagnols_private')));
        if ($sessionName === '' || strlen($sessionName) > 64 || preg_match('/\A[a-z0-9_-]+\z/', $sessionName) !== 1) {
            $issues[] = 'private_session_name_invalid';
        }

        $localEmail = trim((string) \app_config('private.local_user_email', ''));
        if ($localEmail !== '' && filter_var($localEmail, FILTER_VALIDATE_EMAIL) === false) {
            $issues[] = 'private_local_user_email_invalid';
        }

        $localPasswordHash = trim((string) \app_config('private.local_user_password_hash', ''));
        if ($localPasswordHash !== '') {
            $hashInfo = password_get_info($localPasswordHash);
            if (!is_array($hashInfo) || (string) ($hashInfo['algoName'] ?? '') !== 'argon2id') {
                $issues[] = 'private_local_password_hash_algo_invalid';
            }
        }

        if ((int) \app_config('private.login_rate_limit_attempts', 5) < 1) {
            $issues[] = 'private_login_rate_limit_attempts_invalid';
        }

        if ((int) \app_config('private.login_rate_limit_window', 900) < 60) {
            $issues[] = 'private_login_rate_limit_window_invalid';
        }

        if ((int) \app_config('private.inactivity_timeout_seconds', 3600) < 300) {
            $issues[] = 'private_inactivity_timeout_invalid';
        }

        if ((int) \app_config('private.reauth_timeout_seconds', 1800) < 300) {
            $issues[] = 'private_reauth_timeout_invalid';
        }

        return array_values(array_unique($issues));
    }

    public function isValid(): bool
    {
        return $this->issues() === [];
    }
}
