<?php

declare(strict_types=1);

use Caramagnols\Blog\JsonBlogRepository;
use Caramagnols\Content\PageRepository;
use Caramagnols\Feed\SitemapService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class SitemapServiceTest extends TestCase
{
    private string $pagesFile;
    private string $blogDataDir;

    protected function setUp(): void
    {
        $this->pagesFile = ROOT_PATH . '/var/sitemap-pages-' . uniqid('', true) . '.json';
        $this->blogDataDir = sys_get_temp_dir() . '/caramagnols-sitemap-blog-' . bin2hex(random_bytes(6));
        mkdir($this->blogDataDir, 0777, true);

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
                            'layout' => 'standard_page',
                            'route' => '/association',
                            'updated_at' => '2026-03-19T12:00:00+00:00',
                            'translations' => [
                                'fr' => [
                                    'title' => 'Association',
                                ],
                            ],
                        ],
                        [
                            'slug' => 'brouillon',
                            'type' => 'structured_page',
                            'status' => 'draft',
                            'layout' => 'standard_page',
                            'route' => '/brouillon',
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
                    'date' => '2026-03-20 08:30:00',
                    'content' => '<p>Publié FR.</p>',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
        file_put_contents(
            $this->blogDataDir . '/hallo.en.json',
            json_encode(
                [
                    'status' => 'published',
                    'title' => 'Hallo',
                    'slug' => 'hallo',
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

    public function testRenderBuildsXmlFromPublishedPagesAndArticles(): void
    {
        $service = new SitemapService(
            new PageRepository($this->pagesFile),
            new JsonBlogRepository($this->blogDataDir),
            'https://example.test',
            ['fr', 'en'],
            'fr'
        );

        $xml = $service->render();

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<loc>https://example.test/</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.test/association</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.test/blog/article/bonjour</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.test/en/blog/article/hallo</loc>', $xml);
        $this->assertStringNotContainsString('/brouillon</loc>', $xml);
    }
}
