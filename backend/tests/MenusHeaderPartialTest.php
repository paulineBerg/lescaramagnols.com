<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class MenusHeaderPartialTest extends TestCase
{
    public function testActiveBranchStaysClosedByDefaultOnInitialRender(): void
    {
        if (!function_exists('renderSiteHeader')) {
            require ROOT_PATH . '/templates/partials/menus_header.php';
        }

        ob_start();
        renderSiteHeader([
            'brand' => [
                'label' => 'Les Caramagnols',
                'href' => '/',
                'logo' => '/assets/images/structure/favicon-48x48.png',
            ],
            'utility' => [],
            'banner' => [
                'headline' => 'Voyage',
                'title' => 'Voyage',
                'image' => null,
            ],
            'primary' => [
                [
                    'id' => 'primary-auto',
                    'label' => 'Auto-Retro',
                    'href' => null,
                    'title' => 'Auto-Retro',
                    'active' => true,
                    'openInNewTab' => false,
                    'children' => [
                        [
                            'id' => 'primary-mercedes',
                            'label' => 'Mercedes',
                            'href' => '/auto-retro/mercedes/histoire-de-mercedes',
                            'title' => 'Mercedes',
                            'active' => true,
                            'openInNewTab' => false,
                            'children' => [],
                            'presentation' => [],
                            'panelKind' => null,
                            'mega' => null,
                        ],
                    ],
                    'presentation' => ['displayMode' => 'dropdown'],
                    'panelKind' => 'dropdown',
                    'mega' => null,
                ],
            ],
            'languages' => [],
            'search' => [
                'action' => '/search',
                'currentLanguage' => 'fr',
                'label' => 'Recherche',
                'placeholder' => 'Rechercher...',
            ],
        ]);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('site-nav-item site-nav-item-has-children site-nav-item-active', $html);
        $this->assertStringContainsString('site-nav-item-toggleless', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('id="site-nav-panel-desktop-primary-auto"', $html);
        $this->assertStringContainsString('id="site-nav-panel-desktop-primary-auto"', $html);
        $this->assertMatchesRegularExpression('/id="site-nav-panel-desktop-primary-auto"[^>]*hidden/', $html);
        $this->assertMatchesRegularExpression('/id="site-nav-panel-mobile-primary-auto"[^>]*hidden/', $html);
        $this->assertStringNotContainsString('site-nav-item-has-children is-open', $html);
        $this->assertStringNotContainsString('class="site-nav-toggle"', $html);
    }

    public function testMegaSectionPromotesHrefAndAvoidsDuplicateFirstLinkWhenLabelMatchesGroup(): void
    {
        if (!function_exists('normalizeMegaSectionsForRender')) {
            require ROOT_PATH . '/templates/partials/menus_header.php';
        }

        $sections = normalizeMegaSectionsForRender([
            [
                'label' => 'Austin',
                'href' => null,
                'itemColumns' => [[
                    [
                        'label' => 'Austin',
                        'href' => '/auto-retro/austin/histoire-de-austin.php',
                    ],
                    [
                        'label' => 'Histoire de la Mini',
                        'href' => '/auto-retro/austin/aventure-mini-austin.php',
                    ],
                ]],
            ],
        ]);

        $this->assertCount(1, $sections);
        $this->assertSame('Austin', $sections[0]['label'] ?? null);
        $this->assertSame('/auto-retro/austin/histoire-de-austin.php', $sections[0]['href'] ?? null);
        $this->assertCount(1, $sections[0]['itemColumns'] ?? []);
        $this->assertCount(1, $sections[0]['itemColumns'][0] ?? []);
        $this->assertSame('Histoire de la Mini', $sections[0]['itemColumns'][0][0]['label'] ?? null);
    }

    public function testMegaSectionKeepsUnlabeledSectionsSeparatedForColumnDistribution(): void
    {
        if (!function_exists('normalizeMegaSectionsForRender')) {
            require ROOT_PATH . '/templates/partials/menus_header.php';
        }

        $sections = normalizeMegaSectionsForRender([
            [
                'label' => null,
                'href' => null,
                'itemColumns' => [[
                    ['label' => 'Histoire de Austin', 'href' => '/auto-retro/austin/histoire-de-austin'],
                ]],
            ],
            [
                'label' => null,
                'href' => null,
                'itemColumns' => [[
                    ['label' => 'La Mini', 'href' => '/auto-retro/austin/aventure-mini-austin'],
                ]],
            ],
            [
                'label' => null,
                'href' => null,
                'itemColumns' => [[
                    ['label' => 'Histoire de Renault', 'href' => '/auto-retro/renault/histoire-de-renault'],
                ]],
            ],
        ]);

        $this->assertCount(3, $sections);
        $this->assertSame('Histoire de Austin', $sections[0]['itemColumns'][0][0]['label'] ?? null);
        $this->assertSame('La Mini', $sections[1]['itemColumns'][0][0]['label'] ?? null);
        $this->assertSame('Histoire de Renault', $sections[2]['itemColumns'][0][0]['label'] ?? null);
    }

    public function testRenderSiteHeaderRendersSingleTitleForMultiColumnSection(): void
    {
        if (!function_exists('renderSiteHeader')) {
            require ROOT_PATH . '/templates/partials/menus_header.php';
        }

        ob_start();
        renderSiteHeader([
            'brand' => [
                'label' => 'Les Caramagnols',
                'href' => '/',
                'logo' => '/assets/images/structure/favicon-48x48.png',
            ],
            'utility' => [],
            'banner' => [
                'headline' => 'Voyage',
                'title' => 'Voyage',
                'image' => null,
            ],
            'primary' => [
                [
                    'id' => 'primary-auto',
                    'label' => 'Auto-Retro',
                    'href' => null,
                    'title' => 'Auto-Retro',
                    'active' => false,
                    'openInNewTab' => false,
                    'children' => [
                        [
                            'id' => 'primary-austin',
                            'label' => 'Austin',
                            'href' => '/auto-retro/austin',
                            'title' => 'Austin',
                            'active' => false,
                            'openInNewTab' => false,
                            'children' => [],
                            'presentation' => [],
                            'panelKind' => null,
                            'mega' => null,
                        ],
                    ],
                    'presentation' => ['displayMode' => 'mega'],
                    'panelKind' => 'mega',
                    'mega' => [
                        'columnCount' => 2,
                        'featuredCard' => null,
                        'sections' => [
                            [
                                'label' => 'Austin',
                                'href' => '/auto-retro/austin',
                                'itemColumns' => [
                                    [
                                        ['label' => 'Lien A1', 'href' => '/auto-retro/austin/a1'],
                                    ],
                                    [
                                        ['label' => 'Lien A6', 'href' => '/auto-retro/austin/a6'],
                                    ],
                                ],
                                'columnSpan' => 2,
                            ],
                        ],
                    ],
                ],
            ],
            'languages' => [],
            'search' => [
                'action' => '/search',
                'currentLanguage' => 'fr',
                'label' => 'Recherche',
                'placeholder' => 'Rechercher...',
            ],
        ]);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('site-nav-mega-section-multi-column', $html);
        $this->assertStringContainsString('--site-nav-mega-section-columns: 2;', $html);
        $this->assertStringContainsString('Lien A6', $html);
        $this->assertSame(1, substr_count($html, 'site-nav-mega-section-title'));
    }

    public function testRenderSiteHeaderUsesAllMegaGridUnitsBeforeWrapping(): void
    {
        if (!function_exists('renderSiteHeader')) {
            require ROOT_PATH . '/templates/partials/menus_header.php';
        }

        ob_start();
        renderSiteHeader([
            'brand' => [
                'label' => 'Les Caramagnols',
                'href' => '/',
                'logo' => '/assets/images/structure/favicon-48x48.png',
            ],
            'utility' => [],
            'banner' => [
                'headline' => 'Voyage',
                'title' => 'Voyage',
                'image' => null,
            ],
            'primary' => [
                [
                    'id' => 'primary-auto',
                    'label' => 'Auto-Retro',
                    'href' => null,
                    'title' => 'Auto-Retro',
                    'active' => false,
                    'openInNewTab' => false,
                    'children' => [
                        [
                            'id' => 'primary-austin-link',
                            'label' => 'Austin',
                            'href' => '/auto-retro/austin',
                            'title' => 'Austin',
                            'active' => false,
                            'openInNewTab' => false,
                            'children' => [],
                            'presentation' => [],
                            'panelKind' => null,
                            'mega' => null,
                        ],
                    ],
                    'presentation' => ['displayMode' => 'mega'],
                    'panelKind' => 'mega',
                    'mega' => [
                        'columnCount' => 4,
                        'featuredCard' => null,
                        'sections' => [
                            [
                                'label' => 'Austin',
                                'href' => '/auto-retro/austin',
                                'itemColumns' => [[['label' => 'Mini', 'href' => '/mini']]],
                                'columnSpan' => 1,
                            ],
                            [
                                'label' => 'Citroen',
                                'href' => '/auto-retro/citroen',
                                'itemColumns' => [[['label' => 'Traction', 'href' => '/traction']]],
                                'columnSpan' => 1,
                            ],
                            [
                                'label' => 'Mercedes',
                                'href' => '/auto-retro/mercedes',
                                'itemColumns' => [[['label' => 'SLK', 'href' => '/slk']]],
                                'columnSpan' => 1,
                            ],
                            [
                                'label' => 'Panhard',
                                'href' => '/auto-retro/panhard',
                                'itemColumns' => [[['label' => 'Dyna', 'href' => '/dyna']]],
                                'columnSpan' => 1,
                            ],
                            [
                                'label' => 'Renault',
                                'href' => '/auto-retro/renault',
                                'itemColumns' => [[['label' => 'Twingo', 'href' => '/twingo']]],
                                'columnSpan' => 1,
                            ],
                            [
                                'label' => 'Simca',
                                'href' => '/auto-retro/simca',
                                'itemColumns' => [
                                    [['label' => 'Aronde', 'href' => '/aronde']],
                                    [['label' => 'P60', 'href' => '/p60']],
                                ],
                                'columnSpan' => 2,
                            ],
                        ],
                    ],
                ],
            ],
            'languages' => [],
            'search' => [
                'action' => '/search',
                'currentLanguage' => 'fr',
                'label' => 'Recherche',
                'placeholder' => 'Rechercher...',
            ],
        ]);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('--site-nav-mega-columns: 7;', $html);
        $this->assertStringNotContainsString('--site-nav-mega-columns: 6;', $html);
    }

    public function testMobileNavigationPromotesAndMergesDuplicateLabelChildLink(): void
    {
        if (!function_exists('normalizeMobileNavigationItemForRender')) {
            require ROOT_PATH . '/templates/partials/menus_header.php';
        }

        $normalized = normalizeMobileNavigationItemForRender([
            'id' => 'primary-austin',
            'label' => 'Austin',
            'href' => null,
            'title' => 'Austin',
            'openInNewTab' => false,
            'children' => [
                [
                    'id' => 'primary-austin-link',
                    'label' => 'Austin',
                    'href' => '/auto-retro/austin/histoire-de-austin.php',
                    'title' => 'Austin',
                    'openInNewTab' => false,
                    'children' => [],
                ],
                [
                    'id' => 'primary-austin-mini',
                    'label' => 'Histoire de la Mini',
                    'href' => '/auto-retro/austin/aventure-mini-austin.php',
                    'title' => 'Histoire de la Mini',
                    'openInNewTab' => false,
                    'children' => [],
                ],
            ],
        ]);

        $this->assertSame('/auto-retro/austin/histoire-de-austin.php', $normalized['item']['href'] ?? null);
        $this->assertCount(1, $normalized['children']);
        $this->assertSame('Histoire de la Mini', $normalized['children'][0]['label'] ?? null);
        $this->assertSame('/auto-retro/austin/aventure-mini-austin.php', $normalized['children'][0]['href'] ?? null);
    }

}
