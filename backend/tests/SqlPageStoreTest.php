<?php

declare(strict_types=1);

use Caramagnols\Content\SqlPageStore;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class SqlPageStoreTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testReplaceRegistryAndReadBackPagesFromSql(): void
    {
        $store = new SqlPageStore($this->editorialSqlDatabase());

        $saved = $store->replaceRegistry([
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
                                'footer' => [
                                    'component' => 'rich_text',
                                    'html' => '<div class="aligncenter"><em>Footer SQL</em></div>',
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'slug' => 'balade',
                    'type' => 'structured_page',
                    'status' => 'draft',
                    'route' => '/balade',
                    'translations' => [
                        'fr' => [
                            'title' => 'Balade',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($saved);
        $this->assertCount(2, $store->all());

        $dynamic = $store->findPublishedStructuredBySlug('association', 'fr');

        $this->assertNotNull($dynamic);
        $this->assertSame('/association', $dynamic['route']);
        $this->assertStringContainsString('<h1>Association</h1>', $dynamic['blocks']['EditRegion1']);
        $this->assertStringContainsString('Footer SQL', $dynamic['blocks']['EditRegion9']);

        $draft = $store->findBySlug('balade');
        $this->assertNotNull($draft);
        $this->assertSame('draft', $draft['status']);
    }

    public function testSavePageRejectsUnknownSemanticSection(): void
    {
        $store = new SqlPageStore($this->editorialSqlDatabase());

        $saved = $store->savePage([
            'slug' => 'page-invalide',
            'type' => 'structured_page',
            'status' => 'published',
            'translations' => [
                'fr' => [
                    'title' => 'Page invalide',
                    'regions' => [
                        'zone-inconnue' => [
                            'component' => 'rich_text',
                            'html' => '<p>Erreur</p>',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertFalse($saved);
        $this->assertSame([], $store->all());
    }

    public function testDeletePageRemovesRowAndTranslationTables(): void
    {
        $store = new SqlPageStore($this->editorialSqlDatabase());

        $this->assertTrue($store->savePage([
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
                'de' => [
                    'title' => 'Verein',
                ],
            ],
        ]));

        $this->assertTrue($store->deletePage('association'));
        $this->assertNull($store->findBySlug('association'));
        $this->assertSame([], $store->all());

        $pdo = $this->editorialSqlDatabase()->pdo();
        $pagesCount = (int) $pdo->query(sprintf('SELECT COUNT(*) FROM `%s`', $this->editorialSqlDatabase()->table('pages')))->fetchColumn();
        $translationsCount = (int) $pdo->query(sprintf('SELECT COUNT(*) FROM `%s`', $this->editorialSqlDatabase()->table('page_translations')))->fetchColumn();
        $sectionsCount = (int) $pdo->query(sprintf('SELECT COUNT(*) FROM `%s`', $this->editorialSqlDatabase()->table('page_translation_sections')))->fetchColumn();

        $this->assertSame(0, $pagesCount);
        $this->assertSame(0, $translationsCount);
        $this->assertSame(0, $sectionsCount);
    }
}
