<?php

declare(strict_types=1);

namespace Caramagnols\Feed;

use Caramagnols\Blog\BlogRepositoryInterface;
use Caramagnols\Content\PageRepository;
use Caramagnols\Http\Response;

final class SitemapService
{
    /**
     * @param array<int, string> $availableLanguages
     */
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly BlogRepositoryInterface $blogRepository,
        private readonly string $baseUrl,
        private readonly array $availableLanguages = ['fr', 'en', 'de'],
        private readonly string $defaultLanguage = 'fr'
    ) {
    }

    public function response(): Response
    {
        return new Response(
            200,
            ['Content-Type' => 'application/xml; charset=UTF-8'],
            $this->render()
        );
    }

    public function render(): string
    {
        $entries = $this->collectEntries();
        ksort($entries, SORT_STRING);

        $xml = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($entries as $entry) {
            $xml[] = '  <url>';
            $xml[] = '    <loc>' . $this->xmlEscape((string) ($entry['loc'] ?? '')) . '</loc>';

            $lastmod = $entry['lastmod'] ?? null;
            if (is_int($lastmod) && $lastmod > 0) {
                $xml[] = '    <lastmod>' . $this->xmlEscape(gmdate('c', $lastmod)) . '</lastmod>';
            }

            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return implode(PHP_EOL, $xml) . PHP_EOL;
    }

    /**
     * @return array<string, array{loc: string, lastmod: ?int}>
     */
    private function collectEntries(): array
    {
        $entries = [];

        $this->addPath($entries, '/');
        $this->addPath($entries, '/blog');

        foreach ($this->availableLanguages as $language) {
            if (!is_string($language) || trim($language) === '') {
                continue;
            }

            $normalizedLanguage = strtolower(trim($language));

            if ($normalizedLanguage !== $this->defaultLanguage) {
                $this->addPath($entries, '/' . rawurlencode($normalizedLanguage) . '/blog');
            }

            foreach ($this->blogRepository->publishedArticles($normalizedLanguage) as $article) {
                if (!is_array($article)) {
                    continue;
                }

                $slug = trim((string) ($article['slug'] ?? ''));
                if ($slug === '') {
                    continue;
                }

                $path = $normalizedLanguage === $this->defaultLanguage
                    ? '/blog/article/' . rawurlencode($slug)
                    : '/' . rawurlencode($normalizedLanguage) . '/blog/article/' . rawurlencode($slug);

                $this->addPath(
                    $entries,
                    $path,
                    $this->firstTimestamp($article, ['date', 'updated_at', 'created_at'])
                );
            }
        }

        foreach ($this->pageRepository->published() as $page) {
            if (!is_array($page)) {
                continue;
            }

            $route = \normalize_public_route((string) ($page['route'] ?? ''));
            $pageLastmod = $this->firstTimestamp($page, ['updated_at', 'created_at', 'date']);

            if ($route !== null && \preg_match('#^https?://#i', $route) !== 1) {
                $this->addPath($entries, $route, $pageLastmod);
            }

            $translations = is_array($page['translations'] ?? null) ? $page['translations'] : [];

            foreach ($translations as $translation) {
                if (!is_array($translation)) {
                    continue;
                }

                $translatedRoute = \normalize_public_route((string) ($translation['route'] ?? ''));
                if ($translatedRoute === null || \preg_match('#^https?://#i', $translatedRoute) === 1) {
                    continue;
                }

                $this->addPath(
                    $entries,
                    $translatedRoute,
                    $this->firstTimestamp($translation, ['updated_at', 'created_at', 'date']) ?? $pageLastmod
                );
            }
        }

        return $entries;
    }

    /**
     * @param array<string, array{loc: string, lastmod: ?int}> $entries
     */
    private function addPath(array &$entries, string $path, ?int $lastmod = null): void
    {
        $loc = $this->buildAbsoluteUrl($path);

        if (!isset($entries[$loc])) {
            $entries[$loc] = ['loc' => $loc, 'lastmod' => $lastmod];
            return;
        }

        $existingLastmod = $entries[$loc]['lastmod'];
        if (is_int($lastmod) && (!is_int($existingLastmod) || $lastmod > $existingLastmod)) {
            $entries[$loc]['lastmod'] = $lastmod;
        }
    }

    private function buildAbsoluteUrl(string $path): string
    {
        if (\preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $normalizedPath = \normalize_public_route($path) ?? '/';
        $baseUrl = rtrim($this->baseUrl, '/');

        if ($baseUrl === '' || $baseUrl === '/') {
            return $normalizedPath;
        }

        return $baseUrl . $normalizedPath;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $fields
     */
    private function firstTimestamp(array $payload, array $fields): ?int
    {
        foreach ($fields as $field) {
            if (!is_string($field)) {
                continue;
            }

            $raw = $payload[$field] ?? null;
            $timestamp = is_string($raw) ? strtotime($raw) : false;
            if (is_int($timestamp)) {
                return $timestamp;
            }
        }

        return null;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
