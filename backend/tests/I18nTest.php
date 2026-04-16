<?php

declare(strict_types=1);

use Caramagnols\Http\Request;
use Caramagnols\I18n\LanguageResolver;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class I18nTest extends TestCase
{
    protected function tearDown(): void
    {
        global $appConfig;
        if (is_array($appConfig)) {
            unset($appConfig['site']['i18n_overrides']);
        }
    }

    public function testResolveLangFromQuery(): void
    {
        $resolver = new LanguageResolver();
        $request = new Request([], ['lang' => 'en'], [], [], [], []);

        $this->assertSame('en', $resolver->resolve($request));
    }

    public function testFallbackToDefault(): void
    {
        $resolver = new LanguageResolver();
        $request = new Request([], ['lang' => 'es'], [], [], [], []);

        $this->assertSame('fr', $resolver->resolve($request));
    }

    public function testLoadTranslationsAppliesConfiguredOverrides(): void
    {
        global $appConfig;
        $appConfig['site']['i18n_overrides'] = [
            'fr' => [
                'TXT_BANNIERE' => 'BANNIERE TEST OVERRIDE',
            ],
        ];

        $translations = load_translations_cached('fr');

        $this->assertSame('BANNIERE TEST OVERRIDE', $translations['TXT_BANNIERE'] ?? null);
    }
}
