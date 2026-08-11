<?php

declare(strict_types=1);

namespace Caramagnols\SecurityCenter\Device;

final class DeviceSummaryNormalizer
{
    /**
     * @param array<string, mixed> $device
     * @return array{device_token: string, device_kind: string, risk_level: string, summary: array<string, mixed>}
     */
    public function normalize(array $device): array
    {
        $token = is_string($device['device_token'] ?? null) ? trim((string) $device['device_token']) : '';
        if ($token === '' || preg_match('/\A[a-zA-Z0-9._:-]{12,96}\z/', $token) !== 1) {
            $token = hash('sha256', json_encode($device, JSON_UNESCAPED_SLASHES) ?: random_bytes(8));
        }

        $kind = is_string($device['device_kind'] ?? null) ? strtolower(trim((string) $device['device_kind'])) : 'unknown';
        if (!in_array($kind, ['computer', 'phone', 'printer', 'router', 'iot', 'unknown'], true)) {
            $kind = 'unknown';
        }

        $risk = is_string($device['risk_level'] ?? null) ? strtolower(trim((string) $device['risk_level'])) : 'unknown';
        if (!in_array($risk, ['low', 'medium', 'high', 'critical', 'unknown'], true)) {
            $risk = 'unknown';
        }

        $summary = is_array($device['summary'] ?? null) ? $device['summary'] : [];

        return [
            'device_token' => $token,
            'device_kind' => $kind,
            'risk_level' => $risk,
            'summary' => $summary,
        ];
    }
}
