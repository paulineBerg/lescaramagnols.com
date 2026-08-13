<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PbGestion;

use Caramagnols\PbGestion\Photo\PhotoFilenameNormalizer;
use PHPUnit\Framework\TestCase;

final class PhotoFilenameNormalizerTest extends TestCase
{
    public function testNormalizesSpecialCharactersAndReservedWindowsNames(): void
    {
        $normalizer = new PhotoFilenameNormalizer();

        $this->assertSame('Vacances-Cogolin-2026.jpg', $normalizer->normalizeFilename('Vacances/Cogolin:2026', 'jpg'));
        $this->assertSame('CON-file.jpg', $normalizer->normalizeFilename('CON', 'jpg'));
        $this->assertSame('Famille_Cogolin_001.JPG', $normalizer->normalizeFilename('Famille   Cogolin   001', 'JPG', '_'));
    }

    public function testKeepsReasonableLengthAndFallsBackToPhoto(): void
    {
        $normalizer = new PhotoFilenameNormalizer();
        $filename = $normalizer->normalizeFilename(str_repeat('a', 260), 'jpeg', '-', 80);

        $this->assertLessThanOrEqual(80, strlen($filename));
        $this->assertStringEndsWith('.jpeg', $filename);
        $this->assertSame('photo.jpg', $normalizer->normalizeFilename('////', 'jpg'));
    }
}
