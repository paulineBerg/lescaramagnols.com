<?php

declare(strict_types=1);

namespace Caramagnols\Navigation;

use Caramagnols\Content\PageRepository;

final class LegacyMenuRuntime
{
    public function __construct(private readonly PageRepository $pageRepository)
    {
    }

    /**
     * @param array<string, mixed> $canonical
     * @param array<string, string> $translations
     * @return array<string, mixed>
     */
    public function loadLegacyMenus(array $canonical, array $translations): array
    {
        $menus = NavigationRepository::canonicalToLegacy($canonical);
        $menus = self::translateLegacyMenuLabels($menus, $translations);

        return $this->resolvePageSlugs($menus);
    }

    /**
     * @param array<string, mixed> $menus
     * @param array<string, string> $translations
     * @return array<string, mixed>
     */
    public static function translateLegacyMenuLabels(array $menus, array $translations): array
    {
        $translated = $menus;
        $translated['remonter'] = self::translateLegacyMenuScalarFields(
            is_array($translated['remonter'] ?? null) ? $translated['remonter'] : [],
            ['titre', 'alt', 'title'],
            $translations
        );
        $translated['banniere'] = self::translateLegacyMenuScalarFields(
            is_array($translated['banniere'] ?? null) ? $translated['banniere'] : [],
            ['texte_key', 'alt', 'title'],
            $translations
        );

        foreach (['menu1', 'menu2', 'menu3', 'menuDroit', 'menuGauche', 'menu_droit', 'menu_gauche'] as $locationKey) {
            $items = $translated[$locationKey] ?? [];
            $translated[$locationKey] = self::translateLegacyMenuItems(
                is_array($items) ? $items : [],
                $translations
            );
        }

        return $translated;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, string> $fields
     * @param array<string, string> $translations
     * @return array<string, mixed>
     */
    public static function translateLegacyMenuScalarFields(array $item, array $fields, array $translations): array
    {
        foreach ($fields as $field) {
            if (!is_string($field)) {
                continue;
            }

            $value = $item[$field] ?? null;
            if (!is_string($value) || $value === '') {
                continue;
            }

            $item[$field] = $translations[$value] ?? $value;
        }

        return $item;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, string> $translations
     * @return array<int, array<string, mixed>>
     */
    public static function translateLegacyMenuItems(array $items, array $translations): array
    {
        $translated = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $normalized = self::translateLegacyMenuScalarFields($item, ['titre', 'alt', 'title'], $translations);
            $subMenu = $normalized['sous_menu'] ?? [];

            if (is_array($subMenu) && $subMenu !== []) {
                $normalized['sous_menu'] = self::translateLegacyMenuItems($subMenu, $translations);
            }

            $translated[] = $normalized;
        }

        return $translated;
    }

    /**
     * @param array<string, mixed> $menus
     * @return array<string, mixed>
     */
    public function resolvePageSlugs(array $menus): array
    {
        foreach (['menu2', 'menu3', 'menuDroit', 'menuGauche'] as $locationKey) {
            $items = $menus[$locationKey] ?? [];
            $menus[$locationKey] = $this->resolveMenuItemsPageSlugs(is_array($items) ? $items : []);
        }

        return $menus;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public function resolveMenuItemsPageSlugs(array $items): array
    {
        $resolved = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $pageSlug = trim((string) ($item['page_slug'] ?? ''));
            if ($pageSlug !== '') {
                $page = $this->pageRepository->findBySlug($pageSlug);

                if (is_array($page) && ($page['status'] ?? PageRepository::STATUS_DRAFT) === PageRepository::STATUS_PUBLISHED) {
                    $item['chemin'] = normalize_public_route((string) ($page['route'] ?? '')) ?? ('/' . $pageSlug);
                } else {
                    $item['chemin'] = '';
                }
            }

            $subMenu = $item['sous_menu'] ?? [];
            if (is_array($subMenu) && $subMenu !== []) {
                $item['sous_menu'] = $this->resolveMenuItemsPageSlugs($subMenu);
            }

            $resolved[] = $item;
        }

        return $resolved;
    }
}
