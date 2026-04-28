<?php

declare(strict_types=1);

namespace Caramagnols\Http;

use Caramagnols\Blog\BlogRepositoryInterface;
use Caramagnols\Blog\BlogTaxonomy;
use Caramagnols\Content\PageRepository;

final class LegacyRouteResolver
{
    /**
     * @param array<int, string> $availableLanguages
     */
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly BlogRepositoryInterface $blogRepository,
        private readonly array $availableLanguages,
        private readonly string $defaultLanguage = 'fr',
        private readonly ?BlogTaxonomy $blogTaxonomy = null
    ) {
    }

    public function resolve(string $uri): string
    {
        $this->resetRouteContext();

        $route = $this->normalizeRoute($uri);
        [$language, $defaultLanguage] = $this->resolvedLanguages($uri);

        if ($route === '' || $route === 'index.php') {
            $page = $this->pageRepository->findPublishedStructuredByRoute('/', $language, $defaultLanguage);

            if ($page !== null) {
                $GLOBALS['currentDynamicPage'] = $page;

                return 'pages/dynamic.php';
            }

            return 'pages/404.php';
        }

        if (in_array($route, ['search', 'search.php'], true)) {
            return 'pages/search.php';
        }

        if ($route === 'admin' || str_starts_with($route, 'admin/')) {
            return 'pages/404.php';
        }

        $routeFilters = $this->extractBlogFiltersFromRoute($route, $language, $defaultLanguage);
        if ($routeFilters !== null && $routeFilters['invalid']) {
            return 'pages/404.php';
        }

        if (in_array($route, ['blog', 'blog/index', 'blog/index.php'], true) || $routeFilters !== null) {
            $filters = $routeFilters !== null
                ? ['category' => $routeFilters['category'], 'tag' => $routeFilters['tag']]
                : $this->extractBlogFilters($uri);
            $articles = $this->blogRepository->publishedArticleTree($language, $filters['category'], $filters['tag']);
            if ($articles === [] && $language !== $defaultLanguage) {
                $articles = $this->blogRepository->publishedArticleTree($defaultLanguage, $filters['category'], $filters['tag']);
            }

            $GLOBALS['currentBlogArticles'] = $articles;
            $GLOBALS['currentBlogFilters'] = $filters;

            return 'pages/blog/index.php';
        }

        if (in_array($route, ['blog/proposer', 'blog/proposer.php'], true)) {
            return 'pages/blog/proposer.php';
        }

        if (in_array($route, ['blog/article', 'blog/article.php'], true)) {
            return 'pages/404.php';
        }

        if (preg_match('#^blog/article/([^/]+)$#', $route, $matches) === 1) {
            $slug = (string) $matches[1];
            $article = $this->blogRepository->findPublished($slug, $language);
            if (!is_array($article) && $language !== $defaultLanguage) {
                $article = $this->blogRepository->findPublished($slug, $defaultLanguage);
            }

            if (is_array($article)) {
                $GLOBALS['currentBlogArticle'] = $this->blogRepository->withRelations($article, true);

                return 'pages/blog/article.php';
            }
        }

        $registeredRoute = '/' . ltrim($route, '/');
        $registeredPage = $this->pageRepository->findByRoute($registeredRoute);

        if (is_array($registeredPage)) {
            $status = (string) ($registeredPage['status'] ?? PageRepository::STATUS_PUBLISHED);
            if ($status !== PageRepository::STATUS_PUBLISHED) {
                return 'pages/404.php';
            }

            $page = $this->pageRepository->findPublishedStructuredByRoute($registeredRoute, $language, $defaultLanguage);

            if ($page !== null) {
                $GLOBALS['currentDynamicPage'] = $page;

                return 'pages/dynamic.php';
            }
        }

        return 'pages/404.php';
    }

    private function resetRouteContext(): void
    {
        unset($GLOBALS['currentDynamicPage']);
        unset($GLOBALS['currentBlogArticles'], $GLOBALS['currentBlogArticle'], $GLOBALS['currentBlogFilters']);
    }

    private function normalizeRoute(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path)) {
            return '';
        }

        $trimmed = trim($path, '/');
        if ($trimmed === '') {
            return '';
        }

        $segments = explode('/', $trimmed);

        if (isset($segments[0]) && in_array($segments[0], $this->availableLanguages, true)) {
            array_shift($segments);
        }

        return implode('/', $segments);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolvedLanguages(string $uri): array
    {
        $defaultLanguage = defined('DEFAULT_LANG') ? (string) DEFAULT_LANG : $this->defaultLanguage;
        $language = $this->languageFromUri($uri)
            ?? (defined('CURRENT_LANG') ? (string) CURRENT_LANG : $defaultLanguage);

        if (!in_array($language, $this->availableLanguages, true)) {
            $language = $defaultLanguage;
        }

        return [$language, $defaultLanguage];
    }

    private function languageFromUri(string $uri): ?string
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path)) {
            return null;
        }

        $segments = explode('/', trim($path, '/'));
        $candidate = strtolower(trim((string) ($segments[0] ?? '')));
        if ($candidate === '' || !in_array($candidate, $this->availableLanguages, true)) {
            return null;
        }

        return $candidate;
    }

    /**
     * @return array{category: ?string, tag: ?string}
     */
    private function extractBlogFilters(string $uri): array
    {
        $queryString = parse_url($uri, PHP_URL_QUERY);
        if (!is_string($queryString) || trim($queryString) === '') {
            return [
                'category' => null,
                'tag' => null,
            ];
        }

        parse_str($queryString, $query);
        if (!is_array($query)) {
            return [
                'category' => null,
                'tag' => null,
            ];
        }

        return [
            'category' => $this->normalizeBlogFilterValue($query['category'] ?? null),
            'tag' => $this->normalizeBlogFilterValue($query['tag'] ?? null),
        ];
    }

    private function normalizeBlogFilterValue(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array{category: ?string, tag: ?string, invalid: bool}|null
     */
    private function extractBlogFiltersFromRoute(string $route, string $language, string $defaultLanguage): ?array
    {
        if (!str_starts_with($route, 'blog/')) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', substr($route, 5)), static fn (string $segment): bool => $segment !== ''));
        if ($segments === []) {
            return null;
        }

        $firstKey = strtolower(trim((string) ($segments[0] ?? '')));
        if (!in_array($firstKey, ['categorie', 'category', 'tag'], true)) {
            return null;
        }

        if (count($segments) % 2 !== 0) {
            return ['category' => null, 'tag' => null, 'invalid' => true];
        }

        $resolved = ['category' => null, 'tag' => null, 'invalid' => false];

        for ($index = 0; $index < count($segments); $index += 2) {
            $rawKey = strtolower(trim((string) ($segments[$index] ?? '')));
            $rawValue = rawurldecode((string) ($segments[$index + 1] ?? ''));
            $slug = $this->slugifyBlogFilterValue($rawValue);

            if ($slug === '') {
                return ['category' => null, 'tag' => null, 'invalid' => true];
            }

            $target = null;
            if (in_array($rawKey, ['categorie', 'category'], true)) {
                $target = 'category';
            } elseif ($rawKey === 'tag') {
                $target = 'tag';
            }

            if (!is_string($target) || $resolved[$target] !== null) {
                return ['category' => null, 'tag' => null, 'invalid' => true];
            }

            $value = $this->resolveBlogFilterValueFromSlug($target, $slug, $language, $defaultLanguage);
            if (!is_string($value) || $value === '') {
                return ['category' => null, 'tag' => null, 'invalid' => true];
            }

            $resolved[$target] = $value;
        }

        return $resolved;
    }

    private function resolveBlogFilterValueFromSlug(
        string $kind,
        string $slug,
        string $language,
        string $defaultLanguage
    ): ?string {
        $terms = $kind === 'category'
            ? $this->blogRepository->categories($language, true)
            : $this->blogRepository->tags($language, true);

        $resolved = $this->matchBlogFilterSlug($kind, $slug, $terms);
        if ($resolved !== null) {
            return $resolved;
        }

        if ($language !== $defaultLanguage) {
            $fallbackTerms = $kind === 'category'
                ? $this->blogRepository->categories($defaultLanguage, true)
                : $this->blogRepository->tags($defaultLanguage, true);

            $resolved = $this->matchBlogFilterSlug($kind, $slug, $fallbackTerms);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $terms
     */
    private function matchBlogFilterSlug(string $kind, string $slug, array $terms): ?string
    {
        $taxonomy = $this->blogTaxonomy ?? BlogTaxonomy::fromDefaultConfig();

        foreach ($terms as $term) {
            $normalizedTerm = $this->normalizeBlogFilterValue($term);
            if ($normalizedTerm === null) {
                continue;
            }

            $canonicalTerm = $kind === 'category'
                ? $taxonomy->resolveCategorySlug($normalizedTerm)
                : $taxonomy->resolveTagSlug($normalizedTerm);

            if (is_string($canonicalTerm) && $this->slugifyBlogFilterValue($canonicalTerm) === $slug) {
                return $canonicalTerm;
            }

            if ($this->slugifyBlogFilterValue($normalizedTerm) === $slug) {
                return $canonicalTerm ?? $normalizedTerm;
            }
        }

        return null;
    }

    private function slugifyBlogFilterValue(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        $transliterated = function_exists('iconv')
            ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized)
            : $normalized;
        if (!is_string($transliterated) || trim($transliterated) === '') {
            $transliterated = $normalized;
        }

        $slug = strtolower(trim($transliterated));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug;
    }
}
