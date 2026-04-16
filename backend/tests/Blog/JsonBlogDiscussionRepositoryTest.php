<?php

declare(strict_types=1);

use Caramagnols\Blog\JsonBlogDiscussionRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';

final class JsonBlogDiscussionRepositoryTest extends TestCase
{
    private string $discussionDir;

    protected function setUp(): void
    {
        $this->discussionDir = sys_get_temp_dir() . '/caramagnols-discussions-' . bin2hex(random_bytes(6));
        mkdir($this->discussionDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->discussionDir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }

        @rmdir($this->discussionDir);
    }

    public function testSubmitAndModerateDiscussionFlow(): void
    {
        $repository = new JsonBlogDiscussionRepository($this->discussionDir);

        $saved = $repository->submitPending('article-test', 'fr', [
            'author' => 'Pauline',
            'email' => 'pauline@example.com',
            'content' => 'Bonjour à tous',
        ]);

        $this->assertSame('pending', $saved['status']);
        $this->assertNotSame('', (string) ($saved['id'] ?? ''));

        $this->assertSame([], $repository->approvedForArticle('article-test', 'fr'));

        $updated = $repository->moderate((string) ($saved['id'] ?? ''), 'approved', 'admin@example.com');
        $this->assertIsArray($updated);
        $this->assertSame('approved', $updated['status']);

        $approved = $repository->approvedForArticle('article-test', 'fr');
        $this->assertCount(1, $approved);
        $this->assertSame('Pauline', $approved[0]['author']);
        $this->assertSame('approved', $approved[0]['status']);
    }

    public function testStatsAndDelete(): void
    {
        $repository = new JsonBlogDiscussionRepository($this->discussionDir);

        $first = $repository->submitPending('article-1', 'fr', [
            'author' => 'A',
            'email' => 'a@example.com',
            'content' => 'Message A',
        ]);
        $second = $repository->submitPending('article-2', 'en', [
            'author' => 'B',
            'email' => 'b@example.com',
            'content' => 'Message B',
        ]);

        $repository->moderate((string) ($second['id'] ?? ''), 'rejected', 'admin@example.com');

        $stats = $repository->stats();
        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['pending']);
        $this->assertSame(0, $stats['approved']);
        $this->assertSame(1, $stats['rejected']);

        $this->assertTrue($repository->delete((string) ($first['id'] ?? '')));
        $remaining = $repository->all();
        $this->assertCount(1, $remaining);
        $this->assertSame((string) ($second['id'] ?? ''), (string) ($remaining[0]['id'] ?? ''));
    }

    public function testDeleteThreadForArticleRemovesAllAttachedDiscussions(): void
    {
        $repository = new JsonBlogDiscussionRepository($this->discussionDir);

        $repository->submitPending('article-cible', 'fr', [
            'author' => 'Lecteur A',
            'email' => 'a@example.com',
            'content' => 'Message A',
        ]);
        $repository->submitPending('article-cible', 'fr', [
            'author' => 'Lecteur B',
            'email' => 'b@example.com',
            'content' => 'Message B',
        ]);
        $repository->submitPending('article-autre', 'fr', [
            'author' => 'Lecteur C',
            'email' => 'c@example.com',
            'content' => 'Message C',
        ]);

        $removed = $repository->deleteThreadForArticle('article-cible', 'fr');
        $remaining = $repository->all();

        $this->assertSame(2, $removed);
        $this->assertCount(1, $remaining);
        $this->assertSame('article-autre', (string) ($remaining[0]['article_slug'] ?? ''));
    }
}
