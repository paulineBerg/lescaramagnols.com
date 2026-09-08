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

    private function status(string $status): string
    {
        $status = strtolower(trim($status));
        $allowed = ['draft', 'previewed', 'reserved', 'running', 'completed', 'partial', 'failed', 'rolled_back'];

        return in_array($status, $allowed, true) ? $status : 'draft';
    }
}
