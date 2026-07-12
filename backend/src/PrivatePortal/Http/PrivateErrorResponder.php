<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Http;

use Caramagnols\Http\Request;
use Caramagnols\Http\Response;
use Caramagnols\Logging\AppEventLogger;

final class PrivateErrorResponder
{
    public function __construct(private readonly ?AppEventLogger $eventLogger = null)
    {
    }

    public function exception(Request $request, \Throwable $exception): Response
    {
        $this->log('private.request.error', $request, [
            'exception' => $exception::class,
        ], 'error');

        return PrivateResponseHeaders::apply(
            new Response(
                500,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
                'Erreur interne de l’espace privé.'
            )
        );
    }

    /**
     * @param array<int, string> $issues
     */
    public function environmentInvalid(Request $request, array $issues): Response
    {
        $this->log('private.environment.invalid', $request, [
            'issues' => array_values(array_unique(array_map('strval', $issues))),
        ], 'error');

        return PrivateResponseHeaders::apply(
            new Response(
                503,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
                'Espace privé temporairement indisponible.'
            )
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $event, Request $request, array $context, string $level): void
    {
        $logger = $this->eventLogger ?? (\function_exists('app_event_logger') ? \app_event_logger() : null);
        if (!$logger instanceof AppEventLogger) {
            return;
        }

        $logger->security($event, array_merge([
            'path' => \request_path($request->uri()),
            'method' => strtoupper($request->method()),
            'ip' => $request->clientIp((bool) \app_config('private.trust_proxy_headers', false)) ?? 'unknown',
        ], $context), $level);
    }
}
