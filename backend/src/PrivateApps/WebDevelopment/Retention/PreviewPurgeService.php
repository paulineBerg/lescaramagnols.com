<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\WebDevelopment\Retention;

use Caramagnols\Database\EditorialDatabase;
use PDO;
use Throwable;

final class PreviewPurgeService
{
    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    /**
     * Purge les données d'accès expirées pour les previews.
     *
     * @return array{tickets_purged:int, sessions_purged:int, iterations:int}
     */
    public function purgeExpired(int $batchSize = 500, int $maxIterations = 20, bool $dryRun = false): array
    {
        $batchSize = max(50, min(5000, $batchSize));
        $maxIterations = max(1, min(200, $maxIterations));

        $now = gmdate('Y-m-d H:i:s');
        $result = [
            'tickets_purged' => 0,
            'sessions_purged' => 0,
            'iterations' => 0,
        ];

        try {
            if ($dryRun) {
                $result['tickets_purged'] = $this->countTicketsToPurge($now);
                $result['sessions_purged'] = $this->countSessionsToPurge($now);
                return $result;
            }

            $this->database->ensureReady();
            $pdo = $this->database->pdo();
            for ($i = 1; $i <= $maxIterations; $i++) {
                $ticketDeleted = $this->purgeTickets($pdo, $now, $batchSize);
                $sessionDeleted = $this->purgeSessions($pdo, $now, $batchSize);
                ++$result['iterations'];
                $result['tickets_purged'] += $ticketDeleted;
                $result['sessions_purged'] += $sessionDeleted;

                if ($ticketDeleted < $batchSize && $sessionDeleted < $batchSize) {
                    break;
                }
            }
        } catch (Throwable) {
            return $result;
        }

        return $result;
    }

    private function purgeTickets(PDO $pdo, string $now, int $batchSize): int
    {
        $statement = $pdo->prepare(
            sprintf(
                'DELETE FROM `%s`
                 WHERE (`revoked_at` IS NOT NULL
                        OR `consumed_at` IS NOT NULL
                        OR `expires_at` < :now)
                 LIMIT :limit',
                $this->database->table('web_development_preview_tickets')
            )
        );
        $statement->bindValue('now', $now, PDO::PARAM_STR);
        $statement->bindValue('limit', $batchSize, PDO::PARAM_INT);
        $statement->execute();

        return max(0, $statement->rowCount());
    }

    private function purgeSessions(PDO $pdo, string $now, int $batchSize): int
    {
        $statement = $pdo->prepare(
            sprintf(
                'DELETE FROM `%s`
                 WHERE (`revoked_at` IS NOT NULL OR `expires_at` < :now)
                 LIMIT :limit',
                $this->database->table('web_development_preview_sessions')
            )
        );
        $statement->bindValue('now', $now, PDO::PARAM_STR);
        $statement->bindValue('limit', $batchSize, PDO::PARAM_INT);
        $statement->execute();

        return max(0, $statement->rowCount());
    }

    private function countTicketsToPurge(string $now): int
    {
        try {
            $this->database->ensureReady();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT COUNT(*)
                     FROM `%s`
                     WHERE (`revoked_at` IS NOT NULL
                            OR `consumed_at` IS NOT NULL
                            OR `expires_at` < :now)',
                    $this->database->table('web_development_preview_tickets')
                )
            );
            $statement->execute(['now' => $now]);

            $count = $statement->fetchColumn();
        } catch (Throwable) {
            return 0;
        }

        return is_numeric($count) ? max(0, (int) $count) : 0;
    }

    private function countSessionsToPurge(string $now): int
    {
        try {
            $this->database->ensureReady();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT COUNT(*)
                     FROM `%s`
                     WHERE (`revoked_at` IS NOT NULL OR `expires_at` < :now)',
                    $this->database->table('web_development_preview_sessions')
                )
            );
            $statement->execute(['now' => $now]);

            $count = $statement->fetchColumn();
        } catch (Throwable) {
            return 0;
        }

        return is_numeric($count) ? max(0, (int) $count) : 0;
    }
}
