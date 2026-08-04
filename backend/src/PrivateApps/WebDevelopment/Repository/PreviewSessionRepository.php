<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\WebDevelopment\Repository;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class PreviewSessionRepository
{
    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    /**
     * @return array{token: string, expiresAt: int}
     */
    public function create(int $projectId, int $privateUserId, string $clientIp, string $userAgent, int $ttlSeconds): array
    {
        $token = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $expiresAt = time() + max(300, $ttlSeconds);
        $now = $this->currentDateTime();

        try {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`project_id`, `private_user_id`, `session_hash`, `client_ip_hash`, `user_agent_hash`, `created_at`, `last_seen_at`, `expires_at`, `revoked_at`)
                     VALUES
                        (:project_id, :private_user_id, :session_hash, :client_ip_hash, :user_agent_hash, :created_at, :last_seen_at, :expires_at, NULL)',
                    $this->database->table('web_development_preview_sessions')
                )
            );
            $statement->execute([
                'project_id' => $projectId,
                'private_user_id' => $privateUserId,
                'session_hash' => hash('sha256', $token),
                'client_ip_hash' => $this->hashClientValue($clientIp),
                'user_agent_hash' => $this->hashClientValue($userAgent),
                'created_at' => $now,
                'last_seen_at' => $now,
                'expires_at' => gmdate('Y-m-d H:i:s', $expiresAt),
            ]);
        } catch (\Throwable) {
            return [
                'token' => '',
                'expiresAt' => 0,
            ];
        }

        return [
            'token' => $token,
            'expiresAt' => $expiresAt,
        ];
    }

    public function isValidForProject(string $token, int $projectId, string $clientIp, string $userAgent): bool
    {
        if ($token === '' || $projectId <= 0) {
            return false;
        }

        try {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT `id`
                     FROM `%s`
                     WHERE `project_id` = :project_id
                       AND `session_hash` = :session_hash
                       AND `revoked_at` IS NULL
                       AND `expires_at` >= :now
                     LIMIT 1',
                    $this->database->table('web_development_preview_sessions')
                )
            );
            $statement->execute([
                'project_id' => $projectId,
                'session_hash' => hash('sha256', $token),
                'now' => $this->currentDateTime(),
            ]);
            $id = (int) $statement->fetchColumn();
            if ($id <= 0) {
                return false;
            }

            $this->touch($id, $clientIp, $userAgent);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function touch(int $sessionId, string $clientIp, string $userAgent): void
    {
        try {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `last_seen_at` = :last_seen_at,
                         `client_ip_hash` = :client_ip_hash,
                         `user_agent_hash` = :user_agent_hash
                     WHERE `id` = :id',
                    $this->database->table('web_development_preview_sessions')
                )
            );
            $statement->execute([
                'last_seen_at' => $this->currentDateTime(),
                'client_ip_hash' => $this->hashClientValue($clientIp),
                'user_agent_hash' => $this->hashClientValue($userAgent),
                'id' => $sessionId,
            ]);
        } catch (\Throwable) {
            return;
        }
    }

    private function currentDateTime(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    private function hashClientValue(string $value): string
    {
        return hash('sha256', trim($value));
    }
}
