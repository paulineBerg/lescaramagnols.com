<?php

declare(strict_types=1);

use Caramagnols\Admin\AdminBlogService;
use Caramagnols\Blog\BlogSaveService;
use Caramagnols\Blog\JsonBlogDiscussionRepository;
use Caramagnols\Blog\JsonBlogRepository;
use Caramagnols\Content\PageRepository;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\Logging\LoggerFactory;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class AdminBlogServiceTest extends TestCase
{
    private string $blogDir;
    private string $discussionDir;
    private string $logDir;
    private string $pagesFile;

    protected function setUp(): void
    {
        $this->blogDir = sys_get_temp_dir() . '/caramagnols-admin-blog-service-' . bin2hex(random_bytes(6));
        $this->discussionDir = sys_get_temp_dir() . '/caramagnols-admin-discussions-' . bin2hex(random_bytes(6));
        $this->logDir = sys_get_temp_dir() . '/caramagnols-admin-blog-service-logs-' . bin2hex(random_bytes(6));
        $this->pagesFile = ROOT_PATH . '/var/admin-blog-service-pages-' . uniqid() . '.json';

        mkdir($this->blogDir, 0777, true);
        mkdir($this->discussionDir, 0777, true);
        mkdir($this->logDir, 0777, true);

        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'association',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'route' => '/association',
                            'translations' => [
                                'fr' => ['title' => 'Association'],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );
    }

    protected function tearDown(): void
    {
        foreach ([$this->blogDir, $this->discussionDir, $this->logDir] as $dir) {
            $files = glob($dir . '/*');
            if (is_array($files)) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }

            @rmdir($dir);
        }

        if (file_exists($this->pagesFile)) {
            @unlink($this->pagesFile);
        }
    }

    public function testDeleteRemovesArticleAndAttachedDiscussions(): void
    {
        $blogRepository = new JsonBlogRepository($this->blogDir);
        $discussionRepository = new JsonBlogDiscussionRepository($this->discussionDir);

        $blogRepository->save([
            'title' => 'Article cible',
            'slug' => 'article-cible',
            'lang' => 'fr',
            'status' => 'published',
            'date' => '2026-03-19 10:00:00',
            'content' => '<p>Contenu.</p>',
        ]);
        $discussionRepository->submitPending('article-cible', 'fr', [
            'author' => 'A',
            'email' => 'a@example.com',
            'content' => 'Message A',
        ]);
        $discussionRepository->submitPending('article-cible', 'fr', [
            'author' => 'B',
            'email' => 'b@example.com',
            'content' => 'Message B',
        ]);

        $service = new AdminBlogService(
            $blogRepository,
            new BlogSaveService(
                $blogRepository,
                new AppEventLogger(new LoggerFactory($this->logDir, 'test')),
                new PageRepository($this->pagesFile)
            ),
            ['fr', 'en', 'de'],
            'fr',
            new PageRepository($this->pagesFile),
            $discussionRepository
        );

        $result = $service->delete('article-cible', 'fr');

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['deletedDiscussions']);
        $this->assertFileDoesNotExist($this->blogDir . '/article-cible.fr.json');
        $this->assertSame([], $discussionRepository->all());
    }

    public function testSaveRequiresParentPageAttachment(): void
    {
        $blogRepository = new JsonBlogRepository($this->blogDir);
        $discussionRepository = new JsonBlogDiscussionRepository($this->discussionDir);
        $service = new AdminBlogService(
            $blogRepository,
            new BlogSaveService(
                $blogRepository,
                new AppEventLogger(new LoggerFactory($this->logDir, 'test')),
                new PageRepository($this->pagesFile)
            ),
            ['fr', 'en', 'de'],
            'fr',
            new PageRepository($this->pagesFile),
            $discussionRepository
        );

        $result = $service->save([
            'article' => [
                'slug' => 'sans-page-parent',
                'active_language' => 'fr',
                'status' => 'published',
                'date' => '2026-03-20T10:00',
                'category' => 'auto-retro',
                'tags' => ['austin', 'histoire', 'voiture-ancienne'],
                'page_slug' => '',
            ],
            'translations' => [
                'fr' => [
                    'title' => 'Sans page parent',
                    'excerpt' => 'Résumé.',
                    'content' => '<p>Contenu.</p>',
                ],
            ],
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('La page parent est obligatoire pour rattacher l’article.', $result['error']);
    }

    public function testSavePersistsSharedFieldsAcrossMultipleLanguages(): void
    {
        $blogRepository = new JsonBlogRepository($this->blogDir);
        $discussionRepository = new JsonBlogDiscussionRepository($this->discussionDir);
        $service = new AdminBlogService(
            $blogRepository,
            new BlogSaveService(
                $blogRepository,
                new AppEventLogger(new LoggerFactory($this->logDir, 'test')),
                new PageRepository($this->pagesFile)
            ),
            ['fr', 'en', 'de'],
            'fr',
            new PageRepository($this->pagesFile),
            $discussionRepository
        );

        $result = $service->save([
            'article' => [
                'slug' => 'mini-austin-dossier',
                'active_language' => 'en',
                'status' => 'published',
                'author' => 'Admin',
                'date' => '2026-03-20T10:00',
                'page_slug' => 'association',
                'category' => 'auto-retro',
                'subcategory' => 'modeles-et-versions',
                'tags' => ['austin', 'modele', 'collection'],
                'featured_image_src' => '/uploads/editorial/article/mini-austin.webp',
            ],
            'translations' => [
                'fr' => [
                    'title' => 'Mini Austin classique',
                    'excerpt' => 'Version française.',
                    'content' => '<p>Contenu français.</p>',
                ],
                'en' => [
                    'title' => 'Classic Austin Mini',
                    'excerpt' => 'English version.',
                    'content' => '<p>English content.</p>',
                ],
                'de' => [
                    'title' => '',
                    'excerpt' => '',
                    'content' => '',
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('mini-austin-dossier', $result['slug']);
        $this->assertSame('en', $result['lang']);

        $frArticle = $blogRepository->find('mini-austin-dossier', 'fr');
        $enArticle = $blogRepository->find('mini-austin-dossier', 'en');
        $deArticle = $blogRepository->find('mini-austin-dossier', 'de');

        $this->assertIsArray($frArticle);
        $this->assertIsArray($enArticle);
        $this->assertNull($deArticle);

        $this->assertSame('association', $frArticle['page_slug']);
        $this->assertSame('association', $enArticle['page_slug']);
        $this->assertSame('auto-retro', $frArticle['category']);
        $this->assertSame('auto-retro', $enArticle['category']);
        $this->assertSame('modeles-et-versions', $frArticle['subcategory']);
        $this->assertSame('modeles-et-versions', $enArticle['subcategory']);
        $this->assertSame(['austin', 'modele', 'collection'], $frArticle['tags']);
        $this->assertSame(['austin', 'modele', 'collection'], $enArticle['tags']);
        $this->assertSame('/uploads/editorial/article/mini-austin.webp', $frArticle['featured_image']['src'] ?? null);
        $this->assertSame('/uploads/editorial/article/mini-austin.webp', $enArticle['featured_image']['src'] ?? null);
        $this->assertSame('Mini Austin classique', $frArticle['title']);
        $this->assertSame('Classic Austin Mini', $enArticle['title']);
    }
}
