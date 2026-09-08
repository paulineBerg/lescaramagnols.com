<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PrivateApps\PhotoGeoRenamer;

use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\PhotoPathPolicy;
use PHPUnit\Framework\TestCase;

final class PhotoPathPolicyTest extends TestCase
{
    public function testAcceptsOnlyWhitelistedRootsAndRelativePhotoPaths(): void
    {
        $policy = new PhotoPathPolicy();

        $this->assertTrue($policy->isValidRootUid('photos-principales'));
        $this->assertFalse($policy->isValidRootUid('../photos'));
        $this->assertSame('2026/vacances', $policy->normalizeRelativeDirectory('2026\\vacances'));
        $this->assertSame('2026/IMG_0001.JPG', $policy->normalizeRelativePhoto('2026/IMG_0001.JPG'));
    }

    public function testRejectsTraversalAbsolutePathsAndUnsupportedExtensions(): void
    {
        $policy = new PhotoPathPolicy();

        $this->assertNull($policy->normalizeRelativeDirectory('../Windows'));
        $this->assertNull($policy->normalizeRelativeDirectory('C:\\Windows'));
        $this->assertNull($policy->normalizeRelativePhoto('/tmp/photo.jpg'));
        $this->assertNull($policy->normalizeRelativePhoto('photo.exe'));
        $this->assertNull($policy->normalizePhotoList(['photo.jpg', '../secret.jpg']));
    }

    public function testAcceptsLargerBatchesWithoutChangingPathRules(): void
    {
        $policy = new PhotoPathPolicy();
        $items = [];
        for ($index = 1; $index <= 600; $index++) {
            $items[] = sprintf('photos/IMG_%04d.jpg', $index);
        }

        $this->assertCount(600, $policy->normalizePhotoList($items));
        $this->assertContains('heic', $policy->allowedExtensions());
    }
}
