<?php

declare(strict_types=1);

namespace Caramagnols\Content;

final class StandardPageRegionMapper
{
    /**
     * @var array<string, string>
     */
    private const REGION_TO_SLOT = [
        'hero' => 'EditRegion1',
        'intro' => 'EditRegion8',
        'aside' => 'EditRegion2',
        'body' => 'EditRegion3',
        'after_body' => 'EditRegion4',
        'left' => 'EditRegion5',
        'right' => 'EditRegion6',
        'bottom' => 'EditRegion7',
        'postscript' => 'EditRegion11',
        'footer' => 'EditRegion9',
    ];

    /**
     * @var array<string, string>
     */
    private const REGION_TO_FIELD = [
        'hero' => 'hero_html',
        'intro' => 'intro_html',
        'aside' => 'aside_html',
        'body' => 'body_html',
        'after_body' => 'after_body_html',
        'left' => 'left_html',
        'right' => 'right_html',
        'bottom' => 'bottom_html',
        'postscript' => 'postscript_html',
        'footer' => 'footer_html',
    ];

    public function __construct(
        private readonly StructuredPageRenderer $renderer = new StructuredPageRenderer()
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function emptyPlanFields(): array
    {
        $fields = [];

        foreach (self::REGION_TO_FIELD as $field) {
            $fields[$field] = '';
        }

        return $fields;
    }

    /**
     * @return array<string, string>
     */
    public static function regionToFieldMap(): array
    {
        return self::REGION_TO_FIELD;
    }

    /**
     * @param array<string, mixed> $translation
     * @return array<string, string>
     */
    public function formValuesFromTranslation(array $translation): array
    {
        $values = self::emptyPlanFields();
        $blocks = is_array($translation['blocks'] ?? null) ? $translation['blocks'] : [];
        $regions = is_array($translation['regions'] ?? null) ? $translation['regions'] : [];

        foreach (self::REGION_TO_FIELD as $regionName => $fieldName) {
            $slot = self::REGION_TO_SLOT[$regionName];
            $blockHtml = trim((string) ($blocks[$slot] ?? ''));
            if ($blockHtml !== '') {
                $values[$fieldName] = $blockHtml;
            }

            $regionHtml = $this->editableHtmlFromRegion($regionName, $regions[$regionName] ?? null);
            if ($regionHtml !== '') {
                $values[$fieldName] = $regionHtml;
            }
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $existingRegions
     * @return array<string, mixed>
     */
    public function buildRegionsFromPlanValues(array $input, array $existingRegions = []): array
    {
        $regions = [];

        foreach ($existingRegions as $regionName => $value) {
            if (!array_key_exists($regionName, self::REGION_TO_SLOT) && is_array($value)) {
                $regions[$regionName] = $value;
            }
        }

        foreach (self::REGION_TO_FIELD as $regionName => $fieldName) {
            $html = trim((string) ($input[$fieldName] ?? ''));
            $preservedComponents = $this->preservedComponents($existingRegions[$regionName] ?? null);
            $regionValue = $this->composeRegionValue($html, $preservedComponents);

            if ($regionValue !== null) {
                $regions[$regionName] = $regionValue;
            }
        }

        return $regions;
    }

    /**
     * @param array<string, string> $blocks
     * @param array<string, mixed> $existingRegions
     * @return array<string, mixed>
     */
    public function mapBlocksToRegions(array $blocks, array $existingRegions = []): array
    {
        $planValues = self::emptyPlanFields();

        foreach (self::REGION_TO_FIELD as $regionName => $fieldName) {
            $slot = self::REGION_TO_SLOT[$regionName];
            $planValues[$fieldName] = trim((string) ($blocks[$slot] ?? ''));

            if ($planValues[$fieldName] === '') {
                $planValues[$fieldName] = $this->editableHtmlFromRegion($regionName, $existingRegions[$regionName] ?? null);
            }
        }

        return $this->buildRegionsFromPlanValues($planValues, $existingRegions);
    }

    private function editableHtmlFromRegion(string $regionName, mixed $region): string
    {
        if (is_string($region)) {
            return trim($region);
        }

        if (!is_array($region)) {
            return '';
        }

        if (array_is_list($region)) {
            $parts = [];

            foreach ($region as $item) {
                if ($this->isPreservedComponent($item)) {
                    continue;
                }

                $html = $this->renderRegionValue($regionName, $item);
                if ($html !== '') {
                    $parts[] = $html;
                }
            }

            return trim(implode("\n", $parts));
        }

        if ($this->isPreservedComponent($region)) {
            return '';
        }

        return $this->renderRegionValue($regionName, $region);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function preservedComponents(mixed $region): array
    {
        if (!is_array($region)) {
            return [];
        }

        if ($this->isPreservedComponent($region)) {
            return [$region];
        }

        if (!array_is_list($region)) {
            return [];
        }

        $components = [];

        foreach ($region as $item) {
            if ($this->isPreservedComponent($item)) {
                $components[] = $item;
            }
        }

        return $components;
    }

    private function isPreservedComponent(mixed $value): bool
    {
        return is_array($value)
            && isset($value['component'])
            && is_string($value['component'])
            && $value['component'] === 'contact_form';
    }

    private function renderRegionValue(string $regionName, mixed $regionValue): string
    {
        if (is_string($regionValue)) {
            return trim($regionValue);
        }

        if (!is_array($regionValue)) {
            return '';
        }

        $slot = self::REGION_TO_SLOT[$regionName] ?? null;
        if ($slot === null) {
            return '';
        }

        $rendered = $this->renderer->renderRegions([$regionName => $regionValue]);

        return trim((string) ($rendered[$slot] ?? ''));
    }

    /**
     * @param array<int, array<string, mixed>> $preservedComponents
     * @return array<string, mixed>|array<int, array<string, mixed>>|null
     */
    private function composeRegionValue(string $html, array $preservedComponents): array|null
    {
        $items = [];

        if ($html !== '') {
            $items[] = [
                'component' => 'rich_text',
                'html' => $html,
            ];
        }

        foreach ($preservedComponents as $component) {
            $items[] = $component;
        }

        if ($items === []) {
            return null;
        }

        return count($items) === 1 ? $items[0] : $items;
    }
}
