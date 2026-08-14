<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\Blog;

use Caramagnols\Blog\BlogEditorialQualityAuditor;
use PHPUnit\Framework\TestCase;

final class BlogEditorialQualityAuditorTest extends TestCase
{
    public function testCompleteDetailedTripletPasses(): void
    {
        $articles = [];
        foreach (['fr', 'en', 'de'] as $language) {
            $articles[] = $this->article('article-complet', $language, $this->content($language, 650));
        }

        $result = (new BlogEditorialQualityAuditor())->audit($articles);

        $this->assertSame(0, $result['error_count']);
        $this->assertSame(3, $result['article_count']);
        $this->assertSame(1, $result['slug_count']);
    }

    public function testMissingTranslationShortTextAndForbiddenPhraseFail(): void
    {
        $result = (new BlogEditorialQualityAuditor())->audit([
            $this->article(
                'article-incomplet',
                'fr',
                '<p>Pour la lire correctement, ce texte reste bien trop court.</p><h2>Sources</h2><ul><li>A</li></ul>'
            ),
        ]);
        $types = array_column($result['issues'], 'type');

        $this->assertGreaterThan(0, $result['error_count']);
        $this->assertContains('content_too_short', $types);
        $this->assertContains('forbidden_phrase', $types);
        $this->assertContains('sources_insufficient', $types);
        $this->assertContains('translation_missing', $types);
    }

    public function testSentenceRepeatedAcrossThreeSlugsFailsCorpusAudit(): void
    {
        $articles = [];
        foreach (['premier', 'deuxieme', 'troisieme'] as $slug) {
            foreach (['fr', 'en', 'de'] as $language) {
                $content = $this->content($language, 650);
                if ($language === 'fr') {
                    $content = '<p>Cette phrase suffisamment longue est répétée dans plusieurs contenus et révèle clairement un gabarit éditorial mécanique.</p>' . $content;
                }
                $articles[] = $this->article($slug, $language, $content);
            }
        }

        $result = (new BlogEditorialQualityAuditor())->audit($articles);
        $types = array_column($result['issues'], 'type');

        $this->assertContains('repeated_sentence', $types);
    }

    public function testHeadingRepeatedAcrossThreeSlugsFailsCorpusAudit(): void
    {
        $articles = [];
        foreach (['premier', 'deuxieme', 'troisieme'] as $slug) {
            foreach (['fr', 'en', 'de'] as $language) {
                $content = $this->content($language, 650);
                if ($language === 'fr') {
                    $content = '<h2>Une conclusion à retenir</h2>' . $content;
                }
                $articles[] = $this->article($slug, $language, $content);
            }
        }

        $result = (new BlogEditorialQualityAuditor())->audit($articles);

        $this->assertContains('repeated_heading', array_column($result['issues'], 'type'));
    }

    public function testEditorialLexiconSpreadAcrossCorpusFailsAudit(): void
    {
        $articles = [];
        for ($index = 1; $index <= 10; $index++) {
            foreach (['fr', 'en', 'de'] as $language) {
                $content = $this->content($language, 650);
                if ($language === 'fr') {
                    $content = '<p>Ce passage propose une lecture différente du sujet numéro ' . $index . '.</p>' . $content;
                }
                $articles[] = $this->article('article-' . $index, $language, $content);
            }
        }

        $result = (new BlogEditorialQualityAuditor())->audit($articles);

        $this->assertContains('overused_corpus_lexicon', array_column($result['issues'], 'type'));
    }

    public function testMechanicalLexiconSubstitutionsSpreadAcrossCorpusAlsoFailAudit(): void
    {
        $articles = [];
        for ($index = 1; $index <= 10; $index++) {
            foreach (['fr', 'en', 'de'] as $language) {
                $replacement = match ($language) {
                    'fr' => '<p>Mieux vaut examiner précisément le sujet numéro ' . $index . '.</p>',
                    'en' => '<p>This assessment will examine subject number ' . $index . ' precisely.</p>',
                    'de' => '<p>Das Thema Nummer ' . $index . ' ist genau zu prüfen.</p>',
                };
                $articles[] = $this->article(
                    'substitution-' . $index,
                    $language,
                    $replacement . $this->content($language, 650)
                );
            }
        }

        $result = (new BlogEditorialQualityAuditor())->audit($articles);
        $lexiconIssues = array_values(array_filter(
            $result['issues'],
            static fn (array $issue): bool => $issue['type'] === 'overused_corpus_lexicon'
        ));

        $this->assertCount(4, $lexiconIssues);
    }

    public function testRepeatedFigureCaptionIsNotTreatedAsArticleProse(): void
    {
        $articles = [];
        foreach (['premier', 'deuxieme', 'troisieme'] as $slug) {
            foreach (['fr', 'en', 'de'] as $language) {
                $content = '<figure><img src="/image.webp" alt="Illustration" title="Illustration">'
                    . '<figcaption>Cette légende documentaire accompagne la même image dans plusieurs dossiers.</figcaption>'
                    . '</figure>' . $this->content($language, 650);
                $articles[] = $this->article($slug, $language, $content);
            }
        }

        $result = (new BlogEditorialQualityAuditor())->audit($articles);
        $captions = array_filter(
            $result['issues'],
            static fn (array $issue): bool => $issue['type'] === 'repeated_sentence'
                && str_contains($issue['detail'], 'légende documentaire')
        );

        $this->assertSame([], array_values($captions));
    }

    /**
     * @return array<string, mixed>
     */
    private function article(string $slug, string $language, string $content): array
    {
        return [
            'slug' => $slug,
            'lang' => $language,
            'title' => 'Titre précis ' . $language,
            'excerpt' => 'Extrait distinct et concret ' . $language,
            'content' => $content,
        ];
    }

    private function content(string $language, int $wordCount): string
    {
        $word = match ($language) {
            'de' => 'Fahrzeug',
            'en' => 'vehicle',
            default => 'véhicule',
        };
        $heading = $language === 'de' ? 'Quellen' : 'Sources';

        return '<p>' . implode(' ', array_fill(0, $wordCount, $word)) . '</p>'
            . '<p><a href="/page-parent">Page parent</a> et <a href="https://example.com/source">source</a>.</p>'
            . '<h2>' . $heading . '</h2><ul><li>Source A</li><li>Source B</li></ul>';
    }
}
