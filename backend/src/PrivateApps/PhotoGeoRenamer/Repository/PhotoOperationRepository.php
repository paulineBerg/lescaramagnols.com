<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Repository;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class PhotoOperationRepository
{
    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    /**
     * @param array<string, mixed> $operation
     */
    public function add(int $batchId, array $operation): void
    {
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`batch_id`, `file_uid`, `original_name`, `new_name`, `relative_path`, `commune_key`,
                     `commune_name`, `assigned_number`, `taken_at`, `taken_at_source`, `latitude`, `longitude`,
                     `status`, `error_code`, `error_message`, `created_at`)
                 VALUES
                    (:batch_id, :file_uid, :original_name, :new_name, :relative_path, :commune_key,
                     :commune_name, :assigned_number, :taken_at, :taken_at_source, :latitude, :longitude,
                     :status, :error_code, :error_message, :created_at)',
                $this->database->table('photo_geo_operations')
            )
        );
        $statement->execute([
            'batch_id' => $batchId,
            'file_uid' => $this->nullableString($operation['file_uid'] ?? null, 96),
            'original_name' => $this->string($operation['original_name'] ?? $operation['old_name'] ?? '', 240),
            'new_name' => $this->nullableString($operation['new_name'] ?? null, 240),
            'relative_path' => $this->string($operation['relative_path'] ?? $operation['old_name'] ?? '', 512),
            'commune_key' => $this->nullableString($operation['commune_key'] ?? null, 160),
            'commune_name' => $this->nullableString($operation['commune_name'] ?? null, 160),
            'assigned_number' => is_numeric($operation['assigned_number'] ?? null) ? (int) $operation['assigned_number'] : null,
            'taken_at' => $this->nullableString($operation['taken_at'] ?? null, 32),
            'taken_at_source' => $this->nullableString($operation['taken_at_source'] ?? null, 64),
            'latitude' => is_numeric($operation['latitude'] ?? null) ? (float) $operation['latitude'] : null,
            'longitude' => is_numeric($operation['longitude'] ?? null) ? (float) $operation['longitude'] : null,
            'status' => $this->string($operation['status'] ?? 'draft', 32),
            'error_code' => $this->nullableString($operation['error_code'] ?? null, 80),
            'error_message' => $this->nullableString($operation['error_message'] ?? null, 240),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $operations
     */
    public function replacePreviewOperations(int $batchId, array $operations): void
    {
        $delete = $this->database->pdo()->prepare(
            sprintf(
                'DELETE FROM `%s`
                 WHERE `batch_id` = :batch_id AND `status` IN (\'draft\', \'previewed\', \'conflict\')',
                $this->database->table('photo_geo_operations')
            )
        );
        $delete->execute(['batch_id' => $batchId]);

        foreach ($operations as $operation) {
            $operation['status'] = (string) ($operation['status'] ?? 'previewed');
            $this->add($batchId, $operation);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forBatch(int $batchId): array
    {
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'SELECT * FROM `%s` WHERE `batch_id` = :batch_id ORDER BY `id` ASC',
                $this->database->table('photo_geo_operations')
            )
        );
        $statement->execute(['batch_id' => $batchId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_filter(is_array($rows) ? $rows : [], 'is_array'));
    }

    public function markReserved(int $operationId, string $newName, int $assignedNumber): void
    {
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `new_name` = :new_name,
                     `assigned_number` = :assigned_number,
                     `status` = \'reserved\'
                 WHERE `id` = :id',
                $this->database->table('photo_geo_operations')
            )
        );
        $statement->execute([
            'id' => $operationId,
            'new_name' => $this->string($newName, 240),
            'assigned_number' => max(1, $assignedNumber),
        ]);
    }

    /**
     * @param array<string, mixed> $result
     */
    public function markResult(int $batchId, array $result): void
    {
        $relativePath = $this->string($result['relative_path'] ?? '', 512);
        if ($relativePath === '') {
            return;
        }

        $status = strtolower($this->string($result['status'] ?? '', 32));
        if (!in_array($status, ['completed', 'unchanged', 'failed'], true)) {
            $status = 'failed';
        }

        $statement = $this->database->pdo()->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `status` = :status,
                     `error_code` = :error_code,
                     `error_message` = :error_message,
                     `completed_at` = :completed_at
                 WHERE `batch_id` = :batch_id AND `relative_path` = :relative_path',
                $this->database->table('photo_geo_operations')
            )
        );
        $statement->execute([
            'batch_id' => $batchId,
            'relative_path' => $relativePath,
            'status' => $status,
            'error_code' => $status === 'failed' ? $this->nullableString($result['error_code'] ?? null, 80) : null,
            'error_message' => $status === 'failed' ? $this->nullableString($result['error_message'] ?? null, 240) : null,
            'completed_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function string(mixed $value, int $max): string
    {
        return mb_substr(trim(is_string($value) || is_numeric($value) ? (string) $value : ''), 0, $max);
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $value = $this->string($value, $max);

        return $value !== '' ? $value : null;
    }
}
