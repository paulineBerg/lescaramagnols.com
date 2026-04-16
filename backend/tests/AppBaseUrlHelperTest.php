<?php

declare(strict_types=1);

use Caramagnols\Http\Request;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class AppBaseUrlHelperTest extends TestCase
{
    private array $previousSiteUrl = [];

    protected function setUp(): void
    {
        global $appConfig;

        $this->previousSiteUrl = is_array($appConfig['site']['url'] ?? null) ? $appConfig['site']['url'] : [];
        $_SERVER['HTTP_HOST'] = 'fallback.local';
        unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT']);
    }

    protected function tearDown(): void
    {
        global $appConfig;

        $appConfig['site']['url'] = $this->previousSiteUrl;
        unset($_SERVER['HTTP_HOST'], $_SERVER['HTTPS'], $_SERVER['SERVER_PORT']);
    }

    public function testAppBaseUrlUsesConfiguredDomainAndBasePathForHttp(): void
    {
        global $appConfig;

        $appConfig['site']['url'] = [
            'domain' => 'www.example.com',
            'ssl_domain' => 'secure.example.com',
            'base_path' => '/catalogue',
        ];

        $this->assertSame('http://www.example.com/catalogue', app_base_url());
    }

    public function testAppBaseUrlUsesConfiguredSslDomainAndBasePathForHttps(): void
    {
        global $appConfig;

        $appConfig['site']['url'] = [
            'domain' => 'www.example.com',
            'ssl_domain' => 'secure.example.com',
            'base_path' => '/catalogue',
        ];
        $_SERVER['HTTPS'] = 'on';

        $this->assertSame('https://secure.example.com/catalogue', app_base_url());
    }

    public function testAppBaseUrlFallsBackToRequestHostWhenConfiguredHostIsLoopback(): void
    {
        global $appConfig;

        $appConfig['site']['url'] = [
            'domain' => '127.0.0.1:8000',
            'ssl_domain' => '127.0.0.1:8000',
            'base_path' => '/',
        ];

        $request = new Request(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/sitemap.xml'],
            [],
            [],
            [],
            [
                'Host' => 'www.lescaramagnols.com',
                'X-Forwarded-Proto' => 'https',
            ]
        );

        $this->assertSame('https://www.lescaramagnols.com', app_base_url($request));
    }

    public function testAppBaseUrlKeepsConfiguredHostWhenRequestHostIsAlsoLocal(): void
    {
        global $appConfig;

        $appConfig['site']['url'] = [
            'domain' => '127.0.0.1:8000',
            'ssl_domain' => '127.0.0.1:8000',
            'base_path' => '/catalogue',
        ];

        $request = new Request(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/sitemap.xml'],
            [],
            [],
            [],
            ['Host' => '127.0.0.1:8103']
        );

        $this->assertSame('http://127.0.0.1:8000/catalogue', app_base_url($request));
    }
}
