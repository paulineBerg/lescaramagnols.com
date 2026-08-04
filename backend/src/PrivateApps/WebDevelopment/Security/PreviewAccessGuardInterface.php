<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\WebDevelopment\Security;

use Caramagnols\Http\Request;

interface PreviewAccessGuardInterface
{
    /**
     * @return array{project_key: string, session_token: string, expires_at: int}|null
     */
    public function consumeTicket(string $ticket, Request $request): ?array;

    public function canAccessProject(Request $request, string $projectKey): bool;

    public function sessionCookieName(): string;

    public function sessionCookieHeader(string $sessionToken, int $expiresAt, bool $secure): string;
}
