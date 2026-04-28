<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class SecurityHeadersPolicyTest extends TestCase
{
    private array $appConfigBackup = [];

    protected function setUp(): void
    {
        global $appConfig;

        $this->appConfigBackup = $appConfig;
        $appConfig['site']['tarteaucitron'] = [];
        $appConfig['site']['discussions'] = [];
    }

    protected function tearDown(): void
    {
        global $appConfig;

        $appConfig = $this->appConfigBackup;
    }

    public function testRecaptchaOriginsAreAllowedWhenDiscussionProtectionIsEnabled(): void
    {
        global $appConfig;

        $appConfig['site']['discussions'] = [
            'recaptcha' => [
                'enabled' => true,
                'site_key' => 'site-key-123',
            ],
        ];

        $sources = public_csp_third_party_sources();

        $this->assertContains('https://www.google.com', $sources['script']);
        $this->assertContains('https://www.gstatic.com', $sources['script']);
        $this->assertContains('https://www.google.com', $sources['connect']);
        $this->assertContains('https://www.google.com', $sources['frame']);
        $this->assertContains('https://recaptcha.google.com', $sources['frame']);
    }

    public function testRecaptchaOriginsAreAllowedWhenServiceIsConfigured(): void
    {
        global $appConfig;

        $appConfig['site']['tarteaucitron'] = [
            'services' => ['youtube', 'recaptcha'],
        ];

        $sources = public_csp_third_party_sources();

        $this->assertContains('https://www.google.com', $sources['script']);
        $this->assertContains('https://www.gstatic.com', $sources['script']);
        $this->assertContains('https://www.google.com', $sources['connect']);
    }

    public function testGtmOriginsAreAllowedWhenConfigured(): void
    {
        global $appConfig;

        $appConfig['site']['tarteaucitron'] = [
            'services' => ['googletagmanager'],
        ];

        $sources = public_csp_third_party_sources();

        $this->assertContains('https://www.googletagmanager.com', $sources['script']);
        $this->assertContains('https://www.googletagmanager.com', $sources['connect']);
        $this->assertContains('https://www.google-analytics.com', $sources['connect']);
        $this->assertContains('https://region1.google-analytics.com', $sources['connect']);
        $this->assertContains('https://www.googleadservices.com', $sources['connect']);
    }
}
