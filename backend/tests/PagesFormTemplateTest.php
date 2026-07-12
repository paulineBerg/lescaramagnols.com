<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class PagesFormTemplateTest extends TestCase
{
    public function testPagesFormKeepsTileGroupsCompactInPageEditor(): void
    {
        $formData = [
            'slug' => 'association',
            'status' => 'published',
            'route' => '/association',
            'layout' => 'standard_page',
            'translations' => [
                'fr' => [
                    'title' => 'Association',
                    'regions' => [],
                ],
                'en' => [],
                'de' => [],
            ],
            'shared_media' => [],
            'tile_placements' => [
                [
                    'placement_id' => '1',
                    'group_id' => '42',
                    'sort_order' => '10',
                    'overrides' => [
                        'austin' => [
                            'visibility_mode' => 'hidden',
                            'target_mode' => 'default',
                            'target_page_slug' => '',
                            'target_route' => '',
                            'target_url' => '',
                            'translations' => [
                                'fr' => ['label' => '', 'alt' => '', 'title' => ''],
                                'en' => ['label' => '', 'alt' => '', 'title' => ''],
                                'de' => ['label' => '', 'alt' => '', 'title' => ''],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $sharedMediaLibrary = [];
        $contentMediaPicker = ['items' => [], 'folders' => [''], 'favorites' => [''], 'policy' => []];
        $deleteInfo = ['canDelete' => false, 'references' => []];
        $availableLanguages = ['fr', 'en', 'de'];
        $supportedStatuses = ['draft', 'published'];
        $tileSupportEnabled = true;
        $tileGroupOptions = [
            ['id' => 42, 'name' => 'Auto retro', 'theme' => 'editorial', 'tileCount' => 2],
        ];
        $tileGroupCatalog = [
            [
                'id' => 42,
                'name' => 'Auto retro',
                'theme' => 'editorial',
                'items' => [
                    [
                        'item_uid' => 'austin',
                        'label' => 'Austin',
                        'tile_size' => 'rectangle',
                        'color_token' => 'vertfonce',
                        'image_src' => '/assets/images/structure/menu/auto-retro/uiaustin.jpg',
                        'target_summary' => 'Page : austin',
                    ],
                    [
                        'item_uid' => 'mini',
                        'label' => 'Mini',
                        'tile_size' => 'rectangle',
                        'color_token' => 'orange',
                        'image_src' => '/assets/images/structure/menu/auto-retro/uimini.jpg',
                        'target_summary' => 'Page : mini',
                    ],
                ],
            ],
        ];
        $tilePageOptions = [
            ['slug' => 'austin', 'title' => 'Austin', 'route' => '/austin', 'status' => 'published'],
        ];
        $isNewPage = false;
        $currentPageUrl = '/admin/pages/association';
        $pagesIndexUrl = '/admin/pages';
        $adminTilesUrl = '/admin/tiles';
        $csrfToken = 'token-pages';
        $message = null;
        $error = null;

        ob_start();
        include ROOT_PATH . '/templates/admin/pages_form.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('vous gérez seulement l affectation des groupes et leur ordre d affichage', $html);
        $this->assertStringContainsString('Auto retro', $html);
        $this->assertStringContainsString('Les détails du groupe se gèrent dans Tuiles.', $html);
        $this->assertStringContainsString('Des overrides locaux déjà enregistrés pour cette page sont conservés en arrière-plan, sans édition détaillée ici.', $html);
        $this->assertStringNotContainsString('data-page-tile-ui-visibility', $html);
        $this->assertStringNotContainsString('data-page-tile-ui-target-mode', $html);
        $this->assertStringNotContainsString('data-page-tile-ui-target-page', $html);
        $this->assertStringNotContainsString('Route interne', $html);
        $this->assertStringNotContainsString('URL externe', $html);
        $this->assertStringNotContainsString('Textes traduits', $html);
    }
}
