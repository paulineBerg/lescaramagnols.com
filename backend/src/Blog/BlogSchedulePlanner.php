<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

use Caramagnols\Logging\AppEventLogger;

final class BlogSchedulePlanner
{
    private const MAX_CLUSTER_PUBLISHED_OR_SCHEDULED = 5;
    private const SCHEDULE_INTERVAL_DAYS = 11;

    private AppEventLogger $eventLogger;

    public function __construct(
        private readonly BlogRepositoryInterface $repository,
        ?AppEventLogger $logger = null
    ) {
        $this->eventLogger = $logger ?? app_event_logger();
    }

    /**
     * @return array{
     *   dry_run: bool,
     *   scheduled: int,
     *   selected: ?array<string, mixed>,
     *   reason: string,
     *   article_count: int
     * }
     */
    public function planNextDraft(?int $now = null, bool $dryRun = false): array
    {
        $now = $now ?? time();
        $candidate = $this->selectNextDraftCandidate($now);
        if ($candidate === null) {
            return [
                'dry_run' => $dryRun,
                'scheduled' => 0,
                'selected' => null,
                'reason' => 'no_draft_available',
                'article_count' => 0,
            ];
        }

        $scheduledDate = $candidate['scheduledDate'];
        $article = $candidate['article'];
        $scheduledAt = date('Y-m-d H:i:s', $scheduledDate);
        $scheduledArticle = $article;
        $scheduledArticle['status'] = 'scheduled';
        $scheduledArticle['date'] = $scheduledAt;

        $scheduled = 0;
        if (!$dryRun) {
            $this->repository->save(
                $scheduledArticle,
                $article['slug'] ?? null,
                $article['lang'] ?? null
            );

            $scheduled = 1;
            $this->eventLogger->content(
                'blog.article.schedule_next',
                [
                    'reason' => $candidate['reason'],
                    'slug' => $article['slug'] ?? '',
                    'lang' => $article['lang'] ?? '',
                    'page_slug' => $article['page_slug'] ?? '',
                    'scheduled_date' => $scheduledAt,
                ]
            );
        }

        return [
            'dry_run' => $dryRun,
            'scheduled' => $scheduled,
            'selected' => [
                'slug' => (string) ($article['slug'] ?? ''),
                'lang' => (string) ($article['lang'] ?? ''),
                'page_slug' => (string) ($article['page_slug'] ?? ''),
                'scheduled_at' => $scheduledAt,
                'title' => (string) ($article['title'] ?? ''),
                'status' => (string) ($article['status'] ?? 'draft'),
                'reason' => (string) $candidate['reason'],
                'cluster_page_slug' => (string) $candidate['clusterPageSlug'],
                'cluster_published_scheduled_count' => $candidate['clusterCount'],
            ],
            'reason' => (string) $candidate['reason'],
            'article_count' => 1,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function selectNextDraftCandidate(int $now): ?array
    {
        $clusters = [];
        $allDrafts = [];
        $latestScheduled = null;

        foreach ($this->repository->allArticles() as $article) {
            if (!is_array($article)) {
                continue;
            }

            $pageSlug = $this->normalizePageSlug((string) ($article['page_slug'] ?? ''));
            $status = $this->normalizeStatus((string) ($article['status'] ?? 'draft'));

            if (!isset($clusters[$pageSlug])) {
                $clusters[$pageSlug] = [
                    'publishedOrScheduled' => 0,
                    'drafts' => [],
                ];
            }

            if ($status === 'published' || $status === 'scheduled') {
                $clusters[$pageSlug]['publishedOrScheduled']++;
            }

            if ($status === 'scheduled') {
                $scheduledAt = $this->parseDateTimestamp((string) ($article['date'] ?? ''));
                if ($scheduledAt !== null && ($latestScheduled === null || $scheduledAt > $latestScheduled)) {
                    $latestScheduled = $scheduledAt;
                }
            }

            if ($status !== 'draft') {
                continue;
            }

            $draft = [
                'article' => $article,
                'created_at' => $this->draftDateTimestamp($article),
            ];
            $clusters[$pageSlug]['drafts'][] = $draft;
            $allDrafts[] = $draft;
        }

        $activeClusters = [];
        foreach ($clusters as $pageSlug => $cluster) {
            if (
                $cluster['publishedOrScheduled'] < self::MAX_CLUSTER_PUBLISHED_OR_SCHEDULED
                && $cluster['drafts'] !== []
            ) {
                $activeClusters[] = [
                    'pageSlug' => $pageSlug,
                    'publishedOrScheduled' => (int) $cluster['publishedOrScheduled'],
                    'drafts' => $cluster['drafts'],
                ];
            }
        }

        if ($activeClusters !== []) {
            usort(
                $activeClusters,
                static function (array $left, array $right): int {
                    $countDiff = $left['publishedOrScheduled'] <=> $right['publishedOrScheduled'];
                    if ($countDiff !== 0) {
                        return $countDiff;
                    }

                    $leftDraft = $left['drafts'][0] ?? ['created_at' => PHP_INT_MAX];
                    $rightDraft = $right['drafts'][0] ?? ['created_at' => PHP_INT_MAX];
                    if (!is_array($leftDraft) || !is_array($rightDraft)) {
                        return 0;
                    }

                    $leftTime = (int) ($leftDraft['created_at'] ?? PHP_INT_MAX);
                    $rightTime = (int) ($rightDraft['created_at'] ?? PHP_INT_MAX);
                    if ($leftTime !== $rightTime) {
                        return $leftTime <=> $rightTime;
                    }

                    return strcasecmp(
                        (string) ($left['drafts'][0]['article']['slug'] ?? ''),
                        (string) ($right['drafts'][0]['article']['slug'] ?? '')
                    );
                }
            );

            $selected = $activeClusters[0];
            if (!isset($selected['drafts']) || $selected['drafts'] === []) {
                return null;
            }

            $draft = $this->selectOldestDraft($selected['drafts']);
            if (!is_array($draft)) {
                return null;
            }

            $scheduledDate = $this->scheduledDateForNow($now, $latestScheduled);
            if ($scheduledDate === null) {
                return null;
            }

            return [
                'article' => $draft,
                'reason' => 'cluster_with_available_slots',
                'clusterPageSlug' => (string) $selected['pageSlug'],
                'clusterCount' => (int) $selected['publishedOrScheduled'],
                'scheduledDate' => $scheduledDate,
            ];
        }

        if ($allDrafts === []) {
            return null;
        }

        $draft = $this->selectOldestDraft($allDrafts);
        if (!is_array($draft)) {
            return null;
        }

        $scheduledDate = $this->scheduledDateForNow($now, $latestScheduled);
        if ($scheduledDate === null) {
            return null;
        }

        $fallbackClusterSlug = $this->normalizePageSlug((string) ($draft['page_slug'] ?? ''));
        $fallbackClusterCount = $clusters[$fallbackClusterSlug]['publishedOrScheduled'] ?? 0;

        return [
            'article' => $draft,
            'reason' => 'fallback_oldest_draft',
            'clusterPageSlug' => $fallbackClusterSlug,
            'clusterCount' => (int) $fallbackClusterCount,
            'scheduledDate' => $scheduledDate,
        ];
    }

    /**
     * @param array<int, array{article: array<string, mixed>, created_at: int}> $drafts
     * @return array<string, mixed>|null
     */
    private function selectOldestDraft(array $drafts): ?array
    {
        if ($drafts === []) {
            return null;
        }

        usort(
            $drafts,
            static function (array $left, array $right): int {
                $leftTs = (int) ($left['created_at'] ?? PHP_INT_MAX);
                $rightTs = (int) ($right['created_at'] ?? PHP_INT_MAX);
                if ($leftTs !== $rightTs) {
                    return $leftTs <=> $rightTs;
                }

                $leftSlug = (string) ($left['article']['slug'] ?? '');
                $rightSlug = (string) ($right['article']['slug'] ?? '');
                $leftLang = (string) ($left['article']['lang'] ?? '');
                $rightLang = (string) ($right['article']['lang'] ?? '');

                $slugCompare = strcasecmp($leftSlug, $rightSlug);
                if ($slugCompare !== 0) {
                    return $slugCompare;
                }

                return strcasecmp($leftLang, $rightLang);
            }
        );

        return $drafts[0]['article'] ?? null;
    }

    private function scheduledDateForNow(int $now, ?int $latestScheduled): ?int
    {
        if ($latestScheduled === null) {
            $nextTimestamp = strtotime('+' . self::SCHEDULE_INTERVAL_DAYS . ' days', $now);
            return is_int($nextTimestamp) ? $nextTimestamp : null;
        }

        $scheduledDate = strtotime('+' . self::SCHEDULE_INTERVAL_DAYS . ' days', $latestScheduled);
        return is_int($scheduledDate) ? $scheduledDate : null;
    }

    private function normalizePageSlug(string $pageSlug): string
    {
        return trim((string) $pageSlug);
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return in_array($normalized, ['draft', 'scheduled', 'published'], true)
            ? $normalized
            : 'draft';
    }

    /**
     * @return int|null
     */
    private function parseDateTimestamp(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return is_int($timestamp) ? $timestamp : null;
    }

    private function draftDateTimestamp(array $article): int
    {
        $dates = [
            (string) ($article['date'] ?? ''),
            (string) ($article['created_at'] ?? ''),
            (string) ($article['updated_at'] ?? ''),
        ];

        foreach ($dates as $date) {
            $timestamp = $this->parseDateTimestamp($date);
            if ($timestamp !== null) {
                return $timestamp;
            }
        }

        return PHP_INT_MAX;
    }
}
