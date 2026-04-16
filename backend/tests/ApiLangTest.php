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
        $response = lang_api_response('fr');

        $this->assertSame(200, $response->status);
        $this->assertJson($response->body);
        $this->assertSame('application/json; charset=utf-8', $response->headers['Content-Type'] ?? null);
    }

    public function testReturns304WhenEtagMatches(): void
    {
        $langFile = translation_file_path('fr');
        $etag = 'W/"' . (filemtime($langFile) ?: time()) . '"';
        $response = lang_api_response('fr', $etag);

        $this->assertSame(304, $response->status);
        $this->assertSame('', $response->body);
    }
}
