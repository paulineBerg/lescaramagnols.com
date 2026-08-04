<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\WebDevelopment\Http;

use Caramagnols\Http\Request;
use Caramagnols\Http\Response;
use Caramagnols\PrivateApps\WebDevelopment\Repository\PreviewTicketRepositoryInterface;
use Caramagnols\PrivateApps\WebDevelopment\Repository\WebDevelopmentProjectRepositoryInterface;

final class PreviewOpenController
{
    private const METHOD_POST = 'POST';
    private const CSRF_SCOPE = 'private_web_development_preview';

    public function __construct(
        private readonly WebDevelopmentProjectRepositoryInterface $projectRepository,
        private readonly PreviewTicketRepositoryInterface $ticketRepository,
        private readonly string $previewHost,
        private readonly int $ticketTtlSeconds,
        private readonly string $fallbackUrl
    ) {
    }

    public function open(Request $request, int $userId, string $projectKey): Response
    {
        if ($request->method() !== self::METHOD_POST) {
            return $this->redirect($this->fallbackUrl . '?error=web_development_invalid_request');
        }

        $csrfToken = is_string($request->body()['csrf_token'] ?? null) ? (string) $request->body()['csrf_token'] : null;
        if (!csrf_validate($csrfToken, self::CSRF_SCOPE)) {
            return $this->redirect($this->fallbackUrl . '?error=web_development_invalid_csrf');
        }

        $userId = max(0, $userId);
        if ($userId <= 0) {
            return $this->redirect($this->fallbackUrl . '?error=web_development_unauthorized');
        }

        $project = $this->projectRepository->findPreviewProjectByKeyForUser($userId, $projectKey);
        if (!is_array($project)) {
            return $this->redirect($this->fallbackUrl . '?error=web_development_project_forbidden');
        }

        $projectId = (int) ($project['id'] ?? 0);
        if ($projectId <= 0) {
            return $this->redirect($this->fallbackUrl . '?error=web_development_project_forbidden');
        }

        $ticket = $this->ticketRepository->create($projectId, $userId, $this->ticketTtlSeconds);
        if ($ticket === '') {
            return $this->redirect($this->fallbackUrl . '?error=web_development_ticket_failed');
        }

        $previewHost = $this->normalizeHost($this->previewHost);
        if ($previewHost === '') {
            return $this->redirect($this->fallbackUrl . '?error=web_development_preview_host_missing');
        }

        return new Response(302, ['Location' => 'https://' . $previewHost . '/_access/' . rawurlencode($ticket)], '');
    }

    private function redirect(string $url): Response
    {
        return new Response(302, ['Location' => $url], '');
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return '';
        }

        if (preg_match('/^\[(.+)\](?::[0-9]+)?$/', $host, $matches) === 1) {
            return (string) $matches[1];
        }

        if (substr_count($host, ':') === 1) {
            [$hostName, $hostPort] = array_pad(explode(':', $host, 2), 2, '');
            if ($hostName !== '' && ctype_digit($hostPort)) {
                return $hostName;
            }
        }

        return $host;
    }
}
