<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PbGestion;

use Caramagnols\PbGestion\Persistence\PbGestionRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

final class PbGestionRepositoryTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testEnrollmentSyncAndDashboardAreScopedByOwner(): void
    {
        if (!function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('Extension sodium absente.');
        }

        $repository = new PbGestionRepository($this->editorialSqlDatabase());
        $ownerOneAgent = $this->claimAgent($repository, 11, 'PC principal');
        $ownerTwoAgent = $this->claimAgent($repository, 22, 'PC invité');

        $result = $repository->synchronizeAgent($ownerOneAgent, [
            'network' => [
                'network_token' => 'network-token-123456',
                'trust_state' => 'trusted',
                'display_label' => 'Maison',
            ],
            'posture' => [
                'posture_state' => 'healthy',
                'risk_level' => 'low',
                'summary' => ['firewall' => 'ok'],
                'reported_at' => gmdate('c'),
            ],
            'scan_summary' => [
                'collector_epoch' => 1,
                'scan_type' => 'passive',
                'status' => 'received',
                'devices_seen' => 1,
                'changes_seen' => 0,
                'alerts_opened' => 1,
                'scanned_at' => gmdate('c'),
                'summary' => ['scope' => 'summary-only'],
            ],
            'devices' => [
                [
                    'device_token' => 'device-token-123456',
                    'device_kind' => 'computer',
                    'risk_level' => 'low',
                    'summary' => ['vendor' => 'known'],
                    'last_seen_at' => gmdate('c'),
                ],
            ],
            'alerts' => [
                [
                    'source' => 'agent',
                    'subject_token' => 'device-token-123456',
                    'type' => 'backup_missing',
                    'severity' => 'warning',
                    'title' => 'Sauvegarde à vérifier',
                    'summary' => 'Le dernier contrôle de sauvegarde est ancien.',
                ],
            ],
            'backup_status' => [
                'snapshot_state' => 'ok',
                'external_backup_state' => 'warning',
                'reported_at' => gmdate('c'),
            ],
        ]);

        $this->assertTrue($result['ok']);
        $ownerOneDashboard = $repository->dashboardForOwner(11);
        $ownerTwoDashboard = $repository->dashboardForOwner(22);

        $this->assertSame(1, $ownerOneDashboard['agents_total']);
        $this->assertSame(1, $ownerOneDashboard['networks_total']);
        $this->assertSame(1, $ownerOneDashboard['devices_total']);
        $this->assertSame(1, $ownerOneDashboard['alerts_open']);
        $this->assertSame(1, $ownerOneDashboard['scans_total']);
        $this->assertSame(1, $ownerTwoDashboard['agents_total']);
        $this->assertSame(0, $ownerTwoDashboard['networks_total']);

        $this->assertNotSame($ownerOneAgent['agent_uid'], $ownerTwoAgent['agent_uid']);
    }

    public function testSyncRejectsRawNetworkDetails(): void
    {
        if (!function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('Extension sodium absente.');
        }

        $repository = new PbGestionRepository($this->editorialSqlDatabase());
        $agent = $this->claimAgent($repository, 33, 'PC');

        $result = $repository->synchronizeAgent($agent, [
            'network' => [
                'network_token' => 'network-token-raw123',
                'ip' => '192.168.1.2',
            ],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('raw_network_details_rejected', $result['error']);
    }

    public function testQueueCommandIsIdempotentAndCreatesTemporaryDetailRequest(): void
    {
        if (!function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('Extension sodium absente.');
        }

        $repository = new PbGestionRepository($this->editorialSqlDatabase());
        $agent = $this->claimAgent($repository, 44, 'Support');

        $payload = [
            'detail_uid' => str_repeat('a', 32),
            'purpose' => 'support',
        ];
        $first = $repository->queueCommand(44, (int) $agent['id'], 'details.prepare', $payload, 'detail-support-1', 'test');
        $second = $repository->queueCommand(44, (int) $agent['id'], 'details.prepare', $payload, 'detail-support-1', 'test');

        $this->assertTrue($first['ok']);
        $this->assertTrue($second['ok']);
        $this->assertSame($first['command']['command_uid'] ?? null, $second['command']['command_uid'] ?? null);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', (string) ($first['command']['payload']['request_uid'] ?? ''));
        $this->assertSame(1, $repository->dashboardForOwner(44)['details_pending']);
    }

    /**
     * @return array<string, mixed>
     */
    private function claimAgent(PbGestionRepository $repository, int $ownerId, string $locationLabel): array
    {
        $keypair = sodium_crypto_sign_keypair();
        $token = $repository->createEnrollmentToken($ownerId, $locationLabel);
        $claim = $repository->claimEnrollment([
            'code' => $token['code'],
            'public_key_base64' => base64_encode(sodium_crypto_sign_publickey($keypair)),
            'display_name' => $locationLabel,
            'os_family' => 'windows',
            'os_version' => '11',
            'agent_version' => '0.1.0',
            'capabilities' => ['network', 'posture', 'backup'],
        ]);

        $this->assertTrue($claim['ok']);
        $this->assertIsArray($claim['agent'] ?? null);

        return $repository->findAgentByUid((string) $claim['agent']['agent_uid']) ?? [];
    }
}
