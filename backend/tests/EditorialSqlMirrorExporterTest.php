<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests;

use Caramagnols\Blog\JsonBlogDiscussionRepository;
use Caramagnols\Blog\JsonBlogRepository;
use Caramagnols\Blog\SqlBlogDiscussionRepository;
use Caramagnols\Blog\SqlBlogRepository;
use Caramagnols\Content\PageRepository;
use Caramagnols\Content\StructuredPageRenderer;
use Caramagnols\Editorial\EditorialSqlMirrorExporter;
use Caramagnols\Navigation\NavigationRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

final class EditorialSqlMirrorExporterTest extends TestCase
{
    use EditorialSqlTestTrait;

    private string $pagesFile;
    private string $menusFile;
    private string $blogDir;
    private string $discussionsDir;

    protected function setUp(): void
    {
        $suffix = uniqid();
        $this->pagesFile = ROOT_PATH . '/var/editorial-export-pages-' . $suffix . '.json';
        $this->menusFile = ROOT_PATH . '/var/editorial-export-menus-' . $suffix . '.json';
        $this->blogDir = ROOT_PATH . '/var/editorial-export-blog-' . $suffix;
        $this->discussionsDir = ROOT_PATH . '/var/editorial-export-discussions-' . $suffix;

        @mkdir($this->blogDir, 0777, true);
        @mkdir($this->discussionsDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ([$this->pagesFile, $this->pagesFile . '.bak', $this->menusFile, $this->menusFile . '.bak'] as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        foreach ([$this->blogDir, $this->discussionsDir] as $directory) {
            $this->removeDirectory($directory);
        }

        $this->cleanupEditorialSqlDatabase();
    }

    public function testExporterMirrorsSqlContentToJsonAndPrunesStaleFiles(): void
    {
        $database = $this->editorialSqlDatabase();
        $pageRepository = new PageRepository($this->pagesFile, new StructuredPageRenderer(), 'sql', $database);
        $navigationRepository = new NavigationRepository($this->menusFile, 'sql', $database);
        $blogRepository = new SqlBlogRepository($database);
        $discussionRepository = new SqlBlogDiscussionRepository($database);

        $this->assertTrue($pageRepository->savePage([
            'slug' => 'mini-austin',
            'type' => 'structured_page',
            'status' => 'published',
            'route' => '/auto-retro/mini-austin',
            'translations' => [
                'fr' => [
                    'title' => 'Mini Austin',
                    'regions' => [
                        'hero' => [
                            'component' => 'heading',
                            'title' => 'Mini Austin',
                        ],
                    ],
                ],
            ],
        ]));

        $this->assertTrue($navigationRepository->saveCanonical([
            'meta' => ['version' => 2],
            'locations' => [
                'primary' => [[
                    'id' => 'mini',
                    'kind' => 'link',
                    'label' => ['text' => 'Mini Austin'],
                    'target' => ['route' => '/auto-retro/mini-austin'],
                    'children' => [],
                ]],
            ],
        ]));

        $blogRepository->save([
            'slug' => 'mini-austin-histoire',
            'lang' => 'fr',
            'title' => 'Mini Austin histoire',
            'status' => 'published',
            'page_slug' => 'mini-austin',
            'content' => '<p>Article exporte.</p>',
            'category' => 'auto-retro',
            'subcategory' => 'histoire-automobile',
            'tags' => ['mini-austin', 'histoire', 'collection'],
            'created_at' => '2026-04-28T10:00:00+00:00',
            'updated_at' => '2026-04-29T11:00:00+00:00',
        ]);

        $discussionRepository->submitPending('mini-austin-histoire', 'fr', [
            'id' => 'discussion-1',
            'author' => 'Pauline',
            'email' => 'pauline@example.com',
            'content' => 'Bonjour',
            'status' => 'approved',
            'created_at' => '2026-04-29T12:00:00+00:00',
            'updated_at' => '2026-04-29T12:30:00+00:00',
            'moderated_at' => '2026-04-29T12:30:00+00:00',
            'moderated_by' => 'admin@example.com',
            'ip_hash' => 'ip-hash',
            'user_agent_hash' => 'ua-hash',
        ]);

        file_put_contents($this->blogDir . '/obsolete.fr.json', '{"slug":"obsolete","lang":"fr"}');
        file_put_contents($this->discussionsDir . '/obsolete.fr.json', '{"article":{"slug":"obsolete","lang":"fr"},"items":[]}');

        $exporter = new EditorialSqlMirrorExporter(
            $pageRepository,
            $navigationRepository,
            $blogRepository,
            $discussionRepository
        );

        $summary = $exporter->export(
            $this->pagesFile,
            $this->menusFile,
            $this->blogDir,
            $this->discussionsDir,
            true,
            true,
            false
        );

        $this->assertFalse($summary['dry_run']);
        $this->assertSame(1, $summary['pages']);
        $this->assertSame(1, $summary['articles_exported']);
        $this->assertSame(1, $summary['articles_pruned']);
        $this->assertSame(1, $summary['discussion_items_exported']);
        $this->assertSame(1, $summary['discussion_threads_exported']);
        $this->assertSame(1, $summary['discussion_threads_pruned']);

        $this->assertFileExists($this->pagesFile);
        $this->assertFileExists($this->menusFile);
        $this->assertFileExists($this->blogDir . '/mini-austin-histoire.fr.json');
        $this->assertFileDoesNotExist($this->blogDir . '/obsolete.fr.json');
        $this->assertFileExists($this->discussionsDir . '/mini-austin-histoire.fr.json');
        $this->assertFileDoesNotExist($this->discussionsDir . '/obsolete.fr.json');

        $pageMirror = json_decode((string) file_get_contents($this->pagesFile), true);
        $menuMirror = json_decode((string) file_get_contents($this->menusFile), true);
        $articleMirror = (new JsonBlogRepository($this->blogDir))->find('mini-austin-histoire', 'fr');
        $discussionMirror = (new JsonBlogDiscussionRepository($this->discussionsDir))->all();

        $this->assertIsArray($pageMirror);
        $this->assertSame('mini-austin', $pageMirror['pages'][0]['slug'] ?? null);
        $this->assertIsArray($menuMirror);
        $this->assertSame('Mini Austin', $menuMirror['locations']['primary'][0]['label']['text'] ?? null);
        $this->assertIsArray($articleMirror);
        $this->assertSame('mini-austin', $articleMirror['page_slug'] ?? null);
        $this->assertCount(1, $discussionMirror);
        $this->assertSame('mini-austin-histoire', $discussionMirror[0]['article_slug'] ?? null);
    }

    public function testDryRunDoesNotWriteJsonFiles(): void
    {
        $database = $this->editorialSqlDatabase();
        $pageRepository = new PageRepository($this->pagesFile, new StructuredPageRenderer(), 'sql', $database);
        $navigationRepository = new NavigationRepository($this->menusFile, 'sql', $database);
        $blogRepository = new SqlBlogRepository($database);

        $this->assertTrue($pageRepository->savePage([
            'slug' => 'dry-run-page',
            'type' => 'structured_page',
            'status' => 'published',
            'route' => '/dry-run-page',
            'translations' => [
                'fr' => [
                    'title' => 'Dry run page',
                ],
            ],
        ]));

        $exporter = new EditorialSqlMirrorExporter(
            $pageRepository,
            $navigationRepository,
            $blogRepository
        );

        $summary = $exporter->export(
            $this->pagesFile,
            $this->menusFile,
            $this->blogDir,
            $this->discussionsDir,
            false,
            true,
            true
        );

        $this->assertTrue($summary['dry_run']);
        $this->assertFileDoesNotExist($this->pagesFile);
        $this->assertFileDoesNotExist($this->menusFile);
        $this->assertSame([], glob($this->blogDir . '/*.json') ?: []);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (glob($directory . '/*') ?: [] as $path) {
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
