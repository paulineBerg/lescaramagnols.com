<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Empêche lang.php de quitter le process pendant les tests
define('LANG_API_AS_FUNCTION', true);
require_once __DIR__ . '/../core/api/lang.php';

final class ApiLangTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = [];
        $_SERVER['HTTP_IF_NONE_MATCH'] = null;
        http_response_code(200);
    }

    public function testReturnsJson(): void
    {
        $_GET['lang'] = 'fr';
        ob_start();
        handle_lang_api();
        $output = ob_get_clean();

        $this->assertNotFalse($output);
        $this->assertJson($output);
    }

    public function testReturns304WhenEtagMatches(): void
    {
        $_GET['lang'] = 'fr';
        $langFile = ROOT_PATH . '/lang/fr.php';
        $etag = 'W/"' . (filemtime($langFile) ?: time()) . '"';
        $_SERVER['HTTP_IF_NONE_MATCH'] = $etag;

        ob_start();
        handle_lang_api();
        ob_end_clean();

        $this->assertSame(304, http_response_code());
    }
}
