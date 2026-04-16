<?php
// backend/core/menu_loader.php
// Charge les menus depuis backend/data/menus.json.

declare(strict_types=1);

use Caramagnols\Content\PageRepository;
use Caramagnols\Navigation\LegacyMenuRuntime;
use Caramagnols\Navigation\NavigationRepository;
use Caramagnols\Navigation\NavigationViewModelBuilder;

function menus_data_path(): string
{
    $override = menus_data_path_override();
    if ($override !== null) {
        return $override;
    }

    return ROOT_PATH . '/data/menus.json';
}

function menus_data_path_override(): ?string
{
    return $GLOBALS['menusDataPathOverride'] ?? null;
}

function menus_data_set_path_override(?string $path): void
{
    if ($path === null) {
        unset($GLOBALS['menusDataPathOverride']);
        navigation_repository_cache_clear();
        navigation_view_model_cache_clear();
        return;
    }

    $GLOBALS['menusDataPathOverride'] = $path;
    navigation_repository_cache_clear();
    navigation_view_model_cache_clear();
}

function normalize_menu_config(array $menus): array
{
    return NavigationRepository::normalizeLegacyConfig($menus);
}

/**
 * @return array<string, mixed>
 */
function load_navigation_canonical(): array
{
    return navigation_repository_for_path(menus_data_path())->loadCanonical();
}

/**
 * @return array<string, mixed>
 */
function navigation_view_model(?string $requestUri = null, ?string $language = null): array
{
    $currentLanguage = is_string($language) && $language !== ''
        ? $language
        : (defined('CURRENT_LANG') ? CURRENT_LANG : (string) app_config('default_lang', 'fr'));
    $resolvedRequestUri = is_string($requestUri) && $requestUri !== '' ? $requestUri : '/';
    $menusPath = menus_data_path();
    $pagesPath = pages_data_path();
    $menusMtime = is_file($menusPath) ? (int) (@filemtime($menusPath) ?: 0) : 0;
    $pagesMtime = is_file($pagesPath) ? (int) (@filemtime($pagesPath) ?: 0) : 0;
    $translationFileMtime = (int) (@filemtime(translation_file_path($currentLanguage)) ?: 0);
    $translationOverrides = app_config('site.i18n_overrides.' . $currentLanguage, []);
    $overrideHash = hash('sha1', json_encode($translationOverrides, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');

    $cacheKey = implode('|', [
        $currentLanguage,
        $resolvedRequestUri,
        $menusPath,
        (string) $menusMtime,
        $pagesPath,
        (string) $pagesMtime,
        (string) $translationFileMtime,
        $overrideHash,
    ]);
    $viewModelCache =& navigation_view_model_store();

    if (is_array($viewModelCache[$cacheKey] ?? null)) {
        return $viewModelCache[$cacheKey];
    }

    $builder = new NavigationViewModelBuilder(
        page_repository_for_path($pagesPath),
        site_available_languages()
    );

    $viewModel = $builder->build(
        load_navigation_canonical(),
        $currentLanguage,
        $resolvedRequestUri
    );

    $viewModelCache[$cacheKey] = $viewModel;

    if (count($viewModelCache) > 128) {
        array_shift($viewModelCache);
    }

    return $viewModel;
}

function load_menus(): array
{
    $translations = is_array($GLOBALS['langTranslations'] ?? null) ? $GLOBALS['langTranslations'] : [];

    return legacy_menu_runtime()->loadLegacyMenus(load_navigation_canonical(), $translations);
}

/**
 * @param array<string, mixed> $menus
 * @param array<string, string> $translations
 * @return array<string, mixed>
 */
function translate_legacy_menu_labels(array $menus, array $translations): array
{
    return LegacyMenuRuntime::translateLegacyMenuLabels($menus, $translations);
}

/**
 * @param array<string, mixed> $item
 * @param array<int, string> $fields
 * @param array<string, string> $translations
 * @return array<string, mixed>
 */
function translate_legacy_menu_scalar_fields(array $item, array $fields, array $translations): array
{
    return LegacyMenuRuntime::translateLegacyMenuScalarFields($item, $fields, $translations);
}

/**
 * @param array<int, array<string, mixed>> $items
 * @param array<string, string> $translations
 * @return array<int, array<string, mixed>>
 */
function translate_legacy_menu_items(array $items, array $translations): array
{
    return LegacyMenuRuntime::translateLegacyMenuItems($items, $translations);
}

function save_menus(array $menus): bool
{
    $saved = navigation_repository_for_path(menus_data_path())->saveLegacyConfig($menus);

    if ($saved) {
        navigation_view_model_cache_clear();
    }

    return $saved;
}

function resolve_menu_page_slugs(array $menus): array
{
    return legacy_menu_runtime()->resolvePageSlugs($menus);
}

/**
 * @param array<int, array<string, mixed>> $items
 * @return array<int, array<string, mixed>>
 */
function resolve_menu_items_page_slugs(array $items, PageRepository $pageRepository): array
{
    return (new LegacyMenuRuntime($pageRepository))->resolveMenuItemsPageSlugs($items);
}

function legacy_menu_runtime(): LegacyMenuRuntime
{
    static $runtime = null;
    static $pagesPath = null;

    $currentPagesPath = pages_data_path();

    if (!$runtime instanceof LegacyMenuRuntime || $pagesPath !== $currentPagesPath) {
        $runtime = new LegacyMenuRuntime(page_repository_for_path($currentPagesPath));
        $pagesPath = $currentPagesPath;
    }

    return $runtime;
}

function &navigation_repository_store(): array
{
    static $repositories = [];

    return $repositories;
}

function navigation_repository_for_path(string $path): NavigationRepository
{
    $repositories =& navigation_repository_store();

    if (!isset($repositories[$path]) || !$repositories[$path] instanceof NavigationRepository) {
        $repositories[$path] = new NavigationRepository($path);
    }

    return $repositories[$path];
}

function navigation_repository_cache_clear(?string $path = null): void
{
    $repositories =& navigation_repository_store();

    if ($path === null) {
        foreach ($repositories as $repository) {
            if ($repository instanceof NavigationRepository) {
                $repository->clearCache();
            }
        }
        $repositories = [];
        return;
    }

    if (isset($repositories[$path]) && $repositories[$path] instanceof NavigationRepository) {
        $repositories[$path]->clearCache();
    }

    unset($repositories[$path]);
}

function &navigation_view_model_store(): array
{
    static $viewModels = [];

    return $viewModels;
}

function navigation_view_model_cache_clear(): void
{
    $viewModels =& navigation_view_model_store();
    $viewModels = [];
}
