<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';
require_once ROOT_PATH . '/core/content/pages_loader.php';

final class PagesLoaderTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = ROOT_PATH . '/var/pages-test-' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
        pages_cache_clear();
    }

    public function testLoadValidJson(): void
    {
        $json = [
            'meta' => ['version' => 2],
            'pages' => [
                [
                    'slug' => 'test',
                    'type' => 'structured_page',
                    'status' => 'published',
                    'translations' => [
                        'fr' => [
                            'title' => 'Titre',
                            'blocks' => ['EditRegion1' => '<h1>Titre</h1>'],
                        ],
                    ],
                ],
            ],
        ];
        file_put_contents($this->tmpFile, json_encode($json));

        $pages = load_pages($this->tmpFile);
        $this->assertCount(1, $pages);
        $this->assertSame('test', $pages[0]['slug']);
    }

    public function testInvalidJsonReturnsEmpty(): void
    {
        file_put_contents($this->tmpFile, '{invalid json');
        $pages = load_pages($this->tmpFile);
        $this->assertSame([], $pages);
    }

    public function testGetPageBySlugWithLangFallback(): void
    {
        $json = [
            'pages' => [
                [
                    'slug' => 'multilang',
                    'type' => 'structured_page',
                    'translations' => [
                        'fr' => [
                            'title' => 'FR',
                            'blocks' => ['EditRegion1' => 'fr'],
                        ],
                        'en' => [
                            'title' => 'EN',
                            'blocks' => ['EditRegion1' => 'en'],
                        ],
                    ],
                ],
            ],
        ];
        file_put_contents($this->tmpFile, json_encode($json));

        $pageEn = get_page_by_slug('multilang', 'en', 'fr', $this->tmpFile);
        $this->assertSame('EN', $pageEn['title']);
        $this->assertSame('en', $pageEn['blocks']['EditRegion1']);

        $pageEs = get_page_by_slug('multilang', 'es', 'fr', $this->tmpFile);
        $this->assertSame('FR', $pageEs['title']);
        $this->assertSame('fr', $pageEs['blocks']['EditRegion1']);
    }

    public function testDraftReturnsNull(): void
    {
        $json = [
            'pages' => [
                [
                    'slug' => 'draft',
                    'type' => 'structured_page',
                    'status' => 'draft',
                    'translations' => [
                        'fr' => [
                            'title' => 'Titre',
                        ],
                    ],
                ],
            ],
        ];
        file_put_contents($this->tmpFile, json_encode($json));

        $page = get_page_by_slug('draft', 'fr', 'fr', $this->tmpFile);
        $this->assertNull($page);
    }

    public function testGetPageBySlugBuildsBlocksFromSemanticRegions(): void
    {
        $json = [
            'pages' => [
                [
                    'slug' => 'structured',
                    'type' => 'structured_page',
                    'translations' => [
                        'fr' => [
                            'title' => 'Structured',
                            'regions' => [
                                'hero' => [
                                    'component' => 'heading',
                                    'title' => 'Association',
                                    'subtitle' => 'Club auto',
                                ],
                                'left' => [
                                    'component' => 'facts',
                                    'title' => 'Repères',
                                    'items' => [
                                        ['label' => 'Fondé', 'value' => '2010'],
                                    ],
                                ],
                                'bottom' => [
                                    'component' => 'rich_text',
                                    'html' => '<p>Bienvenue.</p>',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        file_put_contents($this->tmpFile, json_encode($json));

        $page = get_page_by_slug('structured', 'fr', 'fr', $this->tmpFile);

        $this->assertNotNull($page);
        $this->assertStringContainsString('<h1>Association</h1>', $page['blocks']['EditRegion1']);
        $this->assertStringContainsString('content-facts', $page['blocks']['EditRegion5']);
        $this->assertStringContainsString('<p>Bienvenue.</p>', $page['blocks']['EditRegion7']);
    }

}
