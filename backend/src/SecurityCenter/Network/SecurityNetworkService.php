<?php

declare(strict_types=1);

namespace Caramagnols\SecurityCenter\Network;

final class SecurityNetworkService
{
    private const TRUST_STATES = ['pending', 'trusted', 'limited', 'public', 'ignored'];
    private const RAW_KEYS = [
        'mac',
        'mac_address',
        'ip',
        'ip_address',
        'hostname',
        'host_name',
        'ports',
        'open_ports',
        'port_list',
    ];

    public function normalizeTrustState(mixed $state): string
    {
        $normalized = is_string($state) ? strtolower(trim($state)) : '';

        return in_array($normalized, self::TRUST_STATES, true) ? $normalized : 'pending';
    }

    public function activeScanAllowed(string $trustState): bool
    {
        return $this->normalizeTrustState($trustState) === 'trusted';
    }

    public function postureAllowed(string $trustState): bool
    {
        return in_array($this->normalizeTrustState($trustState), ['pending', 'trusted', 'limited'], true);
    }

    public function isCollectorEpochFresh(int $reportedEpoch, int $storedEpoch): bool
    {
        return $reportedEpoch >= $storedEpoch;
    }

    /**
     * @param array<string|int, mixed> $payload
     */
    public function containsRawNetworkDetails(array $payload): bool
    {
        foreach ($payload as $key => $value) {
            if (in_array(strtolower(trim((string) $key)), self::RAW_KEYS, true)) {
                return true;
            }

            if (is_array($value) && $this->containsRawNetworkDetails($value)) {
                return true;
            }
        }

        return false;
    }

    public function normalizeToken(mixed $value): string
    {
        $token = is_string($value) ? trim($value) : '';
        if ($token === '' || preg_match('/\A[a-zA-Z0-9._:-]{12,96}\z/', $token) !== 1) {
            return '';
        }

        return $token;
    }
}
