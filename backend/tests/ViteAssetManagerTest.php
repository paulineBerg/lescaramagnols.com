<?php

declare(strict_types=1);

use Caramagnols\Assets\ViteAssetManager;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class ViteAssetManagerTest extends TestCase
{
    private string $manifestPath;

    protected function setUp(): void
    {
        $this->manifestPath = sys_get_temp_dir() . '/caramagnols-vite-' . bin2hex(random_bytes(6)) . '.json';

        file_put_contents(
            $this->manifestPath,
            json_encode(
                [
                    'src/js/main.ts' => [
                        'file' => 'assets/main.ABC12345.js',
                        'css' => ['assets/style.ZYX98765.css'],
                        'assets' => ['assets/hero.HELLO123.jpg'],
                    ],
                    'src/scss/style.scss' => [
                        'file' => 'assets/style.ZYX98765.css',
                    ],
                ],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->manifestPath);
    }

    public function testTagsRenderCssAndScriptFromManifest(): void
    {
        $manager = new ViteAssetManager($this->manifestPath, 'http://localhost:5173', false);

        $tags = $manager->tags('src/js/main.ts', 'nonce123');

        $this->assertStringContainsString('<link rel="stylesheet" href="/assets/style.ZYX98765.css">', $tags);
        $this->assertStringContainsString('<script type="module" nonce="nonce123" src="/assets/main.ABC12345.js"></script>', $tags);
    }

    public function testAssetAndCssUrlsUsePublishedPaths(): void
    {
        $manager = new ViteAssetManager($this->manifestPath, 'http://localhost:5173', false);

        $this->assertSame('/assets/main.ABC12345.js', $manager->assetUrl('src/js/main.ts'));
        $this->assertSame(['/assets/style.ZYX98765.css'], $manager->cssUrls('src/js/main.ts'));
        $this->assertSame('/assets/missing-entry.ts', $manager->assetUrl('missing-entry.ts'));
    }
}
