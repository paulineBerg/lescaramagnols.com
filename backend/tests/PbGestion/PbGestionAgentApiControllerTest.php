<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PbGestion;

use Caramagnols\Http\Request;
use Caramagnols\PbGestion\Api\PbGestionAgentApiController;
use Caramagnols\PbGestion\Persistence\PbGestionRepository;
use Caramagnols\PbGestion\Protocol\AgentErrorCodes;
use Caramagnols\PbGestion\Protocol\CanonicalRequest;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

final class PbGestionAgentApiControllerTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testEnrollmentClaimAndSignedSync(): void
    {
        if (!function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('Extension sodium absente.');
        }

        [$repository, $agent, $secretKey] = $this->repositoryWithClaimedAgent();
        $controller = new PbGestionAgentApiController($repository);
        $body = $this->json([
            'network' => [
                'network_token' => 'network-token-api123',
                'trust_state' => 'trusted',
            ],
            'scan_summary' => [
                'collector_epoch' => 1,
                'scan_type' => 'passive',
                'devices_seen' => 1,
                'scanned_at' => gmdate('c'),
            ],
        ]);

        $response = $controller->handle('sync', $this->signedRequest(
            '/api/pbgestion/v1/sync',
            $body,
            (string) $agent['agent_uid'],
            $secretKey,
            1,
            '00000000-0000-4000-8000-000000000001'
        ));

        $payload = $this->decode($response->body);
        $this->assertSame(200, $response->status);
        $this->assertTrue($payload['ok']);
        $this->assertSame(1, $repository->dashboardForOwner((int) $agent['owner_id'])['scans_total']);
    }

    public function testSignedRequestsRejectReplayInvalidSignatureOldTimestampAndRevokedAgent(): void
    {
        if (!function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('Extension sodium absente.');
        }

        [$repository, $agent, $secretKey] = $this->repositoryWithClaimedAgent();
        $controller = new PbGestionAgentApiController($repository);
        $body = $this->json([]);
        $agentUid = (string) $agent['agent_uid'];

        $ok = $controller->handle('commands_poll', $this->signedRequest(
            '/api/pbgestion/v1/commands/poll',
            $body,
            $agentUid,
            $secretKey,
            1,
            '00000000-0000-4000-8000-000000000010'
        ));
        $this->assertSame(200, $ok->status);

        $sequenceReplay = $controller->handle('commands_poll', $this->signedRequest(
            '/api/pbgestion/v1/commands/poll',
            $body,
            $agentUid,
            $secretKey,
            1,
            '00000000-0000-4000-8000-000000000011'
        ));
        $this->assertErrorCode(AgentErrorCodes::SEQUENCE_REPLAY, $sequenceReplay);

        $requestReplay = $controller->handle('commands_poll', $this->signedRequest(
            '/api/pbgestion/v1/commands/poll',
            $body,
            $agentUid,
            $secretKey,
            2,
            '00000000-0000-4000-8000-000000000010'
        ));
        $this->assertErrorCode(AgentErrorCodes::REQUEST_REPLAY, $requestReplay);

        $invalidSignature = $controller->handle('commands_poll', $this->signedRequest(
            '/api/pbgestion/v1/commands/poll',
            $this->json(['changed' => true]),
            $agentUid,
            $secretKey,
            3,
            '00000000-0000-4000-8000-000000000012',
            bodyUsedForSignature: $body
        ));
        $this->assertErrorCode(AgentErrorCodes::SIGNATURE_INVALID, $invalidSignature);

        $oldTimestamp = $controller->handle('commands_poll', $this->signedRequest(
            '/api/pbgestion/v1/commands/poll',
            $body,
            $agentUid,
            $secretKey,
            4,
            '00000000-0000-4000-8000-000000000013',
            '2000-01-01T00:00:00Z'
        ));
        $this->assertErrorCode(AgentErrorCodes::TIMESTAMP_OUT_OF_WINDOW, $oldTimestamp);

        $repository->revokeAgent((int) $agent['owner_id'], (int) $agent['id'], 'test');
        $revoked = $controller->handle('commands_poll', $this->signedRequest(
            '/api/pbgestion/v1/commands/poll',
            $body,
            $agentUid,
            $secretKey,
            5,
            '00000000-0000-4000-8000-000000000014'
        ));
        $this->assertErrorCode(AgentErrorCodes::AGENT_REVOKED, $revoked);
    }

    public function testPayloadLimitsAndInvalidEnrollmentAreReportedAsJson(): void
    {
        $repository = new PbGestionRepository($this->editorialSqlDatabase());
        $controller = new PbGestionAgentApiController($repository);

        $tooLarge = $controller->handle('details_upload', new Request(
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/pbgestion/v1/details/upload'],
            [],
            [],
            [],
            [],
            str_repeat('x', 300000)
        ));
        $this->assertErrorCode(AgentErrorCodes::PAYLOAD_TOO_LARGE, $tooLarge, 413);

        $invalidEnrollment = $controller->handle('enrollment_claim', new Request(
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/pbgestion/v1/enrollment/claim'],
            [],
            [],
            [],
            ['Content-Type' => 'application/json'],
            $this->json(['code' => 'bad', 'public_key_base64' => base64_encode(str_repeat('k', 32))])
        ));
        $this->assertErrorCode(AgentErrorCodes::ENROLLMENT_INVALID, $invalidEnrollment, 403);
    }

    /**
     * @return array{0: PbGestionRepository, 1: array<string, mixed>, 2: string}
     */
    private function repositoryWithClaimedAgent(): array
    {
        $repository = new PbGestionRepository($this->editorialSqlDatabase());
        $keypair = sodium_crypto_sign_keypair();
        $publicKey = sodium_crypto_sign_publickey($keypair);
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $token = $repository->createEnrollmentToken(55, 'API');
        $claim = $repository->claimEnrollment([
            'code' => $token['code'],
            'public_key_base64' => base64_encode($publicKey),
            'display_name' => 'Agent API',
            'os_family' => 'windows',
            'os_version' => '11',
            'agent_version' => '0.1.0',
            'capabilities' => ['network'],
        ]);
        $this->assertTrue($claim['ok']);
        $this->assertIsArray($claim['agent'] ?? null);

        $agent = $repository->findAgentByUid((string) $claim['agent']['agent_uid']);
        $this->assertIsArray($agent);

        return [$repository, $agent, $secretKey];
    }

    private function signedRequest(
        string $uri,
        string $body,
        string $agentUid,
        string $secretKey,
        int $sequence,
        string $requestId,
        ?string $timestamp = null,
        ?string $bodyUsedForSignature = null
    ): Request {
        $timestamp ??= gmdate('Y-m-d\TH:i:s\Z');
        $canonical = CanonicalRequest::build(
            'POST',
            $uri,
            $bodyUsedForSignature ?? $body,
            $timestamp,
            $sequence,
            $requestId
        );

        return new Request(
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri],
            [],
            [],
            [],
            [
                'Content-Type' => 'application/json',
                'X-PB-Agent-Uid' => $agentUid,
                'X-PB-Timestamp' => $timestamp,
                'X-PB-Sequence' => (string) $sequence,
                'X-PB-Request-Id' => $requestId,
                'X-PB-Signature' => base64_encode(sodium_crypto_sign_detached($canonical, $secretKey)),
            ],
            $body
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->assertIsString($json);

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        $payload = json_decode($json, true);
        $this->assertIsArray($payload);

        return $payload;
    }

    private function assertErrorCode(string $expectedCode, object $response, int $expectedStatus = 401): void
    {
        $this->assertSame($expectedStatus, $response->status);
        $payload = $this->decode($response->body);
        $this->assertFalse($payload['ok']);
        $this->assertSame($expectedCode, $payload['code']);
        $this->assertSame('application/json; charset=utf-8', $response->headers['Content-Type'] ?? null);
    }
}
