<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class TilesFormTemplateTest extends TestCase
{
    public function testTilesFormRendersMoveControlsAndReadonlyPositionField(): void
    {
        $formData = [
            'id' => '42',
            'name' => 'Auto retro',
            'theme' => 'editorial',
            'items' => [
                [
                    'item_uid' => 'austin',
                    'sort_order' => '10',
                    'tile_size' => 'rectangle',
                    'color_token' => 'orange',
                    'image_src' => '/assets/images/structure/menu/auto-retro/uiaustin.jpg',
                    'image_width' => '222',
                    'image_height' => '90',
                    'target_type' => 'page',
                    'target_page_slug' => 'austin',
                    'target_route' => '',
                    'target_url' => '',
                    'open_in_new_tab' => '',
                    'translations' => [
                        'fr' => ['label' => 'Austin', 'alt' => 'Austin Healey', 'title' => 'Austin Healey'],
                        'en' => ['label' => 'Austin', 'alt' => 'Austin Healey', 'title' => 'Austin Healey'],
                        'de' => ['label' => 'Austin', 'alt' => 'Austin Healey', 'title' => 'Austin Healey'],
                    ],
                ],
                [
                    'item_uid' => 'citroen',
                    'sort_order' => '20',
                    'tile_size' => 'medium',
                    'color_token' => 'bleu',
                    'image_src' => '/assets/images/structure/menu/auto-retro/uicitroen.jpg',
                    'image_width' => '222',
                    'image_height' => '90',
                    'target_type' => 'route',
                    'target_page_slug' => '',
                    'target_route' => '/auto-retro/citroen',
                    'target_url' => '',
                    'open_in_new_tab' => '',
                    'translations' => [
                        'fr' => ['label' => 'Citroen', 'alt' => 'Citroen DS', 'title' => 'Citroen DS'],
                        'en' => ['label' => 'Citroen', 'alt' => 'Citroen DS', 'title' => 'Citroen DS'],
                        'de' => ['label' => 'Citroen', 'alt' => 'Citroen DS', 'title' => 'Citroen DS'],
                    ],
                ],
            ],
        ];
        $availableLanguages = ['fr', 'en', 'de'];
        $tileThemes = ['editorial' => 'Editorial'];
        $tileSizes = [
            'small' => 'Petit',
            'medium' => 'Moyen',
            'large' => 'Grand',
            'rectangle' => 'Rectangle',
        ];
        $tileColors = [
            'bleu' => 'Bleu',
            'orange' => 'Orange',
        ];
        $tilePageOptions = [
            ['slug' => 'austin', 'title' => 'Austin Healey', 'route' => '/auto-retro/austin', 'status' => 'published'],
        ];
        $contentMediaPicker = ['items' => []];
        $csrfToken = 'token-tiles';
        $currentTileUrl = '/admin/tiles/42';
        $tilesIndexUrl = '/admin/tiles';
        $adminTilesUrl = '/admin/tiles';
        $isNewTileGroup = false;
        $message = null;
        $error = null;

        ob_start();
        include ROOT_PATH . '/templates/admin/tiles_form.php';
        $html = (string) ob_get_clean();

        $this->assertSame(3, substr_count($html, '>Monter</button>'));
        $this->assertSame(3, substr_count($html, '>Descendre</button>'));
        $this->assertStringContainsString('Monter / Descendre', $html);
        $this->assertMatchesRegularExpression('/name="items\\[0\\]\\[sort_order\\]".*readonly/s', $html);
        $this->assertMatchesRegularExpression('/name="items\\[1\\]\\[sort_order\\]".*readonly/s', $html);
    }
}
