<?php

declare(strict_types=1);

namespace Caramagnols\Identity\Device;

use Caramagnols\Http\Request;
use Caramagnols\Identity\Audit\SessionAuditService;
use Caramagnols\Identity\Repository\PersistentTokenRepository;
use Caramagnols\Identity\Repository\TrustedDeviceRepository;

final class TrustedDeviceService
{
    public function __construct(
        private readonly TrustedDeviceRepository $devices,
        private readonly PersistentTokenRepository $tokens,
        private readonly SessionAuditService $audit
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(string $userScope, ?int $userId, string $identifier): array
    {
        $rows = $this->devices->listForUser($userScope, $userId, $this->audit->hashIdentifier($identifier));
        foreach ($rows as &$row) {
            $row['active_scopes'] = $this->tokens->activeScopesByDevice((int) ($row['id'] ?? 0));
        }

        return $rows;
    }

    public function defaultDeviceName(Request $request): string
    {
        $ua = strtolower((string) ($request->header('User-Agent') ?? ''));
        if (str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return 'Telephone de confiance';
        }
        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) {
            return 'Tablette de confiance';
        }

        return 'Ordinateur de confiance';
    }

    public function deviceType(Request $request): string
    {
        $ua = strtolower((string) ($request->header('User-Agent') ?? ''));
        if (str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return 'mobile';
        }
        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            return 'tablet';
        }

        return 'desktop';
    }

    public function rename(int $deviceId, string $name, string $scope): bool
    {
        $ok = $this->devices->rename($deviceId, $name);
        if ($ok) {
            $this->audit->security('auth.device.renamed', ['trusted_device_id' => $deviceId, 'scope' => $scope]);
        }

        return $ok;
    }
}
