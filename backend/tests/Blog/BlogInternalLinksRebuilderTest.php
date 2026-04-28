<?php

declare(strict_types=1);

use Caramagnols\Blog\BlogInternalLinksRebuilder;
use Caramagnols\Blog\JsonBlogRepository;
use Caramagnols\Content\PageRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';

final class BlogInternalLinksRebuilderTest extends TestCase
{
    private string $blogDir;
    private string $pagesFile;

    protected function setUp(): void
    {
        $this->blogDir = sys_get_temp_dir() . '/caramagnols-blog-internal-links-' . bin2hex(random_bytes(6));
        $this->pagesFile = ROOT_PATH . '/var/blog-internal-links-pages-' . bin2hex(random_bytes(6)) . '.json';
        mkdir($this->blogDir, 0777, true);

        file_put_contents(
            $this->pagesFile,
            (string) json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'auto-retro-austin',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'route' => '/auto-retro/austin',
                            'translations' => [
                                'fr' => [
                                    'title' => 'Austin',
                                    'route' => '/auto-retro/austin',
                                ],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
        );
    }

    protected function tearDown(): void
    {
        $files = glob($this->blogDir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }

        @rmdir($this->blogDir);

        if (is_file($this->pagesFile)) {
            @unlink($this->pagesFile);
        }
    }

    public function testRebuildConvertsBlogArticleLinkToParentRouteWhenTargetIsPublished(): void
    {
        $repository = new JsonBlogRepository($this->blogDir);
        $this->writeArticle([
            'slug' => 'article-source',
            'title' => 'Source',
            'status' => 'published',
            'page_slug' => 'auto-retro-austin',
            'content' => '<p>Voir <a href="/fr/blog/article/article-cible">ce texte</a>.</p>',
        ]);
        $this->writeArticle([
            'slug' => 'article-cible',
            'title' => 'Cible',
            'status' => 'published',
            'page_slug' => 'auto-retro-austin',
            'content' => '<p>Article cible.</p>',
        ]);

        $rebuilder = new BlogInternalLinksRebuilder($repository, new PageRepository($this->pagesFile));
        $result = $rebuilder->rebuild(strtotime('2026-04-01 10:00:00'), false);

        $this->assertSame(2, $result['attempted']);
        $this->assertSame(1, $result['changed']);
        $this->assertSame(1, $result['skipped']);
        $source = $repository->find('article-source', 'fr');
        $this->assertSame(
            '<p>Voir <a href="/fr/auto-retro/austin?open_article=article-cible#attached-article-article-cible">ce texte</a>.</p>',
            $source['content'] ?? ''
        );
        $this->assertSame([], $result['errors']);
    }

    public function testRebuildUsesParentRouteForFutureScheduledTargetWhenAvailable(): void
    {
        $repository = new JsonBlogRepository($this->blogDir);
        $this->writeArticle([
            'slug' => 'article-source',
            'title' => 'Source',
            'status' => 'published',
            'page_slug' => 'auto-retro-austin',
            'content' => '<p>Voir <a href="/fr/blog/article/article-cible">ce texte</a>.</p>',
        ]);
        $this->writeArticle([
            'slug' => 'article-cible',
            'title' => 'Cible',
            'status' => 'scheduled',
            'date' => '2026-06-01 10:00:00',
            'page_slug' => 'auto-retro-austin',
            'content' => '<p>Article cible futur.</p>',
        ]);

        $rebuilder = new BlogInternalLinksRebuilder($repository, new PageRepository($this->pagesFile));
        $result = $rebuilder->rebuild(strtotime('2026-04-01 10:00:00'), false);

        $this->assertSame(2, $result['attempted']);
        $this->assertSame(1, $result['changed']);
        $source = $repository->find('article-source', 'fr');
        $this->assertSame(
            '<p>Voir <a href="/fr/auto-retro/austin?open_article=article-cible#attached-article-article-cible">ce texte</a>.</p>',
            $source['content'] ?? ''
        );
    }

    public function testRebuildFallsBackToBlogIndexWhenFutureScheduledTargetHasNoPublishedParentPage(): void
    {
        $repository = new JsonBlogRepository($this->blogDir);
        $this->writeArticle([
            'slug' => 'article-source',
            'title' => 'Source',
            'status' => 'published',
            'page_slug' => 'auto-retro-austin',
            'content' => '<p>Voir <a href="/fr/blog/article/article-cible">ce texte</a>.</p>',
        ]);
        $this->writeArticle([
            'slug' => 'article-cible',
            'title' => 'Cible',
            'status' => 'scheduled',
            'date' => '2026-06-01 10:00:00',
            'page_slug' => 'page-inexistante',
            'content' => '<p>Article cible futur.</p>',
        ]);

        $rebuilder = new BlogInternalLinksRebuilder($repository, new PageRepository($this->pagesFile));
        $result = $rebuilder->rebuild(strtotime('2026-04-01 10:00:00'), false);

        $this->assertSame(2, $result['attempted']);
        $this->assertSame(1, $result['changed']);
        $source = $repository->find('article-source', 'fr');
        $this->assertSame(
            '<p>Voir <a href="/blog">ce texte</a>.</p>',
            $source['content'] ?? ''
        );
    }

    public function testRebuildProcessesOnlyPublishedOrScheduledArticles(): void
    {
        $repository = new JsonBlogRepository($this->blogDir);
        $this->writeArticle([
            'slug' => 'article-draft',
            'title' => 'Brouillon',
            'status' => 'draft',
            'page_slug' => 'auto-retro-austin',
            'content' => '<p>Voir <a href="/fr/blog/article/article-cible">ce texte</a>.</p>',
        ]);
        $this->writeArticle([
            'slug' => 'article-source',
            'title' => 'Source',
            'status' => 'published',
            'page_slug' => 'auto-retro-austin',
            'content' => '<p>Voir <a href="/fr/blog/article/article-cible">ce texte</a>.</p>',
        ]);
        $this->writeArticle([
            'slug' => 'article-cible',
            'title' => 'Cible',
            'status' => 'published',
            'page_slug' => 'auto-retro-austin',
            'content' => '<p>Article cible.</p>',
        ]);

        $rebuilder = new BlogInternalLinksRebuilder($repository, new PageRepository($this->pagesFile));
        $result = $rebuilder->rebuild(strtotime('2026-04-01 10:00:00'), false);

        $this->assertSame(2, $result['attempted']);
        $this->assertSame(1, $result['changed']);
        $source = $repository->find('article-source', 'fr');
        $this->assertStringContainsString('/auto-retro/austin?open_article=article-cible', (string) ($source['content'] ?? ''));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function writeArticle(array $overrides): void
    {
        $repository = new JsonBlogRepository($this->blogDir);
        $slug = (string) ($overrides['slug'] ?? '');
        $status = (string) ($overrides['status'] ?? 'draft');
        $repository->save(array_merge(
            [
                'title' => $slug !== '' ? $slug : 'Article',
                'slug' => $slug,
                'lang' => 'fr',
                'status' => $status,
                'date' => $status === 'scheduled'
                    ? date('Y-m-d H:i:s', strtotime('2026-05-01 10:00:00'))
                    : '2026-01-01 10:00:00',
                'content' => '<p>Article.</p>',
                'page_slug' => 'auto-retro-austin',
                'category' => 'auto-retro',
                'tags' => ['classic'],
                'featured_image' => [],
            ],
            $overrides
        ));
    }
}
