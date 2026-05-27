<?php

declare(strict_types=1);

use Caramagnols\Navigation\NavigationRepository;
use Caramagnols\Navigation\SqlNavigationStore;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class SqlNavigationStoreTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testSaveLegacyConfigAndReloadCanonicalTreeFromSql(): void
    {
        $store = new SqlNavigationStore($this->editorialSqlDatabase());

        $saved = $store->saveLegacyConfig([
            'remonter' => ['titre' => 'TOP'],
            'menu1' => [['url' => 'https://example.test', 'titre' => 'Réseau']],
            'banniere' => ['image' => '/banner.jpg', 'texte_key' => 'Bannière'],
            'menu2' => [
                [
                    'titre' => 'Accueil',
                    'chemin' => '/accueil',
                    'sous_menu' => [
                        ['titre' => 'Association', 'chemin' => '/association'],
                    ],
                ],
            ],
            'menu3' => [['titre' => 'Plan', 'chemin' => '/plan']],
            'menuDroit' => [
                [
                    'titre' => 'Par marque',
                    'texte' => 'Choisissez une famille de véhicules.',
                    'chemin' => '/auto-retro',
                    'image' => '/assets/images/card.jpg',
                ],
            ],
            'menuGauche' => [],
        ]);

        $this->assertTrue($saved);

        $canonical = $store->loadCanonical();
        $this->assertSame('Accueil', $canonical['locations']['primary'][0]['label']['text']);
        $this->assertSame('group', $canonical['locations']['primary'][0]['kind']);
        $this->assertSame('Association', $canonical['locations']['primary'][0]['children'][0]['label']['text']);
        $this->assertSame('content_card', $canonical['locations']['sideRight'][0]['kind']);
        $this->assertSame('Par marque', $canonical['locations']['sideRight'][0]['label']['text']);

        $legacy = NavigationRepository::canonicalToLegacy($canonical);
        $this->assertSame('Plan', $legacy['menu3'][0]['titre']);
        $this->assertSame('Choisissez une famille de véhicules.', $legacy['menuDroit'][0]['texte']);
    }

    public function testSaveLegacyConfigInfersTranslationKeysForMenuLabels(): void
    {
        $store = new SqlNavigationStore($this->editorialSqlDatabase());

        $saved = $store->saveLegacyConfig([
            'remonter' => [
                'titre' => 'Remonter',
                'alt' => 'Remonter',
                'title' => 'Remonter',
            ],
            'banniere' => [
                'image' => '/banner.jpg',
                'texte_key' => 'VOYAGE DANS LE GOLFE DE SAINT-TROPEZ ET NOS VOITURES DE COLLECTION',
                'alt' => 'VOYAGE DANS LE GOLFE DE SAINT-TROPEZ ET NOS VOITURES DE COLLECTION',
                'title' => 'VOYAGE DANS LE GOLFE DE SAINT-TROPEZ ET NOS VOITURES DE COLLECTION',
            ],
            'menu1' => [],
            'menu2' => [
                ['titre' => 'ACCUEIL', 'chemin' => '/accueil'],
                ['titre' => 'AUTO-RETRO', 'chemin' => '/auto-retro'],
            ],
            'menu3' => [],
            'menuDroit' => [],
            'menuGauche' => [],
        ]);

        $this->assertTrue($saved);

        $canonical = $store->loadCanonical();

        $this->assertSame('REMONTER_TOP', $canonical['locations']['remonter']['label']['translationKey'] ?? null);
        $this->assertSame('TXT_BANNIERE', $canonical['locations']['banner']['headline']['translationKey'] ?? null);
        $this->assertSame('MENU_ACCUEIL', $canonical['locations']['primary'][0]['label']['translationKey'] ?? null);
        $this->assertSame('MENU_AUTORETRO', $canonical['locations']['primary'][1]['label']['translationKey'] ?? null);
        $this->assertNull($canonical['locations']['primary'][1]['label']['text'] ?? null);
    }

    public function testSaveCanonicalPersistsMegaMenuPresentationFields(): void
    {
        $store = new SqlNavigationStore($this->editorialSqlDatabase());

        $saved = $store->saveCanonical([
            'meta' => ['version' => 2],
            'locations' => [
                'remonter' => [],
                'banner' => [],
                'utility' => [],
                'primary' => [
                    [
                        'id' => 'primary-auto',
                        'kind' => 'group',
                        'label' => ['text' => 'Auto-Retro'],
                        'target' => ['pageSlug' => null, 'route' => null, 'url' => null, 'openInNewTab' => false],
                        'media' => [],
                        'content' => [],
                        'accessibility' => [],
                        'presentation' => [
                            'displayMode' => 'mega',
                            'columnCount' => 4,
                            'menuTemplate' => 'brands',
                            'isHighlight' => false,
                            'featuredCard' => [
                                'title' => 'Collection a la une',
                                'text' => 'Selection editoriale',
                                'image' => '/assets/images/hero.jpg',
                                'ctaLabel' => 'Explorer',
                                'target' => [
                                    'pageSlug' => null,
                                    'route' => '/auto-retro',
                                    'url' => null,
                                    'openInNewTab' => false,
                                ],
                            ],
                        ],
                        'children' => [
                            [
                                'id' => 'primary-mini',
                                'kind' => 'route',
                                'label' => ['text' => 'Mini'],
                                'target' => ['pageSlug' => null, 'route' => '/mini', 'url' => null, 'openInNewTab' => false],
                                'media' => [],
                                'content' => [],
                                'accessibility' => [],
                                'presentation' => ['isHighlight' => true],
                                'children' => [],
                            ],
                        ],
                    ],
                ],
                'footer' => [],
                'sideRight' => [],
                'sideLeft' => [],
            ],
        ]);

        $this->assertTrue($saved);

        $canonical = $store->loadCanonical();

        $this->assertSame('mega', $canonical['locations']['primary'][0]['presentation']['displayMode'] ?? null);
        $this->assertSame(4, $canonical['locations']['primary'][0]['presentation']['columnCount'] ?? null);
        $this->assertSame('brands', $canonical['locations']['primary'][0]['presentation']['menuTemplate'] ?? null);
        $this->assertSame('Collection a la une', $canonical['locations']['primary'][0]['presentation']['featuredCard']['title'] ?? null);
        $this->assertSame('/auto-retro', $canonical['locations']['primary'][0]['presentation']['featuredCard']['target']['route'] ?? null);
        $this->assertTrue($canonical['locations']['primary'][0]['children'][0]['presentation']['isHighlight'] ?? false);
    }

    public function testSaveCanonicalPersistsMenuLabelTranslationsByLanguage(): void
    {
        $store = new SqlNavigationStore($this->editorialSqlDatabase());

        $saved = $store->saveCanonical([
            'meta' => ['version' => 2],
            'locations' => [
                'remonter' => [],
                'banner' => [],
                'footerNotice' => [],
                'utility' => [],
                'primary' => [
                    [
                        'id' => 'primary-home',
                        'kind' => 'route',
                        'label' => [
                            'text' => 'Accueil',
                            'translationKey' => null,
                            'defaultLanguage' => 'fr',
                            'translations' => [
                                'fr' => 'Accueil',
                                'de' => 'Startseite',
                                'en' => 'Home',
                            ],
                        ],
                        'target' => ['pageSlug' => null, 'route' => '/accueil', 'url' => null, 'openInNewTab' => false],
                        'media' => [],
                        'content' => [],
                        'accessibility' => [],
                        'presentation' => [],
                        'children' => [],
                    ],
                ],
                'footer' => [],
                'sideRight' => [],
                'sideLeft' => [],
            ],
        ]);

        $this->assertTrue($saved);

        $canonical = $store->loadCanonical();
        $this->assertSame('fr', $canonical['locations']['primary'][0]['label']['defaultLanguage'] ?? null);
        $this->assertSame('Startseite', $canonical['locations']['primary'][0]['label']['translations']['de'] ?? null);
        $this->assertSame('Home', $canonical['locations']['primary'][0]['label']['translations']['en'] ?? null);
    }

    public function testLoadCanonicalFallsBackToLegacyFooterWhenSqlLocationIsMissing(): void
    {
        $database = $this->editorialSqlDatabase();
        $store = new SqlNavigationStore($database);

        $this->assertTrue($store->saveCanonical([
            'meta' => ['version' => 2],
            'locations' => [
                'remonter' => [],
                'banner' => [],
                'footerNotice' => [],
                'utility' => [],
                'primary' => [
                    [
                        'id' => 'primary-home',
                        'kind' => 'route',
                        'label' => ['text' => 'Accueil'],
                        'target' => ['pageSlug' => null, 'route' => '/accueil', 'url' => null, 'openInNewTab' => false],
                        'media' => [],
                        'content' => [],
                        'accessibility' => [],
                        'presentation' => [],
                        'children' => [],
                    ],
                ],
                'footer' => [],
                'sideRight' => [],
                'sideLeft' => [],
            ],
        ]));

        $database->pdo()->prepare(
            sprintf('DELETE FROM `%s` WHERE `location_key` = :location_key', $database->table('navigation_sets'))
        )->execute(['location_key' => 'footer']);
        $store->clearCache();

        $canonical = $store->loadCanonical([
            'menu3' => [
                ['titre' => 'Mentions légales', 'chemin' => '/mentions-legales'],
            ],
        ]);

        $footerLabel = is_array($canonical['locations']['footer'][0]['label'] ?? null)
            ? $canonical['locations']['footer'][0]['label']
            : [];
        $this->assertContains(
            $footerLabel['text'] ?? ($footerLabel['translationKey'] ?? null),
            ['Mentions légales', 'MENU_MENTIONS']
        );
        $this->assertSame('/mentions-legales', $canonical['locations']['footer'][0]['target']['route'] ?? null);
        $this->assertSame('Accueil', $canonical['locations']['primary'][0]['label']['text'] ?? null);
    }
}
