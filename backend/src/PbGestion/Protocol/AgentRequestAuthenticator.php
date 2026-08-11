<?php

declare(strict_types=1);

namespace Caramagnols\PbGestion\Protocol;

use Caramagnols\Http\Request;
use Caramagnols\PbGestion\Persistence\PbGestionRepository;

final class AgentRequestAuthenticator
{
    public const CLOCK_TOLERANCE_SECONDS = 600;

    public function __construct(private readonly PbGestionRepository $repository)
    {
    }

    /**
     * @return array{ok: bool, error?: string, agent?: array<string, mixed>}
     */
    public function authenticate(Request $request): array
    {
        $agentUid = $this->headerValue($request, 'X-PB-Agent-Uid');
        $timestamp = $this->headerValue($request, 'X-PB-Timestamp');
        $sequence = $this->headerValue($request, 'X-PB-Sequence');
        $requestUuid = strtolower($this->headerValue($request, 'X-PB-Request-Id'));
        $signature = $this->headerValue($request, 'X-PB-Signature');

        if (
            !$this->validAgentUid($agentUid)
            || !$this->validTimestamp($timestamp)
            || !$this->validSequence($sequence)
            || !$this->validUuid($requestUuid)
            || $signature === ''
        ) {
            return ['ok' => false, 'error' => AgentErrorCodes::REQUEST_REJECTED];
        }

        $agent = $this->repository->findAgentByUid($agentUid);
        if (!is_array($agent)) {
            return ['ok' => false, 'error' => AgentErrorCodes::AGENT_UNKNOWN];
        }

        $status = strtolower(trim((string) ($agent['status'] ?? '')));
        if ($status !== 'active') {
            return ['ok' => false, 'error' => AgentErrorCodes::AGENT_REVOKED];
        }

        if (!$this->timestampInWindow($timestamp)) {
            return ['ok' => false, 'error' => AgentErrorCodes::TIMESTAMP_OUT_OF_WINDOW];
        }

        $sequenceNumber = (int) $sequence;
        $canonical = CanonicalRequest::build(
            $request->method(),
            $request->uri(),
            $request->content(),
            $timestamp,
            $sequenceNumber,
            $requestUuid
        );

        if (!$this->verifySignature($canonical, $signature, (string) ($agent['public_key_base64'] ?? ''))) {
            return ['ok' => false, 'error' => AgentErrorCodes::SIGNATURE_INVALID];
        }

        $recorded = $this->repository->recordAgentRequest(
            (int) ($agent['id'] ?? 0),
            (int) ($agent['owner_id'] ?? 0),
            $requestUuid,
            CanonicalRequest::pathWithCanonicalQuery($request->uri()),
            $sequenceNumber
        );
        if (($recorded['ok'] ?? false) !== true) {
            return ['ok' => false, 'error' => (string) ($recorded['error'] ?? AgentErrorCodes::REQUEST_REJECTED)];
        }

        $agent['last_sequence'] = $sequenceNumber;

        return ['ok' => true, 'agent' => $agent];
    }

    private function headerValue(Request $request, string $name): string
    {
        return trim((string) ($request->header($name) ?? ''));
    }

    private function validAgentUid(string $value): bool
    {
        return preg_match('/\A[a-f0-9]{32}\z/', strtolower($value)) === 1;
    }

    private function validTimestamp(string $value): bool
    {
        return preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/', $value) === 1;
    }

    private function validSequence(string $value): bool
    {
        return preg_match('/\A[1-9][0-9]{0,19}\z/', $value) === 1;
    }

    private function validUuid(string $value): bool
    {
        return preg_match(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/',
            $value
        ) === 1;
    }

    private function timestampInWindow(string $timestamp): bool
    {
        $time = strtotime($timestamp);
        if ($time === false) {
            return false;
        }

        return abs(time() - $time) <= self::CLOCK_TOLERANCE_SECONDS;
    }

    private function verifySignature(string $canonical, string $signatureBase64, string $publicKeyBase64): bool
    {
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            return false;
        }

        $signature = base64_decode($signatureBase64, true);
        $publicKey = base64_decode($publicKeyBase64, true);
        if (!is_string($signature) || !is_string($publicKey) || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signature, $canonical, $publicKey);
    }
}
