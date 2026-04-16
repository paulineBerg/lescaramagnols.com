<?php

declare(strict_types=1);

use Caramagnols\Blog\JsonBlogRepository;
use Caramagnols\Content\PageRepository;
use Caramagnols\Http\LegacyRouteResolver;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';

final class LegacyRouteResolverTest extends TestCase
{
    private string $pagesFile;
    private string $blogDir;

    protected function setUp(): void
    {
        $this->pagesFile = ROOT_PATH . '/var/legacy-route-pages-' . uniqid() . '.json';
        $this->blogDir = sys_get_temp_dir() . '/caramagnols-legacy-route-blog-' . bin2hex(random_bytes(6));
        mkdir($this->blogDir, 0777, true);

        file_put_contents(
            $this->pagesFile,
            json_encode(
                [
                    'meta' => ['version' => 2],
                    'pages' => [
                        [
                            'slug' => 'home',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'route' => '/',
                            'translations' => [
                                'fr' => ['title' => 'Accueil'],
                            ],
                        ],
                        [
                            'slug' => 'association',
                            'type' => 'structured_page',
                            'status' => 'published',
                            'route' => '/association',
                            'translations' => [
                                'fr' => ['title' => 'Association'],
                            ],
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['currentDynamicPage'], $GLOBALS['currentBlogArticle'], $GLOBALS['currentBlogArticles'], $GLOBALS['currentBlogFilters']);

        if (file_exists($this->pagesFile)) {
            unlink($this->pagesFile);
        }

        $blogFiles = glob($this->blogDir . '/*');
        if (is_array($blogFiles)) {
            foreach ($blogFiles as $file) {
                @unlink($file);
            }
        }

        @rmdir($this->blogDir);
    }

    public function testResolveReturnsDynamicTemplateForPublishedRouteWithLanguagePrefix(): void
    {
        $resolver = $this->resolver();

        $route = $resolver->resolve('/en/association');

        $this->assertSame('pages/dynamic.php', $route);
        $this->assertSame('association', (string) ($GLOBALS['currentDynamicPage']['slug'] ?? ''));
    }

    public function testResolveReturnsBlogArticleTemplateAndHydratesContext(): void
    {
        file_put_contents(
            $this->blogDir . '/histoire.fr.json',
            json_encode(
                [
                    'title' => 'Histoire',
                    'slug' => 'histoire',
                    'lang' => 'fr',
                    'status' => 'published',
                    'date' => '2026-03-19 09:00:00',
                    'content' => '<p>Contenu.</p>',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        $resolver = $this->resolver();

        $route = $resolver->resolve('/blog/article/histoire');

        $this->assertSame('pages/blog/article.php', $route);
        $this->assertSame('histoire', (string) ($GLOBALS['currentBlogArticle']['slug'] ?? ''));
    }

    public function testResolveFallsBackToDefaultLanguageForBlogArticleWhenLocaleIsMissing(): void
    {
        file_put_contents(
            $this->blogDir . '/histoire.fr.json',
            json_encode(
                [
                    'title' => 'Histoire',
                    'slug' => 'histoire',
                    'lang' => 'fr',
                    'status' => 'published',
                    'date' => '2026-03-19 09:00:00',
                    'content' => '<p>Contenu.</p>',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        $resolver = $this->resolver();

        $route = $resolver->resolve('/de/blog/article/histoire');

        $this->assertSame('pages/blog/article.php', $route);
        $this->assertSame('histoire', (string) ($GLOBALS['currentBlogArticle']['slug'] ?? ''));
        $this->assertSame('fr', (string) ($GLOBALS['currentBlogArticle']['lang'] ?? ''));
    }

    public function testResolveFallsBackToDefaultLanguageForBlogIndexWhenLocaleIsMissing(): void
    {
        file_put_contents(
            $this->blogDir . '/histoire.fr.json',
            json_encode(
                [
                    'title' => 'Histoire',
                    'slug' => 'histoire',
                    'lang' => 'fr',
                    'status' => 'published',
                    'date' => '2026-03-19 09:00:00',
                    'content' => '<p>Contenu.</p>',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        $resolver = $this->resolver();

        $route = $resolver->resolve('/de/blog');

        $this->assertSame('pages/blog/index.php', $route);
        $this->assertCount(1, $GLOBALS['currentBlogArticles']);
        $this->assertSame('fr', (string) ($GLOBALS['currentBlogArticles'][0]['lang'] ?? ''));
    }

    public function testResolveBlocksLegacyAdminUrls(): void
    {
        $resolver = $this->resolver();

        $this->assertSame('pages/404.php', $resolver->resolve('/admin'));
        $this->assertSame('pages/404.php', $resolver->resolve('/admin/dashboard'));
    }

    public function testResolveAppliesBlogFiltersFromQueryString(): void
    {
        file_put_contents(
            $this->blogDir . '/sortie.fr.json',
            json_encode(
                [
                    'title' => 'Sortie',
                    'slug' => 'sortie',
                    'lang' => 'fr',
                    'status' => 'published',
                    'category' => 'Sorties',
                    'tags' => ['Club', 'Rassemblement'],
                    'date' => '2026-03-19 09:00:00',
                    'content' => '<p>Sortie.</p>',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
        file_put_contents(
            $this->blogDir . '/atelier.fr.json',
            json_encode(
                [
                    'title' => 'Atelier',
                    'slug' => 'atelier',
                    'lang' => 'fr',
                    'status' => 'published',
                    'category' => 'Technique',
                    'tags' => ['Moteur'],
                    'date' => '2026-03-19 10:00:00',
                    'content' => '<p>Atelier.</p>',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        $resolver = $this->resolver();
        $route = $resolver->resolve('/blog?category=Sorties&tag=Club');

        $this->assertSame('pages/blog/index.php', $route);
        $this->assertCount(1, $GLOBALS['currentBlogArticles']);
        $this->assertSame('sortie', (string) ($GLOBALS['currentBlogArticles'][0]['slug'] ?? ''));
        $this->assertSame('Sorties', (string) ($GLOBALS['currentBlogFilters']['category'] ?? ''));
        $this->assertSame('Club', (string) ($GLOBALS['currentBlogFilters']['tag'] ?? ''));
    }

    public function testResolveAppliesBlogFiltersFromSeoPath(): void
    {
        file_put_contents(
            $this->blogDir . '/sortie.fr.json',
            json_encode(
                [
                    'title' => 'Sortie',
                    'slug' => 'sortie',
                    'lang' => 'fr',
                    'status' => 'published',
                    'category' => 'Sorties club',
                    'tags' => ['Rassemblement 2026'],
                    'date' => '2026-03-19 09:00:00',
                    'content' => '<p>Sortie.</p>',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
        file_put_contents(
            $this->blogDir . '/atelier.fr.json',
            json_encode(
                [
                    'title' => 'Atelier',
                    'slug' => 'atelier',
                    'lang' => 'fr',
                    'status' => 'published',
                    'category' => 'Technique',
                    'tags' => ['Moteur'],
                    'date' => '2026-03-19 10:00:00',
                    'content' => '<p>Atelier.</p>',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        $resolver = $this->resolver();
        $route = $resolver->resolve('/blog/categorie/sorties-club/tag/rassemblement-2026');

        $this->assertSame('pages/blog/index.php', $route);
        $this->assertCount(1, $GLOBALS['currentBlogArticles']);
        $this->assertSame('sortie', (string) ($GLOBALS['currentBlogArticles'][0]['slug'] ?? ''));
        $this->assertSame('Sorties club', (string) ($GLOBALS['currentBlogFilters']['category'] ?? ''));
        $this->assertSame('Rassemblement 2026', (string) ($GLOBALS['currentBlogFilters']['tag'] ?? ''));
    }

    private function resolver(): LegacyRouteResolver
    {
        return new LegacyRouteResolver(
            new PageRepository($this->pagesFile),
            new JsonBlogRepository($this->blogDir),
            ['fr', 'en', 'de'],
            'fr'
        );
    }
}
