<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

interface BlogRepositoryInterface
{
    public function dataDir(): string;

    /**
     * @param array<string, mixed> $article
     * @return array{path: string, article: array<string, mixed>, created: bool}
     */
    public function save(array $article, ?string $previousSlug = null, ?string $previousLanguage = null): array;

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug, string $language): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findPublished(string $slug, string $language): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allArticles(?string $language = null): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publishedArticles(string $language, ?string $category = null, ?string $tag = null): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publishedArticleTree(string $language, ?string $category = null, ?string $tag = null): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publishedArticleTreeForPage(
        string $pageSlug,
        string $language,
        ?string $category = null,
        ?string $tag = null
    ): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function childArticles(string $parentSlug, string $language, bool $publishedOnly = false): array;

    public function hasChildren(string $slug, string $language, bool $publishedOnly = false): bool;

    public function delete(string $slug, string $language): bool;

    public function detachChildrenFromParent(string $parentSlug, string $language): int;

    /**
     * @param array<string, mixed> $article
     * @return array<string, mixed>
     */
    public function withRelations(array $article, bool $publishedOnly = true): array;

    public function reassignChildrenToParentSlug(string $previousSlug, string $language, string $newSlug): void;

    /**
     * @return array<int, string>
     */
    public function categories(?string $language = null, bool $publishedOnly = false): array;

    /**
     * @return array<int, string>
     */
    public function tags(?string $language = null, bool $publishedOnly = false): array;
}
