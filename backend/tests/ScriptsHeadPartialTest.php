<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class ScriptsHeadPartialTest extends TestCase
{
    protected function tearDown(): void
    {
        global $appConfig;
        $appConfig['site']['head_metadata_html'] = '';
        $appConfig['site']['tarteaucitron'] = [];
        $appConfig['site']['discussions'] = [];
    }

    public function testPublicHeadIncludesTarteaucitronScript(): void
    {
        global $appConfig;

        $pageTitle = 'Test';
        $pageMetaDescription = 'Description';
        $GLOBALS['csp_nonce'] = 'testnonce';
        $appConfig['site']['head_metadata_html'] = '<meta name="robots" content="index,follow" />';
        $appConfig['site']['tarteaucitron'] = [
            'enabled' => true,
            'privacy_url' => '/mentions',
            'orientation' => 'top',
            'icon_position' => 'TopLeft',
            'show_icon' => false,
            'show_alert_small' => false,
            'high_privacy' => false,
            'accept_all_cta' => false,
            'deny_all_cta' => false,
            'mandatory' => false,
            'google_consent_mode' => false,
            'bing_consent_mode' => false,
            'user_config_json' => '{"googletagmanagerId":"GTM-MKG2FFBZ"}',
            'services' => ['youtube', 'vimeo'],
        ];

        ob_start();
        include ROOT_PATH . '/templates/partials/scripts_head.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('/tarteaucitron/tarteaucitron.min.js', $html);
        $this->assertStringContainsString('nonce="testnonce"', $html);
        $this->assertStringContainsString('type="module"', $html);
        $this->assertStringContainsString('<meta name="robots" content="index,follow" />', $html);
        $this->assertStringContainsString('window.caramagnolsRuntime', $html);
        $this->assertStringContainsString('"privacy_url":"/mentions"', $html);
        $this->assertStringContainsString('"icon_position":"TopLeft"', $html);
        $this->assertStringContainsString('"user_config_json":"{\"googletagmanagerId\":\"GTM-MKG2FFBZ\"}"', $html);
        $this->assertStringContainsString('"services":["youtube","vimeo"]', $html);
    }

    public function testPublicHeadOmitsTarteaucitronScriptWhenDisabled(): void
    {
        global $appConfig;

        $pageTitle = 'Test';
        $appConfig['site']['tarteaucitron'] = [
            'enabled' => false,
        ];

        ob_start();
        include ROOT_PATH . '/templates/partials/scripts_head.php';
        $html = (string) ob_get_clean();

        $this->assertStringNotContainsString('/tarteaucitron/tarteaucitron.min.js', $html);
        $this->assertStringContainsString('"enabled":false', $html);
    }

    public function testPublicHeadAddsRecaptchaServiceWhenDiscussionRecaptchaIsEnabled(): void
    {
        global $appConfig;

        $pageTitle = 'Test';
        $appConfig['site']['tarteaucitron'] = [
            'enabled' => true,
            'services' => ['youtube'],
        ];
        $appConfig['site']['discussions'] = [
            'enabled' => true,
            'recaptcha' => [
                'enabled' => true,
                'mode' => 'v3_score',
                'site_key' => 'site-key-123',
                'secret_key' => 'secret-key-123',
            ],
        ];

        ob_start();
        include ROOT_PATH . '/templates/partials/scripts_head.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('"services":["youtube","recaptcha"]', $html);
        $this->assertStringContainsString('"discussions":{"enabled":true', $html);
        $this->assertStringContainsString('"mode":"v3_score"', $html);
        $this->assertStringContainsString('"site_key":"site-key-123"', $html);
    }

    public function testPublicHeadIncludesSeoImageMetaTagsWhenProvided(): void
    {
        $pageTitle = 'Test image SEO';
        $pageMetaImage = 'https://example.test/uploads/editorial/article/2026/03/cover.jpg';
        $pageMetaImageAlt = 'Couverture article';

        ob_start();
        include ROOT_PATH . '/templates/partials/scripts_head.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('property="og:image"', $html);
        $this->assertStringContainsString('name="twitter:image"', $html);
        $this->assertStringContainsString('summary_large_image', $html);
        $this->assertStringContainsString('Couverture article', $html);
    }
}
