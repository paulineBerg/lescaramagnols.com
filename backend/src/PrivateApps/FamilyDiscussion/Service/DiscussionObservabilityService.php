<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\FamilyDiscussion\Service;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class DiscussionObservabilityService
{
    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $now = date('Y-m-d H:i:s');
        $since = date('Y-m-d H:i:s', time() - 86400);

        return [
            'generatedAt' => date('c'),
            'volumes' => [
                'activeConversations' => $this->countRows('discussion_conversations', '`archived_at` IS NULL'),
                'messagesLast24h' => $this->countRows('discussion_messages', '`created_at` >= :since', ['since' => $since]),
                'attachmentsLast24h' => $this->countRows('discussion_message_attachments', '`created_at` >= :since', ['since' => $since]),
            ],
            'attachments' => $this->attachmentAvailabilityCounts(),
            'retention' => [
                'expiredMessagesPending' => $this->countRows(
                    'discussion_messages',
                    '`expires_at` <= :now AND `purge_status` <> \'purged\'',
                    ['now' => $now]
                ),
                'expiredAttachmentsPending' => $this->countRows(
                    'discussion_message_attachments',
                    '`expires_at` <= :now AND `purge_status` <> \'purged\'',
                    ['now' => $now]
                ),
                'latestRun' => $this->latestRetentionRun(),
            ],
            'logs' => [
                'streamErrorsLast24h' => $this->countLogEvents('private.discussion.stream_failed', $since),
                'scanFailuresLast24h' => $this->countLogEvents('private.discussion.media_scan.completed', $since, ['warning', 'error', 'critical']),
                'rateLimitedLast24h' => $this->countLogEvents('private.discussion.rate_limited', $since),
                'decryptFailuresLast24h' => $this->countLogEvents('private.discussion.client_decrypt_failed', $since),
            ],
            'logQueries' => [
                'stream' => 'private.discussion.stream_failed',
                'scan' => 'private.discussion.media_scan.completed',
                'rateLimit' => 'private.discussion.rate_limited',
                'decrypt' => 'private.discussion.client_decrypt_failed',
            ],
        ];
    }

    /**
     * @param array<string, scalar|null> $params
     */
    private function countRows(string $table, string $where, array $params = []): int
    {
        try {
            $this->database->ensureReady();
            $statement = $this->database->pdo()->prepare(sprintf(
                'SELECT COUNT(*) FROM `%s` WHERE %s',
                $this->database->table($table),
                $where
            ));
            $statement->execute($params);
            $count = $statement->fetchColumn();

            return is_numeric($count) ? (int) $count : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return array<string, int>
     */
    private function attachmentAvailabilityCounts(): array
    {
        $counts = [
            'pending_scan' => 0,
            'available' => 0,
            'blocked' => 0,
            'deleted' => 0,
            'purged' => 0,
        ];

        try {
            $this->database->ensureReady();
            $statement = $this->database->pdo()->query(sprintf(
                'SELECT `availability_status`, COUNT(*) AS `count`
                 FROM `%s`
                 GROUP BY `availability_status`',
                $this->database->table('discussion_message_attachments')
            ));
            $rows = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (\Throwable) {
            return $counts;
        }

        foreach (is_array($rows) ? $rows : [] as $row) {
            $status = is_string($row['availability_status'] ?? null) ? (string) $row['availability_status'] : '';
            if (!array_key_exists($status, $counts)) {
                continue;
            }

            $counts[$status] = is_numeric($row['count'] ?? null) ? (int) $row['count'] : 0;
        }

        return $counts;
    }

    /**
     * @return array{status:string,startedAt:string,finishedAt:string,messages:int,attachments:int}
     */
    private function latestRetentionRun(): array
    {
        try {
            $this->database->ensureReady();
            $statement = $this->database->pdo()->query(sprintf(
                'SELECT `status`, `started_at`, `finished_at`, `purged_messages_count`, `purged_attachments_count`
                 FROM `%s`
                 ORDER BY `started_at` DESC, `id` DESC
                 LIMIT 1',
                $this->database->table('discussion_retention_runs')
            ));
            $row = $statement !== false ? $statement->fetch(PDO::FETCH_ASSOC) : false;
        } catch (\Throwable) {
            $row = false;
        }

        if (!is_array($row)) {
            return ['status' => '', 'startedAt' => '', 'finishedAt' => '', 'messages' => 0, 'attachments' => 0];
        }

        return [
            'status' => is_string($row['status'] ?? null) ? (string) $row['status'] : '',
            'startedAt' => is_string($row['started_at'] ?? null) ? (string) $row['started_at'] : '',
            'finishedAt' => is_string($row['finished_at'] ?? null) ? (string) $row['finished_at'] : '',
            'messages' => max(0, (int) ($row['purged_messages_count'] ?? 0)),
            'attachments' => max(0, (int) ($row['purged_attachments_count'] ?? 0)),
        ];
    }

    /**
     * @param array<int, string> $levels
     */
    private function countLogEvents(string $event, string $since, array $levels = []): int
    {
        try {
            $this->database->ensureReady();
            $where = '`event` = :event AND `created_at` >= :since';
            $params = ['event' => $event, 'since' => $since];
            if ($levels !== []) {
                $placeholders = [];
                foreach (array_values($levels) as $index => $level) {
                    $key = 'level_' . $index;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $level;
                }
                $where .= ' AND `level` IN (' . implode(', ', $placeholders) . ')';
            }

            $statement = $this->database->pdo()->prepare(sprintf(
                'SELECT COUNT(*) FROM `%s` WHERE %s',
                $this->database->table('log_entries'),
                $where
            ));
            $statement->execute($params);
            $count = $statement->fetchColumn();

            return is_numeric($count) ? (int) $count : 0;
        } catch (\Throwable) {
            return 0;
        }
    }
}
