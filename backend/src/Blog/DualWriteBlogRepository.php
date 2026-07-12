<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

final class DualWriteBlogRepository implements BlogRepositoryInterface
{
    public function __construct(
        private readonly BlogRepositoryInterface $readerRepository,
        private readonly BlogRepositoryInterface $secondaryWriterRepository
    ) {
    }

    public function dataDir(): string
    {
        return $this->readerRepository->dataDir();
    }

    /**
     * @param array<string, mixed> $article
     * @return array{path: string, article: array<string, mixed>, created: bool}
     */
    public function save(array $article, ?string $previousSlug = null, ?string $previousLanguage = null): array
    {
        $this->secondaryWriterRepository->save($article, $previousSlug, $previousLanguage);

        return $this->readerRepository->save($article, $previousSlug, $previousLanguage);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug, string $language): ?array
    {
        return $this->readerRepository->find($slug, $language);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPublished(string $slug, string $language): ?array
    {
        return $this->readerRepository->findPublished($slug, $language);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allArticles(?string $language = null): array
    {
        return $this->readerRepository->allArticles($language);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publishedArticles(string $language, ?string $category = null, ?string $tag = null): array
    {
        return $this->readerRepository->publishedArticles($language, $category, $tag);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publishedArticleTree(string $language, ?string $category = null, ?string $tag = null): array
    {
        return $this->readerRepository->publishedArticleTree($language, $category, $tag);
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
        return $this->readerRepository->publishedArticleTreeForPage($pageSlug, $language, $category, $tag);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function childArticles(string $parentSlug, string $language, bool $publishedOnly = false): array
    {
        return $this->readerRepository->childArticles($parentSlug, $language, $publishedOnly);
    }

    public function hasChildren(string $slug, string $language, bool $publishedOnly = false): bool
    {
        return $this->readerRepository->hasChildren($slug, $language, $publishedOnly);
    }

    public function delete(string $slug, string $language): bool
    {
        if (!$this->secondaryWriterRepository->delete($slug, $language)) {
            return false;
        }

        return $this->readerRepository->delete($slug, $language);
    }

    public function detachChildrenFromParent(string $parentSlug, string $language): int
    {
        $this->secondaryWriterRepository->detachChildrenFromParent($parentSlug, $language);

        return $this->readerRepository->detachChildrenFromParent($parentSlug, $language);
    }

    /**
     * @param array<string, mixed> $article
     * @return array<string, mixed>
     */
    public function withRelations(array $article, bool $publishedOnly = true): array
    {
        return $this->readerRepository->withRelations($article, $publishedOnly);
    }

    public function reassignChildrenToParentSlug(string $previousSlug, string $language, string $newSlug): void
    {
        $this->secondaryWriterRepository->reassignChildrenToParentSlug($previousSlug, $language, $newSlug);
        $this->readerRepository->reassignChildrenToParentSlug($previousSlug, $language, $newSlug);
    }

    /**
     * @return array<int, string>
     */
    public function categories(?string $language = null, bool $publishedOnly = false): array
    {
        return $this->readerRepository->categories($language, $publishedOnly);
    }

    /**
     * @return array<int, string>
     */
    public function tags(?string $language = null, bool $publishedOnly = false): array
    {
        return $this->readerRepository->tags($language, $publishedOnly);
    }
}
