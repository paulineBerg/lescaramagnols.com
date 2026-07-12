<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class TileHelpersTest extends TestCase
{
    public function testGetTileImageMapsRelativeImagesToTheExpectedFolder(): void
    {
        $this->assertSame(
            '/assets/images/structure/menu/boutonrectangle/exemple.png',
            getTileImage('rectangle', 'exemple.png')
        );
    }

    public function testGetTileImageUsesSizeSpecificDefaultButtonAsset(): void
    {
        $this->assertSame(
            '/assets/images/structure/menu/boutonrectangle/btrect_bleu.png',
            getTileImage(\Caramagnols\Content\TileRepository::DEFAULT_SIZE)
        );
        $this->assertSame(
            '/assets/images/structure/menu/boutonmoyen/btmoy_bleu.png',
            getTileImage('medium')
        );
    }

    public function testGetTileButtonImageFallsBackWhenAColorIsMissingForTheSelectedSize(): void
    {
        $this->assertSame(
            '/assets/images/structure/menu/boutonpetit/btptt_bleu.png',
            getTileButtonImage('small', 'rose')
        );
        $this->assertSame(
            '/assets/images/structure/menu/boutongrand/btgrd_rose_selection.png',
            getTileButtonImage('large', 'rose', 'hover')
        );
    }
}
