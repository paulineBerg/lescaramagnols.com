<?php

declare(strict_types=1);

namespace Caramagnols\PbGestion\Api;

use Caramagnols\Http\Request;
use Caramagnols\Http\Response;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PbGestion\Persistence\PbGestionRepository;
use Caramagnols\PbGestion\Protocol\AgentErrorCodes;
use Caramagnols\PbGestion\Protocol\AgentRequestAuthenticator;
use Caramagnols\PbGestion\Synchronization\SyncContract;

final class PbGestionAgentApiController
{
    private const LIMITS = [
        'enrollment_claim' => 8192,
        'sync' => SyncContract::MAX_SYNC_BYTES,
        'commands_poll' => 8192,
        'commands_ack' => 16384,
        'details_upload' => SyncContract::MAX_DETAIL_BYTES,
        'recovery_prepare' => 8192,
    ];

    public function __construct(
        private readonly PbGestionRepository $repository,
        private readonly ?AppEventLogger $eventLogger = null,
        private readonly ?AgentRequestAuthenticator $authenticator = null
    ) {
    }

    public function handle(string $action, Request $request): Response
    {
        if (strtoupper($request->method()) !== 'POST') {
            return $this->error(AgentErrorCodes::REQUEST_REJECTED, 405);
        }

        $limit = self::LIMITS[$action] ?? 8192;
        if (strlen($request->content()) > $limit) {
            return $this->error(AgentErrorCodes::PAYLOAD_TOO_LARGE, 413);
        }

        $payload = $this->jsonPayload($request);
        if ($payload === null) {
            return $this->error(AgentErrorCodes::JSON_INVALID, 400);
        }

        if ($action === 'enrollment_claim') {
            return $this->claimEnrollment($payload);
        }

        $auth = $this->authenticator()->authenticate($request);
        if (($auth['ok'] ?? false) !== true || !is_array($auth['agent'] ?? null)) {
            return $this->error((string) ($auth['error'] ?? AgentErrorCodes::REQUEST_REJECTED), 401);
        }

        $agent = $auth['agent'];

        return match ($action) {
            'sync' => $this->sync($agent, $payload),
            'commands_poll' => $this->pollCommands($agent),
            'commands_ack' => $this->ackCommand($agent, $payload),
            'details_upload' => $this->uploadDetail($agent, $payload),
            'recovery_prepare' => $this->recoveryPrepare($agent),
            default => $this->error(AgentErrorCodes::REQUEST_REJECTED, 404),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function jsonPayload(Request $request): ?array
    {
        $content = trim($request->content());
        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function claimEnrollment(array $payload): Response
    {
        $result = $this->repository->claimEnrollment($payload);
        if (($result['ok'] ?? false) !== true || !is_array($result['agent'] ?? null)) {
            $this->log('pbgestion.enrollment.rejected', ['reason' => (string) ($result['error'] ?? 'unknown')], 'warning');

            return $this->error((string) ($result['error'] ?? AgentErrorCodes::ENROLLMENT_INVALID), 403);
        }

        $agent = $result['agent'];
        $this->log('pbgestion.enrollment.claimed', ['agent_id' => (int) ($agent['id'] ?? 0)], 'info');

        return $this->json([
            'ok' => true,
            'schema_version' => SyncContract::SCHEMA_VERSION,
            'agent_uid' => (string) ($agent['agent_uid'] ?? ''),
            'server_time' => gmdate('c'),
        ]);
    }

    /**
     * @param array<string, mixed> $agent
     * @param array<string, mixed> $payload
     */
    private function sync(array $agent, array $payload): Response
    {
        $result = $this->repository->synchronizeAgent($agent, $payload);
        if (($result['ok'] ?? false) !== true) {
            return $this->error((string) ($result['error'] ?? AgentErrorCodes::REQUEST_REJECTED), 422);
        }

        return $this->json([
            'ok' => true,
            'schema_version' => SyncContract::SCHEMA_VERSION,
            'stored' => is_array($result['stored'] ?? null) ? $result['stored'] : [],
            'server_time' => gmdate('c'),
        ]);
    }

    /**
     * @param array<string, mixed> $agent
     */
    private function pollCommands(array $agent): Response
    {
        return $this->json([
            'ok' => true,
            'schema_version' => SyncContract::SCHEMA_VERSION,
            'commands' => $this->repository->pollCommands($agent),
            'server_time' => gmdate('c'),
        ]);
    }

    /**
     * @param array<string, mixed> $agent
     * @param array<string, mixed> $payload
     */
    private function ackCommand(array $agent, array $payload): Response
    {
        $result = $this->repository->acknowledgeCommand($agent, $payload);
        if (($result['ok'] ?? false) !== true) {
            return $this->error((string) ($result['error'] ?? AgentErrorCodes::COMMAND_PAYLOAD_INVALID), 422);
        }

        return $this->json(['ok' => true, 'schema_version' => SyncContract::SCHEMA_VERSION]);
    }

    /**
     * @param array<string, mixed> $agent
     * @param array<string, mixed> $payload
     */
    private function uploadDetail(array $agent, array $payload): Response
    {
        $result = $this->repository->uploadTemporaryDetail($agent, $payload);
        if (($result['ok'] ?? false) !== true) {
            return $this->error((string) ($result['error'] ?? AgentErrorCodes::COMMAND_PAYLOAD_INVALID), 422);
        }

        return $this->json(['ok' => true, 'schema_version' => SyncContract::SCHEMA_VERSION]);
    }

    /**
     * @param array<string, mixed> $agent
     */
    private function recoveryPrepare(array $agent): Response
    {
        return $this->json([
            'ok' => true,
            'schema_version' => SyncContract::SCHEMA_VERSION,
            'recovery' => [
                'status' => 'manual_confirmation_required',
                'message' => 'La restauration se prepare depuis le BO Private; aucun secret de recuperation n est renvoye par cette route.',
            ],
        ]);
    }

    private function error(string $code, int $status): Response
    {
        $messages = AgentErrorCodes::publicMessages();

        return $this->json([
            'ok' => false,
            'error' => AgentErrorCodes::REQUEST_REJECTED,
            'code' => $code,
            'message' => $messages[$code] ?? $messages[AgentErrorCodes::REQUEST_REJECTED],
        ], $status);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload, int $status = 200): Response
    {
        return Response::json($payload, $status, [
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authenticator(): AgentRequestAuthenticator
    {
        return $this->authenticator ?? new AgentRequestAuthenticator($this->repository);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $event, array $context, string $level): void
    {
        $logger = $this->eventLogger ?? (function_exists('app_event_logger') ? app_event_logger() : null);
        if (!$logger instanceof AppEventLogger) {
            return;
        }

        $logger->security($event, $context, $level);
    }
}
