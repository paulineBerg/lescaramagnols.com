<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';
require_once ROOT_PATH . '/core/content/pages_loader.php';
require_once ROOT_PATH . '/core/router.php';

final class DynamicRouteTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = ROOT_PATH . '/var/pages-route-' . uniqid() . '.json';
        pages_cache_clear();
        pages_data_set_path_override($this->tmpFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
        pages_cache_clear();
        pages_data_set_path_override(null);
    }

    public function testKnownSlugResolvesToDynamicTemplate(): void
    {
        $json = [
            'pages' => [
                [
                    'slug' => 'test-route',
                    'type' => 'structured_page',
                    'translations' => [
                        'fr' => ['title' => 'Test'],
                    ],
                ],
            ],
        ];
        file_put_contents($this->tmpFile, json_encode($json));

        // Force le loader à utiliser notre fichier de test
        load_pages($this->tmpFile);

        $route = resolve_route('/test-route');
        $this->assertSame('pages/dynamic.php', $route);
        $this->assertIsArray($GLOBALS['currentDynamicPage'] ?? null);
        $this->assertSame('Test', $GLOBALS['currentDynamicPage']['title']);
    }

    public function testUnknownSlugFallsbackTo404(): void
    {
        $json = ['pages' => []];
        file_put_contents($this->tmpFile, json_encode($json));
        load_pages($this->tmpFile);

        $route = resolve_route('/nope');
        $this->assertSame('pages/404.php', $route);
        $this->assertArrayNotHasKey('currentDynamicPage', $GLOBALS);
    }

    public function testStructuredHomePageCanResolveFromRootRoute(): void
    {
        $json = [
            'pages' => [
                [
                    'slug' => 'home',
                    'type' => 'structured_page',
                    'status' => 'published',
                    'route' => '/',
                    'translations' => [
                        'fr' => ['title' => 'Accueil'],
                    ],
                ],
            ],
        ];

        file_put_contents($this->tmpFile, json_encode($json));
        load_pages($this->tmpFile);

        $route = resolve_route('/');

        $this->assertSame('pages/dynamic.php', $route);
        $this->assertSame('Accueil', $GLOBALS['currentDynamicPage']['title'] ?? null);
    }

    public function testStructuredPageCanResolveFromItsRegisteredRoute(): void
    {
        $json = [
            'pages' => [
                [
                    'slug' => 'association',
                    'type' => 'structured_page',
                    'status' => 'published',
                    'route' => '/notre-association',
                    'translations' => [
                        'fr' => ['title' => 'Association'],
                    ],
                ],
            ],
        ];

        file_put_contents($this->tmpFile, json_encode($json));
        load_pages($this->tmpFile);

        $route = resolve_route('/notre-association');

        $this->assertSame('pages/dynamic.php', $route);
        $this->assertSame('Association', $GLOBALS['currentDynamicPage']['title'] ?? null);
    }

}
