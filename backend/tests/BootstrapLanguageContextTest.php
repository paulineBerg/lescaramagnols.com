<?php

declare(strict_types=1);

use Caramagnols\Http\Request;
use PHPUnit\Framework\TestCase;

final class BootstrapLanguageContextTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testBootstrapLanguageContextResolvesQueryLanguageAndHydratesTranslations(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/?lang=en',
        ];
        $_GET = ['lang' => 'en'];
        $_COOKIE = [];

        require __DIR__ . '/../core/bootstrap.php';

        $this->assertSame('en', bootstrap_language_context());
        $this->assertTrue(defined('CURRENT_LANG'));
        $this->assertSame('en', CURRENT_LANG);
        $this->assertSame('en', $_COOKIE['lang'] ?? null);
        $this->assertIsArray($GLOBALS['langTranslations'] ?? null);
        $this->assertSame(load_translations_cached('en'), $GLOBALS['langTranslations']);
    }

    /**
     * @runInSeparateProcess
     */
    public function testBootstrapLanguageContextFallsBackToCookieWhenQueryLanguageIsInvalid(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/?lang=es',
        ];
        $_GET = ['lang' => 'es'];
        $_COOKIE = ['lang' => 'de'];

        require __DIR__ . '/../core/bootstrap.php';

        $this->assertSame('de', bootstrap_language_context());
        $this->assertSame('de', $_COOKIE['lang'] ?? null);
    }

    /**
     * @runInSeparateProcess
     */
    public function testBootstrapLanguageContextIsIdempotentOnceResolvedInRequestLifecycle(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/?lang=fr',
        ];
        $_GET = ['lang' => 'fr'];
        $_COOKIE = [];

        require __DIR__ . '/../core/bootstrap.php';

        $this->assertSame('fr', bootstrap_language_context());

        $secondRequest = new Request(
            [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/?lang=en',
            ],
            ['lang' => 'en'],
            [],
            [],
            ['Host' => '127.0.0.1:8000']
        );

        $this->assertSame('fr', bootstrap_language_context($secondRequest));
        $this->assertSame('fr', $_COOKIE['lang'] ?? null);
    }
}
