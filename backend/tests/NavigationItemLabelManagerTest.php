<?php

declare(strict_types=1);

use Caramagnols\Admin\Navigation\NavigationItemLabelManager;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class NavigationItemLabelManagerTest extends TestCase
{
    public function testNormalizeFromPostPersistsPerLanguageTranslations(): void
    {
        $manager = new NavigationItemLabelManager('fr', ['fr', 'de', 'en']);

        $normalized = $manager->normalizeFromPost(
            'Auto-Retro',
            '',
            [
                'fr' => 'Auto-Retro',
                'de' => 'Auto-Retro DE',
                'en' => 'Classic Cars',
            ],
            'fr'
        );

        $this->assertSame('Auto-Retro', $normalized['text'] ?? null);
        $this->assertNull($normalized['translationKey'] ?? null);
        $this->assertSame('fr', $normalized['defaultLanguage'] ?? null);
        $this->assertSame('Auto-Retro DE', $normalized['translations']['de'] ?? null);
        $this->assertSame('Classic Cars', $normalized['translations']['en'] ?? null);
    }

    public function testLabelToStringUsesPreferredLanguageThenDefault(): void
    {
        $manager = new NavigationItemLabelManager('fr', ['fr', 'de', 'en']);

        $label = [
            'text' => null,
            'translationKey' => null,
            'defaultLanguage' => 'fr',
            'translations' => [
                'fr' => 'Accueil',
                'en' => 'Home',
            ],
        ];

        $asEnglish = $manager->labelToString(
            $label,
            'en',
            static fn (string $key): ?string => $key
        );
        $asGerman = $manager->labelToString(
            $label,
            'de',
            static fn (string $key): ?string => $key
        );

        $this->assertSame('Home', $asEnglish);
        $this->assertSame('Accueil', $asGerman);
    }
}

