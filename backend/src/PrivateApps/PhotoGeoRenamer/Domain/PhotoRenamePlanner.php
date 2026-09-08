<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Domain;

final class PhotoRenamePlanner
{
    public function __construct(
        private readonly PhotoRenameTemplate $template = new PhotoRenameTemplate(),
        private readonly PhotoCommuneNormalizer $communeNormalizer = new PhotoCommuneNormalizer(),
        private readonly PhotoDateResolver $dateResolver = new PhotoDateResolver()
    )
    {
    }

    /**
     * @param array<int, array<string, mixed>> $photos
     * @param array<int, string> $selectedNames
     * @param array<int, array<string, mixed>> $blocks
     * @param array<int, string> $existingNames Legacy parameter ignored: destination folders are never scanned for numbering.
     * @return array{ok: bool, operations: array<int, array<string, mixed>>, conflicts: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function preview(
        array $photos,
        array $selectedNames,
        array $blocks,
        array $existingNames = [],
        string $separator = '-',
        int $counterStart = 1,
        int $counterDigits = 2,
        string $sortOrder = 'chronological',
        ?string $batchUid = null
    ): array {
        $selected = array_fill_keys($selectedNames, true);
        $selectedPhotos = array_values(array_filter(
            $photos,
            static fn (array $photo): bool => isset($selected[(string) ($photo['current_name'] ?? $photo['name'] ?? '')])
        ));
        unset($existingNames);

        $operations = [];
        $conflicts = [];
        $targets = [];
        $counters = [];
        $batchUid ??= str_repeat('0', 32);

        $plannedPhotos = $this->sortPhotos($this->preparePhotos($selectedPhotos, $counterStart), $sortOrder);
        foreach ($plannedPhotos as $index => $photo) {
            $oldName = (string) ($photo['current_name'] ?? $photo['name'] ?? '');
            $issues = [];
            if ($oldName === '') {
                $issues[] = 'invalid_name';
            }
            if (($photo['commune_key'] ?? '') === '') {
                $issues[] = 'commune_missing';
            }
            if (($photo['taken_at_timestamp'] ?? null) === null && $sortOrder === 'chronological') {
                $issues[] = 'taken_at_missing';
            }

            $communeKey = (string) ($photo['commune_key'] ?? '');
            if (!isset($counters[$communeKey])) {
                $counters[$communeKey] = (int) ($photo['sequence_last_number'] ?? (max(1, $counterStart) - 1));
            }
            $assignedNumber = is_numeric($photo['assigned_number'] ?? null)
                ? max(1, (int) $photo['assigned_number'])
                : ++$counters[$communeKey];

            $photo['city'] = (string) ($photo['commune_name'] ?? $photo['city'] ?? '');
            $newName = $this->template->filename($photo, $blocks, $separator, $assignedNumber, $counterDigits);
            if ($newName === '') {
                $issues[] = 'invalid_name';
            }
            if (isset($targets[$newName])) {
                $issues[] = 'duplicate_in_batch';
            }

            $targets[$newName] = true;
            $operation = [
                'old_name' => $oldName,
                'new_name' => $newName,
                'temporary_name' => $this->temporaryName($oldName, $batchUid, $index + 1),
                'status' => $oldName === $newName ? 'unchanged' : 'ready',
                'commune_key' => $communeKey,
                'commune_name' => (string) ($photo['commune_name'] ?? ''),
                'assigned_number' => $assignedNumber,
                'taken_at' => (string) ($photo['taken_at'] ?? ''),
                'taken_at_source' => (string) ($photo['taken_at_source'] ?? ''),
            ];

            if ($issues !== []) {
                $operation['status'] = 'conflict';
                $operation['issues'] = $issues;
                $conflicts[] = $operation;
            }

            $operations[] = $operation;
        }

        return [
            'ok' => $conflicts === [],
            'operations' => $operations,
            'conflicts' => $conflicts,
            'summary' => [
                'selected' => count($selectedPhotos),
                'ready' => count(array_filter($operations, static fn (array $op): bool => $op['status'] === 'ready')),
                'unchanged' => count(array_filter($operations, static fn (array $op): bool => $op['status'] === 'unchanged')),
                'conflicts' => count($conflicts),
                'communes' => count(array_filter(array_unique(array_map(
                    static fn (array $op): string => (string) ($op['commune_key'] ?? ''),
                    $operations
                )))),
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $photos
     * @return array<int, array<string, mixed>>
     */
    private function preparePhotos(array $photos, int $counterStart): array
    {
        return array_map(function (array $photo) use ($counterStart): array {
            $commune = $this->communeNormalizer->normalize(
                $photo['commune_name'] ?? $photo['city'] ?? $photo['town'] ?? null
            );
            if ($commune !== null) {
                $photo['commune_key'] = $commune->key;
                $photo['commune_name'] = $commune->name;
            } else {
                $photo['commune_key'] = '';
                $photo['commune_name'] = '';
            }

            $date = $this->dateResolver->resolve($photo, ($photo['allow_filesystem_fallback'] ?? false) === true);
            $photo['taken_at_timestamp'] = $date->timestamp();
            $photo['taken_at_source'] = $date->source;
            if ($date->sqlValue() !== null) {
                $photo['taken_at'] = $date->sqlValue();
            }
            $photo['sequence_last_number'] = is_numeric($photo['sequence_last_number'] ?? null)
                ? (int) $photo['sequence_last_number']
                : max(0, $counterStart - 1);

            return $photo;
        }, $photos);
    }

    /**
     * @param array<int, array<string, mixed>> $photos
     * @return array<int, array<string, mixed>>
     */
    private function sortPhotos(array $photos, string $sortOrder): array
    {
        if ($sortOrder === 'manual') {
            return $photos;
        }

        usort($photos, static function (array $left, array $right) use ($sortOrder): int {
            return match ($sortOrder) {
                'name' => strcmp((string) ($left['current_name'] ?? ''), (string) ($right['current_name'] ?? '')),
                'city' => strcmp((string) ($left['commune_key'] ?? ''), (string) ($right['commune_key'] ?? ''))
                    ?: strcmp((string) ($left['current_name'] ?? ''), (string) ($right['current_name'] ?? '')),
                default => strcmp((string) ($left['commune_key'] ?? ''), (string) ($right['commune_key'] ?? ''))
                    ?: (($left['taken_at_timestamp'] ?? PHP_INT_MAX) <=> ($right['taken_at_timestamp'] ?? PHP_INT_MAX))
                    ?: strcmp((string) ($left['current_name'] ?? ''), (string) ($right['current_name'] ?? '')),
            };
        });

        return $photos;
    }

    private function temporaryName(string $oldName, string $batchUid, int $index): string
    {
        $extension = (string) pathinfo($oldName, PATHINFO_EXTENSION);
        $suffix = $extension !== '' ? '.' . $extension : '';

        return '.pbgestion-' . substr($batchUid, 0, 12) . '-' . str_pad((string) $index, 4, '0', STR_PAD_LEFT) . '.tmp' . $suffix;
    }
}
