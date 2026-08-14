<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\Blog;

use Caramagnols\Blog\BlogRepeatedParagraphPruner;
use PHPUnit\Framework\TestCase;

final class BlogRepeatedParagraphPrunerTest extends TestCase
{
    public function testParagraphRepeatedAcrossThreeSlugsIsRemoved(): void
    {
        $template = '<p>Cette phrase suffisamment longue est copiée dans plusieurs sujets et révèle un remplissage éditorial mécanique.</p>';
        $articles = [];
        foreach (['un', 'deux', 'trois'] as $slug) {
            $articles[] = ['slug' => $slug, 'lang' => 'fr', 'content' => $template];
        }

        $pruner = new BlogRepeatedParagraphPruner();
        $index = $pruner->repeatedSentenceIndex($articles);
        $result = $pruner->prune(
            '<p>Introduction propre et spécifique au premier sujet.</p>'
            . '<h2>Intertitre devenu vide</h2>' . $template
            . '<nav aria-label="Articles associés"><a href="/suite">Suite</a></nav>',
            $index['fr']
        );

        $this->assertTrue($result['changed']);
        $this->assertSame(1, $result['paragraph_count']);
        $this->assertSame(1, $result['heading_count']);
        $this->assertStringContainsString('Introduction propre', $result['content']);
        $this->assertStringNotContainsString('remplissage éditorial', $result['content']);
        $this->assertStringNotContainsString('Intertitre devenu vide', $result['content']);
    }

    public function testParagraphWithInternalLinkIsPreserved(): void
    {
        $paragraph = '<p>Cette phrase suffisamment longue contient <a href="/preuve">une preuve interne</a> et doit rester dans le contenu final.</p>';
        $articles = [];
        foreach (['un', 'deux', 'trois'] as $slug) {
            $articles[] = ['slug' => $slug, 'lang' => 'fr', 'content' => $paragraph];
        }

        $pruner = new BlogRepeatedParagraphPruner();
        $index = $pruner->repeatedSentenceIndex($articles);
        $result = $pruner->prune($paragraph, $index['fr'] ?? []);

        $this->assertFalse($result['changed']);
        $this->assertSame($paragraph, $result['content']);
    }
}
