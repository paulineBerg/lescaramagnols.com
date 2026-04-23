<?php

declare(strict_types=1);

use Caramagnols\Admin\AdminTileService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class AdminTileServiceTest extends TestCase
{
    public function testBuildDuplicatedFormDataKeepsAllTileDetailsAndAppendsCopySuffix(): void
    {
        $service = $this->serviceForTesting();

        $duplicatedForm = $this->invokePrivateMethod(
            $service,
            'buildDuplicatedFormData',
            [[
                'id' => 12,
                'name' => 'Austin hero',
                'theme' => 'windows10-classic',
                'items' => [
                    [
                        'id' => 101,
                        'item_uid' => 'mini-mayfair',
                        'sort_order' => 10,
                        'tile_size' => 'rectangle',
                        'color_token' => 'vertfonce',
                        'image_src' => '/assets/images/autoretro/austin/mini_mayfair.jpg',
                        'image_width' => 222,
                        'image_height' => 90,
                        'target' => [
                            'type' => 'page',
                            'pageSlug' => 'site-auto-retro-austin-une-mini-dans-le-golfe-de-sttropez',
                            'route' => '',
                            'url' => '',
                        ],
                        'open_in_new_tab' => false,
                        'translations' => [
                            'fr' => [
                                'label' => 'La Mini Mayfair',
                                'alt' => 'Mini Mayfair verte',
                                'title' => 'Notre Mini Mayfair',
                            ],
                            'en' => [
                                'label' => 'The Mini Mayfair',
                                'alt' => 'Green Mini Mayfair',
                                'title' => 'Our Mini Mayfair',
                            ],
                        ],
                    ],
                    [
                        'id' => 102,
                        'item_uid' => 'club-link',
                        'sort_order' => 20,
                        'tile_size' => 'medium',
                        'color_token' => 'bleufonce',
                        'image_src' => '',
                        'image_width' => null,
                        'image_height' => null,
                        'target' => [
                            'type' => 'external',
                            'pageSlug' => '',
                            'route' => '',
                            'url' => 'https://example.com/club',
                        ],
                        'open_in_new_tab' => true,
                        'translations' => [
                            'fr' => [
                                'label' => 'Le club',
                                'alt' => 'Lien vers le club',
                                'title' => 'Visiter le club',
                            ],
                            'de' => [
                                'label' => 'Der Club',
                                'alt' => 'Link zum Club',
                                'title' => 'Zum Club',
                            ],
                        ],
                    ],
                ],
            ]]
        );

        $this->assertSame('', $duplicatedForm['id']);
        $this->assertSame('Austin hero - copie', $duplicatedForm['name']);
        $this->assertSame('windows10-classic', $duplicatedForm['theme']);
        $this->assertCount(2, $duplicatedForm['items']);

        $this->assertSame('', $duplicatedForm['items'][0]['item_uid']);
        $this->assertSame('10', $duplicatedForm['items'][0]['sort_order']);
        $this->assertSame('rectangle', $duplicatedForm['items'][0]['tile_size']);
        $this->assertSame('vertfonce', $duplicatedForm['items'][0]['color_token']);
        $this->assertSame('/assets/images/autoretro/austin/mini_mayfair.jpg', $duplicatedForm['items'][0]['image_src']);
        $this->assertSame('222', $duplicatedForm['items'][0]['image_width']);
        $this->assertSame('90', $duplicatedForm['items'][0]['image_height']);
        $this->assertSame('page', $duplicatedForm['items'][0]['target_type']);
        $this->assertSame('site-auto-retro-austin-une-mini-dans-le-golfe-de-sttropez', $duplicatedForm['items'][0]['target_page_slug']);
        $this->assertSame('', $duplicatedForm['items'][0]['target_route']);
        $this->assertSame('', $duplicatedForm['items'][0]['target_url']);
        $this->assertSame('', $duplicatedForm['items'][0]['open_in_new_tab']);
        $this->assertSame(
            [
                'fr' => [
                    'label' => 'La Mini Mayfair',
                    'alt' => 'Mini Mayfair verte',
                    'title' => 'Notre Mini Mayfair',
                ],
                'en' => [
                    'label' => 'The Mini Mayfair',
                    'alt' => 'Green Mini Mayfair',
                    'title' => 'Our Mini Mayfair',
                ],
                'de' => [
                    'label' => '',
                    'alt' => '',
                    'title' => '',
                ],
            ],
            $duplicatedForm['items'][0]['translations']
        );

        $this->assertSame('', $duplicatedForm['items'][1]['item_uid']);
        $this->assertSame('20', $duplicatedForm['items'][1]['sort_order']);
        $this->assertSame('medium', $duplicatedForm['items'][1]['tile_size']);
        $this->assertSame('bleufonce', $duplicatedForm['items'][1]['color_token']);
        $this->assertSame('', $duplicatedForm['items'][1]['image_src']);
        $this->assertSame('', $duplicatedForm['items'][1]['image_width']);
        $this->assertSame('', $duplicatedForm['items'][1]['image_height']);
        $this->assertSame('external', $duplicatedForm['items'][1]['target_type']);
        $this->assertSame('', $duplicatedForm['items'][1]['target_page_slug']);
        $this->assertSame('', $duplicatedForm['items'][1]['target_route']);
        $this->assertSame('https://example.com/club', $duplicatedForm['items'][1]['target_url']);
        $this->assertSame('1', $duplicatedForm['items'][1]['open_in_new_tab']);
        $this->assertSame(
            [
                'fr' => [
                    'label' => 'Le club',
                    'alt' => 'Lien vers le club',
                    'title' => 'Visiter le club',
                ],
                'en' => [
                    'label' => '',
                    'alt' => '',
                    'title' => '',
                ],
                'de' => [
                    'label' => 'Der Club',
                    'alt' => 'Link zum Club',
                    'title' => 'Zum Club',
                ],
            ],
            $duplicatedForm['items'][1]['translations']
        );
    }

    public function testDuplicateGroupNameUsesDashCopySuffixAndIncrements(): void
    {
        $service = $this->serviceForTesting();

        $this->assertSame('Austin hero - copie', $this->invokePrivateMethod($service, 'duplicateGroupName', ['Austin hero']));
        $this->assertSame('Austin hero - copie 2', $this->invokePrivateMethod($service, 'duplicateGroupName', ['Austin hero - copie']));
        $this->assertSame('Austin hero - copie 3', $this->invokePrivateMethod($service, 'duplicateGroupName', ['Austin hero - copie 2']));
        $this->assertSame('Austin hero - copie 2', $this->invokePrivateMethod($service, 'duplicateGroupName', ['Austin hero (copie)']));
    }

    private function serviceForTesting(): AdminTileService
    {
        $reflection = new ReflectionClass(AdminTileService::class);
        $service = $reflection->newInstanceWithoutConstructor();

        $this->setPrivateProperty($service, 'availableLanguages', ['fr', 'en', 'de']);
        $this->setPrivateProperty($service, 'defaultLanguage', 'fr');

        return $service;
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function invokePrivateMethod(object $object, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }

    private function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty($object, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($object, $value);
    }
}
