<?php

declare(strict_types=1);

namespace Caramagnols\Editorial;

use Caramagnols\Blog\BlogDiscussionRepositoryInterface;
use Caramagnols\Blog\BlogRepositoryInterface;
use Caramagnols\Blog\JsonBlogDiscussionRepository;
use Caramagnols\Blog\JsonBlogRepository;
use Caramagnols\Content\JsonPageStore;
use Caramagnols\Content\PageRepository;
use Caramagnols\Navigation\JsonNavigationStore;
use Caramagnols\Navigation\NavigationRepository;
use RuntimeException;

final class EditorialSqlMirrorExporter
{
    public function __construct(
        private readonly PageRepository $pageSource,
        private readonly NavigationRepository $navigationSource,
        private readonly BlogRepositoryInterface $blogSource,
        private readonly ?BlogDiscussionRepositoryInterface $discussionSource = null
    ) {
    }

    /**
     * @return array{
     *     dry_run: bool,
     *     include_discussions: bool,
     *     pages: int,
     *     navigation_locations: int,
     *     articles_exported: int,
     *     articles_pruned: int,
     *     discussion_items_exported: int,
     *     discussion_threads_exported: int,
     *     discussion_threads_pruned: int,
     *     paths: array{pages: string, menus: string, blog: string, discussions: ?string}
     * }
     */
    public function export(
        string $pagesPath,
        string $menusPath,
        string $blogDir,
        ?string $discussionsDir = null,
        bool $includeDiscussions = false,
        bool $prune = true,
        bool $dryRun = false
    ): array {
        $pageRegistry = $this->pageSource->registry();
        $navigation = $this->navigationSource->loadCanonical();
        $articles = array_values(array_filter($this->blogSource->allArticles(), 'is_array'));

        $discussionRows = [];
        $groupedDiscussionRows = [];
        if ($includeDiscussions) {
            if ($this->discussionSource === null) {
                throw new RuntimeException('Export discussions impossible: source discussions SQL absente.');
            }

            if ($discussionsDir === null || trim($discussionsDir) === '') {
                throw new RuntimeException('Export discussions impossible: dossier JSON discussions manquant.');
            }

            $discussionRows = array_values(array_filter($this->discussionSource->all(), 'is_array'));
            $groupedDiscussionRows = $this->groupDiscussionRows($discussionRows);
        }

        $targetBlogRepository = new JsonBlogRepository($blogDir);
        $staleArticles = $prune
            ? $this->staleArticleCoordinates($targetBlogRepository->allArticles(), $articles)
            : [];

        $staleDiscussionThreads = [];
        if ($includeDiscussions && $prune) {
            $targetDiscussionRepository = new JsonBlogDiscussionRepository((string) $discussionsDir);
            $staleDiscussionThreads = $this->staleDiscussionThreadCoordinates(
                $targetDiscussionRepository->all(),
                $groupedDiscussionRows
            );
        }

        if (!$dryRun) {
            $pageStore = new JsonPageStore($pagesPath);
            if (!$pageStore->replaceRegistry($pageRegistry)) {
                throw new RuntimeException(sprintf('Ecriture du miroir pages impossible: %s', $pagesPath));
            }

            $navigationStore = new JsonNavigationStore($menusPath);
            if (!$navigationStore->saveCanonical($navigation)) {
                throw new RuntimeException(sprintf('Ecriture du miroir navigation impossible: %s', $menusPath));
            }

            foreach ($articles as $article) {
                $targetBlogRepository->save($article);
            }

            foreach ($staleArticles as [$slug, $language]) {
                $targetBlogRepository->delete($slug, $language);
            }

            if ($includeDiscussions) {
                $targetDiscussionRepository = new JsonBlogDiscussionRepository((string) $discussionsDir);

                foreach (array_keys($groupedDiscussionRows) as $threadKey) {
                    [$slug, $language] = $this->splitCoordinateKey($threadKey);
                    $targetDiscussionRepository->deleteThreadForArticle($slug, $language);
                }

                foreach ($staleDiscussionThreads as [$slug, $language]) {
                    $targetDiscussionRepository->deleteThreadForArticle($slug, $language);
                }

                foreach ($groupedDiscussionRows as $threadKey => $items) {
                    [$slug, $language] = $this->splitCoordinateKey($threadKey);

                    foreach ($items as $item) {
                        $targetDiscussionRepository->submitPending($slug, $language, $item);
                    }
                }
            }
        }

        return [
            'dry_run' => $dryRun,
            'include_discussions' => $includeDiscussions,
            'pages' => count(is_array($pageRegistry['pages'] ?? null) ? $pageRegistry['pages'] : []),
            'navigation_locations' => count(is_array($navigation['locations'] ?? null) ? $navigation['locations'] : []),
            'articles_exported' => count($articles),
            'articles_pruned' => count($staleArticles),
            'discussion_items_exported' => count($discussionRows),
            'discussion_threads_exported' => count($groupedDiscussionRows),
            'discussion_threads_pruned' => count($staleDiscussionThreads),
            'paths' => [
                'pages' => $pagesPath,
                'menus' => $menusPath,
                'blog' => $blogDir,
                'discussions' => $includeDiscussions ? $discussionsDir : null,
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $existingArticles
     * @param array<int, array<string, mixed>> $incomingArticles
     * @return array<int, array{0: string, 1: string}>
     */
    private function staleArticleCoordinates(array $existingArticles, array $incomingArticles): array
    {
        $incomingKeys = [];
        foreach ($incomingArticles as $article) {
            $slug = $this->normalizeSlug((string) ($article['slug'] ?? ''));
            $language = $this->normalizeLanguage((string) ($article['lang'] ?? 'fr'));
            if ($slug === '') {
                continue;
            }

            $incomingKeys[$this->coordinateKey($slug, $language)] = true;
        }

        $stale = [];
        foreach ($existingArticles as $article) {
            $slug = $this->normalizeSlug((string) ($article['slug'] ?? ''));
            $language = $this->normalizeLanguage((string) ($article['lang'] ?? 'fr'));
            if ($slug === '') {
                continue;
            }

            $key = $this->coordinateKey($slug, $language);
            if (!isset($incomingKeys[$key])) {
                $stale[$key] = [$slug, $language];
            }
        }

        return array_values($stale);
    }

    /**
     * @param array<int, array<string, mixed>> $existingRows
     * @param array<string, array<int, array<string, mixed>>> $incomingRows
     * @return array<int, array{0: string, 1: string}>
     */
    private function staleDiscussionThreadCoordinates(array $existingRows, array $incomingRows): array
    {
        $incomingKeys = [];
        foreach (array_keys($incomingRows) as $threadKey) {
            $incomingKeys[$threadKey] = true;
        }

        $stale = [];
        foreach ($existingRows as $row) {
            $slug = $this->normalizeSlug((string) ($row['article_slug'] ?? ''));
            $language = $this->normalizeLanguage((string) ($row['article_lang'] ?? 'fr'));
            if ($slug === '') {
                continue;
            }

            $key = $this->coordinateKey($slug, $language);
            if (!isset($incomingKeys[$key])) {
                $stale[$key] = [$slug, $language];
            }
        }

        return array_values($stale);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupDiscussionRows(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $slug = $this->normalizeSlug((string) ($row['article_slug'] ?? ''));
            $language = $this->normalizeLanguage((string) ($row['article_lang'] ?? 'fr'));
            if ($slug === '') {
                continue;
            }

            $grouped[$this->coordinateKey($slug, $language)][] = [
                'id' => (string) ($row['id'] ?? ''),
                'author' => (string) ($row['author'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'content' => (string) ($row['content'] ?? ''),
                'status' => (string) ($row['status'] ?? 'pending'),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
                'moderated_at' => $row['moderated_at'] ?? null,
                'moderated_by' => $row['moderated_by'] ?? null,
                'ip_hash' => (string) ($row['ip_hash'] ?? ''),
                'user_agent_hash' => (string) ($row['user_agent_hash'] ?? ''),
            ];
        }

        foreach ($grouped as &$threadItems) {
            usort(
                $threadItems,
                static function (array $left, array $right): int {
                    $leftCreatedAt = strtotime($left['created_at']) ?: 0;
                    $rightCreatedAt = strtotime($right['created_at']) ?: 0;

                    if ($leftCreatedAt !== $rightCreatedAt) {
                        return $leftCreatedAt <=> $rightCreatedAt;
                    }

                    $leftUpdatedAt = strtotime($left['updated_at']) ?: 0;
                    $rightUpdatedAt = strtotime($right['updated_at']) ?: 0;

                    if ($leftUpdatedAt !== $rightUpdatedAt) {
                        return $leftUpdatedAt <=> $rightUpdatedAt;
                    }

                    return strcmp($left['id'], $right['id']);
                }
            );
        }
        unset($threadItems);

        ksort($grouped);

        return $grouped;
    }

    private function coordinateKey(string $slug, string $language): string
    {
        return $this->normalizeSlug($slug) . '|' . $this->normalizeLanguage($language);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitCoordinateKey(string $coordinateKey): array
    {
        $parts = explode('|', $coordinateKey, 2);
        $slug = $this->normalizeSlug((string) ($parts[0] ?? ''));
        $language = $this->normalizeLanguage((string) ($parts[1] ?? 'fr'));

        return [$slug, $language];
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
        $normalized = preg_replace('/[^a-z]/', '', $normalized) ?? '';

        return $normalized !== '' ? $normalized : 'fr';
    }
}
