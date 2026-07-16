<?php

declare(strict_types=1);

use Caramagnols\Blog\DualWriteBlogDiscussionRepository;
use Caramagnols\Blog\DualWriteBlogRepository;
use Caramagnols\Blog\JsonBlogDiscussionRepository;
use Caramagnols\Blog\JsonBlogRepository;
use Caramagnols\Blog\SqlBlogDiscussionRepository;
use Caramagnols\Blog\SqlBlogRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';

final class DualWriteBlogRepositoryTest extends TestCase
{
    use EditorialSqlTestTrait;

    private string $blogDir;
    private string $discussionDir;

    protected function setUp(): void
    {
        $this->blogDir = ROOT_PATH . '/var/dual-write-blog-' . uniqid('', true);
        $this->discussionDir = ROOT_PATH . '/var/dual-write-discussions-' . uniqid('', true);

        mkdir($this->blogDir, 0777, true);
        mkdir($this->discussionDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ([$this->blogDir, $this->discussionDir] as $dir) {
            $files = glob($dir . '/*');
            if (is_array($files)) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }

            @rmdir($dir);
        }

        $this->cleanupEditorialSqlDatabase();
    }

    public function testSaveAndModerateWriteToJsonAndSql(): void
    {
        $database = $this->editorialSqlDatabase();
        $jsonArticleRepository = new JsonBlogRepository($this->blogDir);
        $sqlArticleRepository = new SqlBlogRepository($database);
        $repository = new DualWriteBlogRepository($jsonArticleRepository, $sqlArticleRepository);

        $result = $repository->save([
            'title' => 'Article dual',
            'slug' => 'article-dual',
            'lang' => 'fr',
            'status' => 'published',
            'content' => '<p>Article dual.</p>',
        ]);

        $this->assertTrue($result['created']);
        $this->assertFileExists($this->blogDir . '/article-dual.fr.json');
        $this->assertIsArray($sqlArticleRepository->find('article-dual', 'fr'));

        $jsonDiscussionRepository = new JsonBlogDiscussionRepository($this->discussionDir);
        $sqlDiscussionRepository = new SqlBlogDiscussionRepository($database);
        $discussionRepository = new DualWriteBlogDiscussionRepository(
            $jsonDiscussionRepository,
            $sqlDiscussionRepository
        );

        $savedDiscussion = $discussionRepository->submitPending('article-dual', 'fr', [
            'author' => 'Lecteur',
            'email' => 'lecteur@example.com',
            'content' => 'Message test',
        ]);

        $this->assertFileExists($this->discussionDir . '/article-dual.fr.json');
        $this->assertSame(1, count($sqlDiscussionRepository->all()));

        $discussionRepository->moderate((string) ($savedDiscussion['id'] ?? ''), 'approved', 'admin@example.com');

        $this->assertSame(1, count($jsonDiscussionRepository->approvedForArticle('article-dual', 'fr')));
        $this->assertSame(1, count($sqlDiscussionRepository->approvedForArticle('article-dual', 'fr')));
    }
}
