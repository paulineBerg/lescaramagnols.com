<?php

declare(strict_types=1);

use Caramagnols\Blog\DualWriteBlogDiscussionRepository;
use Caramagnols\Blog\DualWriteBlogRepository;
use Caramagnols\Blog\JsonBlogDiscussionRepository;
use Caramagnols\Blog\JsonBlogRepository;
use Caramagnols\Blog\SqlBlogDiscussionRepository;
use Caramagnols\Blog\SqlBlogRepository;
use Caramagnols\Content\PageRepository;
use Caramagnols\Content\SqlPageStore;
use Caramagnols\Content\StructuredPageRenderer;
use Caramagnols\Navigation\NavigationRepository;
use Caramagnols\Navigation\SqlNavigationStore;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class EditorialStorageModeTest extends TestCase
{
    use EditorialSqlTestTrait;

    private string $pagesFile;
    private string $menusFile;
    private string $menusSnapshotDir;
    private string $blogDir;
    private string $discussionDir;

    protected function setUp(): void
    {
        $this->pagesFile = ROOT_PATH . '/var/editorial-storage-pages-' . uniqid() . '.json';
        $this->menusFile = ROOT_PATH . '/var/editorial-storage-menus-' . uniqid() . '.json';
        $this->menusSnapshotDir = dirname($this->menusFile) . '/snapshots';
        $this->blogDir = ROOT_PATH . '/var/editorial-storage-blog-' . uniqid();
        $this->discussionDir = ROOT_PATH . '/var/editorial-storage-discussions-' . uniqid();

        @mkdir($this->blogDir, 0777, true);
        @mkdir($this->discussionDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ([$this->pagesFile, $this->pagesFile . '.bak', $this->menusFile, $this->menusFile . '.bak'] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        foreach ([$this->blogDir, $this->discussionDir] as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            foreach (glob($directory . '/*') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($directory);
        }

        $snapshotPattern = $this->menusSnapshotDir . '/' . pathinfo($this->menusFile, PATHINFO_FILENAME) . '-*.json';
        foreach (glob($snapshotPattern) ?: [] as $snapshotFile) {
            @unlink($snapshotFile);
        }

        $this->cleanupEditorialSqlDatabase();
    }

    public function testDualWritePersistsToJsonAndSqlForPagesAndNavigation(): void
    {
        $database = $this->editorialSqlDatabase();

        $pageRepository = new PageRepository($this->pagesFile, new StructuredPageRenderer(), 'dual-write', $database);
        $pageSaved = $pageRepository->savePage([
            'slug' => 'association',
            'type' => 'structured_page',
            'status' => 'published',
            'route' => '/association',
            'translations' => [
                'fr' => [
                    'title' => 'Association',
                    'regions' => [
                        'hero' => [
                            'component' => 'heading',
                            'title' => 'Association',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($pageSaved);
        $this->assertFileExists($this->pagesFile);
        $this->assertNotNull((new SqlPageStore($database))->findBySlug('association'));

        $navigationRepository = new NavigationRepository($this->menusFile, 'dual-write', $database);
        $navigationSaved = $navigationRepository->saveLegacyConfig([
            'menu2' => [['titre' => 'Accueil', 'chemin' => '/accueil']],
            'menuDroit' => [['titre' => 'Bloc', 'chemin' => '/bloc', 'texte' => 'Texte']],
        ]);

        $this->assertTrue($navigationSaved);
        $this->assertFileExists($this->menusFile);
        $this->assertSame(
            'Accueil',
            (new SqlNavigationStore($database))->loadCanonical()['locations']['primary'][0]['label']['text']
        );
    }

    public function testSqlModeCreatesSnapshotBeforeReplacingExistingNavigationState(): void
    {
        $database = $this->editorialSqlDatabase();
        $repository = new NavigationRepository($this->menusFile, 'sql', $database);

        $this->assertTrue($repository->saveLegacyConfig([
            'menu2' => [['titre' => 'Accueil', 'chemin' => '/accueil']],
        ]));

        $this->assertTrue($repository->saveLegacyConfig([
            'menu2' => [['titre' => 'Auto-Retro', 'chemin' => '/auto-retro']],
        ]));

        $snapshots = glob($this->menusSnapshotDir . '/' . pathinfo($this->menusFile, PATHINFO_FILENAME) . '-*.json') ?: [];

        $this->assertCount(1, $snapshots);

        $snapshot = json_decode((string) file_get_contents($snapshots[0]), true);
        $this->assertIsArray($snapshot);
        $this->assertSame('Accueil', $snapshot['locations']['primary'][0]['label']['text'] ?? null);
        $this->assertSame('/accueil', $snapshot['locations']['primary'][0]['target']['route'] ?? null);
        $this->assertSame(
            'Auto-Retro',
            (new SqlNavigationStore($database))->loadCanonical()['locations']['primary'][0]['label']['text'] ?? null
        );
    }

    public function testDualWritePersistsBlogAndDiscussionsToJsonAndSql(): void
    {
        $database = $this->editorialSqlDatabase();

        $blogRepository = new DualWriteBlogRepository(
            new JsonBlogRepository($this->blogDir),
            new SqlBlogRepository($database)
        );

        $saved = $blogRepository->save([
            'title' => 'Article dual',
            'slug' => 'article-dual',
            'lang' => 'fr',
            'status' => 'published',
            'content' => '<p>Article dual.</p>',
        ]);

        $this->assertTrue($saved['created']);
        $this->assertFileExists($this->blogDir . '/article-dual.fr.json');
        $this->assertNotNull((new SqlBlogRepository($database))->find('article-dual', 'fr'));

        $discussionRepository = new DualWriteBlogDiscussionRepository(
            new JsonBlogDiscussionRepository($this->discussionDir),
            new SqlBlogDiscussionRepository($database)
        );

        $discussion = $discussionRepository->submitPending('article-dual', 'fr', [
            'author' => 'Pauline',
            'email' => 'pauline@example.com',
            'content' => 'Bonjour',
        ]);

        $this->assertFileExists($this->discussionDir . '/article-dual.fr.json');
        $this->assertSame(1, count((new SqlBlogDiscussionRepository($database))->all()));

        $discussionRepository->moderate((string) ($discussion['id'] ?? ''), 'approved', 'admin@example.com');
        $this->assertSame(1, count((new JsonBlogDiscussionRepository($this->discussionDir))->approvedForArticle('article-dual', 'fr')));
        $this->assertSame(1, count((new SqlBlogDiscussionRepository($database))->approvedForArticle('article-dual', 'fr')));
    }
}
