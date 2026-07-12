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
        unset($GLOBALS['csp_nonce'], $GLOBALS['currentBlogArticle'], $GLOBALS['currentDynamicOpenArticle'], $GLOBALS['currentBlogArticles'], $GLOBALS['currentPageHasDiscussionForm']);
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
        $this->assertStringContainsString('tarteaucitron.min.js" defer', $html);
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
        $GLOBALS['currentPageHasDiscussionForm'] = true;

        ob_start();
        include ROOT_PATH . '/templates/partials/scripts_head.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('"services":["youtube","recaptcha"]', $html);
        $this->assertStringContainsString('"discussions":{"enabled":true', $html);
        $this->assertStringContainsString('"has_form":true', $html);
        $this->assertStringContainsString('"mode":"v3_score"', $html);
        $this->assertStringContainsString('"site_key":"site-key-123"', $html);
    }

    public function testPublicHeadKeepsRecaptchaServiceDisabledWhenNoDiscussionFormIsRendered(): void
    {
        global $appConfig;

        $pageTitle = 'Test sans formulaire';
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
        $GLOBALS['currentPageHasDiscussionForm'] = false;

        ob_start();
        include ROOT_PATH . '/templates/partials/scripts_head.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('"services":["youtube"]', $html);
        $this->assertStringContainsString('"has_form":false', $html);
        $this->assertStringContainsString('"recaptcha":{"enabled":false', $html);
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
        $this->assertStringContainsString('property="og:image:alt"', $html);
        $this->assertStringContainsString('name="twitter:image"', $html);
        $this->assertStringContainsString('summary_large_image', $html);
        $this->assertStringContainsString('Couverture article', $html);
    }

    public function testPublicHeadOmitsGlobalSocialImageWhenPageImageIsSet(): void
    {
        global $appConfig;

        $pageTitle = 'Test image SEO';
        $pageMetaImage = 'https://example.test/uploads/editorial/article/2026/03/cover.jpg';
        $pageMetaImageAlt = 'Couverture article';
        $appConfig['site']['head_metadata_html'] = <<<HTML
<meta property="og:image" content="https://www.lescaramagnols.com/assets/images/bouger/golfe/montage.jpg" />
<meta property="og:image:secure_url" content="https://www.lescaramagnols.com/assets/images/bouger/golfe/montage.jpg" />
<meta property="og:image:width" content="1000" />
<meta property="og:image:height" content="562" />
<meta name="twitter:image" content="https://www.lescaramagnols.com/assets/images/bouger/golfe/montage.jpg" />
<meta name="twitter:image:alt" content="Image par défaut du site" />
<meta name="twitter:card" content="summary_large_image" />
HTML;

        ob_start();
        include ROOT_PATH . '/templates/partials/scripts_head.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('content="https://example.test/uploads/editorial/article/2026/03/cover.jpg"', $html);
        $this->assertStringNotContainsString('content="https://www.lescaramagnols.com/assets/images/bouger/golfe/montage.jpg"', $html);
        $this->assertStringNotContainsString('Image par défaut du site', $html);
        $this->assertStringNotContainsString('<meta property="og:image:width" content="1000"', $html);
        $this->assertStringNotContainsString('<meta property="og:image:height" content="562"', $html);
        $this->assertSame(1, substr_count($html, 'name="twitter:card"'));
    }

    public function testPublicHeadKeepsGlobalSocialImageWhenNoPageImageIsSet(): void
    {
        global $appConfig;

        $pageTitle = 'Test image SEO';
        $appConfig['site']['head_metadata_html'] = <<<HTML
<meta property="og:image" content="https://www.lescaramagnols.com/assets/images/bouger/golfe/montage.jpg" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:image" content="https://www.lescaramagnols.com/assets/images/bouger/golfe/montage.jpg" />
HTML;

        ob_start();
        include ROOT_PATH . '/templates/partials/scripts_head.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('content="https://www.lescaramagnols.com/assets/images/bouger/golfe/montage.jpg"', $html);
        $this->assertStringContainsString('name="twitter:card"', $html);
    }

    public function testPublicHeadRendersCentralJsonLdAndDropsFragmentedGlobalJsonLd(): void
    {
        global $appConfig;

        $pageTitle = 'Page SEO';
        $pageMetaDescription = 'Description SEO.';
        $pageCanonicalUrl = 'https://www.example.com/page#fragment';
        $GLOBALS['csp_nonce'] = 'jsonldnonce';
        $appConfig['site']['head_metadata_html'] = <<<HTML
<meta property="custom:keep" content="kept" />
<script type="application/ld+json">{"@context":"https://schema.org","@id":"https://www.example.com/#legacy","name":"Legacy Schema"}</script>
HTML;

        ob_start();
        include ROOT_PATH . '/templates/partials/scripts_head.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('<link rel="canonical" href="https://www.example.com/page" />', $html);
        $this->assertStringContainsString('property="custom:keep"', $html);
        $this->assertStringNotContainsString('Legacy Schema', $html);

        preg_match_all(
            '/<script\b[^>]*type="application\/ld\+json"[^>]*>(.*?)<\/script>/s',
            $html,
            $matches
        );
        $this->assertCount(1, $matches[1] ?? []);
        $jsonLd = trim(html_entity_decode((string) ($matches[1][0] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $this->assertStringContainsString('"@graph"', $jsonLd);
        $this->assertStringContainsString('"url": "https://www.example.com/page"', $jsonLd);
        $this->assertStringNotContainsString('#', $jsonLd);
        $this->assertStringContainsString('nonce="jsonldnonce"', $html);
    }
}
