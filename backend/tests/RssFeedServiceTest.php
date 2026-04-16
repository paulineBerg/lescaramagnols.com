<?php

declare(strict_types=1);

use Caramagnols\Feed\RssFeedService;
use Caramagnols\Blog\JsonBlogRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class RssFeedServiceTest extends TestCase
{
    private string $blogDataDir;

    protected function setUp(): void
    {
        $this->blogDataDir = sys_get_temp_dir() . '/caramagnols-rss-' . bin2hex(random_bytes(6));

        mkdir($this->blogDataDir, 0777, true);

        file_put_contents(
            $this->blogDataDir . '/bonjour.fr.json',
            json_encode(
                [
                    'status' => 'published',
                    'title' => 'Bonjour',
                    'slug' => 'bonjour',
                    'date' => '2026-03-10 08:30:00',
                    'content' => '<p>Contenu <strong>bonjour</strong> pour le flux.</p>',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        file_put_contents(
            $this->blogDataDir . '/nouveau.fr.json',
            json_encode(
                [
                    'status' => 'published',
                    'title' => 'Nouveau',
                    'slug' => 'nouveau',
                    'date' => '2026-03-12 09:15:00',
                    'content' => '<p>Dernier article publié.</p>',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        file_put_contents(
            $this->blogDataDir . '/brouillon.fr.json',
            json_encode(
                [
                    'status' => 'draft',
                    'title' => 'Brouillon',
                    'slug' => 'brouillon',
                    'date' => '2026-03-11 09:15:00',
                    'content' => '<p>Ne doit pas sortir.</p>',
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        file_put_contents($this->blogDataDir . '/invalide.fr.json', '{invalid json');
    }

    protected function tearDown(): void
    {
        $files = glob($this->blogDataDir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }

        @rmdir($this->blogDataDir);
    }

    public function testRenderIncludesOnlyPublishedArticlesSortedByDateDesc(): void
    {
        $service = new RssFeedService(new JsonBlogRepository($this->blogDataDir), 'https://example.test');

        $xml = $service->render('fr');

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<title>Nouveau</title>', $xml);
        $this->assertStringContainsString('<title>Bonjour</title>', $xml);
        $this->assertStringNotContainsString('Brouillon', $xml);
        $this->assertStringContainsString('https://example.test/fr/blog/article/nouveau', $xml);
        $this->assertStringContainsString('Contenu bonjour pour le flux.', $xml);
        $this->assertLessThan(
            strpos($xml, '<title>Bonjour</title>'),
            strpos($xml, '<title>Nouveau</title>')
        );
    }

    public function testResponseFallsBackToDefaultLanguageForUnknownRequestedLanguage(): void
    {
        $service = new RssFeedService(
            new JsonBlogRepository($this->blogDataDir),
            'https://example.test',
            ['fr', 'en', 'de'],
            'fr'
        );

        $response = $service->response('es');

        $this->assertSame(200, $response->status);
        $this->assertSame('application/rss+xml; charset=UTF-8', $response->headers['Content-Type']);
        $this->assertStringContainsString('<language>fr</language>', $response->body);
    }
}
