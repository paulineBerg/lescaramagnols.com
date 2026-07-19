<?php

declare(strict_types=1);

use Caramagnols\Content\PageRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';

final class PageRepositoryTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = ROOT_PATH . '/var/page-repository-' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testRegistryNormalizesStructuredPagesWithoutPersistingLegacyTemplateMetadata(): void
    {
        $payload = [
            'meta' => ['version' => 2],
            'pages' => [
                [
                    'slug' => 'association',
                    'type' => 'structured_page',
                    'route' => '/association',
                    'translations' => [
                        'fr' => ['title' => 'Association'],
                    ],
                    'template' => 'pages/ancienne-page.php',
                ],
            ],
        ];

        file_put_contents($this->tmpFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $repository = new PageRepository($this->tmpFile);
        $registry = $repository->registry();

        $this->assertSame(2, $registry['meta']['version'] ?? null);
        $this->assertSame(PageRepository::TYPE_STRUCTURED_PAGE, $registry['pages'][0]['type']);
        $this->assertArrayNotHasKey('template', $registry['pages'][0]);
    }

    public function testFindPublishedStructuredBySlugBuildsRenderablePage(): void
    {
        $payload = [
            'pages' => [
                [
                    'slug' => 'association',
                    'type' => 'structured_page',
                    'route' => '/association',
                    'translations' => [
                        'fr' => [
                            'title' => 'Association',
                            'regions' => [
                                'hero' => [
                                    'component' => 'heading',
                                    'title' => 'Association',
                                ],
                                'footer' => [
                                    'component' => 'rich_text',
                                    'html' => '<div class="aligncenter"><em>Footer</em></div>',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        file_put_contents($this->tmpFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $repository = new PageRepository($this->tmpFile);
        $page = $repository->findPublishedStructuredBySlug('association', 'fr');

        $this->assertNotNull($page);
        $this->assertSame('/association', $page['route']);
        $this->assertStringContainsString('<h1>Association</h1>', $page['blocks']['EditRegion1']);
        $this->assertStringContainsString('Footer', $page['blocks']['EditRegion9']);
    }

    public function testFindPublishedStructuredBySlugRendersContactFormComponent(): void
    {
        $payload = [
            'pages' => [
                [
                    'slug' => 'contact',
                    'type' => 'structured_page',
                    'status' => 'published',
                    'route' => '/contact',
                    'translations' => [
                        'fr' => [
                            'title' => 'Contact',
                            'regions' => [
                                'body' => [
                                    [
                                        'component' => 'rich_text',
                                        'html' => '<h1>Contact</h1>',
                                    ],
                                    [
                                        'component' => 'contact_form',
                                        'recipient' => 'contact@example.com',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        file_put_contents($this->tmpFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        $_SESSION['contact_form_token'] = 'seed-token';

        $repository = new PageRepository($this->tmpFile);
        $page = $repository->findPublishedStructuredBySlug('contact', 'fr');

        $this->assertNotNull($page);
        $this->assertStringContainsString('<form method="post"', $page['blocks']['EditRegion3']);
        $this->assertStringContainsString('contact_form_submit', $page['blocks']['EditRegion3']);
        $this->assertStringContainsString('Contact', $page['blocks']['EditRegion3']);
    }

    public function testFindPublishedStructuredBySlugPreservesTrustedYoutubeIframe(): void
    {
        $payload = [
            'pages' => [
                [
                    'slug' => 'simca-aronde',
                    'type' => 'structured_page',
                    'status' => 'published',
                    'route' => '/simca-aronde',
                    'translations' => [
                        'fr' => [
                            'title' => 'Simca Aronde',
                            'regions' => [
                                'body' => [
                                    'component' => 'rich_text',
                                    'html' => '<p>Video</p><div class="video-container"><iframe src="https://www.youtube.com/embed/jHO4WgBiHGQ?list=PLEaZw9SP95T3YS2JYO0fqT032SHk7SFhd" title="SIMCA ARONDE" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        file_put_contents($this->tmpFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $repository = new PageRepository($this->tmpFile);
        $page = $repository->findPublishedStructuredBySlug('simca-aronde', 'fr');

        $this->assertNotNull($page);
        $this->assertStringContainsString('<iframe ', $page['blocks']['EditRegion3']);
        $this->assertStringContainsString(
            'https://www.youtube-nocookie.com/embed/jHO4WgBiHGQ?list=PLEaZw9SP95T3YS2JYO0fqT032SHk7SFhd',
            $page['blocks']['EditRegion3']
        );
        $this->assertStringNotContainsString('https://www.youtube.com/embed/', $page['blocks']['EditRegion3']);
    }

    public function testFindPublishedStructuredBySlugDropsUntrustedIframeSource(): void
    {
        $payload = [
            'pages' => [
                [
                    'slug' => 'simca-aronde',
                    'type' => 'structured_page',
                    'status' => 'published',
                    'route' => '/simca-aronde',
                    'translations' => [
                        'fr' => [
                            'title' => 'Simca Aronde',
                            'regions' => [
                                'body' => [
                                    'component' => 'rich_text',
                                    'html' => '<p>Video</p><iframe src="https://example.org/embed/123" allowfullscreen></iframe>',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        file_put_contents($this->tmpFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $repository = new PageRepository($this->tmpFile);
        $page = $repository->findPublishedStructuredBySlug('simca-aronde', 'fr');

        $this->assertNotNull($page);
        $this->assertStringContainsString('<p>Video</p>', $page['blocks']['EditRegion3']);
        $this->assertStringNotContainsString('<iframe ', $page['blocks']['EditRegion3']);
    }

    public function testFindPublishedStructuredByRouteUsesRegisteredRoute(): void
    {
        $payload = [
            'pages' => [
                [
                    'slug' => 'association',
                    'type' => 'structured_page',
                    'status' => 'published',
                    'route' => '/notre-association',
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
                ],
            ],
        ];

        file_put_contents($this->tmpFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $repository = new PageRepository($this->tmpFile);
        $page = $repository->findPublishedStructuredByRoute('/notre-association', 'fr');

        $this->assertNotNull($page);
        $this->assertSame('/notre-association', $page['route']);
        $this->assertSame('Association', $page['title']);
    }

    public function testFindPublishedStructuredBySlugFallsBackToDefaultLanguageTranslation(): void
    {
        $payload = [
            'pages' => [
                [
                    'slug' => 'association',
                    'type' => 'structured_page',
                    'status' => 'published',
                    'route' => '/association',
                    'translations' => [
                        'fr' => [
                            'title' => 'Association FR',
                            'regions' => [
                                'hero' => [
                                    'component' => 'heading',
                                    'title' => 'Association FR',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        file_put_contents($this->tmpFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $repository = new PageRepository($this->tmpFile);
        $page = $repository->findPublishedStructuredBySlug('association', 'de', 'fr');

        $this->assertNotNull($page);
        $this->assertSame('Association FR', $page['title']);
        $this->assertStringContainsString('<h1>Association FR</h1>', $page['blocks']['EditRegion1']);
    }

    public function testSavePagePersistsNormalizedPayloadAndSupportsSlugChange(): void
    {
        $payload = [
            'pages' => [
                [
                    'slug' => 'balade-collective',
                    'type' => 'structured_page',
                    'status' => 'draft',
                    'route' => '/balade-collective',
                    'translations' => [
                        'fr' => ['title' => 'Balade collective'],
                    ],
                ],
            ],
        ];

        file_put_contents($this->tmpFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $repository = new PageRepository($this->tmpFile);
        $saved = $repository->savePage(
            [
                'slug' => 'balade-de-printemps',
                'type' => 'structured_page',
                'status' => 'published',
                'route' => '/balade-de-printemps',
                'translations' => [
                    'fr' => ['title' => 'Balade de printemps'],
                ],
            ],
            'balade-collective'
        );

        $this->assertTrue($saved);
        $updated = $repository->findBySlug('balade-de-printemps');

        $this->assertNotNull($updated);
        $this->assertSame('published', $updated['status']);
        $this->assertSame('/balade-de-printemps', $updated['route']);
        $this->assertFileExists($this->tmpFile . '.bak');
    }

    public function testDeletePageRemovesPageAndAllTranslationsFromRegistry(): void
    {
        $payload = [
            'pages' => [
                [
                    'slug' => 'association',
                    'type' => 'structured_page',
                    'status' => 'published',
                    'route' => '/association',
                    'translations' => [
                        'fr' => ['title' => 'Association'],
                        'de' => ['title' => 'Verein'],
                    ],
                ],
            ],
        ];

        file_put_contents($this->tmpFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $repository = new PageRepository($this->tmpFile);

        $this->assertTrue($repository->deletePage('association'));
        $this->assertNull($repository->findBySlug('association'));
        $this->assertSame([], $repository->all());
        $this->assertFileExists($this->tmpFile . '.bak');
    }
}
