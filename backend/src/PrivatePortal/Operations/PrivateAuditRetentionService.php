<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Operations;

use Caramagnols\Logging\SqlLogStore;
use DateTimeInterface;

final class PrivateAuditRetentionService
{
    public function __construct(private readonly ?SqlLogStore $sqlLogStore = null)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function purgeSqlLogs(int $retentionDays, int $sensitiveRetentionDays, bool $dryRun = false, ?DateTimeInterface $now = null): array
    {
        if (!$this->sqlLogStore instanceof SqlLogStore) {
            return [
                'deleted' => 0,
                'regularDeleted' => 0,
                'sensitiveDeleted' => 0,
                'regularMatched' => 0,
                'sensitiveMatched' => 0,
                'retentionDays' => max(1, $retentionDays),
                'sensitiveRetentionDays' => max(1, $sensitiveRetentionDays),
                'dryRun' => $dryRun,
                'available' => false,
            ];
        }

        $result = $this->sqlLogStore->purgeOlderThan($retentionDays, $sensitiveRetentionDays, $dryRun, $now);
        $result['available'] = true;

        return $result;
    }

    /**
     * @return array{deleted:int, matched:int, cutoff:int, dryRun:bool}
     */
    public function purgeFiles(string $directory, int $retentionDays, bool $dryRun = false, ?int $now = null): array
    {
        $directory = rtrim(str_replace('\\', '/', trim($directory)), '/');
        $retentionDays = max(1, min(3650, $retentionDays));
        $reference = $now ?? time();
        $cutoff = $reference - ($retentionDays * 86400);
        $matched = 0;
        $deleted = 0;

        if ($directory === '' || !is_dir($directory)) {
            return ['deleted' => 0, 'matched' => 0, 'cutoff' => $cutoff, 'dryRun' => $dryRun];
        }

        foreach (glob($directory . '/*.log*') ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }
            $mtime = @filemtime($file);
            if (!is_int($mtime) || $mtime >= $cutoff) {
                continue;
            }
            ++$matched;
            if (!$dryRun && @unlink($file)) {
                ++$deleted;
            }
        }

        return ['deleted' => $deleted, 'matched' => $matched, 'cutoff' => $cutoff, 'dryRun' => $dryRun];
    }
}
