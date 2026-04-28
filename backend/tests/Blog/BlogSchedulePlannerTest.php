<?php

declare(strict_types=1);

use Caramagnols\Blog\BlogSchedulePlanner;
use Caramagnols\Blog\JsonBlogRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';

final class BlogSchedulePlannerTest extends TestCase
{
    private string $blogDir;

    protected function setUp(): void
    {
        $this->blogDir = sys_get_temp_dir() . '/caramagnols-blog-schedule-planner-' . bin2hex(random_bytes(6));
        mkdir($this->blogDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->blogDir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }

        @rmdir($this->blogDir);
    }

    public function testPlanNextDraftPicksActiveClusterWithLeastPublishedOrScheduledAndOldestDraft(): void
    {
        $repository = new JsonBlogRepository($this->blogDir);
        $this->seedArticleGroup('cluster-actif', [
            'slug' => 'actif-oldest',
            'status' => 'draft',
            'date' => '2026-01-10 09:00:00',
        ]);
        $this->seedArticleGroup('cluster-actif', [
            'slug' => 'actif-newer',
            'status' => 'draft',
            'date' => '2026-01-15 09:00:00',
        ]);
        $this->seedArticleGroup('cluster-full', [
            'slug' => 'full-published',
            'status' => 'published',
            'date' => '2026-01-01 09:00:00',
        ]);
        $this->seedArticleGroup('cluster-full', [
            'slug' => 'full-draft',
            'status' => 'draft',
            'date' => '2026-01-05 09:00:00',
        ]);

        for ($index = 1; $index <= 4; $index++) {
            $this->seedArticleGroup('cluster-full', [
                'slug' => 'full-draft-' . $index,
                'status' => 'scheduled',
                'date' => sprintf('2026-01-%02d 09:00:00', 16 + $index),
            ]);
        }

        $planner = new BlogSchedulePlanner($repository);
        $now = strtotime('2026-01-20 10:00:00');
        $result = $planner->planNextDraft($now, true);

        $this->assertSame(1, $result['article_count']);
        $this->assertSame('actif-oldest', $result['selected']['slug'] ?? '');
        $this->assertSame(['fr', 'de', 'en'], $result['selected']['langs'] ?? []);
        $this->assertSame(3, $result['selected']['variant_count'] ?? null);
        $this->assertSame('cluster-actif', $result['selected']['cluster_page_slug'] ?? '');
        $this->assertSame('cluster_with_available_slots', $result['selected']['reason'] ?? '');
        $this->assertSame(0, $result['scheduled']);
    }

    public function testPlanNextDraftFallsBackToOldestDraftWhenAllClustersAreFull(): void
    {
        $repository = new JsonBlogRepository($this->blogDir);
        $this->seedArticles('cluster-a', [
            'slug' => 'a-draft',
            'status' => 'draft',
            'date' => '2026-01-20 09:00:00',
        ]);
        $this->seedArticles('cluster-b', [
            'slug' => 'b-oldest',
            'status' => 'draft',
            'date' => '2026-01-10 09:00:00',
        ]);
        $this->seedArticles('cluster-b', [
            'slug' => 'b-draft',
            'status' => 'draft',
            'date' => '2026-01-21 09:00:00',
        ]);

        for ($index = 1; $index <= 5; $index++) {
            $this->seedArticles('cluster-a', [
                'slug' => 'a-planifie-' . $index,
                'status' => 'scheduled',
                'date' => sprintf('2026-01-%02d 09:00:00', 1 + $index),
            ]);
            $this->seedArticles('cluster-b', [
                'slug' => 'b-planifie-' . $index,
                'status' => 'published',
                'date' => sprintf('2026-01-%02d 09:00:00', 10 + $index),
            ]);
        }

        $planner = new BlogSchedulePlanner($repository);
        $now = strtotime('2026-01-30 10:00:00');
        $result = $planner->planNextDraft($now, true);

        $this->assertSame(1, $result['article_count']);
        $this->assertSame('b-oldest', $result['selected']['slug'] ?? '');
        $this->assertSame('fallback_oldest_draft', $result['selected']['reason'] ?? '');
        $this->assertSame(5, $result['selected']['cluster_published_scheduled_count'] ?? null);
    }

    public function testPlanNextDraftUsesLatestScheduledDateInClusterToComputeNextDate(): void
    {
        $repository = new JsonBlogRepository($this->blogDir);
        $this->seedArticles('cluster-date', [
            'slug' => 'draft-oldest',
            'status' => 'draft',
            'date' => '2026-01-01 09:00:00',
        ]);
        $this->seedArticles('cluster-date', [
            'slug' => 'scheduled-ancien',
            'status' => 'scheduled',
            'date' => '2026-01-11 09:00:00',
        ]);
        $this->seedArticles('cluster-date', [
            'slug' => 'scheduled-recente',
            'status' => 'scheduled',
            'date' => '2026-01-20 09:00:00',
        ]);

        $planner = new BlogSchedulePlanner($repository);
        $result = $planner->planNextDraft(strtotime('2026-01-01 10:00:00'), true);
        $this->assertSame('2026-01-31 09:00:00', $result['selected']['scheduled_at'] ?? '');
    }

    public function testPlanNextDraftUsesNowIfClusterHasNoScheduledArticle(): void
    {
        $repository = new JsonBlogRepository($this->blogDir);
        $this->seedArticles('cluster-sans-planning', [
            'slug' => 'draft',
            'status' => 'draft',
            'date' => '2026-01-01 09:00:00',
        ]);

        $planner = new BlogSchedulePlanner($repository);
        $now = strtotime('2026-01-05 10:00:00');
        $result = $planner->planNextDraft($now, true);
        $this->assertSame('2026-01-16 10:00:00', $result['selected']['scheduled_at'] ?? '');
    }

    public function testPlanNextDraftPersistsStatusAndDateWhenNotDryRun(): void
    {
        $repository = new JsonBlogRepository($this->blogDir);
        $this->seedArticleGroup('cluster-persistance', [
            'slug' => 'draft-persiste',
            'status' => 'draft',
            'date' => '2026-01-01 09:00:00',
        ]);

        $planner = new BlogSchedulePlanner($repository);
        $result = $planner->planNextDraft(strtotime('2026-01-05 10:00:00'), false);

        $this->assertSame(3, $result['scheduled']);
        $this->assertSame(1, $result['article_count']);

        foreach (['fr', 'en', 'de'] as $language) {
            $article = $repository->find('draft-persiste', $language);
            $this->assertIsArray($article);
            $this->assertSame('scheduled', $article['status'] ?? '');
            $this->assertSame('2026-01-16 10:00:00', $article['date'] ?? '');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function seedArticles(string $pageSlug, array $data): void
    {
        $repository = new JsonBlogRepository($this->blogDir);
        $repository->save(array_merge(
            [
                'title' => (string) $data['slug'],
                'slug' => (string) $data['slug'],
                'lang' => 'fr',
                'status' => 'draft',
                'category' => 'auto-retro',
                'tags' => ['classic'],
                'content' => '<p>Article.</p>',
                'featured_image' => [],
                'page_slug' => $pageSlug,
                'date' => '2026-01-01 09:00:00',
            ],
            $data
        ));
    }

    public function testPlanNextDraftCountsClusterQuotaBySlugAndNotByLanguageVariant(): void
    {
        $repository = new JsonBlogRepository($this->blogDir);

        $this->seedArticleGroup('cluster-triplet', [
            'slug' => 'triplet-planifie',
            'status' => 'scheduled',
            'date' => '2026-01-05 09:00:00',
        ]);
        $this->seedArticleGroup('cluster-triplet', [
            'slug' => 'triplet-draft',
            'status' => 'draft',
            'date' => '2026-01-06 09:00:00',
        ]);

        $this->seedArticles('cluster-single', [
            'slug' => 'single-planifie-1',
            'status' => 'published',
            'date' => '2026-01-03 09:00:00',
        ]);
        $this->seedArticles('cluster-single', [
            'slug' => 'single-planifie-2',
            'status' => 'published',
            'date' => '2026-01-04 09:00:00',
        ]);
        $this->seedArticles('cluster-single', [
            'slug' => 'single-draft',
            'status' => 'draft',
            'date' => '2026-01-02 09:00:00',
        ]);

        $planner = new BlogSchedulePlanner($repository);
        $result = $planner->planNextDraft(strtotime('2026-01-07 10:00:00'), true);

        $this->assertSame('cluster-triplet', $result['selected']['cluster_page_slug'] ?? '');
        $this->assertSame('triplet-draft', $result['selected']['slug'] ?? '');
        $this->assertSame(1, $result['selected']['cluster_published_scheduled_count'] ?? null);
    }

    public function testPlanNextDraftCompletesPartiallyScheduledTripletWithSameDate(): void
    {
        $repository = new JsonBlogRepository($this->blogDir);

        $this->seedArticles('cluster-partiel', [
            'slug' => 'triplet-partiel',
            'lang' => 'fr',
            'status' => 'draft',
            'date' => '2026-01-01 09:00:00',
        ]);
        $this->seedArticles('cluster-partiel', [
            'slug' => 'triplet-partiel',
            'lang' => 'en',
            'status' => 'draft',
            'date' => '2026-01-01 09:00:00',
        ]);
        $this->seedArticles('cluster-partiel', [
            'slug' => 'triplet-partiel',
            'lang' => 'de',
            'status' => 'scheduled',
            'date' => '2026-01-20 09:00:00',
        ]);

        $planner = new BlogSchedulePlanner($repository);
        $result = $planner->planNextDraft(strtotime('2026-01-07 10:00:00'), false);

        $this->assertSame('triplet-partiel', $result['selected']['slug'] ?? '');
        $this->assertSame('2026-01-20 09:00:00', $result['selected']['scheduled_at'] ?? '');
        $this->assertSame(2, $result['scheduled']);

        $articleFr = $repository->find('triplet-partiel', 'fr');
        $articleEn = $repository->find('triplet-partiel', 'en');
        $articleDe = $repository->find('triplet-partiel', 'de');

        $this->assertSame('scheduled', $articleFr['status'] ?? '');
        $this->assertSame('2026-01-20 09:00:00', $articleFr['date'] ?? '');
        $this->assertSame('scheduled', $articleEn['status'] ?? '');
        $this->assertSame('2026-01-20 09:00:00', $articleEn['date'] ?? '');
        $this->assertSame('scheduled', $articleDe['status'] ?? '');
        $this->assertSame('2026-01-20 09:00:00', $articleDe['date'] ?? '');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function seedArticleGroup(string $pageSlug, array $data): void
    {
        foreach (['fr', 'en', 'de'] as $language) {
            $this->seedArticles($pageSlug, array_merge($data, ['lang' => $language]));
        }
    }
}
