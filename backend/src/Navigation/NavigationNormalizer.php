<?php

declare(strict_types=1);

namespace Caramagnols\Navigation;

final class NavigationNormalizer
{
    public const SCHEMA_VERSION = 2;
    private const FOOTER_NOTICE_DEFAULT_LANGUAGE = 'fr';

    /**
     * @return array<int, string>
     */
    public static function locationKeys(): array
    {
        return ['remonter', 'banner', 'footerNotice', 'utility', 'primary', 'footer', 'sideRight', 'sideLeft'];
    }

    /**
     * @return array<int, string>
     */
    public static function itemLocationKeys(): array
    {
        return ['utility', 'primary', 'footer', 'sideRight', 'sideLeft'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalizeLegacyConfig(array $menus): array
    {
        return [
            'remonter' => is_array($menus['remonter'] ?? null) ? $menus['remonter'] : [],
            'menu1' => is_array($menus['menu1'] ?? null) ? $menus['menu1'] : [],
            'banniere' => is_array($menus['banniere'] ?? null) ? $menus['banniere'] : [],
            'footerNotice' => is_array($menus['footerNotice'] ?? null)
                ? $menus['footerNotice']
                : (is_array($menus['footer_notice'] ?? null) ? $menus['footer_notice'] : []),
            'menu2' => is_array($menus['menu2'] ?? null) ? $menus['menu2'] : [],
            'menu3' => is_array($menus['menu3'] ?? null) ? $menus['menu3'] : [],
            'menuDroit' => is_array($menus['menuDroit'] ?? null)
                ? $menus['menuDroit']
                : (is_array($menus['menu_droit'] ?? null) ? $menus['menu_droit'] : []),
            'menuGauche' => is_array($menus['menuGauche'] ?? null)
                ? $menus['menuGauche']
                : (is_array($menus['menu_gauche'] ?? null) ? $menus['menu_gauche'] : []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function legacyToCanonical(array $legacy): array
    {
        $legacy = self::normalizeLegacyConfig($legacy);

        return [
            'meta' => [
                'version' => self::SCHEMA_VERSION,
            ],
            'locations' => [
                'remonter' => self::legacyBackToTopToCanonical($legacy['remonter']),
                'banner' => self::legacyBannerToCanonical($legacy['banniere']),
                'footerNotice' => self::legacyFooterNoticeToCanonical($legacy['footerNotice'] ?? []),
                'utility' => self::legacyItemsToCanonical($legacy['menu1'], 'utility', 0),
                'primary' => self::legacyItemsToCanonical($legacy['menu2'], 'primary', 0),
                'footer' => self::legacyItemsToCanonical($legacy['menu3'], 'footer', 0),
                'sideRight' => self::legacyItemsToCanonical($legacy['menuDroit'], 'sideRight', 0),
                'sideLeft' => self::legacyItemsToCanonical($legacy['menuGauche'], 'sideLeft', 0),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $canonical
     * @return array<string, mixed>
     */
    public static function canonicalToLegacy(array $canonical): array
    {
        $canonical = self::normalizeCanonical($canonical);
        $locations = is_array($canonical['locations'] ?? null) ? $canonical['locations'] : [];

        return [
            'remonter' => self::canonicalBackToTopToLegacy($locations['remonter'] ?? []),
            'menu1' => self::canonicalItemsToLegacy($locations['utility'] ?? [], 'utility'),
            'banniere' => self::canonicalBannerToLegacy($locations['banner'] ?? []),
            'footerNotice' => self::canonicalFooterNoticeToLegacy($locations['footerNotice'] ?? []),
            'menu2' => self::canonicalItemsToLegacy($locations['primary'] ?? [], 'primary'),
            'menu3' => self::canonicalItemsToLegacy($locations['footer'] ?? [], 'footer'),
            'menuDroit' => self::canonicalItemsToLegacy($locations['sideRight'] ?? [], 'sideRight'),
            'menuGauche' => self::canonicalItemsToLegacy($locations['sideLeft'] ?? [], 'sideLeft'),
        ];
    }

    /**
     * @param array<string, mixed> $canonical
     * @return array<string, mixed>
     */
    public static function normalizeCanonical(array $canonical): array
    {
        $locations = is_array($canonical['locations'] ?? null) ? $canonical['locations'] : [];

        return [
            'meta' => ['version' => self::SCHEMA_VERSION],
            'locations' => [
                'remonter' => self::normalizeCanonicalBackToTop($locations['remonter'] ?? []),
                'banner' => self::normalizeCanonicalBanner($locations['banner'] ?? []),
                'footerNotice' => self::normalizeCanonicalFooterNotice($locations['footerNotice'] ?? []),
                'utility' => self::normalizeCanonicalItems($locations['utility'] ?? [], 'utility', 0),
                'primary' => self::normalizeCanonicalItems($locations['primary'] ?? [], 'primary', 0),
                'footer' => self::normalizeCanonicalItems($locations['footer'] ?? [], 'footer', 0),
                'sideRight' => self::normalizeCanonicalItems($locations['sideRight'] ?? [], 'sideRight', 0),
                'sideLeft' => self::normalizeCanonicalItems($locations['sideLeft'] ?? [], 'sideLeft', 0),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $decoded
     */
    public static function looksLikeCanonical(array $decoded): bool
    {
        return is_array($decoded['locations'] ?? null);
    }

    /**
     * @param mixed $items
     * @return array<int, array<string, mixed>>
     */
    private static function legacyItemsToCanonical(mixed $items, string $location, int $depth): array
    {
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $children = self::legacyItemsToCanonical($item['sous_menu'] ?? [], $location, $depth + 1);
            $url = self::normalizeOptionalString($item['url'] ?? null);
            $path = self::normalizeOptionalString($item['chemin'] ?? null);
            $pageSlug = self::normalizeOptionalString($item['page_slug'] ?? null);
            $contentText = self::normalizeOptionalString($item['texte'] ?? null);
            $labelRaw = self::normalizeOptionalString($item['titre'] ?? null);

            if ($url === null && $path !== null && preg_match('#^https?://#i', $path) === 1) {
                $url = $path;
                $path = null;
            }

            $kind = 'route';
            if (in_array($location, ['sideRight', 'sideLeft'], true) && $children === []) {
                $kind = 'content_card';
            } elseif ($children !== []) {
                $kind = 'group';
            } elseif ($pageSlug !== null) {
                $kind = 'page';
            } elseif ($url !== null) {
                $kind = 'external';
            } elseif ($path === null || $path === '#') {
                $kind = 'group';
            }

            $normalized[] = [
                'id' => self::itemId($location, is_int($index) ? $index : count($normalized), $item),
                'kind' => $kind,
                'label' => self::normalizeLocalizedValue($labelRaw, ['MENU_']),
                'target' => [
                    'pageSlug' => $pageSlug,
                    'route' => $path !== null && $path !== '#' ? self::normalizeRoute($path) : null,
                    'url' => $url,
                    'openInNewTab' => $kind === 'external',
                ],
                'media' => [
                    'image' => self::normalizeOptionalString($item['image'] ?? null),
                ],
                'content' => [
                    'text' => $contentText,
                ],
                'accessibility' => [
                    'alt' => self::normalizeAccessibilityValue($item['alt'] ?? null, $labelRaw),
                    'title' => self::normalizeAccessibilityValue($item['title'] ?? null, $labelRaw),
                ],
                'presentation' => self::normalizeCanonicalPresentation(
                    [],
                    $kind,
                    $location,
                    $children,
                    $depth
                ),
                'children' => $children,
            ];
        }

        return $normalized;
    }

    /**
     * @param mixed $items
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeCanonicalItems(mixed $items, string $location, int $depth): array
    {
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $children = self::normalizeCanonicalItems($item['children'] ?? [], $location, $depth + 1);
            $target = is_array($item['target'] ?? null) ? $item['target'] : [];
            $pageSlug = self::normalizeOptionalString($target['pageSlug'] ?? null);
            $route = self::normalizeRoute((string) ($target['route'] ?? ''));
            $url = self::normalizeOptionalString($target['url'] ?? null);
            $kind = strtolower(trim((string) ($item['kind'] ?? '')));

            if (!in_array($kind, ['page', 'route', 'external', 'group', 'content_card'], true)) {
                if (in_array($location, ['sideRight', 'sideLeft'], true) && $children === []) {
                    $kind = 'content_card';
                } elseif ($children !== []) {
                    $kind = 'group';
                } elseif ($pageSlug !== null) {
                    $kind = 'page';
                } elseif ($url !== null) {
                    $kind = 'external';
                } else {
                    $kind = 'route';
                }
            } elseif (in_array($location, ['sideRight', 'sideLeft'], true) && $kind !== 'content_card' && $children === []) {
                $kind = 'content_card';
            }

            $normalized[] = [
                'id' => self::normalizeOptionalString($item['id'] ?? null)
                    ?? self::itemId($location, is_int($index) ? $index : count($normalized), $item),
                'kind' => $kind,
                'label' => self::normalizeLocalizedValue($item['label'] ?? null),
                'target' => [
                    'pageSlug' => $pageSlug,
                    'route' => $route,
                    'url' => $url,
                    'openInNewTab' => (bool) ($target['openInNewTab'] ?? ($kind === 'external')),
                ],
                'media' => [
                    'image' => self::normalizeOptionalString(($item['media']['image'] ?? null)),
                ],
                'content' => [
                    'text' => self::normalizeOptionalString(($item['content']['text'] ?? $item['texte'] ?? null)),
                ],
                'accessibility' => [
                    'alt' => self::normalizeOptionalString(($item['accessibility']['alt'] ?? null)),
                    'title' => self::normalizeOptionalString(($item['accessibility']['title'] ?? null)),
                ],
                'presentation' => self::normalizeCanonicalPresentation(
                    $item['presentation'] ?? [],
                    $kind,
                    $location,
                    $children,
                    $depth
                ),
                'children' => $children,
            ];
        }

        return $normalized;
    }

    /**
     * @param mixed $items
     * @return array<int, array<string, mixed>>
     */
    private static function canonicalItemsToLegacy(mixed $items, string $location): array
    {
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $children = self::canonicalItemsToLegacy($item['children'] ?? [], $location);
            $label = self::localizedValueToString($item['label'] ?? null);
            $target = is_array($item['target'] ?? null) ? $item['target'] : [];
            $pageSlug = self::normalizeOptionalString($target['pageSlug'] ?? null);
            $route = self::normalizeRoute((string) ($target['route'] ?? ''));
            $url = self::normalizeOptionalString($target['url'] ?? null);
            $kind = strtolower(trim((string) ($item['kind'] ?? 'route')));
            $contentText = self::normalizeOptionalString(($item['content']['text'] ?? null));

            $legacyItem = [];

            if ($label !== null) {
                $legacyItem['titre'] = $label;
            }

            if ($contentText !== null) {
                $legacyItem['texte'] = $contentText;
            }

            $alt = self::normalizeOptionalString(($item['accessibility']['alt'] ?? null)) ?? $label;
            $title = self::normalizeOptionalString(($item['accessibility']['title'] ?? null)) ?? $label;

            if ($alt !== null) {
                $legacyItem['alt'] = $alt;
            }

            if ($title !== null) {
                $legacyItem['title'] = $title;
            }

            $image = self::normalizeOptionalString(($item['media']['image'] ?? null));
            if ($image !== null) {
                $legacyItem['image'] = $image;
            }

            if ($location === 'utility') {
                if ($url !== null) {
                    $legacyItem['url'] = $url;
                } elseif ($route !== null) {
                    $legacyItem['url'] = $route;
                }
            } else {
                if ($pageSlug !== null) {
                    $legacyItem['page_slug'] = $pageSlug;
                    $legacyItem['chemin'] = '/' . ltrim($pageSlug, '/');
                } elseif ($route !== null) {
                    $legacyItem['chemin'] = $route;
                } elseif ($url !== null) {
                    $legacyItem['chemin'] = $url;
                } elseif ($children !== [] || $kind === 'group') {
                    $legacyItem['chemin'] = '#';
                } else {
                    $legacyItem['chemin'] = '';
                }
            }

            if ($children !== []) {
                $legacyItem['sous_menu'] = $children;
            }

            $normalized[] = $legacyItem;
        }

        return $normalized;
    }

    /**
     * @param mixed $banner
     * @return array<string, mixed>
     */
    private static function legacyBannerToCanonical(mixed $banner): array
    {
        if (!is_array($banner)) {
            return self::normalizeCanonicalBanner([]);
        }

        return [
            'image' => self::normalizeOptionalString($banner['image'] ?? null),
            'headline' => self::normalizeLocalizedValue($banner['texte_key'] ?? null, ['TXT_']),
            'accessibility' => [
                'alt' => self::normalizeAccessibilityValue($banner['alt'] ?? null, $banner['texte_key'] ?? null),
                'title' => self::normalizeAccessibilityValue($banner['title'] ?? null, $banner['texte_key'] ?? null),
            ],
        ];
    }

    /**
     * @param mixed $banner
     * @return array<string, mixed>
     */
    private static function normalizeCanonicalBanner(mixed $banner): array
    {
        if (!is_array($banner)) {
            $banner = [];
        }

        return [
            'image' => self::normalizeOptionalString($banner['image'] ?? null),
            'headline' => self::normalizeLocalizedValue($banner['headline'] ?? ($banner['texte_key'] ?? null)),
            'accessibility' => [
                'alt' => self::normalizeOptionalString(($banner['accessibility']['alt'] ?? $banner['alt'] ?? null)),
                'title' => self::normalizeOptionalString(($banner['accessibility']['title'] ?? $banner['title'] ?? null)),
            ],
        ];
    }

    /**
     * @param mixed $banner
     * @return array<string, mixed>
     */
    private static function canonicalBannerToLegacy(mixed $banner): array
    {
        if (!is_array($banner)) {
            return [];
        }

        return [
            'image' => self::normalizeOptionalString($banner['image'] ?? null) ?? '',
            'texte_key' => self::localizedValueToString($banner['headline'] ?? null) ?? '',
            'alt' => self::normalizeOptionalString(($banner['accessibility']['alt'] ?? null)) ?? '',
            'title' => self::normalizeOptionalString(($banner['accessibility']['title'] ?? null)) ?? '',
        ];
    }

    /**
     * @param mixed $backToTop
     * @return array<string, mixed>
     */
    private static function legacyBackToTopToCanonical(mixed $backToTop): array
    {
        if (!is_array($backToTop)) {
            return self::normalizeCanonicalBackToTop([]);
        }

        return [
            'label' => self::normalizeLocalizedValue($backToTop['titre'] ?? null, ['REMONTER_']),
            'accessibility' => [
                'alt' => self::normalizeAccessibilityValue($backToTop['alt'] ?? null, $backToTop['titre'] ?? null),
                'title' => self::normalizeAccessibilityValue($backToTop['title'] ?? null, $backToTop['titre'] ?? null),
            ],
        ];
    }

    /**
     * @param mixed $backToTop
     * @return array<string, mixed>
     */
    private static function normalizeCanonicalBackToTop(mixed $backToTop): array
    {
        if (!is_array($backToTop)) {
            $backToTop = [];
        }

        return [
            'label' => self::normalizeLocalizedValue($backToTop['label'] ?? ($backToTop['titre'] ?? null)),
            'accessibility' => [
                'alt' => self::normalizeOptionalString(($backToTop['accessibility']['alt'] ?? $backToTop['alt'] ?? null)),
                'title' => self::normalizeOptionalString(($backToTop['accessibility']['title'] ?? $backToTop['title'] ?? null)),
            ],
        ];
    }

    /**
     * @param mixed $backToTop
     * @return array<string, mixed>
     */
    private static function canonicalBackToTopToLegacy(mixed $backToTop): array
    {
        if (!is_array($backToTop)) {
            return [];
        }

        return [
            'titre' => self::localizedValueToString($backToTop['label'] ?? null) ?? '',
            'alt' => self::normalizeOptionalString(($backToTop['accessibility']['alt'] ?? null)) ?? '',
            'title' => self::normalizeOptionalString(($backToTop['accessibility']['title'] ?? null)) ?? '',
        ];
    }

    /**
     * @param mixed $footerNotice
     * @return array<string, mixed>
     */
    private static function legacyFooterNoticeToCanonical(mixed $footerNotice): array
    {
        if (!is_array($footerNotice)) {
            return self::normalizeCanonicalFooterNotice([]);
        }

        $translations = is_array($footerNotice['translations'] ?? null) ? $footerNotice['translations'] : [];
        $defaultLanguage = self::normalizeOptionalString($footerNotice['defaultLanguage'] ?? null)
            ?? self::FOOTER_NOTICE_DEFAULT_LANGUAGE;
        $translationKey = self::normalizeOptionalString($footerNotice['translationKey'] ?? null);

        return self::normalizeCanonicalFooterNotice([
            'translations' => $translations,
            'defaultLanguage' => $defaultLanguage,
            'translationKey' => $translationKey,
        ]);
    }

    /**
     * @param mixed $footerNotice
     * @return array<string, mixed>
     */
    private static function normalizeCanonicalFooterNotice(mixed $footerNotice): array
    {
        if (!is_array($footerNotice)) {
            $footerNotice = [];
        }

        $defaultLanguage = self::normalizeOptionalString($footerNotice['defaultLanguage'] ?? null);
        $translationKey = self::normalizeOptionalString($footerNotice['translationKey'] ?? null);

        $translationsInput = is_array($footerNotice['translations'] ?? null) ? $footerNotice['translations'] : [];
        $translations = [];

        foreach ($translationsInput as $language => $text) {
            if (!is_string($language)) {
                continue;
            }

            $language = strtolower(trim($language));
            if ($language === '') {
                continue;
            }

            $normalizedText = self::normalizeOptionalString($text);
            if ($normalizedText === null) {
                continue;
            }

            $translations[$language] = $normalizedText;
        }

        if ($translations === [] && $translationKey === null) {
            return [];
        }

        $defaultLanguage = $defaultLanguage ?? self::FOOTER_NOTICE_DEFAULT_LANGUAGE;

        return [
            'defaultLanguage' => $defaultLanguage,
            'translationKey' => $translationKey,
            'translations' => $translations,
        ];
    }

    /**
     * @param mixed $footerNotice
     * @return array<string, mixed>
     */
    private static function canonicalFooterNoticeToLegacy(mixed $footerNotice): array
    {
        if (!is_array($footerNotice)) {
            return [];
        }

        $normalized = self::normalizeCanonicalFooterNotice($footerNotice);
        if ($normalized === []) {
            return [];
        }

        return [
            'defaultLanguage' => $normalized['defaultLanguage'],
            'translationKey' => $normalized['translationKey'],
            'translations' => $normalized['translations'],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $children
     * @return array<string, mixed>
     */
    private static function normalizeCanonicalPresentation(
        mixed $presentation,
        string $kind,
        string $location,
        array $children,
        int $depth
    ): array {
        $presentation = is_array($presentation) ? $presentation : [];
        $displayMode = self::normalizeDisplayMode(
            $presentation['displayMode'] ?? null,
            $kind,
            $location,
            $children,
            $depth
        );

        return [
            'displayMode' => $displayMode,
            'columnCount' => $displayMode === 'mega'
                ? self::normalizeColumnCount($presentation['columnCount'] ?? null, $children)
                : null,
            'menuTemplate' => $displayMode === 'mega'
                ? (self::normalizeOptionalString($presentation['menuTemplate'] ?? null) ?? 'standard')
                : null,
            'isHighlight' => !empty($presentation['isHighlight']) || !empty($presentation['highlight']),
            'featuredCard' => self::normalizeFeaturedCard($presentation['featuredCard'] ?? []),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $children
     */
    private static function normalizeDisplayMode(
        mixed $displayMode,
        string $kind,
        string $location,
        array $children,
        int $depth
    ): string {
        if ($kind !== 'group') {
            return 'link';
        }

        $requested = strtolower(trim(is_string($displayMode) ? $displayMode : ''));

        if (in_array($requested, ['dropdown', 'mega'], true)) {
            return $requested;
        }

        if ($location === 'primary' && $depth === 0 && $children !== []) {
            return 'mega';
        }

        return 'dropdown';
    }

    /**
     * @param array<int, array<string, mixed>> $children
     */
    private static function normalizeColumnCount(mixed $value, array $children): int
    {
        $count = is_numeric($value) ? (int) $value : self::inferredColumnCount($children);

        return max(2, min(4, $count));
    }

    /**
     * @param array<int, array<string, mixed>> $children
     */
    private static function inferredColumnCount(array $children): int
    {
        $groupSections = 0;

        foreach ($children as $child) {
            if (is_array($child) && ($child['kind'] ?? null) === 'group' && ((array) ($child['children'] ?? [])) !== []) {
                $groupSections++;
            }
        }

        if ($groupSections > 0) {
            return max(2, min(4, $groupSections));
        }

        return max(2, min(4, count($children)));
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeFeaturedCard(mixed $featuredCard): array
    {
        $featuredCard = is_array($featuredCard) ? $featuredCard : [];
        $target = is_array($featuredCard['target'] ?? null) ? $featuredCard['target'] : [];

        return [
            'title' => self::normalizeOptionalString($featuredCard['title'] ?? null),
            'text' => self::normalizeOptionalString($featuredCard['text'] ?? null),
            'image' => self::normalizeOptionalString($featuredCard['image'] ?? null),
            'ctaLabel' => self::normalizeOptionalString($featuredCard['ctaLabel'] ?? null),
            'target' => [
                'pageSlug' => self::normalizeOptionalString($target['pageSlug'] ?? null),
                'route' => self::normalizeRoute((string) ($target['route'] ?? '')),
                'url' => self::normalizeOptionalString($target['url'] ?? null),
                'openInNewTab' => !empty($target['openInNewTab']),
            ],
        ];
    }

    /**
     * @param mixed $value
     * @param array<int, string>|null $preferredPrefixes
     * @return array{
     *   text: string|null,
     *   translationKey: string|null,
     *   defaultLanguage?: string,
     *   translations?: array<string, string>
     * }
     */
    private static function normalizeLocalizedValue(mixed $value, ?array $preferredPrefixes = null): array
    {
        if (is_array($value)) {
            $normalized = [
                'text' => self::normalizeOptionalString($value['text'] ?? null),
                'translationKey' => self::normalizeOptionalString($value['translationKey'] ?? null),
            ];

            $translations = self::normalizeLocalizedTranslations($value['translations'] ?? null);
            if ($translations !== []) {
                $defaultLanguage = self::normalizeLanguageCode($value['defaultLanguage'] ?? null)
                    ?? self::FOOTER_NOTICE_DEFAULT_LANGUAGE;
                if (!isset($translations[$defaultLanguage])) {
                    $firstLanguage = array_key_first($translations);
                    if (is_string($firstLanguage)) {
                        $defaultLanguage = $firstLanguage;
                    }
                }

                $normalized['defaultLanguage'] = $defaultLanguage;
                $normalized['translations'] = $translations;
            }

            return $normalized;
        }

        $stringValue = self::normalizeOptionalString($value);
        if ($stringValue === null) {
            return [
                'text' => null,
                'translationKey' => null,
            ];
        }

        if (preg_match('/^[A-Z0-9_.-]+$/', $stringValue) === 1 && function_exists('translation_key_exists') && translation_key_exists($stringValue)) {
            return [
                'text' => null,
                'translationKey' => $stringValue,
            ];
        }

        if (function_exists('translation_key_for_text')) {
            $translationKey = translation_key_for_text($stringValue, $preferredPrefixes);

            if (is_string($translationKey) && $translationKey !== '') {
                return [
                    'text' => null,
                    'translationKey' => $translationKey,
                ];
            }
        }

        return [
            'text' => $stringValue,
            'translationKey' => null,
        ];
    }

    private static function localizedValueToString(mixed $value): ?string
    {
        if (is_array($value)) {
            $translations = self::normalizeLocalizedTranslations($value['translations'] ?? null);
            if ($translations !== []) {
                $defaultLanguage = self::normalizeLanguageCode($value['defaultLanguage'] ?? null)
                    ?? self::FOOTER_NOTICE_DEFAULT_LANGUAGE;

                if (is_string($translations[$defaultLanguage] ?? null)) {
                    return (string) $translations[$defaultLanguage];
                }

                $firstTranslation = array_values($translations)[0] ?? null;
                if (is_string($firstTranslation) && trim($firstTranslation) !== '') {
                    return trim($firstTranslation);
                }
            }

            $text = self::normalizeOptionalString($value['text'] ?? null);
            if ($text !== null) {
                return $text;
            }

            return self::normalizeOptionalString($value['translationKey'] ?? null);
        }

        return self::normalizeOptionalString($value);
    }

    /**
     * @return array<string, string>
     */
    private static function normalizeLocalizedTranslations(mixed $translations): array
    {
        if (!is_array($translations)) {
            return [];
        }

        $normalized = [];
        foreach ($translations as $language => $value) {
            $languageCode = self::normalizeLanguageCode($language);
            $text = self::normalizeOptionalString($value);
            if ($languageCode === null || $text === null) {
                continue;
            }

            $normalized[$languageCode] = $text;
        }

        ksort($normalized);

        return $normalized;
    }

    private static function normalizeLanguageCode(mixed $language): ?string
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
     * @param array<string, mixed> $item
     */
    private static function itemId(string $location, int $index, array $item): string
    {
        $seed = json_encode(
            [
                'location' => $location,
                'index' => $index,
                'titre' => $item['titre'] ?? $item['label'] ?? null,
                'path' => $item['chemin'] ?? $item['route'] ?? null,
                'url' => $item['url'] ?? null,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return sprintf('%s-%s', strtolower($location), substr(md5((string) $seed), 0, 8));
    }

    private static function normalizeOptionalString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function normalizeAccessibilityValue(mixed $value, mixed $reference): ?string
    {
        $normalized = self::normalizeOptionalString($value);
        $normalizedReference = self::normalizeOptionalString($reference);

        if ($normalized !== null && $normalizedReference !== null && $normalized === $normalizedReference) {
            return null;
        }

        return $normalized;
    }

    private static function normalizeRoute(string $route): ?string
    {
        return normalize_public_route($route);
    }
}
