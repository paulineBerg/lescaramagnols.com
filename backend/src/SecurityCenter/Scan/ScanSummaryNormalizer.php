<?php

declare(strict_types=1);

namespace Caramagnols\SecurityCenter\Scan;

final class ScanSummaryNormalizer
{
    /**
     * @param array<string, mixed> $scan
     * @return array{scan_type: string, status: string, devices_seen: int, changes_seen: int, alerts_opened: int}
     */
    public function normalize(array $scan): array
    {
        $type = is_string($scan['scan_type'] ?? null) ? strtolower(trim((string) $scan['scan_type'])) : 'passive';
        if (!in_array($type, ['passive', 'active_limited', 'posture'], true)) {
            $type = 'passive';
        }

        $status = is_string($scan['status'] ?? null) ? strtolower(trim((string) $scan['status'])) : 'received';
        if (!in_array($status, ['received', 'partial', 'rejected', 'stale'], true)) {
            $status = 'received';
        }

        return [
            'scan_type' => $type,
            'status' => $status,
            'devices_seen' => max(0, min(10000, (int) ($scan['devices_seen'] ?? 0))),
            'changes_seen' => max(0, min(10000, (int) ($scan['changes_seen'] ?? 0))),
            'alerts_opened' => max(0, min(1000, (int) ($scan['alerts_opened'] ?? 0))),
        ];
    }
}
