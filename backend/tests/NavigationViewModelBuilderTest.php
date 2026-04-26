<?php

declare(strict_types=1);

use Caramagnols\Content\PageRepository;
use Caramagnols\Navigation\NavigationViewModelBuilder;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class NavigationViewModelBuilderTest extends TestCase
{
    private string $pagesFile;

    protected function setUp(): void
    {
        $this->pagesFile = ROOT_PATH . '/var/navigation-view-model-' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->pagesFile)) {
            unlink($this->pagesFile);
        }

        if (file_exists($this->pagesFile . '.bak')) {
            unlink($this->pagesFile . '.bak');
        }
    }

    public function testBuildResolvesPageSlugTargetsAndMarksActiveBranch(): void
    {
        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'association',
                            'type' => PageRepository::TYPE_STRUCTURED_PAGE,
                            'status' => PageRepository::STATUS_PUBLISHED,
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

        $builder = new NavigationViewModelBuilder(new PageRepository($this->pagesFile), ['fr', 'en']);
        $viewModel = $builder->build(
            [
                'meta' => ['version' => 2],
                'locations' => [
                    'remonter' => [],
                    'banner' => [
                        'image' => '/assets/images/structure/banniere.jpg',
                        'headline' => ['text' => 'Voyage dans le golfe'],
                        'accessibility' => ['alt' => 'Bannière', 'title' => 'Bannière'],
                    ],
                    'utility' => [],
                    'primary' => [
                        [
                            'id' => 'primary-auto',
                            'kind' => 'group',
                            'label' => ['text' => 'Auto-Retro'],
                            'target' => ['pageSlug' => null, 'route' => null, 'url' => null],
                            'media' => [],
                            'accessibility' => ['alt' => 'Auto-Retro', 'title' => 'Auto-Retro'],
                            'children' => [
                                [
                                    'id' => 'primary-association',
                                    'kind' => 'page',
                                    'label' => ['text' => 'Association'],
                                    'target' => ['pageSlug' => 'association', 'route' => null, 'url' => null],
                                    'media' => [],
                                    'accessibility' => ['alt' => 'Association', 'title' => 'Association'],
                                    'children' => [],
                                ],
                            ],
                        ],
                    ],
                    'footer' => [],
                    'sideRight' => [
                        [
                            'id' => 'side-right-association',
                            'kind' => 'content_card',
                            'label' => ['text' => 'Par marque'],
                            'target' => ['pageSlug' => 'association', 'route' => null, 'url' => null],
                            'media' => ['image' => '/assets/images/card.jpg'],
                            'content' => ['text' => 'Choisissez une famille de véhicules.'],
                            'accessibility' => ['alt' => 'Par marque', 'title' => 'Par marque'],
                            'children' => [],
                        ],
                    ],
                    'sideLeft' => [],
                ],
            ],
            'fr',
            '/association?foo=bar'
        );

        $this->assertSame('Voyage dans le golfe', $viewModel['banner']['headline'] ?? null);
        $this->assertTrue($viewModel['primary'][0]['active'] ?? false);
        $this->assertSame('/association', $viewModel['primary'][0]['children'][0]['href'] ?? null);
        $this->assertTrue($viewModel['primary'][0]['children'][0]['active'] ?? false);
        $this->assertSame('/association', $viewModel['sideRight'][0]['href'] ?? null);
    }

    public function testBuildResolvesLegacyPrefixedPageSlugTargets(): void
    {
        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'site-sava-sava-auto-retro-rioz',
                            'type' => PageRepository::TYPE_STRUCTURED_PAGE,
                            'status' => PageRepository::STATUS_PUBLISHED,
                            'route' => '/sava/sava-auto-retro-rioz.php',
                            'translations' => [
                                'fr' => ['title' => 'SAVA'],
                            ],
                        ],
                        [
                            'slug' => 'site-bc-boulyetcailloux-des-bijoux-artisanaux',
                            'type' => PageRepository::TYPE_STRUCTURED_PAGE,
                            'status' => PageRepository::STATUS_PUBLISHED,
                            'route' => '/bc/boulyetcailloux-des-bijoux-artisanaux.php',
                            'translations' => [
                                'fr' => ['title' => 'B&C Bijoux'],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $builder = new NavigationViewModelBuilder(new PageRepository($this->pagesFile), ['fr', 'en']);
        $viewModel = $builder->build(
            [
                'meta' => ['version' => 2],
                'locations' => [
                    'primary' => [
                        [
                            'id' => 'primary-sava',
                            'kind' => 'page',
                            'label' => [
                                'defaultLanguage' => 'fr',
                                'translations' => ['fr' => 'SAVA'],
                            ],
                            'target' => ['pageSlug' => 'sava-sava-auto-retro-rioz', 'route' => null, 'url' => null],
                            'children' => [],
                        ],
                        [
                            'id' => 'primary-bc',
                            'kind' => 'page',
                            'label' => [
                                'defaultLanguage' => 'fr',
                                'translations' => ['fr' => 'B&C BIJOUX'],
                            ],
                            'target' => [
                                'pageSlug' => 'boulyetcailloux-des-bijoux-artisanaux',
                                'route' => null,
                                'url' => null,
                            ],
                            'children' => [],
                        ],
                    ],
                ],
            ],
            'fr',
            '/sava/sava-auto-retro-rioz.php'
        );

        $this->assertSame('/sava/sava-auto-retro-rioz.php', $viewModel['primary'][0]['href'] ?? null);
        $this->assertTrue($viewModel['primary'][0]['active'] ?? false);
        $this->assertSame('/bc/boulyetcailloux-des-bijoux-artisanaux.php', $viewModel['primary'][1]['href'] ?? null);
    }

    public function testBuildRewritesLanguageLinksOnCurrentRequest(): void
    {
        $builder = new NavigationViewModelBuilder(new PageRepository($this->pagesFile), ['fr', 'de', 'en']);
        $viewModel = $builder->build(
            [
                'meta' => ['version' => 2],
                'locations' => [],
            ],
            'fr',
            '/association?foo=bar&lang=fr'
        );

        $this->assertSame('/association?foo=bar&lang=fr', $viewModel['languages'][0]['href'] ?? null);
        $this->assertSame('/association?foo=bar&lang=de', $viewModel['languages'][1]['href'] ?? null);
        $this->assertSame('/association?foo=bar&lang=en', $viewModel['languages'][2]['href'] ?? null);
        $this->assertTrue($viewModel['languages'][0]['active'] ?? false);
        $this->assertFalse($viewModel['languages'][2]['active'] ?? true);
    }

    public function testBuildCreatesMegaMenuColumnsAndFeaturedCard(): void
    {
        $builder = new NavigationViewModelBuilder(new PageRepository($this->pagesFile), ['fr', 'de']);
        $viewModel = $builder->build(
            [
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
                            'target' => ['pageSlug' => null, 'route' => null, 'url' => null],
                            'media' => [],
                            'content' => [],
                            'accessibility' => ['alt' => 'Auto-Retro', 'title' => 'Auto-Retro'],
                            'presentation' => [
                                'displayMode' => 'mega',
                                'columnCount' => 3,
                                'menuTemplate' => 'brands',
                                'isHighlight' => false,
                                'featuredCard' => [
                                    'title' => 'Collection a la une',
                                    'text' => 'Une selection de voitures anciennes.',
                                    'image' => '/assets/images/hero.jpg',
                                    'ctaLabel' => 'Explorer',
                                    'target' => ['pageSlug' => null, 'route' => '/auto-retro', 'url' => null],
                                ],
                            ],
                            'children' => [
                                [
                                    'id' => 'primary-austin',
                                    'kind' => 'group',
                                    'label' => [
                                        'text' => 'Austin',
                                        'defaultLanguage' => 'fr',
                                        'translations' => [
                                            'fr' => 'Austin',
                                        ],
                                    ],
                                    'target' => ['pageSlug' => null, 'route' => null, 'url' => null],
                                    'media' => [],
                                    'content' => [],
                                    'accessibility' => [],
                                    'children' => [
                                        [
                                            'id' => 'primary-mini',
                                            'kind' => 'route',
                                            'label' => ['text' => 'Mini'],
                                            'target' => ['pageSlug' => null, 'route' => '/mini', 'url' => null],
                                            'media' => [],
                                            'content' => [],
                                            'accessibility' => [],
                                            'presentation' => ['isHighlight' => true],
                                            'children' => [],
                                        ],
                                    ],
                                ],
                                [
                                    'id' => 'primary-renault',
                                    'kind' => 'group',
                                    'label' => ['text' => 'Renault'],
                                    'target' => ['pageSlug' => null, 'route' => null, 'url' => null],
                                    'media' => [],
                                    'content' => [],
                                    'accessibility' => [],
                                    'children' => [
                                        [
                                            'id' => 'primary-twingo',
                                            'kind' => 'route',
                                            'label' => ['text' => 'Twingo'],
                                            'target' => ['pageSlug' => null, 'route' => '/twingo', 'url' => null],
                                            'media' => [],
                                            'content' => [],
                                            'accessibility' => [],
                                            'children' => [],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'footer' => [],
                    'sideRight' => [],
                    'sideLeft' => [],
                ],
            ],
            'fr',
            '/auto-retro'
        );

        $this->assertSame('mega', $viewModel['primary'][0]['panelKind'] ?? null);
        $this->assertSame(2, count($viewModel['primary'][0]['mega']['sections'] ?? []));
        $this->assertSame(3, $viewModel['primary'][0]['mega']['columnCount'] ?? null);
        $this->assertSame('Collection a la une', $viewModel['primary'][0]['mega']['featuredCard']['title'] ?? null);
        $this->assertSame('/auto-retro', $viewModel['primary'][0]['mega']['featuredCard']['href'] ?? null);
        $this->assertTrue($viewModel['primary'][0]['mega']['featuredCard']['active'] ?? false);
        $this->assertSame('Austin', $viewModel['primary'][0]['mega']['sections'][0]['label'] ?? null);
        $this->assertTrue($viewModel['primary'][0]['mega']['sections'][0]['itemColumns'][0][0]['presentation']['isHighlight'] ?? false);
    }

    public function testBuildMegaMenuFlattensUnlabeledGroupsAndDistributesAcrossColumns(): void
    {
        $builder = new NavigationViewModelBuilder(new PageRepository($this->pagesFile), ['fr', 'de']);
        $viewModel = $builder->build(
            [
                'meta' => ['version' => 2],
                'locations' => [
                    'primary' => [
                        [
                            'id' => 'primary-auto',
                            'kind' => 'group',
                            'label' => ['text' => 'Auto-Retro'],
                            'target' => ['pageSlug' => null, 'route' => null, 'url' => null],
                            'media' => [],
                            'content' => [],
                            'accessibility' => [],
                            'presentation' => [
                                'displayMode' => 'mega',
                                'columnCount' => 4,
                            ],
                            'children' => [
                                [
                                    'id' => 'primary-unlabeled',
                                    'kind' => 'group',
                                    'label' => ['text' => ''],
                                    'target' => ['pageSlug' => null, 'route' => null, 'url' => null],
                                    'media' => [],
                                    'content' => [],
                                    'accessibility' => [],
                                    'children' => [
                                        [
                                            'id' => 'primary-link-1',
                                            'kind' => 'route',
                                            'label' => ['text' => 'Lien 1'],
                                            'target' => ['pageSlug' => null, 'route' => '/lien-1', 'url' => null],
                                            'children' => [],
                                        ],
                                        [
                                            'id' => 'primary-link-2',
                                            'kind' => 'route',
                                            'label' => ['text' => 'Lien 2'],
                                            'target' => ['pageSlug' => null, 'route' => '/lien-2', 'url' => null],
                                            'children' => [],
                                        ],
                                        [
                                            'id' => 'primary-link-3',
                                            'kind' => 'route',
                                            'label' => ['text' => 'Lien 3'],
                                            'target' => ['pageSlug' => null, 'route' => '/lien-3', 'url' => null],
                                            'children' => [],
                                        ],
                                        [
                                            'id' => 'primary-link-4',
                                            'kind' => 'route',
                                            'label' => ['text' => 'Lien 4'],
                                            'target' => ['pageSlug' => null, 'route' => '/lien-4', 'url' => null],
                                            'children' => [],
                                        ],
                                        [
                                            'id' => 'primary-link-5',
                                            'kind' => 'route',
                                            'label' => ['text' => 'Lien 5'],
                                            'target' => ['pageSlug' => null, 'route' => '/lien-5', 'url' => null],
                                            'children' => [],
                                        ],
                                        [
                                            'id' => 'primary-link-6',
                                            'kind' => 'route',
                                            'label' => ['text' => 'Lien 6'],
                                            'target' => ['pageSlug' => null, 'route' => '/lien-6', 'url' => null],
                                            'children' => [],
                                        ],
                                        [
                                            'id' => 'primary-link-7',
                                            'kind' => 'route',
                                            'label' => ['text' => 'Lien 7'],
                                            'target' => ['pageSlug' => null, 'route' => '/lien-7', 'url' => null],
                                            'children' => [],
                                        ],
                                        [
                                            'id' => 'primary-link-8',
                                            'kind' => 'route',
                                            'label' => ['text' => 'Lien 8'],
                                            'target' => ['pageSlug' => null, 'route' => '/lien-8', 'url' => null],
                                            'children' => [],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'fr',
            '/'
        );

        $mega = $viewModel['primary'][0]['mega'] ?? [];
        $this->assertSame(4, $mega['columnCount'] ?? null);
        $this->assertCount(8, $mega['sections'] ?? []);
        $this->assertSame('Lien 1', $mega['sections'][0]['itemColumns'][0][0]['rawLabel'] ?? null);
        $this->assertSame('Lien 2', $mega['sections'][1]['itemColumns'][0][0]['rawLabel'] ?? null);
        $this->assertSame('Lien 5', $mega['sections'][4]['itemColumns'][0][0]['rawLabel'] ?? null);
        $this->assertSame(1, $mega['sections'][0]['columnSpan'] ?? null);
    }

    public function testBuildMegaMenuSplitsSingleSectionAfterFiveItems(): void
    {
        $builder = new NavigationViewModelBuilder(new PageRepository($this->pagesFile), ['fr', 'de']);
        $viewModel = $builder->build(
            [
                'meta' => ['version' => 2],
                'locations' => [
                    'primary' => [
                        [
                            'id' => 'primary-auto',
                            'kind' => 'group',
                            'label' => ['text' => 'Auto-Retro'],
                            'target' => ['pageSlug' => null, 'route' => null, 'url' => null],
                            'media' => [],
                            'content' => [],
                            'accessibility' => [],
                            'presentation' => [
                                'displayMode' => 'mega',
                                'columnCount' => 3,
                            ],
                            'children' => [
                                [
                                    'id' => 'primary-austin',
                                    'kind' => 'group',
                                    'label' => [
                                        'text' => 'Austin',
                                        'defaultLanguage' => 'fr',
                                        'translations' => [
                                            'fr' => 'Austin',
                                        ],
                                    ],
                                    'target' => ['pageSlug' => null, 'route' => null, 'url' => null],
                                    'media' => [],
                                    'content' => [],
                                    'accessibility' => [],
                                    'children' => [
                                        [
                                            'id' => 'primary-austin-1',
                                            'kind' => 'route',
                                            'label' => ['text' => 'Lien A1'],
                                            'target' => ['pageSlug' => null, 'route' => '/a1', 'url' => null],
                                            'children' => [],
                                        ],
                                        [
                                            'id' => 'primary-austin-2',
                                            'kind' => 'route',
                                            'label' => ['text' => 'Lien A2'],
                                            'target' => ['pageSlug' => null, 'route' => '/a2', 'url' => null],
                                            'children' => [],
                                        ],
                                        [
                                            'id' => 'primary-austin-3',
                                            'kind' => 'route',
                                            'label' => ['text' => 'Lien A3'],
                                            'target' => ['pageSlug' => null, 'route' => '/a3', 'url' => null],
                                            'children' => [],
                                        ],
                                        [
                                            'id' => 'primary-austin-4',
                                            'kind' => 'route',
                                            'label' => ['text' => 'Lien A4'],
                                            'target' => ['pageSlug' => null, 'route' => '/a4', 'url' => null],
                                            'children' => [],
                                        ],
                                        [
                                            'id' => 'primary-austin-5',
                                            'kind' => 'route',
                                            'label' => ['text' => 'Lien A5'],
                                            'target' => ['pageSlug' => null, 'route' => '/a5', 'url' => null],
                                            'children' => [],
                                        ],
                                        [
                                            'id' => 'primary-austin-6',
                                            'kind' => 'route',
                                            'label' => ['text' => 'Lien A6'],
                                            'target' => ['pageSlug' => null, 'route' => '/a6', 'url' => null],
                                            'children' => [],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'fr',
            '/'
        );

        $mega = $viewModel['primary'][0]['mega'] ?? [];
        $this->assertSame(3, $mega['columnCount'] ?? null);
        $this->assertCount(1, $mega['sections'] ?? []);
        $this->assertSame('Austin', $mega['sections'][0]['label'] ?? null);
        $this->assertSame(2, $mega['sections'][0]['columnSpan'] ?? null);
        $this->assertCount(2, $mega['sections'][0]['itemColumns'] ?? []);
        $this->assertCount(5, $mega['sections'][0]['itemColumns'][0] ?? []);
        $this->assertSame('Lien A1', $mega['sections'][0]['itemColumns'][0][0]['rawLabel'] ?? null);
        $this->assertSame('Lien A6', $mega['sections'][0]['itemColumns'][1][0]['rawLabel'] ?? null);
    }

    public function testBuildUsesLanguageSpecificLabelsForBrandAndLanguageSwitcher(): void
    {
        $builder = new NavigationViewModelBuilder(new PageRepository($this->pagesFile), ['fr', 'de', 'en']);
        $viewModel = $builder->build(
            [
                'meta' => ['version' => 2],
                'locations' => [],
            ],
            'en',
            '/en/blog'
        );

        $this->assertSame('LesCaramagnols', $viewModel['brand']['label'] ?? null);
        $this->assertSame('French', $viewModel['languages'][0]['label'] ?? null);
        $this->assertSame('German', $viewModel['languages'][1]['label'] ?? null);
        $this->assertSame('English', $viewModel['languages'][2]['label'] ?? null);
    }

    public function testBuildResolvesMenuItemLabelTranslationsWithFallback(): void
    {
        $builder = new NavigationViewModelBuilder(new PageRepository($this->pagesFile), ['fr', 'de', 'en']);

        $viewModelDe = $builder->build(
            [
                'meta' => ['version' => 2],
                'locations' => [
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
                                    'en' => 'Home',
                                ],
                            ],
                            'target' => ['pageSlug' => null, 'route' => '/accueil', 'url' => null],
                            'media' => [],
                            'content' => [],
                            'accessibility' => [],
                            'children' => [],
                        ],
                    ],
                ],
            ],
            'de',
            '/accueil'
        );

        $viewModelEn = $builder->build(
            [
                'meta' => ['version' => 2],
                'locations' => [
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
                                    'en' => 'Home',
                                ],
                            ],
                            'target' => ['pageSlug' => null, 'route' => '/accueil', 'url' => null],
                            'media' => [],
                            'content' => [],
                            'accessibility' => [],
                            'children' => [],
                        ],
                    ],
                ],
            ],
            'en',
            '/accueil'
        );

        $this->assertSame('Accueil', $viewModelDe['primary'][0]['label'] ?? null);
        $this->assertSame('Home', $viewModelEn['primary'][0]['label'] ?? null);
    }

    public function testBuildHidesPlainMenuLabelTextOnFrontUntilTranslationsExist(): void
    {
        $builder = new NavigationViewModelBuilder(new PageRepository($this->pagesFile), ['fr', 'de']);
        $viewModel = $builder->build(
            [
                'meta' => ['version' => 2],
                'locations' => [
                    'primary' => [
                        [
                            'id' => 'primary-auto',
                            'kind' => 'route',
                            'label' => [
                                'text' => 'Nom interne item',
                                'translationKey' => null,
                            ],
                            'target' => ['pageSlug' => null, 'route' => '/auto-retro', 'url' => null],
                            'children' => [],
                        ],
                        [
                            'id' => 'primary-bouger',
                            'kind' => 'route',
                            'label' => [
                                'text' => 'Nom interne item 2',
                                'defaultLanguage' => 'fr',
                                'translations' => [
                                    'fr' => 'Bouger',
                                ],
                            ],
                            'target' => ['pageSlug' => null, 'route' => '/bouger', 'url' => null],
                            'children' => [],
                        ],
                    ],
                ],
            ],
            'fr',
            '/'
        );

        $this->assertNull($viewModel['primary'][0]['label'] ?? null);
        $this->assertSame('Nom interne item', $viewModel['primary'][0]['rawLabel'] ?? null);
        $this->assertSame('Bouger', $viewModel['primary'][1]['label'] ?? null);
        $this->assertSame('Nom interne item 2', $viewModel['primary'][1]['rawLabel'] ?? null);
    }

    public function testBuildResolvesFooterNoticeWithLanguageFallbackToFrench(): void
    {
        $builder = new NavigationViewModelBuilder(new PageRepository($this->pagesFile), ['fr', 'de', 'en']);

        $viewModelFallback = $builder->build(
            [
                'meta' => ['version' => 2],
                'locations' => [
                    'footerNotice' => [
                        'defaultLanguage' => 'fr',
                        'translationKey' => 'TXT_PiedPageModele',
                        'translations' => [
                            'fr' => 'Texte FR pied de page',
                        ],
                    ],
                ],
            ],
            'de',
            '/'
        );

        $this->assertSame('Texte FR pied de page', $viewModelFallback['footerNotice']['text'] ?? null);

        $viewModelLocalized = $builder->build(
            [
                'meta' => ['version' => 2],
                'locations' => [
                    'footerNotice' => [
                        'defaultLanguage' => 'fr',
                        'translationKey' => 'TXT_PiedPageModele',
                        'translations' => [
                            'fr' => 'Texte FR pied de page',
                            'de' => 'Text DE Fusszeile',
                        ],
                    ],
                ],
            ],
            'de',
            '/'
        );

        $this->assertSame('Text DE Fusszeile', $viewModelLocalized['footerNotice']['text'] ?? null);
    }
}
