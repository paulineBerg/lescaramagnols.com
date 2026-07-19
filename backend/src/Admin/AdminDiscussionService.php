<?php

declare(strict_types=1);

namespace Caramagnols\Admin;

use Caramagnols\Blog\BlogDiscussionRepositoryInterface;
use Caramagnols\Blog\BlogRepositoryInterface;

final class AdminDiscussionService
{
    /**
     * @param array<int, string> $availableLanguages
     */
    public function __construct(
        private readonly BlogDiscussionRepositoryInterface $discussionRepository,
        private readonly BlogRepositoryInterface $articleRepository,
        private readonly array $availableLanguages,
        private readonly string $defaultLanguage = 'fr'
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: ?string, lang: ?string, q: string}
     */
    public function normalizeFilters(array $query): array
    {
        $status = is_string($query['status'] ?? null) ? strtolower(trim((string) $query['status'])) : '';
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $status = '';
        }

        $language = is_string($query['lang'] ?? null) ? strtolower(trim((string) $query['lang'])) : '';
        if (!in_array($language, $this->availableLanguages, true)) {
            $language = '';
        }

        return [
            'status' => $status !== '' ? $status : null,
            'lang' => $language !== '' ? $language : null,
            'q' => is_string($query['q'] ?? null) ? trim((string) $query['q']) : '',
        ];
    }

    /**
     * @param array{status: ?string, lang: ?string, q: string} $filters
     * @return array{
     *   filters: array{status: ?string, lang: ?string, q: string},
     *   rows: array<int, array<string, mixed>>,
     *   counts: array{total: int, pending: int, approved: int, rejected: int}
     * }
     */
    public function viewModel(array $filters): array
    {
        $search = $this->normalizeSearch($filters['q']);
        $articles = $this->articleLookup();

        $rows = array_values(array_filter(
            $this->discussionRepository->all($filters['status']),
            function (array $row) use ($filters, $search, $articles): bool {
                $rowLanguage = (string) ($row['article_lang'] ?? $this->defaultLanguage);
                if ($filters['lang'] !== null && $rowLanguage !== $filters['lang']) {
                    return false;
                }

                if ($search === null) {
                    return true;
                }

                $articleKey = $this->articleKey(
                    (string) ($row['article_slug'] ?? ''),
                    $rowLanguage
                );
                $articleTitle = $articleKey !== null ? (string) ($articles[$articleKey] ?? '') : '';

                $haystack = implode(' ', [
                    (string) ($row['author'] ?? ''),
                    (string) ($row['email'] ?? ''),
                    (string) ($row['content'] ?? ''),
                    (string) ($row['article_slug'] ?? ''),
                    $articleTitle,
                ]);

                $normalizedHaystack = $this->normalizeSearch($haystack);

                return $normalizedHaystack !== null && str_contains($normalizedHaystack, $search);
            }
        ));

        $rows = array_map(
            fn (array $row): array => $this->mapRow($row, $articles),
            $rows
        );

        return [
            'filters' => $filters,
            'rows' => $rows,
            'counts' => $this->discussionRepository->stats(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: ?string, error: ?string}
     */
    public function handleAction(array $payload, ?string $actorIdentifier = null): array
    {
        $action = is_string($payload['discussion_action'] ?? null)
            ? strtolower(trim((string) $payload['discussion_action']))
            : '';
        $discussionId = is_string($payload['discussion_id'] ?? null)
            ? trim((string) $payload['discussion_id'])
            : '';

        if ($discussionId === '') {
            return [
                'success' => false,
                'message' => null,
                'error' => 'Discussion introuvable.',
            ];
        }

        if ($action === 'delete') {
            $deleted = $this->discussionRepository->delete($discussionId);

            return [
                'success' => $deleted,
                'message' => $deleted ? 'Discussion supprimée.' : null,
                'error' => $deleted ? null : 'Impossible de supprimer cette discussion.',
            ];
        }

        $targetStatus = match ($action) {
            'approve' => 'approved',
            'reject' => 'rejected',
            'pending' => 'pending',
            default => null,
        };

        if ($targetStatus === null) {
            return [
                'success' => false,
                'message' => null,
                'error' => 'Action de modération inconnue.',
            ];
        }

        $updated = $this->discussionRepository->moderate($discussionId, $targetStatus, $actorIdentifier);

        if (!is_array($updated)) {
            return [
                'success' => false,
                'message' => null,
                'error' => 'Discussion introuvable.',
            ];
        }

        $label = match ($targetStatus) {
            'approved' => 'approuvée',
            'rejected' => 'rejetée',
            default => 'remise en attente',
        };

        return [
            'success' => true,
            'message' => 'Discussion ' . $label . '.',
            'error' => null,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function availableLanguages(): array
    {
        return $this->availableLanguages;
    }

    /**
     * @return array<int, string>
     */
    public function supportedStatuses(): array
    {
        return ['pending', 'approved', 'rejected'];
    }

    /**
     * @return array{total: int, pending: int, approved: int, rejected: int}
     */
    public function dashboardSummary(): array
    {
        return $this->discussionRepository->stats();
    }

    /**
     * @return array<string, string>
     */
    private function articleLookup(): array
    {
        $lookup = [];

        foreach ($this->articleRepository->allArticles() as $article) {
            $slug = (string) ($article['slug'] ?? '');
            $language = (string) ($article['lang'] ?? $this->defaultLanguage);
            $key = $this->articleKey($slug, $language);

            if ($key === null) {
                continue;
            }

            $lookup[$key] = (string) ($article['title'] ?? 'Article sans titre');
        }

        return $lookup;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, string> $articles
     * @return array<string, mixed>
     */
    private function mapRow(array $row, array $articles): array
    {
        $slug = (string) ($row['article_slug'] ?? '');
        $language = (string) ($row['article_lang'] ?? $this->defaultLanguage);
        $key = $this->articleKey($slug, $language);
        $title = $key !== null ? (string) ($articles[$key] ?? 'Article introuvable') : 'Article introuvable';

        return [
            'id' => (string) ($row['id'] ?? ''),
            'articleSlug' => $slug,
            'articleLang' => $language,
            'articleTitle' => $title,
            'articleUrl' => app_url($language . '/blog/article/' . rawurlencode($slug)),
            'author' => (string) ($row['author'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'content' => (string) ($row['content'] ?? ''),
            'status' => (string) ($row['status'] ?? 'pending'),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
            'moderatedAt' => (string) ($row['moderated_at'] ?? ''),
            'moderatedBy' => (string) ($row['moderated_by'] ?? ''),
        ];
    }

    private function articleKey(string $slug, string $language): ?string
    {
        $slug = trim($slug);
        $language = trim($language);

        if ($slug === '' || $language === '') {
            return null;
        }

        return $language . ':' . $slug;
    }

    private function normalizeSearch(string $value): ?string
    {
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
