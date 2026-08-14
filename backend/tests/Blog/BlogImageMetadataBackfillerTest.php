<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\Blog;

use Caramagnols\Blog\BlogImageMetadataBackfiller;
use PHPUnit\Framework\TestCase;

final class BlogImageMetadataBackfillerTest extends TestCase
{
    public function testMissingTitleUsesExistingAltText(): void
    {
        $content = '<figure><img src="/image.jpg" alt="Vue avant précise" width="400" height="300" /></figure>';

        $result = (new BlogImageMetadataBackfiller())->addMissingTitles($content);

        $this->assertTrue($result['changed']);
        $this->assertSame(1, $result['image_count']);
        $this->assertStringContainsString('alt="Vue avant précise" title="Vue avant précise"', $result['content']);
    }

    public function testExistingTitleAndEmptyAltRemainUntouched(): void
    {
        $content = '<img src="/one.jpg" alt="Vue" title="Titre" /><img src="/two.jpg" alt="" />';

        $result = (new BlogImageMetadataBackfiller())->addMissingTitles($content);

        $this->assertFalse($result['changed']);
        $this->assertSame($content, $result['content']);
    }
}
