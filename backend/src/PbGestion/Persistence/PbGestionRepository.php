<?php

declare(strict_types=1);

namespace Caramagnols\PbGestion\Persistence;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PbGestion\Command\CommandPolicy;
use Caramagnols\PbGestion\Protocol\AgentErrorCodes;
use Caramagnols\SecurityCenter\Alert\AlertDeduplicator;
use Caramagnols\SecurityCenter\Dashboard\CoverageCalculator;
use Caramagnols\SecurityCenter\Device\DeviceSummaryNormalizer;
use Caramagnols\SecurityCenter\Network\SecurityNetworkService;
use Caramagnols\SecurityCenter\Posture\PostureNormalizer;
use Caramagnols\SecurityCenter\Scan\ScanSummaryNormalizer;
use PDO;
use RuntimeException;

final class PbGestionRepository
{
    private bool $schemaReady = false;

    public function __construct(
        private readonly EditorialDatabase $database,
        private readonly ?CommandPolicy $commandPolicy = null,
        private readonly ?SecurityNetworkService $networkService = null,
        private readonly ?AlertDeduplicator $alertDeduplicator = null,
        private readonly ?CoverageCalculator $coverageCalculator = null,
        private readonly ?DeviceSummaryNormalizer $deviceNormalizer = null,
        private readonly ?ScanSummaryNormalizer $scanNormalizer = null,
        private readonly ?PostureNormalizer $postureNormalizer = null
    ) {
    }

    /**
     * @return array{token_uid: string, code: string, code_grouped: string, expires_at: string}
     */
    public function createEnrollmentToken(int $ownerId, string $locationLabel = ''): array
    {
        $this->ensureSchema();
        $tokenUid = bin2hex(random_bytes(16));
        $code = $this->randomEnrollmentCode();
        $now = $this->now();
        $expiresAt = gmdate('Y-m-d H:i:s', time() + 600);
        $hash = password_hash($code, PASSWORD_DEFAULT);
        if (!is_string($hash)) {
            throw new RuntimeException('Impossible de generer le hash du code Sécurité réseau.');
        }

        $statement = $this->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`owner_id`, `token_uid`, `code_hash`, `location_label`, `status`, `max_attempts`, `expires_at`, `created_at`, `updated_at`)
                 VALUES
                    (:owner_id, :token_uid, :code_hash, :location_label, \'pending\', 5, :expires_at, :created_at, :updated_at)',
                $this->table('pb_enrollment_tokens')
            )
        );
        $statement->execute([
            'owner_id' => $ownerId,
            'token_uid' => $tokenUid,
            'code_hash' => $hash,
            'location_label' => $this->shortText($locationLabel, 160) ?: null,
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'token_uid' => $tokenUid,
            'code' => $code,
            'code_grouped' => substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 4),
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, error?: string, agent?: array<string, mixed>}
     */
    public function claimEnrollment(array $payload): array
    {
        $this->ensureSchema();
        $code = $this->normalizeEnrollmentCode($payload['code'] ?? null);
        if ($code === '') {
            return ['ok' => false, 'error' => AgentErrorCodes::ENROLLMENT_INVALID];
        }

        $publicKey = is_string($payload['public_key_base64'] ?? null) ? trim((string) $payload['public_key_base64']) : '';
        if (!$this->validPublicKey($publicKey)) {
            return ['ok' => false, 'error' => AgentErrorCodes::ENROLLMENT_INVALID];
        }

        $statement = $this->pdo()->prepare(
            sprintf(
                'SELECT * FROM `%s`
                 WHERE `status` = \'pending\'
                   AND `expires_at` >= :now
                   AND `attempts` < `max_attempts`
                 ORDER BY `created_at` DESC
                 LIMIT 20',
                $this->table('pb_enrollment_tokens')
            )
        );
        $statement->execute(['now' => $this->now()]);
        $tokens = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tokens as $token) {
            if (!is_array($token) || !password_verify($code, (string) ($token['code_hash'] ?? ''))) {
                continue;
            }

            return $this->attachAgentToToken($token, $payload, $publicKey);
        }

        $this->incrementEnrollmentAttempts();

        return ['ok' => false, 'error' => AgentErrorCodes::ENROLLMENT_INVALID];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAgentByUid(string $agentUid): ?array
    {
        $this->ensureSchema();
        $statement = $this->pdo()->prepare(
            sprintf('SELECT * FROM `%s` WHERE `agent_uid` = :agent_uid LIMIT 1', $this->table('pb_agents'))
        );
        $statement->execute(['agent_uid' => strtolower(trim($agentUid))]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAgentForOwner(int $ownerId, int $agentId): ?array
    {
        $this->ensureSchema();
        $statement = $this->pdo()->prepare(
            sprintf(
                'SELECT * FROM `%s` WHERE `id` = :agent_id AND `owner_id` = :owner_id LIMIT 1',
                $this->table('pb_agents')
            )
        );
        $statement->execute(['agent_id' => $agentId, 'owner_id' => $ownerId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function recordAgentRequest(
        int $agentId,
        int $ownerId,
        string $requestUuid,
        string $requestPath,
        int $sequence
    ): array {
        $this->ensureSchema();
        $this->cleanupExpiredRequestLog();

        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $agentStatement = $pdo->prepare(
                sprintf(
                    'SELECT `id`, `last_sequence`, `status`
                     FROM `%s`
                     WHERE `id` = :agent_id AND `owner_id` = :owner_id
                     LIMIT 1
                     FOR UPDATE',
                    $this->table('pb_agents')
                )
            );
            $agentStatement->execute(['agent_id' => $agentId, 'owner_id' => $ownerId]);
            $agent = $agentStatement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($agent)) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => AgentErrorCodes::AGENT_UNKNOWN];
            }

            if (strtolower((string) ($agent['status'] ?? '')) !== 'active') {
                $pdo->rollBack();
                return ['ok' => false, 'error' => AgentErrorCodes::AGENT_REVOKED];
            }

            if ($sequence <= (int) ($agent['last_sequence'] ?? 0)) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => AgentErrorCodes::SEQUENCE_REPLAY];
            }

            $replayStatement = $pdo->prepare(
                sprintf(
                    'SELECT `id` FROM `%s` WHERE `agent_id` = :agent_id AND `request_uuid` = :request_uuid LIMIT 1',
                    $this->table('pb_agent_request_log')
                )
            );
            $replayStatement->execute(['agent_id' => $agentId, 'request_uuid' => $requestUuid]);
            if ($replayStatement->fetchColumn() !== false) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => AgentErrorCodes::REQUEST_REPLAY];
            }

            $now = $this->now();
            $requestLog = $pdo->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`owner_id`, `agent_id`, `request_uuid`, `request_path`, `sequence_number`, `received_at`, `expires_at`)
                     VALUES
                        (:owner_id, :agent_id, :request_uuid, :request_path, :sequence_number, :received_at, :expires_at)',
                    $this->table('pb_agent_request_log')
                )
            );
            $requestLog->execute([
                'owner_id' => $ownerId,
                'agent_id' => $agentId,
                'request_uuid' => $requestUuid,
                'request_path' => $this->shortText($requestPath, 160),
                'sequence_number' => $sequence,
                'received_at' => $now,
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
            ]);

            $updateAgent = $pdo->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `last_sequence` = :sequence, `last_seen_at` = :last_seen_at, `updated_at` = :updated_at
                     WHERE `id` = :agent_id AND `owner_id` = :owner_id',
                    $this->table('pb_agents')
                )
            );
            $updateAgent->execute([
                'sequence' => $sequence,
                'last_seen_at' => $now,
                'updated_at' => $now,
                'agent_id' => $agentId,
                'owner_id' => $ownerId,
            ]);

            $this->upsertSyncState($ownerId, $agentId, ['last_sequence' => $sequence, 'last_sync_at' => $now]);
            $pdo->commit();

            return ['ok' => true];
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function synchronizeAgent(array $agent, array $payload): array
    {
        $this->ensureSchema();
        $ownerId = (int) ($agent['owner_id'] ?? 0);
        $agentId = (int) ($agent['id'] ?? 0);
        if ($ownerId <= 0 || $agentId <= 0) {
            return ['ok' => false, 'error' => AgentErrorCodes::AGENT_UNKNOWN];
        }

        if ($this->networkService()->containsRawNetworkDetails($payload)) {
            return ['ok' => false, 'error' => 'raw_network_details_rejected'];
        }

        $counts = [
            'networks' => 0,
            'devices' => 0,
            'changes' => 0,
            'alerts' => 0,
            'postures' => 0,
            'scans' => 0,
            'backups' => 0,
        ];

        $this->updateAgentRuntime($agentId, $ownerId, $payload);

        $networkRow = null;
        if (is_array($payload['network'] ?? null)) {
            $networkRow = $this->upsertNetwork($ownerId, $payload['network']);
            if (is_array($networkRow)) {
                $counts['networks']++;
            }
        }

        if (is_array($payload['posture'] ?? null)) {
            $this->upsertPosture($ownerId, $agentId, $payload['posture']);
            $counts['postures']++;
        }

        if (is_array($payload['scan_summary'] ?? null)) {
            $scanResult = $this->storeScanSummary($ownerId, $agentId, $networkRow, $payload['scan_summary']);
            if (($scanResult['ok'] ?? false) !== true) {
                return $scanResult;
            }
            $counts['scans']++;
        }

        if (is_array($payload['devices'] ?? null) && is_array($networkRow)) {
            foreach ($payload['devices'] as $device) {
                if (!is_array($device) || $this->networkService()->containsRawNetworkDetails($device)) {
                    return ['ok' => false, 'error' => 'raw_network_details_rejected'];
                }
                $this->upsertDevice($ownerId, (int) $networkRow['id'], $device);
                $counts['devices']++;
            }
        }

        if (is_array($payload['changes'] ?? null) && is_array($networkRow)) {
            foreach ($payload['changes'] as $change) {
                if (!is_array($change) || $this->networkService()->containsRawNetworkDetails($change)) {
                    return ['ok' => false, 'error' => 'raw_network_details_rejected'];
                }
                $this->insertDeviceChange($ownerId, (int) $networkRow['id'], $change);
                $counts['changes']++;
            }
        }

        if (is_array($payload['alerts'] ?? null)) {
            foreach ($payload['alerts'] as $alert) {
                if (!is_array($alert) || $this->networkService()->containsRawNetworkDetails($alert)) {
                    return ['ok' => false, 'error' => 'raw_network_details_rejected'];
                }
                $this->upsertAlert($ownerId, $alert);
                $counts['alerts']++;
            }
        }

        if (is_array($payload['backup_status'] ?? null)) {
            $this->upsertBackupStatus($ownerId, $agentId, $payload['backup_status']);
            $counts['backups']++;
        }

        $this->upsertSyncState($ownerId, $agentId, ['last_sync_at' => $this->now()]);

        return ['ok' => true, 'stored' => $counts];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, error?: string, command?: array<string, mixed>}
     */
    public function queueCommand(
        int $ownerId,
        int $agentId,
        string $commandType,
        array $payload,
        string $idempotencyKey,
        ?string $requestedBy = null
    ): array {
        $this->ensureSchema();
        $agent = $this->findAgentForOwner($ownerId, $agentId);
        if (!is_array($agent)) {
            return ['ok' => false, 'error' => AgentErrorCodes::AGENT_UNKNOWN];
        }
        if (strtolower((string) ($agent['status'] ?? '')) !== 'active') {
            return ['ok' => false, 'error' => AgentErrorCodes::AGENT_REVOKED];
        }

        $policy = $this->commandPolicy()->validate($commandType, $payload);
        if (($policy['ok'] ?? false) !== true) {
            return ['ok' => false, 'error' => AgentErrorCodes::COMMAND_PAYLOAD_INVALID];
        }

        $commandType = strtolower(trim($commandType));
        $idempotencyKey = $this->shortText($idempotencyKey, 96);
        if ($idempotencyKey === '') {
            $idempotencyKey = hash('sha256', $commandType . json_encode($payload, JSON_UNESCAPED_SLASHES));
        }

        $detailRequest = null;
        if ($commandType === 'details.prepare') {
            $requestUid = bin2hex(random_bytes(16));
            $payload['request_uid'] = $requestUid;
            $detailRequest = [
                'detail_uid' => (string) $payload['detail_uid'],
                'request_uid' => $requestUid,
                'purpose' => (string) $payload['purpose'],
            ];
        }

        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $existing = $this->findCommandByIdempotency($ownerId, $agentId, $idempotencyKey);
            if (is_array($existing)) {
                $pdo->commit();
                return ['ok' => true, 'command' => $existing];
            }

            if (is_array($detailRequest)) {
                $this->createDetailRequest(
                    $ownerId,
                    $agentId,
                    $detailRequest['detail_uid'],
                    $detailRequest['request_uid'],
                    $detailRequest['purpose']
                );
            }

            $serverSequence = $this->nextServerSequence($agentId);
            $commandUid = bin2hex(random_bytes(16));
            $now = $this->now();
            $statement = $pdo->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`owner_id`, `agent_id`, `command_uid`, `command_type`, `payload_json`, `idempotency_key`,
                         `server_sequence`, `status`, `requested_by`, `expires_at`, `created_at`, `updated_at`)
                     VALUES
                        (:owner_id, :agent_id, :command_uid, :command_type, :payload_json, :idempotency_key,
                         :server_sequence, \'pending\', :requested_by, :expires_at, :created_at, :updated_at)',
                    $this->table('pb_commands')
                )
            );
            $statement->execute([
                'owner_id' => $ownerId,
                'agent_id' => $agentId,
                'command_uid' => $commandUid,
                'command_type' => $commandType,
                'payload_json' => $this->json($payload),
                'idempotency_key' => $idempotencyKey,
                'server_sequence' => $serverSequence,
                'requested_by' => $this->shortText((string) $requestedBy, 254) ?: null,
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 1800),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $command = $this->findCommandByUid($commandUid);
            $pdo->commit();

            return ['ok' => true, 'command' => is_array($command) ? $command : []];
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pollCommands(array $agent, int $limit = 10): array
    {
        $this->ensureSchema();
        $this->expireCommands();
        $agentId = (int) ($agent['id'] ?? 0);
        $ownerId = (int) ($agent['owner_id'] ?? 0);
        if ($agentId <= 0 || $ownerId <= 0 || strtolower((string) ($agent['status'] ?? '')) !== 'active') {
            return [];
        }

        $limit = max(1, min(25, $limit));
        $statement = $this->pdo()->prepare(
            sprintf(
                'SELECT * FROM `%s`
                 WHERE `owner_id` = :owner_id
                   AND `agent_id` = :agent_id
                   AND `status` = \'pending\'
                   AND `expires_at` >= :now
                 ORDER BY `server_sequence` ASC
                 LIMIT %d',
                $this->table('pb_commands'),
                $limit
            )
        );
        $statement->execute(['owner_id' => $ownerId, 'agent_id' => $agentId, 'now' => $this->now()]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $commandUids = [];
        foreach ($rows as $row) {
            if (is_array($row) && is_string($row['command_uid'] ?? null)) {
                $commandUids[] = (string) $row['command_uid'];
            }
        }

        if ($commandUids !== []) {
            $placeholders = implode(',', array_fill(0, count($commandUids), '?'));
            $update = $this->pdo()->prepare(
                sprintf(
                    'UPDATE `%s` SET `status` = \'delivered\', `delivered_at` = ?, `updated_at` = ?
                     WHERE `command_uid` IN (%s) AND `status` = \'pending\'',
                    $this->table('pb_commands'),
                    $placeholders
                )
            );
            $now = $this->now();
            $update->execute(array_merge([$now, $now], $commandUids));
        }

        return array_values(array_map(fn (array $row): array => $this->hydrateCommand($row), $rows));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, error?: string}
     */
    public function acknowledgeCommand(array $agent, array $payload): array
    {
        $this->ensureSchema();
        $agentId = (int) ($agent['id'] ?? 0);
        $ownerId = (int) ($agent['owner_id'] ?? 0);
        $commandUid = is_string($payload['command_uid'] ?? null) ? strtolower(trim((string) $payload['command_uid'])) : '';
        $status = is_string($payload['status'] ?? null) ? strtolower(trim((string) $payload['status'])) : '';
        if ($commandUid === '' || preg_match('/\A[a-f0-9]{32}\z/', $commandUid) !== 1) {
            return ['ok' => false, 'error' => AgentErrorCodes::COMMAND_PAYLOAD_INVALID];
        }
        if (!in_array($status, ['running', 'succeeded', 'failed', 'cancelled'], true)) {
            return ['ok' => false, 'error' => AgentErrorCodes::COMMAND_PAYLOAD_INVALID];
        }

        $now = $this->now();
        $statement = $this->pdo()->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `status` = :status,
                     `result_code` = :result_code,
                     `result_message` = :result_message,
                     `started_at` = CASE WHEN :status_running = 1 AND `started_at` IS NULL THEN :now1 ELSE `started_at` END,
                     `completed_at` = CASE WHEN :status_terminal = 1 THEN :now2 ELSE `completed_at` END,
                     `updated_at` = :now3
                 WHERE `owner_id` = :owner_id
                   AND `agent_id` = :agent_id
                   AND `command_uid` = :command_uid
                   AND `status` IN (\'delivered\', \'running\')',
                $this->table('pb_commands')
            )
        );
        $terminal = in_array($status, ['succeeded', 'failed', 'cancelled'], true);
        $statement->execute([
            'status' => $status,
            'result_code' => $this->shortText((string) ($payload['result_code'] ?? ''), 80) ?: null,
            'result_message' => $this->shortText((string) ($payload['result_message'] ?? ''), 240) ?: null,
            'status_running' => $status === 'running' ? 1 : 0,
            'status_terminal' => $terminal ? 1 : 0,
            'now1' => $now,
            'now2' => $now,
            'now3' => $now,
            'owner_id' => $ownerId,
            'agent_id' => $agentId,
            'command_uid' => $commandUid,
        ]);

        return $statement->rowCount() > 0 ? ['ok' => true] : ['ok' => false, 'error' => AgentErrorCodes::COMMAND_EXPIRED];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, error?: string}
     */
    public function uploadTemporaryDetail(array $agent, array $payload): array
    {
        $this->ensureSchema();
        $agentId = (int) ($agent['id'] ?? 0);
        $ownerId = (int) ($agent['owner_id'] ?? 0);
        $detailUid = is_string($payload['detail_uid'] ?? null) ? strtolower(trim((string) $payload['detail_uid'])) : '';
        $requestUid = is_string($payload['request_uid'] ?? null) ? strtolower(trim((string) $payload['request_uid'])) : '';
        $encrypted = is_string($payload['encrypted_payload_base64'] ?? null)
            ? base64_decode((string) $payload['encrypted_payload_base64'], true)
            : false;
        $sha256 = is_string($payload['payload_sha256'] ?? null) ? strtolower(trim((string) $payload['payload_sha256'])) : '';
        if (
            preg_match('/\A[a-f0-9]{32}\z/', $detailUid) !== 1
            || preg_match('/\A[a-f0-9]{32}\z/', $requestUid) !== 1
            || !is_string($encrypted)
            || strlen($encrypted) > 262144
            || preg_match('/\A[a-f0-9]{64}\z/', $sha256) !== 1
            || hash('sha256', $encrypted) !== $sha256
        ) {
            return ['ok' => false, 'error' => AgentErrorCodes::COMMAND_PAYLOAD_INVALID];
        }

        $now = $this->now();
        $statement = $this->pdo()->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `encrypted_payload` = :payload,
                     `payload_sha256` = :payload_sha256,
                     `status` = \'collected\',
                     `collected_at` = :collected_at,
                     `updated_at` = :updated_at
                 WHERE `owner_id` = :owner_id
                   AND `agent_id` = :agent_id
                   AND `detail_uid` = :detail_uid
                   AND `request_uid` = :request_uid
                   AND `status` = \'requested\'
                   AND `expires_at` >= :now_check',
                $this->table('security_detail_requests')
            )
        );
        $statement->bindValue('payload', $encrypted, PDO::PARAM_LOB);
        $statement->bindValue('payload_sha256', $sha256);
        $statement->bindValue('collected_at', $now);
        $statement->bindValue('updated_at', $now);
        $statement->bindValue('owner_id', $ownerId, PDO::PARAM_INT);
        $statement->bindValue('agent_id', $agentId, PDO::PARAM_INT);
        $statement->bindValue('detail_uid', $detailUid);
        $statement->bindValue('request_uid', $requestUid);
        $statement->bindValue('now_check', $now);
        $statement->execute();

        return $statement->rowCount() > 0 ? ['ok' => true] : ['ok' => false, 'error' => AgentErrorCodes::COMMAND_EXPIRED];
    }

    public function revokeAgent(int $ownerId, int $agentId, string $reason = 'admin'): bool
    {
        $this->ensureSchema();
        $now = $this->now();
        $statement = $this->pdo()->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `status` = \'revoked\', `revoked_at` = :revoked_at, `revoked_reason` = :reason, `updated_at` = :updated_at
                 WHERE `id` = :agent_id AND `owner_id` = :owner_id',
                $this->table('pb_agents')
            )
        );
        $statement->execute([
            'revoked_at' => $now,
            'reason' => $this->shortText($reason, 160),
            'updated_at' => $now,
            'agent_id' => $agentId,
            'owner_id' => $ownerId,
        ]);

        if ($statement->rowCount() <= 0) {
            return false;
        }

        $cancel = $this->pdo()->prepare(
            sprintf(
                'UPDATE `%s` SET `status` = \'cancelled\', `updated_at` = :updated_at
                 WHERE `owner_id` = :owner_id AND `agent_id` = :agent_id AND `status` = \'pending\'',
                $this->table('pb_commands')
            )
        );
        $cancel->execute(['updated_at' => $now, 'owner_id' => $ownerId, 'agent_id' => $agentId]);

        return true;
    }

    public function purgeExpiredDetails(bool $dryRun = false, int $limit = 200): int
    {
        $this->ensureSchema();
        $limit = max(1, min(1000, $limit));
        if ($dryRun) {
            $statement = $this->pdo()->prepare(
                sprintf(
                    'SELECT COUNT(*) FROM `%s`
                     WHERE `expires_at` < :now OR (`read_at` IS NOT NULL AND `status` = \'read\')',
                    $this->table('security_detail_requests')
                )
            );
            $statement->execute(['now' => $this->now()]);

            return max(0, (int) $statement->fetchColumn());
        }

        $statement = $this->pdo()->prepare(
            sprintf(
                'DELETE FROM `%s`
                 WHERE `expires_at` < :now OR (`read_at` IS NOT NULL AND `status` = \'read\')
                 LIMIT %d',
                $this->table('security_detail_requests'),
                $limit
            )
        );
        $statement->execute(['now' => $this->now()]);

        return $statement->rowCount();
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardForOwner(int $ownerId): array
    {
        $this->ensureSchema();
        $agents = $this->listAgentsForOwner($ownerId);
        $latestSeen = null;
        foreach ($agents as $agent) {
            $seen = is_string($agent['last_seen_at'] ?? null) ? (string) $agent['last_seen_at'] : null;
            if ($seen !== null && ($latestSeen === null || strcmp($seen, $latestSeen) > 0)) {
                $latestSeen = $seen;
            }
        }

        return [
            'agents' => $agents,
            'agents_total' => count($agents),
            'agents_active' => $this->countRows('pb_agents', '`owner_id` = :owner_id AND `status` = \'active\'', ['owner_id' => $ownerId]),
            'networks_total' => $this->countRows('security_networks', '`owner_id` = :owner_id', ['owner_id' => $ownerId]),
            'devices_total' => $this->countRows('security_devices_current', '`owner_id` = :owner_id', ['owner_id' => $ownerId]),
            'alerts_open' => $this->countRows('security_alerts', '`owner_id` = :owner_id AND `status` = \'open\'', ['owner_id' => $ownerId]),
            'commands_pending' => $this->countRows('pb_commands', '`owner_id` = :owner_id AND `status` IN (\'pending\', \'delivered\', \'running\')', ['owner_id' => $ownerId]),
            'scans_total' => $this->countRows('security_scan_summaries', '`owner_id` = :owner_id', ['owner_id' => $ownerId]),
            'latest_seen_at' => $latestSeen,
            'latest_seen_age' => $this->coverageCalculator()->ageLabel($latestSeen),
            'coverage_state' => $this->coverageCalculator()->coverageState($latestSeen),
            'networks' => $this->listNetworksForOwner($ownerId),
            'devices' => $this->listDevicesForOwner($ownerId, 50),
            'alerts' => $this->listAlertsForOwner($ownerId, 50),
            'commands' => $this->listCommandsForOwner($ownerId, 50),
            'backups' => $this->listBackupsForOwner($ownerId),
            'details_pending' => $this->countRows('security_detail_requests', '`owner_id` = :owner_id', ['owner_id' => $ownerId]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function adminDashboard(): array
    {
        $this->ensureSchema();

        return [
            'agents_total' => $this->countRows('pb_agents', '1=1', []),
            'agents_active' => $this->countRows('pb_agents', '`status` = \'active\'', []),
            'agents_revoked' => $this->countRows('pb_agents', '`status` = \'revoked\'', []),
            'owners_total' => $this->countDistinctRows('pb_agents', 'owner_id', '1=1', []),
            'networks_total' => $this->countRows('security_networks', '1=1', []),
            'devices_total' => $this->countRows('security_devices_current', '1=1', []),
            'commands_pending' => $this->countRows('pb_commands', '`status` IN (\'pending\', \'delivered\', \'running\')', []),
            'alerts_open' => $this->countRows('security_alerts', '`status` = \'open\'', []),
            'details_to_purge' => $this->purgeExpiredDetails(true),
            'agents' => $this->fetchRows('pb_agents', '1=1', [], '`last_seen_at` DESC, `created_at` DESC', 100),
            'revoked_agents' => $this->fetchRows('pb_agents', '`status` = \'revoked\'', [], '`revoked_at` DESC, `updated_at` DESC', 50),
            'policies' => $this->fetchRows('pb_policies', '1=1', [], '`created_at` DESC', 50),
            'latest_scans' => $this->fetchRows('security_scan_summaries', '1=1', [], '`scanned_at` DESC, `created_at` DESC', 50),
            'postures' => $this->fetchRows('security_posture_current', '1=1', [], '`reported_at` DESC, `updated_at` DESC', 50),
            'versions' => $this->listAgentVersions(),
            'retentions' => [
                'scans' => '90 jours',
                'changements' => '180 jours',
                'alertes resolues' => '365 jours',
                'commandes' => '30 jours',
                'logs techniques' => '30 jours',
                'audit securite' => '365 jours',
                'details temporaires' => 'lecture ou 15 minutes',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAgentsForOwner(int $ownerId): array
    {
        return $this->fetchRows(
            'pb_agents',
            '`owner_id` = :owner_id',
            ['owner_id' => $ownerId],
            '`status` ASC, `last_seen_at` DESC, `created_at` DESC',
            100
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listNetworksForOwner(int $ownerId): array
    {
        return $this->fetchRows('security_networks', '`owner_id` = :owner_id', ['owner_id' => $ownerId], '`updated_at` DESC', 100);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listDevicesForOwner(int $ownerId, int $limit = 100): array
    {
        return $this->fetchRows('security_devices_current', '`owner_id` = :owner_id', ['owner_id' => $ownerId], '`risk_level` DESC, `last_seen_at` DESC', $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAlertsForOwner(int $ownerId, int $limit = 100): array
    {
        return $this->fetchRows('security_alerts', '`owner_id` = :owner_id', ['owner_id' => $ownerId], '`status` ASC, `severity` DESC, `last_seen_at` DESC', $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listCommandsForOwner(int $ownerId, int $limit = 100): array
    {
        return $this->fetchRows('pb_commands', '`owner_id` = :owner_id', ['owner_id' => $ownerId], '`created_at` DESC', $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listBackupsForOwner(int $ownerId): array
    {
        return $this->fetchRows('pb_backup_status', '`owner_id` = :owner_id', ['owner_id' => $ownerId], '`reported_at` DESC', 100);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAgentVersions(): array
    {
        return $this->fetchRows('pb_agent_versions', '1=1', [], '`created_at` DESC', 50);
    }

    public function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        $this->database->ensureReady();
        $this->ensurePrivateModuleRegistryTables();
        foreach ([ROOT_PATH . '/sql/private/pbgestion.sql', ROOT_PATH . '/sql/private/security_center.sql'] as $filePath) {
            if (!is_file($filePath)) {
                throw new RuntimeException('Migration Sécurité réseau introuvable: ' . $filePath);
            }

            $sql = file_get_contents($filePath);
            if ($sql === false) {
                throw new RuntimeException('Migration Sécurité réseau illisible: ' . $filePath);
            }

            foreach ($this->splitSql($this->rewritePrefix($sql)) as $statement) {
                $this->pdo()->exec($statement);
            }
        }

        $this->schemaReady = true;
    }

    private function ensurePrivateModuleRegistryTables(): void
    {
        $this->pdo()->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `code` VARCHAR(64) NOT NULL UNIQUE,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `display_name` VARCHAR(128) NOT NULL,
                    `description` TEXT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY `idx_private_modules_active` (`is_active`),
                    KEY `idx_private_modules_code` (`code`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->table('private_modules')
            )
        );
        $this->pdo()->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `private_user_id` INT NOT NULL,
                    `private_module_id` INT NOT NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `granted_by_admin_email` VARCHAR(254) NULL,
                    `granted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `revoked_at` DATETIME NULL,
                    `revoked_by_admin_email` VARCHAR(254) NULL,
                    UNIQUE KEY `uq_private_user_module_permissions_user_module` (`private_user_id`, `private_module_id`),
                    KEY `idx_private_user_module_permissions_user` (`private_user_id`),
                    KEY `idx_private_user_module_permissions_module` (`private_module_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->table('private_user_module_permissions')
            )
        );
    }

    /**
     * @param array<string, mixed> $token
     * @param array<string, mixed> $payload
     * @return array{ok: bool, error?: string, agent?: array<string, mixed>}
     */
    private function attachAgentToToken(array $token, array $payload, string $publicKey): array
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $ownerId = (int) ($token['owner_id'] ?? 0);
            $tokenId = (int) ($token['id'] ?? 0);
            if ($ownerId <= 0 || $tokenId <= 0) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => AgentErrorCodes::ENROLLMENT_INVALID];
            }

            $now = $this->now();
            $agentUid = bin2hex(random_bytes(16));
            $displayName = $this->shortText((string) ($payload['display_name'] ?? 'Agent Sécurité réseau'), 160) ?: 'Agent Sécurité réseau';
            $capabilities = is_array($payload['capabilities'] ?? null) ? array_values($payload['capabilities']) : [];
            $insert = $pdo->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`owner_id`, `agent_uid`, `display_name`, `public_key_base64`, `status`, `os_family`,
                         `os_version`, `agent_version`, `location_label`, `capabilities_json`, `created_at`, `updated_at`)
                     VALUES
                        (:owner_id, :agent_uid, :display_name, :public_key, \'active\', :os_family,
                         :os_version, :agent_version, :location_label, :capabilities_json, :created_at, :updated_at)',
                    $this->table('pb_agents')
                )
            );
            $insert->execute([
                'owner_id' => $ownerId,
                'agent_uid' => $agentUid,
                'display_name' => $displayName,
                'public_key' => $publicKey,
                'os_family' => $this->shortText((string) ($payload['os_family'] ?? 'windows'), 32) ?: 'windows',
                'os_version' => $this->shortText((string) ($payload['os_version'] ?? ''), 80) ?: null,
                'agent_version' => $this->shortText((string) ($payload['agent_version'] ?? ''), 80) ?: null,
                'location_label' => $this->shortText((string) ($token['location_label'] ?? ''), 160) ?: null,
                'capabilities_json' => $this->json($capabilities),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $agentId = (int) $pdo->lastInsertId();

            $update = $pdo->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `status` = \'claimed\', `claimed_at` = :claimed_at, `claimed_agent_id` = :agent_id, `updated_at` = :updated_at
                     WHERE `id` = :token_id AND `status` = \'pending\'',
                    $this->table('pb_enrollment_tokens')
                )
            );
            $update->execute([
                'claimed_at' => $now,
                'agent_id' => $agentId,
                'updated_at' => $now,
                'token_id' => $tokenId,
            ]);

            foreach ($capabilities as $capability) {
                if (!is_string($capability) || trim($capability) === '') {
                    continue;
                }
                $capabilityStatement = $pdo->prepare(
                    sprintf(
                        'INSERT IGNORE INTO `%s`
                            (`owner_id`, `agent_id`, `capability_code`, `is_enabled`, `created_at`, `updated_at`)
                         VALUES
                            (:owner_id, :agent_id, :capability_code, 1, :created_at, :updated_at)',
                        $this->table('pb_agent_capabilities')
                    )
                );
                $capabilityStatement->execute([
                    'owner_id' => $ownerId,
                    'agent_id' => $agentId,
                    'capability_code' => $this->shortText($capability, 80),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->upsertSyncState($ownerId, $agentId, ['last_sequence' => 0]);
            $pdo->commit();

            return [
                'ok' => true,
                'agent' => [
                    'id' => $agentId,
                    'owner_id' => $ownerId,
                    'agent_uid' => $agentUid,
                    'display_name' => $displayName,
                ],
            ];
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function incrementEnrollmentAttempts(): void
    {
        $statement = $this->pdo()->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `attempts` = LEAST(`attempts` + 1, `max_attempts`), `updated_at` = :updated_at
                 WHERE `status` = \'pending\' AND `expires_at` >= :now',
                $this->table('pb_enrollment_tokens')
            )
        );
        $now = $this->now();
        $statement->execute(['updated_at' => $now, 'now' => $now]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function updateAgentRuntime(int $agentId, int $ownerId, array $payload): void
    {
        $fields = [
            'os_version' => $this->shortText((string) ($payload['os_version'] ?? ''), 80) ?: null,
            'agent_version' => $this->shortText((string) ($payload['agent_version'] ?? ''), 80) ?: null,
            'capabilities_json' => is_array($payload['capabilities'] ?? null) ? $this->json(array_values($payload['capabilities'])) : null,
            'last_seen_at' => $this->now(),
            'updated_at' => $this->now(),
            'agent_id' => $agentId,
            'owner_id' => $ownerId,
        ];
        $statement = $this->pdo()->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `os_version` = COALESCE(:os_version, `os_version`),
                     `agent_version` = COALESCE(:agent_version, `agent_version`),
                     `capabilities_json` = COALESCE(:capabilities_json, `capabilities_json`),
                     `last_seen_at` = :last_seen_at,
                     `updated_at` = :updated_at
                 WHERE `id` = :agent_id AND `owner_id` = :owner_id',
                $this->table('pb_agents')
            )
        );
        $statement->execute($fields);
    }

    /**
     * @param array<string, mixed> $network
     * @return array<string, mixed>|null
     */
    private function upsertNetwork(int $ownerId, array $network): ?array
    {
        $token = $this->networkService()->normalizeToken($network['network_token'] ?? null);
        if ($token === '') {
            return null;
        }

        $trustState = $this->networkService()->normalizeTrustState($network['trust_state'] ?? null);
        $now = $this->now();
        $statement = $this->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`owner_id`, `network_token`, `trust_state`, `display_label`, `last_seen_at`, `created_at`, `updated_at`)
                 VALUES
                    (:owner_id, :network_token, :trust_state, :display_label, :last_seen_at, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    `trust_state` = VALUES(`trust_state`),
                    `display_label` = COALESCE(VALUES(`display_label`), `display_label`),
                    `last_seen_at` = VALUES(`last_seen_at`),
                    `updated_at` = VALUES(`updated_at`)',
                $this->table('security_networks')
            )
        );
        $statement->execute([
            'owner_id' => $ownerId,
            'network_token' => $token,
            'trust_state' => $trustState,
            'display_label' => $this->shortText((string) ($network['display_label'] ?? ''), 160) ?: null,
            'last_seen_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $select = $this->pdo()->prepare(
            sprintf(
                'SELECT * FROM `%s` WHERE `owner_id` = :owner_id AND `network_token` = :network_token LIMIT 1',
                $this->table('security_networks')
            )
        );
        $select->execute(['owner_id' => $ownerId, 'network_token' => $token]);
        $row = $select->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $posture
     */
    private function upsertPosture(int $ownerId, int $agentId, array $posture): void
    {
        $normalized = $this->postureNormalizer()->normalize($posture);
        $now = $this->now();
        $reportedAt = $this->dateOrNow($posture['reported_at'] ?? null);
        $statement = $this->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`owner_id`, `agent_id`, `posture_state`, `risk_level`, `summary_json`, `reported_at`, `created_at`, `updated_at`)
                 VALUES
                    (:owner_id, :agent_id, :posture_state, :risk_level, :summary_json, :reported_at, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    `posture_state` = VALUES(`posture_state`),
                    `risk_level` = VALUES(`risk_level`),
                    `summary_json` = VALUES(`summary_json`),
                    `reported_at` = VALUES(`reported_at`),
                    `updated_at` = VALUES(`updated_at`)',
                $this->table('security_posture_current')
            )
        );
        $statement->execute([
            'owner_id' => $ownerId,
            'agent_id' => $agentId,
            'posture_state' => $normalized['posture_state'],
            'risk_level' => $normalized['risk_level'],
            'summary_json' => $this->json($normalized['summary']),
            'reported_at' => $reportedAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->upsertSyncState($ownerId, $agentId, ['last_posture_at' => $reportedAt]);
    }

    /**
     * @param array<string, mixed>|null $networkRow
     * @param array<string, mixed> $scan
     * @return array<string, mixed>
     */
    private function storeScanSummary(int $ownerId, int $agentId, ?array $networkRow, array $scan): array
    {
        $normalized = $this->scanNormalizer()->normalize($scan);
        $trustState = is_array($networkRow) ? (string) ($networkRow['trust_state'] ?? 'pending') : 'pending';
        if ($normalized['scan_type'] === 'active_limited' && !$this->networkService()->activeScanAllowed($trustState)) {
            return ['ok' => false, 'error' => AgentErrorCodes::NETWORK_NOT_TRUSTED];
        }

        $reportedEpoch = max(0, (int) ($scan['collector_epoch'] ?? 0));
        $storedEpoch = is_array($networkRow) ? $this->storedCollectorEpoch((int) $networkRow['id']) : 0;
        if (!$this->networkService()->isCollectorEpochFresh($reportedEpoch, $storedEpoch)) {
            return ['ok' => false, 'error' => AgentErrorCodes::COLLECTOR_EPOCH_STALE];
        }

        $now = $this->now();
        $scannedAt = $this->dateOrNow($scan['scanned_at'] ?? null);
        $statement = $this->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`owner_id`, `agent_id`, `network_id`, `collector_epoch`, `scan_type`, `status`, `devices_seen`,
                     `changes_seen`, `alerts_opened`, `summary_json`, `scanned_at`, `created_at`, `updated_at`)
                 VALUES
                    (:owner_id, :agent_id, :network_id, :collector_epoch, :scan_type, :status, :devices_seen,
                     :changes_seen, :alerts_opened, :summary_json, :scanned_at, :created_at, :updated_at)',
                $this->table('security_scan_summaries')
            )
        );
        $statement->execute([
            'owner_id' => $ownerId,
            'agent_id' => $agentId,
            'network_id' => is_array($networkRow) ? (int) $networkRow['id'] : null,
            'collector_epoch' => $reportedEpoch,
            'scan_type' => $normalized['scan_type'],
            'status' => $normalized['status'],
            'devices_seen' => $normalized['devices_seen'],
            'changes_seen' => $normalized['changes_seen'],
            'alerts_opened' => $normalized['alerts_opened'],
            'summary_json' => $this->json(is_array($scan['summary'] ?? null) ? $scan['summary'] : []),
            'scanned_at' => $scannedAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (is_array($networkRow)) {
            $this->upsertCollector($ownerId, (int) $networkRow['id'], $agentId, $reportedEpoch);
        }
        $this->upsertSyncState($ownerId, $agentId, ['last_scan_summary_at' => $scannedAt, 'collector_epoch' => $reportedEpoch]);

        return ['ok' => true];
    }

    private function storedCollectorEpoch(int $networkId): int
    {
        $statement = $this->pdo()->prepare(
            sprintf(
                'SELECT `collector_epoch` FROM `%s` WHERE `network_id` = :network_id LIMIT 1',
                $this->table('security_network_collectors')
            )
        );
        $statement->execute(['network_id' => $networkId]);
        $value = $statement->fetchColumn();

        return is_numeric($value) ? (int) $value : 0;
    }

    private function upsertCollector(int $ownerId, int $networkId, int $agentId, int $epoch): void
    {
        $now = $this->now();
        $statement = $this->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`owner_id`, `network_id`, `collector_agent_id`, `collector_epoch`, `lease_expires_at`, `last_renewed_at`, `created_at`, `updated_at`)
                 VALUES
                    (:owner_id, :network_id, :agent_id, :epoch, :lease_expires_at, :last_renewed_at, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    `collector_agent_id` = VALUES(`collector_agent_id`),
                    `collector_epoch` = VALUES(`collector_epoch`),
                    `lease_expires_at` = VALUES(`lease_expires_at`),
                    `last_renewed_at` = VALUES(`last_renewed_at`),
                    `updated_at` = VALUES(`updated_at`)',
                $this->table('security_network_collectors')
            )
        );
        $statement->execute([
            'owner_id' => $ownerId,
            'network_id' => $networkId,
            'agent_id' => $agentId,
            'epoch' => $epoch,
            'lease_expires_at' => gmdate('Y-m-d H:i:s', time() + 180),
            'last_renewed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $device
     */
    private function upsertDevice(int $ownerId, int $networkId, array $device): void
    {
        $normalized = $this->deviceNormalizer()->normalize($device);
        $now = $this->now();
        $lastSeenAt = $this->dateOrNow($device['last_seen_at'] ?? null);
        $statement = $this->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`owner_id`, `network_id`, `device_token`, `device_kind`, `risk_level`, `first_seen_at`, `last_seen_at`,
                     `summary_json`, `created_at`, `updated_at`)
                 VALUES
                    (:owner_id, :network_id, :device_token, :device_kind, :risk_level, :first_seen_at, :last_seen_at,
                     :summary_json, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    `network_id` = VALUES(`network_id`),
                    `device_kind` = VALUES(`device_kind`),
                    `risk_level` = VALUES(`risk_level`),
                    `last_seen_at` = VALUES(`last_seen_at`),
                    `summary_json` = VALUES(`summary_json`),
                    `updated_at` = VALUES(`updated_at`)',
                $this->table('security_devices_current')
            )
        );
        $statement->execute([
            'owner_id' => $ownerId,
            'network_id' => $networkId,
            'device_token' => $normalized['device_token'],
            'device_kind' => $normalized['device_kind'],
            'risk_level' => $normalized['risk_level'],
            'first_seen_at' => $this->dateOrNow($device['first_seen_at'] ?? null),
            'last_seen_at' => $lastSeenAt,
            'summary_json' => $this->json($normalized['summary']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $change
     */
    private function insertDeviceChange(int $ownerId, int $networkId, array $change): void
    {
        $token = is_string($change['device_token'] ?? null) ? trim((string) $change['device_token']) : '';
        if ($token === '') {
            return;
        }

        $now = $this->now();
        $statement = $this->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`owner_id`, `network_id`, `device_token`, `change_type`, `summary_json`, `detected_at`, `created_at`, `updated_at`)
                 VALUES
                    (:owner_id, :network_id, :device_token, :change_type, :summary_json, :detected_at, :created_at, :updated_at)',
                $this->table('security_device_changes')
            )
        );
        $statement->execute([
            'owner_id' => $ownerId,
            'network_id' => $networkId,
            'device_token' => $this->shortText($token, 96),
            'change_type' => $this->shortText((string) ($change['change_type'] ?? 'changed'), 64) ?: 'changed',
            'summary_json' => $this->json(is_array($change['summary'] ?? null) ? $change['summary'] : []),
            'detected_at' => $this->dateOrNow($change['detected_at'] ?? null),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $alert
     */
    private function upsertAlert(int $ownerId, array $alert): void
    {
        $source = is_string($alert['source'] ?? null) ? (string) $alert['source'] : 'agent';
        $subject = is_string($alert['subject_token'] ?? null) ? (string) $alert['subject_token'] : 'general';
        $type = is_string($alert['type'] ?? null) ? (string) $alert['type'] : 'notice';
        $logicalKey = $this->alertDeduplicator()->logicalKey($source, $subject, $type);
        $now = $this->now();
        $severity = is_string($alert['severity'] ?? null) ? strtolower(trim((string) $alert['severity'])) : 'info';
        if (!in_array($severity, ['info', 'warning', 'high', 'critical'], true)) {
            $severity = 'info';
        }

        $statement = $this->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`owner_id`, `alert_uid`, `logical_key`, `severity`, `status`, `title`, `summary`,
                     `opened_at`, `last_seen_at`, `created_at`, `updated_at`)
                 VALUES
                    (:owner_id, :alert_uid, :logical_key, :severity, \'open\', :title, :summary,
                     :opened_at, :last_seen_at, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    `severity` = VALUES(`severity`),
                    `title` = VALUES(`title`),
                    `summary` = VALUES(`summary`),
                    `last_seen_at` = VALUES(`last_seen_at`),
                    `updated_at` = VALUES(`updated_at`)',
                $this->table('security_alerts')
            )
        );
        $statement->execute([
            'owner_id' => $ownerId,
            'alert_uid' => bin2hex(random_bytes(16)),
            'logical_key' => $logicalKey,
            'severity' => $severity,
            'title' => $this->shortText((string) ($alert['title'] ?? 'Alerte Sécurité réseau'), 190) ?: 'Alerte Sécurité réseau',
            'summary' => $this->shortText((string) ($alert['summary'] ?? 'Un signal de securite demande une verification.'), 500),
            'opened_at' => $this->dateOrNow($alert['opened_at'] ?? null),
            'last_seen_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $backup
     */
    private function upsertBackupStatus(int $ownerId, int $agentId, array $backup): void
    {
        $now = $this->now();
        $statement = $this->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`owner_id`, `agent_id`, `snapshot_state`, `external_backup_state`, `external_volume_token`,
                     `last_snapshot_at`, `last_external_backup_at`, `last_verify_at`, `restore_state`, `reported_at`, `created_at`, `updated_at`)
                 VALUES
                    (:owner_id, :agent_id, :snapshot_state, :external_backup_state, :external_volume_token,
                     :last_snapshot_at, :last_external_backup_at, :last_verify_at, :restore_state, :reported_at, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    `snapshot_state` = VALUES(`snapshot_state`),
                    `external_backup_state` = VALUES(`external_backup_state`),
                    `external_volume_token` = VALUES(`external_volume_token`),
                    `last_snapshot_at` = VALUES(`last_snapshot_at`),
                    `last_external_backup_at` = VALUES(`last_external_backup_at`),
                    `last_verify_at` = VALUES(`last_verify_at`),
                    `restore_state` = VALUES(`restore_state`),
                    `reported_at` = VALUES(`reported_at`),
                    `updated_at` = VALUES(`updated_at`)',
                $this->table('pb_backup_status')
            )
        );
        $statement->execute([
            'owner_id' => $ownerId,
            'agent_id' => $agentId,
            'snapshot_state' => $this->state((string) ($backup['snapshot_state'] ?? 'unknown')),
            'external_backup_state' => $this->state((string) ($backup['external_backup_state'] ?? 'unknown')),
            'external_volume_token' => $this->shortText((string) ($backup['external_volume_token'] ?? ''), 96) ?: null,
            'last_snapshot_at' => $this->dateOrNull($backup['last_snapshot_at'] ?? null),
            'last_external_backup_at' => $this->dateOrNull($backup['last_external_backup_at'] ?? null),
            'last_verify_at' => $this->dateOrNull($backup['last_verify_at'] ?? null),
            'restore_state' => $this->state((string) ($backup['restore_state'] ?? 'not_requested')),
            'reported_at' => $this->dateOrNow($backup['reported_at'] ?? null),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function upsertSyncState(int $ownerId, int $agentId, array $fields): void
    {
        $now = $this->now();
        $statement = $this->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`owner_id`, `agent_id`, `last_sequence`, `last_sync_at`, `last_posture_at`, `last_scan_summary_at`,
                     `coverage_state`, `collector_epoch`, `created_at`, `updated_at`)
                 VALUES
                    (:owner_id, :agent_id, :last_sequence, :last_sync_at, :last_posture_at, :last_scan_summary_at,
                     :coverage_state, :collector_epoch, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    `last_sequence` = GREATEST(`last_sequence`, VALUES(`last_sequence`)),
                    `last_sync_at` = COALESCE(VALUES(`last_sync_at`), `last_sync_at`),
                    `last_posture_at` = COALESCE(VALUES(`last_posture_at`), `last_posture_at`),
                    `last_scan_summary_at` = COALESCE(VALUES(`last_scan_summary_at`), `last_scan_summary_at`),
                    `coverage_state` = VALUES(`coverage_state`),
                    `collector_epoch` = GREATEST(`collector_epoch`, VALUES(`collector_epoch`)),
                    `updated_at` = VALUES(`updated_at`)',
                $this->table('pb_agent_sync_state')
            )
        );
        $lastSync = is_string($fields['last_sync_at'] ?? null) ? (string) $fields['last_sync_at'] : null;
        $statement->execute([
            'owner_id' => $ownerId,
            'agent_id' => $agentId,
            'last_sequence' => max(0, (int) ($fields['last_sequence'] ?? 0)),
            'last_sync_at' => $lastSync,
            'last_posture_at' => is_string($fields['last_posture_at'] ?? null) ? (string) $fields['last_posture_at'] : null,
            'last_scan_summary_at' => is_string($fields['last_scan_summary_at'] ?? null) ? (string) $fields['last_scan_summary_at'] : null,
            'coverage_state' => $this->coverageCalculator()->coverageState($lastSync),
            'collector_epoch' => max(0, (int) ($fields['collector_epoch'] ?? 0)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function createDetailRequest(int $ownerId, int $agentId, string $detailUid, string $requestUid, string $purpose): void
    {
        $now = $this->now();
        $statement = $this->pdo()->prepare(
            sprintf(
                'INSERT IGNORE INTO `%s`
                    (`owner_id`, `agent_id`, `detail_uid`, `request_uid`, `purpose`, `status`, `requested_at`, `expires_at`, `created_at`, `updated_at`)
                 VALUES
                    (:owner_id, :agent_id, :detail_uid, :request_uid, :purpose, \'requested\', :requested_at, :expires_at, :created_at, :updated_at)',
                $this->table('security_detail_requests')
            )
        );
        $statement->execute([
            'owner_id' => $ownerId,
            'agent_id' => $agentId,
            'detail_uid' => strtolower($detailUid),
            'request_uid' => strtolower($requestUid),
            'purpose' => $this->shortText($purpose, 120),
            'requested_at' => $now,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 900),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function expireCommands(): void
    {
        $statement = $this->pdo()->prepare(
            sprintf(
                'UPDATE `%s` SET `status` = \'expired\', `updated_at` = :updated_at
                 WHERE `status` IN (\'pending\', \'delivered\', \'running\') AND `expires_at` < :now',
                $this->table('pb_commands')
            )
        );
        $now = $this->now();
        $statement->execute(['updated_at' => $now, 'now' => $now]);
    }

    private function cleanupExpiredRequestLog(): void
    {
        $statement = $this->pdo()->prepare(
            sprintf('DELETE FROM `%s` WHERE `expires_at` < :now LIMIT 500', $this->table('pb_agent_request_log'))
        );
        $statement->execute(['now' => $this->now()]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findCommandByIdempotency(int $ownerId, int $agentId, string $idempotencyKey): ?array
    {
        $statement = $this->pdo()->prepare(
            sprintf(
                'SELECT * FROM `%s`
                 WHERE `owner_id` = :owner_id AND `agent_id` = :agent_id AND `idempotency_key` = :idempotency_key
                 LIMIT 1',
                $this->table('pb_commands')
            )
        );
        $statement->execute([
            'owner_id' => $ownerId,
            'agent_id' => $agentId,
            'idempotency_key' => $idempotencyKey,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateCommand($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findCommandByUid(string $commandUid): ?array
    {
        $statement = $this->pdo()->prepare(
            sprintf('SELECT * FROM `%s` WHERE `command_uid` = :command_uid LIMIT 1', $this->table('pb_commands'))
        );
        $statement->execute(['command_uid' => $commandUid]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateCommand($row) : null;
    }

    private function nextServerSequence(int $agentId): int
    {
        $statement = $this->pdo()->prepare(
            sprintf(
                'SELECT COALESCE(MAX(`server_sequence`), 0) + 1 FROM `%s` WHERE `agent_id` = :agent_id',
                $this->table('pb_commands')
            )
        );
        $statement->execute(['agent_id' => $agentId]);

        return max(1, (int) $statement->fetchColumn());
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrateCommand(array $row): array
    {
        $payload = json_decode((string) ($row['payload_json'] ?? '{}'), true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $row['payload'] = $payload;
        unset($row['payload_json']);

        return $row;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function countRows(string $table, string $where, array $params): int
    {
        $this->ensureSchema();
        $statement = $this->pdo()->prepare(sprintf('SELECT COUNT(*) FROM `%s` WHERE %s', $this->table($table), $where));
        $statement->execute($params);

        return max(0, (int) $statement->fetchColumn());
    }

    /**
     * @param array<string, mixed> $params
     */
    private function countDistinctRows(string $table, string $column, string $where, array $params): int
    {
        $this->ensureSchema();
        $statement = $this->pdo()->prepare(
            sprintf('SELECT COUNT(DISTINCT `%s`) FROM `%s` WHERE %s', $column, $this->table($table), $where)
        );
        $statement->execute($params);

        return max(0, (int) $statement->fetchColumn());
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private function fetchRows(string $table, string $where, array $params, string $orderBy, int $limit): array
    {
        $this->ensureSchema();
        $limit = max(1, min(200, $limit));
        $statement = $this->pdo()->prepare(
            sprintf('SELECT * FROM `%s` WHERE %s ORDER BY %s LIMIT %d', $this->table($table), $where, $orderBy, $limit)
        );
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_filter(is_array($rows) ? $rows : [], 'is_array'));
    }

    private function randomEnrollmentCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 12; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }

    private function normalizeEnrollmentCode(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $code = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $value) ?? '');

        return preg_match('/\A[A-Z2-9]{12}\z/', $code) === 1 ? $code : '';
    }

    private function validPublicKey(string $publicKey): bool
    {
        $decoded = base64_decode($publicKey, true);
        $expectedLength = defined('SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES') ? SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES : 32;

        return is_string($decoded) && strlen($decoded) === $expectedLength;
    }

    private function json(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($json) ? $json : '{}';
    }

    private function dateOrNow(mixed $value): string
    {
        return $this->dateOrNull($value) ?? $this->now();
    }

    private function dateOrNull(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function state(string $value): string
    {
        $state = strtolower(trim($value));
        $state = preg_replace('/[^a-z0-9_-]+/', '_', $state) ?? '';

        return $this->shortText($state, 32) ?: 'unknown';
    }

    private function shortText(string $value, int $max): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        if ($value === '') {
            return '';
        }

        return mb_substr($value, 0, $max);
    }

    /**
     * @return array<int, string>
     */
    private function splitSql(string $sql): array
    {
        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];

        return array_values(
            array_filter(
                array_map(static function (string $statement): string {
                    $lines = preg_split('/\r?\n/', $statement) ?: [];
                    $lines = array_values(array_filter(
                        $lines,
                        static fn (string $line): bool => !str_starts_with(ltrim($line), '--')
                    ));

                    return trim(implode("\n", $lines));
                }, $statements),
                static fn (string $statement): bool => $statement !== ''
            )
        );
    }

    private function rewritePrefix(string $sql): string
    {
        $prefix = $this->tablePrefix();
        if ($prefix === 'car_') {
            return $sql;
        }

        return (string) preg_replace('/\bcar_([a-zA-Z0-9_]+)\b/', $prefix . '$1', $sql);
    }

    private function tablePrefix(): string
    {
        $sample = $this->database->table('x');

        return substr($sample, 0, -1) ?: '';
    }

    private function table(string $name): string
    {
        return $this->database->table($name);
    }

    private function pdo(): PDO
    {
        return $this->database->pdo();
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    private function commandPolicy(): CommandPolicy
    {
        return $this->commandPolicy ?? new CommandPolicy();
    }

    private function networkService(): SecurityNetworkService
    {
        return $this->networkService ?? new SecurityNetworkService();
    }

    private function alertDeduplicator(): AlertDeduplicator
    {
        return $this->alertDeduplicator ?? new AlertDeduplicator();
    }

    private function coverageCalculator(): CoverageCalculator
    {
        return $this->coverageCalculator ?? new CoverageCalculator();
    }

    private function deviceNormalizer(): DeviceSummaryNormalizer
    {
        return $this->deviceNormalizer ?? new DeviceSummaryNormalizer();
    }

    private function scanNormalizer(): ScanSummaryNormalizer
    {
        return $this->scanNormalizer ?? new ScanSummaryNormalizer();
    }

    private function postureNormalizer(): PostureNormalizer
    {
        return $this->postureNormalizer ?? new PostureNormalizer();
    }
}
