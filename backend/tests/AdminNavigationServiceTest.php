<?php

declare(strict_types=1);

use Caramagnols\Admin\AdminNavigationService;
use Caramagnols\Content\PageRepository;
use Caramagnols\Navigation\NavigationRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class AdminNavigationServiceTest extends TestCase
{
    private string $menusFile;
    private string $pagesFile;

    protected function setUp(): void
    {
        $this->menusFile = ROOT_PATH . '/var/admin-navigation-' . uniqid() . '.json';
        $this->pagesFile = ROOT_PATH . '/var/admin-navigation-pages-' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->menusFile)) {
            unlink($this->menusFile);
        }

        if (file_exists($this->menusFile . '.bak')) {
            unlink($this->menusFile . '.bak');
        }

        if (file_exists($this->pagesFile)) {
            unlink($this->pagesFile);
        }

        if (file_exists($this->pagesFile . '.bak')) {
            unlink($this->pagesFile . '.bak');
        }
    }

    public function testHandleSavePersistsBuilderSchemaByLocation(): void
    {
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
                            'route' => '/association',
                            'translations' => [
                                'fr' => ['title' => 'Association'],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $service = new AdminNavigationService(
            new NavigationRepository($this->menusFile),
            new PageRepository($this->pagesFile)
        );

        $result = $service->handle([
            'active_location' => 'sideRight',
            'selected_item' => 'sideRight|0',
            'builder_action' => 'save',
            'banner' => [
                'image' => '/assets/images/structure/banniere.jpg',
                'headline' => 'Voyage dans le golfe',
                'alt' => 'Voyage dans le golfe',
                'title' => 'Voyage dans le golfe',
            ],
            'remonter' => [
                'label' => 'Top',
                'alt' => 'Remonter',
                'title' => 'Remonter',
            ],
            'locations' => [
                'utility' => [],
                'primary' => [
                    [
                        'id' => 'primary-home',
                        'kind' => 'group',
                        'label_text' => 'Auto-Retro',
                        'target_mode' => 'none',
                        'target_page_slug' => '',
                        'target_route' => '',
                        'target_url' => '',
                        'image' => '',
                        'content_text' => '',
                        'alt' => 'Auto-Retro',
                        'title' => 'Auto-Retro',
                        'display_mode' => 'mega',
                        'column_count' => '3',
                        'menu_template' => 'brands',
                        'featured_title' => 'Collection a la une',
                        'featured_text' => 'Selection editoriale',
                        'featured_image' => '/assets/images/hero.jpg',
                        'featured_cta_label' => 'Explorer',
                        'featured_target_mode' => 'page',
                        'featured_target_page_slug' => 'association',
                        'featured_target_route' => '',
                        'featured_target_url' => '',
                        'children' => [
                            [
                                'id' => 'primary-home-child',
                                'kind' => 'page',
                                'label_text' => 'Association',
                                'target_mode' => 'page',
                                'target_page_slug' => 'association',
                                'target_route' => '',
                                'target_url' => '',
                                'image' => '',
                                'content_text' => '',
                                'alt' => 'Association',
                                'title' => 'Association',
                                'is_highlight' => '1',
                            ],
                        ],
                    ],
                ],
                'footer' => [],
                'sideRight' => [
                    [
                        'id' => 'side-right-1',
                        'kind' => 'content_card',
                        'label_text' => 'Par marque',
                        'target_mode' => 'route',
                        'target_page_slug' => '',
                        'target_route' => '/auto-retro',
                        'target_url' => '',
                        'image' => '/assets/images/card.jpg',
                        'content_text' => 'Choisissez une famille de véhicules.',
                        'alt' => 'Par marque',
                        'title' => 'Par marque',
                    ],
                ],
                'sideLeft' => [],
            ],
        ]);

        $this->assertSame('Menus sauvegardés via le builder visuel.', $result['message']);
        $this->assertNull($result['error']);

        $decoded = json_decode((string) file_get_contents($this->menusFile), true);

        $this->assertIsArray($decoded);
        $this->assertSame('group', $decoded['locations']['primary'][0]['kind']);
        $this->assertSame('mega', $decoded['locations']['primary'][0]['presentation']['displayMode']);
        $this->assertSame(3, $decoded['locations']['primary'][0]['presentation']['columnCount']);
        $this->assertSame('brands', $decoded['locations']['primary'][0]['presentation']['menuTemplate']);
        $this->assertSame('Collection a la une', $decoded['locations']['primary'][0]['presentation']['featuredCard']['title']);
        $this->assertSame('association', $decoded['locations']['primary'][0]['presentation']['featuredCard']['target']['pageSlug']);
        $this->assertSame('association', $decoded['locations']['primary'][0]['children'][0]['target']['pageSlug']);
        $this->assertTrue($decoded['locations']['primary'][0]['children'][0]['presentation']['isHighlight']);
        $this->assertSame('content_card', $decoded['locations']['sideRight'][0]['kind']);
        $this->assertSame(
            'Choisissez une famille de véhicules.',
            $decoded['locations']['sideRight'][0]['content']['text']
        );
    }

    public function testHandleSaveRejectsDraftPagesAsTargets(): void
    {
        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'brouillon',
                            'type' => 'structured_page',
                            'status' => 'draft',
                            'route' => '/brouillon',
                            'translations' => [
                                'fr' => ['title' => 'Brouillon'],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $service = new AdminNavigationService(
            new NavigationRepository($this->menusFile),
            new PageRepository($this->pagesFile)
        );

        $result = $service->handle([
            'active_location' => 'primary',
            'selected_item' => 'primary|0',
            'builder_action' => 'save',
            'banner' => [],
            'remonter' => [],
            'locations' => [
                'utility' => [],
                'primary' => [
                    [
                        'id' => 'primary-draft',
                        'kind' => 'page',
                        'label_text' => 'Brouillon',
                        'target_mode' => 'page',
                        'target_page_slug' => 'brouillon',
                        'target_route' => '',
                        'target_url' => '',
                        'image' => '',
                        'content_text' => '',
                        'alt' => 'Brouillon',
                        'title' => 'Brouillon',
                    ],
                ],
                'footer' => [],
                'sideRight' => [],
                'sideLeft' => [],
            ],
        ]);

        $this->assertNull($result['message']);
        $this->assertSame('Menu principal > Brouillon : la page cible doit être publiée.', $result['error']);
        $this->assertFileDoesNotExist($this->menusFile);
    }

    public function testHandleSaveConvertsRouteItemToPageWhenPageTargetIsSelected(): void
    {
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
                            'route' => '/association',
                            'translations' => [
                                'fr' => ['title' => 'Association'],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $service = new AdminNavigationService(
            new NavigationRepository($this->menusFile),
            new PageRepository($this->pagesFile)
        );

        $result = $service->handle([
            'active_location' => 'primary',
            'selected_item' => 'primary|0',
            'builder_action' => 'save',
            'banner' => [],
            'remonter' => [],
            'locations' => [
                'utility' => [],
                'primary' => [
                    [
                        'id' => 'primary-link',
                        'kind' => 'route',
                        'label_text' => 'Association',
                        'target_mode' => 'route',
                        'target_page_slug' => 'association',
                        'target_route' => '/bc/boulyetcailloux-des-bijoux-artisanaux.php',
                        'target_url' => '',
                        'image' => '',
                        'content_text' => '',
                        'alt' => 'Association',
                        'title' => 'Association',
                    ],
                ],
                'footer' => [],
                'sideRight' => [],
                'sideLeft' => [],
            ],
        ]);

        $this->assertSame('Menus sauvegardés via le builder visuel.', $result['message']);
        $this->assertNull($result['error']);

        $decoded = json_decode((string) file_get_contents($this->menusFile), true);
        $this->assertIsArray($decoded);
        $this->assertSame('page', $decoded['locations']['primary'][0]['kind'] ?? null);
        $this->assertSame('association', $decoded['locations']['primary'][0]['target']['pageSlug'] ?? null);
        $this->assertNull($decoded['locations']['primary'][0]['target']['route'] ?? null);
    }

    public function testHandleSavePreservesTranslationKeysWhenPopupPostsResolvedLabels(): void
    {
        file_put_contents(
            $this->menusFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'locations' => [
                        'remonter' => [
                            'label' => ['text' => null, 'translationKey' => 'REMONTER_TOP'],
                            'accessibility' => ['alt' => 'Top', 'title' => 'Top'],
                        ],
                        'banner' => [
                            'image' => '/assets/images/structure/banniere.jpg',
                            'headline' => ['text' => null, 'translationKey' => 'TXT_BANNIERE'],
                            'accessibility' => ['alt' => null, 'title' => null],
                        ],
                        'utility' => [],
                        'primary' => [
                            [
                                'id' => 'primary-group',
                                'kind' => 'group',
                                'label' => ['text' => null, 'translationKey' => 'MENU_AUTORETRO'],
                                'target' => ['pageSlug' => null, 'route' => null, 'url' => null, 'openInNewTab' => false],
                                'media' => [],
                                'content' => [],
                                'accessibility' => [],
                                'presentation' => ['displayMode' => 'mega', 'columnCount' => 3, 'menuTemplate' => 'brands'],
                                'children' => [
                                    [
                                        'id' => 'primary-child',
                                        'kind' => 'route',
                                        'label' => ['text' => null, 'translationKey' => 'MENU_MERCEDES'],
                                        'target' => [
                                            'pageSlug' => null,
                                            'route' => '/auto-retro/mercedes/histoire-de-mercedes.php',
                                            'url' => null,
                                            'openInNewTab' => false,
                                        ],
                                        'media' => [],
                                        'content' => [],
                                        'accessibility' => [],
                                        'presentation' => [],
                                        'children' => [],
                                    ],
                                ],
                            ],
                        ],
                        'footer' => [],
                        'sideRight' => [],
                        'sideLeft' => [],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
        );

        $service = new AdminNavigationService(
            new NavigationRepository($this->menusFile),
            new PageRepository($this->pagesFile)
        );

        $result = $service->handle([
            'active_location' => 'primary',
            'selected_item' => 'primary|0',
            'builder_action' => 'save',
            'banner' => [
                'image' => '/assets/images/structure/banniere.jpg',
                'headline' => t('TXT_BANNIERE'),
                'headline_translation_key' => 'TXT_BANNIERE',
                'alt' => '',
                'title' => '',
            ],
            'remonter' => [
                'label' => t('REMONTER_TOP'),
                'label_translation_key' => 'REMONTER_TOP',
                'alt' => 'Top',
                'title' => 'Top',
            ],
            'locations' => [
                'utility' => [],
                'primary' => [
                    [
                        'id' => 'primary-group',
                        'kind' => 'group',
                        'label_text' => t('MENU_AUTORETRO'),
                        'label_translation_key' => 'MENU_AUTORETRO',
                        'target_mode' => 'group',
                        'target_page_slug' => '',
                        'target_route' => '',
                        'target_url' => '',
                        'image' => '',
                        'content_text' => '',
                        'alt' => '',
                        'title' => '',
                        'display_mode' => 'mega',
                        'column_count' => '3',
                        'menu_template' => 'brands',
                        'children' => [
                            [
                                'id' => 'primary-child',
                                'kind' => 'route',
                                'label_text' => t('MENU_MERCEDES'),
                                'label_translation_key' => 'MENU_MERCEDES',
                                'target_mode' => 'route',
                                'target_page_slug' => '',
                                'target_route' => '/auto-retro/mercedes/histoire-de-mercedes.php',
                                'target_url' => '',
                                'image' => '',
                                'content_text' => '',
                                'alt' => '',
                                'title' => '',
                            ],
                        ],
                    ],
                ],
                'footer' => [],
                'sideRight' => [],
                'sideLeft' => [],
            ],
        ]);

        $this->assertSame('Menus sauvegardés via le builder visuel.', $result['message']);
        $this->assertNull($result['error']);

        $decoded = json_decode((string) file_get_contents($this->menusFile), true);

        $this->assertSame('MENU_AUTORETRO', $decoded['locations']['primary'][0]['label']['translationKey'] ?? null);
        $this->assertNull($decoded['locations']['primary'][0]['label']['text'] ?? null);
        $this->assertSame('MENU_MERCEDES', $decoded['locations']['primary'][0]['children'][0]['label']['translationKey'] ?? null);
        $this->assertNull($decoded['locations']['primary'][0]['children'][0]['label']['text'] ?? null);
        $this->assertSame('TXT_BANNIERE', $decoded['locations']['banner']['headline']['translationKey'] ?? null);
        $this->assertSame('REMONTER_TOP', $decoded['locations']['remonter']['label']['translationKey'] ?? null);
    }

    public function testHandleSaveAllowsCustomGroupLabelOverride(): void
    {
        file_put_contents(
            $this->menusFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'locations' => [
                        'remonter' => [],
                        'banner' => [],
                        'utility' => [],
                        'primary' => [
                            [
                                'id' => 'primary-group',
                                'kind' => 'group',
                                'label' => ['text' => null, 'translationKey' => 'MENU_AUTORETRO'],
                                'target' => ['pageSlug' => null, 'route' => null, 'url' => null, 'openInNewTab' => false],
                                'media' => [],
                                'content' => [],
                                'accessibility' => [],
                                'presentation' => ['displayMode' => 'dropdown'],
                                'children' => [],
                            ],
                        ],
                        'footer' => [],
                        'sideRight' => [],
                        'sideLeft' => [],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
        );

        $service = new AdminNavigationService(
            new NavigationRepository($this->menusFile),
            new PageRepository($this->pagesFile)
        );

        $result = $service->handle([
            'active_location' => 'primary',
            'selected_item' => 'primary|0',
            'builder_action' => 'save',
            'banner' => [],
            'remonter' => [],
            'locations' => [
                'utility' => [],
                'primary' => [
                    [
                        'id' => 'primary-group',
                        'kind' => 'group',
                        'label_text' => 'Nos anciennes',
                        'label_translation_key' => 'MENU_AUTORETRO',
                        'target_mode' => 'group',
                        'target_page_slug' => '',
                        'target_route' => '',
                        'target_url' => '',
                        'image' => '',
                        'content_text' => '',
                        'alt' => '',
                        'title' => '',
                        'display_mode' => 'dropdown',
                        'column_count' => '',
                        'menu_template' => '',
                    ],
                ],
                'footer' => [],
                'sideRight' => [],
                'sideLeft' => [],
            ],
        ]);

        $this->assertSame('Menus sauvegardés via le builder visuel.', $result['message']);
        $this->assertNull($result['error']);

        $decoded = json_decode((string) file_get_contents($this->menusFile), true);

        $this->assertSame('Nos anciennes', $decoded['locations']['primary'][0]['label']['text'] ?? null);
        $this->assertNull($decoded['locations']['primary'][0]['label']['translationKey'] ?? null);
    }

    public function testHandleSavePersistsMenuLabelTranslationsByLanguage(): void
    {
        $service = new AdminNavigationService(
            new NavigationRepository($this->menusFile),
            new PageRepository($this->pagesFile)
        );

        $result = $service->handle([
            'active_location' => 'primary',
            'selected_item' => 'primary|0',
            'builder_action' => 'save',
            'banner' => [],
            'remonter' => [],
            'locations' => [
                'utility' => [],
                'primary' => [
                    [
                        'id' => 'primary-home',
                        'kind' => 'route',
                        'label_text' => 'Accueil',
                        'label_translation_key' => '',
                        'label_default_language' => 'fr',
                        'label_translations' => [
                            'fr' => 'Accueil',
                            'de' => 'Startseite',
                            'en' => 'Home',
                        ],
                        'target_mode' => 'route',
                        'target_page_slug' => '',
                        'target_route' => '/accueil',
                        'target_url' => '',
                        'image' => '',
                        'content_text' => '',
                        'alt' => 'Accueil',
                        'title' => 'Accueil',
                    ],
                ],
                'footer' => [],
                'sideRight' => [],
                'sideLeft' => [],
            ],
        ]);

        $this->assertSame('Menus sauvegardés via le builder visuel.', $result['message']);
        $this->assertNull($result['error']);

        $decoded = json_decode((string) file_get_contents($this->menusFile), true);
        $this->assertIsArray($decoded);
        $this->assertSame('fr', $decoded['locations']['primary'][0]['label']['defaultLanguage'] ?? null);
        $this->assertSame('Startseite', $decoded['locations']['primary'][0]['label']['translations']['de'] ?? null);
        $this->assertSame('Home', $decoded['locations']['primary'][0]['label']['translations']['en'] ?? null);
    }

    public function testHandleSavePersistsBannerHeadlineTranslationsByLanguage(): void
    {
        $service = new AdminNavigationService(
            new NavigationRepository($this->menusFile),
            new PageRepository($this->pagesFile)
        );

        $result = $service->handle([
            'active_location' => 'primary',
            'selected_item' => '',
            'builder_action' => 'save',
            'banner' => [
                'image' => '/assets/images/structure/banniere.jpg',
                'headline' => 'Voyage dans le golfe',
                'headline_translation_key' => '',
                'headline_default_language' => 'fr',
                'headline_translations' => [
                    'fr' => 'Voyage dans le golfe',
                    'de' => 'Reise durch den Golf',
                    'en' => 'Journey through the gulf',
                ],
                'alt' => 'Banniere',
                'title' => 'Banniere',
            ],
            'remonter' => [],
            'locations' => [
                'utility' => [],
                'primary' => [],
                'footer' => [],
                'sideRight' => [],
                'sideLeft' => [],
            ],
        ]);

        $this->assertSame('Menus sauvegardés via le builder visuel.', $result['message']);
        $this->assertNull($result['error']);

        $decoded = json_decode((string) file_get_contents($this->menusFile), true);
        $this->assertIsArray($decoded);
        $this->assertSame('fr', $decoded['locations']['banner']['headline']['defaultLanguage'] ?? null);
        $this->assertSame('Voyage dans le golfe', $decoded['locations']['banner']['headline']['translations']['fr'] ?? null);
        $this->assertSame('Reise durch den Golf', $decoded['locations']['banner']['headline']['translations']['de'] ?? null);
        $this->assertSame('Journey through the gulf', $decoded['locations']['banner']['headline']['translations']['en'] ?? null);
    }

    public function testHandleSavePersistsBackToTopLabelTranslationsByLanguage(): void
    {
        $service = new AdminNavigationService(
            new NavigationRepository($this->menusFile),
            new PageRepository($this->pagesFile)
        );

        $result = $service->handle([
            'active_location' => 'primary',
            'selected_item' => '',
            'builder_action' => 'save',
            'banner' => [],
            'remonter' => [
                'label' => 'Remonter',
                'label_translation_key' => '',
                'label_default_language' => 'fr',
                'label_translations' => [
                    'fr' => 'Remonter',
                    'de' => 'Nach oben',
                ],
                'alt' => 'Remonter',
                'title' => 'Remonter',
            ],
            'locations' => [
                'utility' => [],
                'primary' => [],
                'footer' => [],
                'sideRight' => [],
                'sideLeft' => [],
            ],
        ]);

        $this->assertSame('Menus sauvegardés via le builder visuel.', $result['message']);
        $this->assertNull($result['error']);

        $decoded = json_decode((string) file_get_contents($this->menusFile), true);
        $this->assertIsArray($decoded);
        $this->assertSame('fr', $decoded['locations']['remonter']['label']['defaultLanguage'] ?? null);
        $this->assertSame('Remonter', $decoded['locations']['remonter']['label']['translations']['fr'] ?? null);
        $this->assertSame('Nach oben', $decoded['locations']['remonter']['label']['translations']['de'] ?? null);
    }

    public function testHandleSavePersistsFooterNoticeTranslationsWithFrenchDefault(): void
    {
        $service = new AdminNavigationService(
            new NavigationRepository($this->menusFile),
            new PageRepository($this->pagesFile)
        );

        $result = $service->handle([
            'active_location' => 'footer',
            'selected_item' => '',
            'builder_action' => 'save',
            'banner' => [],
            'remonter' => [],
            'footer_notice' => [
                'default_language' => 'fr',
                'translation_key' => 'TXT_PiedPageModele',
                'translations' => [
                    'fr' => 'Texte footer FR',
                    'de' => 'Footertext DE',
                    'en' => 'Footer text EN',
                ],
            ],
            'locations' => [
                'utility' => [],
                'primary' => [],
                'footer' => [],
                'sideRight' => [],
                'sideLeft' => [],
            ],
        ]);

        $this->assertSame('Menus sauvegardés via le builder visuel.', $result['message']);
        $this->assertNull($result['error']);

        $decoded = json_decode((string) file_get_contents($this->menusFile), true);
        $this->assertIsArray($decoded);
        $this->assertSame('fr', $decoded['locations']['footerNotice']['defaultLanguage'] ?? null);
        $this->assertSame('TXT_PiedPageModele', $decoded['locations']['footerNotice']['translationKey'] ?? null);
        $this->assertSame('Texte footer FR', $decoded['locations']['footerNotice']['translations']['fr'] ?? null);
        $this->assertSame('Footertext DE', $decoded['locations']['footerNotice']['translations']['de'] ?? null);
        $this->assertSame('Footer text EN', $decoded['locations']['footerNotice']['translations']['en'] ?? null);
    }

    public function testHandleSaveFooterNoticeOnlyPreservesExistingFooterLinks(): void
    {
        file_put_contents(
            $this->menusFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'locations' => [
                        'remonter' => [],
                        'banner' => [],
                        'footerNotice' => [
                            'defaultLanguage' => 'fr',
                            'translationKey' => 'TXT_PiedPageModele',
                            'translations' => ['fr' => 'Ancien texte'],
                        ],
                        'utility' => [],
                        'primary' => [],
                        'footer' => [
                            [
                                'id' => 'footer-mentions',
                                'kind' => 'route',
                                'label' => ['text' => 'Mentions légales', 'translationKey' => null],
                                'target' => ['pageSlug' => null, 'route' => '/mentions-legales', 'url' => null, 'openInNewTab' => false],
                                'media' => [],
                                'content' => [],
                                'accessibility' => [],
                                'presentation' => [],
                                'children' => [],
                            ],
                        ],
                        'sideRight' => [],
                        'sideLeft' => [],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
        );

        $service = new AdminNavigationService(
            new NavigationRepository($this->menusFile),
            new PageRepository($this->pagesFile)
        );

        $result = $service->handle([
            'active_location' => 'primary',
            'selected_item' => '',
            'builder_action' => 'save',
            'footer_notice' => [
                'translation_key' => 'TXT_PiedPageModele',
                'default_language' => 'fr',
                'translations' => [
                    'fr' => 'Nouveau texte footer',
                ],
            ],
        ]);

        $this->assertSame('Menus sauvegardés via le builder visuel.', $result['message']);
        $this->assertNull($result['error']);

        $decoded = json_decode((string) file_get_contents($this->menusFile), true);
        $this->assertIsArray($decoded);
        $this->assertSame('Nouveau texte footer', $decoded['locations']['footerNotice']['translations']['fr'] ?? null);
        $this->assertSame('Mentions légales', $decoded['locations']['footer'][0]['label']['text'] ?? null);
        $this->assertSame('/mentions-legales', $decoded['locations']['footer'][0]['target']['route'] ?? null);
    }

    public function testHandleSaveClearsNavigationViewModelCacheAfterPersistingMenus(): void
    {
        file_put_contents(
            $this->menusFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'locations' => [
                        'remonter' => [],
                        'banner' => [],
                        'footerNotice' => [],
                        'utility' => [],
                        'primary' => [],
                        'footer' => [],
                        'sideRight' => [],
                        'sideLeft' => [],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );
        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        menus_data_set_path_override($this->menusFile);
        pages_data_set_path_override($this->pagesFile);

        try {
            navigation_view_model('/admin/menus', 'fr');
            $cacheBeforeSave = navigation_view_model_store();
            $this->assertNotSame([], $cacheBeforeSave);

            $service = new AdminNavigationService(
                new NavigationRepository($this->menusFile),
                new PageRepository($this->pagesFile)
            );

            $result = $service->handle([
                'active_location' => 'primary',
                'selected_item' => '',
                'builder_action' => 'save',
                'banner' => [
                    'image' => '/assets/images/structure/banniere.jpg',
                    'headline' => 'Titre',
                    'headline_translation_key' => 'TXT_BANNIERE',
                    'alt' => 'Banniere',
                    'title' => 'Banniere',
                ],
                'remonter' => [
                    'label' => 'Top',
                    'label_translation_key' => 'REMONTER_TOP',
                    'alt' => 'Top',
                    'title' => 'Top',
                ],
                'footer_notice' => [
                    'default_language' => 'fr',
                    'translation_key' => 'TXT_PiedPageModele',
                    'translations' => ['fr' => 'Texte footer'],
                ],
                'locations' => [
                    'utility' => [],
                    'primary' => [],
                    'footer' => [],
                    'sideRight' => [],
                    'sideLeft' => [],
                ],
            ]);

            $this->assertSame('Menus sauvegardés via le builder visuel.', $result['message']);
            $this->assertNull($result['error']);

            $cacheAfterSave = navigation_view_model_store();
            $this->assertSame([], $cacheAfterSave);
        } finally {
            menus_data_set_path_override(null);
            pages_data_set_path_override(null);
        }
    }
}
