<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

final class DualWriteBlogDiscussionRepository implements BlogDiscussionRepositoryInterface
{
    public function __construct(
        private readonly BlogDiscussionRepositoryInterface $readerRepository,
        private readonly BlogDiscussionRepositoryInterface $secondaryWriterRepository
    ) {
    }

    public function dataDir(): string
    {
        return $this->readerRepository->dataDir();
    }

    /**
     * @param array<string, mixed> $comment
     * @return array<string, mixed>
     */
    public function submitPending(string $articleSlug, string $articleLanguage, array $comment): array
    {
        $seeded = $this->seedComment($comment);

        $this->secondaryWriterRepository->submitPending($articleSlug, $articleLanguage, $seeded);

        return $this->readerRepository->submitPending($articleSlug, $articleLanguage, $seeded);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function approvedForArticle(string $articleSlug, string $articleLanguage): array
    {
        return $this->readerRepository->approvedForArticle($articleSlug, $articleLanguage);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(?string $status = null): array
    {
        return $this->readerRepository->all($status);
    }

    /**
     * @return array{total: int, pending: int, approved: int, rejected: int}
     */
    public function stats(): array
    {
        return $this->readerRepository->stats();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function moderate(string $id, string $status, ?string $moderatorIdentifier = null): ?array
    {
        $this->secondaryWriterRepository->moderate($id, $status, $moderatorIdentifier);

        return $this->readerRepository->moderate($id, $status, $moderatorIdentifier);
    }

    public function delete(string $id): bool
    {
        if (!$this->secondaryWriterRepository->delete($id)) {
            return false;
        }

        return $this->readerRepository->delete($id);
    }

    public function deleteThreadForArticle(string $articleSlug, string $articleLanguage): int
    {
        $this->secondaryWriterRepository->deleteThreadForArticle($articleSlug, $articleLanguage);

        return $this->readerRepository->deleteThreadForArticle($articleSlug, $articleLanguage);
    }

    /**
     * @param array<string, mixed> $comment
     * @return array<string, mixed>
     */
    private function seedComment(array $comment): array
    {
        $seeded = $comment;
        $status = strtolower(trim((string) ($seeded['status'] ?? 'pending')));
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $status = 'pending';
        }

        $now = date('c');
        $seeded['status'] = $status;
        $seeded['id'] = $this->normalizeIdentifier((string) ($seeded['id'] ?? ''));
        if ($seeded['id'] === '') {
            $seeded['id'] = bin2hex(random_bytes(12));
        }

        $seeded['created_at'] = $this->normalizeDateTime($seeded['created_at'] ?? null, $now);
        $seeded['updated_at'] = $this->normalizeDateTime($seeded['updated_at'] ?? null, (string) $seeded['created_at']);

        if ($status !== 'pending') {
            $seeded['moderated_at'] = $this->normalizeDateTime($seeded['moderated_at'] ?? null, (string) $seeded['updated_at']);
            $moderatedBy = trim((string) ($seeded['moderated_by'] ?? ''));
            $seeded['moderated_by'] = $moderatedBy !== '' ? $moderatedBy : null;
        } else {
            $seeded['moderated_at'] = null;
            $seeded['moderated_by'] = null;
        }

        return $seeded;
    }

    private function normalizeIdentifier(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9]/', '', $normalized) ?? '';

        return $normalized;
    }

    private function normalizeDateTime(mixed $value, string $fallback): string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : $fallback;
    }
}
