<?php

declare(strict_types=1);

use Caramagnols\Admin\Settings\AdminTranslationSettingsManager;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class AdminTranslationSettingsManagerTest extends TestCase
{
    public function testConfiguredBuildsTextareaValuesByLanguage(): void
    {
        $manager = $this->manager();

        $configured = $manager->configured(
            [
                'fr' => ['MENU_ACCUEIL' => 'Accueil'],
                'en' => ['MENU_ACCUEIL' => 'Home'],
            ],
            ['fr', 'en']
        );

        $this->assertSame(['fr', 'en'], $configured['languages']);
        $this->assertStringContainsString('MENU_ACCUEIL=Accueil', $configured['textByLanguage']['fr']);
        $this->assertStringContainsString('MENU_ACCUEIL=Home', $configured['textByLanguage']['en']);
    }

    public function testNormalizeConfigRejectsUnknownKey(): void
    {
        $manager = $this->manager();

        $normalized = $manager->normalizeConfig([
            'languages' => ['fr'],
            'textByLanguage' => [
                'fr' => 'UNKNOWN_KEY=Valeur',
            ],
        ]);

        $this->assertNotNull($normalized['error']);
        $this->assertSame([], $normalized['data']);
    }

    public function testNormalizeConfigAcceptsKnownKey(): void
    {
        $manager = $this->manager();

        $normalized = $manager->normalizeConfig([
            'languages' => ['fr'],
            'textByLanguage' => [
                'fr' => 'MENU_ACCUEIL=Accueil',
            ],
        ]);

        $this->assertNull($normalized['error']);
        $this->assertSame('Accueil', $normalized['data']['fr']['MENU_ACCUEIL']);
    }

    public function testConfiguredKeepsDefaultLanguageFirstEvenWhenMissingFromSubmittedOrder(): void
    {
        $manager = $this->manager();

        $configured = $manager->configured(
            [
                'fr' => ['MENU_ACCUEIL' => 'Accueil'],
                'en' => ['MENU_ACCUEIL' => 'Home'],
            ],
            ['en', 'de']
        );

        $this->assertSame(['fr', 'en', 'de'], $configured['languages']);
        $this->assertSame('', $configured['textByLanguage']['de']);
    }

    public function testDictionaryEntriesForLanguageUsesRequestedLanguageThenFallsBackToDefault(): void
    {
        $manager = new AdminTranslationSettingsManager(
            'fr',
            static function (string $language): array {
                if ($language === 'fr') {
                    return [
                        'MENU_ACCUEIL' => 'Accueil',
                        'MENU_RECHERCHE' => 'Recherche',
                    ];
                }

                if ($language === 'en') {
                    return [
                        'MENU_ACCUEIL' => 'Home',
                        'MENU_RECHERCHE' => 'Search',
                    ];
                }

                return [];
            }
        );

        $englishEntries = $manager->dictionaryEntriesForLanguage('en');
        $germanEntries = $manager->dictionaryEntriesForLanguage('de');

        $this->assertSame('Home', $englishEntries['MENU_ACCUEIL'] ?? null);
        $this->assertSame('Search', $englishEntries['MENU_RECHERCHE'] ?? null);
        $this->assertSame('Accueil', $germanEntries['MENU_ACCUEIL'] ?? null);
        $this->assertSame('Recherche', $germanEntries['MENU_RECHERCHE'] ?? null);
    }

    private function manager(): AdminTranslationSettingsManager
    {
        return new AdminTranslationSettingsManager(
            'fr',
            static function (string $language): array {
                if ($language === 'fr') {
                    return [
                        'MENU_ACCUEIL' => 'Accueil',
                        'MENU_RECHERCHE' => 'Recherche',
                    ];
                }

                return [
                    'MENU_ACCUEIL' => 'Home',
                    'MENU_RECHERCHE' => 'Search',
                ];
            }
        );
    }
}
