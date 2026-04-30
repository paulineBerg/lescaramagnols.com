<?php

declare(strict_types=1);

use Caramagnols\Http\PublicUrlNormalizer;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class PublicUrlNormalizerTest extends TestCase
{
    public function testNormalizeRouteCanonicalizesLegacyLocalPaths(): void
    {
        $this->assertSame(
            '/assets/images/autoretro/austin/mini_mayfair.jpg',
            PublicUrlNormalizer::normalizeRoute('/images/autoretro/austin/mini_mayfair.jpg')
        );
        $this->assertSame(
            '/assets/images/structure/menu/banniere.gif',
            PublicUrlNormalizer::normalizeRoute('https://www.lescaramagnols.com/structure/images/menu/banniere.gif')
        );
        $this->assertSame(
            '/bouger/se-promener-dans-le-golfe-de-sttropez.php',
            PublicUrlNormalizer::normalizeRoute('/bouger/site/bouger/se-promener-dans-le-golfe-de-sttropez.php')
        );
        $this->assertSame(
            '/core/api/lang.php',
            PublicUrlNormalizer::normalizeRoute('/auto-retro/simca/core/api/lang.php')
        );
        $this->assertSame(
            '/auto-retro/panhard/la-dyna-modele-z12.php',
            PublicUrlNormalizer::normalizeRoute('/auto-retro-panhard-la-dyna-modele-z12.php')
        );
        $this->assertSame(
            '/auto-retro/simca/histoire-simca-aronde-icone-francaise.php',
            PublicUrlNormalizer::normalizeRoute('/auto-retro/simca/simca-aronde-icone-francaise.php')
        );
    }

    public function testRewriteHtmlFragmentResolvesRelativeLegacyLinksAndMissingImages(): void
    {
        $html = '<p><a href="site/bouger/se-promener-dans-les-villages-du-golfe-de-sttropez.php">Balade</a></p>'
            . '<img src="/images/structure/banniere.jpg" alt="Banniere">'
            . '<img src="/images/does-not-exist.jpg" alt="Manquante">';

        $rewritten = PublicUrlNormalizer::rewriteHtmlFragment(
            $html,
            '/bouger/les-animations-dans-le-golfe-de-sttropez.php'
        );

        $this->assertStringContainsString(
            'href="/bouger/se-promener-dans-les-villages-du-golfe-de-sttropez.php"',
            $rewritten
        );
        $this->assertStringContainsString(
            'src="/assets/images/structure/banniere.jpg"',
            $rewritten
        );
        $this->assertStringContainsString(
            'src="' . PublicUrlNormalizer::missingImagePlaceholderPath() . '"',
            $rewritten
        );
        $this->assertStringContainsString(
            'src="' . PublicUrlNormalizer::missingImagePlaceholderPath() . '" data-fallback-image="placeholder"',
            $rewritten
        );
    }
}
