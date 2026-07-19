<?php

declare(strict_types=1);

namespace Caramagnols\Admin\Navigation;

final class NavigationItemPathCodec
{
    /**
     * @param array<int, string> $availableLocations
     */
    public function __construct(
        private readonly array $availableLocations,
        private readonly string $defaultLocation
    ) {
    }

    /**
     * @return array{location: string, indices: array<int, int>}|null
     */
    public function parse(?string $path): ?array
    {
        if ($path === null || $path === '') {
            return null;
        }

        $segments = array_values(array_filter(explode('|', $path), static fn (string $part): bool => $part !== ''));
        if ($segments === []) {
            return null;
        }

        $location = $this->normalizeLocation((string) array_shift($segments));
        $indices = [];

        foreach ($segments as $segment) {
            if (!ctype_digit($segment)) {
                return null;
            }

            $indices[] = (int) $segment;
        }

        if ($indices === []) {
            return null;
        }

        return [
            'location' => $location,
            'indices' => $indices,
        ];
    }

    /**
     * @param array<int, int> $indices
     */
    public function encode(string $location, array $indices): string
    {
        return $this->normalizeLocation($location) . '|' . implode('|', $indices);
    }

    /**
     * @param array<int, int> $indices
     */
    public function inputName(string $location, array $indices): string
    {
        $name = sprintf('locations[%s]', $this->normalizeLocation($location));
        $lastIndex = count($indices) - 1;

        foreach ($indices as $offset => $index) {
            $name .= sprintf('[%d]', $index);

            if ($offset < $lastIndex) {
                $name .= '[children]';
            }
        }

        return $name;
    }

    private function normalizeLocation(string $location): string
    {
        return in_array($location, $this->availableLocations, true)
            ? $location
            : $this->defaultLocation;
    }
}
