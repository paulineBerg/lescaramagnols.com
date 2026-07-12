<?php

declare(strict_types=1);

namespace Caramagnols\Feed;

use Caramagnols\Blog\BlogRepositoryInterface;
use Caramagnols\Blog\BlogPublicUrlResolver;
use Caramagnols\Content\PageRepository;
use Caramagnols\Seo\SeoUrlNormalizer;

final class SitemapEntryCollector
{
    private BlogPublicUrlResolver $publicUrlResolver;

    /**
     * @param array<int, string> $availableLanguages
     */
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly BlogRepositoryInterface $blogRepository,
        private readonly string $baseUrl = '/',
        private readonly array $availableLanguages = ['fr', 'en', 'de'],
        private readonly string $defaultLanguage = 'fr'
    ) {
        $this->publicUrlResolver = new BlogPublicUrlResolver(
            $this->blogRepository,
            $this->pageRepository,
            $this->defaultLanguage
        );
    }

    /**
     * @return array<string, array{
     *   loc: string,
     *   path: string,
     *   lastmod: ?int,
     *   type: string,
     *   language: string,
     *   title: string
     * }>
     */
    public function collectEntries(): array
    {
        $entries = [];

        $this->addPath($entries, '/', 'page', $this->defaultLanguage, '');
        $this->addPath($entries, '/blog', 'blog_index', $this->defaultLanguage, 'Blog');

        foreach ($this->normalizedLanguages() as $language) {
            if ($language !== $this->defaultLanguage) {
                $this->addPath(
                    $entries,
                    $this->blogIndexPath($language),
                    'blog_index',
                    $language,
                    'Blog'
                );
            }

            foreach ($this->blogRepository->publishedArticles($language) as $article) {
                $slug = trim((string) ($article['slug'] ?? ''));
                if ($slug === '') {
                    continue;
                }

                $this->addPath(
                    $entries,
                    $this->blogArticlePath($article, $slug, $language),
                    'blog_article',
                    $language,
                    $this->articleTitle($article, $slug),
                    $this->firstTimestamp($article, ['date', 'updated_at', 'created_at'])
                );
            }
        }

        foreach ($this->pageRepository->published() as $page) {
            $route = \normalize_public_route((string) ($page['route'] ?? ''));
            $pageLastmod = $this->firstTimestamp($page, ['updated_at', 'created_at', 'date']);

            if ($route !== null && \preg_match('#^https?://#i', $route) !== 1) {
                $this->addPath(
                    $entries,
                    $route,
                    'page',
                    $this->defaultLanguage,
                    $this->pageTitle($page, $this->defaultLanguage),
                    $pageLastmod
                );
            }

            $translations = is_array($page['translations'] ?? null) ? $page['translations'] : [];
            foreach ($translations as $language => $translation) {
                if (!is_string($language) || !is_array($translation)) {
                    continue;
                }

                $translatedRoute = \normalize_public_route((string) ($translation['route'] ?? ''));
                if ($translatedRoute === null || \preg_match('#^https?://#i', $translatedRoute) === 1) {
                    continue;
                }

                $this->addPath(
                    $entries,
                    $translatedRoute,
                    'page',
                    strtolower(trim($language)),
                    $this->pageTitle($page, $language),
                    $this->firstTimestamp($translation, ['updated_at', 'created_at', 'date']) ?? $pageLastmod
                );
            }
        }

        ksort($entries, SORT_STRING);

        return $entries;
    }

    /**
     * @return array<int, array{
     *   loc: string,
     *   path: string,
     *   lastmod: ?int,
     *   type: string,
     *   language: string,
     *   title: string
     * }>
     */
    public function collectEntriesForLanguage(string $language): array
    {
        $language = $this->normalizeLanguage($language);
        $entries = [];

        foreach ($this->pageRepository->published() as $page) {
            $route = $this->pageRouteForLanguage($page, $language);
            if ($route === null || \preg_match('#^https?://#i', $route) === 1) {
                continue;
            }

            $this->addPath(
                $entries,
                $route,
                'page',
                $language,
                $this->pageTitle($page, $language),
                $this->pageLastmodForLanguage($page, $language)
            );
        }

        $this->addPath($entries, $this->blogIndexPath($language), 'blog_index', $language, 'Blog');

        foreach ($this->blogRepository->publishedArticles($language) as $article) {
            $slug = trim((string) ($article['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $this->addPath(
                $entries,
                $this->blogArticlePath($article, $slug, $language),
                'blog_article',
                $language,
                $this->articleTitle($article, $slug),
                $this->firstTimestamp($article, ['date', 'updated_at', 'created_at'])
            );
        }

        ksort($entries, SORT_STRING);

        return array_values($entries);
    }

    /**
     * @param array<string, array{
     *   loc: string,
     *   path: string,
     *   lastmod: ?int,
     *   type: string,
     *   language: string,
     *   title: string
     * }> $entries
     */
    private function addPath(
        array &$entries,
        string $path,
        string $type,
        string $language,
        string $title = '',
        ?int $lastmod = null
    ): void {
        $normalizedPath = \normalize_public_route(SeoUrlNormalizer::withoutFragment($path)) ?? '/';
        $loc = $this->buildAbsoluteUrl($normalizedPath);

        if (!isset($entries[$loc])) {
            $entries[$loc] = [
                'loc' => $loc,
                'path' => $normalizedPath,
                'lastmod' => $lastmod,
                'type' => $type,
                'language' => $this->normalizeLanguage($language),
                'title' => trim($title),
            ];

            return;
        }

        $existingLastmod = $entries[$loc]['lastmod'];
        if (is_int($lastmod) && (!is_int($existingLastmod) || $lastmod > $existingLastmod)) {
            $entries[$loc]['lastmod'] = $lastmod;
        }

        if ($entries[$loc]['title'] === '' && trim($title) !== '') {
            $entries[$loc]['title'] = trim($title);
        }
    }

    private function buildAbsoluteUrl(string $path): string
    {
        if (\preg_match('#^https?://#i', $path) === 1) {
            return SeoUrlNormalizer::withoutFragment($path);
        }

        $normalizedPath = \normalize_public_route(SeoUrlNormalizer::withoutFragment($path)) ?? '/';
        $baseUrl = rtrim($this->baseUrl, '/');

        if ($baseUrl === '' || $baseUrl === '/') {
            return $normalizedPath;
        }

        return $baseUrl . $normalizedPath;
    }

    private function blogIndexPath(string $language): string
    {
        $language = $this->normalizeLanguage($language);

        return $language === $this->defaultLanguage ? '/blog' : '/' . rawurlencode($language) . '/blog';
    }

    /**
     * @param array<string, mixed> $article
     */
    private function blogArticlePath(array $article, string $slug, string $language): string
    {
        return $this->publicUrlResolver->publicPathForArticle($article)
            ?? $this->publicUrlResolver->fallbackArticlePath($slug, $language);
    }

    /**
     * @param array<string, mixed> $page
     */
    private function pageRouteForLanguage(array $page, string $language): ?string
    {
        $translations = is_array($page['translations'] ?? null) ? $page['translations'] : [];
        $translation = is_array($translations[$language] ?? null) ? $translations[$language] : [];
        $translationRoute = \normalize_public_route((string) ($translation['route'] ?? ''));
        if ($translationRoute !== null) {
            return $translationRoute;
        }

        return \normalize_public_route((string) ($page['route'] ?? ''));
    }

    /**
     * @param array<string, mixed> $page
     */
    private function pageLastmodForLanguage(array $page, string $language): ?int
    {
        $translations = is_array($page['translations'] ?? null) ? $page['translations'] : [];
        $translation = is_array($translations[$language] ?? null) ? $translations[$language] : [];

        return $this->firstTimestamp($translation, ['updated_at', 'created_at', 'date'])
            ?? $this->firstTimestamp($page, ['updated_at', 'created_at', 'date']);
    }

    /**
     * @param array<string, mixed> $page
     */
    private function pageTitle(array $page, string $language): string
    {
        $translations = is_array($page['translations'] ?? null) ? $page['translations'] : [];
        $translation = is_array($translations[$language] ?? null) ? $translations[$language] : [];
        $fallbackTranslation = is_array($translations[$this->defaultLanguage] ?? null)
            ? $translations[$this->defaultLanguage]
            : [];

        foreach ([$translation, $fallbackTranslation, $page] as $candidate) {
            $title = trim((string) ($candidate['title'] ?? ''));
            if ($title !== '') {
                return $title;
            }
        }

        $slug = trim((string) ($page['slug'] ?? ''));
        if ($slug !== '') {
            return $slug;
        }

        return trim((string) ($page['route'] ?? '')) ?: 'Page';
    }

    /**
     * @param array<string, mixed> $article
     */
    private function articleTitle(array $article, string $slug): string
    {
        $title = trim((string) ($article['title'] ?? ''));

        return $title !== '' ? $title : $slug;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $fields
     */
    private function firstTimestamp(array $payload, array $fields): ?int
    {
        foreach ($fields as $field) {
            $raw = $payload[$field] ?? null;
            $timestamp = is_string($raw) ? strtotime($raw) : false;
            if (is_int($timestamp)) {
                return $timestamp;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function normalizedLanguages(): array
    {
        $languages = array_values(array_filter(
            array_map(static fn (mixed $language): string => is_string($language) ? strtolower(trim($language)) : '', $this->availableLanguages),
            static fn (string $language): bool => $language !== ''
        ));

        return $languages !== [] ? $languages : [$this->defaultLanguage];
    }

    private function normalizeLanguage(string $language): string
    {
        $normalized = strtolower(trim($language));

        return $normalized !== '' ? $normalized : $this->defaultLanguage;
    }
}
