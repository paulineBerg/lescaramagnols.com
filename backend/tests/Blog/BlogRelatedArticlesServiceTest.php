<?php

declare(strict_types=1);

use Caramagnols\Blog\BlogRelatedArticlesService;
use Caramagnols\Blog\BlogTaxonomy;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';

final class BlogRelatedArticlesServiceTest extends TestCase
{
    public function testSuggestPrioritizesSameSubcategoryAndLimitsResults(): void
    {
        $service = new BlogRelatedArticlesService(BlogTaxonomy::fromDefaultConfig());
        $article = [
            'slug' => 'courant',
            'lang' => 'fr',
            'category' => 'auto-retro',
            'subcategory' => 'histoire-automobile',
            'tags' => ['austin', 'histoire', 'voiture-ancienne'],
        ];

        $suggestions = $service->suggest(
            $article,
            [
                [
                    'slug' => 'meme-sous-categorie',
                    'lang' => 'fr',
                    'category' => 'auto-retro',
                    'subcategory' => 'histoire-automobile',
                    'tags' => ['austin', 'histoire', 'voiture-ancienne'],
                    'date' => '2026-03-20 10:00:00',
                ],
                [
                    'slug' => 'meme-categorie',
                    'lang' => 'fr',
                    'category' => 'auto-retro',
                    'subcategory' => 'modeles-et-versions',
                    'tags' => ['austin', 'modele', 'voiture-ancienne'],
                    'date' => '2026-03-21 10:00:00',
                ],
                [
                    'slug' => 'tags-communs',
                    'lang' => 'fr',
                    'category' => 'patrimoine',
                    'subcategory' => 'lieux-et-memoire',
                    'tags' => ['austin', 'histoire', 'patrimoine'],
                    'date' => '2026-03-22 10:00:00',
                ],
                [
                    'slug' => 'hors-sujet',
                    'lang' => 'fr',
                    'category' => 'territoire',
                    'subcategory' => 'villages-et-balades',
                    'tags' => ['village', 'balade', 'patrimoine'],
                    'date' => '2026-03-23 10:00:00',
                ],
            ],
            [],
            3
        );

        $this->assertSame(
            ['meme-sous-categorie', 'meme-categorie', 'tags-communs'],
            array_map(static fn (array $suggestion): string => (string) $suggestion['slug'], $suggestions)
        );
    }
}
