<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Documents;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class PrivateDocumentRepository
{
    private const DOCUMENT_ID_MAX_LENGTH = 64;

    private bool $privateSchemaReady = false;

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function table(): string
    {
        return $this->database->table('private_documents');
    }

    public function findByDocumentId(string $documentId): ?array
    {
        $documentId = $this->normalizeDocumentId($documentId);
        if ($documentId === '') {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s`
                     WHERE `document_id` = :document_id
                       AND `is_active` = 1
                     LIMIT 1',
                    $this->table()
                )
            );
            $statement->execute(['document_id' => $documentId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($row)) {
            return null;
        }

        return $this->hydrateRow($row);
    }

    public function findByDocumentIdAndUser(string $documentId, int $userId): ?array
    {
        $documentId = $this->normalizeDocumentId($documentId);
        if ($documentId === '' || $userId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s`
                     WHERE `document_id` = :document_id
                       AND `private_user_id` = :user_id
                       AND `is_active` = 1
                     LIMIT 1',
                    $this->table()
                )
            );
            $statement->execute([
                'document_id' => $documentId,
                'user_id' => $userId,
            ]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($row)) {
            return null;
        }

        return $this->hydrateRow($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listActiveByUser(int $userId, int $limit = 30): array
    {
        if ($userId <= 0 || $limit <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s`
                     WHERE `private_user_id` = :user_id
                       AND `is_active` = 1
                     ORDER BY `uploaded_at` DESC, `id` DESC
                     LIMIT :limit',
                    $this->table()
                )
            );
            $statement->bindValue('user_id', $userId, PDO::PARAM_INT);
            $statement->bindValue('limit', min($limit, 200), PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($rows)) {
            return [];
        }

        $documents = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $document = $this->hydrateRow($row);
            if (is_array($document)) {
                $documents[] = $document;
            }
        }

        return $documents;
    }

    public function create(
        int $userId,
        string $documentId,
        string $storagePath,
        string $originalName,
        string $extension,
        string $mimeType,
        int $sizeBytes,
        int $uploadedBy
    ): ?array {
        $documentId = $this->normalizeDocumentId($documentId);
        $storagePath = trim($storagePath);
        $originalName = trim($originalName);
        $extension = trim($extension);
        $mimeType = trim($mimeType);

        if (
            $documentId === ''
            || $storagePath === ''
            || $originalName === ''
            || $extension === ''
            || $mimeType === ''
            || $sizeBytes < 0
            || $userId <= 0
            || $uploadedBy <= 0
        ) {
            return null;
        }

        if (strlen($storagePath) > 255 || strlen($originalName) > 255 || strlen($mimeType) > 128) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`private_user_id`, `document_id`, `storage_path`, `original_name`, `extension`, `mime_type`, `size_bytes`, `uploaded_by_private_user_id`)
                     VALUES
                        (:user_id, :document_id, :storage_path, :original_name, :extension, :mime_type, :size_bytes, :uploaded_by)',
                    $this->table()
                )
            );
            $statement->execute([
                'user_id' => $userId,
                'document_id' => $documentId,
                'storage_path' => $storagePath,
                'original_name' => $originalName,
                'extension' => $extension,
                'mime_type' => $mimeType,
                'size_bytes' => $sizeBytes,
                'uploaded_by' => $uploadedBy,
            ]);
        } catch (\Throwable) {
            return null;
        }

        return $this->findByDocumentId($documentId);
    }

    public function deactivateByDocumentId(string $documentId, int $actorId): bool
    {
        $documentId = $this->normalizeDocumentId($documentId);
        if ($documentId === '' || $actorId <= 0) {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `is_active` = 0,
                         `deleted_at` = :deleted_at,
                         `deleted_by_private_user_id` = :actor_id
                     WHERE `document_id` = :document_id
                       AND `is_active` = 1',
                    $this->table()
                )
            );
            $statement->execute([
                'deleted_at' => $this->currentDateTime(),
                'actor_id' => $actorId,
                'document_id' => $documentId,
            ]);
        } catch (\Throwable) {
            return false;
        }

        return $statement->rowCount() > 0;
    }

    public function ensureSchema(): void
    {
        if ($this->privateSchemaReady) {
            return;
        }

        $this->database->ensureReady();
        $pdo = $this->database->pdo();
        $userConstraint = $this->constraintName('user');
        $uploaderConstraint = $this->constraintName('uploader');
        $deleterConstraint = $this->constraintName('deleter');
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `private_user_id` INT NOT NULL,
                    `document_id` VARCHAR(64) NOT NULL,
                    `storage_path` VARCHAR(255) NOT NULL,
                    `original_name` VARCHAR(255) NOT NULL,
                    `extension` VARCHAR(32) NOT NULL,
                    `mime_type` VARCHAR(128) NOT NULL,
                    `size_bytes` BIGINT UNSIGNED NOT NULL,
                    `uploaded_by_private_user_id` INT NOT NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `deleted_at` DATETIME NULL,
                    `deleted_by_private_user_id` INT NULL,
                    UNIQUE KEY `uq_private_documents_document_id` (`document_id`),
                    UNIQUE KEY `uq_private_documents_storage_path` (`storage_path`),
                    KEY `idx_private_documents_user_active` (`private_user_id`, `is_active`),
                    KEY `idx_private_documents_active` (`is_active`),
                    KEY `idx_private_documents_uploaded` (`uploaded_at`),
                    CONSTRAINT `%s`
                        FOREIGN KEY (`private_user_id`)
                        REFERENCES `%s` (`id`)
                        ON DELETE CASCADE,
                    CONSTRAINT `%s`
                        FOREIGN KEY (`uploaded_by_private_user_id`)
                        REFERENCES `%s` (`id`)
                        ON DELETE CASCADE,
                    CONSTRAINT `%s`
                        FOREIGN KEY (`deleted_by_private_user_id`)
                        REFERENCES `%s` (`id`)
                        ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->table(),
                $userConstraint,
                $this->database->table('private_users'),
                $uploaderConstraint,
                $this->database->table('private_users'),
                $deleterConstraint,
                $this->database->table('private_users')
            )
        );

        $this->privateSchemaReady = true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function hydrateRow(array $row): ?array
    {
        $id = is_scalar($row['id'] ?? null) ? (int) $row['id'] : 0;
        $privateUserId = is_scalar($row['private_user_id'] ?? null) ? (int) $row['private_user_id'] : 0;
        $sizeBytes = is_scalar($row['size_bytes'] ?? null) ? (int) $row['size_bytes'] : 0;
        $uploadedBy = is_scalar($row['uploaded_by_private_user_id'] ?? null) ? (int) $row['uploaded_by_private_user_id'] : 0;

        $documentId = is_string($row['document_id'] ?? null) ? (string) $row['document_id'] : '';
        if ($id <= 0 || $privateUserId <= 0 || $documentId === '') {
            return null;
        }

        return [
            'id' => $id,
            'privateUserId' => $privateUserId,
            'documentId' => $documentId,
            'storagePath' => is_string($row['storage_path'] ?? null) ? (string) $row['storage_path'] : '',
            'originalName' => is_string($row['original_name'] ?? null) ? (string) $row['original_name'] : '',
            'extension' => is_string($row['extension'] ?? null) ? (string) $row['extension'] : '',
            'mimeType' => is_string($row['mime_type'] ?? null) ? (string) $row['mime_type'] : '',
            'sizeBytes' => max(0, $sizeBytes),
            'uploadedBy' => $uploadedBy,
            'isActive' => (int) ($row['is_active'] ?? 0) === 1,
            'uploadedAt' => is_string($row['uploaded_at'] ?? null) ? (string) $row['uploaded_at'] : '',
        ];
    }

    private function normalizeDocumentId(string $documentId): string
    {
        $normalized = trim($documentId);

        if (preg_match('/\A[A-Za-z0-9._-]{1,' . self::DOCUMENT_ID_MAX_LENGTH . '}\z/', $normalized) !== 1) {
            return '';
        }

        return $normalized;
    }

    private function currentDateTime(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function constraintName(string $purpose): string
    {
        $baseTable = (string) preg_replace('/[^a-z0-9_]/i', '_', $this->table());
        if ($baseTable === '') {
            $baseTable = 'private_documents';
        }

        $candidate = sprintf('fk_%s_%s', $baseTable, strtolower(trim($purpose)));
        if (strlen($candidate) <= 64) {
            return $candidate;
        }

        return substr($candidate, 0, 48) . '_' . substr(sha1($candidate), 0, 8);
    }
}
