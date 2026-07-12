<?php

declare(strict_types=1);

use Caramagnols\Editorial\EditorialMediaValidator;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class EditorialMediaValidatorTest extends TestCase
{
    private string $frontendRoot;
    private string $publicRoot;

    protected function setUp(): void
    {
        $token = bin2hex(random_bytes(6));
        $this->frontendRoot = sys_get_temp_dir() . '/caramagnols-editorial-media-frontend-' . $token;
        $this->publicRoot = sys_get_temp_dir() . '/caramagnols-editorial-media-public-' . $token;

        mkdir($this->frontendRoot . '/boulyetcailloux', 0777, true);
        mkdir($this->publicRoot . '/assets/images/boulyetcailloux', 0777, true);
        mkdir($this->publicRoot . '/uploads/editorial/article/2026/04', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->frontendRoot);
        $this->removeDirectory($this->publicRoot);
    }

    public function testValidateAcceptsExistingCanonicalAssetsAndRuntimeUploads(): void
    {
        file_put_contents($this->frontendRoot . '/boulyetcailloux/boulyetcailloux-bague-fuyu.webp', 'webp');
        file_put_contents($this->frontendRoot . '/boulyetcailloux/boulyetcailloux-sac-lineage.webp', 'webp');
        file_put_contents($this->publicRoot . '/assets/images/boulyetcailloux/boulyetcailloux-bague-fuyu.webp', 'webp');
        file_put_contents($this->publicRoot . '/assets/images/boulyetcailloux/boulyetcailloux-sac-lineage.webp', 'webp');
        file_put_contents($this->publicRoot . '/uploads/editorial/article/2026/04/bouly-cover.webp', 'webp');

        $validator = new EditorialMediaValidator($this->frontendRoot, $this->publicRoot);
        $result = $validator->validate(
            [
                [
                    'scope' => 'page',
                    'entity' => 'bouly-page',
                    'payload' => [
                        'html' => '<img src="/assets/images/boulyetcailloux/boulyetcailloux-bague-fuyu.webp" alt=""><img src="/assets/images/boulyetcailloux/boulyetcailloux-sac-lineage.webp" alt="">',
                    ],
                ],
                [
                    'scope' => 'blog',
                    'entity' => 'bouly-article.fr',
                    'payload' => [
                        'featured_image' => [
                            'src' => '/uploads/editorial/article/2026/04/bouly-cover.webp',
                        ],
                    ],
                ],
            ],
            true
        );

        $this->assertSame([], $result['issues']);
        $this->assertSame(3, $result['reference_count']);
    }

    public function testValidateReportsLegacyMissingAndRuntimeIssues(): void
    {
        file_put_contents($this->publicRoot . '/assets/images/boulyetcailloux/boulyetcailloux-bague-fuyu.webp', 'webp');

        $validator = new EditorialMediaValidator($this->frontendRoot, $this->publicRoot);
        $result = $validator->validate(
            [
                [
                    'scope' => 'page',
                    'entity' => 'bouly-page',
                    'payload' => [
                        'html' => '<img src="/images/boulyetcailloux/boulyetcailloux-bague-fuyu.webp" alt="">',
                    ],
                ],
                [
                    'scope' => 'blog',
                    'entity' => 'bouly-article.fr',
                    'payload' => [
                        'featured_image' => [
                            'src' => '/uploads/editorial/article/2026/04/bouly-cover.webp',
                        ],
                    ],
                ],
            ],
            true
        );

        $issueTypes = array_values(array_unique(array_map(
            static fn (array $issue): string => (string) ($issue['type'] ?? ''),
            $result['issues']
        )));
        sort($issueTypes);

        $this->assertSame(['legacy_path', 'runtime_missing', 'source_missing'], $issueTypes);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            @rmdir($path);
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $target = $path . '/' . $item;
            if (is_dir($target)) {
                $this->removeDirectory($target);
                continue;
            }

            @unlink($target);
        }

        @rmdir($path);
    }
}
