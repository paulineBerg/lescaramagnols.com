<?php

declare(strict_types=1);

namespace Caramagnols\Identity\PersistentSession;

use Caramagnols\Http\Request;
use Caramagnols\Identity\Audit\SessionAuditService;
use Caramagnols\Identity\SessionScope;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivatePortal\Security\PrivateAuth;

final class PersistentSessionGuard
{
    public function __construct(
        private readonly PersistentSessionService $sessions,
        private readonly SessionAuditService $audit
    ) {
    }

    public function restorePrivate(Request $request, PrivateAuth $auth, PrivateUserRepository $users): ?string
    {
        $result = $this->sessions->consume($request, SessionScope::PRIVATE);
        if ($result['status'] !== 'valid') {
            return $result['set_cookie'];
        }

        $device = $result['device'];
        $userId = is_array($device) && is_numeric($device['user_id'] ?? null) ? (int) $device['user_id'] : 0;
        $user = $userId > 0 ? $users->findById($userId) : null;
        $status = is_array($user) ? strtolower((string) ($user['status'] ?? '')) : '';
        $email = is_array($user) && is_string($user['email'] ?? null) ? trim((string) $user['email']) : '';
        if ($email === '' || $status !== 'active') {
            $this->audit->security('auth.private.session_restored', ['result' => 'account_refused', 'scope' => SessionScope::PRIVATE], 'warning');
            return $result['set_cookie'];
        }

        $auth->restorePersistentSession($email, $request->clientIp((bool) app_config('private.trust_proxy_headers', false)));
        $this->audit->security('auth.private.session_restored', [
            'trusted_device_id' => (int) ($device['id'] ?? 0),
            'scope' => SessionScope::PRIVATE,
            'result' => 'success',
        ]);

        return $result['set_cookie'];
    }

    public function restoreAdmin(Request $request): ?string
    {
        $result = $this->sessions->consume($request, SessionScope::ADMIN);
        if ($result['status'] !== 'valid') {
            return $result['set_cookie'];
        }

        $device = $result['device'];
        $identifier = function_exists('admin_configured_identifier') ? admin_configured_identifier() : '';
        $expectedHash = $this->audit->hashIdentifier($identifier);
        $actualHash = is_array($device) ? (string) ($device['user_identifier_hash'] ?? '') : '';
        if ($identifier === '' || $actualHash === '' || !hash_equals($expectedHash, $actualHash)) {
            $this->audit->security('auth.admin.session_restored', ['result' => 'account_refused', 'scope' => SessionScope::ADMIN], 'warning');
            return $result['set_cookie'];
        }

        $trustedReauthUntil = strtotime((string) ($device['trusted_until'] ?? '')) ?: null;
        admin_restore_session($identifier, false, $trustedReauthUntil);
        $this->audit->security('auth.admin.session_restored', [
            'trusted_device_id' => (int) ($device['id'] ?? 0),
            'scope' => SessionScope::ADMIN,
            'result' => 'success',
        ]);

        return $result['set_cookie'];
    }
}
