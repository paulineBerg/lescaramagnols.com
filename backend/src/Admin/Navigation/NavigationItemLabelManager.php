<?php

declare(strict_types=1);

namespace Caramagnols\Admin\Navigation;

final class NavigationItemLabelManager
{
    /**
     * @param array<int, string>|null $availableLanguages
     */
    public function __construct(
        private readonly string $defaultLanguage = 'fr',
        private readonly ?array $availableLanguages = null
    ) {
    }

    /**
     * @param array<string, mixed>|null $existingLabel
     * @param array<int, string>|null $preferredPrefixes
     * @return array{
     *   text: string|null,
     *   translationKey: string|null,
     *   defaultLanguage?: string,
     *   translations?: array<string, string>
     * }
     */
    public function normalizeFromPost(
        mixed $textValue,
        mixed $translationKeyValue,
        mixed $translationsValue = null,
        mixed $defaultLanguageValue = null,
        ?array $existingLabel = null,
        ?array $preferredPrefixes = null
    ): array {
        $existingLabel = is_array($existingLabel) ? $existingLabel : [];
        $text = $this->stringOrNull($textValue);
        $translationKey = $this->stringOrNull($translationKeyValue);

        $normalized = $this->normalizeLocalizedValue($text, $translationKey, $preferredPrefixes);
        $translations = $this->mergeTranslations($translationsValue, $existingLabel['translations'] ?? null);
        $defaultLanguage = $this->normalizeLanguageCode($defaultLanguageValue)
            ?? $this->normalizeLanguageCode($existingLabel['defaultLanguage'] ?? null)
            ?? $this->defaultLanguage;

        if ($translations !== []) {
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

    /**
     * @param array<string, mixed>|null $label
     * @param callable(string): ?string $translationResolver
     */
    public function labelToString(?array $label, ?string $preferredLanguage, callable $translationResolver): ?string
    {
        $label = is_array($label) ? $label : [];
        $preferredLanguage = $this->normalizeLanguageCode($preferredLanguage) ?? $this->defaultLanguage;
        $defaultLanguage = $this->normalizeLanguageCode($label['defaultLanguage'] ?? null) ?? $this->defaultLanguage;
        $translations = $this->normalizeTranslations($label['translations'] ?? null);

        if ($preferredLanguage !== '' && is_string($translations[$preferredLanguage] ?? null)) {
            return (string) $translations[$preferredLanguage];
        }

        if ($defaultLanguage !== '' && is_string($translations[$defaultLanguage] ?? null)) {
            return (string) $translations[$defaultLanguage];
        }

        if ($translations !== []) {
            $first = array_values($translations)[0] ?? null;
            if (is_string($first) && trim($first) !== '') {
                return trim($first);
            }
        }

        $text = $this->stringOrNull($label['text'] ?? null);
        if ($text !== null) {
            return $text;
        }

        $translationKey = $this->stringOrNull($label['translationKey'] ?? null);
        if ($translationKey === null) {
            return null;
        }

        return $translationResolver($translationKey) ?? $translationKey;
    }

    /**
     * @return array<int, string>
     */
    public function availableLanguages(): array
    {
        $languages = is_array($this->availableLanguages)
            ? $this->normalizeLanguageList($this->availableLanguages)
            : $this->normalizeLanguageList(
                function_exists('site_available_languages') ? site_available_languages() : []
            );

        if (!in_array($this->defaultLanguage, $languages, true)) {
            array_unshift($languages, $this->defaultLanguage);
        }

        return array_values(array_unique($languages));
    }

    /**
     * @param array<int, string>|null $preferredPrefixes
     * @return array{text: string|null, translationKey: string|null}
     */
    private function normalizeLocalizedValue(mixed $textValue, mixed $translationKeyValue, ?array $preferredPrefixes): array
    {
        $text = $this->stringOrNull($textValue);
        $translationKey = $this->stringOrNull($translationKeyValue);

        if ($translationKey !== null) {
            $translatedValue = $this->translateKey($translationKey);
            if ($text === null || $text === $translationKey || ($translatedValue !== null && $text === $translatedValue)) {
                return [
                    'text' => null,
                    'translationKey' => $translationKey,
                ];
            }
        }

        if ($text !== null && function_exists('translation_key_for_text')) {
            $resolvedKey = translation_key_for_text($text, $preferredPrefixes);
            if (is_string($resolvedKey) && $resolvedKey !== '') {
                return [
                    'text' => null,
                    'translationKey' => $resolvedKey,
                ];
            }
        }

        return [
            'text' => $text,
            'translationKey' => null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mergeTranslations(mixed $postedTranslations, mixed $existingTranslations): array
    {
        $posted = $this->normalizeTranslations($postedTranslations);
        $existing = $this->normalizeTranslations($existingTranslations);
        $merged = [];

        foreach ($this->availableLanguages() as $language) {
            $value = $posted[$language] ?? $existing[$language] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $merged[$language] = trim($value);
            }
        }

        foreach ($posted as $language => $value) {
            if (!isset($merged[$language])) {
                $merged[$language] = $value;
            }
        }

        return $merged;
    }

    /**
     * @return array<string, string>
     */
    private function normalizeTranslations(mixed $translations): array
    {
        if (!is_array($translations)) {
            return [];
        }

        $normalized = [];
        foreach ($translations as $language => $value) {
            $normalizedLanguage = $this->normalizeLanguageCode($language);
            $normalizedValue = $this->stringOrNull($value);

            if ($normalizedLanguage === null || $normalizedValue === null) {
                continue;
            }

            $normalized[$normalizedLanguage] = $normalizedValue;
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param mixed $languages
     * @return array<int, string>
     */
    private function normalizeLanguageList(mixed $languages): array
    {
        if (!is_array($languages)) {
            return [];
        }

        $normalized = [];
        foreach ($languages as $language) {
            $normalizedLanguage = $this->normalizeLanguageCode($language);
            if ($normalizedLanguage !== null) {
                $normalized[] = $normalizedLanguage;
            }
        }

        return array_values(array_unique($normalized));
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

    private function translateKey(string $key): ?string
    {
        if (function_exists('admin_translate')) {
            $translated = admin_translate($key);
        } elseif (function_exists('t')) {
            $translated = t($key);
        } else {
            return null;
        }

        if (!is_string($translated) || $translated === '' || $translated === '[[' . $key . ']]') {
            return null;
        }

        return $translated;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
