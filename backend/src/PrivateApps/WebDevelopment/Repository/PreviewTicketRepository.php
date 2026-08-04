<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\WebDevelopment\Repository;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class PreviewTicketRepository
    implements PreviewTicketRepositoryInterface
{
    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    /**
     * @return array{id: int, project_id: int, private_user_id: int}|null
     */
    public function consume(string $ticket, string $clientIp, string $userAgent): ?array
    {
        $ticketHash = hash('sha256', $ticket);
        $now = $this->currentDateTime();

        try {
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();

            $statement = $pdo->prepare(
                sprintf(
                    'SELECT `id`, `project_id`, `private_user_id`
                     FROM `%s`
                     WHERE `ticket_hash` = :ticket_hash
                       AND `consumed_at` IS NULL
                       AND `revoked_at` IS NULL
                       AND `expires_at` >= :now
                     LIMIT 1',
                    $this->database->table('web_development_preview_tickets')
                )
            );
            $statement->execute([
                'ticket_hash' => $ticketHash,
                'now' => $now,
            ]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                $pdo->rollBack();
                return null;
            }

            $update = $pdo->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `consumed_at` = :consumed_at,
                         `consumed_ip_hash` = :ip_hash,
                         `consumed_user_agent_hash` = :user_agent_hash
                     WHERE `id` = :id AND `consumed_at` IS NULL',
                    $this->database->table('web_development_preview_tickets')
                )
            );
            $update->execute([
                'consumed_at' => $now,
                'ip_hash' => $this->hashClientValue($clientIp),
                'user_agent_hash' => $this->hashClientValue($userAgent),
                'id' => (int) $row['id'],
            ]);

            if ($update->rowCount() !== 1) {
                $pdo->rollBack();
                return null;
            }

            $pdo->commit();
        } catch (\Throwable) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return null;
        }

        $id = (int) ($row['id'] ?? 0);
        $projectId = (int) ($row['project_id'] ?? 0);
        $userId = (int) ($row['private_user_id'] ?? 0);
        if ($id <= 0 || $projectId <= 0 || $userId <= 0) {
            return null;
        }

        return [
            'id' => $id,
            'project_id' => $projectId,
            'private_user_id' => $userId,
        ];
    }

    public function create(int $projectId, int $privateUserId, int $ttlSeconds): string
    {
        if ($projectId <= 0 || $privateUserId <= 0) {
            return '';
        }

        $expiresAt = time() + max(30, min(600, $ttlSeconds));
        $expiresAtDateTime = gmdate('Y-m-d H:i:s', $expiresAt);
        $now = $this->currentDateTime();

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $ticket = $this->generateTicket();
            $ticketHash = hash('sha256', $ticket);

            try {
                $statement = $this->database->pdo()->prepare(
                    sprintf(
                        'INSERT INTO `%s`
                            (`project_id`, `private_user_id`, `ticket_hash`, `expires_at`, `created_at`, `revoked_at`)
                         VALUES
                            (:project_id, :private_user_id, :ticket_hash, :expires_at, :created_at, NULL)',
                        $this->database->table('web_development_preview_tickets')
                    )
                );
                $statement->execute([
                    'project_id' => $projectId,
                    'private_user_id' => $privateUserId,
                    'ticket_hash' => $ticketHash,
                    'expires_at' => $expiresAtDateTime,
                    'created_at' => $now,
                ]);

                return $ticket;
            } catch (\Throwable $exception) {
                if (!str_contains((string) $exception->getMessage(), '1062')) {
                    return '';
                }
            }
        }

        return '';
    }

    private function generateTicket(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
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
