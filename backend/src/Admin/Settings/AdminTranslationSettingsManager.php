<?php

declare(strict_types=1);

namespace Caramagnols\Admin\Settings;

final class AdminTranslationSettingsManager
{
    /**
     * @var callable(string): array<string, string>
     */
    private $translationsLoader;

    /**
     * @param callable(string): array<string, string>|null $translationsLoader
     */
    public function __construct(
        private readonly string $defaultLanguage = 'fr',
        ?callable $translationsLoader = null
    ) {
        $this->translationsLoader = $translationsLoader ?? static function (string $language): array {
            if (!function_exists('load_translations_cached')) {
                return [];
            }

            $translations = load_translations_cached($language);

            return is_array($translations) ? $translations : [];
        };
    }

    /**
     * @param mixed $configuredOverrides
     * @param array<int, string>|null $languages
     * @return array{languages: array<int, string>, textByLanguage: array<string, string>}
     */
    public function configured(mixed $configuredOverrides, ?array $languages = null): array
    {
        $languages = $this->normalizeLanguages($languages ?? $this->defaultLanguages());
        $overrides = $this->normalizeOverrides($configuredOverrides);
        $textByLanguage = [];

        foreach ($languages as $language) {
            $entries = is_array($overrides[$language] ?? null) ? $overrides[$language] : [];
            $textByLanguage[$language] = $this->serializeOverrideLines($entries);
        }

        return [
            'languages' => $languages,
            'textByLanguage' => $textByLanguage,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array{languages: array<int, string>, textByLanguage: array<string, string>} $fallback
     * @return array{languages: array<int, string>, textByLanguage: array<string, string>}
     */
    public function form(array $payload, array $fallback): array
    {
        $languages = $this->normalizeLanguages($fallback['languages'] ?? []);
        $fallbackTextByLanguage = is_array($fallback['textByLanguage'] ?? null) ? $fallback['textByLanguage'] : [];
        $textByLanguage = [];

        foreach ($languages as $language) {
            if (array_key_exists($language, $payload) && is_scalar($payload[$language])) {
                $textByLanguage[$language] = trim((string) $payload[$language]);
                continue;
            }

            $textByLanguage[$language] = trim((string) ($fallbackTextByLanguage[$language] ?? ''));
        }

        return [
            'languages' => $languages,
            'textByLanguage' => $textByLanguage,
        ];
    }

    /**
     * @param array{languages: array<int, string>, textByLanguage: array<string, string>} $translations
     * @return array{data: array<string, array<string, string>>, error: string|null}
     */
    public function normalizeConfig(array $translations): array
    {
        $languages = $this->normalizeLanguages($translations['languages'] ?? []);
        $knownLookup = array_fill_keys($this->knownKeys(), true);
        $textByLanguage = is_array($translations['textByLanguage'] ?? null) ? $translations['textByLanguage'] : [];
        $normalized = [];

        foreach ($languages as $language) {
            $rawText = trim((string) ($textByLanguage[$language] ?? ''));
            if ($rawText === '') {
                continue;
            }

            $entries = $this->parseOverrideLines($rawText);
            if ($entries['error'] !== null) {
                return ['data' => [], 'error' => sprintf('Traductions %s: %s', strtoupper($language), $entries['error'])];
            }

            $languageEntries = [];
            foreach ($entries['data'] as $key => $value) {
                if (!isset($knownLookup[$key])) {
                    return ['data' => [], 'error' => sprintf('Traductions %s: la clé "%s" est inconnue.', strtoupper($language), $key)];
                }

                $languageEntries[$key] = $value;
            }

            if ($languageEntries !== []) {
                ksort($languageEntries);
                $normalized[$language] = $languageEntries;
            }
        }

        ksort($normalized);

        return ['data' => $normalized, 'error' => null];
    }

    /**
     * @param mixed $overrides
     * @return array<string, array<string, string>>
     */
    public function normalizeOverrides(mixed $overrides): array
    {
        if (!is_array($overrides)) {
            return [];
        }

        $normalized = [];
        foreach ($overrides as $language => $values) {
            $language = strtolower(trim((string) $language));
            if ($language === '' || preg_match('/^[a-z]{2,5}$/', $language) !== 1 || !is_array($values)) {
                continue;
            }

            $languageValues = [];
            foreach ($values as $key => $value) {
                $key = trim((string) $key);
                if ($key === '' || !is_scalar($value)) {
                    continue;
                }

                $languageValues[$key] = trim((string) $value);
            }

            if ($languageValues === []) {
                continue;
            }

            ksort($languageValues);
            $normalized[$language] = $languageValues;
        }

        ksort($normalized);

        return $normalized;
    }

    public function countOverrideLines(string $rawText): int
    {
        $parsed = $this->parseOverrideLines($rawText);
        if ($parsed['error'] !== null) {
            return 0;
        }

        return count($parsed['data']);
    }

    /**
     * @return array<string, string>
     */
    public function dictionaryEntriesForLanguage(string $language): array
    {
        $normalizedLanguage = strtolower(trim($language));
        if ($normalizedLanguage === '') {
            $normalizedLanguage = strtolower(trim($this->defaultLanguage));
        }

        if ($normalizedLanguage === '') {
            $normalizedLanguage = 'fr';
        }

        $translations = ($this->translationsLoader)($normalizedLanguage);
        if ($translations === [] && $normalizedLanguage !== strtolower(trim($this->defaultLanguage))) {
            $translations = ($this->translationsLoader)(strtolower(trim($this->defaultLanguage)));
        }

        $normalized = [];
        foreach ($translations as $key => $value) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '' || !is_scalar($value)) {
                continue;
            }

            $normalized[$normalizedKey] = trim((string) $value);
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    public function knownKeys(): array
    {
        $translations = ($this->translationsLoader)($this->defaultLanguage);
        if ($translations === []) {
            $translations = ($this->translationsLoader)('fr');
        }

        $keys = array_values(array_filter(
            array_keys($translations),
            static fn (mixed $key): bool => is_string($key) && trim((string) $key) !== ''
        ));
        sort($keys);

        return $keys;
    }

    /**
     * @param array<string, string> $entries
     */
    public function serializeOverrideLines(array $entries): string
    {
        if ($entries === []) {
            return '';
        }

        ksort($entries);
        $lines = [];

        foreach ($entries as $key => $value) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '' || !is_scalar($value)) {
                continue;
            }

            $lines[] = $normalizedKey . '=' . trim((string) $value);
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * @return array{data: array<string, string>, error: string|null}
     */
    public function parseOverrideLines(string $rawText): array
    {
        $lines = preg_split('/\R/', $rawText) ?: [];
        $entries = [];

        foreach ($lines as $lineNumber => $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '') {
                continue;
            }

            if (str_starts_with($trimmed, '#') || str_starts_with($trimmed, ';')) {
                continue;
            }

            if (!str_contains($trimmed, '=')) {
                return [
                    'data' => [],
                    'error' => sprintf('ligne %d invalide (format attendu KEY=Valeur).', $lineNumber + 1),
                ];
            }

            [$rawKey, $rawValue] = explode('=', $trimmed, 2);
            $key = trim((string) $rawKey);
            $value = trim((string) $rawValue);

            if ($key === '') {
                return [
                    'data' => [],
                    'error' => sprintf('ligne %d invalide (clé vide).', $lineNumber + 1),
                ];
            }

            $entries[$key] = $value;
        }

        return ['data' => $entries, 'error' => null];
    }

    /**
     * @param array<int, string> $languages
     * @return array<int, string>
     */
    private function normalizeLanguages(array $languages): array
    {
        $normalized = array_values(array_filter(
            array_map(static fn ($value): string => strtolower(trim((string) $value)), $languages),
            static fn (string $value): bool => $value !== ''
        ));

        $defaultLanguage = strtolower(trim($this->defaultLanguage));
        if ($defaultLanguage === '') {
            $defaultLanguage = 'fr';
        }

        $ordered = [$defaultLanguage];
        foreach ($normalized as $language) {
            if ($language === $defaultLanguage) {
                continue;
            }

            $ordered[] = $language;
        }

        return array_values(array_unique($ordered));
    }

    /**
     * @return array<int, string>
     */
    private function defaultLanguages(): array
    {
        if (!function_exists('site_available_languages')) {
            return [$this->defaultLanguage];
        }

        $languages = site_available_languages();

        return is_array($languages) ? $languages : [$this->defaultLanguage];
    }
}
