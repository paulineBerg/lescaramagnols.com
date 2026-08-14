<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\Blog;

use Caramagnols\Blog\BlogRelatedLinksNormalizer;
use PHPUnit\Framework\TestCase;

final class BlogRelatedLinksNormalizerTest extends TestCase
{
    /**
     * @dataProvider languageProvider
     */
    public function testTemplateParagraphBecomesSemanticNavigation(
        string $language,
        string $paragraph,
        string $label
    ): void {
        $result = (new BlogRelatedLinksNormalizer())->normalize($paragraph, $language);

        $this->assertTrue($result['changed']);
        $this->assertSame(2, $result['link_count']);
        $this->assertStringContainsString('<nav class="article-related"', $result['content']);
        $this->assertStringContainsString('aria-label="' . $label . '"', $result['content']);
        $this->assertStringContainsString('<li><a href="/parent">Parent</a></li>', $result['content']);
        $this->assertStringNotContainsString('Ce repère', $result['content']);
        $this->assertStringNotContainsString('Within the same cluster', $result['content']);
        $this->assertStringNotContainsString('Im selben Dossier', $result['content']);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function languageProvider(): array
    {
        return [
            'fr' => [
                'fr',
                '<p>Ce repère prolonge la page <a href="/parent">Parent</a>. Dans le même dossier, voir <a href="/suite">Suite</a>.</p>',
                'Articles associés',
            ],
            'en' => [
                'en',
                '<p>This note extends the page <a href="/parent">Parent</a>. Within the same cluster, see <a href="/next">Next</a>.</p>',
                'Related articles',
            ],
            'de' => [
                'de',
                '<p>Dieser Beitrag ergaenzt die Seite <a href="/parent">Parent</a>. Im selben Dossier folgt <a href="/weiter">Weiter</a>.</p>',
                'Verwandte Artikel',
            ],
        ];
    }

    public function testEditorialParagraphWithoutCueIsUntouched(): void
    {
        $content = '<p>La page <a href="/parent">principale</a> apporte un contexte utile sans phrase gabarit.</p>';

        $result = (new BlogRelatedLinksNormalizer())->normalize($content, 'fr');

        $this->assertFalse($result['changed']);
        $this->assertSame($content, $result['content']);
    }
}
