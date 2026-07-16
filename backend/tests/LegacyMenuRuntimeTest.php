<?php

declare(strict_types=1);

use Caramagnols\Content\PageRepository;
use Caramagnols\Navigation\LegacyMenuRuntime;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class LegacyMenuRuntimeTest extends TestCase
{
    private string $pagesFile;

    protected function setUp(): void
    {
        $this->pagesFile = ROOT_PATH . '/var/legacy-menu-runtime-pages-' . uniqid() . '.json';

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
                            'translations' => ['fr' => ['title' => 'Association']],
                        ],
                        [
                            'slug' => 'brouillon',
                            'type' => 'structured_page',
                            'status' => 'draft',
                            'route' => '/brouillon',
                            'translations' => ['fr' => ['title' => 'Brouillon']],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );
    }

    protected function tearDown(): void
    {
        if (file_exists($this->pagesFile)) {
            unlink($this->pagesFile);
        }
    }

    public function testTranslateLegacyMenuLabelsResolvesKnownTranslationKeys(): void
    {
        $translated = LegacyMenuRuntime::translateLegacyMenuLabels(
            [
                'remonter' => ['titre' => 'REMONTER_TOP'],
                'banniere' => ['texte_key' => 'TXT_BANNIERE'],
                'menu2' => [
                    [
                        'titre' => 'MENU_ACCUEIL',
                        'sous_menu' => [
                            ['titre' => 'MENU_RECHERCHE'],
                        ],
                    ],
                ],
            ],
            [
                'REMONTER_TOP' => 'Haut',
                'TXT_BANNIERE' => 'Banniere',
                'MENU_ACCUEIL' => 'Accueil',
                'MENU_RECHERCHE' => 'Recherche',
            ]
        );

        $this->assertSame('Haut', $translated['remonter']['titre']);
        $this->assertSame('Banniere', $translated['banniere']['texte_key']);
        $this->assertSame('Accueil', $translated['menu2'][0]['titre']);
        $this->assertSame('Recherche', $translated['menu2'][0]['sous_menu'][0]['titre']);
    }

    public function testResolvePageSlugsOnlyKeepsPublishedRoutes(): void
    {
        $runtime = new LegacyMenuRuntime(new PageRepository($this->pagesFile));

        $resolved = $runtime->resolvePageSlugs(
            [
                'menu2' => [
                    ['titre' => 'Association', 'page_slug' => 'association', 'chemin' => '/old-association'],
                    ['titre' => 'Brouillon', 'page_slug' => 'brouillon', 'chemin' => '/old-brouillon'],
                ],
                'menu3' => [],
                'menuDroit' => [],
                'menuGauche' => [],
            ]
        );

        $this->assertSame('/association', $resolved['menu2'][0]['chemin']);
        $this->assertSame('', $resolved['menu2'][1]['chemin']);
    }
}
