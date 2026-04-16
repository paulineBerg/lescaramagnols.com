<?php

declare(strict_types=1);

namespace Caramagnols\Content;

final class PagePayloadNormalizer
{
    public function __construct(
        private readonly StructuredPageRenderer $renderer = new StructuredPageRenderer(),
        private readonly EditorialSectionValidator $sectionValidator = new EditorialSectionValidator()
    ) {
    }

    /**
     * @return array{meta: array<string, mixed>, pages: array<int, array<string, mixed>>}
     */
    public function emptyRegistry(): array
    {
        return [
            'meta' => ['version' => PageRepository::SCHEMA_VERSION],
            'pages' => [],
        ];
    }

    /**
     * @param array<string, mixed> $page
     * @return array<string, mixed>|null
     */
    public function normalizePage(array $page): ?array
    {
        $slug = trim((string) ($page['slug'] ?? ''));
        if ($slug === '') {
            return null;
        }

        $type = PageRepository::TYPE_STRUCTURED_PAGE;
        $status = $this->normalizeStatus((string) ($page['status'] ?? PageRepository::STATUS_PUBLISHED));

        $route = $this->normalizeRoute((string) ($page['route'] ?? ''));
        if ($route === null) {
            $route = '/' . $slug;
        }

        $blocks = $this->normalizeSectionCollection($page['blocks'] ?? [], 'blocks');
        $regions = $this->normalizeSectionCollection($page['regions'] ?? [], 'regions');
        $translations = $this->normalizeTranslations($page['translations'] ?? []);

        if ($blocks === null || $regions === null || $translations === null) {
            return null;
        }

        return [
            'slug' => $slug,
            'type' => $type,
            'status' => $status,
            'title' => isset($page['title']) ? (string) $page['title'] : null,
            'layout' => trim((string) ($page['layout'] ?? 'standard_page')),
            'route' => $route,
            'blocks' => $blocks,
            'regions' => $regions,
            'translations' => $translations,
            'meta' => is_array($page['meta'] ?? null) ? $page['meta'] : [],
        ];
    }

    /**
     * @param array<string, mixed> $page
     * @return array<string, mixed>|null
     */
    public function buildRenderableStructuredPage(array $page, string $lang, string $fallbackLang): ?array
    {
        if (($page['status'] ?? PageRepository::STATUS_PUBLISHED) !== PageRepository::STATUS_PUBLISHED) {
            return null;
        }

        if (($page['type'] ?? PageRepository::TYPE_STRUCTURED_PAGE) !== PageRepository::TYPE_STRUCTURED_PAGE) {
            return null;
        }

        $translations = is_array($page['translations'] ?? null) ? $page['translations'] : [];
        $translation = $translations[$lang] ?? null;

        if ($translation === null && $fallbackLang !== $lang) {
            $translation = $translations[$fallbackLang] ?? null;
        }

        if ($translation === null && $translations !== []) {
            $translation = reset($translations);
        }

        $translation = is_array($translation) ? $translation : [];
        $baseRegions = is_array($page['regions'] ?? null) ? $page['regions'] : [];
        $baseBlocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : [];
        $translationRegions = is_array($translation['regions'] ?? null) ? $translation['regions'] : [];
        $translationBlocks = is_array($translation['blocks'] ?? null) ? $translation['blocks'] : [];

        $blocks = array_merge(
            self::blockDefaults(),
            $this->renderer->renderRegions($baseRegions),
            $baseBlocks,
            $this->renderer->renderRegions($translationRegions),
            $translationBlocks
        );

        $metaBase = is_array($page['meta'] ?? null) ? $page['meta'] : [];
        $metaTranslated = is_array($translation['meta'] ?? null) ? $translation['meta'] : [];
        $slug = (string) ($page['slug'] ?? '');

        return [
            'slug' => $slug,
            'type' => $page['type'] ?? PageRepository::TYPE_STRUCTURED_PAGE,
            'layout' => $page['layout'] ?? 'standard_page',
            'route' => normalize_public_route((string) ($page['route'] ?? '')) ?? '/' . $slug,
            'title' => (string) ($translation['title'] ?? $page['title'] ?? $slug),
            'blocks' => $blocks,
            'meta' => array_merge($metaBase, $metaTranslated),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function blockDefaults(): array
    {
        $defaults = [];

        for ($i = 1; $i <= 12; $i++) {
            $defaults['EditRegion' . $i] = '';
        }

        return $defaults;
    }

    /**
     * @param mixed $translations
     * @return array<string, array<string, mixed>>|null
     */
    private function normalizeTranslations(mixed $translations): ?array
    {
        if (!is_array($translations)) {
            return [];
        }

        $normalized = [];

        foreach ($translations as $locale => $translation) {
            if (!is_string($locale) || !is_array($translation)) {
                continue;
            }

            $blocks = $this->normalizeSectionCollection($translation['blocks'] ?? [], 'blocks');
            $regions = $this->normalizeSectionCollection($translation['regions'] ?? [], 'regions');

            if ($blocks === null || $regions === null) {
                return null;
            }

            $normalized[$locale] = [
                'title' => isset($translation['title']) ? (string) $translation['title'] : null,
                'blocks' => $blocks,
                'regions' => $regions,
                'meta' => is_array($translation['meta'] ?? null) ? $translation['meta'] : [],
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeSectionCollection(mixed $sections, string $sectionGroup): ?array
    {
        if (!is_array($sections)) {
            return [];
        }

        $normalized = [];

        foreach ($sections as $sectionKey => $sectionPayload) {
            if (!is_string($sectionKey) || !$this->sectionValidator->allows($sectionGroup, $sectionKey)) {
                return null;
            }

            if ($sectionGroup === 'regions' && !is_array($sectionPayload)) {
                return null;
            }

            $normalized[$sectionKey] = $sectionPayload;
        }

        return $normalized;
    }

    private function normalizeStatus(string $status): string
    {
        return strtolower(trim($status)) === PageRepository::STATUS_DRAFT
            ? PageRepository::STATUS_DRAFT
            : PageRepository::STATUS_PUBLISHED;
    }

    private function normalizeRoute(string $route): ?string
    {
        return normalize_public_route($route);
    }
}
