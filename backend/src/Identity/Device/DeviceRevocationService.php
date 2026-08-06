<?php

declare(strict_types=1);

namespace Caramagnols\Identity\Device;

use Caramagnols\Identity\Audit\SessionAuditService;
use Caramagnols\Identity\Repository\PersistentTokenRepository;
use Caramagnols\Identity\Repository\TrustedDeviceRepository;

final class DeviceRevocationService
{
    public function __construct(
        private readonly TrustedDeviceRepository $devices,
        private readonly PersistentTokenRepository $tokens,
        private readonly SessionAuditService $audit
    ) {
    }

    public function revokeDevice(int $deviceId, string $reason, string $scope): bool
    {
        $this->tokens->revokeDeviceTokens($deviceId, $reason);
        $ok = $this->devices->revoke($deviceId, $reason);
        $this->audit->security('auth.device.revoked', [
            'trusted_device_id' => $deviceId,
            'scope' => $scope,
            'reason' => $reason,
            'result' => $ok ? 'success' : 'not_found',
        ], $ok ? 'info' : 'warning');

        return $ok;
    }

    public function revokeAllForUser(string $userScope, ?int $userId, string $identifierHash, string $reason, string $scope): int
    {
        $devices = $this->devices->listForUser($userScope, $userId, $identifierHash);
        $count = 0;
        foreach ($devices as $device) {
            $deviceId = (int) ($device['id'] ?? 0);
            if ($deviceId <= 0) {
                continue;
            }
            $this->tokens->revokeDeviceTokens($deviceId, $reason);
            if ($this->devices->revoke($deviceId, $reason)) {
                $count++;
            }
        }

        $this->audit->security('auth.device.revoked', [
            'scope' => $scope,
            'reason' => $reason,
            'result' => 'bulk',
            'count' => $count,
        ], $reason === 'security_incident' ? 'critical' : 'warning');

        return $count;
    }
}
