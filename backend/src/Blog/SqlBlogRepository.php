<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

use Caramagnols\Admin\AdminEditorialImageService;
use Caramagnols\Database\EditorialDatabase;
use PDO;

final class SqlBlogRepository implements BlogRepositoryInterface
{
    private const TRANSLATIONS_META_KEY = '__article_meta';
    private const TRANSLATIONS_META_FEATURED_IMAGE_KEY = 'featured_image';

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function dataDir(): string
    {
        return 'sql://' . $this->database->table('blog_articles');
    }

    /**
     * @param array<string, mixed> $article
     * @return array{path: string, article: array<string, mixed>, created: bool}
     */
    public function save(array $article, ?string $previousSlug = null, ?string $previousLanguage = null): array
    {
        $slug = $this->sanitizeSlug((string) ($article['slug'] ?? ''));
        $language = $this->sanitizeLanguage((string) ($article['lang'] ?? 'fr'));

        $this->database->ensureReady();
        $pdo = $this->database->pdo();
        $pdo->beginTransaction();

        try {
            $targetRow = $this->findRow($pdo, $slug, $language);
            $previousRow = null;

            if (is_string($previousSlug) && trim($previousSlug) !== '' && is_string($previousLanguage) && trim($previousLanguage) !== '') {
                $previousRow = $this->findRow(
                    $pdo,
                    $this->normalizeOptionalSlug($previousSlug),
                    $this->sanitizeLanguage($previousLanguage)
                );
            }

            $existingArticle = null;
            if (is_array($targetRow)) {
                $existingArticle = $this->rowToArticle($targetRow);
            } elseif (is_array($previousRow)) {
                $existingArticle = $this->rowToArticle($previousRow);
            }

            $storedArticle = $this->normalizeArticle($article);
            $storedArticle['slug'] = $slug;
            $storedArticle['lang'] = $language;
            $storedArticle['created_at'] = (string) ($existingArticle['created_at'] ?? $article['created_at'] ?? date('c'));
            $storedArticle['updated_at'] = (string) ($article['updated_at'] ?? date('c'));

            $payload = $this->articlePayload($storedArticle);
            $created = $targetRow === null && $previousRow === null;

            if (is_array($targetRow)) {
                $this->updateRow($pdo, (int) $targetRow['id'], $payload);

                if (
                    is_array($previousRow)
                    && (int) $previousRow['id'] !== (int) $targetRow['id']
                ) {
                    $this->deleteById($pdo, (int) $previousRow['id']);
                }
            } elseif (is_array($previousRow)) {
                $this->updateRowWithCoordinates($pdo, (int) $previousRow['id'], $payload, $slug, $language);
            } else {
                $this->insertRow($pdo, $payload, $slug, $language);
            }

            $pdo->commit();

            return [
                'path' => sprintf('sql://%s/%s.%s', $this->database->table('blog_articles'), $slug, $language),
                'article' => $storedArticle,
                'created' => $created,
            ];
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug, string $language): ?array
    {
        $this->database->ensureReady();
        $row = $this->findRow($this->database->pdo(), $this->normalizeOptionalSlug($slug), $this->sanitizeLanguage($language));

        return is_array($row) ? $this->rowToArticle($row) : null;
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
        $this->database->ensureReady();
        $pdo = $this->database->pdo();

        if (is_string($language) && trim($language) !== '') {
            $statement = $pdo->prepare(
                sprintf(
                    'SELECT * FROM `%s` WHERE `lang` = :lang ORDER BY `id` ASC',
                    $this->database->table('blog_articles')
                )
            );
            $statement->execute(['lang' => $this->sanitizeLanguage($language)]);
            $rows = $statement->fetchAll();
        } else {
            $rows = $pdo->query(
                sprintf('SELECT * FROM `%s` ORDER BY `id` ASC', $this->database->table('blog_articles'))
            )->fetchAll();
        }

        $articles = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $articles[] = $this->rowToArticle($row);
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
        $this->database->ensureReady();
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'DELETE FROM `%s` WHERE `slug` = :slug AND `lang` = :lang',
                $this->database->table('blog_articles')
            )
        );

        $statement->execute([
            'slug' => $this->normalizeOptionalSlug($slug),
            'lang' => $this->sanitizeLanguage($language),
        ]);

        return $statement->rowCount() > 0;
    }

    public function detachChildrenFromParent(string $parentSlug, string $language): int
    {
        $this->database->ensureReady();
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `parent_slug` = \'\', `parent_lang` = \'\', `child_sort_order` = NULL, `updated_at` = :updated_at
                 WHERE `parent_slug` = :parent_slug AND `parent_lang` = :parent_lang',
                $this->database->table('blog_articles')
            )
        );
        $statement->execute([
            'updated_at' => date('c'),
            'parent_slug' => $this->normalizeOptionalSlug($parentSlug),
            'parent_lang' => $this->sanitizeLanguage($language),
        ]);

        return $statement->rowCount();
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

        $this->database->ensureReady();
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `parent_slug` = :new_parent_slug, `parent_lang` = :new_parent_lang, `updated_at` = :updated_at
                 WHERE `parent_slug` = :previous_parent_slug AND `parent_lang` = :previous_parent_lang',
                $this->database->table('blog_articles')
            )
        );
        $statement->execute([
            'new_parent_slug' => $normalizedNewSlug,
            'new_parent_lang' => $normalizedLanguage,
            'updated_at' => date('c'),
            'previous_parent_slug' => $normalizedPreviousSlug,
            'previous_parent_lang' => $normalizedLanguage,
        ]);
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
     * @return array<string, mixed>|null
     */
    private function findRow(PDO $pdo, string $slug, string $language): ?array
    {
        $statement = $pdo->prepare(
            sprintf(
                'SELECT * FROM `%s` WHERE `slug` = :slug AND `lang` = :lang LIMIT 1',
                $this->database->table('blog_articles')
            )
        );
        $statement->execute([
            'slug' => $slug,
            'lang' => $language,
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $article
     * @return array<string, mixed>
     */
    private function articlePayload(array $article): array
    {
        $translations = is_array($article['translations'] ?? null) ? $article['translations'] : [];
        $translations = $this->embedFeaturedImageInTranslations(
            $translations,
            is_array($article['featured_image'] ?? null) ? $article['featured_image'] : null
        );

        return [
            'title' => (string) ($article['title'] ?? ''),
            'status' => (string) ($article['status'] ?? 'draft'),
            'author' => $this->nullableString($article['author'] ?? null),
            'category' => $this->nullableString($article['category'] ?? null),
            'date_value' => $this->nullableString($article['date'] ?? null),
            'excerpt' => $this->nullableString($article['excerpt'] ?? null),
            'content' => (string) ($article['content'] ?? ''),
            'tags_json' => $this->encodeJson(is_array($article['tags'] ?? null) ? $article['tags'] : []),
            'translations_json' => $this->encodeJson($translations),
            'comments_json' => $this->encodeJson(is_array($article['comments'] ?? null) ? $article['comments'] : []),
            'page_slug' => $this->nullableSlug((string) ($article['page_slug'] ?? '')),
            'parent_slug' => $this->nullableSlug((string) ($article['parent_slug'] ?? '')),
            'parent_lang' => $this->nullableLanguage((string) ($article['parent_lang'] ?? '')),
            'child_sort_order' => $this->normalizeStoredSortOrder($article['child_sort_order'] ?? null),
            'created_at' => (string) ($article['created_at'] ?? date('c')),
            'updated_at' => (string) ($article['updated_at'] ?? date('c')),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insertRow(PDO $pdo, array $payload, string $slug, string $language): void
    {
        $statement = $pdo->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`slug`, `lang`, `title`, `status`, `author`, `category`, `date_value`, `excerpt`, `content`,
                     `tags_json`, `translations_json`, `comments_json`, `page_slug`, `parent_slug`, `parent_lang`,
                     `child_sort_order`, `created_at`, `updated_at`)
                 VALUES
                    (:slug, :lang, :title, :status, :author, :category, :date_value, :excerpt, :content,
                     :tags_json, :translations_json, :comments_json, :page_slug, :parent_slug, :parent_lang,
                     :child_sort_order, :created_at, :updated_at)',
                $this->database->table('blog_articles')
            )
        );

        $statement->execute(array_merge(
            ['slug' => $slug, 'lang' => $language],
            $payload
        ));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function updateRow(PDO $pdo, int $id, array $payload): void
    {
        $statement = $pdo->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `title` = :title,
                     `status` = :status,
                     `author` = :author,
                     `category` = :category,
                     `date_value` = :date_value,
                     `excerpt` = :excerpt,
                     `content` = :content,
                     `tags_json` = :tags_json,
                     `translations_json` = :translations_json,
                     `comments_json` = :comments_json,
                     `page_slug` = :page_slug,
                     `parent_slug` = :parent_slug,
                     `parent_lang` = :parent_lang,
                     `child_sort_order` = :child_sort_order,
                     `created_at` = :created_at,
                     `updated_at` = :updated_at
                 WHERE `id` = :id',
                $this->database->table('blog_articles')
            )
        );
        $statement->execute(array_merge($payload, ['id' => $id]));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function updateRowWithCoordinates(PDO $pdo, int $id, array $payload, string $slug, string $language): void
    {
        $statement = $pdo->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `slug` = :slug,
                     `lang` = :lang,
                     `title` = :title,
                     `status` = :status,
                     `author` = :author,
                     `category` = :category,
                     `date_value` = :date_value,
                     `excerpt` = :excerpt,
                     `content` = :content,
                     `tags_json` = :tags_json,
                     `translations_json` = :translations_json,
                     `comments_json` = :comments_json,
                     `page_slug` = :page_slug,
                     `parent_slug` = :parent_slug,
                     `parent_lang` = :parent_lang,
                     `child_sort_order` = :child_sort_order,
                     `created_at` = :created_at,
                     `updated_at` = :updated_at
                 WHERE `id` = :id',
                $this->database->table('blog_articles')
            )
        );
        $statement->execute(array_merge(
            $payload,
            [
                'id' => $id,
                'slug' => $slug,
                'lang' => $language,
            ]
        ));
    }

    private function deleteById(PDO $pdo, int $id): void
    {
        $statement = $pdo->prepare(
            sprintf('DELETE FROM `%s` WHERE `id` = :id', $this->database->table('blog_articles'))
        );
        $statement->execute(['id' => $id]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function rowToArticle(array $row): array
    {
        $translations = $this->decodeJsonAssoc($row['translations_json'] ?? null);
        $featuredImage = $this->extractFeaturedImageFromTranslations($translations);

        $article = [
            'title' => (string) ($row['title'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'lang' => (string) ($row['lang'] ?? 'fr'),
            'status' => (string) ($row['status'] ?? 'draft'),
            'author' => $row['author'] !== null ? (string) $row['author'] : '',
            'category' => $row['category'] !== null ? (string) $row['category'] : '',
            'date' => $row['date_value'] !== null ? (string) $row['date_value'] : '',
            'excerpt' => $row['excerpt'] !== null ? (string) $row['excerpt'] : '',
            'content' => $row['content'] !== null ? (string) $row['content'] : '',
            'tags' => $this->decodeJsonArray($row['tags_json'] ?? null),
            'translations' => $translations,
            'comments' => $this->decodeJsonArray($row['comments_json'] ?? null),
            'featured_image' => $featuredImage,
            'page_slug' => $row['page_slug'] !== null ? (string) $row['page_slug'] : '',
            'parent_slug' => $row['parent_slug'] !== null ? (string) $row['parent_slug'] : '',
            'parent_lang' => $row['parent_lang'] !== null ? (string) $row['parent_lang'] : '',
            'child_sort_order' => $this->normalizeStoredSortOrder($row['child_sort_order'] ?? null),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];

        return $this->normalizeArticle($article);
    }

    private function encodeJson(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($json)) {
            return '[]';
        }

        return $json;
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeJsonArray(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonAssoc(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function nullableSlug(string $slug): ?string
    {
        $normalized = $this->normalizeOptionalSlug($slug);

        return $normalized === '' ? null : $normalized;
    }

    private function nullableLanguage(string $language): ?string
    {
        if (trim($language) === '') {
            return null;
        }

        $normalized = $this->sanitizeLanguage($language);

        return $normalized === '' ? null : $normalized;
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
        $translations = is_array($article['translations'] ?? null) ? $article['translations'] : [];
        $featuredImage = AdminEditorialImageService::sanitizeImageMetadata(
            is_array($article['featured_image'] ?? null) ? $article['featured_image'] : []
        );
        if ($featuredImage === null) {
            $featuredImage = $this->extractFeaturedImageFromTranslations($translations);
        }

        $article['slug'] = $this->normalizeOptionalSlug((string) ($article['slug'] ?? ''));
        $article['lang'] = $this->sanitizeLanguage((string) ($article['lang'] ?? 'fr'));
        $article['status'] = $this->normalizeStatus((string) ($article['status'] ?? 'draft'));
        $article['parent_slug'] = $this->normalizeOptionalSlug((string) ($article['parent_slug'] ?? ''));
        $article['parent_lang'] = $article['parent_slug'] !== ''
            ? $this->sanitizeLanguage((string) ($article['parent_lang'] ?? $article['lang']))
            : '';
        $article['child_sort_order'] = $this->normalizeStoredSortOrder($article['child_sort_order'] ?? null);
        $article['page_slug'] = $this->normalizeOptionalSlug((string) ($article['page_slug'] ?? ''));
        $article['translations'] = $translations;
        $article['featured_image'] = $featuredImage;

        return $article;
    }

    /**
     * @param array<string, mixed> $translations
     * @param array<string, mixed>|null $featuredImage
     * @return array<string, mixed>
     */
    private function embedFeaturedImageInTranslations(array $translations, ?array $featuredImage): array
    {
        $meta = is_array($translations[self::TRANSLATIONS_META_KEY] ?? null)
            ? $translations[self::TRANSLATIONS_META_KEY]
            : [];
        $normalizedImage = AdminEditorialImageService::sanitizeImageMetadata(is_array($featuredImage) ? $featuredImage : []);

        if ($normalizedImage === null) {
            unset($meta[self::TRANSLATIONS_META_FEATURED_IMAGE_KEY]);
        } else {
            $meta[self::TRANSLATIONS_META_FEATURED_IMAGE_KEY] = $normalizedImage;
        }

        if ($meta === []) {
            unset($translations[self::TRANSLATIONS_META_KEY]);
        } else {
            $translations[self::TRANSLATIONS_META_KEY] = $meta;
        }

        return $translations;
    }

    /**
     * @param array<string, mixed> $translations
     * @return array<string, mixed>|null
     */
    private function extractFeaturedImageFromTranslations(array &$translations): ?array
    {
        $meta = is_array($translations[self::TRANSLATIONS_META_KEY] ?? null)
            ? $translations[self::TRANSLATIONS_META_KEY]
            : [];
        $featuredRaw = $meta[self::TRANSLATIONS_META_FEATURED_IMAGE_KEY] ?? null;
        $featured = AdminEditorialImageService::sanitizeImageMetadata(
            is_array($featuredRaw) ? $featuredRaw : []
        );

        unset($meta[self::TRANSLATIONS_META_FEATURED_IMAGE_KEY]);
        if ($meta === []) {
            unset($translations[self::TRANSLATIONS_META_KEY]);
        } else {
            $translations[self::TRANSLATIONS_META_KEY] = $meta;
        }

        return $featured;
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

    /**
     * @param array<string, mixed> $article
     */
    private function matchesFilters(array $article, ?string $category, ?string $tag): bool
    {
        if ($category !== null) {
            $articleCategory = $this->normalizeTerm((string) ($article['category'] ?? ''));
            if ($articleCategory !== $category) {
                return false;
            }
        }

        if ($tag !== null) {
            $articleTags = array_map(
                fn (mixed $rawTag): ?string => $this->normalizeTerm((string) $rawTag),
                is_array($article['tags'] ?? null) ? $article['tags'] : []
            );

            if (!in_array($tag, array_filter($articleTags, static fn (?string $value): bool => $value !== null), true)) {
                return false;
            }
        }

        return true;
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
}
