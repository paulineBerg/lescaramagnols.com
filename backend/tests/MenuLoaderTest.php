<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';
require_once ROOT_PATH . '/core/menu_loader.php';

final class MenuLoaderTest extends TestCase
{
    private string $tmpFile;
    private string $tmpPagesFile;
    /**
     * @var array<string, string>|null
     */
    private ?array $originalTranslations = null;

    protected function setUp(): void
    {
        $this->tmpFile = ROOT_PATH . '/var/menus-test-' . uniqid() . '.json';
        $this->tmpPagesFile = ROOT_PATH . '/var/pages-menu-test-' . uniqid() . '.json';
        menus_data_set_path_override($this->tmpFile);
        pages_data_set_path_override($this->tmpPagesFile);
        pages_cache_clear();
        $translations = $GLOBALS['langTranslations'] ?? null;
        $this->originalTranslations = is_array($translations) ? $translations : null;
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }

        if (file_exists($this->tmpFile . '.bak')) {
            unlink($this->tmpFile . '.bak');
        }

        if (file_exists($this->tmpPagesFile)) {
            unlink($this->tmpPagesFile);
        }

        menus_data_set_path_override(null);
        pages_data_set_path_override(null);
        pages_cache_clear();
        $GLOBALS['langTranslations'] = $this->originalTranslations ?? load_translations_cached('fr');
    }

    public function testNormalizeMenuConfigMapsLegacySnakeCaseToCanonicalCamelCase(): void
    {
        $normalized = normalize_menu_config([
            'menu1' => [['url' => 'https://example.test']],
            'menu2' => [['titre' => 'Accueil']],
            'menu3' => [['titre' => 'Plan']],
            'menu_droit' => [['titre' => 'Droite']],
            'menu_gauche' => [['titre' => 'Gauche']],
        ]);

        $this->assertArrayHasKey('menuDroit', $normalized);
        $this->assertArrayHasKey('menuGauche', $normalized);
        $this->assertSame('Droite', $normalized['menuDroit'][0]['titre']);
        $this->assertSame('Gauche', $normalized['menuGauche'][0]['titre']);
    }

    public function testSaveMenusWritesVersionedCanonicalSchema(): void
    {
        $saved = save_menus([
            'menu1' => [['url' => 'https://example.test', 'title' => 'Réseau']],
            'banniere' => ['image' => '/banner.jpg', 'texte_key' => 'Bannière'],
            'menu2' => [['titre' => 'Accueil', 'chemin' => '/accueil']],
            'menu3' => [['titre' => 'Plan', 'chemin' => '/plan']],
            'menuDroit' => [['titre' => 'Droite', 'chemin' => '/droite']],
            'menuGauche' => [['titre' => 'Gauche', 'chemin' => '/gauche']],
        ]);

        $this->assertTrue($saved);
        $this->assertFileExists($this->tmpFile);

        $decoded = json_decode((string) file_get_contents($this->tmpFile), true);

        $this->assertIsArray($decoded);
        $this->assertSame(2, $decoded['meta']['version'] ?? null);
        $this->assertArrayHasKey('locations', $decoded);
        $this->assertArrayHasKey('primary', $decoded['locations']);
        $this->assertArrayHasKey('sideRight', $decoded['locations']);
        $this->assertArrayHasKey('sideLeft', $decoded['locations']);
    }

    public function testLoadMenusReadsVersionedSchemaAndReturnsLegacyView(): void
    {
        $canonical = [
            'meta' => ['version' => 2],
            'locations' => [
                'remonter' => [],
                'banner' => ['image' => '/banner.jpg', 'headline' => ['text' => 'Bannière']],
                'utility' => [],
                'primary' => [
                    [
                        'id' => 'primary-home',
                        'kind' => 'route',
                        'label' => ['text' => 'Accueil'],
                        'target' => ['route' => '/accueil'],
                        'media' => [],
                        'accessibility' => ['alt' => 'Accueil', 'title' => 'Accueil'],
                        'children' => [],
                    ],
                ],
                'footer' => [],
                'sideRight' => [],
                'sideLeft' => [],
            ],
        ];

        file_put_contents($this->tmpFile, json_encode($canonical, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $menus = load_menus();

        $this->assertSame('Accueil', $menus['menu2'][0]['titre']);
        $this->assertSame('/accueil', $menus['menu2'][0]['chemin']);
        $this->assertSame('/banner.jpg', $menus['banniere']['image']);
    }

    public function testLoadMenusResolvesPageSlugToRegisteredRouteAndDisablesDraftPages(): void
    {
        $pages = [
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
        ];

        file_put_contents($this->tmpPagesFile, json_encode($pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        save_menus([
            'menu1' => [],
            'banniere' => ['image' => '/banner.jpg', 'texte_key' => 'Bannière'],
            'menu2' => [
                ['titre' => 'Association', 'page_slug' => 'association', 'chemin' => '/association'],
                ['titre' => 'Brouillon', 'page_slug' => 'brouillon', 'chemin' => '/brouillon'],
            ],
            'menu3' => [],
            'menuDroit' => [],
            'menuGauche' => [],
        ]);

        $menus = load_menus();

        $this->assertSame('/notre-association', $menus['menu2'][0]['chemin']);
        $this->assertSame('', $menus['menu2'][1]['chemin']);
    }

    public function testLoadMenusKeepsSideCardEditorialTextFromCanonicalSchema(): void
    {
        $canonical = [
            'meta' => ['version' => 2],
            'locations' => [
                'remonter' => [],
                'banner' => [],
                'utility' => [],
                'primary' => [],
                'footer' => [],
                'sideRight' => [
                    [
                        'id' => 'side-right-card',
                        'kind' => 'content_card',
                        'label' => ['text' => 'Par marque', 'translationKey' => null],
                        'target' => ['pageSlug' => null, 'route' => '/auto-retro', 'url' => null, 'openInNewTab' => false],
                        'media' => ['image' => '/assets/images/card.jpg'],
                        'content' => ['text' => 'Choisissez une marque de voiture.'],
                        'accessibility' => ['alt' => 'Par marque', 'title' => 'Par marque'],
                        'children' => [],
                    ],
                ],
                'sideLeft' => [],
            ],
        ];

        file_put_contents($this->tmpFile, json_encode($canonical, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $menus = load_menus();

        $this->assertSame('Par marque', $menus['menuDroit'][0]['titre']);
        $this->assertSame('Choisissez une marque de voiture.', $menus['menuDroit'][0]['texte']);
        $this->assertSame('/auto-retro', $menus['menuDroit'][0]['chemin']);
    }

    public function testLoadMenusTranslatesCanonicalTranslationKeysForCurrentLanguage(): void
    {
        $canonical = [
            'meta' => ['version' => 2],
            'locations' => [
                'remonter' => ['label' => ['translationKey' => 'REMONTER_TOP']],
                'banner' => [
                    'image' => '/banner.jpg',
                    'headline' => ['translationKey' => 'TXT_BANNIERE'],
                ],
                'utility' => [],
                'primary' => [
                    [
                        'id' => 'primary-home',
                        'kind' => 'route',
                        'label' => ['translationKey' => 'MENU_ACCUEIL'],
                        'target' => ['route' => '/accueil'],
                        'media' => [],
                        'accessibility' => [],
                        'children' => [],
                    ],
                ],
                'footer' => [],
                'sideRight' => [],
                'sideLeft' => [],
            ],
        ];

        file_put_contents($this->tmpFile, json_encode($canonical, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $GLOBALS['langTranslations'] = load_translations_cached('de');

        $menus = load_menus();

        $this->assertSame('STARTSEITE', $menus['menu2'][0]['titre']);
        $this->assertSame('REISE IN DEN GOLF VON SAINT-TROPEZ UND UNSERE SAMMLERAUTOS', $menus['banniere']['texte_key']);
        $this->assertSame('Nach oben', $menus['remonter']['titre']);
    }
}
