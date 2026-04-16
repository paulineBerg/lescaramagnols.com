<?php

declare(strict_types=1);

use Caramagnols\Admin\Navigation\NavigationItemPathCodec;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class NavigationItemPathCodecTest extends TestCase
{
    private NavigationItemPathCodec $codec;

    protected function setUp(): void
    {
        $this->codec = new NavigationItemPathCodec(['utility', 'primary', 'footer'], 'primary');
    }

    public function testParseNormalizesUnknownLocationToDefault(): void
    {
        $parsed = $this->codec->parse('unknown|1|2');

        $this->assertIsArray($parsed);
        $this->assertSame('primary', $parsed['location']);
        $this->assertSame([1, 2], $parsed['indices']);
    }

    public function testEncodeAndInputNameUseNormalizedLocation(): void
    {
        $encoded = $this->codec->encode('footer', [0, 3]);
        $inputName = $this->codec->inputName('footer', [0, 3]);

        $this->assertSame('footer|0|3', $encoded);
        $this->assertSame('locations[footer][0][children][3]', $inputName);
    }

    public function testParseReturnsNullWhenPathContainsNonNumericIndex(): void
    {
        $this->assertNull($this->codec->parse('primary|abc'));
    }
}
