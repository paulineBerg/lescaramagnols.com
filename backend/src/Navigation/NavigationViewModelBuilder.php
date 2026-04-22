<?php

declare(strict_types=1);

namespace Caramagnols\Navigation;

use Caramagnols\Content\PageRepository;

final class NavigationViewModelBuilder
{
    private const FOOTER_NOTICE_FALLBACK_TRANSLATION_KEY = 'TXT_PiedPageModele';
    private const FOOTER_NOTICE_FALLBACK_LANGUAGE = 'fr';
    private const DESKTOP_MEGA_SECTION_ITEM_LIMIT = 5;
    private string $runtimeLanguage = self::FOOTER_NOTICE_FALLBACK_LANGUAGE;

    /**
     * @param array<int, string> $availableLanguages
     */
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly array $availableLanguages = ['fr', 'de', 'en']
    ) {
    }

    /**
     * @param array<string, mixed> $canonical
     * @return array<string, mixed>
     */
    public function build(array $canonical, string $language = 'fr', string $requestUri = '/'): array
    {
        $this->runtimeLanguage = $this->normalizeLanguageCode($language) ?? self::FOOTER_NOTICE_FALLBACK_LANGUAGE;
        $path = $this->normalizeCurrentPath($requestUri);
        $translations = $this->translationsFor($this->runtimeLanguage);
        $locations = is_array($canonical['locations'] ?? null) ? $canonical['locations'] : [];
        $brandLabel = $this->translationForKeyInMap('TXT_SITE_BRAND', $translations) ?? 'LesCaramagnols';

        return [
            'brand' => [
                'label' => $brandLabel,
                'href' => '/',
                'logo' => '/assets/images/structure/favicon-48x48.png',
            ],
            'utility' => $this->buildNavigationItems(
                is_array($locations['utility'] ?? null) ? $locations['utility'] : [],
                $translations,
                $path
            ),
            'banner' => $this->buildBanner(
                is_array($locations['banner'] ?? null) ? $locations['banner'] : [],
                $translations
            ),
            'primary' => $this->buildNavigationItems(
                is_array($locations['primary'] ?? null) ? $locations['primary'] : [],
                $translations,
                $path
            ),
            'footer' => $this->buildNavigationItems(
                is_array($locations['footer'] ?? null) ? $locations['footer'] : [],
                $translations,
                $path
            ),
            'footerNotice' => $this->buildFooterNotice(
                is_array($locations['footerNotice'] ?? null) ? $locations['footerNotice'] : [],
                $translations,
                $this->runtimeLanguage
            ),
            'sideLeft' => $this->buildCardItems(
                is_array($locations['sideLeft'] ?? null) ? $locations['sideLeft'] : [],
                $translations,
                $path
            ),
            'sideRight' => $this->buildCardItems(
                is_array($locations['sideRight'] ?? null) ? $locations['sideRight'] : [],
                $translations,
                $path
            ),
            'languages' => $this->buildLanguageLinks($requestUri, $this->runtimeLanguage, $translations),
            'search' => [
                'action' => '/search',
                'currentLanguage' => $this->runtimeLanguage,
                'label' => $this->resolveText(['translationKey' => 'MENU_RECHERCHE'], $translations),
                'placeholder' => $this->resolveText(['translationKey' => 'MENU_RECHERCHER'], $translations) . '...',
            ],
            'meta' => [
                'currentPath' => $path,
                'currentLanguage' => $this->runtimeLanguage,
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function buildNavigationItems(array $items, array $translations, string $currentPath): array
    {
        $resolved = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $resolved[] = $this->buildNavigationItem($item, $translations, $currentPath);
        }

        return $resolved;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function buildNavigationItem(array $item, array $translations, string $currentPath): array
    {
        $children = $this->buildNavigationItems(
            is_array($item['children'] ?? null) ? $item['children'] : [],
            $translations,
            $currentPath
        );
        $target = is_array($item['target'] ?? null) ? $item['target'] : [];
        $href = $this->resolveHref($target);
        $internalLabel = $this->resolveNavigationInternalLabel($item['label'] ?? null, $translations);
        $label = $this->resolveNavigationLabel($item['label'] ?? null, $translations);
        $kind = strtolower(trim((string) ($item['kind'] ?? 'route')));
        $isExternal = $this->isExternalHref($href);
        $active = $this->matchesCurrentPath($href, $currentPath) || $this->childrenContainActive($children);
        $presentation = $this->buildPresentation($item, $translations, $currentPath);
        $panelKind = $children !== []
            ? (($presentation['displayMode'] ?? 'dropdown') === 'mega' ? 'mega' : 'dropdown')
            : null;

        return [
            'id' => $this->stringOrNull($item['id'] ?? null) ?? 'nav-item',
            'kind' => $kind,
            'label' => $label,
            'rawLabel' => $internalLabel,
            'href' => $href,
            'active' => $active,
            'hasChildren' => $children !== [],
            'children' => $children,
            'external' => $isExternal,
            'openInNewTab' => (bool) ($target['openInNewTab'] ?? $isExternal),
            'image' => $this->stringOrNull($item['media']['image'] ?? null),
            'alt' => $this->resolveText(
                ['text' => $item['accessibility']['alt'] ?? null],
                $translations,
                $label ?? $internalLabel
            ),
            'title' => $this->resolveText(
                ['text' => $item['accessibility']['title'] ?? null],
                $translations,
                $label ?? $internalLabel
            ),
            'presentation' => $presentation,
            'panelKind' => $panelKind,
            'mega' => $panelKind === 'mega'
                ? $this->buildMegaMenu($children, $presentation)
                : null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function buildCardItems(array $items, array $translations, string $currentPath): array
    {
        $cards = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $target = is_array($item['target'] ?? null) ? $item['target'] : [];
            $href = $this->resolveHref($target);
            $internalLabel = $this->resolveNavigationInternalLabel($item['label'] ?? null, $translations);
            $label = $this->resolveNavigationLabel($item['label'] ?? null, $translations);

            $cards[] = [
                'id' => $this->stringOrNull($item['id'] ?? null) ?? 'card-item',
                'label' => $label,
                'rawLabel' => $internalLabel,
                'href' => $href,
                'active' => $this->matchesCurrentPath($href, $currentPath),
                'image' => $this->stringOrNull($item['media']['image'] ?? null),
                'text' => $this->stringOrNull($item['content']['text'] ?? null),
                'alt' => $this->resolveText(
                    ['text' => $item['accessibility']['alt'] ?? null],
                    $translations,
                    $label ?? $internalLabel
                ),
                'title' => $this->resolveText(
                    ['text' => $item['accessibility']['title'] ?? null],
                    $translations,
                    $label ?? $internalLabel
                ),
            ];
        }

        return $cards;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function buildPresentation(array $item, array $translations, string $currentPath): array
    {
        $presentation = is_array($item['presentation'] ?? null) ? $item['presentation'] : [];
        $featuredRaw = is_array($presentation['featuredCard'] ?? null) ? $presentation['featuredCard'] : [];
        $featuredTarget = is_array($featuredRaw['target'] ?? null) ? $featuredRaw['target'] : [];
        $featuredHref = $this->resolveHref($featuredTarget);
        $featuredTitle = $this->stringOrNull($featuredRaw['title'] ?? null);
        $featuredText = $this->stringOrNull($featuredRaw['text'] ?? null);
        $featuredImage = $this->stringOrNull($featuredRaw['image'] ?? null);
        $featuredCtaLabel = $this->stringOrNull($featuredRaw['ctaLabel'] ?? null);
        $featuredHasContent = $featuredTitle !== null
            || $featuredText !== null
            || $featuredImage !== null
            || $featuredCtaLabel !== null
            || $featuredHref !== null;

        return [
            'displayMode' => $this->stringOrNull($presentation['displayMode'] ?? null)
                ?? (((string) ($item['kind'] ?? 'route')) === 'group' ? 'dropdown' : 'link'),
            'columnCount' => is_numeric($presentation['columnCount'] ?? null)
                ? max(2, min(4, (int) $presentation['columnCount']))
                : 3,
            'menuTemplate' => $this->stringOrNull($presentation['menuTemplate'] ?? null) ?? 'standard',
            'isHighlight' => !empty($presentation['isHighlight']),
            'featuredCard' => $featuredHasContent ? [
                'title' => $featuredTitle,
                'text' => $featuredText,
                'image' => $featuredImage,
                'ctaLabel' => $featuredCtaLabel,
                'href' => $featuredHref,
                'active' => $this->matchesCurrentPath($featuredHref, $currentPath),
                'external' => $this->isExternalHref($featuredHref),
                'openInNewTab' => !empty($featuredTarget['openInNewTab']) || $this->isExternalHref($featuredHref),
            ] : null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $children
     * @param array<string, mixed> $presentation
     * @return array<string, mixed>
     */
    private function buildMegaMenu(array $children, array $presentation): array
    {
        $sections = [];

        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }

            $grandChildren = is_array($child['children'] ?? null) ? $child['children'] : [];

            if (($child['kind'] ?? null) === 'group' && $grandChildren !== []) {
                $childLabel = trim((string) ($child['label'] ?? ''));
                $childRawLabel = trim((string) ($child['rawLabel'] ?? $child['label'] ?? ''));

                if ($childRawLabel === '') {
                    foreach ($grandChildren as $grandChildIndex => $grandChild) {
                        if (!is_array($grandChild)) {
                            continue;
                        }

                        $sections[] = [
                            'id' => ($child['id'] ?? 'section') . '-flattened-' . $grandChildIndex,
                            'label' => null,
                            'href' => null,
                            'itemColumns' => [[$grandChild]],
                            'columnSpan' => 1,
                        ];
                    }
                    continue;
                }

                $itemColumns = $this->chunkMegaSectionItems($grandChildren, self::DESKTOP_MEGA_SECTION_ITEM_LIMIT);
                $sections[] = [
                    'id' => $child['id'] ?? 'section',
                    'label' => $childLabel !== '' ? $childLabel : null,
                    'href' => $child['href'] ?? null,
                    'itemColumns' => $itemColumns,
                    'columnSpan' => max(1, count($itemColumns)),
                ];
                continue;
            }

            $sections[] = [
                'id' => $child['id'] ?? 'section',
                'label' => null,
                'href' => null,
                'itemColumns' => [[$child]],
                'columnSpan' => 1,
            ];
        }

        $columnCount = is_numeric($presentation['columnCount'] ?? null)
            ? max(2, min(4, (int) $presentation['columnCount']))
            : 3;

        return [
            'sections' => $sections,
            'featuredCard' => is_array($presentation['featuredCard'] ?? null) ? $presentation['featuredCard'] : null,
            'menuTemplate' => $presentation['menuTemplate'] ?? 'standard',
            'columnCount' => $columnCount,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function chunkMegaSectionItems(array $items, int $itemLimit): array
    {
        $normalizedItems = array_values(
            array_filter(
                $items,
                static fn (mixed $item): bool => is_array($item)
            )
        );

        if ($normalizedItems === []) {
            return [[]];
        }

        if ($itemLimit < 1 || count($normalizedItems) <= $itemLimit) {
            return [$normalizedItems];
        }

        return array_values(array_filter(
            array_chunk($normalizedItems, $itemLimit),
            static fn (array $chunk): bool => $chunk !== []
        ));
    }

    /**
     * @param array<string, mixed> $banner
     * @return array<string, string|null>
     */
    private function buildBanner(array $banner, array $translations): array
    {
        return [
            'image' => $this->stringOrNull($banner['image'] ?? null),
            'headline' => $this->resolveText($banner['headline'] ?? null, $translations),
            'alt' => $this->resolveText(
                ['text' => $banner['accessibility']['alt'] ?? null],
                $translations
            ),
            'title' => $this->resolveText(
                ['text' => $banner['accessibility']['title'] ?? null],
                $translations
            ),
        ];
    }

    /**
     * @param array<string, mixed> $footerNotice
     * @param array<string, string> $currentTranslations
     * @return array{
     *   text: string|null,
     *   defaultLanguage: string,
     *   translationKey: string|null,
     *   translations: array<string, string>
     * }
     */
    private function buildFooterNotice(array $footerNotice, array $currentTranslations, string $currentLanguage): array
    {
        $defaultLanguage = $this->stringOrNull($footerNotice['defaultLanguage'] ?? null)
            ?? self::FOOTER_NOTICE_FALLBACK_LANGUAGE;
        $translationKey = $this->stringOrNull($footerNotice['translationKey'] ?? null)
            ?? self::FOOTER_NOTICE_FALLBACK_TRANSLATION_KEY;
        $translationsInput = is_array($footerNotice['translations'] ?? null) ? $footerNotice['translations'] : [];
        $translations = [];

        foreach ($translationsInput as $language => $value) {
            if (!is_string($language)) {
                continue;
            }

            $language = strtolower(trim($language));
            $normalized = $this->stringOrNull($value);
            if ($language === '' || $normalized === null) {
                continue;
            }

            $translations[$language] = $normalized;
        }

        $resolvedText = $translations[$currentLanguage]
            ?? $translations[$defaultLanguage]
            ?? null;

        if ($resolvedText === null && $translationKey !== null) {
            $resolvedText = $this->translationForKeyInMap($translationKey, $currentTranslations);

            if ($resolvedText === null && $defaultLanguage !== $currentLanguage) {
                $resolvedText = $this->translationForKeyInMap(
                    $translationKey,
                    $this->translationsFor($defaultLanguage)
                );
            }
        }

        return [
            'text' => $resolvedText,
            'defaultLanguage' => $defaultLanguage,
            'translationKey' => $translationKey,
            'translations' => $translations,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildLanguageLinks(string $requestUri, string $currentLanguage, array $translations): array
    {
        $labels = [
            'fr' => [
                'label' => $this->translationForKeyInMap('TXT_LANGUAGE_FR_LABEL', $translations) ?? 'Français',
                'flag' => '/assets/images/structure/menu/drapeaufranc.gif',
            ],
            'de' => [
                'label' => $this->translationForKeyInMap('TXT_LANGUAGE_DE_LABEL', $translations) ?? 'Allemand',
                'flag' => '/assets/images/structure/menu/drapeauallem.gif',
            ],
            'en' => [
                'label' => $this->translationForKeyInMap('TXT_LANGUAGE_EN_LABEL', $translations) ?? 'Anglais',
                'flag' => '/assets/images/structure/menu/drapeauangl.gif',
            ],
        ];

        $path = parse_url($requestUri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';
        $path = normalize_public_route($path) ?? '/';
        $query = [];
        parse_str((string) parse_url($requestUri, PHP_URL_QUERY), $query);

        $links = [];

        foreach ($this->availableLanguages as $language) {
            $languageKey = is_string($language) ? trim($language) : '';
            if ($languageKey === '') {
                continue;
            }

            $languageQuery = $query;
            $languageQuery['lang'] = $languageKey;
            $queryString = http_build_query($languageQuery);

            $links[] = [
                'code' => $languageKey,
                'label' => $labels[$languageKey]['label'] ?? strtoupper($languageKey),
                'flag' => $labels[$languageKey]['flag'] ?? null,
                'href' => $queryString !== '' ? $path . '?' . $queryString : $path,
                'active' => $languageKey === $currentLanguage,
            ];
        }

        return $links;
    }

    /**
     * @param array<string, mixed> $target
     */
    private function resolveHref(array $target): ?string
    {
        $pageSlug = $this->stringOrNull($target['pageSlug'] ?? null);
        if ($pageSlug !== null) {
            $page = $this->pageRepository->findBySlug($pageSlug);
            if ($page === null || ($page['status'] ?? PageRepository::STATUS_DRAFT) !== PageRepository::STATUS_PUBLISHED) {
                return null;
            }

            $route = $this->stringOrNull($page['route'] ?? null);

            return $route !== null
                ? (normalize_public_route($route) ?? $route)
                : '/' . ltrim($pageSlug, '/');
        }

        $route = $this->stringOrNull($target['route'] ?? null);
        if ($route !== null) {
            return normalize_public_route($route) ?? $route;
        }

        return $this->stringOrNull($target['url'] ?? null);
    }

    private function resolveText(mixed $value, array $translations, ?string $fallback = null): ?string
    {
        if (is_array($value)) {
            $localizedTranslations = $this->normalizeLocalizedTranslations($value['translations'] ?? null);
            $defaultLanguage = $this->normalizeLanguageCode($value['defaultLanguage'] ?? null)
                ?? self::FOOTER_NOTICE_FALLBACK_LANGUAGE;

            if ($localizedTranslations !== []) {
                if (is_string($localizedTranslations[$this->runtimeLanguage] ?? null)) {
                    return (string) $localizedTranslations[$this->runtimeLanguage];
                }

                if (is_string($localizedTranslations[$defaultLanguage] ?? null)) {
                    return (string) $localizedTranslations[$defaultLanguage];
                }

                $firstTranslation = array_values($localizedTranslations)[0] ?? null;
                if (is_string($firstTranslation) && trim($firstTranslation) !== '') {
                    return trim($firstTranslation);
                }
            }

            $text = $this->stringOrNull($value['text'] ?? null);
            if ($text !== null) {
                return $text;
            }

            $translationKey = $this->stringOrNull($value['translationKey'] ?? null);
            if ($translationKey !== null) {
                return is_string($translations[$translationKey] ?? null)
                    ? (string) $translations[$translationKey]
                    : '[[' . $translationKey . ']]';
            }
        }

        $stringValue = $this->stringOrNull($value);
        if ($stringValue !== null) {
            return $stringValue;
        }

        return $fallback;
    }

    private function resolveNavigationLabel(mixed $value, array $translations): ?string
    {
        if (!is_array($value)) {
            // Legacy format: keep scalar labels visible.
            return $this->stringOrNull($value);
        }

        $localizedTranslations = $this->normalizeLocalizedTranslations($value['translations'] ?? null);
        $defaultLanguage = $this->normalizeLanguageCode($value['defaultLanguage'] ?? null)
            ?? self::FOOTER_NOTICE_FALLBACK_LANGUAGE;

        if ($localizedTranslations !== []) {
            if (is_string($localizedTranslations[$this->runtimeLanguage] ?? null)) {
                return (string) $localizedTranslations[$this->runtimeLanguage];
            }

            if (is_string($localizedTranslations[$defaultLanguage] ?? null)) {
                return (string) $localizedTranslations[$defaultLanguage];
            }

            $firstTranslation = array_values($localizedTranslations)[0] ?? null;
            if (is_string($firstTranslation) && trim($firstTranslation) !== '') {
                return trim($firstTranslation);
            }
        }

        $translationKey = $this->stringOrNull($value['translationKey'] ?? null);
        if ($translationKey !== null) {
            return $this->translationForKeyInMap($translationKey, $translations);
        }

        // Label text is used as editor/internal name only and must not be rendered on front.
        return null;
    }

    private function resolveNavigationInternalLabel(mixed $value, array $translations): ?string
    {
        if (!is_array($value)) {
            return $this->stringOrNull($value);
        }

        $text = $this->stringOrNull($value['text'] ?? null);
        if ($text !== null) {
            return $text;
        }

        $localizedTranslations = $this->normalizeLocalizedTranslations($value['translations'] ?? null);
        $defaultLanguage = $this->normalizeLanguageCode($value['defaultLanguage'] ?? null)
            ?? self::FOOTER_NOTICE_FALLBACK_LANGUAGE;

        if ($localizedTranslations !== []) {
            if (is_string($localizedTranslations[$this->runtimeLanguage] ?? null)) {
                return (string) $localizedTranslations[$this->runtimeLanguage];
            }

            if (is_string($localizedTranslations[$defaultLanguage] ?? null)) {
                return (string) $localizedTranslations[$defaultLanguage];
            }

            $firstTranslation = array_values($localizedTranslations)[0] ?? null;
            if (is_string($firstTranslation) && trim($firstTranslation) !== '') {
                return trim($firstTranslation);
            }
        }

        $translationKey = $this->stringOrNull($value['translationKey'] ?? null);
        if ($translationKey === null) {
            return null;
        }

        return $this->translationForKeyInMap($translationKey, $translations) ?? $translationKey;
    }

    private function normalizeCurrentPath(string $requestUri): string
    {
        $path = parse_url($requestUri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return '/';
        }

        return normalize_public_route($path) ?? $path;
    }

    private function matchesCurrentPath(?string $href, string $currentPath): bool
    {
        if ($href === null || $this->isExternalHref($href)) {
            return false;
        }

        $hrefPath = parse_url($href, PHP_URL_PATH);
        if (!is_string($hrefPath) || $hrefPath === '') {
            return false;
        }

        if ($hrefPath === '/') {
            return $currentPath === '/';
        }

        return $currentPath === $hrefPath || str_starts_with($currentPath, rtrim($hrefPath, '/') . '/');
    }

    /**
     * @param array<int, array<string, mixed>> $children
     */
    private function childrenContainActive(array $children): bool
    {
        foreach ($children as $child) {
            if (($child['active'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    private function isExternalHref(?string $href): bool
    {
        return is_string($href) && preg_match('#^https?://#i', $href) === 1;
    }

    /**
     * @return array<string, string>
     */
    private function translationsFor(string $language): array
    {
        if (function_exists('load_translations_cached')) {
            $translations = load_translations_cached($language);

            return is_array($translations) ? $translations : [];
        }

        return [];
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, string>
     */
    private function normalizeLocalizedTranslations(mixed $translations): array
    {
        if (!is_array($translations)) {
            return [];
        }

        $normalized = [];
        foreach ($translations as $language => $value) {
            $languageCode = $this->normalizeLanguageCode($language);
            $text = $this->stringOrNull($value);
            if ($languageCode === null || $text === null) {
                continue;
            }

            $normalized[$languageCode] = $text;
        }

        ksort($normalized);

        return $normalized;
    }

    private function normalizeLanguageCode(mixed $language): ?string
    {
        if (!is_string($language)) {
            return null;
        }

        $normalized = strtolower(trim($language));
        if ($normalized === '' || preg_match('/^[a-z]{2,5}$/', $normalized) !== 1) {
            return null;
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $translations
     */
    private function translationForKeyInMap(string $key, array $translations): ?string
    {
        $translated = $translations[$key] ?? null;

        return is_string($translated) && trim($translated) !== '' ? $translated : null;
    }
}
