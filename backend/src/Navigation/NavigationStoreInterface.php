<?php

declare(strict_types=1);

namespace Caramagnols\Navigation;

interface NavigationStoreInterface
{
    /**
     * @return array<string, mixed>
     */
    public function loadCanonical(array $fallbackLegacy = []): array;

    /**
     * @return array<string, mixed>
     */
    public function loadLegacyConfig(array $fallbackLegacy = []): array;

    public function saveLegacyConfig(array $legacy): bool;

    /**
     * @param array<string, mixed> $canonical
     */
    public function saveCanonical(array $canonical): bool;

    public function clearCache(): void;
}
