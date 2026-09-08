<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Domain;

final class PhotoDateResolver
{
    private const SOURCES = [
        'SubSecDateTimeOriginal',
        'DateTimeOriginal',
        'CreateDate',
        'MediaCreateDate',
        'taken_at',
        'date_taken',
        'filesystem_mtime',
    ];

    /**
     * @param array<string, mixed> $metadata
     */
    public function resolve(array $metadata, bool $allowFilesystemFallback = false): ResolvedPhotoDate
    {
        foreach (self::SOURCES as $source) {
            if ($source === 'filesystem_mtime' && !$allowFilesystemFallback) {
                continue;
            }

            $date = $this->parse($metadata[$source] ?? null, $metadata);
            if ($date instanceof \DateTimeImmutable) {
                return new ResolvedPhotoDate($date, $source);
            }
        }

        return new ResolvedPhotoDate(null, 'missing');
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function parse(mixed $value, array $metadata): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (is_int($value) || is_float($value)) {
            return (new \DateTimeImmutable('@' . (int) $value))->setTimezone(new \DateTimeZone('UTC'));
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        $offset = is_string($metadata['OffsetTimeOriginal'] ?? null) ? trim((string) $metadata['OffsetTimeOriginal']) : '';
        $normalized = preg_replace('/^(\d{4}):(\d{2}):(\d{2})\s+/', '$1-$2-$3 ', $value) ?? $value;

        if ($offset !== '' && preg_match('/[+-]\d{2}:?\d{2}\z/', $normalized) !== 1) {
            $normalized .= $offset;
        }

        try {
            return new \DateTimeImmutable($normalized);
        } catch (\Throwable) {
            return null;
        }
    }
}
