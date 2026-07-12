<?php

declare(strict_types=1);

use Caramagnols\Blog\SqlBlogRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';

final class SqlBlogRepositoryTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testPublishedTreeSortsChildrenByManualOrderThenCreationDate(): void
    {
        $repository = new SqlBlogRepository($this->editorialSqlDatabase());

        $repository->save([
            'title' => 'Parent',
            'slug' => 'parent',
            'lang' => 'fr',
            'status' => 'published',
            'category' => 'Actu',
            'tags' => ['Club'],
            'date' => '2026-03-10 08:00:00',
            'content' => '<p>Parent.</p>',
            'created_at' => '2026-03-10T08:00:00+00:00',
        ]);
        $repository->save([
            'title' => 'Enfant 2',
            'slug' => 'enfant-2',
            'lang' => 'fr',
            'status' => 'published',
            'category' => 'Actu',
            'tags' => ['Club', 'Sort'],
            'date' => '2026-03-11 08:00:00',
            'content' => '<p>Enfant 2.</p>',
            'parent_slug' => 'parent',
            'parent_lang' => 'fr',
            'child_sort_order' => 2,
            'created_at' => '2026-03-11T08:00:00+00:00',
        ]);
        $repository->save([
            'title' => 'Enfant 1',
            'slug' => 'enfant-1',
            'lang' => 'fr',
            'status' => 'published',
            'category' => 'Actu',
            'tags' => ['Sort'],
            'date' => '2026-03-12 08:00:00',
            'content' => '<p>Enfant 1.</p>',
            'parent_slug' => 'parent',
            'parent_lang' => 'fr',
            'child_sort_order' => 1,
            'created_at' => '2026-03-12T08:00:00+00:00',
        ]);

        $tree = $repository->publishedArticleTree('fr');

        $this->assertCount(1, $tree);
        $this->assertSame('parent', $tree[0]['slug']);
        $this->assertSame(
            ['enfant-1', 'enfant-2'],
            array_map(static fn (array $article): string => (string) $article['slug'], $tree[0]['child_articles'])
        );
        $this->assertSame(['Actu'], $repository->categories('fr', true));
        $this->assertSame(['Club', 'Sort'], $repository->tags('fr', true));
    }

    public function testDetachAndReassignChildren(): void
    {
        $repository = new SqlBlogRepository($this->editorialSqlDatabase());

        $repository->save([
            'title' => 'Parent',
            'slug' => 'parent',
            'lang' => 'fr',
            'status' => 'published',
            'content' => '<p>Parent.</p>',
        ]);
        $repository->save([
            'title' => 'Enfant',
            'slug' => 'enfant',
            'lang' => 'fr',
            'status' => 'published',
            'content' => '<p>Enfant.</p>',
            'parent_slug' => 'parent',
            'parent_lang' => 'fr',
        ]);

        $repository->reassignChildrenToParentSlug('parent', 'fr', 'parent-renomme');
        $child = $repository->find('enfant', 'fr');

        $this->assertIsArray($child);
        $this->assertSame('parent-renomme', $child['parent_slug']);

        $detached = $repository->detachChildrenFromParent('parent-renomme', 'fr');
        $this->assertSame(1, $detached);

        $child = $repository->find('enfant', 'fr');
        $this->assertIsArray($child);
        $this->assertSame('', $child['parent_slug']);
        $this->assertSame('', $child['parent_lang']);
    }

    public function testScheduledArticlesArePublishedAutomaticallyWhenDateIsReached(): void
    {
        $repository = new SqlBlogRepository($this->editorialSqlDatabase());

        $repository->save([
            'title' => 'Publie',
            'slug' => 'publie',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-10 08:00:00',
            'content' => '<p>Publie.</p>',
        ]);
        $repository->save([
            'title' => 'Planifie atteint',
            'slug' => 'planifie-atteint',
            'lang' => 'fr',
            'status' => 'scheduled',
            'date' => '2020-01-01 08:00:00',
            'content' => '<p>Planifie atteint.</p>',
        ]);
        $repository->save([
            'title' => 'Planifie futur',
            'slug' => 'planifie-futur',
            'lang' => 'fr',
            'status' => 'scheduled',
            'date' => '2099-01-01 08:00:00',
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

    public function testSaveAndFindRoundTripFeaturedImageMetadata(): void
    {
        $repository = new SqlBlogRepository($this->editorialSqlDatabase());

        $repository->save([
            'title' => 'Article image',
            'slug' => 'article-image',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-21 10:00:00',
            'content' => '<p>Contenu.</p>',
            'featured_image' => [
                'src' => '/uploads/editorial/article/2026/03/article-image.jpg',
                'alt' => 'Visuel article',
                'title' => 'Titre visuel',
                'width' => 1200,
                'height' => 630,
            ],
        ]);

        $article = $repository->find('article-image', 'fr');

        $this->assertIsArray($article);
        $this->assertIsArray($article['featured_image'] ?? null);
        $this->assertSame('/uploads/editorial/article/2026/03/article-image.jpg', $article['featured_image']['src'] ?? null);
        $this->assertSame('Visuel article', $article['featured_image']['alt'] ?? null);
        $this->assertSame(1200, $article['featured_image']['width'] ?? null);
        $this->assertSame(630, $article['featured_image']['height'] ?? null);
        $this->assertIsArray($article['translations'] ?? null);
        $this->assertArrayNotHasKey('__article_meta', $article['translations']);
    }
}
