<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\Seo;

use Caramagnols\Seo\StructuredDataBuilder;
use PHPUnit\Framework\TestCase;

final class StructuredDataBuilderTest extends TestCase
{
    public function testBuildsStableWebPageGraphWithoutUrlFragments(): void
    {
        $payload = $this->builder()->build([
            'title' => 'Page Austin',
            'description' => 'Une page de test.',
            'canonical_url' => 'https://www.example.com/auto-retro/austin#top',
            'language' => 'fr',
            'image' => [
                'url' => '/assets/images/austin.jpg#image',
                'alt' => 'Austin ancienne',
                'width' => 1200,
                'height' => 630,
            ],
        ]);

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('#', $json);

        $webPage = $this->graphNode($payload, 'WebPage');
        $this->assertSame('https://www.example.com/auto-retro/austin', $webPage['url'] ?? null);
        $this->assertSame('fr-FR', $webPage['inLanguage'] ?? null);
        $this->assertSame('https://www.example.com/assets/images/austin.jpg', $webPage['thumbnailUrl'] ?? null);

        $organization = $this->graphNode($payload, 'Organization');
        $this->assertSame('accueil@lescaramagnols.com', $organization['email'] ?? null);
    }

    public function testBuildsBlogPostingFromArticleContext(): void
    {
        $payload = $this->builder()->build([
            'title' => 'Article attache',
            'description' => 'Extrait article.',
            'canonical_url' => 'https://www.example.com/fr/association?open_article=article-attache#attached-article-article-attache',
            'language' => 'fr',
            'page_kind' => 'blog_article',
            'article' => [
                'title' => 'Article attache',
                'slug' => 'article-attache',
                'lang' => 'fr',
                'author' => 'Les Caramagnols',
                'date' => '2026-03-20 10:00:00',
                'updated_at' => '2026-03-21T09:30:00+00:00',
                'excerpt' => 'Extrait article.',
                'tags' => ['auto-retro', 'essai'],
            ],
            'image' => [
                'url' => 'https://www.example.com/uploads/editorial/article.jpg',
                'width' => 1200,
                'height' => 630,
            ],
        ]);

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('#', $json);

        $article = $this->graphNode($payload, 'BlogPosting');
        $this->assertSame('Article attache', $article['headline'] ?? null);
        $this->assertSame(
            'https://www.example.com/fr/association?open_article=article-attache',
            $article['url'] ?? null
        );
        $this->assertSame('Organization', $article['author']['@type'] ?? null);
        $this->assertSame('auto-retro, essai', $article['keywords'] ?? null);
        $this->assertSame('https://www.example.com/uploads/editorial/article.jpg', $article['image']['url'] ?? null);
    }

    public function testBuildsFaqOnlyWhenQuestionsAreProvided(): void
    {
        $withoutFaq = $this->builder()->build([
            'title' => 'Page simple',
            'canonical_url' => 'https://www.example.com/page',
            'language' => 'fr',
        ]);
        $this->assertNull($this->graphNode($withoutFaq, 'FAQPage', false));

        $withFaq = $this->builder()->build([
            'title' => 'Page FAQ',
            'canonical_url' => 'https://www.example.com/page-faq#question',
            'language' => 'fr',
            'faq' => [
                [
                    'question' => 'Quelle voiture choisir ?',
                    'answer' => '<p>Une voiture dont l etat est verifie.</p>',
                ],
            ],
        ]);

        $faq = $this->graphNode($withFaq, 'FAQPage');
        $this->assertSame('https://www.example.com/page-faq', $faq['url'] ?? null);
        $this->assertSame('Quelle voiture choisir ?', $faq['mainEntity'][0]['name'] ?? null);
    }

    private function builder(): StructuredDataBuilder
    {
        return new StructuredDataBuilder(
            'https://www.example.com',
            'Les Caramagnols',
            'Description du site',
            'Pauline Bergon',
            'Editrice du site',
            'Les Caramagnols',
            ['fr', 'en', 'de']
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function graphNode(array $payload, string $type, bool $required = true): ?array
    {
        $graph = is_array($payload['@graph'] ?? null) ? $payload['@graph'] : [];
        foreach ($graph as $node) {
            if (!is_array($node) || ($node['@type'] ?? null) !== $type) {
                continue;
            }

            return $node;
        }

        if ($required) {
            $this->fail(sprintf('No %s node found in JSON-LD graph.', $type));
        }

        return null;
    }
}
