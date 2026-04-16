<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class MenusFixesPartialTest extends TestCase
{
    public function testPartialDoesNotResetCallerMenuVariables(): void
    {
        $menuConfig = [
            'menuDroit' => [
                [
                    'chemin' => '/auto-retro/austin/histoire-de-austin.php',
                    'image' => '/assets/images/structure/menu/auto-retro/btaustin.jpg',
                    'alt' => 'Austin',
                    'title' => 'Austin',
                ],
            ],
            'menuGauche' => [
                [
                    'chemin' => '/auto-retro/austin/une-mini-dans-le-golfe-de-sttropez.php',
                    'image' => '/assets/images/structure/menu/auto-retro/btaustin.jpg',
                    'alt' => 'La Mini Mayfair',
                    'title' => 'La Mini Mayfair',
                ],
            ],
        ];

        $menuDroit = $menuConfig['menuDroit'];
        $menuGauche = $menuConfig['menuGauche'];

        require ROOT_PATH . '/templates/partials/menus_fixes.php';

        $this->assertCount(1, $menuDroit);
        $this->assertCount(1, $menuGauche);
        $this->assertSame('Austin', $menuDroit[0]['title']);
        $this->assertSame('La Mini Mayfair', $menuGauche[0]['title']);
    }

    public function testRenderMenuFixeOutputsFixedMenuImageMarkup(): void
    {
        if (!function_exists('renderMenuFixe')) {
            require ROOT_PATH . '/templates/partials/menus_fixes.php';
        }

        ob_start();
        renderMenuFixe(
            [
                [
                    'chemin' => '/auto-retro/austin/histoire-de-austin.php',
                    'image' => '/assets/images/structure/menu/auto-retro/btaustin.jpg',
                    'alt' => 'Austin',
                    'title' => 'Austin',
                    'titre' => 'Austin',
                    'texte' => 'Berline noire de collection',
                ],
            ],
            'menu-droit',
            'Nos voitures'
        );
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('Nos voitures', $html);
        $this->assertStringContainsString('/assets/images/structure/menu/auto-retro/btaustin.jpg', $html);
        $this->assertStringContainsString('/auto-retro/austin/histoire-de-austin.php', $html);
        $this->assertStringContainsString('Austin', $html);
        $this->assertStringContainsString('Berline noire de collection', $html);
    }

    public function testRenderMenuFixeFallsBackToTitleWhenTitreIsMissing(): void
    {
        if (!function_exists('renderMenuFixe')) {
            require ROOT_PATH . '/templates/partials/menus_fixes.php';
        }

        ob_start();
        renderMenuFixe(
            [
                [
                    'chemin' => '/auto-retro/panhard/histoire-de-panhard.php',
                    'image' => '/assets/images/structure/menu/auto-retro/btpanhard.jpg',
                    'alt' => 'Panhard',
                    'title' => 'Panhard',
                    'titre' => '',
                ],
            ],
            'menu-droit',
            'Par marque'
        );
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('Panhard', $html);
        $this->assertStringContainsString('menu-fixe-item-content', $html);
    }
}
