<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Repository;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivateApps\Documents\Contract\DocumentEntityRef;
use PDO;

/**
 * Accès SQL de la bibliothèque documentaire centrale : objets physiques
 * dédupliqués, documents logiques, rattachements, versions et jobs d'import.
 */
final class DocumentHubRepository
{
    public const OBJECT_STATUS_READY = 'ready';
    public const OBJECT_STATUS_QUARANTINED = 'quarantined';
    public const OBJECT_STATUS_MISSING = 'missing';

    public const DOC_STATUS_ACTIVE = 'active';
    public const DOC_STATUS_CLOSED = 'closed';
    public const DOC_STATUS_ARCHIVED = 'archived';
    public const DOC_STATUS_TRASHED = 'trashed';
    public const DOC_STATUS_PENDING_DELETION = 'pending_deletion';
    public const DOC_STATUS_DELETED = 'deleted';

    public const JOB_STATUS_QUARANTINED = 'quarantined';
    public const JOB_STATUS_VALIDATING = 'validating';
    public const JOB_STATUS_PROCESSING = 'processing';
    public const JOB_STATUS_READY = 'ready';
    public const JOB_STATUS_REJECTED = 'rejected';
    public const JOB_STATUS_FAILED = 'failed';

    private const VISIBLE_STATUSES = [
        self::DOC_STATUS_ACTIVE,
        self::DOC_STATUS_CLOSED,
        self::DOC_STATUS_ARCHIVED,
    ];

    private bool $schemaReady = false;

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function database(): EditorialDatabase
    {
        return $this->database;
    }

    public function objectsTable(): string
    {
        return $this->database->table('private_document_objects');
    }

    public function documentsTable(): string
    {
        return $this->database->table('private_document_library');
    }

    public function linksTable(): string
    {
        return $this->database->table('private_document_links');
    }

    public function versionsTable(): string
    {
        return $this->database->table('private_document_versions');
    }

    public function jobsTable(): string
    {
        return $this->database->table('private_document_import_jobs');
    }

    public function derivativesTable(): string
    {
        return $this->database->table('private_document_derivatives');
    }

    public function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        $this->database->ensureReady();
        $pdo = $this->database->pdo();
        $usersTable = $this->database->table('private_users');

        $pdo->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `sha256` CHAR(64) NOT NULL,
                `mime_type` VARCHAR(128) NOT NULL,
                `extension` VARCHAR(16) NOT NULL,
                `storage_key` VARCHAR(255) NOT NULL,
                `original_size` BIGINT UNSIGNED NOT NULL,
                `stored_size` BIGINT UNSIGNED NOT NULL,
                `status` VARCHAR(24) NOT NULL DEFAULT \'ready\',
                `scan_status` VARCHAR(32) NOT NULL DEFAULT \'clean\',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `integrity_checked_at` DATETIME NULL,
                UNIQUE KEY `uq_private_document_objects_sha256` (`sha256`),
                UNIQUE KEY `uq_private_document_objects_storage_key` (`storage_key`),
                KEY `idx_private_document_objects_status` (`status`, `created_at`),
                KEY `idx_private_document_objects_integrity` (`integrity_checked_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $this->objectsTable()
        ));

        $pdo->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `document_uid` VARCHAR(64) NOT NULL,
                `object_id` INT NOT NULL,
                `category_code` VARCHAR(96) NOT NULL DEFAULT \'inbox\',
                `original_filename` VARCHAR(255) NOT NULL,
                `title` VARCHAR(255) NOT NULL DEFAULT \'\',
                `description` VARCHAR(1000) NOT NULL DEFAULT \'\',
                `document_date` DATE NULL,
                `fiscal_year` SMALLINT UNSIGNED NULL,
                `status` VARCHAR(24) NOT NULL DEFAULT \'active\',
                `retention_until` DATE NULL,
                `legal_hold` TINYINT(1) NOT NULL DEFAULT 0,
                `created_by` INT NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `archived_at` DATETIME NULL,
                `trashed_at` DATETIME NULL,
                `deleted_at` DATETIME NULL,
                UNIQUE KEY `uq_private_document_library_uid` (`document_uid`),
                KEY `idx_private_document_library_object` (`object_id`),
                KEY `idx_private_document_library_category` (`category_code`, `status`),
                KEY `idx_private_document_library_status` (`status`, `created_at`),
                KEY `idx_private_document_library_fiscal_year` (`fiscal_year`, `status`),
                KEY `idx_private_document_library_date` (`document_date`),
                KEY `idx_private_document_library_created_by` (`created_by`, `status`),
                CONSTRAINT `%s`
                    FOREIGN KEY (`object_id`) REFERENCES `%s` (`id`),
                CONSTRAINT `%s`
                    FOREIGN KEY (`created_by`) REFERENCES `%s` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $this->documentsTable(),
            $this->constraintName('library_object'),
            $this->objectsTable(),
            $this->constraintName('library_created_by'),
            $usersTable
        ));

        $pdo->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `document_id` INT NOT NULL,
                `entity_type` VARCHAR(64) NOT NULL,
                `entity_id` VARCHAR(64) NOT NULL,
                `link_role` VARCHAR(32) NOT NULL DEFAULT \'attachment\',
                `created_by` INT NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_private_document_links_unique` (`document_id`, `entity_type`, `entity_id`, `link_role`),
                KEY `idx_private_document_links_entity` (`entity_type`, `entity_id`),
                KEY `idx_private_document_links_document` (`document_id`),
                CONSTRAINT `%s`
                    FOREIGN KEY (`document_id`) REFERENCES `%s` (`id`) ON DELETE CASCADE,
                CONSTRAINT `%s`
                    FOREIGN KEY (`created_by`) REFERENCES `%s` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $this->linksTable(),
            $this->constraintName('links_document'),
            $this->documentsTable(),
            $this->constraintName('links_created_by'),
            $usersTable
        ));

        $pdo->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `document_id` INT NOT NULL,
                `version_number` INT UNSIGNED NOT NULL,
                `object_id` INT NOT NULL,
                `reason` VARCHAR(255) NOT NULL DEFAULT \'\',
                `created_by` INT NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_private_document_versions_number` (`document_id`, `version_number`),
                KEY `idx_private_document_versions_object` (`object_id`),
                CONSTRAINT `%s`
                    FOREIGN KEY (`document_id`) REFERENCES `%s` (`id`) ON DELETE CASCADE,
                CONSTRAINT `%s`
                    FOREIGN KEY (`object_id`) REFERENCES `%s` (`id`),
                CONSTRAINT `%s`
                    FOREIGN KEY (`created_by`) REFERENCES `%s` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $this->versionsTable(),
            $this->constraintName('versions_document'),
            $this->documentsTable(),
            $this->constraintName('versions_object'),
            $this->objectsTable(),
            $this->constraintName('versions_created_by'),
            $usersTable
        ));

        $pdo->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `object_id` INT NOT NULL,
                `derivative_type` VARCHAR(32) NOT NULL,
                `storage_key` VARCHAR(255) NOT NULL,
                `mime_type` VARCHAR(128) NOT NULL,
                `size_bytes` BIGINT UNSIGNED NOT NULL,
                `generator_version` VARCHAR(32) NOT NULL DEFAULT \'1\',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `last_accessed_at` DATETIME NULL,
                UNIQUE KEY `uq_private_document_derivatives_type` (`object_id`, `derivative_type`),
                UNIQUE KEY `uq_private_document_derivatives_key` (`storage_key`),
                CONSTRAINT `%s`
                    FOREIGN KEY (`object_id`) REFERENCES `%s` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $this->derivativesTable(),
            $this->constraintName('derivatives_object'),
            $this->objectsTable()
        ));

        $pdo->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `document_id` INT NULL,
                `import_source` VARCHAR(64) NOT NULL DEFAULT \'\',
                `profile_code` VARCHAR(96) NOT NULL DEFAULT \'\',
                `context_type` VARCHAR(64) NOT NULL DEFAULT \'\',
                `context_id` VARCHAR(64) NOT NULL DEFAULT \'\',
                `original_filename` VARCHAR(255) NOT NULL DEFAULT \'\',
                `classification_source` VARCHAR(32) NOT NULL DEFAULT \'\',
                `classification_confidence` TINYINT UNSIGNED NULL,
                `status` VARCHAR(24) NOT NULL DEFAULT \'quarantined\',
                `error_code` VARCHAR(64) NULL,
                `error_message_sanitized` VARCHAR(255) NULL,
                `created_by` INT NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `started_at` DATETIME NULL,
                `finished_at` DATETIME NULL,
                KEY `idx_private_document_import_jobs_status` (`status`, `created_at`),
                KEY `idx_private_document_import_jobs_document` (`document_id`),
                KEY `idx_private_document_import_jobs_profile` (`profile_code`, `created_at`),
                CONSTRAINT `%s`
                    FOREIGN KEY (`document_id`) REFERENCES `%s` (`id`) ON DELETE SET NULL,
                CONSTRAINT `%s`
                    FOREIGN KEY (`created_by`) REFERENCES `%s` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $this->jobsTable(),
            $this->constraintName('jobs_document'),
            $this->documentsTable(),
            $this->constraintName('jobs_created_by'),
            $usersTable
        ));

        $this->schemaReady = true;
    }

    // ------------------------------------------------------------------
    // Objets physiques (déduplication SHA-256)
    // ------------------------------------------------------------------

    /**
     * Retrouve ou crée l'objet physique d'une empreinte. Gère la course entre
     * deux imports concurrents du même contenu via la contrainte unique.
     *
     * @return array{id: int, created: bool}|null
     */
    public function findOrCreateObject(
        string $sha256,
        string $mimeType,
        string $extension,
        string $storageKey,
        int $sizeBytes,
        string $scanStatus
    ): ?array {
        if (preg_match('/\A[a-f0-9]{64}\z/', $sha256) !== 1 || $sizeBytes <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $existing = $this->findObjectBySha256($sha256);
            if (is_array($existing)) {
                return ['id' => (int) $existing['id'], 'created' => false];
            }

            $statement = $this->database->pdo()->prepare(sprintf(
                'INSERT INTO `%s`
                    (`sha256`, `mime_type`, `extension`, `storage_key`, `original_size`, `stored_size`, `status`, `scan_status`)
                 VALUES (:sha256, :mime_type, :extension, :storage_key, :original_size, :stored_size, :status, :scan_status)',
                $this->objectsTable()
            ));
            $statement->execute([
                'sha256' => $sha256,
                'mime_type' => substr($mimeType, 0, 128),
                'extension' => substr($extension, 0, 16),
                'storage_key' => substr($storageKey, 0, 255),
                'original_size' => $sizeBytes,
                'stored_size' => $sizeBytes,
                'status' => self::OBJECT_STATUS_READY,
                'scan_status' => substr($scanStatus, 0, 32),
            ]);

            return ['id' => (int) $this->database->pdo()->lastInsertId(), 'created' => true];
        } catch (\PDOException $exception) {
            // 23000 : violation d'unicité -> un import concurrent a créé l'objet.
            if ($exception->getCode() === '23000') {
                $existing = $this->findObjectBySha256($sha256);
                if (is_array($existing)) {
                    return ['id' => (int) $existing['id'], 'created' => false];
                }
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function findObjectBySha256(string $sha256): ?array
    {
        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                'SELECT * FROM `%s` WHERE `sha256` = :sha256 LIMIT 1',
                $this->objectsTable()
            ));
            $statement->execute(['sha256' => $sha256]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function findObjectById(int $objectId): ?array
    {
        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                'SELECT * FROM `%s` WHERE `id` = :id LIMIT 1',
                $this->objectsTable()
            ));
            $statement->execute(['id' => $objectId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Nombre de références SQL vers un objet (documents + versions).
     * Un objet référencé ne doit jamais être purgé physiquement.
     */
    public function objectReferenceCount(int $objectId): int
    {
        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                'SELECT
                    (SELECT COUNT(*) FROM `%s` WHERE `object_id` = :object_id_a AND `status` <> :deleted)
                  + (SELECT COUNT(*) FROM `%s` WHERE `object_id` = :object_id_b) AS `total`',
                $this->documentsTable(),
                $this->versionsTable()
            ));
            $statement->execute([
                'object_id_a' => $objectId,
                'object_id_b' => $objectId,
                'deleted' => self::DOC_STATUS_DELETED,
            ]);

            return (int) $statement->fetchColumn();
        } catch (\Throwable) {
            return PHP_INT_MAX; // en cas de doute, considérer l'objet comme référencé
        }
    }

    // ------------------------------------------------------------------
    // Documents logiques
    // ------------------------------------------------------------------

    /**
     * @return array|null document créé (ligne complète)
     */
    public function createDocument(
        int $objectId,
        string $categoryCode,
        string $originalFilename,
        string $title,
        string $description,
        ?string $documentDate,
        ?int $fiscalYear,
        int $createdBy
    ): ?array {
        try {
            $this->ensureSchema();
            $documentUid = bin2hex(random_bytes(16));
            $statement = $this->database->pdo()->prepare(sprintf(
                'INSERT INTO `%s`
                    (`document_uid`, `object_id`, `category_code`, `original_filename`, `title`, `description`,
                     `document_date`, `fiscal_year`, `status`, `created_by`)
                 VALUES
                    (:document_uid, :object_id, :category_code, :original_filename, :title, :description,
                     :document_date, :fiscal_year, :status, :created_by)',
                $this->documentsTable()
            ));
            $statement->execute([
                'document_uid' => $documentUid,
                'object_id' => $objectId,
                'category_code' => substr($categoryCode, 0, 96),
                'original_filename' => substr($originalFilename, 0, 255),
                'title' => substr($title, 0, 255),
                'description' => substr($description, 0, 1000),
                'document_date' => $documentDate,
                'fiscal_year' => $fiscalYear,
                'status' => self::DOC_STATUS_ACTIVE,
                'created_by' => $createdBy,
            ]);

            $documentId = (int) $this->database->pdo()->lastInsertId();
            $this->insertVersion($documentId, 1, $objectId, 'import initial', $createdBy);

            return $this->findDocumentById($documentId);
        } catch (\Throwable) {
            return null;
        }
    }

    public function findDocumentByUid(string $documentUid): ?array
    {
        $documentUid = trim($documentUid);
        if (preg_match('/\A[a-f0-9]{32}\z/', $documentUid) !== 1) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                'SELECT d.*, o.`sha256`, o.`mime_type`, o.`extension`, o.`storage_key`, o.`stored_size`, o.`scan_status`
                 FROM `%s` d
                 INNER JOIN `%s` o ON o.`id` = d.`object_id`
                 WHERE d.`document_uid` = :document_uid
                 LIMIT 1',
                $this->documentsTable(),
                $this->objectsTable()
            ));
            $statement->execute(['document_uid' => $documentUid]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function findDocumentById(int $documentId): ?array
    {
        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                'SELECT d.*, o.`sha256`, o.`mime_type`, o.`extension`, o.`storage_key`, o.`stored_size`, o.`scan_status`
                 FROM `%s` d
                 INNER JOIN `%s` o ON o.`id` = d.`object_id`
                 WHERE d.`id` = :id
                 LIMIT 1',
                $this->documentsTable(),
                $this->objectsTable()
            ));
            $statement->execute(['id' => $documentId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Liste paginée avec filtres. Les liens sont chargés en une requête (pas de N+1).
     *
     * @param array{
     *     search?: string,
     *     category_code?: string,
     *     status?: string,
     *     fiscal_year?: int,
     *     entity_type?: string,
     *     entity_id?: string,
     *     extension?: string,
     *     inbox_only?: bool
     * } $filters
     * @return array<int, array<string, mixed>>
     */
    public function listDocuments(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        try {
            $this->ensureSchema();
            [$where, $params, $joins] = $this->buildFilters($filters);

            $sql = sprintf(
                'SELECT DISTINCT d.*, o.`sha256`, o.`mime_type`, o.`extension`, o.`storage_key`, o.`stored_size`, o.`scan_status`
                 FROM `%s` d
                 INNER JOIN `%s` o ON o.`id` = d.`object_id`
                 %s
                 WHERE %s
                 ORDER BY d.`created_at` DESC, d.`id` DESC
                 LIMIT %d OFFSET %d',
                $this->documentsTable(),
                $this->objectsTable(),
                $joins,
                $where,
                max(1, min(500, $limit)),
                max(0, $offset)
            );

            $statement = $this->database->pdo()->prepare($sql);
            $statement->execute($params);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            $rows = is_array($rows) ? $rows : [];

            return $this->attachLinks($rows);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function countDocuments(array $filters = []): int
    {
        try {
            $this->ensureSchema();
            [$where, $params, $joins] = $this->buildFilters($filters);
            $statement = $this->database->pdo()->prepare(sprintf(
                'SELECT COUNT(DISTINCT d.`id`) FROM `%s` d %s WHERE %s',
                $this->documentsTable(),
                $joins,
                $where
            ));
            $statement->execute($params);

            return (int) $statement->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function updateDocumentCategory(int $documentId, string $categoryCode): bool
    {
        return $this->updateDocumentFields($documentId, ['category_code' => substr($categoryCode, 0, 96)]);
    }

    public function updateDocumentMeta(int $documentId, string $title, string $description, ?string $documentDate, ?int $fiscalYear): bool
    {
        return $this->updateDocumentFields($documentId, [
            'title' => substr($title, 0, 255),
            'description' => substr($description, 0, 1000),
            'document_date' => $documentDate,
            'fiscal_year' => $fiscalYear,
        ]);
    }

    /**
     * Transition de statut contrôlée. Le gel juridique bloque toute transition
     * destructive (corbeille, suppression).
     */
    public function transitionStatus(int $documentId, string $targetStatus): bool
    {
        $document = $this->findDocumentById($documentId);
        if ($document === null) {
            return false;
        }

        $current = (string) ($document['status'] ?? '');
        $legalHold = (int) ($document['legal_hold'] ?? 0) === 1;

        $allowed = match ($targetStatus) {
            self::DOC_STATUS_CLOSED => $current === self::DOC_STATUS_ACTIVE,
            self::DOC_STATUS_ARCHIVED => in_array($current, [self::DOC_STATUS_ACTIVE, self::DOC_STATUS_CLOSED], true),
            self::DOC_STATUS_TRASHED => !$legalHold && in_array($current, self::VISIBLE_STATUSES, true),
            self::DOC_STATUS_ACTIVE => in_array($current, [self::DOC_STATUS_TRASHED, self::DOC_STATUS_ARCHIVED, self::DOC_STATUS_CLOSED], true),
            self::DOC_STATUS_PENDING_DELETION => !$legalHold && $current === self::DOC_STATUS_TRASHED,
            default => false,
        };

        if (!$allowed) {
            return false;
        }

        $fields = ['status' => $targetStatus];
        if ($targetStatus === self::DOC_STATUS_ARCHIVED) {
            $fields['archived_at'] = date('Y-m-d H:i:s');
        }
        if ($targetStatus === self::DOC_STATUS_TRASHED) {
            $fields['trashed_at'] = date('Y-m-d H:i:s');
        }
        if ($targetStatus === self::DOC_STATUS_ACTIVE) {
            $fields['trashed_at'] = null;
            $fields['archived_at'] = null;
        }

        return $this->updateDocumentFields($documentId, $fields);
    }

    public function setLegalHold(int $documentId, bool $legalHold): bool
    {
        return $this->updateDocumentFields($documentId, ['legal_hold' => $legalHold ? 1 : 0]);
    }

    // ------------------------------------------------------------------
    // Rattachements
    // ------------------------------------------------------------------

    public function addLink(int $documentId, DocumentEntityRef $ref, int $createdBy): bool
    {
        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                'INSERT INTO `%s` (`document_id`, `entity_type`, `entity_id`, `link_role`, `created_by`)
                 VALUES (:document_id, :entity_type, :entity_id, :link_role, :created_by)',
                $this->linksTable()
            ));
            $statement->execute([
                'document_id' => $documentId,
                'entity_type' => $ref->entityType,
                'entity_id' => $ref->entityId,
                'link_role' => $ref->linkRole,
                'created_by' => $createdBy,
            ]);

            return true;
        } catch (\PDOException $exception) {
            // Lien déjà présent : idempotent, pas une erreur.
            return $exception->getCode() === '23000';
        } catch (\Throwable) {
            return false;
        }
    }

    public function removeLink(int $documentId, DocumentEntityRef $ref): bool
    {
        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                'DELETE FROM `%s`
                 WHERE `document_id` = :document_id
                   AND `entity_type` = :entity_type
                   AND `entity_id` = :entity_id
                   AND `link_role` = :link_role',
                $this->linksTable()
            ));
            $statement->execute([
                'document_id' => $documentId,
                'entity_type' => $ref->entityType,
                'entity_id' => $ref->entityId,
                'link_role' => $ref->linkRole,
            ]);

            return $statement->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function linksForDocument(int $documentId): array
    {
        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                'SELECT * FROM `%s` WHERE `document_id` = :document_id ORDER BY `created_at` ASC',
                $this->linksTable()
            ));
            $statement->execute(['document_id' => $documentId]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Documents visibles rattachés à une entité (liste filtrée d'un onglet métier).
     *
     * @return array<int, array<string, mixed>>
     */
    public function documentsForEntity(string $entityType, string $entityId, int $limit = 100, int $offset = 0): array
    {
        return $this->listDocuments([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ], $limit, $offset);
    }

    // ------------------------------------------------------------------
    // Versions
    // ------------------------------------------------------------------

    /**
     * Crée une nouvelle version pointant vers un nouvel objet et fait du
     * nouvel objet l'objet courant du document. L'ancien objet reste intact.
     */
    public function createVersion(int $documentId, int $newObjectId, string $reason, int $createdBy): ?int
    {
        try {
            $this->ensureSchema();
            $pdo = $this->database->pdo();
            $statement = $pdo->prepare(sprintf(
                'SELECT COALESCE(MAX(`version_number`), 0) FROM `%s` WHERE `document_id` = :document_id',
                $this->versionsTable()
            ));
            $statement->execute(['document_id' => $documentId]);
            $nextVersion = ((int) $statement->fetchColumn()) + 1;

            if (!$this->insertVersion($documentId, $nextVersion, $newObjectId, $reason, $createdBy)) {
                return null;
            }

            if (!$this->updateDocumentFields($documentId, ['object_id' => $newObjectId])) {
                return null;
            }

            return $nextVersion;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function versionsForDocument(int $documentId): array
    {
        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                'SELECT v.*, o.`sha256`, o.`mime_type`, o.`extension`, o.`storage_key`, o.`stored_size`
                 FROM `%s` v
                 INNER JOIN `%s` o ON o.`id` = v.`object_id`
                 WHERE v.`document_id` = :document_id
                 ORDER BY v.`version_number` DESC',
                $this->versionsTable(),
                $this->objectsTable()
            ));
            $statement->execute(['document_id' => $documentId]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (\Throwable) {
            return [];
        }
    }

    // ------------------------------------------------------------------
    // Jobs d'import
    // ------------------------------------------------------------------

    public function createImportJob(
        int $createdBy,
        string $importSource,
        string $profileCode,
        string $contextType,
        string $contextId,
        string $originalFilename
    ): ?int {
        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                'INSERT INTO `%s`
                    (`import_source`, `profile_code`, `context_type`, `context_id`, `original_filename`, `status`, `created_by`, `started_at`)
                 VALUES (:import_source, :profile_code, :context_type, :context_id, :original_filename, :status, :created_by, NOW())',
                $this->jobsTable()
            ));
            $statement->execute([
                'import_source' => substr($importSource, 0, 64),
                'profile_code' => substr($profileCode, 0, 96),
                'context_type' => substr($contextType, 0, 64),
                'context_id' => substr($contextId, 0, 64),
                'original_filename' => substr($originalFilename, 0, 255),
                'status' => self::JOB_STATUS_VALIDATING,
                'created_by' => $createdBy,
            ]);

            return (int) $this->database->pdo()->lastInsertId();
        } catch (\Throwable) {
            return null;
        }
    }

    public function finishImportJob(
        int $jobId,
        string $status,
        ?int $documentId = null,
        ?string $errorCode = null,
        ?string $classificationSource = null,
        ?int $classificationConfidence = null
    ): bool {
        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                'UPDATE `%s`
                 SET `status` = :status,
                     `document_id` = :document_id,
                     `error_code` = :error_code,
                     `classification_source` = :classification_source,
                     `classification_confidence` = :classification_confidence,
                     `finished_at` = NOW()
                 WHERE `id` = :id',
                $this->jobsTable()
            ));
            $statement->execute([
                'status' => substr($status, 0, 24),
                'document_id' => $documentId,
                'error_code' => $errorCode !== null ? substr($errorCode, 0, 64) : null,
                'classification_source' => (string) $classificationSource,
                'classification_confidence' => $classificationConfidence,
                'id' => $jobId,
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ------------------------------------------------------------------
    // Intégrité et statistiques
    // ------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allObjects(int $limit = 10000, int $offset = 0): array
    {
        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                'SELECT * FROM `%s` ORDER BY `id` ASC LIMIT %d OFFSET %d',
                $this->objectsTable(),
                max(1, $limit),
                max(0, $offset)
            ));
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function markObjectIntegrityChecked(int $objectId, string $status): bool
    {
        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                'UPDATE `%s` SET `status` = :status, `integrity_checked_at` = NOW() WHERE `id` = :id',
                $this->objectsTable()
            ));
            $statement->execute(['status' => substr($status, 0, 24), 'id' => $objectId]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{documents: int, objects: int, inbox: int, dedup_saved_bytes: int}
     */
    public function stats(): array
    {
        try {
            $this->ensureSchema();
            $pdo = $this->database->pdo();
            $documents = (int) $pdo->query(sprintf(
                'SELECT COUNT(*) FROM `%s` WHERE `status` IN (\'active\', \'closed\', \'archived\')',
                $this->documentsTable()
            ))->fetchColumn();
            $objects = (int) $pdo->query(sprintf('SELECT COUNT(*) FROM `%s`', $this->objectsTable()))->fetchColumn();
            $inbox = (int) $pdo->query(sprintf(
                'SELECT COUNT(*) FROM `%s` WHERE `category_code` = \'inbox\' AND `status` IN (\'active\', \'closed\')',
                $this->documentsTable()
            ))->fetchColumn();
            $saved = (int) $pdo->query(sprintf(
                'SELECT COALESCE(SUM(o.`stored_size` * (refs.`n` - 1)), 0)
                 FROM `%s` o
                 INNER JOIN (
                     SELECT `object_id`, COUNT(*) AS `n` FROM `%s` GROUP BY `object_id` HAVING COUNT(*) > 1
                 ) refs ON refs.`object_id` = o.`id`',
                $this->objectsTable(),
                $this->documentsTable()
            ))->fetchColumn();

            return ['documents' => $documents, 'objects' => $objects, 'inbox' => $inbox, 'dedup_saved_bytes' => $saved];
        } catch (\Throwable) {
            return ['documents' => 0, 'objects' => 0, 'inbox' => 0, 'dedup_saved_bytes' => 0];
        }
    }

    // ------------------------------------------------------------------
    // Interne
    // ------------------------------------------------------------------

    private function insertVersion(int $documentId, int $versionNumber, int $objectId, string $reason, int $createdBy): bool
    {
        try {
            $statement = $this->database->pdo()->prepare(sprintf(
                'INSERT INTO `%s` (`document_id`, `version_number`, `object_id`, `reason`, `created_by`)
                 VALUES (:document_id, :version_number, :object_id, :reason, :created_by)',
                $this->versionsTable()
            ));
            $statement->execute([
                'document_id' => $documentId,
                'version_number' => $versionNumber,
                'object_id' => $objectId,
                'reason' => substr($reason, 0, 255),
                'created_by' => $createdBy,
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function updateDocumentFields(int $documentId, array $fields): bool
    {
        if ($fields === []) {
            return false;
        }

        $assignments = [];
        $params = ['id' => $documentId];
        foreach ($fields as $column => $value) {
            if (preg_match('/\A[a-z_]+\z/', (string) $column) !== 1) {
                return false;
            }

            $assignments[] = sprintf('`%s` = :%s', $column, $column);
            $params[$column] = $value;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                'UPDATE `%s` SET %s, `updated_at` = NOW() WHERE `id` = :id',
                $this->documentsTable(),
                implode(', ', $assignments)
            ));
            $statement->execute($params);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    private function buildFilters(array $filters): array
    {
        $conditions = [];
        $params = [];
        $joins = '';

        $status = is_string($filters['status'] ?? null) ? trim((string) $filters['status']) : '';
        if ($status !== '') {
            $conditions[] = 'd.`status` = :f_status';
            $params['f_status'] = $status;
        } else {
            $conditions[] = 'd.`status` IN (\'active\', \'closed\', \'archived\')';
        }

        if (($filters['inbox_only'] ?? false) === true) {
            $conditions[] = 'd.`category_code` = \'inbox\'';
        }

        $categoryCode = is_string($filters['category_code'] ?? null) ? trim((string) $filters['category_code']) : '';
        if ($categoryCode !== '') {
            $conditions[] = '(d.`category_code` = :f_category OR d.`category_code` LIKE :f_category_prefix)';
            $params['f_category'] = $categoryCode;
            $params['f_category_prefix'] = $categoryCode . '.%';
        }

        if (is_numeric($filters['fiscal_year'] ?? null) && (int) $filters['fiscal_year'] > 0) {
            $conditions[] = 'd.`fiscal_year` = :f_year';
            $params['f_year'] = (int) $filters['fiscal_year'];
        }

        $search = is_string($filters['search'] ?? null) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $conditions[] = '(d.`title` LIKE :f_search_a OR d.`original_filename` LIKE :f_search_b OR d.`description` LIKE :f_search_c)';
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $params['f_search_a'] = $like;
            $params['f_search_b'] = $like;
            $params['f_search_c'] = $like;
        }

        $entityType = is_string($filters['entity_type'] ?? null) ? trim((string) $filters['entity_type']) : '';
        $entityId = is_string($filters['entity_id'] ?? null) ? trim((string) $filters['entity_id']) : '';
        if ($entityType !== '') {
            $joins = sprintf('INNER JOIN `%s` l ON l.`document_id` = d.`id`', $this->linksTable());
            $conditions[] = 'l.`entity_type` = :f_entity_type';
            $params['f_entity_type'] = $entityType;
            if ($entityId !== '') {
                $conditions[] = 'l.`entity_id` = :f_entity_id';
                $params['f_entity_id'] = $entityId;
            }
        }

        $extension = is_string($filters['extension'] ?? null) ? strtolower(trim((string) $filters['extension'])) : '';
        if ($extension !== '') {
            $joins .= $joins === '' ? '' : ' ';
            // o est déjà joint dans listDocuments ; pour countDocuments on joint ici.
            $conditions[] = 'EXISTS (SELECT 1 FROM `' . $this->objectsTable() . '` oe WHERE oe.`id` = d.`object_id` AND oe.`extension` = :f_extension)';
            $params['f_extension'] = $extension;
        }

        return [implode(' AND ', $conditions), $params, $joins];
    }

    /**
     * Charge les liens de toutes les lignes en une seule requête.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function attachLinks(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        try {
            $statement = $this->database->pdo()->prepare(sprintf(
                'SELECT * FROM `%s` WHERE `document_id` IN (%s) ORDER BY `created_at` ASC',
                $this->linksTable(),
                $placeholders
            ));
            $statement->execute($ids);
            $links = $statement->fetchAll(PDO::FETCH_ASSOC);
            $links = is_array($links) ? $links : [];
        } catch (\Throwable) {
            $links = [];
        }

        $byDocument = [];
        foreach ($links as $link) {
            $byDocument[(int) $link['document_id']][] = $link;
        }

        foreach ($rows as $index => $row) {
            $rows[$index]['links'] = $byDocument[(int) $row['id']] ?? [];
        }

        return $rows;
    }

    private function constraintName(string $suffix): string
    {
        return substr('fk_' . $this->database->table('pdh') . '_' . $suffix, 0, 64);
    }
}
