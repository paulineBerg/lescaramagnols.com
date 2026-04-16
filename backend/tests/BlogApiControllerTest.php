<?php

declare(strict_types=1);

use Caramagnols\Blog\BlogApiController;
use Caramagnols\Blog\BlogSaveService;
use Caramagnols\Blog\JsonBlogRepository;
use Caramagnols\Http\Request;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\Logging\LoggerFactory;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';
require_once ROOT_PATH . '/core/auth/admin.php';

final class BlogApiControllerTest extends TestCase
{
    private string $blogDir;
    private string $logDir;

    protected function setUp(): void
    {
        ensure_session_started();
        $_SESSION = [];

        global $appConfig;
        $appConfig['admin']['email'] = 'admin@example.com';
        $appConfig['admin']['password_hash'] = password_hash('topsecret', PASSWORD_DEFAULT);
        $appConfig['admin']['session_key'] = '_blog_api_test';

        $this->blogDir = sys_get_temp_dir() . '/caramagnols-blog-api-' . bin2hex(random_bytes(6));
        $this->logDir = sys_get_temp_dir() . '/caramagnols-blog-api-logs-' . bin2hex(random_bytes(6));

        mkdir($this->blogDir, 0777, true);
        mkdir($this->logDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];

        foreach ([$this->blogDir, $this->logDir] as $dir) {
            $files = glob($dir . '/*');
            if (is_array($files)) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }

            @rmdir($dir);
        }
    }

    public function testSaveArticleRequiresAuthentication(): void
    {
        $controller = $this->controller();

        $response = $controller->saveArticle(
            $this->jsonRequest('POST', '/admin/articles/save', ['title' => 'Test'])
        );

        $this->assertSame(401, $response->status);
    }

    public function testSaveArticlePersistsJsonWhenAuthenticatedAndCsrfIsValid(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();
        $token = admin_csrf_token();

        $response = $controller->saveArticle(
            $this->jsonRequest(
                'POST',
                '/admin/articles/save',
                [
                    'csrf_token' => $token,
                    'title' => 'Article test',
                    'slug' => 'article-test',
                    'lang' => 'fr',
                    'status' => 'published',
                    'content' => '<p>Contenu.</p>',
                ]
            )
        );

        $this->assertSame(201, $response->status);
        $this->assertFileExists($this->blogDir . '/article-test.fr.json');
        $this->assertStringContainsString('"storage":"json"', $response->body);
    }

    public function testSaveArticleRejectsInvalidCsrfToken(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();

        $response = $controller->saveArticle(
            $this->jsonRequest(
                'POST',
                '/admin/articles/save',
                [
                    'csrf_token' => 'invalid',
                    'title' => 'Article test',
                    'slug' => 'article-test',
                    'lang' => 'fr',
                    'status' => 'draft',
                    'content' => '<p>Contenu.</p>',
                ]
            )
        );

        $this->assertSame(403, $response->status);
    }

    public function testSaveArticleAcceptsScheduledStatusWithPlannedDate(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $controller = $this->controller();
        $token = admin_csrf_token();

        $response = $controller->saveArticle(
            $this->jsonRequest(
                'POST',
                '/admin/articles/save',
                [
                    'csrf_token' => $token,
                    'title' => 'Article planifie',
                    'slug' => 'article-planifie',
                    'lang' => 'fr',
                    'status' => 'scheduled',
                    'date' => '2099-01-01 10:00:00',
                    'content' => '<p>Contenu.</p>',
                ]
            )
        );

        $this->assertSame(201, $response->status);
        $this->assertFileExists($this->blogDir . '/article-planifie.fr.json');

        $saved = json_decode((string) file_get_contents($this->blogDir . '/article-planifie.fr.json'), true);
        $this->assertIsArray($saved);
        $this->assertSame('scheduled', $saved['status'] ?? null);
        $this->assertSame('2099-01-01 10:00:00', $saved['date'] ?? null);
    }

    private function controller(): BlogApiController
    {
        $logger = new AppEventLogger(new LoggerFactory($this->logDir, 'test'));

        return new BlogApiController(
            new BlogSaveService(new JsonBlogRepository($this->blogDir), $logger),
            $logger
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonRequest(string $method, string $uri, array $payload): Request
    {
        return new Request(
            [
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $uri,
            ],
            [],
            [],
            [],
            ['Host' => '127.0.0.1:8000', 'Content-Type' => 'application/json'],
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''
        );
    }
}
