<?php

declare(strict_types=1);

use Caramagnols\Http\Request;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class AppBaseUrlHelperTest extends TestCase
{
    private array $previousSiteUrl = [];
    private mixed $previousBaseUrl = null;
    private bool $hadPreviousBaseUrl = false;

    protected function setUp(): void
    {
        global $appConfig;
        $appConfig = is_array($appConfig ?? null) ? $appConfig : [];

        $this->hadPreviousBaseUrl = array_key_exists('base_url', $appConfig);
        $this->previousBaseUrl = $appConfig['base_url'] ?? null;
        $appConfig['base_url'] = '/';
        $this->previousSiteUrl = is_array($appConfig['site']['url'] ?? null) ? $appConfig['site']['url'] : [];
        $_SERVER['HTTP_HOST'] = 'fallback.local';
        unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT']);
    }

    protected function tearDown(): void
    {
        global $appConfig;
        $appConfig = is_array($appConfig ?? null) ? $appConfig : [];

        if ($this->hadPreviousBaseUrl) {
            $appConfig['base_url'] = $this->previousBaseUrl;
        } else {
            unset($appConfig['base_url']);
        }
        $appConfig['site']['url'] = $this->previousSiteUrl;
        unset($_SERVER['HTTP_HOST'], $_SERVER['HTTPS'], $_SERVER['SERVER_PORT']);
    }

    public function testAppBaseUrlUsesConfiguredDomainAndBasePathForHttp(): void
    {
        global $appConfig;

        unset($_SERVER['HTTP_HOST']);

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

        unset($_SERVER['HTTP_HOST']);

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

    public function testAppBaseUrlPrefersLocalRequestHostOverConfiguredPublicHost(): void
    {
        global $appConfig;

        $appConfig['site']['url'] = [
            'domain' => 'example.com',
            'ssl_domain' => 'example.com',
            'base_path' => '/',
        ];

        $request = new Request(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/blog'],
            [],
            [],
            [],
            ['Host' => '127.0.0.1:8000']
        );

        $this->assertSame('http://127.0.0.1:8000', app_base_url($request));
    }
}
