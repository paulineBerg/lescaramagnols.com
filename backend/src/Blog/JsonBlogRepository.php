<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

use Caramagnols\Admin\AdminEditorialImageService;

final class JsonBlogRepository implements BlogRepositoryInterface
{
    private BlogTaxonomy $taxonomy;

    public function __construct(private readonly string $dataDir, ?BlogTaxonomy $taxonomy = null)
    {
        $this->taxonomy = $taxonomy ?? BlogTaxonomy::fromDefaultConfig();
    }

    public function dataDir(): string
    {
        return $this->dataDir;
    }

    /**
     * @param array<string, mixed> $article
     * @return array{path: string, article: array<string, mixed>, created: bool}
     */
    public function save(array $article, ?string $previousSlug = null, ?string $previousLanguage = null): array
    {
        $slug = $this->sanitizeSlug((string) ($article['slug'] ?? ''));
        $language = $this->sanitizeLanguage((string) ($article['lang'] ?? 'fr'));
        $targetPath = $this->articlePath($slug, $language);
        $previousPath = null;

        if (is_string($previousSlug) && $previousSlug !== '' && is_string($previousLanguage) && $previousLanguage !== '') {
            $previousPath = $this->articlePath($previousSlug, $previousLanguage);
        }

        $existing = $this->find($slug, $language);
        if ($existing === null && is_string($previousPath) && $previousPath !== $targetPath) {
            $existing = $this->readArticleFile($previousPath);
        }

        $storedArticle = $this->normalizeArticle($article);
        $storedArticle['slug'] = $slug;
        $storedArticle['lang'] = $language;
        $storedArticle['created_at'] = (string) ($existing['created_at'] ?? $article['created_at'] ?? date('c'));
        $storedArticle['updated_at'] = (string) ($article['updated_at'] ?? date('c'));

        $dir = dirname($targetPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Impossible de créer le dossier blog : %s', $dir));
        }

        $json = json_encode($storedArticle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new \RuntimeException('Impossible d’encoder l’article blog en JSON.');
        }

        $created = !file_exists($targetPath) && (!is_string($previousPath) || !file_exists($previousPath));
        $tmpPath = $targetPath . '.tmp';

        if (file_put_contents($tmpPath, $json) === false) {
            throw new \RuntimeException(sprintf('Impossible d’écrire le fichier temporaire blog : %s', $tmpPath));
        }

        if (!rename($tmpPath, $targetPath)) {
            @unlink($tmpPath);
            throw new \RuntimeException(sprintf('Impossible de déplacer le fichier blog vers : %s', $targetPath));
        }

        if (is_string($previousPath) && $previousPath !== $targetPath && file_exists($previousPath)) {
            @unlink($previousPath);
        }

        return [
            'path' => $targetPath,
            'article' => $storedArticle,
            'created' => $created,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug, string $language): ?array
    {
        return $this->readArticleFile($this->articlePath($slug, $language));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPublished(string $slug, string $language): ?array
    {
        $article = $this->find($slug, $language);

        if (!is_array($article) || !$this->isEffectivelyPublished($article)) {
            return null;
        }

        return $article;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allArticles(?string $language = null): array
    {
        $files = $this->articleFiles($language);

        if ($files === []) {
            return [];
        }

        $articles = [];

        foreach ($files as $file) {
            $article = $this->readArticleFile($file);

            if (!is_array($article)) {
                continue;
            }

            $articles[] = $article;
        }

        usort(
            $articles,
            fn (array $left, array $right): int => $this->articleTimestamp($right) <=> $this->articleTimestamp($left)
        );

        return $articles;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publishedArticles(string $language, ?string $category = null, ?string $tag = null): array
    {
        $articles = array_filter(
            $this->allArticles($language),
            fn (array $article): bool => $this->isEffectivelyPublished($article)
        );

        $normalizedCategory = $this->normalizeTerm($category);
        $normalizedTag = $this->normalizeTerm($tag);

        if ($normalizedCategory !== null || $normalizedTag !== null) {
            $articles = array_filter(
                $articles,
                fn (array $article): bool => $this->matchesFilters($article, $normalizedCategory, $normalizedTag)
            );
        }

        return array_values($articles);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publishedArticleTree(string $language, ?string $category = null, ?string $tag = null): array
    {
        return $this->buildArticleTree($this->publishedArticles($language, $category, $tag));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publishedArticleTreeForPage(
        string $pageSlug,
        string $language,
        ?string $category = null,
        ?string $tag = null
    ): array {
        $normalizedPageSlug = $this->normalizeOptionalSlug($pageSlug);

        if ($normalizedPageSlug === '') {
            return [];
        }

        $articles = array_filter(
            $this->publishedArticles($language, $category, $tag),
            static fn (array $article): bool => (string) ($article['page_slug'] ?? '') === $normalizedPageSlug
        );

        return $this->buildArticleTree(array_values($articles));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function childArticles(string $parentSlug, string $language, bool $publishedOnly = false): array
    {
        $normalizedParentSlug = $this->normalizeOptionalSlug($parentSlug);
        $normalizedLanguage = $this->sanitizeLanguage($language);

        if ($normalizedParentSlug === '') {
            return [];
        }

        $articles = array_filter(
            $this->allArticles($normalizedLanguage),
            function (array $article) use ($normalizedParentSlug, $normalizedLanguage, $publishedOnly): bool {
                if ($publishedOnly && !$this->isEffectivelyPublished($article)) {
                    return false;
                }

                return (string) ($article['parent_slug'] ?? '') === $normalizedParentSlug
                    && (string) ($article['parent_lang'] ?? '') === $normalizedLanguage;
            }
        );

        return $this->sortChildArticles(array_values($articles));
    }

    public function hasChildren(string $slug, string $language, bool $publishedOnly = false): bool
    {
        return $this->childArticles($slug, $language, $publishedOnly) !== [];
    }

    public function delete(string $slug, string $language): bool
    {
        $path = $this->articlePath($slug, $language);

        if (!is_file($path)) {
            return false;
        }

        return @unlink($path);
    }

    public function detachChildrenFromParent(string $parentSlug, string $language): int
    {
        $children = $this->childArticles($parentSlug, $language, false);
        $detached = 0;

        foreach ($children as $childArticle) {
            $childArticle['parent_slug'] = '';
            $childArticle['parent_lang'] = '';
            $childArticle['child_sort_order'] = null;
            $childArticle['updated_at'] = date('c');

            $this->save(
                $childArticle,
                (string) ($childArticle['slug'] ?? ''),
                (string) ($childArticle['lang'] ?? $language)
            );
            $detached++;
        }

        return $detached;
    }

    /**
     * @param array<string, mixed> $article
     * @return array<string, mixed>
     */
    public function withRelations(array $article, bool $publishedOnly = true): array
    {
        $article = $this->normalizeArticle($article);
        $parentSlug = (string) ($article['parent_slug'] ?? '');
        $parentLanguage = (string) ($article['parent_lang'] ?? '');

        $article['parent_article'] = null;
        if ($parentSlug !== '' && $parentLanguage !== '') {
            $parentArticle = $publishedOnly
                ? $this->findPublished($parentSlug, $parentLanguage)
                : $this->find($parentSlug, $parentLanguage);

            if (is_array($parentArticle)) {
                $article['parent_article'] = $parentArticle;
            }
        }

        $article['child_articles'] = $this->childArticles(
            (string) ($article['slug'] ?? ''),
            (string) ($article['lang'] ?? 'fr'),
            $publishedOnly
        );

        return $article;
    }

    public function reassignChildrenToParentSlug(string $previousSlug, string $language, string $newSlug): void
    {
        $normalizedPreviousSlug = $this->normalizeOptionalSlug($previousSlug);
        $normalizedNewSlug = $this->normalizeOptionalSlug($newSlug);
        $normalizedLanguage = $this->sanitizeLanguage($language);

        if ($normalizedPreviousSlug === '' || $normalizedNewSlug === '' || $normalizedPreviousSlug === $normalizedNewSlug) {
            return;
        }

        foreach ($this->childArticles($normalizedPreviousSlug, $normalizedLanguage, false) as $childArticle) {
            $childArticle['parent_slug'] = $normalizedNewSlug;
            $childArticle['parent_lang'] = $normalizedLanguage;
            $childArticle['updated_at'] = date('c');

            $this->save($childArticle, (string) ($childArticle['slug'] ?? ''), (string) ($childArticle['lang'] ?? $normalizedLanguage));
        }
    }

    /**
     * @return array<int, string>
     */
    public function categories(?string $language = null, bool $publishedOnly = false): array
    {
        $categories = [];

        foreach ($this->allArticles($language) as $article) {
            if ($publishedOnly && !$this->isEffectivelyPublished($article)) {
                continue;
            }

            $category = trim((string) ($article['category'] ?? ''));
            if ($category === '') {
                continue;
            }

            $categories[$this->normalizeTerm($category) ?? $category] = $category;
        }

        natcasesort($categories);

        return array_values($categories);
    }

    /**
     * @return array<int, string>
     */
    public function tags(?string $language = null, bool $publishedOnly = false): array
    {
        $tags = [];

        foreach ($this->allArticles($language) as $article) {
            if ($publishedOnly && !$this->isEffectivelyPublished($article)) {
                continue;
            }

            foreach (is_array($article['tags'] ?? null) ? $article['tags'] : [] as $rawTag) {
                $tag = trim((string) $rawTag);
                if ($tag === '') {
                    continue;
                }

                $tags[$this->normalizeTerm($tag) ?? $tag] = $tag;
            }
        }

        natcasesort($tags);

        return array_values($tags);
    }

    /**
     * @return array<int, string>
     */
    private function articleFiles(?string $language = null): array
    {
        $pattern = rtrim($this->dataDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.json';
        if (is_string($language) && trim($language) !== '') {
            $pattern = rtrim($this->dataDir, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . '*.'
                . $this->sanitizeLanguage($language)
                . '.json';
        }

        $files = glob($pattern);

        if ($files === false) {
            return [];
        }

        sort($files);

        return $files;
    }

    /**
     * @param array<string, mixed> $article
     */
    private function matchesFilters(array $article, ?string $category, ?string $tag): bool
    {
        if ($category !== null) {
            $articleCategory = $this->normalizeTaxonomyCategory((string) ($article['category'] ?? ''));
            if ($articleCategory !== $this->normalizeTaxonomyCategory($category)) {
                return false;
            }
        }

        if ($tag !== null) {
            $articleTags = array_map(
                fn (mixed $rawTag): ?string => $this->normalizeTaxonomyTag((string) $rawTag),
                is_array($article['tags'] ?? null) ? $article['tags'] : []
            );

            if (!in_array($this->normalizeTaxonomyTag($tag), array_filter($articleTags, static fn (?string $value): bool => $value !== null), true)) {
                return false;
            }
        }

        return true;
    }

    private function articlePath(string $slug, string $language): string
    {
        return rtrim($this->dataDir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $this->sanitizeSlug($slug)
            . '.'
            . $this->sanitizeLanguage($language)
            . '.json';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readArticleFile(string $path): ?array
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $raw = file_get_contents($path);

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $this->normalizeArticle($data) : null;
    }

    private function sanitizeSlug(string $slug): string
    {
        $normalized = strtolower(trim($slug));
        $normalized = preg_replace('/[^a-z0-9-]+/i', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');

        if ($normalized === '') {
            throw new \InvalidArgumentException('Slug d’article blog invalide.');
        }

        return $normalized;
    }

    private function sanitizeLanguage(string $language): string
    {
        $normalized = strtolower(trim($language));
        $normalized = preg_replace('/[^a-z]/', '', $normalized) ?? '';

        if ($normalized === '') {
            return 'fr';
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $article
     */
    private function articleTimestamp(array $article): int
    {
        $date = $article['date'] ?? $article['updated_at'] ?? null;
        $timestamp = is_string($date) ? strtotime($date) : false;

        return is_int($timestamp) ? $timestamp : 0;
    }

    /**
     * @param array<string, mixed> $article
     * @return array<string, mixed>
     */
    private function normalizeArticle(array $article): array
    {
        $article['slug'] = $this->normalizeOptionalSlug((string) ($article['slug'] ?? ''));
        $article['lang'] = $this->sanitizeLanguage((string) ($article['lang'] ?? 'fr'));
        $article['status'] = $this->normalizeStatus((string) ($article['status'] ?? 'draft'));
        $article['subcategory'] = $this->normalizeStoredText((string) ($article['subcategory'] ?? ''));
        $article['parent_slug'] = $this->normalizeOptionalSlug((string) ($article['parent_slug'] ?? ''));
        $article['parent_lang'] = $article['parent_slug'] !== ''
            ? $this->sanitizeLanguage((string) ($article['parent_lang'] ?? $article['lang']))
            : '';
        $article['child_sort_order'] = $this->normalizeStoredSortOrder($article['child_sort_order'] ?? null);
        $article['page_slug'] = $this->normalizeOptionalSlug((string) ($article['page_slug'] ?? ''));
        $article['featured_image'] = AdminEditorialImageService::sanitizeImageMetadata(
            is_array($article['featured_image'] ?? null) ? $article['featured_image'] : []
        );

        return $article;
    }

    private function normalizeOptionalSlug(string $slug): string
    {
        $normalized = strtolower(trim($slug));
        $normalized = preg_replace('/[^a-z0-9-]+/i', '-', $normalized) ?? '';

        return trim($normalized, '-');
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return in_array($normalized, ['draft', 'scheduled', 'published'], true) ? $normalized : 'draft';
    }

    private function normalizeStoredText(string $value): string
    {
        $normalized = trim($value);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return $normalized;
    }

    private function normalizeStoredSortOrder(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (!is_numeric($value)) {
            return null;
        }

        $normalized = (int) $value;

        return $normalized >= 1 ? $normalized : null;
    }

    /**
     * @param array<int, array<string, mixed>> $articles
     * @return array<int, array<string, mixed>>
     */
    private function buildArticleTree(array $articles): array
    {
        if ($articles === []) {
            return [];
        }

        $articlesByKey = [];
        $orderedKeys = [];

        foreach ($articles as $article) {
            $normalized = $this->normalizeArticle($article);
            $key = $this->articleKey($normalized);

            if ($key === null) {
                continue;
            }

            $normalized['child_articles'] = [];
            $articlesByKey[$key] = $normalized;
            $orderedKeys[] = $key;
        }

        if ($orderedKeys === []) {
            return [];
        }

        $childrenByParent = [];
        $rootKeys = [];

        foreach ($orderedKeys as $key) {
            $article = $articlesByKey[$key];
            $parentKey = $this->parentKey($article);

            if ($parentKey !== null && $parentKey !== $key && isset($articlesByKey[$parentKey])) {
                $childrenByParent[$parentKey][] = $key;
                continue;
            }

            $rootKeys[] = $key;
        }

        foreach ($childrenByParent as &$childKeys) {
            usort(
                $childKeys,
                fn (string $leftKey, string $rightKey): int => $this->compareChildArticles(
                    $articlesByKey[$leftKey],
                    $articlesByKey[$rightKey]
                )
            );
        }
        unset($childKeys);

        $tree = [];
        $visited = [];

        foreach ($rootKeys as $key) {
            $tree[] = $this->buildArticleNode($key, $articlesByKey, $childrenByParent, [], $visited);
        }

        foreach ($orderedKeys as $key) {
            if (isset($visited[$key])) {
                continue;
            }

            $tree[] = $this->buildArticleNode($key, $articlesByKey, $childrenByParent, [], $visited);
        }

        return $tree;
    }

    /**
     * @param array<string, array<string, mixed>> $articlesByKey
     * @param array<string, array<int, string>> $childrenByParent
     * @param array<string, true> $stack
     * @param array<string, true> $visited
     * @return array<string, mixed>
     */
    private function buildArticleNode(
        string $key,
        array $articlesByKey,
        array $childrenByParent,
        array $stack,
        array &$visited
    ): array {
        $article = $articlesByKey[$key];
        $visited[$key] = true;
        $stack[$key] = true;

        $article['child_articles'] = [];

        foreach ($childrenByParent[$key] ?? [] as $childKey) {
            if (isset($stack[$childKey]) || !isset($articlesByKey[$childKey])) {
                continue;
            }

            $article['child_articles'][] = $this->buildArticleNode(
                $childKey,
                $articlesByKey,
                $childrenByParent,
                $stack,
                $visited
            );
        }

        return $article;
    }

    /**
     * @param array<string, mixed> $article
     */
    private function articleKey(array $article): ?string
    {
        $slug = (string) ($article['slug'] ?? '');
        $language = (string) ($article['lang'] ?? '');

        if ($slug === '' || $language === '') {
            return null;
        }

        return $language . ':' . $slug;
    }

    /**
     * @param array<string, mixed> $article
     */
    private function parentKey(array $article): ?string
    {
        $parentSlug = (string) ($article['parent_slug'] ?? '');
        $parentLanguage = (string) ($article['parent_lang'] ?? '');

        if ($parentSlug === '' || $parentLanguage === '') {
            return null;
        }

        return $parentLanguage . ':' . $parentSlug;
    }

    /**
     * @param array<int, array<string, mixed>> $articles
     * @return array<int, array<string, mixed>>
     */
    private function sortChildArticles(array $articles): array
    {
        usort($articles, fn (array $left, array $right): int => $this->compareChildArticles($left, $right));

        return $articles;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function compareChildArticles(array $left, array $right): int
    {
        $leftOrder = $this->normalizeStoredSortOrder($left['child_sort_order'] ?? null);
        $rightOrder = $this->normalizeStoredSortOrder($right['child_sort_order'] ?? null);
        $leftHasOrder = $leftOrder !== null;
        $rightHasOrder = $rightOrder !== null;

        if ($leftHasOrder && $rightHasOrder) {
            $comparison = $leftOrder <=> $rightOrder;
            if ($comparison !== 0) {
                return $comparison;
            }
        } elseif ($leftHasOrder !== $rightHasOrder) {
            return $leftHasOrder ? -1 : 1;
        }

        $comparison = $this->articleCreationTimestamp($left) <=> $this->articleCreationTimestamp($right);
        if ($comparison !== 0) {
            return $comparison;
        }

        return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
    }

    /**
     * @param array<string, mixed> $article
     */
    private function articleCreationTimestamp(array $article): int
    {
        foreach (['created_at', 'date', 'updated_at'] as $field) {
            $value = $article[$field] ?? null;
            $timestamp = is_string($value) ? strtotime($value) : false;

            if (is_int($timestamp)) {
                return $timestamp;
            }
        }

        return 0;
    }

    private function normalizeTerm(?string $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return function_exists('mb_strtolower')
            ? mb_strtolower($normalized, 'UTF-8')
            : strtolower($normalized);
    }

    private function normalizeTaxonomyCategory(?string $value): ?string
    {
        return $this->taxonomy->resolveCategorySlug($value) ?? $this->normalizeTerm($value);
    }

    private function normalizeTaxonomyTag(?string $value): ?string
    {
        return $this->taxonomy->resolveTagSlug($value) ?? $this->normalizeTerm($value);
    }

    /**
     * @param array<string, mixed> $article
     */
    private function isEffectivelyPublished(array $article): bool
    {
        $status = $this->normalizeStatus((string) ($article['status'] ?? 'draft'));
        if ($status === 'published') {
            return true;
        }

        if ($status !== 'scheduled') {
            return false;
        }

        $timestamp = $this->scheduledPublishTimestamp($article);

        return is_int($timestamp) && $timestamp <= time();
    }

    /**
     * @param array<string, mixed> $article
     */
    private function scheduledPublishTimestamp(array $article): ?int
    {
        $date = trim((string) ($article['date'] ?? ''));
        if ($date === '') {
            return null;
        }

        $timestamp = strtotime($date);

        return is_int($timestamp) ? $timestamp : null;
    }
}
