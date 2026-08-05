<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PrivateApps\WebDevelopment;

use Caramagnols\Admin\AdminController;
use Caramagnols\Admin\AdminRouteResolver;
use Caramagnols\Blog\BlogApiController;
use Caramagnols\Blog\BlogSaveService;
use Caramagnols\Http\FrontController;
use Caramagnols\Http\Request;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\Logging\LoggerFactory;
use Caramagnols\PrivateApps\WebDevelopment\Http\PreviewGatewayController;
use Caramagnols\PrivateApps\WebDevelopment\Repository\WebDevelopmentProjectRepositoryInterface;
use Caramagnols\PrivateApps\WebDevelopment\Security\PreviewAccessGuardInterface;
use Caramagnols\PrivateApps\WebDevelopment\Service\PreviewFileService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../core/bootstrap.php';

final class PreviewGatewayControllerTest extends TestCase
{
    private string $tmpRoot;
    private FakeProjectRepository $projectRepository;
    private FakePreviewAccessGuard $accessGuard;

    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/caramagnols-preview-gateway-' . bin2hex(random_bytes(6));
        mkdir($this->tmpRoot . '/project-000123/releases/r1/public/assets', 0777, true);
        file_put_contents($this->tmpRoot . '/project-000123/releases/r1/public/index.html', '<h1>Preview OK</h1>');
        file_put_contents($this->tmpRoot . '/project-000123/releases/r1/public/assets/app.js', 'console.log("preview");');

        $this->projectRepository = new FakeProjectRepository([
            'id' => 123,
            'projectKey' => 'lordelaroche',
            'publicPath' => 'project-000123/releases/r1/public',
        ]);
        $this->accessGuard = new FakePreviewAccessGuard();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpRoot);
    }

    public function testPreviewHostIsHandledBeforePublicRobotsRoute(): void
    {
        $response = $this->frontController()->handle(
            $this->request('GET', '/robots.txt', ['Host' => 'preview.lescaramagnols.com'])
        );

        self::assertSame(200, $response->status);
        self::assertSame("User-agent: *\nDisallow: /\n", $response->body);
        self::assertSame('private, no-store, no-cache, must-revalidate', $response->headers['Cache-Control'] ?? null);
    }

    public function testForwardedHostDoesNotSelectPreviewGateway(): void
    {
        $response = $this->frontController()->handle(
            $this->request(
                'GET',
                '/robots.txt',
                [
                    'Host' => 'www.lescaramagnols.com',
                    'X-Forwarded-Host' => 'preview.lescaramagnols.com',
                ]
            )
        );

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Allow: /', $response->body);
        self::assertStringNotContainsString('Disallow: /', $response->body);
    }

    public function testUnknownPreviewPathReturnsNeutralNotFound(): void
    {
        $response = $this->gateway()->handle($this->request('GET', '/admin', ['Host' => 'preview.lescaramagnols.com']));

        self::assertSame(404, $response->status);
        self::assertSame('Not found', $response->body);
        self::assertSame('noindex, nofollow, noarchive, noimageindex', $response->headers['X-Robots-Tag'] ?? null);
    }

    public function testPublicHostOnlyMatchesPrivatePreviewPaths(): void
    {
        global $appConfig;
        $previousBaseUrl = $appConfig['base_url'] ?? null;
        $appConfig['base_url'] = 'https://www.lescaramagnols.com';

        try {
            $gateway = $this->gateway('www.lescaramagnols.com');

            self::assertFalse($gateway->matchesPreviewHost(
                $this->request('GET', '/', ['Host' => 'www.lescaramagnols.com'])
            ));
            self::assertFalse($gateway->matchesPreviewHost(
                $this->request('GET', '/robots.txt', ['Host' => 'www.lescaramagnols.com'])
            ));
            self::assertTrue($gateway->matchesPreviewHost(
                $this->request(
                    'GET',
                    '/_access/abcdefghijklmnopqrstuvwxyzABCDEF012345',
                    ['Host' => 'www.lescaramagnols.com']
                )
            ));
            self::assertTrue($gateway->matchesPreviewHost(
                $this->request('GET', '/p/lordelaroche/', ['Host' => 'www.lescaramagnols.com'])
            ));
        } finally {
            if ($previousBaseUrl === null) {
                unset($appConfig['base_url']);
            } else {
                $appConfig['base_url'] = $previousBaseUrl;
            }
        }
    }

    public function testProjectPreviewRequiresAuthorizedPreviewSession(): void
    {
        $this->accessGuard->allowProjectAccess = false;

        $response = $this->gateway()->handle(
            $this->request('GET', '/p/lordelaroche/', ['Host' => 'preview.lescaramagnols.com'])
        );

        self::assertSame(404, $response->status);
        self::assertSame('Not found', $response->body);
    }

    public function testAuthorizedProjectPreviewServesStaticFilesThroughGateway(): void
    {
        $this->accessGuard->allowProjectAccess = true;

        $index = $this->gateway()->handle(
            $this->request('GET', '/p/lordelaroche/', ['Host' => 'preview.lescaramagnols.com'])
        );
        $asset = $this->gateway()->handle(
            $this->request('GET', '/p/lordelaroche/assets/app.js', ['Host' => 'preview.lescaramagnols.com'])
        );

        self::assertSame(200, $index->status);
        self::assertStringContainsString('Preview OK', $index->body);
        self::assertSame('text/html; charset=UTF-8', $index->headers['Content-Type'] ?? null);
        self::assertSame(200, $asset->status);
        self::assertSame('console.log("preview");', $asset->body);
        self::assertSame('text/javascript; charset=UTF-8', $asset->headers['Content-Type'] ?? null);
    }

    public function testAccessTicketCreatesPreviewSessionCookieAndRedirectsToProject(): void
    {
        $response = $this->gateway()->handle(
            $this->request('GET', '/_access/abcdefghijklmnopqrstuvwxyzABCDEF012345', ['Host' => 'preview.lescaramagnols.com'])
        );

        self::assertSame(302, $response->status);
        self::assertSame('/p/lordelaroche/', $response->headers['Location'] ?? null);
        self::assertStringStartsWith('preview_session=session-token;', $response->headers['Set-Cookie'] ?? '');
        self::assertStringContainsString('HttpOnly', $response->headers['Set-Cookie'] ?? '');
        self::assertStringContainsString('SameSite=Lax', $response->headers['Set-Cookie'] ?? '');
    }

    private function gateway(string $previewHost = 'preview.lescaramagnols.com'): PreviewGatewayController
    {
        return new PreviewGatewayController(
            $previewHost,
            $this->accessGuard,
            new PreviewFileService($this->tmpRoot),
            $this->projectRepository
        );
    }

    private function frontController(): FrontController
    {
        $routeResolver = new AdminRouteResolver('admin');
        $logger = new AppEventLogger(new LoggerFactory($this->tmpRoot . '/logs', 'test'));

        return new FrontController(
            $routeResolver,
            new AdminController($routeResolver),
            new BlogApiController(new BlogSaveService(blog_repository(), $logger), $logger),
            $logger,
            null,
            null,
            null,
            $this->gateway()
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(string $method, string $uri, array $headers): Request
    {
        return new Request(
            [
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $uri,
                'REMOTE_ADDR' => '127.0.0.1',
                'SERVER_PORT' => '443',
                'HTTPS' => 'on',
            ],
            [],
            [],
            [],
            $headers
        );
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path . '/' . $item;
            if (is_dir($child)) {
                $this->removeDirectory($child);
            } elseif (is_file($child)) {
                unlink($child);
            }
        }

        rmdir($path);
    }
}

final class FakeProjectRepository implements WebDevelopmentProjectRepositoryInterface
{
    /**
     * @param array{id: int, projectKey: string, publicPath: string} $project
     */
    public function __construct(private readonly array $project)
    {
    }

    public function findPreviewProjectsForUser(int $privateUserId): array
    {
        return $privateUserId > 0
            ? [[
                'id' => $this->project['id'],
                'projectKey' => $this->project['projectKey'],
                'displayName' => 'Lor de la Roche',
                'description' => '',
            ]]
            : [];
    }

    public function findPreviewProjectById(int $projectId): ?array
    {
        return $projectId === $this->project['id'] ? $this->project : null;
    }

    public function findPreviewProjectByKey(string $projectKey): ?array
    {
        return $projectKey === $this->project['projectKey'] ? $this->project : null;
    }

    public function findPreviewProjectByKeyForUser(int $privateUserId, string $projectKey): ?array
    {
        return $privateUserId > 0 && $projectKey === $this->project['projectKey'] ? $this->project : null;
    }
}

final class FakePreviewAccessGuard implements PreviewAccessGuardInterface
{
    public bool $allowProjectAccess = true;

    public function consumeTicket(string $ticket, Request $request): ?array
    {
        return [
            'project_key' => 'lordelaroche',
            'session_token' => 'session-token',
            'expires_at' => time() + 3600,
        ];
    }

    public function canAccessProject(Request $request, string $projectKey): bool
    {
        return $this->allowProjectAccess && $projectKey === 'lordelaroche';
    }

    public function sessionCookieName(): string
    {
        return 'preview_session';
    }

    public function sessionCookieHeader(string $sessionToken, int $expiresAt, bool $secure): string
    {
        return 'preview_session=' . $sessionToken . '; Path=/; HttpOnly; SameSite=Lax' . ($secure ? '; Secure' : '');
    }
}
