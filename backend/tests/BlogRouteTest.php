<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';
require_once ROOT_PATH . '/core/router.php';

final class BlogRouteTest extends TestCase
{
    private string $blogDir;

    protected function setUp(): void
    {
        $this->blogDir = sys_get_temp_dir() . '/caramagnols-blog-route-' . bin2hex(random_bytes(6));
        mkdir($this->blogDir, 0777, true);

        global $appConfig;
        $appConfig['blog']['data_dir'] = $this->blogDir;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['currentBlogArticles'], $GLOBALS['currentBlogArticle'], $GLOBALS['currentBlogFilters']);

        $files = glob($this->blogDir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }

        @rmdir($this->blogDir);
    }

    public function testPublishedArticleRouteResolvesToBlogArticleTemplate(): void
    {
        file_put_contents(
            $this->blogDir . '/bonjour.fr.json',
            json_encode(
                [
                    'title' => 'Bonjour',
                    'slug' => 'bonjour',
                    'lang' => 'fr',
                    'status' => 'published',
                    'date' => '2026-03-17 10:00:00',
                    'content' => '<p>Bonjour.</p>',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        $route = resolve_route('/blog/article/bonjour');

        $this->assertSame('pages/blog/article.php', $route);
        $this->assertSame('Bonjour', $GLOBALS['currentBlogArticle']['title']);
    }

    public function testBlogRoutesExposeChildrenUnderTheirParent(): void
    {
        file_put_contents(
            $this->blogDir . '/parent.fr.json',
            json_encode(
                [
                    'title' => 'Parent',
                    'slug' => 'parent',
                    'lang' => 'fr',
                    'status' => 'published',
                    'date' => '2026-03-18 10:00:00',
                    'content' => '<p>Parent.</p>',
                    'created_at' => '2026-03-18T10:00:00+00:00',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
        file_put_contents(
            $this->blogDir . '/enfant.fr.json',
            json_encode(
                [
                    'title' => 'Enfant',
                    'slug' => 'enfant',
                    'lang' => 'fr',
                    'status' => 'published',
                    'date' => '2026-03-19 10:00:00',
                    'content' => '<p>Enfant.</p>',
                    'parent_slug' => 'parent',
                    'parent_lang' => 'fr',
                    'created_at' => '2026-03-19T10:00:00+00:00',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        $this->assertSame('pages/blog/index.php', resolve_route('/blog'));
        $this->assertCount(1, $GLOBALS['currentBlogArticles']);
        $this->assertSame('parent', $GLOBALS['currentBlogArticles'][0]['slug']);
        $this->assertSame('enfant', $GLOBALS['currentBlogArticles'][0]['child_articles'][0]['slug']);

        $this->assertSame('pages/blog/article.php', resolve_route('/blog/article/parent'));
        $this->assertCount(1, $GLOBALS['currentBlogArticle']['child_articles']);
        $this->assertSame('enfant', $GLOBALS['currentBlogArticle']['child_articles'][0]['slug']);
    }

    public function testBlogCategoryAndTagRoutesAcceptCanonicalTaxonomySlugs(): void
    {
        file_put_contents(
            $this->blogDir . '/austin.fr.json',
            json_encode(
                [
                    'title' => 'Austin',
                    'slug' => 'austin',
                    'lang' => 'fr',
                    'status' => 'published',
                    'category' => 'Histoire de marque',
                    'tags' => ['Austin', 'Longbridge', 'BMC'],
                    'date' => '2026-03-17 10:00:00',
                    'content' => '<p>Austin.</p>',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        $this->assertSame('pages/blog/index.php', resolve_route('/blog/categorie/auto-retro/tag/histoire'));
        $this->assertCount(1, $GLOBALS['currentBlogArticles']);
        $this->assertSame('austin', $GLOBALS['currentBlogArticles'][0]['slug']);
        $this->assertSame('auto-retro', $GLOBALS['currentBlogFilters']['category']);
        $this->assertSame('histoire', $GLOBALS['currentBlogFilters']['tag']);
    }

    public function testLegacyAdminTemplatesAreNoLongerRoutable(): void
    {
        $this->assertSame('pages/404.php', resolve_route('/admin/dashboard'));
        $this->assertSame('pages/404.php', resolve_route('/admin/articles'));
        $this->assertSame('pages/404.php', resolve_route('/blog/article'));
    }
}
