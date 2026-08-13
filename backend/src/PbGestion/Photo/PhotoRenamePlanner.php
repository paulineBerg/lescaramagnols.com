<?php

declare(strict_types=1);

namespace Caramagnols\PbGestion\Photo;

final class PhotoRenamePlanner
{
    public function __construct(private readonly PhotoRenameTemplate $template = new PhotoRenameTemplate())
    {
    }

    /**
     * @param array<int, array<string, mixed>> $photos
     * @param array<int, string> $selectedNames
     * @param array<int, array<string, mixed>> $blocks
     * @param array<int, string> $existingNames
     * @return array{ok: bool, operations: array<int, array<string, mixed>>, conflicts: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function preview(
        array $photos,
        array $selectedNames,
        array $blocks,
        array $existingNames = [],
        string $separator = '-',
        int $counterStart = 1,
        int $counterDigits = 3,
        string $sortOrder = 'chronological',
        ?string $batchUid = null
    ): array {
        $selected = array_fill_keys($selectedNames, true);
        $selectedPhotos = array_values(array_filter(
            $photos,
            static fn (array $photo): bool => isset($selected[(string) ($photo['current_name'] ?? $photo['name'] ?? '')])
        ));
        $selectedPhotos = $this->sortPhotos($selectedPhotos, $sortOrder);
        $existing = array_fill_keys($existingNames, true);
        $selectedCurrent = array_fill_keys(array_map(
            static fn (array $photo): string => (string) ($photo['current_name'] ?? $photo['name'] ?? ''),
            $selectedPhotos
        ), true);

        $operations = [];
        $conflicts = [];
        $targets = [];
        $counter = max(1, $counterStart);
        $batchUid ??= str_repeat('0', 32);

        foreach ($selectedPhotos as $index => $photo) {
            $oldName = (string) ($photo['current_name'] ?? $photo['name'] ?? '');
            $newName = $this->template->filename($photo, $blocks, $separator, $counter, $counterDigits);
            $counter++;

            $issues = [];
            if ($oldName === '' || $newName === '') {
                $issues[] = 'invalid_name';
            }
            if (isset($targets[$newName])) {
                $issues[] = 'duplicate_in_batch';
            }
            if (isset($existing[$newName]) && !isset($selectedCurrent[$newName]) && $newName !== $oldName) {
                $issues[] = 'target_exists';
            }

            $targets[$newName] = true;
            $operation = [
                'old_name' => $oldName,
                'new_name' => $newName,
                'temporary_name' => $this->temporaryName($oldName, $batchUid, $index + 1),
                'status' => $oldName === $newName ? 'unchanged' : 'ready',
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
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $photos
     * @return array<int, array<string, mixed>>
     */
    private function sortPhotos(array $photos, string $sortOrder): array
    {
        usort($photos, static function (array $left, array $right) use ($sortOrder): int {
            return match ($sortOrder) {
                'name' => strcmp((string) ($left['current_name'] ?? ''), (string) ($right['current_name'] ?? '')),
                'city' => strcmp((string) ($left['city'] ?? ''), (string) ($right['city'] ?? ''))
                    ?: strcmp((string) ($left['current_name'] ?? ''), (string) ($right['current_name'] ?? '')),
                default => strcmp((string) ($left['taken_at'] ?? $left['date_taken'] ?? ''), (string) ($right['taken_at'] ?? $right['date_taken'] ?? ''))
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
