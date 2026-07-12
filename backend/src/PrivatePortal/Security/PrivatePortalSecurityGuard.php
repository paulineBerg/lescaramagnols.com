<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Security;

use Caramagnols\Logging\AppEventLogger;
use Caramagnols\Http\Request;
use Caramagnols\Http\Response;

final class PrivatePortalSecurityGuard
{
    public function __construct(
        private readonly PrivateAuth $auth,
        private readonly ?AppEventLogger $eventLogger = null
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
            $this->logSecurityRejection($request, $scope);
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

    private function logSecurityRejection(Request $request, string $scope): void
    {
        $this->logSecurityEvent(
            'private.csrf.rejected',
            [
                'path' => request_path($request->uri()),
                'uri' => $request->uri(),
                'ip' => $this->requestIp($request),
                'method' => strtoupper((string) $request->method()),
                'scope' => $scope,
            ]
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
