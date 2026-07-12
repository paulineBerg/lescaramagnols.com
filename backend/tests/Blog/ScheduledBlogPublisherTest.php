<?php

declare(strict_types=1);

namespace Caramagnols\Tests\Blog;

use Caramagnols\Blog\JsonBlogRepository;
use Caramagnols\Blog\ScheduledBlogPublisher;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';

final class ScheduledBlogPublisherTest extends TestCase
{
    private string $blogDir;

    protected function setUp(): void
    {
        $this->blogDir = sys_get_temp_dir() . '/caramagnols-scheduled-blog-publisher-' . bin2hex(random_bytes(6));
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

    public function testPublishDueArticlesPromotesReachedScheduledArticles(): void
    {
        $repository = new JsonBlogRepository($this->blogDir);
        $this->writeArticle([
            'title' => 'Planifie atteint',
            'slug' => 'planifie-atteint',
            'lang' => 'fr',
            'status' => 'scheduled',
            'date' => '2026-04-20 09:00:00',
            'content' => '<p>Planifie.</p>',
        ]);
        $this->writeArticle([
            'title' => 'Planifie futur',
            'slug' => 'planifie-futur',
            'lang' => 'fr',
            'status' => 'scheduled',
            'date' => '2026-05-20 09:00:00',
            'content' => '<p>Futur.</p>',
        ]);

        $publisher = new ScheduledBlogPublisher($repository);
        $result = $publisher->publishDueArticles(false, strtotime('2026-04-23 10:00:00'));

        $this->assertSame(2, $result['checked']);
        $this->assertSame(1, $result['due']);
        $this->assertSame(1, $result['published']);
        $this->assertSame('published', $repository->find('planifie-atteint', 'fr')['status'] ?? null);
        $this->assertSame('scheduled', $repository->find('planifie-futur', 'fr')['status'] ?? null);
    }

    public function testPublishDueArticlesSupportsDryRunWithoutPersistingStatus(): void
    {
        $repository = new JsonBlogRepository($this->blogDir);
        $this->writeArticle([
            'title' => 'Planifie atteint',
            'slug' => 'planifie-atteint',
            'lang' => 'fr',
            'status' => 'scheduled',
            'date' => '2026-04-20 09:00:00',
            'content' => '<p>Planifie.</p>',
        ]);

        $publisher = new ScheduledBlogPublisher($repository);
        $result = $publisher->publishDueArticles(true, strtotime('2026-04-23 10:00:00'));

        $this->assertTrue($result['dry_run']);
        $this->assertSame(1, $result['due']);
        $this->assertSame(0, $result['published']);
        $this->assertSame('scheduled', $repository->find('planifie-atteint', 'fr')['status'] ?? null);
    }

    /**
     * @param array<string, mixed> $article
     */
    private function writeArticle(array $article): void
    {
        $path = $this->blogDir . '/' . $article['slug'] . '.' . $article['lang'] . '.json';
        file_put_contents(
            $path,
            (string) json_encode($article, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
