<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

use Caramagnols\Logging\AppEventLogger;

final class BlogSchedulePlanner
{
    private const MAX_CLUSTER_PUBLISHED_OR_SCHEDULED = 5;
    private const SCHEDULE_INTERVAL_DAYS = 11;
    private const CLUSTER_ROTATION_WINDOW_SLUGS = self::MAX_CLUSTER_PUBLISHED_OR_SCHEDULED;

    private AppEventLogger $eventLogger;
    private string $defaultLanguage;

    public function __construct(
        private readonly BlogRepositoryInterface $repository,
        ?AppEventLogger $logger = null
    ) {
        $this->eventLogger = $logger ?? app_event_logger();
        $this->defaultLanguage = strtolower(trim((string) app_config('default_lang', 'fr')));
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

        $scheduledDate = (int) $candidate['scheduledDate'];
        $primaryArticle = $candidate['primaryArticle'];
        $draftArticles = $candidate['draftArticles'];
        $languages = $candidate['languages'];
        $scheduledAt = date('Y-m-d H:i:s', $scheduledDate);

        $scheduled = 0;
        if (!$dryRun) {
            foreach ($draftArticles as $article) {
                $scheduledArticle = $article;
                $scheduledArticle['status'] = 'scheduled';
                $scheduledArticle['date'] = $scheduledAt;

                $this->repository->save(
                    $scheduledArticle,
                    $article['slug'] ?? null,
                    $article['lang'] ?? null
                );
                $scheduled++;
            }

            $this->eventLogger->content(
                'blog.article.schedule_next',
                [
                    'reason' => $candidate['reason'],
                    'slug' => $primaryArticle['slug'] ?? '',
                    'lang' => $primaryArticle['lang'] ?? '',
                    'langs' => $languages,
                    'scheduled_variant_count' => count($draftArticles),
                    'page_slug' => $primaryArticle['page_slug'] ?? '',
                    'scheduled_date' => $scheduledAt,
                ]
            );
        }

        return [
            'dry_run' => $dryRun,
            'scheduled' => $scheduled,
            'selected' => [
                'slug' => (string) ($primaryArticle['slug'] ?? ''),
                'lang' => (string) ($primaryArticle['lang'] ?? ''),
                'langs' => $languages,
                'page_slug' => (string) ($primaryArticle['page_slug'] ?? ''),
                'scheduled_at' => $scheduledAt,
                'title' => (string) ($primaryArticle['title'] ?? ''),
                'status' => (string) ($primaryArticle['status'] ?? 'draft'),
                'reason' => (string) $candidate['reason'],
                'cluster_page_slug' => (string) $candidate['clusterPageSlug'],
                'cluster_published_scheduled_count' => $candidate['clusterCount'],
                'rotation_window_index' => $candidate['rotationWindowIndex'],
                'rotation_window_size' => $candidate['rotationWindowSize'],
                'variant_count' => count($draftArticles),
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
        $clusterPublishedOrScheduledSlugs = [];
        $globalPublishedOrScheduledSlugs = [];
        $groups = [];
        $latestScheduled = null;

        foreach ($this->repository->allArticles() as $article) {
            if (!is_array($article)) {
                continue;
            }

            $pageSlug = $this->normalizePageSlug((string) ($article['page_slug'] ?? ''));
            $slug = $this->normalizeSlug((string) ($article['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $status = $this->normalizeStatus((string) ($article['status'] ?? 'draft'));
            $groupKey = $pageSlug . "\n" . $slug;

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'pageSlug' => $pageSlug,
                    'slug' => $slug,
                    'articles' => [],
                    'draftArticles' => [],
                    'draftOldestTimestamp' => PHP_INT_MAX,
                    'latestScheduledDate' => null,
                    'latestPublishedDate' => null,
                ];
            }

            $groups[$groupKey]['articles'][] = $article;

            if ($status === 'published' || $status === 'scheduled') {
                $clusterPublishedOrScheduledSlugs[$pageSlug][$slug] = true;
                $globalPublishedOrScheduledSlugs[$groupKey] = true;
                $articleDate = $this->parseDateTimestamp((string) ($article['date'] ?? ''));

                if ($status === 'published') {
                    $groups[$groupKey]['latestPublishedDate'] = $this->maxTimestamp(
                        $groups[$groupKey]['latestPublishedDate'],
                        $articleDate
                    );
                }
            }

            if ($status === 'scheduled') {
                $scheduledAt = $this->parseDateTimestamp((string) ($article['date'] ?? ''));
                if ($scheduledAt !== null && ($latestScheduled === null || $scheduledAt > $latestScheduled)) {
                    $latestScheduled = $scheduledAt;
                }

                $groups[$groupKey]['latestScheduledDate'] = $this->maxTimestamp(
                    $groups[$groupKey]['latestScheduledDate'],
                    $scheduledAt
                );
            }

            if ($status !== 'draft') {
                continue;
            }

            $groups[$groupKey]['draftArticles'][] = $article;
            $groups[$groupKey]['draftOldestTimestamp'] = min(
                (int) $groups[$groupKey]['draftOldestTimestamp'],
                $this->draftDateTimestamp($article)
            );
        }

        $clusters = [];
        $allDraftGroups = [];

        foreach ($groups as $group) {
            $pageSlug = (string) $group['pageSlug'];
            if (!isset($clusters[$pageSlug])) {
                $clusters[$pageSlug] = [
                    'publishedOrScheduled' => count($clusterPublishedOrScheduledSlugs[$pageSlug] ?? []),
                    'draftGroups' => [],
                ];
            }

            if ($group['draftArticles'] === []) {
                continue;
            }

            $draftGroup = [
                'pageSlug' => $pageSlug,
                'slug' => (string) $group['slug'],
                'draftArticles' => $this->sortArticlesByLanguagePreference($group['draftArticles']),
                'draftOldestTimestamp' => (int) $group['draftOldestTimestamp'],
                'languages' => $this->extractLanguages($group['draftArticles']),
                'primaryArticle' => $this->selectPrimaryArticle($group['draftArticles'], $group['articles']),
                'publicationReferenceDate' => $group['latestScheduledDate'] ?? $group['latestPublishedDate'],
            ];

            $clusters[$pageSlug]['draftGroups'][] = $draftGroup;
            $allDraftGroups[] = $draftGroup;
        }

        $activeClusters = [];
        foreach ($clusters as $pageSlug => $cluster) {
            if (
                $cluster['publishedOrScheduled'] < self::MAX_CLUSTER_PUBLISHED_OR_SCHEDULED
                && $cluster['draftGroups'] !== []
            ) {
                $activeClusters[] = [
                    'pageSlug' => $pageSlug,
                    'publishedOrScheduled' => (int) $cluster['publishedOrScheduled'],
                    'draftGroups' => $cluster['draftGroups'],
                ];
            }
        }

        $rotationWindowIndex = intdiv(
            count($globalPublishedOrScheduledSlugs),
            self::CLUSTER_ROTATION_WINDOW_SLUGS
        );

        if ($activeClusters !== []) {
            $minimumClusterCount = min(
                array_map(
                    static fn (array $cluster): int => (int) $cluster['publishedOrScheduled'],
                    $activeClusters
                )
            );
            $tiedClusters = array_values(
                array_filter(
                    $activeClusters,
                    static fn (array $cluster): bool => (int) $cluster['publishedOrScheduled'] === $minimumClusterCount
                )
            );
            $selected = $this->selectRotatingCluster($tiedClusters, $rotationWindowIndex);
            if (!is_array($selected)) {
                return null;
            }

            $draftGroup = self::selectHighestPriorityDraftGroup($selected['draftGroups']);
            if (!is_array($draftGroup)) {
                return null;
            }

            $scheduledDate = $this->scheduledDateForGroup($now, $latestScheduled, $draftGroup);
            return [
                'draftArticles' => $draftGroup['draftArticles'],
                'primaryArticle' => $draftGroup['primaryArticle'],
                'languages' => $draftGroup['languages'],
                'reason' => 'cluster_with_available_slots',
                'clusterPageSlug' => (string) $selected['pageSlug'],
                'clusterCount' => (int) $selected['publishedOrScheduled'],
                'rotationWindowIndex' => $rotationWindowIndex,
                'rotationWindowSize' => self::CLUSTER_ROTATION_WINDOW_SLUGS,
                'scheduledDate' => $scheduledDate,
            ];
        }

        if ($allDraftGroups === []) {
            return null;
        }

        $draftGroup = self::selectHighestPriorityDraftGroup($allDraftGroups);
        if (!is_array($draftGroup)) {
            return null;
        }

        $scheduledDate = $this->scheduledDateForGroup($now, $latestScheduled, $draftGroup);
        $fallbackClusterSlug = $this->normalizePageSlug((string) ($draftGroup['pageSlug'] ?? ''));
        $fallbackClusterCount = $clusters[$fallbackClusterSlug]['publishedOrScheduled'] ?? 0;

        return [
            'draftArticles' => $draftGroup['draftArticles'],
            'primaryArticle' => $draftGroup['primaryArticle'],
            'languages' => $draftGroup['languages'],
            'reason' => 'fallback_oldest_draft',
            'clusterPageSlug' => $fallbackClusterSlug,
            'clusterCount' => (int) $fallbackClusterCount,
            'rotationWindowIndex' => $rotationWindowIndex,
            'rotationWindowSize' => self::CLUSTER_ROTATION_WINDOW_SLUGS,
            'scheduledDate' => $scheduledDate,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $draftGroups
     * @return array<string, mixed>|null
     */
    private static function selectHighestPriorityDraftGroup(array $draftGroups): ?array
    {
        if ($draftGroups === []) {
            return null;
        }

        usort(
            $draftGroups,
            self::compareDraftGroups(...)
        );

        return $draftGroups[0];
    }

    /**
     * @param array<int, array<string, mixed>> $clusters
     * @return array<string, mixed>|null
     */
    private function selectRotatingCluster(array $clusters, int $rotationWindowIndex): ?array
    {
        if ($clusters === []) {
            return null;
        }

        usort(
            $clusters,
            static function (array $left, array $right) use ($rotationWindowIndex): int {
                $leftScore = self::clusterRotationScore($left, $rotationWindowIndex);
                $rightScore = self::clusterRotationScore($right, $rotationWindowIndex);
                if ($leftScore !== $rightScore) {
                    return $leftScore <=> $rightScore;
                }

                $leftDraftGroup = self::selectHighestPriorityDraftGroup($left['draftGroups'] ?? []);
                $rightDraftGroup = self::selectHighestPriorityDraftGroup($right['draftGroups'] ?? []);
                if (is_array($leftDraftGroup) && is_array($rightDraftGroup)) {
                    $draftGroupCompare = self::compareDraftGroups($leftDraftGroup, $rightDraftGroup);
                    if ($draftGroupCompare !== 0) {
                        return $draftGroupCompare;
                    }
                }

                return strcasecmp(
                    (string) ($left['pageSlug'] ?? ''),
                    (string) ($right['pageSlug'] ?? '')
                );
            }
        );

        return $clusters[0] ?? null;
    }

    /**
     * @param array<string, mixed> $cluster
     */
    private static function clusterRotationScore(array $cluster, int $rotationWindowIndex): string
    {
        return hash(
            'sha256',
            $rotationWindowIndex . "\n" . trim((string) ($cluster['pageSlug'] ?? ''))
        );
    }

    private function scheduledDateForNow(int $now, ?int $latestScheduled): int
    {
        if ($latestScheduled === null) {
            return strtotime('+' . self::SCHEDULE_INTERVAL_DAYS . ' days', $now);
        }

        return strtotime('+' . self::SCHEDULE_INTERVAL_DAYS . ' days', $latestScheduled);
    }

    /**
     * @param array<string, mixed> $draftGroup
     */
    private function scheduledDateForGroup(int $now, ?int $latestScheduled, array $draftGroup): int
    {
        $referenceDate = $draftGroup['publicationReferenceDate'] ?? null;
        if (is_int($referenceDate)) {
            return $referenceDate;
        }

        return $this->scheduledDateForNow($now, $latestScheduled);
    }

    private function normalizePageSlug(string $pageSlug): string
    {
        return trim((string) $pageSlug);
    }

    private function normalizeSlug(string $slug): string
    {
        return trim((string) $slug);
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

    /**
     * @param array<string, mixed> $leftDraftGroup
     * @param array<string, mixed> $rightDraftGroup
     */
    private static function compareDraftGroups(array $leftDraftGroup, array $rightDraftGroup): int
    {
        $leftHasPublication = isset($leftDraftGroup['publicationReferenceDate']) && is_int($leftDraftGroup['publicationReferenceDate']);
        $rightHasPublication = isset($rightDraftGroup['publicationReferenceDate']) && is_int($rightDraftGroup['publicationReferenceDate']);
        if ($leftHasPublication !== $rightHasPublication) {
            return $leftHasPublication ? -1 : 1;
        }

        $leftTime = (int) ($leftDraftGroup['draftOldestTimestamp'] ?? PHP_INT_MAX);
        $rightTime = (int) ($rightDraftGroup['draftOldestTimestamp'] ?? PHP_INT_MAX);
        if ($leftTime !== $rightTime) {
            return $leftTime <=> $rightTime;
        }

        $slugCompare = strcasecmp(
            (string) ($leftDraftGroup['slug'] ?? ''),
            (string) ($rightDraftGroup['slug'] ?? '')
        );
        if ($slugCompare !== 0) {
            return $slugCompare;
        }

        return strcasecmp(
            (string) ($leftDraftGroup['pageSlug'] ?? ''),
            (string) ($rightDraftGroup['pageSlug'] ?? '')
        );
    }

    /**
     * @param array<int, array<string, mixed>> $articles
     * @return array<int, array<string, mixed>>
     */
    private function sortArticlesByLanguagePreference(array $articles): array
    {
        usort(
            $articles,
            fn (array $left, array $right): int => $this->compareLanguageCodes(
                (string) ($left['lang'] ?? ''),
                (string) ($right['lang'] ?? '')
            )
        );

        return $articles;
    }

    /**
     * @param array<int, array<string, mixed>> $articles
     * @return array<int, string>
     */
    private function extractLanguages(array $articles): array
    {
        $languages = [];
        foreach ($articles as $article) {
            $language = strtolower(trim((string) ($article['lang'] ?? '')));
            if ($language === '') {
                continue;
            }

            $languages[$language] = true;
        }

        $sortedLanguages = array_keys($languages);
        usort($sortedLanguages, fn (string $left, string $right): int => $this->compareLanguageCodes($left, $right));

        return array_values($sortedLanguages);
    }

    /**
     * @param array<int, array<string, mixed>> $preferredArticles
     * @param array<int, array<string, mixed>> $fallbackArticles
     * @return array<string, mixed>
     */
    private function selectPrimaryArticle(array $preferredArticles, array $fallbackArticles): array
    {
        $article = $this->articleForPreferredLanguage($preferredArticles);
        if ($article !== null) {
            return $article;
        }

        $article = $this->articleForPreferredLanguage($fallbackArticles);
        if ($article !== null) {
            return $article;
        }

        return [];
    }

    /**
     * @param array<int, array<string, mixed>> $articles
     * @return array<string, mixed>|null
     */
    private function articleForPreferredLanguage(array $articles): ?array
    {
        $sortedArticles = $this->sortArticlesByLanguagePreference($articles);

        return $sortedArticles[0] ?? null;
    }

    private function compareLanguageCodes(string $left, string $right): int
    {
        $left = strtolower(trim($left));
        $right = strtolower(trim($right));

        if ($left === $right) {
            return 0;
        }

        if ($left === $this->defaultLanguage) {
            return -1;
        }

        if ($right === $this->defaultLanguage) {
            return 1;
        }

        return strcasecmp($left, $right);
    }

    private function maxTimestamp(?int $current, ?int $candidate): ?int
    {
        if ($candidate === null) {
            return $current;
        }

        if ($current === null || $candidate > $current) {
            return $candidate;
        }

        return $current;
    }
}
