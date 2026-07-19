<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class PrivateDocumentRepository
{
    private const DOCUMENT_ID_MAX_LENGTH = 64;
    public const SCAN_STATUS_PENDING_SCAN = PrivateDocumentScanResult::STATUS_PENDING_SCAN;
    public const SCAN_STATUS_CLEAN = PrivateDocumentScanResult::STATUS_CLEAN;
    public const SCAN_STATUS_INFECTED = PrivateDocumentScanResult::STATUS_INFECTED;
    public const SCAN_STATUS_UNAVAILABLE = PrivateDocumentScanResult::STATUS_SCAN_UNAVAILABLE;
    public const DEFAULT_CATEGORY_COLOR = '#ffffff';
    public const CATEGORY_COLORS = [
        '#ffffff',
        '#fff1d6',
        '#ffe0e0',
        '#e1f7d5',
        '#d6ecff',
        '#eadbff',
        '#ffdff3',
    ];

    private bool $privateSchemaReady = false;

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function table(): string
    {
        return $this->database->table('private_documents');
    }

    public function categoryTable(): string
    {
        return $this->database->table('private_document_categories');
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
                    'SELECT d.*, c.`name` AS `category_name`, c.`slug` AS `category_slug`, c.`color` AS `category_color`
                     FROM `%s` d
                     LEFT JOIN `%s` c ON c.`id` = d.`category_id` AND c.`is_active` = 1
                     WHERE d.`document_id` = :document_id
                       AND d.`is_active` = 1
                     LIMIT 1',
                    $this->table(),
                    $this->categoryTable()
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
                    'SELECT d.*, c.`name` AS `category_name`, c.`slug` AS `category_slug`, c.`color` AS `category_color`
                     FROM `%s` d
                     LEFT JOIN `%s` c ON c.`id` = d.`category_id` AND c.`is_active` = 1
                     WHERE d.`document_id` = :document_id
                       AND d.`private_user_id` = :user_id
                       AND d.`is_active` = 1
                     LIMIT 1',
                    $this->table(),
                    $this->categoryTable()
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
                    'SELECT d.*, c.`name` AS `category_name`, c.`slug` AS `category_slug`, c.`color` AS `category_color`
                     FROM `%s` d
                     LEFT JOIN `%s` c ON c.`id` = d.`category_id` AND c.`is_active` = 1
                     WHERE d.`private_user_id` = :user_id
                       AND d.`is_active` = 1
                     ORDER BY c.`name` ASC, d.`uploaded_at` DESC, d.`id` DESC
                     LIMIT :limit',
                    $this->table(),
                    $this->categoryTable()
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listCategoriesForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT c.*,
                            (
                                SELECT COUNT(*)
                                FROM `%s` d
                                WHERE d.`category_id` = c.`id`
                                  AND d.`private_user_id` = c.`private_user_id`
                                  AND d.`is_active` = 1
                            ) AS `documents_count`
                     FROM `%s`
                     c
                     WHERE c.`private_user_id` = :user_id
                       AND c.`is_active` = 1
                     ORDER BY c.`name` ASC, c.`id` ASC',
                    $this->table(),
                    $this->categoryTable()
                )
            );
            $statement->execute(['user_id' => $userId]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $categories = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $category = $this->hydrateCategoryRow($row);
            if (is_array($category)) {
                $categories[] = $category;
            }
        }

        return $categories;
    }

    public function findCategoryForUser(int $categoryId, int $userId): ?array
    {
        if ($categoryId <= 0 || $userId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT *
                     FROM `%s`
                     WHERE `id` = :id
                       AND `private_user_id` = :user_id
                       AND `is_active` = 1
                     LIMIT 1',
                    $this->categoryTable()
                )
            );
            $statement->execute(['id' => $categoryId, 'user_id' => $userId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->hydrateCategoryRow($row) : null;
    }

    public function createCategory(int $userId, string $name, string $color = ''): ?array
    {
        return $this->saveCategory($userId, 0, $name, $color);
    }

    public function saveCategory(int $userId, int $categoryId, string $name, string $color = ''): ?array
    {
        $name = sanitize_text_field($name, 80);
        $name = trim((string) preg_replace('/\s+/', ' ', $name));
        $slug = $this->slugifyCategoryName($name);
        $color = $this->normalizeCategoryColor($color);
        if ($userId <= 0 || $name === '' || $slug === '') {
            return null;
        }

        try {
            $this->ensureSchema();
            if ($categoryId > 0 && $this->findCategoryForUser($categoryId, $userId) !== null) {
                $statement = $this->database->pdo()->prepare(
                    sprintf(
                        'UPDATE `%s`
                         SET `name` = :name,
                             `slug` = :slug,
                             `color` = :color,
                             `is_active` = 1,
                             `updated_at` = :updated_at
                         WHERE `id` = :id
                           AND `private_user_id` = :user_id
                           AND `is_active` = 1',
                        $this->categoryTable()
                    )
                );
                $statement->execute([
                    'name' => $name,
                    'slug' => $slug,
                    'color' => $color,
                    'updated_at' => $this->currentDateTime(),
                    'id' => $categoryId,
                    'user_id' => $userId,
                ]);

                return $this->findCategoryForUser($categoryId, $userId);
            }

            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`private_user_id`, `name`, `slug`, `color`, `is_active`, `created_at`, `updated_at`)
                     VALUES
                        (:user_id, :name, :slug, :color, 1, :created_at, :updated_at)
                     ON DUPLICATE KEY UPDATE
                        `name` = VALUES(`name`),
                        `color` = VALUES(`color`),
                        `is_active` = 1,
                        `updated_at` = VALUES(`updated_at`)',
                    $this->categoryTable()
                )
            );
            $now = $this->currentDateTime();
            $statement->execute([
                'user_id' => $userId,
                'name' => $name,
                'slug' => $slug,
                'color' => $color,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $this->findCategoryBySlugForUser($slug, $userId);
        } catch (\Throwable) {
            return null;
        }
    }

    public function deactivateCategory(int $userId, int $categoryId): bool
    {
        if ($userId <= 0 || $categoryId <= 0 || $this->findCategoryForUser($categoryId, $userId) === null) {
            return false;
        }

        try {
            $this->ensureSchema();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();

            $detachDocuments = $pdo->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `category_id` = NULL
                     WHERE `private_user_id` = :user_id
                       AND `category_id` = :category_id',
                    $this->table()
                )
            );
            $detachDocuments->execute(['user_id' => $userId, 'category_id' => $categoryId]);

            $deactivate = $pdo->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `is_active` = 0,
                         `updated_at` = :updated_at
                     WHERE `private_user_id` = :user_id
                       AND `id` = :category_id
                       AND `is_active` = 1',
                    $this->categoryTable()
                )
            );
            $deactivate->execute([
                'updated_at' => $this->currentDateTime(),
                'user_id' => $userId,
                'category_id' => $categoryId,
            ]);

            $pdo->commit();

            return $deactivate->rowCount() > 0;
        } catch (\Throwable) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return false;
        }
    }

    public function create(
        int $userId,
        string $documentId,
        string $storagePath,
        string $originalName,
        string $extension,
        string $mimeType,
        int $sizeBytes,
        int $uploadedBy,
        ?int $categoryId = null,
        string $scanStatus = self::SCAN_STATUS_CLEAN,
        ?int $scanExitCode = null,
        ?int $scanDurationMs = null,
        string $scanError = '',
        ?string $scannedAt = null
    ): ?array {
        $documentId = $this->normalizeDocumentId($documentId);
        $storagePath = trim($storagePath);
        $originalName = trim($originalName);
        $extension = trim($extension);
        $mimeType = trim($mimeType);
        $categoryId = $categoryId !== null && $categoryId > 0 ? $categoryId : null;
        $scanStatus = $this->normalizeScanStatus($scanStatus);
        $scanExitCode = $scanExitCode !== null ? max(-1, min(255, $scanExitCode)) : null;
        $scanDurationMs = $scanDurationMs !== null ? max(0, $scanDurationMs) : null;
        $scanError = $this->normalizeScanError($scanError);
        $scannedAt = is_string($scannedAt) && trim($scannedAt) !== '' ? trim($scannedAt) : null;

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
        if ($categoryId !== null && $this->findCategoryForUser($categoryId, $userId) === null) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`private_user_id`, `category_id`, `document_id`, `storage_path`, `original_name`, `extension`, `mime_type`, `size_bytes`, `scan_status`, `scan_exit_code`, `scan_duration_ms`, `scan_error`, `scanned_at`, `uploaded_by_private_user_id`)
                     VALUES
                        (:user_id, :category_id, :document_id, :storage_path, :original_name, :extension, :mime_type, :size_bytes, :scan_status, :scan_exit_code, :scan_duration_ms, :scan_error, :scanned_at, :uploaded_by)',
                    $this->table()
                )
            );
            $statement->execute([
                'user_id' => $userId,
                'category_id' => $categoryId,
                'document_id' => $documentId,
                'storage_path' => $storagePath,
                'original_name' => $originalName,
                'extension' => $extension,
                'mime_type' => $mimeType,
                'size_bytes' => $sizeBytes,
                'scan_status' => $scanStatus,
                'scan_exit_code' => $scanExitCode,
                'scan_duration_ms' => $scanDurationMs,
                'scan_error' => $scanError,
                'scanned_at' => $scannedAt,
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
        $categoryUserConstraint = $this->constraintName('category_user');
        $userConstraint = $this->constraintName('user');
        $uploaderConstraint = $this->constraintName('uploader');
        $deleterConstraint = $this->constraintName('deleter');
        $categoryConstraint = $this->constraintName('category');
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `private_user_id` INT NOT NULL,
                    `name` VARCHAR(80) NOT NULL,
                    `slug` VARCHAR(96) NOT NULL,
                    `color` CHAR(7) NOT NULL DEFAULT \'\',
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `uq_private_document_categories_user_slug` (`private_user_id`, `slug`),
                    KEY `idx_private_document_categories_user` (`private_user_id`, `is_active`, `name`),
                    CONSTRAINT `%s`
                        FOREIGN KEY (`private_user_id`)
                        REFERENCES `%s` (`id`)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->categoryTable(),
                $categoryUserConstraint,
                $this->database->table('private_users')
            )
        );
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `private_user_id` INT NOT NULL,
                    `category_id` INT NULL,
                    `document_id` VARCHAR(64) NOT NULL,
                    `storage_path` VARCHAR(255) NOT NULL,
                    `original_name` VARCHAR(255) NOT NULL,
                    `extension` VARCHAR(32) NOT NULL,
                    `mime_type` VARCHAR(128) NOT NULL,
                    `size_bytes` BIGINT UNSIGNED NOT NULL,
                    `scan_status` VARCHAR(32) NOT NULL DEFAULT \'clean\',
                    `scan_exit_code` INT NULL,
                    `scan_duration_ms` INT UNSIGNED NULL,
                    `scan_error` VARCHAR(255) NOT NULL DEFAULT \'\',
                    `scanned_at` DATETIME NULL,
                    `uploaded_by_private_user_id` INT NOT NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `deleted_at` DATETIME NULL,
                    `deleted_by_private_user_id` INT NULL,
                    UNIQUE KEY `uq_private_documents_document_id` (`document_id`),
                    UNIQUE KEY `uq_private_documents_storage_path` (`storage_path`),
                    KEY `idx_private_documents_user_active` (`private_user_id`, `is_active`),
                    KEY `idx_private_documents_category` (`category_id`, `is_active`),
                    KEY `idx_private_documents_scan_status` (`scan_status`, `is_active`),
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
                    ,
                    CONSTRAINT `%s`
                        FOREIGN KEY (`category_id`)
                        REFERENCES `%s` (`id`)
                        ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->table(),
                $userConstraint,
                $this->database->table('private_users'),
                $uploaderConstraint,
                $this->database->table('private_users'),
                $deleterConstraint,
                $this->database->table('private_users'),
                $categoryConstraint,
                $this->categoryTable()
            )
        );
        $this->ensureColumn($pdo, $this->table(), 'category_id', '`category_id` INT NULL');
        $this->ensureColumn($pdo, $this->table(), 'scan_status', '`scan_status` VARCHAR(32) NOT NULL DEFAULT \'clean\'');
        $this->ensureColumn($pdo, $this->table(), 'scan_exit_code', '`scan_exit_code` INT NULL');
        $this->ensureColumn($pdo, $this->table(), 'scan_duration_ms', '`scan_duration_ms` INT UNSIGNED NULL');
        $this->ensureColumn($pdo, $this->table(), 'scan_error', '`scan_error` VARCHAR(255) NOT NULL DEFAULT \'\'');
        $this->ensureColumn($pdo, $this->table(), 'scanned_at', '`scanned_at` DATETIME NULL');
        $this->ensureIndex($pdo, $this->table(), 'idx_private_documents_category', '`category_id`, `is_active`');
        $this->ensureIndex($pdo, $this->table(), 'idx_private_documents_scan_status', '`scan_status`, `is_active`');

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
        $categoryId = is_scalar($row['category_id'] ?? null) ? (int) $row['category_id'] : 0;

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
            'scanStatus' => $this->normalizeScanStatus(is_string($row['scan_status'] ?? null) ? (string) $row['scan_status'] : ''),
            'scanExitCode' => is_scalar($row['scan_exit_code'] ?? null) ? (int) $row['scan_exit_code'] : null,
            'scanDurationMs' => is_scalar($row['scan_duration_ms'] ?? null) ? max(0, (int) $row['scan_duration_ms']) : null,
            'scanError' => is_string($row['scan_error'] ?? null) ? (string) $row['scan_error'] : '',
            'scannedAt' => is_string($row['scanned_at'] ?? null) ? (string) $row['scanned_at'] : '',
            'uploadedBy' => $uploadedBy,
            'isActive' => (int) ($row['is_active'] ?? 0) === 1,
            'uploadedAt' => is_string($row['uploaded_at'] ?? null) ? (string) $row['uploaded_at'] : '',
            'categoryId' => $categoryId > 0 ? $categoryId : null,
            'categoryName' => is_string($row['category_name'] ?? null) ? (string) $row['category_name'] : '',
            'categorySlug' => is_string($row['category_slug'] ?? null) ? (string) $row['category_slug'] : '',
            'categoryColor' => is_string($row['category_color'] ?? null) ? (string) $row['category_color'] : '',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function hydrateCategoryRow(array $row): ?array
    {
        $id = is_scalar($row['id'] ?? null) ? (int) $row['id'] : 0;
        $userId = is_scalar($row['private_user_id'] ?? null) ? (int) $row['private_user_id'] : 0;
        $name = is_string($row['name'] ?? null) ? (string) $row['name'] : '';
        $slug = is_string($row['slug'] ?? null) ? (string) $row['slug'] : '';
        if ($id <= 0 || $userId <= 0 || $name === '' || $slug === '') {
            return null;
        }

        return [
            'id' => $id,
            'privateUserId' => $userId,
            'name' => $name,
            'slug' => $slug,
            'color' => is_string($row['color'] ?? null) ? (string) $row['color'] : '',
            'documentsCount' => is_scalar($row['documents_count'] ?? null) ? (int) $row['documents_count'] : 0,
            'isActive' => (int) ($row['is_active'] ?? 0) === 1,
            'createdAt' => is_string($row['created_at'] ?? null) ? (string) $row['created_at'] : '',
            'updatedAt' => is_string($row['updated_at'] ?? null) ? (string) $row['updated_at'] : '',
        ];
    }

    private function findCategoryBySlugForUser(string $slug, int $userId): ?array
    {
        if ($slug === '' || $userId <= 0) {
            return null;
        }

        try {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT *
                     FROM `%s`
                     WHERE `private_user_id` = :user_id
                       AND `slug` = :slug
                       AND `is_active` = 1
                     LIMIT 1',
                    $this->categoryTable()
                )
            );
            $statement->execute(['user_id' => $userId, 'slug' => $slug]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->hydrateCategoryRow($row) : null;
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

    private function slugifyCategoryName(string $name): string
    {
        $slug = strtolower(trim($name));
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
        if (is_string($converted) && $converted !== '') {
            $slug = $converted;
        }

        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
        $slug = is_string($slug) ? trim(strtolower($slug), '-') : '';

        return $slug !== '' && strlen($slug) <= 96 ? $slug : substr($slug, 0, 96);
    }

    private function normalizeCategoryColor(string $color): string
    {
        $color = trim($color);

        return preg_match('/\A#[0-9A-Fa-f]{6}\z/', $color) === 1 ? strtolower($color) : self::DEFAULT_CATEGORY_COLOR;
    }

    public static function isDownloadableScanStatus(string $status): bool
    {
        return PrivateDocumentScanResult::isDownloadable($status);
    }

    private function normalizeScanStatus(string $status): string
    {
        return PrivateDocumentScanResult::normalizeStatus($status);
    }

    private function normalizeScanError(string $error): string
    {
        $normalized = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $error);
        $normalized = is_string($normalized) ? trim($normalized) : '';

        return substr($normalized, 0, 255);
    }

    private function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        try {
            $statement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND COLUMN_NAME = :column'
            );
            $statement->execute(['table' => $table, 'column' => $column]);
            if ((int) $statement->fetchColumn() > 0) {
                return;
            }

            $pdo->exec(sprintf('ALTER TABLE `%s` ADD COLUMN %s', $table, $definition));
        } catch (\Throwable) {
            return;
        }
    }

    private function ensureIndex(PDO $pdo, string $table, string $index, string $columns): void
    {
        try {
            $statement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND INDEX_NAME = :index'
            );
            $statement->execute(['table' => $table, 'index' => $index]);
            if ((int) $statement->fetchColumn() > 0) {
                return;
            }

            $pdo->exec(sprintf('ALTER TABLE `%s` ADD KEY `%s` (%s)', $table, $index, $columns));
        } catch (\Throwable) {
            return;
        }
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
