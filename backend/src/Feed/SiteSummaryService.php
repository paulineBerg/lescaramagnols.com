<?php

declare(strict_types=1);

namespace Caramagnols\Feed;

final class SiteSummaryService
{
    /**
     * @param array<int, string> $availableLanguages
     */
    public function __construct(
        private readonly SitemapEntryCollector $collector,
        private readonly array $availableLanguages = ['fr', 'en', 'de'],
        private readonly string $defaultLanguage = 'fr'
    ) {
    }

    public function render(string $language): string
    {
        $language = $this->normalizeLanguage($language);
        $groups = $this->groupEntries($this->collector->collectEntriesForLanguage($language), $language);
        $labels = $this->labels($language);

        $html = [
            '<h2>' . $this->escape($labels['heading']) . '</h2>',
            '<div class="site-summary">',
        ];

        foreach ($this->orderedGroupKeys() as $groupKey) {
            $entries = $groups[$groupKey] ?? [];
            if ($entries === []) {
                continue;
            }

            $html[] = '<section class="site-summary__section">';
            $html[] = '  <h3>'
                . $this->escape($labels['groups'][$groupKey] ?? $groupKey)
                . ' <span>'
                . $this->escape($this->countLabel(count($entries), $language))
                . '</span></h3>';
            $html[] = '  <ul>';

            foreach ($entries as $entry) {
                $path = (string) ($entry['path'] ?? '/');
                $title = trim((string) ($entry['title'] ?? ''));
                if ($title === '') {
                    $title = $path;
                }

                $html[] = '    <li><a href="' . $this->escape($path) . '">' . $this->escape($title) . '</a></li>';
            }

            $html[] = '  </ul>';
            $html[] = '</section>';
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupEntries(array $entries, string $language): array
    {
        $groups = array_fill_keys($this->orderedGroupKeys(), []);

        foreach ($entries as $entry) {
            $groupKey = $this->groupKey((string) ($entry['path'] ?? '/'), $language);
            $groups[$groupKey][] = $entry;
        }

        foreach ($groups as $groupKey => $groupEntries) {
            usort(
                $groupEntries,
                static fn (array $left, array $right): int => strnatcasecmp(
                    (string) ($left['path'] ?? ''),
                    (string) ($right['path'] ?? '')
                )
            );
            $groups[$groupKey] = $groupEntries;
        }

        return $groups;
    }

    private function groupKey(string $path, string $language): string
    {
        $path = $this->pathWithoutLanguagePrefix($path, $language);

        if ($path === '/' || str_starts_with($path, '/accueil/')) {
            return 'home';
        }

        if (str_starts_with($path, '/auto-retro')) {
            return 'auto_retro';
        }

        if (str_starts_with($path, '/bouger/')) {
            return 'bouger';
        }

        if ($path === '/blog' || str_starts_with($path, '/blog/')) {
            return 'blog';
        }

        return 'other';
    }

    private function pathWithoutLanguagePrefix(string $path, string $language): string
    {
        $language = $this->normalizeLanguage($language);
        if ($language === $this->defaultLanguage) {
            return $path;
        }

        $prefix = '/' . rawurlencode($language);
        if ($path === $prefix) {
            return '/';
        }

        if (str_starts_with($path, $prefix . '/')) {
            return substr($path, strlen($prefix)) ?: '/';
        }

        return $path;
    }

    /**
     * @return array<int, string>
     */
    private function orderedGroupKeys(): array
    {
        return ['home', 'auto_retro', 'bouger', 'blog', 'other'];
    }

    /**
     * @return array{heading: string, groups: array<string, string>}
     */
    private function labels(string $language): array
    {
        return match ($language) {
            'en' => [
                'heading' => 'Sitemap',
                'groups' => [
                    'home' => 'Home',
                    'auto_retro' => 'Classic cars',
                    'bouger' => 'Out & about',
                    'blog' => 'Blog',
                    'other' => 'Other pages',
                ],
            ],
            'de' => [
                'heading' => 'Sitemap',
                'groups' => [
                    'home' => 'Start',
                    'auto_retro' => 'Oldtimer',
                    'bouger' => 'Unterwegs',
                    'blog' => 'Blog',
                    'other' => 'Weitere Seiten',
                ],
            ],
            default => [
                'heading' => 'Sommaire',
                'groups' => [
                    'home' => 'Accueil',
                    'auto_retro' => 'Auto-rétro',
                    'bouger' => 'Bouger',
                    'blog' => 'Blog',
                    'other' => 'Autres pages',
                ],
            ],
        };
    }

    private function countLabel(int $count, string $language): string
    {
        return match ($language) {
            'de' => $count . ' ' . ($count > 1 ? 'Seiten' : 'Seite'),
            default => $count . ' ' . ($count > 1 ? 'pages' : 'page'),
        };
    }

    private function normalizeLanguage(string $language): string
    {
        $normalized = strtolower(trim($language));
        $languages = array_values(array_filter(
            array_map(static fn (mixed $value): string => is_string($value) ? strtolower(trim($value)) : '', $this->availableLanguages),
            static fn (string $value): bool => $value !== ''
        ));

        if (in_array($normalized, $languages, true)) {
            return $normalized;
        }

        return $this->defaultLanguage;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
