<?php

declare(strict_types=1);

namespace Caramagnols\PbGestion\Photo;

final class PhotoPathPolicy
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic'];

    public function isValidRootUid(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{2,63}\z/', $value) === 1;
    }

    public function normalizeRelativeDirectory(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim(str_replace('\\', '/', $value));
        if ($value === '' || $value === '.') {
            return '';
        }

        return $this->normalizeRelativePath($value, false);
    }

    public function normalizeRelativePhoto(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $path = $this->normalizeRelativePath(trim(str_replace('\\', '/', $value)), true);
        if ($path === null) {
            return null;
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, self::ALLOWED_EXTENSIONS, true) ? $path : null;
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, string>|null
     */
    public function normalizePhotoList(array $items, int $limit = 500): ?array
    {
        if ($items === [] || count($items) > $limit) {
            return null;
        }

        $normalized = [];
        foreach ($items as $item) {
            $photo = $this->normalizeRelativePhoto($item);
            if ($photo === null) {
                return null;
            }
            $normalized[] = $photo;
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeRelativePath(string $value, bool $requireFile): ?string
    {
        if (
            $value === ''
            || str_starts_with($value, '/')
            || str_starts_with($value, '//')
            || preg_match('/\A[A-Za-z]:/', $value) === 1
            || preg_match('/[\x00-\x1F<>:"|?*]/', $value) === 1
        ) {
            return null;
        }

        $segments = [];
        foreach (explode('/', $value) as $segment) {
            $segment = trim($segment);
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
            if (preg_match('/\A[. ]+|[. ]+\z/', $segment) === 1) {
                return null;
            }
            $segments[] = $segment;
        }

        if ($requireFile && (pathinfo((string) end($segments), PATHINFO_EXTENSION) === '')) {
            return null;
        }

        return implode('/', $segments);
    }
}
