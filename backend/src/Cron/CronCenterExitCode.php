<?php

declare(strict_types=1);

namespace Caramagnols\Cron;

final class CronCenterExitCode
{
    /**
     * @param array<string, mixed> $result
     */
    public static function forResult(array $result, bool $failOnJobError = false): int
    {
        if (($result['locked'] ?? false) === true) {
            return 0;
        }

        if (($result['success'] ?? false) !== true) {
            return 1;
        }

        if (!$failOnJobError) {
            return 0;
        }

        $runs = is_array($result['runs'] ?? null) ? $result['runs'] : [];
        foreach ($runs as $run) {
            if (!is_array($run)) {
                continue;
            }

            if (in_array((string) ($run['status'] ?? ''), ['failed', 'timeout'], true)) {
                return 2;
            }
        }

        return 0;
    }
}
