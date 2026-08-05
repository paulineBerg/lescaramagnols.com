<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\WebDevelopment\Http;

use Caramagnols\Http\Request;
use Caramagnols\Http\Response;
use Caramagnols\PrivateApps\WebDevelopment\Repository\WebDevelopmentProjectRepositoryInterface;
use Caramagnols\PrivateApps\WebDevelopment\Security\PreviewAccessGuardInterface;
use Caramagnols\PrivateApps\WebDevelopment\Service\PreviewFileService;

final class PreviewGatewayController
{
    public function __construct(
        private readonly string $previewHost,
        private readonly PreviewAccessGuardInterface $accessGuard,
        private readonly PreviewFileService $fileService,
        private readonly WebDevelopmentProjectRepositoryInterface $projectRepository
    ) {
    }

    public function matchesPreviewHost(Request $request): bool
    {
        $configuredHost = $this->normalizeHost($this->previewHost);
        if ($configuredHost === '') {
            return false;
        }

        $requestHost = $this->normalizeHost(
            (string) ($request->header('Host') ?? $request->server('HTTP_HOST', ''))
        );
        if ($requestHost !== $configuredHost) {
            return false;
        }

        if ($configuredHost !== $this->publicHost()) {
            return true;
        }

        $path = \request_path($request->uri());

        return preg_match('#\A/(?:_access|p)(?:/|\z)#', $path) === 1;
    }

    public function handle(Request $request): Response
    {
        if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $this->fileService->notFound();
        }

        $path = \request_path($request->uri());

        if ($path === '/robots.txt') {
            return $this->fileService->withPreviewHeaders(
                new Response(
                    200,
                    ['Content-Type' => 'text/plain; charset=UTF-8'],
                    "User-agent: *\nDisallow: /\n"
                )
            );
        }

        if (preg_match('#\A/_access/([A-Za-z0-9_-]{32,256})\z#', $path, $matches) === 1) {
            return $this->ticketResponse((string) $matches[1], $request);
        }

        if (preg_match('#\A/p/([a-z0-9][a-z0-9_-]{1,80})(?:/(.*))?\z#', $path, $matches) === 1) {
            $projectKey = (string) $matches[1];
            $assetPath = is_string($matches[2] ?? null) ? (string) $matches[2] : '';

            return $this->projectResponse($projectKey, $assetPath, $request);
        }

        return $this->fileService->notFound();
    }

    private function ticketResponse(string $ticket, Request $request): Response
    {
        $session = $this->accessGuard->consumeTicket($ticket, $request);
        if (!is_array($session) || ($session['session_token'] ?? '') === '') {
            return $this->fileService->notFound();
        }

        $location = '/p/' . rawurlencode((string) $session['project_key']) . '/';
        $response = new Response(
            302,
            [
                'Location' => $location,
                'Set-Cookie' => $this->accessGuard->sessionCookieHeader(
                    (string) $session['session_token'],
                    (int) $session['expires_at'],
                    $this->requestIsSecure($request)
                ),
            ],
            ''
        );

        return $this->fileService->withPreviewHeaders($response);
    }

    private function projectResponse(string $projectKey, string $assetPath, Request $request): Response
    {
        if (!$this->accessGuard->canAccessProject($request, $projectKey)) {
            return $this->diagnosticNotFound('access-denied');
        }

        $project = $this->projectRepository->findPreviewProjectByKey($projectKey);
        if (!is_array($project)) {
            return $this->diagnosticNotFound('project-not-found');
        }

        $response = $this->fileService->serve($project, $assetPath, $request->method());
        if ($response->status === 404) {
            $response->headers['X-Preview-Diagnostic'] = 'file-not-found';
        }

        return $response;
    }

    private function diagnosticNotFound(string $reason): Response
    {
        $response = $this->fileService->notFound();
        $response->headers['X-Preview-Diagnostic'] = $reason;

        return $response;
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return '';
        }

        if (preg_match('/^\[(.+)\](?::\d+)?$/', $host, $matches) === 1) {
            $host = (string) $matches[1];
        } elseif (substr_count($host, ':') === 1) {
            [$candidateHost, $candidatePort] = array_pad(explode(':', $host, 2), 2, '');
            if ($candidateHost !== '' && ctype_digit($candidatePort)) {
                $host = $candidateHost;
            }
        }

        return rtrim($host, '.');
    }

    private function requestIsSecure(Request $request): bool
    {
        $https = strtolower(trim((string) $request->server('HTTPS', '')));
        if ($https !== '' && $https !== 'off') {
            return true;
        }

        return (string) $request->server('SERVER_PORT', '') === '443';
    }

    private function publicHost(): string
    {
        $baseUrl = app_config('base_url', '');
        if (!is_string($baseUrl) || trim($baseUrl) === '') {
            return '';
        }

        $host = parse_url($baseUrl, PHP_URL_HOST);

        return is_string($host) ? $this->normalizeHost($host) : '';
    }
}
