<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

use Caramagnols\Content\PageRepository;
use Caramagnols\Http\Request;

final class BlogPublicUrlResolver
{
    /** @var array<string, array<string, string|null>> */
    private array $pageRouteCache = [];

    public function __construct(
        private readonly BlogRepositoryInterface $repository,
        private readonly PageRepository $pageRepository,
        private readonly string $defaultLanguage = 'fr'
    ) {
    }

    /**
     * @param array<string, mixed> $article
     */
    public function publicPathForArticle(array $article): ?string
    {
        $slug = $this->normalizeSlug((string) ($article['slug'] ?? ''));
        $language = $this->normalizeLanguage((string) ($article['lang'] ?? $this->defaultLanguage));
        $pageSlug = $this->normalizeSlug((string) ($article['page_slug'] ?? ''));

        if ($slug === '' || $language === '') {
            return null;
        }

        if ($pageSlug !== '') {
            $attachedPath = $this->attachedPathFor($slug, $language, $pageSlug);
            if ($attachedPath !== null) {
                return $attachedPath;
            }
        }

        return $this->fallbackArticlePath($slug, $language);
    }

    public function publicPathForPublishedArticleSlug(string $slug, string $language): ?string
    {
        $article = $this->findPublishedArticle($slug, $language);
        if (!is_array($article)) {
            return null;
        }

        return $this->publicPathForArticle($article);
    }

    public function attachedPathForPublishedArticleSlug(string $slug, string $language): ?string
    {
        $article = $this->findPublishedArticle($slug, $language);
        if (!is_array($article)) {
            return null;
        }

        return $this->attachedPathForArticle($article);
    }

    public function fallbackArticlePath(string $slug, string $language): string
    {
        $normalizedSlug = $this->normalizeSlug($slug);
        $normalizedLanguage = $this->normalizeLanguage($language);
        $path = '/blog/article/' . rawurlencode($normalizedSlug);

        if ($normalizedLanguage === '' || $normalizedLanguage === $this->defaultLanguage) {
            return $path;
        }

        return '/' . rawurlencode($normalizedLanguage) . $path;
    }

    public function blogIndexPath(string $language, bool $forceLanguagePrefix = false): string
    {
        $normalizedLanguage = $this->normalizeLanguage($language);
        $path = '/blog';

        if ($normalizedLanguage === '' || (!$forceLanguagePrefix && $normalizedLanguage === $this->defaultLanguage)) {
            return $path;
        }

        return '/' . rawurlencode($normalizedLanguage) . $path;
    }

    /**
     * @param array<string, mixed> $article
     */
    public function attachedPathForArticle(array $article): ?string
    {
        $slug = $this->normalizeSlug((string) ($article['slug'] ?? ''));
        $language = $this->normalizeLanguage((string) ($article['lang'] ?? $this->defaultLanguage));
        $pageSlug = $this->normalizeSlug((string) ($article['page_slug'] ?? ''));

        if ($slug === '' || $language === '' || $pageSlug === '') {
            return null;
        }

        return $this->attachedPathFor($slug, $language, $pageSlug);
    }

    public function attachedPathForPageRoute(string $slug, string $language, string $pageRoute): ?string
    {
        $normalizedSlug = $this->normalizeSlug($slug);
        $normalizedLanguage = $this->normalizeLanguage($language);
        $normalizedRoute = normalize_public_route($pageRoute);

        if ($normalizedSlug === '' || $normalizedLanguage === '' || $normalizedRoute === null) {
            return null;
        }

        return $this->prefixRouteWithLanguage($normalizedLanguage, $normalizedRoute, true)
            . '?open_article=' . rawurlencode($normalizedSlug)
            . '#attached-article-' . rawurlencode($normalizedSlug);
    }

    public function publicUrl(string $path, ?Request $request = null): string
    {
        return app_url(ltrim($path, '/'), $request);
    }

    public function isDefaultLanguage(string $language): bool
    {
        return $this->normalizeLanguage($language) === $this->defaultLanguage;
    }

    private function attachedPathFor(string $slug, string $language, string $pageSlug): ?string
    {
        $pageRoute = $this->resolvePublishedPageRoute($pageSlug, $language);
        if ($pageRoute === null) {
            return null;
        }

        return $this->prefixRouteWithLanguage($language, $pageRoute, true)
            . '?open_article=' . rawurlencode($slug)
            . '#attached-article-' . rawurlencode($slug);
    }

    private function resolvePublishedPageRoute(string $pageSlug, string $language): ?string
    {
        if (isset($this->pageRouteCache[$pageSlug][$language])) {
            return $this->pageRouteCache[$pageSlug][$language];
        }

        $page = $this->pageRepository->findPublishedStructuredBySlug($pageSlug, $language, $this->defaultLanguage);
        if (!is_array($page)) {
            $this->pageRouteCache[$pageSlug][$language] = null;
            return null;
        }

        $route = normalize_public_route((string) ($page['route'] ?? ''));
        $this->pageRouteCache[$pageSlug][$language] = $route;

        return $route;
    }

    private function prefixRouteWithLanguage(string $language, string $route, bool $forceLanguagePrefix): string
    {
        $normalizedRoute = normalize_public_route($route) ?? '/';
        $normalizedLanguage = $this->normalizeLanguage($language);

        if ($normalizedLanguage === '' || (!$forceLanguagePrefix && $normalizedLanguage === $this->defaultLanguage)) {
            return $normalizedRoute;
        }

        if ($normalizedRoute === '/') {
            return '/' . rawurlencode($normalizedLanguage);
        }

        return '/' . rawurlencode($normalizedLanguage) . $normalizedRoute;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findPublishedArticle(string $slug, string $language): ?array
    {
        $normalizedSlug = $this->normalizeSlug($slug);
        $normalizedLanguage = $this->normalizeLanguage($language);
        if ($normalizedSlug === '' || $normalizedLanguage === '') {
            return null;
        }

        $article = $this->repository->findPublished($normalizedSlug, $normalizedLanguage);
        if (is_array($article)) {
            return $article;
        }

        if ($normalizedLanguage === $this->defaultLanguage) {
            return null;
        }

        return $this->repository->findPublished($normalizedSlug, $this->defaultLanguage);
    }

    private function normalizeSlug(string $slug): string
    {
        $normalized = strtolower(trim($slug));
        $normalized = preg_replace('/[^a-z0-9-]+/i', '-', $normalized) ?? '';

        return trim($normalized, '-');
    }

    private function normalizeLanguage(string $language): string
    {
        $normalized = strtolower(trim($language));

        return $normalized !== '' ? $normalized : $this->defaultLanguage;
    }
}
