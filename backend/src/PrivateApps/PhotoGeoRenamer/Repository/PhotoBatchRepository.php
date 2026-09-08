<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Repository;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class PhotoBatchRepository
{
    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function create(int $privateUserId, ?int $agentId, int $totalFiles, string $status = 'draft'): array
    {
        $batchUid = bin2hex(random_bytes(16));
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`batch_uid`, `private_user_id`, `agent_id`, `total_files`, `status`, `created_at`, `updated_at`)
                 VALUES
                    (:batch_uid, :private_user_id, :agent_id, :total_files, :status, :created_at, :updated_at)',
                $this->database->table('photo_geo_batches')
            )
        );
        $statement->execute([
            'batch_uid' => $batchUid,
            'private_user_id' => $privateUserId > 0 ? $privateUserId : null,
            'agent_id' => $agentId,
            'total_files' => max(0, $totalFiles),
            'status' => $this->status($status),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findByUid($batchUid) ?? ['batch_uid' => $batchUid];
    }

    /**
     * @param array<int, array<string, mixed>> $template
     * @return array<string, mixed>
     */
    public function upsertPreview(
        int $privateUserId,
        int $agentId,
        string $agentUid,
        string $batchUid,
        string $previewUid,
        string $rootUid,
        string $relativeDir,
        array $template,
        string $separator,
        int $counterDigits,
        string $sortOrder,
        int $totalFiles
    ): array {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`batch_uid`, `preview_uid`, `private_user_id`, `agent_id`, `agent_uid`, `root_uid`, `relative_dir`,
                     `template_json`, `separator`, `counter_digits`, `sort_order`, `total_files`, `status`, `created_at`, `updated_at`)
                 VALUES
                    (:batch_uid, :preview_uid, :private_user_id, :agent_id, :agent_uid, :root_uid, :relative_dir,
                     :template_json, :separator, :counter_digits, :sort_order, :total_files, \'previewed\', :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                     `private_user_id` = VALUES(`private_user_id`),
                     `agent_id` = VALUES(`agent_id`),
                     `agent_uid` = VALUES(`agent_uid`),
                     `root_uid` = VALUES(`root_uid`),
                     `relative_dir` = VALUES(`relative_dir`),
                     `template_json` = VALUES(`template_json`),
                     `separator` = VALUES(`separator`),
                     `counter_digits` = VALUES(`counter_digits`),
                     `sort_order` = VALUES(`sort_order`),
                     `total_files` = VALUES(`total_files`),
                     `status` = CASE
                         WHEN `status` IN (\'draft\', \'previewed\', \'conflict\') THEN \'previewed\'
                         ELSE `status`
                     END,
                     `updated_at` = VALUES(`updated_at`)',
                $this->database->table('photo_geo_batches')
            )
        );
        $statement->execute([
            'batch_uid' => $batchUid,
            'preview_uid' => $previewUid,
            'private_user_id' => $privateUserId > 0 ? $privateUserId : null,
            'agent_id' => $agentId > 0 ? $agentId : null,
            'agent_uid' => $agentUid !== '' ? $agentUid : null,
            'root_uid' => $rootUid,
            'relative_dir' => $relativeDir,
            'template_json' => json_encode(array_values($template), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'separator' => mb_substr($separator, 0, 4),
            'counter_digits' => max(2, min(6, $counterDigits)),
            'sort_order' => $this->sortOrder($sortOrder),
            'total_files' => max(0, $totalFiles),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findByUid($batchUid) ?? ['batch_uid' => $batchUid];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUid(string $batchUid): ?array
    {
        if (preg_match('/\A[a-f0-9]{32}\z/', $batchUid) !== 1) {
            return null;
        }

        $statement = $this->database->pdo()->prepare(
            sprintf('SELECT * FROM `%s` WHERE `batch_uid` = :batch_uid LIMIT 1', $this->database->table('photo_geo_batches'))
        );
        $statement->execute(['batch_uid' => $batchUid]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $statement = $this->database->pdo()->query(
            sprintf(
                'SELECT * FROM `%s` ORDER BY `created_at` DESC, `id` DESC LIMIT %d',
                $this->database->table('photo_geo_batches'),
                $limit
            )
        );
        $rows = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];

        return array_values(array_filter(is_array($rows) ? $rows : [], 'is_array'));
    }

    public function markReserved(int $batchId, int $totalFiles): void
    {
        $this->updateStatus($batchId, 'reserved', [
            'total_files' => max(0, $totalFiles),
            'started_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function updateStatus(int $batchId, string $status, array $fields = []): void
    {
        $sets = ['`status` = :status', '`updated_at` = :updated_at'];
        $params = [
            'batch_id' => $batchId,
            'status' => $this->status($status),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        foreach (['total_files', 'success_files', 'failed_files'] as $field) {
            if (isset($fields[$field]) && is_numeric($fields[$field])) {
                $sets[] = sprintf('`%s` = :%s', $field, $field);
                $params[$field] = max(0, (int) $fields[$field]);
            }
        }
        foreach (['started_at', 'completed_at'] as $field) {
            if (array_key_exists($field, $fields)) {
                $sets[] = sprintf('`%s` = :%s', $field, $field);
                $params[$field] = is_string($fields[$field] ?? null) ? (string) $fields[$field] : null;
            }
        }

        $statement = $this->database->pdo()->prepare(
            sprintf(
                'UPDATE `%s` SET %s WHERE `id` = :batch_id',
                $this->database->table('photo_geo_batches'),
                implode(', ', $sets)
            )
        );
        $statement->execute($params);
    }

    private function status(string $status): string
    {
        $status = strtolower(trim($status));
        $allowed = ['draft', 'previewed', 'reserved', 'running', 'completed', 'partial', 'failed', 'rolled_back'];

        return in_array($status, $allowed, true) ? $status : 'draft';
    }

    private function sortOrder(string $sortOrder): string
    {
        $sortOrder = strtolower(trim($sortOrder));
        $allowed = ['chronological', 'name', 'city', 'manual'];

        return in_array($sortOrder, $allowed, true) ? $sortOrder : 'chronological';
    }
}
