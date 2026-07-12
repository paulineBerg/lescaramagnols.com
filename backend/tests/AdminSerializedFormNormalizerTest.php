<?php

declare(strict_types=1);

use Caramagnols\Admin\AdminSerializedFormNormalizer;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class AdminSerializedFormNormalizerTest extends TestCase
{
    public function testMenuBuilderDecodesSerializedStateAndPreservesControlFields(): void
    {
        $normalizer = new AdminSerializedFormNormalizer();

        $normalized = $normalizer->menuBuilder([
            'builder_state_json' => json_encode(['active_location' => 'primary', 'locations' => ['primary' => []]]),
            'builder_action' => 'save',
            'csrf_token' => 'token-123',
        ]);

        $this->assertSame('primary', $normalized['active_location']);
        $this->assertSame('save', $normalized['builder_action']);
        $this->assertSame('token-123', $normalized['csrf_token']);
    }

    public function testPageEditorReturnsRawBodyWhenSerializedStateIsInvalid(): void
    {
        $normalizer = new AdminSerializedFormNormalizer();
        $body = [
            'page_state_json' => '{invalid',
            'slug' => 'association',
            'csrf_token' => 'token',
        ];

        $this->assertSame($body, $normalizer->pageEditor($body));
    }

    public function testMenuBuilderMergesMissingPayloadKeysFromRawBody(): void
    {
        $normalizer = new AdminSerializedFormNormalizer();

        $normalized = $normalizer->menuBuilder([
            'builder_state_json' => json_encode([
                'active_location' => 'footer',
                'locations' => ['footer' => []],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'builder_action' => 'save',
            'csrf_token' => 'token-footer',
            'footer_notice' => [
                'default_language' => 'fr',
                'translation_key' => 'TXT_PiedPageModele',
                'translations' => [
                    'fr' => 'Texte pied de page',
                ],
            ],
        ]);

        $this->assertSame('save', $normalized['builder_action']);
        $this->assertSame('token-footer', $normalized['csrf_token']);
        $this->assertSame('fr', $normalized['footer_notice']['default_language'] ?? null);
        $this->assertSame('TXT_PiedPageModele', $normalized['footer_notice']['translation_key'] ?? null);
        $this->assertSame('Texte pied de page', $normalized['footer_notice']['translations']['fr'] ?? null);
    }

    public function testMenuBuilderMergesSystemModalFieldsWhenMissingFromSerializedState(): void
    {
        $normalizer = new AdminSerializedFormNormalizer();

        $normalized = $normalizer->menuBuilder([
            'builder_state_json' => json_encode([
                'active_location' => 'primary',
                'selected_item' => 'primary|0',
                'locations' => [
                    'utility' => [],
                    'primary' => [],
                    'footer' => [],
                    'sideRight' => [],
                    'sideLeft' => [],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'builder_action' => 'save',
            'csrf_token' => 'token-system-fields',
            'banner' => [
                'image' => '/assets/images/structure/banniere.jpg',
                'headline' => 'Titre banniere',
                'headline_translation_key' => 'TXT_BANNIERE',
                'alt' => 'Alt banniere',
                'title' => 'Titre banniere',
            ],
            'remonter' => [
                'label' => 'Remonter',
                'label_translation_key' => 'REMONTER_TOP',
                'alt' => 'Remonter',
                'title' => 'Remonter',
            ],
            'footer_notice' => [
                'default_language' => 'fr',
                'translation_key' => 'TXT_PiedPageModele',
                'translations' => [
                    'fr' => 'Texte pied de page',
                ],
            ],
        ]);

        $this->assertSame('save', $normalized['builder_action'] ?? null);
        $this->assertSame('token-system-fields', $normalized['csrf_token'] ?? null);
        $this->assertSame('/assets/images/structure/banniere.jpg', $normalized['banner']['image'] ?? null);
        $this->assertSame('TXT_BANNIERE', $normalized['banner']['headline_translation_key'] ?? null);
        $this->assertSame('REMONTER_TOP', $normalized['remonter']['label_translation_key'] ?? null);
        $this->assertSame('TXT_PiedPageModele', $normalized['footer_notice']['translation_key'] ?? null);
        $this->assertSame('Texte pied de page', $normalized['footer_notice']['translations']['fr'] ?? null);
    }
}
