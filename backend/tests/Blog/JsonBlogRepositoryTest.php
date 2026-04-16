<?php

declare(strict_types=1);

use Caramagnols\Blog\JsonBlogRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';

final class JsonBlogRepositoryTest extends TestCase
{
    private string $blogDir;

    protected function setUp(): void
    {
        $this->blogDir = sys_get_temp_dir() . '/caramagnols-blog-repository-' . bin2hex(random_bytes(6));
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

    public function testPublishedArticleTreeGroupsChildrenByCreationDateWhenNoManualOrderIsDefined(): void
    {
        $repository = new JsonBlogRepository($this->blogDir);

        $this->writeArticle([
            'title' => 'Parent',
            'slug' => 'parent',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-12 10:00:00',
            'content' => '<p>Parent.</p>',
            'created_at' => '2026-03-12T10:00:00+00:00',
        ]);
        $this->writeArticle([
            'title' => 'Enfant ancien',
            'slug' => 'enfant-ancien',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-15 10:00:00',
            'content' => '<p>Ancien.</p>',
            'parent_slug' => 'parent',
            'parent_lang' => 'fr',
            'created_at' => '2026-03-10T08:00:00+00:00',
        ]);
        $this->writeArticle([
            'title' => 'Enfant recent',
            'slug' => 'enfant-recent',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-16 10:00:00',
            'content' => '<p>Recent.</p>',
            'parent_slug' => 'parent',
            'parent_lang' => 'fr',
            'created_at' => '2026-03-11T08:00:00+00:00',
        ]);

        $tree = $repository->publishedArticleTree('fr');

        $this->assertCount(1, $tree);
        $this->assertSame('parent', $tree[0]['slug']);
        $this->assertSame(
            ['enfant-ancien', 'enfant-recent'],
            array_map(static fn (array $article): string => (string) $article['slug'], $tree[0]['child_articles'])
        );
    }

    public function testPublishedArticleTreeUsesManualOrderBeforeCreationDate(): void
    {
        $repository = new JsonBlogRepository($this->blogDir);

        $this->writeArticle([
            'title' => 'Parent',
            'slug' => 'parent',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-12 10:00:00',
            'content' => '<p>Parent.</p>',
            'created_at' => '2026-03-12T10:00:00+00:00',
        ]);
        $this->writeArticle([
            'title' => 'Ordre 2',
            'slug' => 'ordre-2',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-15 10:00:00',
            'content' => '<p>Ordre 2.</p>',
            'parent_slug' => 'parent',
            'parent_lang' => 'fr',
            'child_sort_order' => 2,
            'created_at' => '2026-03-10T08:00:00+00:00',
        ]);
        $this->writeArticle([
            'title' => 'Ordre 1',
            'slug' => 'ordre-1',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-16 10:00:00',
            'content' => '<p>Ordre 1.</p>',
            'parent_slug' => 'parent',
            'parent_lang' => 'fr',
            'child_sort_order' => 1,
            'created_at' => '2026-03-11T08:00:00+00:00',
        ]);
        $this->writeArticle([
            'title' => 'Sans ordre',
            'slug' => 'sans-ordre',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-17 10:00:00',
            'content' => '<p>Sans ordre.</p>',
            'parent_slug' => 'parent',
            'parent_lang' => 'fr',
            'created_at' => '2026-03-09T08:00:00+00:00',
        ]);

        $tree = $repository->publishedArticleTree('fr');

        $this->assertSame(
            ['ordre-1', 'ordre-2', 'sans-ordre'],
            array_map(static fn (array $article): string => (string) $article['slug'], $tree[0]['child_articles'])
        );
    }

    public function testPublishedArticleTreeForPageReturnsOnlyAttachedPublishedArticles(): void
    {
        $repository = new JsonBlogRepository($this->blogDir);

        $this->writeArticle([
            'title' => 'Parent associe',
            'slug' => 'parent-associe',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-18 10:00:00',
            'content' => '<p>Parent associe.</p>',
            'page_slug' => 'association',
            'created_at' => '2026-03-18T10:00:00+00:00',
        ]);
        $this->writeArticle([
            'title' => 'Enfant associe',
            'slug' => 'enfant-associe',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-19 10:00:00',
            'content' => '<p>Enfant associe.</p>',
            'page_slug' => 'association',
            'parent_slug' => 'parent-associe',
            'parent_lang' => 'fr',
            'created_at' => '2026-03-19T10:00:00+00:00',
        ]);
        $this->writeArticle([
            'title' => 'Article autre page',
            'slug' => 'autre-page',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-20 10:00:00',
            'content' => '<p>Autre page.</p>',
            'page_slug' => 'evenements',
            'created_at' => '2026-03-20T10:00:00+00:00',
        ]);
        $this->writeArticle([
            'title' => 'Brouillon associe',
            'slug' => 'brouillon-associe',
            'lang' => 'fr',
            'status' => 'draft',
            'date' => '2026-03-21 10:00:00',
            'content' => '<p>Brouillon.</p>',
            'page_slug' => 'association',
            'created_at' => '2026-03-21T10:00:00+00:00',
        ]);

        $tree = $repository->publishedArticleTreeForPage('association', 'fr');

        $this->assertCount(1, $tree);
        $this->assertSame('parent-associe', $tree[0]['slug']);
        $this->assertSame(
            ['enfant-associe'],
            array_map(static fn (array $article): string => (string) $article['slug'], $tree[0]['child_articles'])
        );
    }

    public function testScheduledArticlesArePublishedAutomaticallyWhenDateIsReached(): void
    {
        $repository = new JsonBlogRepository($this->blogDir);

        $this->writeArticle([
            'title' => 'Publie',
            'slug' => 'publie',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-21 10:00:00',
            'content' => '<p>Publie.</p>',
        ]);
        $this->writeArticle([
            'title' => 'Planifie atteint',
            'slug' => 'planifie-atteint',
            'lang' => 'fr',
            'status' => 'scheduled',
            'date' => '2020-01-01 10:00:00',
            'content' => '<p>Planifie atteint.</p>',
        ]);
        $this->writeArticle([
            'title' => 'Planifie futur',
            'slug' => 'planifie-futur',
            'lang' => 'fr',
            'status' => 'scheduled',
            'date' => '2099-01-01 10:00:00',
            'content' => '<p>Planifie futur.</p>',
        ]);

        $published = $repository->publishedArticles('fr');
        $slugs = array_map(static fn (array $article): string => (string) $article['slug'], $published);

        $this->assertContains('publie', $slugs);
        $this->assertContains('planifie-atteint', $slugs);
        $this->assertNotContains('planifie-futur', $slugs);
        $this->assertNotNull($repository->findPublished('planifie-atteint', 'fr'));
        $this->assertNull($repository->findPublished('planifie-futur', 'fr'));
    }

    /**
     * @param array<string, mixed> $article
     */
    private function writeArticle(array $article): void
    {
        $path = $this->blogDir . '/' . $article['slug'] . '.' . $article['lang'] . '.json';
        file_put_contents($path, json_encode($article, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
