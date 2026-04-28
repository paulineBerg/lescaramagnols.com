<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

use Caramagnols\Content\PageRepository;
use Caramagnols\Logging\AppEventLogger;

final class BlogInternalLinksRebuilder
{
    private const BLOG_ARTICLE_LINK_PATTERN = '/\\b(href)\\s*=\\s*([\"\'])(.*?)\\2/i';

    private AppEventLogger $eventLogger;

    /** @var array<string, array<string, string|null>> */
    private array $pageRouteCache = [];

    public function __construct(
        private readonly BlogRepositoryInterface $repository,
        private readonly PageRepository $pageRepository,
        ?AppEventLogger $logger = null
    ) {
        $this->eventLogger = $logger ?? app_event_logger();
    }

    /**
     * @return array{
     *   attempted: int,
     *   changed: int,
     *   skipped: int,
     *   updated: int,
     *   dry_run: bool,
     *   errors: array<int, string>
     * }
     */
    public function rebuild(int $now = null, bool $dryRun = false): array
    {
        $now = $now ?? time();
        $errors = [];
        $attempted = 0;
        $changed = 0;
        $skipped = 0;

        $candidates = [];
        $publishedOrScheduledIndexes = [];

        foreach ($this->repository->allArticles() as $article) {
            if (!is_array($article)) {
                continue;
            }

            $status = $this->normalizeStatus((string) ($article['status'] ?? 'draft'));
            if (!in_array($status, ['published', 'scheduled'], true)) {
                continue;
            }

            $slug = $this->normalizeSlug((string) ($article['slug'] ?? ''));
            $lang = strtolower(trim((string) ($article['lang'] ?? 'fr')));
            if ($slug === '' || $lang === '') {
                continue;
            }

            $publishedOrScheduledIndexes[$lang][$slug] = $article;
            $candidates[] = [
                'article' => $article,
                'lang' => $lang,
            ];
        }

        $attempted = count($candidates);
        if ($attempted === 0) {
            return [
                'attempted' => 0,
                'changed' => 0,
                'skipped' => 0,
                'updated' => 0,
                'dry_run' => $dryRun,
                'errors' => [],
            ];
        }

        foreach ($candidates as $candidate) {
            $article = $candidate['article'];
            $language = $candidate['lang'];
            if (!is_array($article)) {
                $skipped++;
                continue;
            }

            $content = (string) ($article['content'] ?? '');
            if ($content === '') {
                $skipped++;
                continue;
            }

            $replaced = preg_replace_callback(
                self::BLOG_ARTICLE_LINK_PATTERN,
                function (array $matches) use ($language, $publishedOrScheduledIndexes, $now): string {
                    $value = (string) ($matches[3] ?? '');
                    $rewritten = $this->rewriteBlogHref($value, $language, $publishedOrScheduledIndexes, $now);
                    if ($rewritten === null) {
                        return $matches[0];
                    }

                    $quote = $matches[2];
                    return (string) $matches[1] . '=' . $quote . $rewritten . $quote;
                },
                $content
            );

            if ($replaced === null || $replaced === $content) {
                $skipped++;
                continue;
            }

            $changed++;
            if (!$dryRun) {
                $article['content'] = $replaced;
                $this->repository->save(
                    $article,
                    $article['slug'] ?? null,
                    $article['lang'] ?? null
                );
            }
        }

        if (!$dryRun && $changed > 0) {
            app_runtime_cache_clear(['pages', 'navigation']);
            $this->eventLogger->content(
                'blog.article.internal_links_rebuilt',
                [
                    'articles_processed' => $attempted,
                    'links_rewritten' => $changed,
                    'dry_run' => false,
                ]
            );
        }

        return [
            'attempted' => $attempted,
            'changed' => $changed,
            'skipped' => $skipped,
            'updated' => $changed,
            'dry_run' => $dryRun,
            'errors' => $errors,
        ];
    }

    private function rewriteBlogHref(
        string $href,
        string $sourceLanguage,
        array $articleMap,
        int $now
    ): ?string {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }

        if (parse_url($href, PHP_URL_SCHEME) !== null || str_starts_with($href, '//')) {
            return null;
        }

        $path = (string) parse_url($href, PHP_URL_PATH);
        if ($path === '') {
            return null;
        }

        $normalizedPath = '/' . ltrim($path, '/');

        $targetLanguage = $sourceLanguage;
        $targetSlug = '';

        if (
            preg_match('@^/([a-z]{2}(?:-[a-z]{2})?)/blog/article/([^/?#]+)$@i', $normalizedPath, $matches) === 1
        ) {
            $targetLanguage = strtolower((string) $matches[1]);
            $targetSlug = $this->normalizeSlug((string) ($matches[2] ?? ''));
        } elseif (preg_match('@^/blog/article/([^/?#]+)$@i', $normalizedPath, $matches) === 1) {
            $targetSlug = $this->normalizeSlug((string) ($matches[1] ?? ''));
        }

        if ($targetSlug === '' || $targetLanguage === '') {
            return null;
        }

        $targetArticle = $articleMap[$targetLanguage][$targetSlug] ?? null;
        if (!is_array($targetArticle)) {
            return null;
        }

        $targetStatus = $this->normalizeStatus((string) ($targetArticle['status'] ?? 'draft'));
        if (!in_array($targetStatus, ['published', 'scheduled'], true)) {
            return null;
        }

        $targetPageSlug = trim((string) ($targetArticle['page_slug'] ?? ''));
        $targetPageRoute = $targetPageSlug !== '' ? $this->resolvePublishedPageRoute($targetPageSlug, $targetLanguage) : null;
        $isVisible = $this->hasVisiblePublicationDate($targetArticle, $now, $targetStatus);

        if ($isVisible || $targetPageRoute !== null) {
            if ($targetPageRoute !== null) {
                return '/' . $targetLanguage . $targetPageRoute
                    . '?open_article=' . rawurlencode($targetSlug)
                    . '#attached-article-' . rawurlencode($targetSlug);
            }
        }

        if ($isVisible) {
            return '/' . $targetLanguage . '/blog/article/' . rawurlencode($targetSlug);
        }

        return '/' . $targetLanguage . '/blog';
    }

    private function hasVisiblePublicationDate(array $article, int $now, string $status): bool
    {
        if ($status === 'published') {
            return true;
        }

        if ($status !== 'scheduled') {
            return false;
        }

        $timestamp = $this->parseDateTimestamp((string) ($article['date'] ?? ''));
        return $timestamp !== null && $timestamp <= $now;
    }

    private function resolvePublishedPageRoute(string $pageSlug, string $language): ?string
    {
        if (isset($this->pageRouteCache[$pageSlug][$language])) {
            return $this->pageRouteCache[$pageSlug][$language];
        }

        $page = $this->pageRepository->findBySlug($pageSlug);
        if (!is_array($page) || ($page['status'] ?? '') !== 'published') {
            $this->pageRouteCache[$pageSlug][$language] = null;
            return null;
        }

        $translations = is_array($page['translations'] ?? null) ? $page['translations'] : [];
        $route = '';

        if (is_array($translations[$language] ?? null) && isset($translations[$language]['route'])) {
            $route = trim((string) $translations[$language]['route']);
        }

        if ($route === '') {
            $route = trim((string) ($page['route'] ?? ''));
        }

        if (!isset($this->pageRouteCache[$pageSlug])) {
            $this->pageRouteCache[$pageSlug] = [];
        }

        if ($route === '') {
            $this->pageRouteCache[$pageSlug][$language] = null;
            return null;
        }

        $normalizedRoute = normalize_public_route($route);
        if (!is_string($normalizedRoute)) {
            $this->pageRouteCache[$pageSlug][$language] = null;
            return null;
        }

        $this->pageRouteCache[$pageSlug][$language] = $normalizedRoute;
        return $normalizedRoute;
    }

    private function parseDateTimestamp(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return is_int($timestamp) ? $timestamp : null;
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return in_array($normalized, ['draft', 'scheduled', 'published'], true)
            ? $normalized
            : 'draft';
    }

    private function normalizeSlug(string $slug): string
    {
        $normalized = strtolower(trim($slug));
        $normalized = preg_replace('/[^a-z0-9-]+/i', '-', $normalized) ?? '';

        return trim($normalized, '-');
    }
}
