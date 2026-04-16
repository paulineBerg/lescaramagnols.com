<?php
// backend/core/content/pages_loader.php
// Charge et expose les pages dynamiques depuis backend/data/pages.json

declare(strict_types=1);

use Caramagnols\Content\PageRepository;
use Caramagnols\Content\StructuredPageRenderer;

/**
 * Retourne le chemin du fichier JSON des pages.
 */
function pages_data_path(): string
{
    $override = pages_data_path_override();
    if ($override !== null) {
        return $override;
    }

    return ROOT_PATH . '/data/pages.json';
}

function pages_data_path_override(): ?string
{
    return $GLOBALS['pagesDataPathOverride'] ?? null;
}

function pages_data_set_path_override(?string $path): void
{
    if ($path === null) {
        unset($GLOBALS['pagesDataPathOverride']);
        return;
    }

    $GLOBALS['pagesDataPathOverride'] = $path;
}

function &pages_repository_store(): array
{
    static $repositories = [];
    return $repositories;
}

function page_repository_for_path(string $path): PageRepository
{
    $repositories =& pages_repository_store();

    if (!isset($repositories[$path]) || !$repositories[$path] instanceof PageRepository) {
        $repositories[$path] = new PageRepository($path, new StructuredPageRenderer());
    }

    return $repositories[$path];
}

/**
 * Valeurs par défaut pour les blocs EditRegion1..12.
 */
function page_block_defaults(): array
{
    $defaults = [];
    for ($i = 1; $i <= 12; $i++) {
        $defaults['EditRegion' . $i] = '';
    }

    return $defaults;
}

/**
 * Charge la liste des pages depuis un fichier JSON.
 * - $path est surchargable pour les tests, par défaut backend/data/pages.json
 * - JSON invalide ou fichier illisible => tableau vide et log léger
 */
function load_pages(?string $path = null): array
{
    $path ??= pages_data_path();

    return page_repository_for_path($path)->all();
}

/**
 * Récupère une page publiée par son slug et la langue demandée.
 * Fallback : langue par défaut puis première traduction disponible.
 */
function get_page_by_slug(string $slug, string $lang, ?string $fallbackLang = null, ?string $path = null): ?array
{
    $fallbackLang = $fallbackLang ?? (defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr');
    $path ??= pages_data_path();

    return page_repository_for_path($path)->findPublishedStructuredBySlug($slug, $lang, $fallbackLang);
}

/**
 * Permet de purger le cache (utile en tests ou lors d'un reload forcé).
 */
function pages_cache_clear(?string $path = null): void
{
    $repositories =& pages_repository_store();

    if ($path === null) {
        foreach ($repositories as $repository) {
            if ($repository instanceof PageRepository) {
                $repository->clearCache();
            }
        }
        $repositories = [];
        return;
    }

    if (isset($repositories[$path]) && $repositories[$path] instanceof PageRepository) {
        $repositories[$path]->clearCache();
    }

    unset($repositories[$path]);
}
