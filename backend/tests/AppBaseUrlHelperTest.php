<?php

declare(strict_types=1);

use Caramagnols\Http\Request;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class AppBaseUrlHelperTest extends TestCase
{
    private array $previousAppConfig = [];
    private array $previousServer = [];
    private array $previousEnv = [];
    private string|false $previousForceHttpsOnLocalhost = false;

    protected function setUp(): void
    {
        global $appConfig;

        $this->previousAppConfig = is_array($appConfig) ? $appConfig : [];
        $this->previousServer = $_SERVER;
        $this->previousEnv = $_ENV;
        $this->previousForceHttpsOnLocalhost = getenv('FORCE_HTTPS_ON_LOCALHOST');

        $_SERVER['HTTP_HOST'] = 'fallback.local';
        unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT']);
        unset($_SERVER['FORCE_HTTPS_ON_LOCALHOST'], $_ENV['FORCE_HTTPS_ON_LOCALHOST']);
        putenv('FORCE_HTTPS_ON_LOCALHOST');
    }

    protected function tearDown(): void
    {
        global $appConfig;

        $appConfig = $this->previousAppConfig;
        $_SERVER = $this->previousServer;
        $_ENV = $this->previousEnv;
        if ($this->previousForceHttpsOnLocalhost === false) {
            putenv('FORCE_HTTPS_ON_LOCALHOST');
        } else {
            putenv('FORCE_HTTPS_ON_LOCALHOST=' . $this->previousForceHttpsOnLocalhost);
        }
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

    public function testAppUrlDowngradesConfiguredHttpsLocalhostWhenLocalTlsIsNotForced(): void
    {
        global $appConfig;

        unset($_SERVER['HTTP_HOST']);
        $appConfig['base_url'] = 'https://127.0.0.1:8000';
        $appConfig['site']['url'] = [];

        $this->assertSame(
            'http://127.0.0.1:8000/private/password/reset/token',
            app_url('/private/password/reset/token')
        );

        $appConfig['site']['url'] = ['base_path' => '/'];

        $this->assertSame(
            'http://127.0.0.1:8000/private/password/reset/token',
            app_url('/private/password/reset/token')
        );
    }

    public function testAppUrlKeepsConfiguredHttpsLocalhostWhenLocalTlsIsForced(): void
    {
        global $appConfig;

        unset($_SERVER['HTTP_HOST']);
        $_ENV['FORCE_HTTPS_ON_LOCALHOST'] = 'true';
        $appConfig['base_url'] = 'https://127.0.0.1:8000';
        $appConfig['site']['url'] = [];

        $this->assertSame(
            'https://127.0.0.1:8000/private/password/reset/token',
            app_url('/private/password/reset/token')
        );
    }
}
