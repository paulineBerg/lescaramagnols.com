<?php

declare(strict_types=1);

use Caramagnols\Http\PublicUrlNormalizer;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class PublicUrlNormalizerTest extends TestCase
{
    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->temporaryPaths) as $path) {
            if (is_file($path)) {
                @unlink($path);
                continue;
            }

            if (is_dir($path)) {
                @rmdir($path);
            }
        }
    }

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

    public function testRewriteHtmlFragmentKeepsCanonicalSourceImageWhenPublicMirrorIsNotPublishedYet(): void
    {
        $token = bin2hex(random_bytes(6));
        $relativePath = 'tests/public-url-normalizer-' . $token . '.jpg';
        $frontendDirectory = dirname(ROOT_PATH) . '/frontend/src/assets/images/tests';
        $frontendPath = $frontendDirectory . '/public-url-normalizer-' . $token . '.jpg';
        $publicPath = ROOT_PATH . '/public/assets/images/tests/public-url-normalizer-' . $token . '.jpg';

        if (!is_dir($frontendDirectory)) {
            mkdir($frontendDirectory, 0777, true);
            $this->temporaryPaths[] = $frontendDirectory;
        }

        file_put_contents($frontendPath, 'jpg');
        $this->temporaryPaths[] = $frontendPath;

        if (is_file($publicPath)) {
            @unlink($publicPath);
        }

        $html = '<img src="/assets/images/' . $relativePath . '" alt="Source canonique">';
        $rewritten = PublicUrlNormalizer::rewriteHtmlFragment($html, '/auto-retro/citroen/la-2cv4-restauration.php');

        $this->assertStringContainsString(
            'src="/assets/images/' . $relativePath . '"',
            $rewritten
        );
        $this->assertStringNotContainsString(
            PublicUrlNormalizer::missingImagePlaceholderPath(),
            $rewritten
        );
        $this->assertStringNotContainsString(
            'data-fallback-image="placeholder"',
            $rewritten
        );
    }

    public function testRewriteHtmlFragmentAddsImagePerformanceAttributesAndDimensionsWhenMissing(): void
    {
        $token = bin2hex(random_bytes(6));
        $relativePath = 'tests/public-url-normalizer-' . $token . '.gif';
        $publicDirectory = ROOT_PATH . '/public/assets/images/tests';
        $publicPath = $publicDirectory . '/public-url-normalizer-' . $token . '.gif';

        if (!is_dir($publicDirectory)) {
            mkdir($publicDirectory, 0777, true);
            $this->temporaryPaths[] = $publicDirectory;
        }

        $tinyGif = base64_decode('R0lGODdhAQABAIABAP///wAAACwAAAAAAQABAAACAkQBADs=');
        $this->assertNotFalse($tinyGif);
        file_put_contents($publicPath, (string) $tinyGif);
        $this->temporaryPaths[] = $publicPath;

        $html = '<img src="/assets/images/' . $relativePath . '" alt="Miniature">';
        $rewritten = PublicUrlNormalizer::rewriteHtmlFragment($html, '/auto-retro/citroen/la-2cv4-restauration.php');

        $this->assertStringContainsString('loading="lazy"', $rewritten);
        $this->assertStringContainsString('decoding="async"', $rewritten);
        $this->assertStringContainsString('fetchpriority="low"', $rewritten);
        $this->assertStringContainsString('width="1"', $rewritten);
        $this->assertStringContainsString('height="1"', $rewritten);
    }

    public function testRewriteHtmlFragmentBuildsResponsiveSrcsetForEditorialUploadVariants(): void
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg') || !function_exists('imagewebp')) {
            $this->markTestSkipped('GD avec support JPEG/WebP est requis pour ce test.');
        }

        $token = bin2hex(random_bytes(6));
        $publicDirectory = ROOT_PATH . '/public/uploads/editorial/media/tests';
        if (!is_dir($publicDirectory)) {
            mkdir($publicDirectory, 0777, true);
            $this->temporaryPaths[] = $publicDirectory;
        }

        $sourceRelative = '/uploads/editorial/media/tests/public-url-normalizer-' . $token . '.jpg';
        $sourcePath = ROOT_PATH . '/public' . $sourceRelative;
        $jpg640Path = ROOT_PATH . '/public/uploads/editorial/media/tests/public-url-normalizer-' . $token . '-w640.jpg';
        $jpg960Path = ROOT_PATH . '/public/uploads/editorial/media/tests/public-url-normalizer-' . $token . '-w960.jpg';
        $webp640Path = ROOT_PATH . '/public/uploads/editorial/media/tests/public-url-normalizer-' . $token . '-w640.webp';
        $webp960Path = ROOT_PATH . '/public/uploads/editorial/media/tests/public-url-normalizer-' . $token . '-w960.webp';

        $this->createImageFixture($sourcePath, 1200, 800, 'jpeg');
        $this->createImageFixture($jpg640Path, 640, 427, 'jpeg');
        $this->createImageFixture($jpg960Path, 960, 640, 'jpeg');
        $this->createImageFixture($webp640Path, 640, 427, 'webp');
        $this->createImageFixture($webp960Path, 960, 640, 'webp');

        $this->temporaryPaths[] = $sourcePath;
        $this->temporaryPaths[] = $jpg640Path;
        $this->temporaryPaths[] = $jpg960Path;
        $this->temporaryPaths[] = $webp640Path;
        $this->temporaryPaths[] = $webp960Path;

        $html = '<img src="' . $sourceRelative . '" alt="Austin historique">';
        $rewritten = PublicUrlNormalizer::rewriteHtmlFragment($html, '/auto-retro/austin/histoire-de-austin.php');

        $this->assertStringContainsString('<picture>', $rewritten);
        $this->assertStringContainsString('type="image/webp"', $rewritten);
        $this->assertStringContainsString('/uploads/editorial/media/tests/public-url-normalizer-' . $token . '-w640.webp 640w', $rewritten);
        $this->assertStringContainsString('/uploads/editorial/media/tests/public-url-normalizer-' . $token . '-w960.webp 960w', $rewritten);
        $this->assertStringContainsString('/uploads/editorial/media/tests/public-url-normalizer-' . $token . '-w640.jpg 640w', $rewritten);
        $this->assertStringContainsString('/uploads/editorial/media/tests/public-url-normalizer-' . $token . '-w960.jpg 960w', $rewritten);
        $this->assertStringContainsString('/uploads/editorial/media/tests/public-url-normalizer-' . $token . '.jpg 1200w', $rewritten);
        $this->assertStringContainsString('sizes="(max-width: 768px) 100vw, (max-width: 1200px) 90vw, 1200px"', $rewritten);
        $this->assertStringContainsString('width="1200"', $rewritten);
        $this->assertStringContainsString('height="800"', $rewritten);
    }

    public function testPrioritizeFirstImageInHtmlSetsEagerHighWithoutTouchingFollowingImages(): void
    {
        $html = '<p>Intro</p>'
            . '<img src="/uploads/editorial/media/2026/04/herbert-austin-1905.jpg" loading="lazy" fetchpriority="low" alt="Herbert">'
            . '<img src="/uploads/editorial/media/2026/04/austin-25-30-1906-wikimedia.jpg" loading="lazy" fetchpriority="low" alt="Austin">';

        $prioritized = PublicUrlNormalizer::prioritizeFirstImageInHtml($html);

        $this->assertMatchesRegularExpression(
            '#<img\b[^>]*src="/uploads/editorial/media/2026/04/herbert-austin-1905\.jpg"[^>]*loading="eager"[^>]*fetchpriority="high"[^>]*>#',
            $prioritized
        );
        $this->assertMatchesRegularExpression(
            '#<img\b[^>]*src="/uploads/editorial/media/2026/04/austin-25-30-1906-wikimedia\.jpg"[^>]*loading="lazy"[^>]*fetchpriority="low"[^>]*>#',
            $prioritized
        );
    }

    private function createImageFixture(string $path, int $width, int $height, string $format): void
    {
        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            $this->fail('Impossible de créer une image GD de test.');
        }

        $background = imagecolorallocate($image, 24, 120, 220);
        if ($background !== false) {
            imagefilledrectangle($image, 0, 0, $width, $height, $background);
        }

        $written = false;
        if ($format === 'jpeg') {
            $written = imagejpeg($image, $path, 80);
        } elseif ($format === 'webp') {
            $written = imagewebp($image, $path, 80);
        }

        imagedestroy($image);

        if (!$written) {
            $this->fail(sprintf('Impossible d écrire le fixture image (%s).', $format));
        }
    }
}
