<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\Blog;

use Caramagnols\Blog\BlogAustinMediaBackfiller;
use PHPUnit\Framework\TestCase;

final class BlogAustinMediaBackfillerTest extends TestCase
{
    public function testMiniSportReceivesDistinctFeatureAndBodyImages(): void
    {
        $result = (new BlogAustinMediaBackfiller())->backfill([
            'slug' => 'victoires-mini-rallye-monte-carlo',
            'lang' => 'fr',
            'page_slug' => 'auto-retro-austin-aventure-mini-austin',
            'content' => '<p>Une introduction documentée sur la Mini.</p>',
            'featured_image' => [],
        ]);

        $this->assertTrue($result['changed']);
        $article = $result['article'];
        $this->assertSame(
            '/assets/images/autoretro/austin/Mini_Cooper_1964.jpg',
            $article['featured_image']['src']
        );
        $this->assertStringContainsString('/assets/images/autoretro/austin/Mini_cooper-knightsbridge.jpg', $article['content']);
        $this->assertStringContainsString('width="400" height="297"', $article['content']);
    }

    public function testExistingMediaRemainUntouched(): void
    {
        $article = [
            'slug' => 'austin-seven-voiture-populaire-anglaise',
            'lang' => 'en',
            'page_slug' => 'auto-retro-austin-histoire-de-austin',
            'content' => '<p>Text.</p><figure><img src="/existing.jpg" alt="Existing" /></figure>',
            'featured_image' => ['src' => '/existing-feature.jpg'],
        ];

        $result = (new BlogAustinMediaBackfiller())->backfill($article);

        $this->assertFalse($result['changed']);
        $this->assertSame($article, $result['article']);
    }
}
