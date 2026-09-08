<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Domain;

final class PhotoCommuneNormalizer
{
    public function __construct(private readonly PhotoFilenameNormalizer $filenameNormalizer = new PhotoFilenameNormalizer())
    {
    }

    public function normalize(mixed $value): ?PhotoCommune
    {
        if (!is_string($value)) {
            return null;
        }

        $name = $this->filenameNormalizer->normalizePart($value, '-');
        if ($name === '') {
            return null;
        }

        $key = strtolower($name);
        $key = preg_replace('/[^a-z0-9-]+/', '-', $key) ?? '';
        $key = trim(preg_replace('/-+/', '-', $key) ?? '', '-');
        if ($key === '') {
            return null;
        }

        return new PhotoCommune($key, $this->displayName($name));
    }

    private function displayName(string $name): string
    {
        $parts = explode('-', strtolower($name));
        $parts = array_map(
            static fn (string $part): string => $part !== '' ? ucfirst($part) : '',
            $parts
        );

        return implode('-', array_filter($parts, static fn (string $part): bool => $part !== ''));
    }
}
