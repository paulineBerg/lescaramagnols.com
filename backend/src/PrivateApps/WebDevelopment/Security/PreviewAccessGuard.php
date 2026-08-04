<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\WebDevelopment\Security;

use Caramagnols\Http\Request;
use Caramagnols\PrivateApps\WebDevelopment\Repository\PreviewSessionRepository;
use Caramagnols\PrivateApps\WebDevelopment\Repository\PreviewTicketRepository;
use Caramagnols\PrivateApps\WebDevelopment\Repository\WebDevelopmentProjectRepository;

final class PreviewAccessGuard implements PreviewAccessGuardInterface
{
    public function __construct(
        private readonly WebDevelopmentProjectRepository $projectRepository,
        private readonly PreviewTicketRepository $ticketRepository,
        private readonly PreviewSessionRepository $sessionRepository,
        private readonly string $cookieName,
        private readonly int $sessionTtlSeconds
    ) {
    }

    public function consumeTicket(string $ticket, Request $request): ?array
    {
        $ticket = trim($ticket);
        if ($ticket === '' || preg_match('/\A[A-Za-z0-9_-]{32,256}\z/', $ticket) !== 1) {
            return null;
        }

        $clientIp = $request->clientIp((bool) \app_config('private.trust_proxy_headers', false)) ?? '';
        $userAgent = trim((string) ($request->header('User-Agent') ?? ''));
        $ticketRow = $this->ticketRepository->consume($ticket, $clientIp, $userAgent);
        if (!is_array($ticketRow)) {
            return null;
        }

        $projectId = (int) ($ticketRow['project_id'] ?? 0);
        $userId = (int) ($ticketRow['private_user_id'] ?? 0);
        $project = $this->projectRepository->findPreviewProjectById($projectId);
        if (!is_array($project) || $userId <= 0) {
            return null;
        }

        $session = $this->sessionRepository->create(
            $projectId,
            $userId,
            $clientIp,
            $userAgent,
            max(300, $this->sessionTtlSeconds)
        );

        return [
            'project_key' => (string) $project['projectKey'],
            'session_token' => $session['token'],
            'expires_at' => $session['expiresAt'],
        ];
    }

    public function canAccessProject(Request $request, string $projectKey): bool
    {
        $project = $this->projectRepository->findPreviewProjectByKey($projectKey);
        if (!is_array($project)) {
            return false;
        }

        $token = $request->cookies()[$this->sessionCookieName()] ?? null;
        if (!is_string($token) || preg_match('/\A[A-Za-z0-9_-]{32,256}\z/', $token) !== 1) {
            return false;
        }

        $clientIp = $request->clientIp((bool) \app_config('private.trust_proxy_headers', false)) ?? '';
        $userAgent = trim((string) ($request->header('User-Agent') ?? ''));

        return $this->sessionRepository->isValidForProject($token, (int) $project['id'], $clientIp, $userAgent);
    }

    public function sessionCookieName(): string
    {
        $normalized = strtolower(trim($this->cookieName));
        $normalized = preg_replace('/[^a-z0-9_-]+/', '_', $normalized) ?? '';

        return trim($normalized, '_-') !== '' ? trim($normalized, '_-') : 'caramagnols_preview';
    }

    public function sessionCookieHeader(string $sessionToken, int $expiresAt, bool $secure): string
    {
        $host = $this->normalizeCookieDomain(app_config('web_development.preview_host', ''));
        $parts = [
            $this->sessionCookieName() . '=' . rawurlencode($sessionToken),
            'Path=/',
            'Expires=' . gmdate('D, d M Y H:i:s', $expiresAt) . ' GMT',
            'Max-Age=' . max(0, $expiresAt - time()),
            'HttpOnly',
            'SameSite=Lax',
        ];

        if ($host !== '') {
            $parts[] = 'Domain=' . $host;
        }

        if ($secure) {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }

    private function normalizeCookieDomain(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return '';
        }

        if (str_starts_with($host, '[')) {
            $closingBracket = strpos($host, ']');
            if ($closingBracket !== false) {
                return substr($host, 1, $closingBracket - 1);
            }

            return trim($host, '[]');
        }

        if (substr_count($host, ':') === 1) {
            [$candidateHost, $candidatePort] = array_pad(explode(':', $host, 2), 2, '');
            if (preg_match('/\A[0-9]+\z/', (string) $candidatePort)) {
                return trim((string) $candidateHost);
            }
        }

        return trim($host);
    }
}
