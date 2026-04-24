<?php

declare(strict_types=1);

use Caramagnols\Content\PageRepository;
use Caramagnols\Content\PageTileRenderer;
use Caramagnols\Content\TileRepository;
use Caramagnols\Database\DatabaseConfig;
use Caramagnols\Database\EditorialDatabase;
use Caramagnols\Database\EditorialSchemaManager;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class PageTileRendererTest extends TestCase
{
    private string $pagesFile;

    protected function setUp(): void
    {
        $this->pagesFile = ROOT_PATH . '/var/page-tile-renderer-' . uniqid('', true) . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->pagesFile)) {
            @unlink($this->pagesFile);
        }
    }

    public function testRenderAfterBodyBuildsWindows10TileMarkupFromServerData(): void
    {
        file_put_contents(
            $this->pagesFile,
            (string) json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'page-source',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'route' => '/page-source',
                            'translations' => [
                                'fr' => ['title' => 'Page source'],
                            ],
                        ],
                        [
                            'slug' => 'histoire-austin',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'route' => '/histoire-austin',
                            'translations' => [
                                'fr' => ['title' => 'Histoire Austin'],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $pageRepository = new PageRepository($this->pagesFile);
        $tileRepository = $this->tileRepositoryWithCaches(
            [
                'page-source|' . TileRepository::DEFAULT_REGION => [
                    [
                        'id' => 100,
                        'page_slug' => 'page-source',
                        'region_key' => TileRepository::DEFAULT_REGION,
                        'group_id' => 1,
                        'sort_order' => 10,
                        'overrides' => [
                            'panhard' => [
                                'labels' => ['fr' => 'Panhard Dyna'],
                                'titles' => ['fr' => 'Histoire complète'],
                            ],
                            'hidden-medium' => [
                                'is_visible' => false,
                            ],
                        ],
                    ],
                ],
            ],
            [
                1 => [
                    'id' => 1,
                    'name' => 'A lire ensuite',
                    'theme' => TileRepository::DEFAULT_THEME,
                    'items' => [
                        [
                            'id' => 1,
                            'item_uid' => 'austin',
                            'sort_order' => 10,
                            'tile_size' => 'large',
                            'color_token' => 'vertfonce',
                            'image_src' => '/assets/images/structure/menu/auto-retro/uiaustin.jpg',
                            'image_width' => 222,
                            'image_height' => 90,
                            'target' => [
                                'type' => 'page',
                                'pageSlug' => 'histoire-austin',
                                'route' => '',
                                'url' => '',
                            ],
                            'open_in_new_tab' => false,
                            'translations' => [
                                'fr' => [
                                    'label' => 'Austin',
                                    'alt' => 'Austin sur route',
                                    'title' => 'Histoire Austin',
                                ],
                            ],
                        ],
                        [
                            'id' => 2,
                            'item_uid' => 'panhard',
                            'sort_order' => 20,
                            'tile_size' => 'medium',
                            'color_token' => 'orange',
                            'image_src' => '/assets/images/structure/menu/auto-retro/uipanhard.jpg',
                            'image_width' => 222,
                            'image_height' => 90,
                            'target' => [
                                'type' => 'route',
                                'pageSlug' => '',
                                'route' => '/auto-retro/panhard',
                                'url' => '',
                            ],
                            'open_in_new_tab' => false,
                            'translations' => [
                                'fr' => [
                                    'label' => 'Panhard',
                                    'alt' => 'Panhard Dyna',
                                    'title' => 'Dyna',
                                ],
                            ],
                        ],
                        [
                            'id' => 3,
                            'item_uid' => 'golfe',
                            'sort_order' => 30,
                            'tile_size' => 'rectangle',
                            'color_token' => 'bleufonce',
                            'image_src' => '/assets/images/structure/menu/bouger/uisttropez.jpg',
                            'image_width' => 222,
                            'image_height' => 90,
                            'target' => [
                                'type' => 'external',
                                'pageSlug' => '',
                                'route' => '',
                                'url' => 'https://example.com/golfe',
                            ],
                            'open_in_new_tab' => true,
                            'translations' => [
                                'fr' => [
                                    'label' => 'Golfe St-Tropez',
                                    'alt' => 'Golfe de Saint-Tropez',
                                    'title' => 'Promenade',
                                ],
                            ],
                        ],
                        [
                            'id' => 4,
                            'item_uid' => 'blog',
                            'sort_order' => 40,
                            'tile_size' => 'small',
                            'color_token' => 'jaune',
                            'image_src' => '',
                            'image_width' => null,
                            'image_height' => null,
                            'target' => [
                                'type' => 'route',
                                'pageSlug' => '',
                                'route' => '/blog',
                                'url' => '',
                            ],
                            'open_in_new_tab' => false,
                            'translations' => [
                                'fr' => [
                                    'label' => 'Blog',
                                    'alt' => 'Blog',
                                    'title' => 'Nos articles',
                                ],
                            ],
                        ],
                        [
                            'id' => 5,
                            'item_uid' => 'hidden-medium',
                            'sort_order' => 50,
                            'tile_size' => 'medium',
                            'color_token' => 'gris',
                            'image_src' => '/assets/images/structure/menu/sava/uisava.jpg',
                            'image_width' => 222,
                            'image_height' => 90,
                            'target' => [
                                'type' => 'route',
                                'pageSlug' => '',
                                'route' => '/cache',
                                'url' => '',
                            ],
                            'open_in_new_tab' => false,
                            'translations' => [
                                'fr' => [
                                    'label' => 'Cache',
                                    'alt' => 'Cache',
                                    'title' => 'Cache',
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );

        $renderer = new PageTileRenderer($tileRepository, $pageRepository, 'fr');
        $html = $renderer->renderAfterBody('page-source', 'fr');

        $this->assertStringContainsString('page-tile-groups', $html);
        $this->assertStringContainsString('aria-label="A lire ensuite"', $html);
        $this->assertStringNotContainsString('page-tile-group__title', $html);
        $this->assertStringContainsString('page-tile--size-large page-tile--color-vertfonce page-tile--with-media', $html);
        $this->assertStringContainsString('page-tile--size-medium page-tile--color-orange page-tile--with-media', $html);
        $this->assertStringContainsString('page-tile--size-rectangle page-tile--color-bleufonce page-tile--with-media', $html);
        $this->assertStringContainsString('page-tile--size-small page-tile--color-jaune', $html);
        $this->assertStringContainsString('href="/histoire-austin"', $html);
        $this->assertStringContainsString('href="/auto-retro/panhard"', $html);
        $this->assertStringContainsString('href="https://example.com/golfe"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('Panhard Dyna', $html);
        $this->assertStringContainsString('Histoire complète', $html);
        $this->assertStringContainsString('Nos articles', $html);
        $this->assertStringContainsString('/assets/images/structure/menu/boutongrand/btgrd_vertfonce.png', $html);
        $this->assertStringContainsString('/assets/images/structure/menu/boutonmoyen/btmoy_orange_selection.png', $html);
        $this->assertStringContainsString('/assets/images/structure/menu/boutonrectangle/btrect_bleufonce_clic.png', $html);
        $this->assertStringContainsString('/assets/images/structure/menu/boutonpetit/btptt_jaune.png', $html);
        $this->assertStringNotContainsString('Cache', $html);
    }

    public function testRenderAfterBodyGroupsSmallTilesInTwoByTwoCluster(): void
    {
        file_put_contents(
            $this->pagesFile,
            (string) json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'page-source',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'route' => '/page-source',
                            'translations' => [
                                'fr' => ['title' => 'Page source'],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $pageRepository = new PageRepository($this->pagesFile);
        $tileRepository = $this->tileRepositoryWithCaches(
            [
                'page-source|' . TileRepository::DEFAULT_REGION => [
                    [
                        'id' => 100,
                        'page_slug' => 'page-source',
                        'region_key' => TileRepository::DEFAULT_REGION,
                        'group_id' => 1,
                        'sort_order' => 10,
                        'overrides' => [],
                    ],
                ],
            ],
            [
                1 => [
                    'id' => 1,
                    'name' => 'Maillage transversal',
                    'theme' => TileRepository::DEFAULT_THEME,
                    'items' => [
                        $this->smallTileItem(1, 'dyna-z12', 'Dyna Z12', '/dyna-z12'),
                        $this->smallTileItem(2, 'notre-dyna', 'Notre Dyna', '/notre-dyna'),
                        $this->smallTileItem(3, 'notre-mini', 'Notre Mini', '/notre-mini'),
                        $this->smallTileItem(4, 'notre-slk', 'Notre SLK', '/notre-slk'),
                    ],
                ],
            ]
        );

        $renderer = new PageTileRenderer($tileRepository, $pageRepository, 'fr');
        $html = $renderer->renderAfterBody('page-source', 'fr');

        $this->assertStringContainsString('page-tile-small-cluster', $html);
        $this->assertStringContainsString('grid-column:span 2', $html);
        $this->assertStringContainsString('grid-row:span 2', $html);
        $this->assertStringContainsString('grid-template-columns:repeat(2,minmax(0,1fr))', $html);
        $this->assertStringContainsString('grid-template-rows:repeat(2,minmax(0,1fr))', $html);
        $this->assertSame(4, substr_count($html, 'page-tile--size-small'));
        $this->assertMatchesRegularExpression(
            '/page-tile-small-cluster.*Dyna Z12.*Notre Dyna.*Notre Mini.*Notre SLK.*<\\/div><\\/div>/s',
            $html
        );
    }

    public function testRenderAfterBodyHidesTilesThatPointToCurrentPage(): void
    {
        file_put_contents(
            $this->pagesFile,
            (string) json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'page-source',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'route' => '/page-source',
                            'translations' => [
                                'fr' => ['title' => 'Page source'],
                            ],
                        ],
                        [
                            'slug' => 'page-cible',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'route' => '/page-cible',
                            'translations' => [
                                'fr' => ['title' => 'Page cible'],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $pageRepository = new PageRepository($this->pagesFile);
        $tileRepository = $this->tileRepositoryWithCaches(
            [
                'page-source|' . TileRepository::DEFAULT_REGION => [
                    [
                        'id' => 100,
                        'page_slug' => 'page-source',
                        'region_key' => TileRepository::DEFAULT_REGION,
                        'group_id' => 1,
                        'sort_order' => 10,
                        'overrides' => [],
                    ],
                ],
            ],
            [
                1 => [
                    'id' => 1,
                    'name' => 'Auto-reference',
                    'theme' => TileRepository::DEFAULT_THEME,
                    'items' => [
                        $this->pageTileItem(1, 'self-page', 'Page en cours', 'page-source'),
                        $this->routeTileItem(2, 'self-route', 'Route courante', '/page-source'),
                        $this->routeTileItem(3, 'sibling-route', 'Page cible', '/page-cible'),
                    ],
                ],
            ]
        );

        $renderer = new PageTileRenderer($tileRepository, $pageRepository, 'fr');
        $html = $renderer->renderAfterBody('page-source', 'fr');

        $this->assertStringNotContainsString('Page en cours', $html);
        $this->assertStringNotContainsString('Route courante', $html);
        $this->assertStringContainsString('Page cible', $html);
        $this->assertStringContainsString('href="/page-cible"', $html);
        $this->assertSame(1, substr_count($html, 'page-tile page-tile--size-medium'));
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $placementsCache
     * @param array<int, array<string, mixed>> $groupCache
     */
    private function tileRepositoryWithCaches(array $placementsCache, array $groupCache): TileRepository
    {
        $database = new EditorialDatabase(
            new DatabaseConfig('127.0.0.1', 3306, '', '', ''),
            'test_tiles_',
            new EditorialSchemaManager('test_tiles_')
        );
        $repository = new TileRepository($database);

        $placementsProperty = new ReflectionProperty(TileRepository::class, 'pagePlacementsCache');
        $placementsProperty->setAccessible(true);
        $placementsProperty->setValue($repository, $placementsCache);

        $groupsProperty = new ReflectionProperty(TileRepository::class, 'groupCache');
        $groupsProperty->setAccessible(true);
        $groupsProperty->setValue($repository, $groupCache);

        return $repository;
    }

    /**
     * @return array<string, mixed>
     */
    private function smallTileItem(int $id, string $uid, string $label, string $route): array
    {
        return [
            'id' => $id,
            'item_uid' => $uid,
            'sort_order' => $id * 10,
            'tile_size' => 'small',
            'color_token' => 'orange',
            'image_src' => '',
            'image_width' => null,
            'image_height' => null,
            'target' => [
                'type' => 'route',
                'pageSlug' => '',
                'route' => $route,
                'url' => '',
            ],
            'open_in_new_tab' => false,
            'translations' => [
                'fr' => [
                    'label' => $label,
                    'alt' => $label,
                    'title' => $label,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function routeTileItem(int $id, string $uid, string $label, string $route): array
    {
        return [
            'id' => $id,
            'item_uid' => $uid,
            'sort_order' => $id * 10,
            'tile_size' => 'medium',
            'color_token' => 'bleuvert',
            'image_src' => '',
            'image_width' => null,
            'image_height' => null,
            'target' => [
                'type' => 'route',
                'pageSlug' => '',
                'route' => $route,
                'url' => '',
            ],
            'open_in_new_tab' => false,
            'translations' => [
                'fr' => [
                    'label' => $label,
                    'alt' => $label,
                    'title' => $label,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pageTileItem(int $id, string $uid, string $label, string $pageSlug): array
    {
        return [
            'id' => $id,
            'item_uid' => $uid,
            'sort_order' => $id * 10,
            'tile_size' => 'medium',
            'color_token' => 'bleuvert',
            'image_src' => '',
            'image_width' => null,
            'image_height' => null,
            'target' => [
                'type' => 'page',
                'pageSlug' => $pageSlug,
                'route' => '',
                'url' => '',
            ],
            'open_in_new_tab' => false,
            'translations' => [
                'fr' => [
                    'label' => $label,
                    'alt' => $label,
                    'title' => $label,
                ],
            ],
        ];
    }
}
