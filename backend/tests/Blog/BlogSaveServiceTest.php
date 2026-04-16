<?php

declare(strict_types=1);

use Caramagnols\Blog\BlogSaveService;
use Caramagnols\Blog\JsonBlogRepository;
use Caramagnols\Content\PageRepository;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\Logging\LoggerFactory;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';

final class BlogSaveServiceTest extends TestCase
{
    private string $blogDir;
    private string $logDir;
    private string $pagesFile;

    protected function setUp(): void
    {
        $this->blogDir = sys_get_temp_dir() . '/caramagnols-blog-' . bin2hex(random_bytes(6));
        $this->logDir = sys_get_temp_dir() . '/caramagnols-blog-logs-' . bin2hex(random_bytes(6));
        $this->pagesFile = sys_get_temp_dir() . '/caramagnols-pages-' . bin2hex(random_bytes(6)) . '.json';

        mkdir($this->blogDir, 0777, true);
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
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
    }

    protected function tearDown(): void
    {
        foreach ([$this->blogDir, $this->logDir] as $dir) {
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

    public function testSaveCreatesPublishedJsonArticle(): void
    {
        $service = $this->service();

        $result = $service->save(
            [
                'title' => 'Premier article',
                'slug' => 'premier-article',
                'lang' => 'fr',
                'status' => 'published',
                'date' => '2026-03-17 10:15:00',
                'content' => '<p>Contenu publié.</p>',
                'tags' => ['Austin', 'Club'],
            ],
            'admin@example.com'
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(201, $result['status']);
        $this->assertFileExists($this->blogDir . '/premier-article.fr.json');

        $stored = json_decode((string) file_get_contents($this->blogDir . '/premier-article.fr.json'), true);
        $this->assertIsArray($stored);
        $this->assertSame('published', $stored['status']);
        $this->assertSame('Premier article', $stored['title']);
        $this->assertSame('fr', $stored['lang']);
    }

    public function testSaveUpdatesExistingArticleAndRenamesOldFileWhenSlugChanges(): void
    {
        file_put_contents(
            $this->blogDir . '/ancien.fr.json',
            json_encode(
                [
                    'title' => 'Ancien',
                    'slug' => 'ancien',
                    'lang' => 'fr',
                    'status' => 'draft',
                    'date' => '2026-03-10 09:00:00',
                    'content' => '<p>Ancien contenu.</p>',
                    'created_at' => '2026-03-10T09:00:00+00:00',
                    'updated_at' => '2026-03-10T09:00:00+00:00',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        $service = $this->service();
        $result = $service->save(
            [
                'title' => 'Nouvel article',
                'slug' => 'nouvel-article',
                'previous_slug' => 'ancien',
                'previous_lang' => 'fr',
                'lang' => 'fr',
                'status' => 'draft',
                'date' => '2026-03-17 12:30:00',
                'content' => '<p>Contenu mis à jour.</p>',
            ],
            'admin@example.com'
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['status']);
        $this->assertFileDoesNotExist($this->blogDir . '/ancien.fr.json');
        $this->assertFileExists($this->blogDir . '/nouvel-article.fr.json');
    }

    public function testSaveReturnsValidationErrorsWhenRequiredFieldsAreMissing(): void
    {
        $service = $this->service();

        $result = $service->save(['slug' => '', 'content' => ''], 'admin@example.com');

        $this->assertFalse($result['ok']);
        $this->assertSame(422, $result['status']);
        $this->assertContains('Le titre est obligatoire.', $result['errors']);
        $this->assertContains('Le slug est obligatoire.', $result['errors']);
        $this->assertContains('Le contenu est obligatoire.', $result['errors']);
    }

    public function testRenamingParentSlugReassignsExistingChildren(): void
    {
        file_put_contents(
            $this->blogDir . '/parent.fr.json',
            json_encode(
                [
                    'title' => 'Parent',
                    'slug' => 'parent',
                    'lang' => 'fr',
                    'status' => 'published',
                    'date' => '2026-03-10 09:00:00',
                    'content' => '<p>Parent.</p>',
                    'created_at' => '2026-03-10T09:00:00+00:00',
                    'updated_at' => '2026-03-10T09:00:00+00:00',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
        file_put_contents(
            $this->blogDir . '/enfant.fr.json',
            json_encode(
                [
                    'title' => 'Enfant',
                    'slug' => 'enfant',
                    'lang' => 'fr',
                    'status' => 'published',
                    'date' => '2026-03-11 09:00:00',
                    'content' => '<p>Enfant.</p>',
                    'parent_slug' => 'parent',
                    'parent_lang' => 'fr',
                    'created_at' => '2026-03-11T09:00:00+00:00',
                    'updated_at' => '2026-03-11T09:00:00+00:00',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        $service = $this->service();
        $result = $service->save(
            [
                'title' => 'Parent renomme',
                'slug' => 'parent-renomme',
                'previous_slug' => 'parent',
                'previous_lang' => 'fr',
                'lang' => 'fr',
                'status' => 'published',
                'date' => '2026-03-17 12:30:00',
                'content' => '<p>Parent mis a jour.</p>',
            ],
            'admin@example.com'
        );

        $this->assertTrue($result['ok']);

        $child = json_decode((string) file_get_contents($this->blogDir . '/enfant.fr.json'), true);
        $this->assertIsArray($child);
        $this->assertSame('parent-renomme', $child['parent_slug']);
        $this->assertSame('fr', $child['parent_lang']);
    }

    public function testSaveRejectsUnknownPageAttachmentSlug(): void
    {
        $service = $this->service();

        $result = $service->save(
            [
                'title' => 'Article accroche invalide',
                'slug' => 'article-invalide',
                'lang' => 'fr',
                'status' => 'published',
                'date' => '2026-03-20 10:00:00',
                'content' => '<p>Contenu.</p>',
                'page_slug' => 'page-introuvable',
            ],
            'admin@example.com'
        );

        $this->assertFalse($result['ok']);
        $this->assertSame(422, $result['status']);
        $this->assertContains('La page sélectionnée pour l’accroche est introuvable.', $result['errors']);
    }

    public function testSavePersistsPageAttachmentSlugWhenPageExists(): void
    {
        $service = $this->service();

        $result = $service->save(
            [
                'title' => 'Article accroche valide',
                'slug' => 'article-valide',
                'lang' => 'fr',
                'status' => 'published',
                'date' => '2026-03-20 10:00:00',
                'content' => '<p>Contenu.</p>',
                'page_slug' => 'association',
            ],
            'admin@example.com'
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(201, $result['status']);

        $stored = json_decode((string) file_get_contents($this->blogDir . '/article-valide.fr.json'), true);
        $this->assertIsArray($stored);
        $this->assertSame('association', $stored['page_slug'] ?? null);
    }

    public function testSavePersistsFeaturedImageMetadata(): void
    {
        $service = $this->service();

        $result = $service->save(
            [
                'title' => 'Article avec image',
                'slug' => 'article-avec-image',
                'lang' => 'fr',
                'status' => 'published',
                'date' => '2026-03-21 11:00:00',
                'content' => '<p>Contenu avec image.</p>',
                'featured_image' => [
                    'src' => '/uploads/editorial/article/2026/03/article-avec-image-photo.jpg',
                    'alt' => '',
                    'title' => 'Photo de couverture',
                    'width' => 1280,
                    'height' => 720,
                ],
            ],
            'admin@example.com'
        );

        $this->assertTrue($result['ok']);
        $stored = json_decode((string) file_get_contents($this->blogDir . '/article-avec-image.fr.json'), true);
        $this->assertIsArray($stored);
        $this->assertIsArray($stored['featured_image'] ?? null);
        $this->assertSame('/uploads/editorial/article/2026/03/article-avec-image-photo.jpg', $stored['featured_image']['src'] ?? null);
        $this->assertSame('Article avec image', $stored['featured_image']['alt'] ?? null);
        $this->assertSame(1280, $stored['featured_image']['width'] ?? null);
        $this->assertSame(720, $stored['featured_image']['height'] ?? null);
    }

    private function service(): BlogSaveService
    {
        return new BlogSaveService(
            new JsonBlogRepository($this->blogDir),
            new AppEventLogger(new LoggerFactory($this->logDir, 'test')),
            new PageRepository($this->pagesFile)
        );
    }
}
