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
                            'alt' => 'Bague Bouly et Cailloux',
                            'title' => 'Bague artisanale',
                            'caption' => 'Bague montée sur un anneau en acier inoxydable.',
                            'width' => 800,
                            'height' => 600,
                        ],
                        'content' => '<figure><img src="/assets/images/boulyetcailloux/boulyetcailloux-sac-lineage.webp" alt="Sac en tissu" title="Sac Lineage" width="640" height="480"></figure>',
                    ],
                ],
            ],
            true
        );

        $this->assertSame([], $result['issues']);
        $this->assertSame(4, $result['reference_count']);
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
                            'alt' => 'Bague Bouly et Cailloux',
                            'title' => 'Bague artisanale',
                            'caption' => 'Bague montée sur un anneau en acier inoxydable.',
                            'width' => 800,
                            'height' => 600,
                        ],
                        'content' => '<img src="https://example.com/body.webp" alt="Sac en tissu" title="Sac Lineage" width="640" height="480">',
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

    public function testValidateReportsMissingIncompleteAndDuplicateBlogMedia(): void
    {
        file_put_contents($this->frontendRoot . '/boulyetcailloux/boulyetcailloux-bague-fuyu.webp', 'webp');
        file_put_contents($this->publicRoot . '/assets/images/boulyetcailloux/boulyetcailloux-bague-fuyu.webp', 'webp');

        $validator = new EditorialMediaValidator($this->frontendRoot, $this->publicRoot);
        $result = $validator->validate(
            [
                [
                    'scope' => 'blog',
                    'entity' => 'missing.fr',
                    'payload' => ['content' => '<p>Sans média.</p>'],
                ],
                [
                    'scope' => 'blog',
                    'entity' => 'duplicate.fr',
                    'payload' => [
                        'featured_image' => [
                            'src' => '/assets/images/boulyetcailloux/boulyetcailloux-bague-fuyu.webp',
                            'alt' => 'Bague Bouly et Cailloux',
                            'title' => 'Bague artisanale',
                            'caption' => 'Bague montée sur un anneau en acier inoxydable.',
                            'width' => 800,
                            'height' => 600,
                        ],
                        'content' => '<img src="/assets/images/boulyetcailloux/boulyetcailloux-bague-fuyu.webp" alt="" title="Bague" width="0" height="600">',
                    ],
                ],
            ],
            true
        );

        $issuesByEntity = [];
        foreach ($result['issues'] as $issue) {
            $issuesByEntity[(string) ($issue['entity'] ?? '')][] = (string) ($issue['type'] ?? '');
        }

        $this->assertContains('featured_image_missing', $issuesByEntity['missing.fr'] ?? []);
        $this->assertContains('body_image_missing', $issuesByEntity['missing.fr'] ?? []);
        $this->assertContains('featured_body_duplicate', $issuesByEntity['duplicate.fr'] ?? []);
        $this->assertContains('body_image_metadata_missing', $issuesByEntity['duplicate.fr'] ?? []);
        $this->assertContains('body_image_dimension_invalid', $issuesByEntity['duplicate.fr'] ?? []);
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
