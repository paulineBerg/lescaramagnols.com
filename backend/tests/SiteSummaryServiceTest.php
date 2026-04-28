<?php

declare(strict_types=1);

use Caramagnols\Blog\JsonBlogRepository;
use Caramagnols\Content\PageRepository;
use Caramagnols\Feed\SitemapEntryCollector;
use Caramagnols\Feed\SiteSummaryService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class SiteSummaryServiceTest extends TestCase
{
    private string $pagesFile;
    private string $blogDataDir;

    protected function setUp(): void
    {
        $this->pagesFile = ROOT_PATH . '/var/site-summary-pages-' . uniqid('', true) . '.json';
        $this->blogDataDir = sys_get_temp_dir() . '/caramagnols-site-summary-blog-' . bin2hex(random_bytes(6));
        mkdir($this->blogDataDir, 0777, true);

        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'home',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/',
                            'translations' => [
                                'fr' => ['title' => 'Accueil'],
                                'en' => ['title' => 'Home'],
                            ],
                        ],
                        [
                            'slug' => 'association',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'layout' => 'standard_page',
                            'route' => '/accueil/association.php',
                            'translations' => [
                                'fr' => ['title' => 'Association'],
                                'en' => ['title' => 'Association EN'],
                            ],
                        ],
                        [
                            'slug' => 'draft',
                            'type' => 'structured_page',
                            'status' => 'draft',
                            'layout' => 'standard_page',
                            'route' => '/accueil/brouillon.php',
                            'translations' => [
                                'fr' => ['title' => 'Brouillon'],
                            ],
                        ],
                    ],
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
            )
        );

        file_put_contents(
            $this->blogDataDir . '/bonjour.fr.json',
            json_encode(
                [
                    'status' => 'published',
                    'title' => 'Bonjour',
                    'slug' => 'bonjour',
                    'lang' => 'fr',
                    'page_slug' => 'association',
                    'date' => '2026-03-20 08:30:00',
                    'content' => '<p>Publié FR.</p>',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
        file_put_contents(
            $this->blogDataDir . '/hello.en.json',
            json_encode(
                [
                    'status' => 'published',
                    'title' => 'Hello',
                    'slug' => 'hello',
                    'lang' => 'en',
                    'date' => '2026-03-21 09:00:00',
                    'content' => '<p>Published EN.</p>',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
        file_put_contents(
            $this->blogDataDir . '/brouillon.fr.json',
            json_encode(
                [
                    'status' => 'draft',
                    'title' => 'Brouillon',
                    'slug' => 'brouillon',
                    'lang' => 'fr',
                    'date' => '2026-03-21 10:00:00',
                    'content' => '<p>Ne doit pas apparaître.</p>',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
    }

    protected function tearDown(): void
    {
        if (file_exists($this->pagesFile)) {
            @unlink($this->pagesFile);
        }

        $files = glob($this->blogDataDir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }

        @rmdir($this->blogDataDir);
    }

    public function testRenderBuildsHtmlFromSitemapCollectorEntries(): void
    {
        $collector = new SitemapEntryCollector(
            new PageRepository($this->pagesFile),
            new JsonBlogRepository($this->blogDataDir),
            '/',
            ['fr', 'en'],
            'fr'
        );
        $service = new SiteSummaryService($collector, ['fr', 'en'], 'fr');

        $html = $service->render('fr');

        $this->assertStringContainsString('<h2>Sommaire</h2>', $html);
        $this->assertStringContainsString('<a href="/">Accueil</a>', $html);
        $this->assertStringContainsString('<a href="/accueil/association.php">Association</a>', $html);
        $this->assertStringContainsString('<a href="/blog">Blog</a>', $html);
        $this->assertStringContainsString(
            '<a href="/fr/accueil/association.php?open_article=bonjour#attached-article-bonjour">Bonjour</a>',
            $html
        );
        $this->assertStringNotContainsString('Brouillon', $html);
    }

    public function testRenderUsesRequestedLanguageTitlesAndBlogRoutes(): void
    {
        $collector = new SitemapEntryCollector(
            new PageRepository($this->pagesFile),
            new JsonBlogRepository($this->blogDataDir),
            '/',
            ['fr', 'en'],
            'fr'
        );
        $service = new SiteSummaryService($collector, ['fr', 'en'], 'fr');

        $html = $service->render('en');

        $this->assertStringContainsString('<h2>Sitemap</h2>', $html);
        $this->assertStringContainsString('<a href="/">Home</a>', $html);
        $this->assertStringContainsString('<a href="/accueil/association.php">Association EN</a>', $html);
        $this->assertStringContainsString('<a href="/en/blog">Blog</a>', $html);
        $this->assertStringContainsString('<a href="/en/blog/article/hello">Hello</a>', $html);
        $this->assertStringNotContainsString('/blog/article/bonjour', $html);
    }
}
