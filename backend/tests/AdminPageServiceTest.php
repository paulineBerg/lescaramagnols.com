<?php

declare(strict_types=1);

use Caramagnols\Admin\AdminPageService;
use Caramagnols\Content\PageRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class AdminPageServiceTest extends TestCase
{
    private string $pagesFile;

    protected function setUp(): void
    {
        $this->pagesFile = ROOT_PATH . '/var/admin-page-service-' . uniqid('', true) . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->pagesFile)) {
            @unlink($this->pagesFile);
        }
    }

    public function testSavePersistsSeoImageMetadataInsideTranslationMeta(): void
    {
        $repository = new PageRepository($this->pagesFile);
        $service = new AdminPageService($repository, ['fr', 'en', 'de'], 'fr');

        $result = $service->save([
            'slug' => 'actualites',
            'status' => 'published',
            'route' => '/actualites',
            'layout' => 'standard_page',
            'translations' => [
                'fr' => [
                    'title' => 'Actualites',
                    'meta_description' => 'Dernieres actualites',
                    'meta_image_src' => '/uploads/editorial/page/2026/03/actualites.jpg',
                    'meta_image_alt' => 'Bandeau actualites',
                    'meta_image_title' => 'Visuel actualites',
                    'meta_image_width' => '1280',
                    'meta_image_height' => '720',
                    'regions' => [
                        'hero_html' => '<p>Contenu hero</p>',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($result['success']);

        $saved = $repository->findBySlug('actualites');
        $this->assertIsArray($saved);
        $translation = is_array($saved['translations']['fr'] ?? null) ? $saved['translations']['fr'] : null;
        $this->assertIsArray($translation);
        $meta = is_array($translation['meta'] ?? null) ? $translation['meta'] : null;
        $this->assertIsArray($meta);
        $this->assertSame('/uploads/editorial/page/2026/03/actualites.jpg', $meta['image']['src'] ?? null);
        $this->assertSame('Bandeau actualites', $meta['image']['alt'] ?? null);
        $this->assertSame(1280, $meta['image']['width'] ?? null);
        $this->assertSame(720, $meta['image']['height'] ?? null);
    }

    public function testSavePersistsSharedMediaAtRootMetaOnly(): void
    {
        $repository = new PageRepository($this->pagesFile);
        $service = new AdminPageService($repository, ['fr', 'en', 'de'], 'fr');

        $result = $service->save([
            'slug' => 'galerie',
            'status' => 'published',
            'route' => '/galerie',
            'layout' => 'standard_page',
            'shared_media' => [
                [
                    'src' => '/uploads/editorial/media/2026/03/galerie-01.webp',
                    'alt' => 'Simca au soleil',
                    'title' => 'Sortie club',
                    'caption' => 'Printemps 2026',
                    'width' => '1600',
                    'height' => '900',
                ],
                [
                    'src' => '/uploads/editorial/media/2026/03/galerie-02.webp',
                    'alt' => 'Rassemblement',
                    'title' => '',
                    'caption' => '',
                    'width' => '1200',
                    'height' => '800',
                ],
            ],
            'translations' => [
                'fr' => [
                    'title' => 'Galerie',
                    'regions' => [
                        'hero_html' => '<h1>Galerie club</h1>',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($result['success']);

        $saved = $repository->findBySlug('galerie');
        $this->assertIsArray($saved);
        $rootMeta = is_array($saved['meta'] ?? null) ? $saved['meta'] : null;
        $this->assertIsArray($rootMeta);
        $this->assertCount(2, $rootMeta['shared_media'] ?? []);
        $this->assertSame('/uploads/editorial/media/2026/03/galerie-01.webp', $rootMeta['shared_media'][0]['src'] ?? null);
        $this->assertSame(1600, $rootMeta['shared_media'][0]['width'] ?? null);
        $this->assertSame(900, $rootMeta['shared_media'][0]['height'] ?? null);

        $translationMeta = $saved['translations']['fr']['meta'] ?? [];
        $this->assertIsArray($translationMeta);
        $this->assertArrayNotHasKey('shared_media', $translationMeta);
    }
}
