<?php

declare(strict_types=1);

namespace Caramagnols\PbGestion\Synchronization;

final class SyncContract
{
    public const SCHEMA_VERSION = '1.0.0';
    public const MAX_SYNC_BYTES = 65536;
    public const MAX_DETAIL_BYTES = 262144;

    /**
     * @return array<int, string>
     */
    public static function acceptedSections(): array
    {
        return [
            'network',
            'posture',
            'scan_summary',
            'devices',
            'changes',
            'alerts',
            'backup_status',
            'capabilities',
        ];
    }
}
