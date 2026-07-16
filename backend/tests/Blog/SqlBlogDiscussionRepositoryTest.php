<?php

declare(strict_types=1);

use Caramagnols\Blog\SqlBlogDiscussionRepository;
use Caramagnols\Blog\SqlBlogRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';

final class SqlBlogDiscussionRepositoryTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testSubmitModerateAndDeleteFlow(): void
    {
        $articleRepository = new SqlBlogRepository($this->editorialSqlDatabase());
        $discussionRepository = new SqlBlogDiscussionRepository($this->editorialSqlDatabase());

        $articleRepository->save([
            'title' => 'Article test',
            'slug' => 'article-test',
            'lang' => 'fr',
            'status' => 'published',
            'content' => '<p>Article test.</p>',
        ]);

        $saved = $discussionRepository->submitPending('article-test', 'fr', [
            'author' => 'Pauline',
            'email' => 'pauline@example.com',
            'content' => 'Bonjour',
        ]);

        $this->assertSame('pending', $saved['status']);

        $updated = $discussionRepository->moderate((string) ($saved['id'] ?? ''), 'approved', 'admin@example.com');
        $this->assertIsArray($updated);
        $this->assertSame('approved', $updated['status']);

        $approved = $discussionRepository->approvedForArticle('article-test', 'fr');
        $this->assertCount(1, $approved);
        $this->assertSame('Pauline', $approved[0]['author']);

        $stats = $discussionRepository->stats();
        $this->assertSame(1, $stats['total']);
        $this->assertSame(1, $stats['approved']);

        $this->assertTrue($discussionRepository->delete((string) ($saved['id'] ?? '')));
        $this->assertSame(0, $discussionRepository->stats()['total']);
    }

    public function testDeleteThreadAndCascadeWhenArticleIsDeleted(): void
    {
        $articleRepository = new SqlBlogRepository($this->editorialSqlDatabase());
        $discussionRepository = new SqlBlogDiscussionRepository($this->editorialSqlDatabase());

        $articleRepository->save([
            'title' => 'Article cascade',
            'slug' => 'article-cascade',
            'lang' => 'fr',
            'status' => 'published',
            'content' => '<p>Article.</p>',
        ]);

        $discussionRepository->submitPending('article-cascade', 'fr', [
            'author' => 'A',
            'email' => 'a@example.com',
            'content' => 'Message A',
        ]);
        $discussionRepository->submitPending('article-cascade', 'fr', [
            'author' => 'B',
            'email' => 'b@example.com',
            'content' => 'Message B',
        ]);

        $this->assertSame(2, $discussionRepository->deleteThreadForArticle('article-cascade', 'fr'));
        $this->assertSame(0, $discussionRepository->stats()['total']);

        $discussionRepository->submitPending('article-cascade', 'fr', [
            'author' => 'C',
            'email' => 'c@example.com',
            'content' => 'Message C',
        ]);

        $this->assertTrue($articleRepository->delete('article-cascade', 'fr'));
        $this->assertSame(0, $discussionRepository->stats()['total']);
    }
}
