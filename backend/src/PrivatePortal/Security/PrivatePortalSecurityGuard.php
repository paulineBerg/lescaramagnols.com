<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Security;

use Caramagnols\Logging\AppEventLogger;
use Caramagnols\Http\Request;
use Caramagnols\Http\Response;
use Caramagnols\Identity\PersistentSession\PersistentSessionGuard;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;

final class PrivatePortalSecurityGuard
{
    public function __construct(
        private readonly PrivateAuth $auth,
        private readonly ?AppEventLogger $eventLogger = null,
        private readonly ?PersistentSessionGuard $persistentGuard = null,
        private readonly ?PrivateUserRepository $userRepository = null
    ) {
    }

    private function getLogger(): ?AppEventLogger
    {
        if ($this->eventLogger instanceof AppEventLogger) {
            return $this->eventLogger;
        }

        if (!function_exists('app_event_logger')) {
            return null;
        }

        return app_event_logger();
    }

    public function requireAuthenticated(
        Request $request,
        string $loginUrl,
        bool $requireFreshSession = false
    ): ?Response {
        if (!$this->auth->isAuthenticated() && $this->persistentGuard instanceof PersistentSessionGuard && $this->userRepository instanceof PrivateUserRepository) {
            $cookie = $this->persistentGuard->restorePrivate($request, $this->auth, $this->userRepository);
            if (is_string($cookie) && $cookie !== '') {
                $GLOBALS['private_persistent_set_cookie_header'] = $cookie;
            }
        }

        if ($this->auth->isAuthenticated()) {
            if (!$requireFreshSession || $this->auth->isReauthFresh()) {
                return null;
            }

            $this->logSecurityEvent(
                'private.reauth.required',
                [
                    'path' => request_path($request->uri()),
                    'uri' => $request->uri(),
                    'ip' => $this->requestIp($request),
                    'method' => strtoupper((string) $request->method()),
                ]
            );

            return new Response(302, ['Location' => $loginUrl], '');
        }

        $this->logSecurityEvent(
            'private.access.denied',
            [
                'path' => request_path($request->uri()),
                'uri' => $request->uri(),
                'ip' => $this->requestIp($request),
                'method' => strtoupper((string) $request->method()),
            ]
        );

        if ($this->wantsJson($request)) {
            return new Response(401, ['Content-Type' => 'application/json; charset=utf-8'], '{"error":"private_access_denied"}');
        }

        return new Response(302, ['Location' => $loginUrl], '');
    }

    public function validateCsrf(Request $request, string $scope = 'private'): bool
    {
        $method = strtoupper((string) $request->method());
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;
        }

        $body = $request->body();
        $bodyToken = is_string($body['csrf_token'] ?? null) ? $body['csrf_token'] : null;
        $headerToken = is_string($request->header('X-CSRF-Token') ?? null) ? (string) $request->header('X-CSRF-Token') : null;
        $token = $bodyToken !== null && $bodyToken !== '' ? $bodyToken : $headerToken;

        if (!csrf_validate($token, $scope)) {
            $sessionScopeKey = sprintf('_csrf_%s', $scope);
            $sessionScopeValue = session_status() === PHP_SESSION_ACTIVE && is_string($_SESSION[$sessionScopeKey] ?? null)
                ? (string) $_SESSION[$sessionScopeKey]
                : '';
            $this->logSecurityRejection($request, $scope, [
                'has_body_token' => $bodyToken !== null && $bodyToken !== '',
                'has_header_token' => $headerToken !== null && $headerToken !== '',
                'session_name' => session_status() === PHP_SESSION_ACTIVE ? session_name() : '',
                'session_has_scope' => session_status() === PHP_SESSION_ACTIVE
                    && $sessionScopeValue !== '',
                'submitted_sig' => is_string($token) && $token !== '' ? substr(hash('sha256', $token), 0, 12) : '',
                'stored_sig' => $sessionScopeValue !== '' ? substr(hash('sha256', $sessionScopeValue), 0, 12) : '',
            ]);
            return false;
        }

        return true;
    }

    private function wantsJson(Request $request): bool
    {
        $accept = strtolower((string) ($request->header('Accept') ?? ''));
        $requestedWith = strtolower((string) ($request->header('X-Requested-With') ?? ''));

        return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
    }

    private function logSecurityRejection(Request $request, string $scope, array $details = []): void
    {
        $this->logSecurityEvent(
            'private.csrf.rejected',
            array_merge([
                'path' => request_path($request->uri()),
                'uri' => $request->uri(),
                'ip' => $this->requestIp($request),
                'method' => strtoupper((string) $request->method()),
                'scope' => $scope,
            ], $details)
        );
    }

    private function logSecurityEvent(string $event, array $context): void
    {
        $this->authAttemptLog($event, $context);
    }

    private function requestIp(Request $request): string
    {
        return $request->clientIp((bool) app_config('private.trust_proxy_headers', false)) ?? 'unknown';
    }

    private function authAttemptLog(string $event, array $context): void
    {
        $logger = $this->getLogger();
        if ($logger === null) {
            return;
        }

        $logger->security($event, $context, 'warning');
    }
}
