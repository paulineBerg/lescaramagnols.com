<?php

declare(strict_types=1);

namespace Caramagnols\Content;

final class EditorialSectionValidator
{
    /**
     * @return array<int, string>
     */
    public function allowedRegionKeys(): array
    {
        return array_keys(StandardPageLayout::semanticSlots());
    }

    /**
     * @return array<int, string>
     */
    public function allowedBlockKeys(): array
    {
        $keys = [];

        for ($i = 1; $i <= 12; $i++) {
            $keys[] = 'EditRegion' . $i;
        }

        return $keys;
    }

    public function allows(string $sectionGroup, string $sectionKey): bool
    {
        return match ($sectionGroup) {
            'regions' => in_array($sectionKey, $this->allowedRegionKeys(), true),
            'blocks' => in_array($sectionKey, $this->allowedBlockKeys(), true),
            default => false,
        };
    }
}
