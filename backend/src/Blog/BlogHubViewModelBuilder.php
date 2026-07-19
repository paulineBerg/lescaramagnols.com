<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

use Caramagnols\Admin\AdminEditorialImageService;
use Caramagnols\Content\PageRepository;

final class BlogHubViewModelBuilder
{
    private const DEFAULT_ARTICLES_PER_PAGE = 12;

    private BlogPublicUrlResolver $publicUrlResolver;

    /** @var array<string, array<string, mixed>|null> */
    private array $parentPageCache = [];

    public function __construct(
        private readonly BlogRepositoryInterface $blogRepository,
        private readonly PageRepository $pageRepository,
        private readonly BlogTaxonomy $taxonomy,
        private readonly string $defaultLanguage = 'fr'
    ) {
        $this->publicUrlResolver = new BlogPublicUrlResolver(
            $this->blogRepository,
            $this->pageRepository,
            $this->defaultLanguage
        );
    }

    /**
     * @param array<int, array<string, mixed>> $articleTree
     * @param array{category?: ?string, tag?: ?string} $filters
     * @param array<string, mixed>|null $hubPage
     * @return array<string, mixed>
     */
    public function build(
        array $articleTree,
        array $filters,
        string $language,
        string $requestUri = '/',
        ?array $hubPage = null,
        int $perPage = self::DEFAULT_ARTICLES_PER_PAGE
    ): array {
        $resolvedLanguage = $this->normalizeLanguage($language);
        $activeCategory = $this->normalizeOptionalString($filters['category'] ?? null);
        $activeTag = $this->normalizeOptionalString($filters['tag'] ?? null);
        $activeCategorySlug = $this->taxonomy->resolveCategorySlug($activeCategory) ?? $activeCategory;
        $activeTagSlug = $this->taxonomy->resolveTagSlug($activeTag) ?? $activeTag;
        $activeCategoryLabel = $activeCategorySlug !== null
            ? $this->taxonomy->categoryLabel($activeCategorySlug, $resolvedLanguage)
            : '';
        $activeTagLabel = $activeTagSlug !== null
            ? $this->taxonomy->tagLabel($activeTagSlug, $resolvedLanguage)
            : '';

        $flatArticles = $this->flattenArticles($articleTree);
        $requestedPage = $this->resolveRequestedPage($requestUri);
        $perPage = max(1, $perPage);
        $totalArticles = count($flatArticles);
        $totalPages = $totalArticles > 0
            ? (int) ceil($totalArticles / $perPage)
            : 1;
        $currentPage = min(max(1, $requestedPage), $totalPages);
        $offset = ($currentPage - 1) * $perPage;
        $visibleArticles = $totalArticles > 0
            ? array_slice($flatArticles, $offset, $perPage)
            : [];

        $fromIndex = $totalArticles > 0 ? $offset + 1 : 0;
        $toIndex = $totalArticles > 0 ? min($offset + count($visibleArticles), $totalArticles) : 0;

        $basePath = $this->buildHubPath($resolvedLanguage, $activeCategorySlug, $activeTagSlug);
        $canonicalPath = $this->pathForPage($basePath, $currentPage);

        return [
            'hubPage' => $hubPage,
            'filters' => [
                'category' => $activeCategory,
                'categorySlug' => $activeCategorySlug,
                'categoryLabel' => $activeCategoryLabel,
                'tag' => $activeTag,
                'tagSlug' => $activeTagSlug,
                'tagLabel' => $activeTagLabel,
            ],
            'categoryFilters' => $this->buildCategoryFilters(
                $resolvedLanguage,
                $activeCategorySlug,
                $activeTagSlug
            ),
            'articles' => array_map(
                fn (array $article): array => $this->buildArticleCard($article, $resolvedLanguage),
                $visibleArticles
            ),
            'pagination' => [
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'totalArticles' => $totalArticles,
                'perPage' => $perPage,
                'from' => $fromIndex,
                'to' => $toIndex,
                'previousPath' => $currentPage > 1 ? $this->pathForPage($basePath, $currentPage - 1) : null,
                'nextPath' => $currentPage < $totalPages ? $this->pathForPage($basePath, $currentPage + 1) : null,
            ],
            'canonicalPath' => $canonicalPath,
            'indexPath' => $this->buildHubPath($resolvedLanguage, null, null),
            'robots' => $activeTagSlug !== null ? 'noindex,follow' : 'index,follow',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $articleTree
     * @return array<int, array<string, mixed>>
     */
    private function flattenArticles(array $articleTree): array
    {
        $flat = [];
        $seen = [];

        $walk = function (array $articles) use (&$walk, &$flat, &$seen): void {
            foreach ($articles as $article) {
                if (!is_array($article)) {
                    continue;
                }

                $key = $this->articleKey($article);
                if ($key !== null && !isset($seen[$key])) {
                    $seen[$key] = true;
                    $flat[] = $article;
                }

                $children = is_array($article['child_articles'] ?? null)
                    ? $article['child_articles']
                    : [];
                if ($children !== []) {
                    $walk($children);
                }
            }
        };

        $walk($articleTree);

        usort(
            $flat,
            fn (array $left, array $right): int => $this->compareArticles($left, $right)
        );

        return $flat;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCategoryFilters(
        string $language,
        ?string $activeCategorySlug,
        ?string $activeTagSlug
    ): array {
        $filters = [[
            'slug' => null,
            'label' => '',
            'path' => $this->buildHubPath($language, null, null),
            'active' => $activeCategorySlug === null && $activeTagSlug === null,
        ]];

        $categories = $this->blogRepository->categories($language, true);
        if ($categories === [] && $language !== $this->defaultLanguage) {
            $categories = $this->blogRepository->categories($this->defaultLanguage, true);
        }

        $seen = [];
        foreach ($categories as $category) {
            if (!is_string($category)) {
                continue;
            }

            $slug = $this->taxonomy->resolveCategorySlug($category) ?? $this->normalizeOptionalString($category);
            if ($slug === null || isset($seen[$slug])) {
                continue;
            }

            $seen[$slug] = true;
            $filters[] = [
                'slug' => $slug,
                'label' => $this->taxonomy->categoryLabel($slug, $language),
                'path' => $this->buildHubPath($language, $slug, null),
                'active' => $slug === $activeCategorySlug && $activeTagSlug === null,
            ];
        }

        return $filters;
    }

    /**
     * @param array<string, mixed> $article
     * @return array<string, mixed>
     */
    private function buildArticleCard(array $article, string $language): array
    {
        $slug = trim((string) ($article['slug'] ?? ''));
        $articleLanguage = $this->normalizeLanguage((string) ($article['lang'] ?? $language));
        $categorySlug = $this->taxonomy->resolveCategorySlug($article['category'] ?? null);
        $parentPage = $this->resolveParentPage((string) ($article['page_slug'] ?? ''), $language);
        $path = $this->publicUrlResolver->publicPathForArticle($article)
            ?? $this->publicUrlResolver->fallbackArticlePath($slug, $articleLanguage);

        return [
            'slug' => $slug,
            'title' => trim((string) ($article['title'] ?? '')),
            'path' => $path,
            'categoryLabel' => $categorySlug !== null
                ? $this->taxonomy->categoryLabel($categorySlug, $language)
                : trim((string) ($article['category'] ?? '')),
            'dateLabel' => $this->formatPublicationDate((string) ($article['date'] ?? ''), $language),
            'excerpt' => $this->resolveExcerpt($article),
            'image' => $this->resolveCardImage($article, $parentPage),
            'parentPage' => $parentPage,
        ];
    }

    /**
     * @param array<string, mixed> $article
     * @param array<string, mixed>|null $parentPage
     * @return array<string, mixed>|null
     */
    private function resolveCardImage(array $article, ?array $parentPage): ?array
    {
        $image = $this->sanitizeImageMetadata($article['featured_image'] ?? null);
        if ($image !== null) {
            return $image;
        }

        return $parentPage !== null
            ? $this->sanitizeImageMetadata($parentPage['image'] ?? null)
            : null;
    }

    /**
     * @param array<string, mixed> $article
     */
    private function resolveExcerpt(array $article): string
    {
        $excerpt = trim((string) ($article['excerpt'] ?? ''));
        if ($excerpt === '') {
            $excerpt = trim(strip_tags((string) ($article['content'] ?? '')));
        }

        $excerpt = preg_replace('/\s+/u', ' ', $excerpt) ?? $excerpt;
        if ($excerpt === '') {
            return '';
        }

        return function_exists('mb_substr')
            ? trim((string) mb_substr($excerpt, 0, 220))
            : trim(substr($excerpt, 0, 220));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveParentPage(string $pageSlug, string $language): ?array
    {
        $normalizedSlug = $this->normalizeOptionalString($pageSlug);
        if ($normalizedSlug === null) {
            return null;
        }

        $cacheKey = $language . ':' . $normalizedSlug;
        if (array_key_exists($cacheKey, $this->parentPageCache)) {
            return $this->parentPageCache[$cacheKey];
        }

        $page = $this->pageRepository->findPublishedStructuredBySlug(
            $normalizedSlug,
            $language,
            $this->defaultLanguage
        );

        if (!is_array($page)) {
            $this->parentPageCache[$cacheKey] = null;
            return null;
        }

        $this->parentPageCache[$cacheKey] = [
            'slug' => $normalizedSlug,
            'title' => trim((string) ($page['title'] ?? $normalizedSlug)),
            'route' => normalize_public_route((string) ($page['route'] ?? '')),
            'image' => is_array($page['meta']['image'] ?? null) ? $page['meta']['image'] : null,
        ];

        return $this->parentPageCache[$cacheKey];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sanitizeImageMetadata(mixed $payload): ?array
    {
        if (!is_array($payload)) {
            return null;
        }

        $image = AdminEditorialImageService::sanitizeImageMetadata($payload);
        if (!is_array($image)) {
            return null;
        }

        $src = trim((string) ($image['src'] ?? ''));
        if ($src === '') {
            return null;
        }

        return [
            'src' => $src,
            'alt' => trim((string) ($image['alt'] ?? '')),
            'title' => trim((string) ($image['title'] ?? '')),
            'width' => isset($image['width']) ? max(1, min(8192, (int) $image['width'])) : 1200,
            'height' => isset($image['height']) ? max(1, min(8192, (int) $image['height'])) : 630,
        ];
    }

    private function resolveRequestedPage(string $requestUri): int
    {
        $query = parse_url($requestUri, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return 1;
        }

        parse_str($query, $parameters);
        $page = $parameters['page'] ?? null;

        if (is_array($page)) {
            $page = $page[0] ?? null;
        }

        if (!is_scalar($page)) {
            return 1;
        }

        $normalized = (int) $page;

        return $normalized >= 1 ? $normalized : 1;
    }

    private function buildHubPath(string $language, ?string $categorySlug, ?string $tagSlug): string
    {
        $path = $this->publicUrlResolver->blogIndexPath($language, true);

        if ($categorySlug !== null && $categorySlug !== '') {
            $path .= '/categorie/' . rawurlencode($categorySlug);
        }

        if ($tagSlug !== null && $tagSlug !== '') {
            $path .= '/tag/' . rawurlencode($tagSlug);
        }

        return $path;
    }

    private function pathForPage(string $basePath, int $page): string
    {
        if ($page <= 1) {
            return $basePath;
        }

        return $basePath . '?page=' . $page;
    }

    /**
     * @param array<string, mixed> $article
     */
    private function articleKey(array $article): ?string
    {
        $slug = trim((string) ($article['slug'] ?? ''));
        $language = trim((string) ($article['lang'] ?? ''));

        if ($slug === '' || $language === '') {
            return null;
        }

        return strtolower($language) . ':' . strtolower($slug);
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function compareArticles(array $left, array $right): int
    {
        $leftTimestamp = $this->articleTimestamp($left);
        $rightTimestamp = $this->articleTimestamp($right);

        if ($leftTimestamp !== $rightTimestamp) {
            return $rightTimestamp <=> $leftTimestamp;
        }

        return strcasecmp(
            trim((string) ($left['title'] ?? '')),
            trim((string) ($right['title'] ?? ''))
        );
    }

    /**
     * @param array<string, mixed> $article
     */
    private function articleTimestamp(array $article): int
    {
        foreach (['date', 'updated_at', 'created_at'] as $field) {
            $value = $article[$field] ?? null;
            $timestamp = is_string($value) ? strtotime($value) : false;
            if (is_int($timestamp)) {
                return $timestamp;
            }
        }

        return 0;
    }

    private function formatPublicationDate(string $value, string $language): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $timestamp = strtotime($trimmed);
        if (!is_int($timestamp)) {
            return $trimmed;
        }

        if (class_exists(\IntlDateFormatter::class)) {
            $locales = [
                'fr' => 'fr_FR',
                'en' => 'en_GB',
                'de' => 'de_DE',
            ];
            $locale = $locales[$language] ?? $locales[$this->defaultLanguage] ?? 'fr_FR';
            $formatter = new \IntlDateFormatter(
                $locale,
                \IntlDateFormatter::LONG,
                \IntlDateFormatter::NONE,
                date_default_timezone_get() ?: 'UTC',
                \IntlDateFormatter::GREGORIAN,
                'd MMMM yyyy'
            );

            $formatted = $formatter->format($timestamp);
            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        }

        return date('d/m/Y', $timestamp);
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeLanguage(string $language): string
    {
        $normalized = strtolower(trim($language));

        return $normalized !== '' ? $normalized : $this->defaultLanguage;
    }
}
