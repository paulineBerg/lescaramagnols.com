<?php
// core/i18n.php

use Caramagnols\I18n\Translator;

/**
 * Charge un fichier de traduction et le met en cache (en memoire par requete)
 * en surveillant sa date de modification pour invalider automatiquement
 * la lecture si le fichier change.
 */
function translation_loader(): Translator
{
    static $translator = null;

    if (!$translator instanceof Translator) {
        $defaultLang = defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr';
        $translator = new Translator(ROOT_PATH . '/lang', $defaultLang);
    }

    return $translator;
}

function translation_file_path(string $lang): string
{
    return translation_loader()->resolveFile($lang);
}

function load_translations_cached(string $lang): array
{
    $translations = translation_loader()->load($lang);
    $overrides = app_config('site.i18n_overrides.' . $lang, []);

    if (!is_array($overrides)) {
        return $translations;
    }

    foreach ($overrides as $key => $value) {
        $normalizedKey = trim((string) $key);
        if ($normalizedKey === '' || !is_scalar($value)) {
            continue;
        }

        $translations[$normalizedKey] = trim((string) $value);
    }

    return $translations;
}

function translation_runtime_cache_clear(?string $lang = null): void
{
    translation_loader()->clearCache($lang);

    $currentLang = defined('CURRENT_LANG') ? (string) CURRENT_LANG : (defined('DEFAULT_LANG') ? (string) DEFAULT_LANG : 'fr');
    if ($lang === null || $currentLang === $lang) {
        $GLOBALS['langTranslations'] = load_translations_cached($currentLang);
    }

    $store =& translation_lookup_runtime_store();
    $store['key_for_text'] = [];
    $store['key_exists'] = null;
}

// Charge les traductions si non encore chargees
if (!isset($langTranslations)) {
    $lang = defined('CURRENT_LANG') ? CURRENT_LANG : (defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr');
    $langTranslations = load_translations_cached($lang);
}

// Fonction de traduction PHP
function t(string $key): string
{
    global $langTranslations;
    return $langTranslations[$key] ?? '[[' . $key . ']]';
}

/**
 * @param array<int, string>|null $preferredPrefixes
 */
function translation_key_for_text(string $value, ?array $preferredPrefixes = null): ?string
{
    $store =& translation_lookup_runtime_store();
    if (!is_array($store['key_for_text'] ?? null)) {
        $store['key_for_text'] = [];
    }
    $cache =& $store['key_for_text'];

    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $prefixes = [];
    if (is_array($preferredPrefixes)) {
        foreach ($preferredPrefixes as $prefix) {
            if (is_string($prefix) && $prefix !== '') {
                $prefixes[] = $prefix;
            }
        }
    }

    $cacheKey = implode('|', $prefixes);
    if (array_key_exists($cacheKey, $cache) && array_key_exists($value, $cache[$cacheKey])) {
        return $cache[$cacheKey][$value];
    }

    $languages = function_exists('site_available_languages')
        ? site_available_languages()
        : [defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr'];

    $matches = [];

    foreach ($languages as $language) {
        if (!is_string($language) || $language === '') {
            continue;
        }

        foreach (load_translations_cached($language) as $key => $translation) {
            if (!is_string($key) || !is_string($translation) || $translation !== $value) {
                continue;
            }

            if ($prefixes !== [] && !translation_key_matches_prefixes($key, $prefixes)) {
                continue;
            }

            $matches[$key] = true;
        }
    }

    $resolved = count($matches) === 1 ? (string) array_key_first($matches) : null;
    $cache[$cacheKey][$value] = $resolved;

    return $resolved;
}

function translation_key_exists(string $key): bool
{
    $store =& translation_lookup_runtime_store();
    if (!array_key_exists('key_exists', $store)) {
        $store['key_exists'] = null;
    }
    $cache =& $store['key_exists'];

    $key = trim($key);
    if ($key === '') {
        return false;
    }

    if (!is_array($cache)) {
        $languages = function_exists('site_available_languages')
            ? site_available_languages()
            : [defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr'];

        $cache = [];

        foreach ($languages as $language) {
            if (!is_string($language) || $language === '') {
                continue;
            }

            foreach (load_translations_cached($language) as $translationKey => $translation) {
                if (is_string($translationKey) && $translationKey !== '') {
                    $cache[$translationKey] = true;
                }
            }
        }
    }

    return isset($cache[$key]);
}

/**
 * @return array{key_for_text?: array<string, array<string, string|null>>, key_exists?: array<string, bool>|null}
 */
function &translation_lookup_runtime_store(): array
{
    static $store = [];

    return $store;
}

/**
 * @param array<int, string> $prefixes
 */
function translation_key_matches_prefixes(string $key, array $prefixes): bool
{
    foreach ($prefixes as $prefix) {
        if ($prefix !== '' && str_starts_with($key, $prefix)) {
            return true;
        }
    }

    return false;
}
