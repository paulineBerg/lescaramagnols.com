<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

interface BlogDiscussionRepositoryInterface
{
    public function dataDir(): string;

    /**
     * @param array<string, mixed> $comment
     * @return array<string, mixed>
     */
    public function submitPending(string $articleSlug, string $articleLanguage, array $comment): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function approvedForArticle(string $articleSlug, string $articleLanguage): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(?string $status = null): array;

    /**
     * @return array{total: int, pending: int, approved: int, rejected: int}
     */
    public function stats(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function moderate(string $id, string $status, ?string $moderatorIdentifier = null): ?array;

    public function delete(string $id): bool;

    public function deleteThreadForArticle(string $articleSlug, string $articleLanguage): int;
}
