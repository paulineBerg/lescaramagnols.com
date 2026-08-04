<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PrivateApps\WebDevelopment;

use Caramagnols\Http\Request;
use Caramagnols\PrivateApps\WebDevelopment\Http\PreviewOpenController;
use Caramagnols\PrivateApps\WebDevelopment\Repository\PreviewTicketRepositoryInterface;
use Caramagnols\PrivateApps\WebDevelopment\Repository\WebDevelopmentProjectRepositoryInterface;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../core/bootstrap.php';

final class PreviewOpenControllerTest extends TestCase
{
    public function testOpenPreviewRedirectsToAccessHostWithTicket(): void
    {
        $controller = $this->createController('preview.lescaramagnols.com', 'ticket-xyz');

        $response = $controller->open(
            $this->request('POST', ['csrf_token' => csrf_token('private_web_development_preview')]),
            123,
            'project-000123-lor-de-la-roche'
        );

        self::assertSame(302, $response->status);
        self::assertSame('https://preview.lescaramagnols.com/_access/ticket-xyz', $response->headers['Location'] ?? null);
    }

    public function testInvalidCsrfReturnsFallbackError(): void
    {
        $controller = $this->createController('preview.lescaramagnols.com', 'ticket-xyz');
        $response = $controller->open(
            $this->request('POST', ['csrf_token' => 'bad-token']),
            123,
            'project-000123-lor-de-la-roche'
        );

        self::assertSame(302, $response->status);
        self::assertStringContainsString('error=web_development_invalid_csrf', $response->headers['Location'] ?? '');
    }

    public function testUnauthorizedUserCannotOpenProjectPreview(): void
    {
        $controller = $this->createController('preview.lescaramagnols.com', 'ticket-xyz');
        $response = $controller->open(
            $this->request('POST', ['csrf_token' => csrf_token('private_web_development_preview')]),
            123,
            'project-forbidden'
        );

        self::assertSame(302, $response->status);
        self::assertStringContainsString('error=web_development_project_forbidden', $response->headers['Location'] ?? '');
    }

    public function testMissingPreviewHostFallsBackWithError(): void
    {
        $controller = $this->createController('', 'ticket-xyz');
        $response = $controller->open(
            $this->request('POST', ['csrf_token' => csrf_token('private_web_development_preview')]),
            123,
            'project-000123-lor-de-la-roche'
        );

        self::assertSame(302, $response->status);
        self::assertStringContainsString('error=web_development_preview_host_missing', $response->headers['Location'] ?? '');
    }

    private function createController(string $previewHost, string $ticket): PreviewOpenController
    {
        return new PreviewOpenController(
            new PreviewOpenFakeProjectRepository(123),
            new FakeTicketRepository($ticket),
            $previewHost,
            60,
            'https://www.lescaramagnols.com/private/web-development'
        );
    }

    /**
     * @param array<string, string> $body
     */
    private function request(string $method, array $body = []): Request
    {
        return new Request(
            [
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => '/private/web-development/preview/project-000123-lor-de-la-roche',
                'REMOTE_ADDR' => '127.0.0.1',
                'SERVER_PORT' => '443',
                'HTTPS' => 'on',
            ],
            [],
            $body,
            [],
            []
        );
    }
}

final class PreviewOpenFakeProjectRepository implements WebDevelopmentProjectRepositoryInterface
{
    public function __construct(
        private readonly int $allowedProjectId,
        private readonly int $allowedUserId = 123,
    ) {
    }

    public function findPreviewProjectById(int $projectId): ?array
    {
        return $projectId === $this->allowedProjectId
            ? [
                'id' => $this->allowedProjectId,
                'projectKey' => 'project-000123-lor-de-la-roche',
                'publicPath' => '/tmp',
            ]
            : null;
    }

    public function findPreviewProjectByKey(string $projectKey): ?array
    {
        return $projectKey === 'project-000123-lor-de-la-roche'
            ? [
                'id' => $this->allowedProjectId,
                'projectKey' => 'project-000123-lor-de-la-roche',
                'publicPath' => '/tmp',
            ]
            : null;
    }

    public function findPreviewProjectByKeyForUser(int $privateUserId, string $projectKey): ?array
    {
        return $privateUserId === $this->allowedUserId && $projectKey === 'project-000123-lor-de-la-roche'
            ? [
                'id' => $this->allowedProjectId,
                'projectKey' => 'project-000123-lor-de-la-roche',
                'publicPath' => '/tmp',
            ]
            : null;
    }
}

final class FakeTicketRepository implements PreviewTicketRepositoryInterface
{
    public function __construct(private readonly string $ticket)
    {
    }

    public function create(int $projectId, int $privateUserId, int $ttlSeconds): string
    {
        if ($projectId <= 0 || $privateUserId <= 0 || $ttlSeconds <= 0) {
            return '';
        }

        return $this->ticket;
    }
}
