<?php

declare(strict_types=1);

namespace Caramagnols\Identity\PersistentSession;

use Caramagnols\Http\Request;
use Caramagnols\Identity\Audit\SessionAuditService;
use Caramagnols\Identity\Device\TrustedDeviceService;
use Caramagnols\Identity\Repository\PersistentTokenRepository;
use Caramagnols\Identity\Repository\TrustedDeviceRepository;
use Caramagnols\Identity\SessionScope;

final class PersistentSessionService
{
    public function __construct(
        private readonly TrustedDeviceRepository $devices,
        private readonly PersistentTokenRepository $tokens,
        private readonly PersistentSessionCookieManager $cookies,
        private readonly PersistentTokenRotator $rotator,
        private readonly SessionAuditService $audit,
        private readonly TrustedDeviceService $trustedDevices
    ) {
    }

    /**
     * @return array{set_cookie: string, device_id: int}|null
     */
    public function rememberAfterLogin(string $scope, string $userScope, ?int $userId, string $identifier, Request $request): ?array
    {
        if (!$this->enabled($scope)) {
            return null;
        }

        $ttl = $this->trustedTtl($scope);
        $deviceId = $this->devices->upsert(
            $userScope,
            $userId,
            $this->audit->hashIdentifier($identifier),
            $this->trustedDevices->defaultDeviceName($request),
            $this->trustedDevices->deviceType($request),
            $this->audit->hashUserAgent($request->header('User-Agent')),
            $this->audit->hashIp($request->clientIp((bool) app_config($scope . '.trust_proxy_headers', false))),
            gmdate('Y-m-d H:i:s', time() + $ttl)
        );
        $token = $this->tokens->create($deviceId, $scope, $ttl);

        $this->audit->security('auth.device.registered', ['trusted_device_id' => $deviceId, 'scope' => $scope, 'result' => 'success']);
        $this->audit->security('auth.token.created', ['trusted_device_id' => $deviceId, 'scope' => $scope, 'result' => 'success']);

        return [
            'set_cookie' => $this->cookies->issueHeader($scope, $token['selector'], $token['secret'], strtotime($token['expires_at']) ?: time() + $ttl),
            'device_id' => $deviceId,
        ];
    }

    /**
     * @return array{status: string, set_cookie: string|null, token: array<string, mixed>|null, device: array<string, mixed>|null}
     */
    public function consume(Request $request, string $scope): array
    {
        if (!$this->enabled($scope)) {
            return ['status' => 'disabled', 'set_cookie' => null, 'token' => null, 'device' => null];
        }

        $cookie = $this->cookies->read($request, $scope);
        if ($cookie === null) {
            return ['status' => 'missing', 'set_cookie' => null, 'token' => null, 'device' => null];
        }

        $token = $this->tokens->findBySelector($cookie['selector']);
        if (!is_array($token)) {
            $this->audit->security('auth.token.revoked', ['scope' => $scope, 'result' => 'unknown_selector'], 'warning');
            return ['status' => 'invalid', 'set_cookie' => $this->cookies->clearHeader($scope), 'token' => null, 'device' => null];
        }

        $device = $this->devices->findById((int) ($token['trusted_device_id'] ?? 0));
        if (!is_array($device) || !$this->tokens->secretMatches($token, $cookie['secret'])) {
            return ['status' => 'invalid', 'set_cookie' => $this->cookies->clearHeader($scope), 'token' => $token, 'device' => $device];
        }

        if ($this->isReused($token)) {
            $this->tokens->revokeFamily((string) ($token['token_family_id'] ?? ''), 'reuse_detected');
            if ((bool) app_config('identity.persistent.revoke_device_on_reuse', true) && is_array($device)) {
                $this->devices->revoke((int) ($device['id'] ?? 0), 'token_reuse_detected');
            }
            $this->audit->security('auth.token.reuse_detected', [
                'trusted_device_id' => (int) ($token['trusted_device_id'] ?? 0),
                'scope' => $scope,
                'result' => 'family_revoked',
            ], 'critical');

            return ['status' => 'reuse_detected', 'set_cookie' => $this->cookies->clearHeader($scope), 'token' => $token, 'device' => $device];
        }

        if ($this->isExpired($token) || $this->isExpired($device) || (string) ($token['scope'] ?? '') !== $scope) {
            $this->tokens->revoke((int) ($token['id'] ?? 0), 'expired_or_scope_mismatch');
            $this->audit->security('auth.token.expired', ['scope' => $scope, 'result' => 'rejected'], 'warning');

            return ['status' => 'expired', 'set_cookie' => $this->cookies->clearHeader($scope), 'token' => $token, 'device' => $device];
        }

        $ttl = $this->trustedTtl($scope);
        $new = $this->rotator->rotate($token, $ttl);
        $this->devices->touch(
            (int) ($device['id'] ?? 0),
            $this->audit->hashIp($request->clientIp((bool) app_config($scope . '.trust_proxy_headers', false))),
            gmdate('Y-m-d H:i:s', time() + $ttl)
        );

        $this->audit->security('auth.token.rotated', [
            'trusted_device_id' => (int) ($device['id'] ?? 0),
            'scope' => $scope,
            'result' => 'success',
        ]);

        return [
            'status' => 'valid',
            'set_cookie' => $this->cookies->issueHeader($scope, $new['selector'], $new['secret'], strtotime($new['expires_at']) ?: time() + $ttl),
            'token' => $token,
            'device' => $device,
        ];
    }

    public function clearCookieHeader(string $scope): string
    {
        return $this->cookies->clearHeader($scope);
    }

    public function revokePresentedToken(Request $request, string $scope, string $reason = 'logout'): string
    {
        $cookie = $this->cookies->read($request, $scope);
        if ($cookie !== null) {
            $token = $this->tokens->findBySelector($cookie['selector']);
            if (is_array($token) && $this->tokens->secretMatches($token, $cookie['secret'])) {
                $this->tokens->revoke((int) ($token['id'] ?? 0), $reason);
                $this->audit->security('auth.token.revoked', [
                    'trusted_device_id' => (int) ($token['trusted_device_id'] ?? 0),
                    'scope' => $scope,
                    'reason' => $reason,
                    'result' => 'success',
                ]);
            }
        }

        return $this->cookies->clearHeader($scope);
    }

    public function enabled(string $scope): bool
    {
        $scope = SessionScope::normalize($scope);
        if ($scope === '') {
            return false;
        }

        return (bool) app_config('identity.persistent.enabled', false)
            && (bool) app_config('identity.persistent.' . $scope . '_enabled', false);
    }

    public function trustedTtl(string $scope): int
    {
        $fallback = $scope === SessionScope::ADMIN ? 2592000 : 7776000;

        return max(300, (int) app_config('identity.persistent.' . $scope . '_trusted_device_ttl_seconds', $fallback));
    }

    private function isReused(array $token): bool
    {
        return (bool) app_config('identity.persistent.reuse_detection_enabled', true)
            && ((string) ($token['rotated_at'] ?? '') !== '' || (string) ($token['revoked_at'] ?? '') !== '');
    }

    private function isExpired(array $row): bool
    {
        if ((string) ($row['revoked_at'] ?? '') !== '') {
            return true;
        }

        $expires = (string) ($row['expires_at'] ?? $row['trusted_until'] ?? '');
        if ($expires === '') {
            return true;
        }

        return (strtotime($expires) ?: 0) <= time();
    }
}
