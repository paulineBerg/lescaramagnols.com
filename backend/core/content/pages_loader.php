<?php
// backend/core/content/pages_loader.php
// Charge et expose les pages dynamiques depuis backend/data/pages.json

declare(strict_types=1);

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

/**
 * Stockage cache (référence) pour réutiliser le chargement JSON.
 */
function &pages_cache_store(): array
{
    static $cache = [];
    return $cache;
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
    $cache =& pages_cache_store();

    if (array_key_exists($path, $cache)) {
        return $cache[$path];
    }

    if (!file_exists($path)) {
        $cache[$path] = [];
        return $cache[$path];
    }

    if (!is_readable($path)) {
        error_log('[pages_loader] Fichier non lisible : ' . $path);
        $cache[$path] = [];
        return $cache[$path];
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        error_log('[pages_loader] Impossible de lire le fichier : ' . $path);
        $cache[$path] = [];
        return $cache[$path];
    }

    $decoded = json_decode($raw, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        error_log('[pages_loader] JSON invalide dans ' . $path . ' : ' . json_last_error_msg());
        $cache[$path] = [];
        return $cache[$path];
    }

    if (!is_array($decoded)) {
        error_log('[pages_loader] Le fichier doit contenir un objet ou un tableau.');
        $cache[$path] = [];
        return $cache[$path];
    }

    $pages = $decoded['pages'] ?? $decoded;
    if (!is_array($pages)) {
        error_log('[pages_loader] La clé "pages" doit être un tableau.');
        $cache[$path] = [];
        return $cache[$path];
    }

    $normalized = [];
    foreach ($pages as $page) {
        if (!is_array($page)) {
            continue;
        }

        $slug = trim((string) ($page['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }

        $normalized[] = [
            'slug' => $slug,
            'status' => strtolower((string) ($page['status'] ?? 'published')),
            'title' => $page['title'] ?? null,
            'blocks' => is_array($page['blocks'] ?? null) ? $page['blocks'] : [],
            'translations' => is_array($page['translations'] ?? null) ? $page['translations'] : [],
            'meta' => is_array($page['meta'] ?? null) ? $page['meta'] : [],
        ];
    }

    $cache[$path] = $normalized;
    return $cache[$path];
}

/**
 * Récupère une page publiée par son slug et la langue demandée.
 * Fallback : langue par défaut puis première traduction disponible.
 */
function get_page_by_slug(string $slug, string $lang, ?string $fallbackLang = null, ?string $path = null): ?array
{
    $fallbackLang = $fallbackLang ?? (defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr');
    $pages = load_pages($path);

    foreach ($pages as $page) {
        if (($page['slug'] ?? '') !== $slug) {
            continue;
        }

        if (($page['status'] ?? 'published') !== 'published') {
            return null;
        }

        $translations = is_array($page['translations'] ?? null) ? $page['translations'] : [];
        $translation = $translations[$lang] ?? null;

        if ($translation === null && $fallbackLang !== $lang) {
            $translation = $translations[$fallbackLang] ?? null;
        }

        if ($translation === null && !empty($translations)) {
            $translation = reset($translations);
        }

        $baseBlocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : [];
        $translationBlocks = is_array($translation['blocks'] ?? null) ? $translation['blocks'] : [];

        $blocks = array_merge(
            page_block_defaults(),
            $baseBlocks,
            $translationBlocks
        );

        $metaBase = is_array($page['meta'] ?? null) ? $page['meta'] : [];
        $metaTranslated = is_array($translation['meta'] ?? null) ? $translation['meta'] : [];

        return [
            'slug' => $slug,
            'title' => (string) ($translation['title'] ?? $page['title'] ?? $slug),
            'blocks' => $blocks,
            'meta' => array_merge($metaBase, $metaTranslated),
        ];
    }

    return null;
}

/**
 * Permet de purger le cache (utile en tests ou lors d'un reload forcé).
 */
function pages_cache_clear(?string $path = null): void
{
    $cache =& pages_cache_store();

    if ($path === null) {
        $cache = [];
        return;
    }

    unset($cache[$path]);
}
