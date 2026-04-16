<?php

declare(strict_types=1);

use Caramagnols\Content\PageRepository;
use Caramagnols\Content\SqlPageStore;
use Caramagnols\Content\StructuredPageRenderer;
use Caramagnols\Editorial\EditorialImportService;
use Caramagnols\Navigation\NavigationNormalizer;
use Caramagnols\Navigation\NavigationRepository;
use Caramagnols\Navigation\SqlNavigationStore;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class EditorialImportServiceTest extends TestCase
{
    use EditorialSqlTestTrait;

    private string $pagesFile;
    private string $menusFile;

    protected function setUp(): void
    {
        $this->pagesFile = ROOT_PATH . '/var/editorial-import-pages-' . uniqid() . '.json';
        $this->menusFile = ROOT_PATH . '/var/editorial-import-menus-' . uniqid() . '.json';

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
                                'fr' => [
                                    'title' => 'Association',
                                    'regions' => [
                                        'hero' => [
                                            'component' => 'heading',
                                            'title' => 'Association',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );
    }

    protected function tearDown(): void
    {
        foreach ([$this->pagesFile, $this->pagesFile . '.bak', $this->menusFile, $this->menusFile . '.bak'] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        $this->cleanupEditorialSqlDatabase();
    }

    public function testImportCopiesPagesAndNavigationToSql(): void
    {
        $service = new EditorialImportService(
            new SqlPageStore($this->editorialSqlDatabase()),
            new SqlNavigationStore($this->editorialSqlDatabase())
        );

        $result = $service->import(
            new PageRepository($this->pagesFile, new StructuredPageRenderer(), 'json'),
            new NavigationRepository($this->menusFile, 'json'),
            [
                'menu2' => [
                    ['titre' => 'Accueil', 'chemin' => '/accueil'],
                ],
                'menuDroit' => [
                    ['titre' => 'Carte', 'chemin' => '/carte', 'texte' => 'Bloc latéral'],
                ],
            ]
        );

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['pages']);
        $this->assertSame(count(NavigationNormalizer::locationKeys()), $result['menu_locations']);

        $pageStore = new SqlPageStore($this->editorialSqlDatabase());
        $navigationStore = new SqlNavigationStore($this->editorialSqlDatabase());

        $this->assertNotNull($pageStore->findBySlug('association'));
        $this->assertSame('Accueil', $navigationStore->loadCanonical()['locations']['primary'][0]['label']['text']);
        $this->assertSame('Carte', $navigationStore->loadCanonical()['locations']['sideRight'][0]['label']['text']);
    }
}
