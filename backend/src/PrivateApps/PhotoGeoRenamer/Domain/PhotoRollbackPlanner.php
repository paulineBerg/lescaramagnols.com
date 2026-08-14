<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Domain;

final class PhotoRollbackPlanner
{
    /**
     * @param array<int, array<string, mixed>> $history
     * @param array<int, string> $existingNames
     * @return array{ok: bool, operations: array<int, array<string, mixed>>, conflicts: array<int, array<string, mixed>>}
     */
    public function preview(array $history, array $existingNames): array
    {
        $existing = array_fill_keys($existingNames, true);
        $currentBatchNames = array_fill_keys(array_map(
            static fn (array $row): string => (string) ($row['new_name'] ?? ''),
            $history
        ), true);
        $operations = [];
        $conflicts = [];

        foreach (array_reverse($history) as $row) {
            $current = (string) ($row['new_name'] ?? '');
            $restore = (string) ($row['old_name'] ?? '');
            $issues = [];
            if ($current === '' || $restore === '') {
                $issues[] = 'invalid_history';
            }
            if (isset($existing[$restore]) && !isset($currentBatchNames[$restore]) && $restore !== $current) {
                $issues[] = 'restore_target_exists';
            }

            $operation = [
                'old_name' => $current,
                'new_name' => $restore,
                'status' => $issues === [] ? 'ready' : 'conflict',
            ];
            if ($issues !== []) {
                $operation['issues'] = $issues;
                $conflicts[] = $operation;
            }
            $operations[] = $operation;
        }

        return [
            'ok' => $conflicts === [],
            'operations' => $operations,
            'conflicts' => $conflicts,
        ];
    }
}
