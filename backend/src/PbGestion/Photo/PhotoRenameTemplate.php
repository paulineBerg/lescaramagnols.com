<?php

declare(strict_types=1);

namespace Caramagnols\PbGestion\Photo;

final class PhotoRenameTemplate
{
    private const ALLOWED_BLOCKS = ['text', 'city', 'department', 'region', 'country', 'date', 'time', 'counter', 'original'];

    public function __construct(private readonly PhotoFilenameNormalizer $normalizer = new PhotoFilenameNormalizer())
    {
    }

    /**
     * @param array<string, mixed> $photo
     * @param array<int, array<string, mixed>> $blocks
     */
    public function filename(array $photo, array $blocks, string $separator, int $counter, int $counterDigits): string
    {
        $separator = $this->normalizer->separator($separator);
        $counterDigits = max(1, min(6, $counterDigits));
        $parts = [];
        foreach ($this->normalizeBlocks($blocks) as $block) {
            $part = $this->part($photo, $block, $counter, $counterDigits);
            $part = $this->normalizer->normalizePart($part, $separator);
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        if ($parts === []) {
            $parts[] = $this->normalizer->normalizePart($this->originalBaseName($photo), $separator);
            $parts[] = str_pad((string) $counter, $counterDigits, '0', STR_PAD_LEFT);
        }

        $extension = (string) pathinfo($this->currentName($photo), PATHINFO_EXTENSION);

        return $this->normalizer->normalizeFilename(implode($separator, $parts), $extension, $separator);
    }

    /**
     * @param array<int, mixed> $blocks
     * @return array<int, array{type: string, value: string}>
     */
    public function normalizeBlocks(array $blocks): array
    {
        $normalized = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = is_string($block['type'] ?? null) ? strtolower(trim((string) $block['type'])) : '';
            if (!in_array($type, self::ALLOWED_BLOCKS, true)) {
                continue;
            }
            $normalized[] = [
                'type' => $type,
                'value' => is_string($block['value'] ?? null) ? trim((string) $block['value']) : '',
            ];
        }

        return $normalized !== [] ? $normalized : [
            ['type' => 'city', 'value' => ''],
            ['type' => 'date', 'value' => ''],
            ['type' => 'counter', 'value' => ''],
        ];
    }

    /**
     * @param array<string, mixed> $photo
     * @param array{type: string, value: string} $block
     */
    private function part(array $photo, array $block, int $counter, int $counterDigits): string
    {
        return match ($block['type']) {
            'text' => $block['value'],
            'city' => $this->stringValue($photo, 'city'),
            'department' => $this->stringValue($photo, 'department'),
            'region' => $this->stringValue($photo, 'region'),
            'country' => $this->stringValue($photo, 'country'),
            'date' => $this->datePart($photo),
            'time' => $this->timePart($photo),
            'counter' => str_pad((string) $counter, $counterDigits, '0', STR_PAD_LEFT),
            'original' => $this->originalBaseName($photo),
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $photo
     */
    private function datePart(array $photo): string
    {
        $timestamp = $this->timestamp($photo);

        return $timestamp !== null ? gmdate('Y-m-d', $timestamp) : '';
    }

    /**
     * @param array<string, mixed> $photo
     */
    private function timePart(array $photo): string
    {
        $timestamp = $this->timestamp($photo);

        return $timestamp !== null ? gmdate('H-i-s', $timestamp) : '';
    }

    /**
     * @param array<string, mixed> $photo
     */
    private function timestamp(array $photo): ?int
    {
        $value = $this->stringValue($photo, 'taken_at') ?: $this->stringValue($photo, 'date_taken');
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? $timestamp : null;
    }

    /**
     * @param array<string, mixed> $photo
     */
    private function originalBaseName(array $photo): string
    {
        return (string) pathinfo($this->currentName($photo), PATHINFO_FILENAME);
    }

    /**
     * @param array<string, mixed> $photo
     */
    private function currentName(array $photo): string
    {
        return $this->stringValue($photo, 'current_name') ?: $this->stringValue($photo, 'name');
    }

    /**
     * @param array<string, mixed> $photo
     */
    private function stringValue(array $photo, string $key): string
    {
        return is_string($photo[$key] ?? null) ? trim((string) $photo[$key]) : '';
    }
}
