<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Service;

use Caramagnols\Database\EditorialDatabase;

/**
 * Service de gestion des jobs d'export documentaire en arrière-plan.
 *
 * Permet la construction asynchrone des archives d'export pour les gros volumes,
 * avec suivi de l'état et notifications.
 */
final class DocumentExportJobService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    private const TABLE_NAME = 'document_export_jobs';
    private bool $schemaReady = false;

    public function __construct(
        private readonly EditorialDatabase $database,
        private readonly DocumentExportService $exportService
    ) {
    }

    public function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        $this->database->ensureReady();
        $pdo = $this->database->pdo();

        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `job_uid` VARCHAR(64) NOT NULL,
                `private_user_id` INT NOT NULL,
                `export_type` VARCHAR(64) NOT NULL DEFAULT \'full\',
                `filters_json` TEXT NOT NULL,
                `status` VARCHAR(24) NOT NULL DEFAULT \'pending\',
                `progress_percent` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `result_json` TEXT NULL,
                `error_code` VARCHAR(64) NULL,
                `error_message` VARCHAR(500) NULL,
                `file_path` VARCHAR(1000) NULL,
                `file_size` BIGINT UNSIGNED NULL,
                `file_sha256` VARCHAR(64) NULL,
                `started_at` DATETIME NULL,
                `completed_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_document_export_jobs_uid` (`job_uid`),
                KEY `idx_document_export_jobs_status` (`status`, `created_at`),
                KEY `idx_document_export_jobs_user` (`private_user_id`, `created_at`),
                KEY `idx_document_export_jobs_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->table()
            )
        );

        $this->schemaReady = true;
    }

    public function table(): string
    {
        return $this->database->table(self::TABLE_NAME);
    }

    /**
     * @param  array<string, mixed> $filters    Filtres à appliquer à
     *                                          l'export
     * @param  string               $exportType Type d'export : 'full', 'by_entity', 'by_category', 'by_year'
     * @return array<string, mixed>
     */
    public function createJob(int $privateUserId, array $filters, string $exportType = 'full', string $label = ''): array
    {
        $this->ensureSchema();

        $jobUid = $this->generateJobUid();
        $filtersJson = json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($filtersJson)) {
            return ['success' => false, 'error' => 'invalid_filters'];
        }

        try {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                 (`job_uid`, `private_user_id`, `export_type`, `filters_json`, `status`, `created_at`, `updated_at`)
                 VALUES (:job_uid, :user_id, :export_type, :filters_json, :status, NOW(), NOW())',
                    $this->table()
                )
            );

            $statement->execute(
                [
                'job_uid' => $jobUid,
                'user_id' => $privateUserId,
                'export_type' => $exportType,
                'filters_json' => $filtersJson,
                'status' => self::STATUS_PENDING,
                ]
            );

            return [
                'success' => true,
                'job_uid' => $jobUid,
                'status' => self::STATUS_PENDING,
                'created_at' => date('c'),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getJob(string $jobUid): ?array
    {
        $this->ensureSchema();

        try {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s` WHERE `job_uid` = :job_uid LIMIT 1',
                    $this->table()
                )
            );
            $statement->execute(['job_uid' => $jobUid]);
            $row = $statement->fetch(\PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                return null;
            }

            // Normaliser les champs
            $row['filters'] = $this->decodeJson($row['filters_json'] ?? '{}');
            $row['result'] = $this->decodeJson($row['result_json'] ?? '{}');

            return $row;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listJobs(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $this->ensureSchema();

        $conditions = [];
        $params = [];

        if (isset($filters['private_user_id']) && is_numeric($filters['private_user_id'])) {
            $conditions[] = '`private_user_id` = :user_id';
            $params['user_id'] = (int) $filters['private_user_id'];
        }

        if (isset($filters['status']) && is_string($filters['status'])) {
            $conditions[] = '`status` = :status';
            $params['status'] = $filters['status'];
        }

        if (isset($filters['export_type']) && is_string($filters['export_type'])) {
            $conditions[] = '`export_type` = :export_type';
            $params['export_type'] = $filters['export_type'];
        }

        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $order = 'ORDER BY `created_at` DESC';
        $limitClause = "LIMIT {$limit} OFFSET {$offset}";

        try {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s` %s %s %s',
                    $this->table(),
                    $where,
                    $order,
                    $limitClause
                )
            );
            $statement->execute($params);
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

            if (!is_array($rows)) {
                return [];
            }

            // Normaliser les champs
            foreach ($rows as &$row) {
                $row['filters'] = $this->decodeJson($row['filters_json'] ?? '{}');
                $row['result'] = $this->decodeJson($row['result_json'] ?? '{}');
            }

            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Met à jour l'état d'un job.
     *
     * @param array<string, mixed> $updates
     */
    public function updateJob(string $jobUid, array $updates): bool
    {
        $this->ensureSchema();

        if (empty($updates)) {
            return false;
        }

        $allowedColumns = [
            'status', 'progress_percent', 'result_json',
            'error_code', 'error_message', 'file_path',
            'file_size', 'file_sha256', 'started_at', 'completed_at'
        ];

        $assignments = [];
        $params = ['job_uid' => $jobUid];

        foreach ($updates as $column => $value) {
            if (!in_array($column, $allowedColumns, true)) {
                continue;
            }

            $assignments[] = "`{$column}` = :{$column}";
            $params[$column] = $value;
        }

        if ($assignments === []) {
            return false;
        }

        try {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s` SET %s, `updated_at` = NOW() WHERE `job_uid` = :job_uid',
                    $this->table(),
                    implode(', ', $assignments)
                )
            );

            return $statement->execute($params);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Exécute un job en arrière-plan.
     *
     * @param  string $jobUid UID du job à exécuter
     * @param  bool   $isCli  Si vrai, l'exécution est en CLI (peuvent avoir des
     *                        limites différentes)
     * @return array<string, mixed>
     */
    public function executeJob(string $jobUid, bool $isCli = false): array
    {
        $job = $this->getJob($jobUid);
        if ($job === null) {
            return ['success' => false, 'error' => 'job_not_found', 'job_uid' => $jobUid];
        }

        $currentStatus = (string) ($job['status'] ?? '');
        if ($currentStatus === self::STATUS_PROCESSING) {
            return ['success' => false, 'error' => 'job_already_processing', 'job_uid' => $jobUid];
        }

        if ($currentStatus === self::STATUS_COMPLETED) {
            return ['success' => true, 'already_completed' => true, 'job' => $job];
        }

        if ($currentStatus === self::STATUS_CANCELLED) {
            return ['success' => false, 'error' => 'job_cancelled', 'job_uid' => $jobUid];
        }

        // Mettre à jour l'état pour indiquer le début
        $this->updateJob(
            $jobUid, [
            'status' => self::STATUS_PROCESSING,
            'progress_percent' => 0,
            'started_at' => date('Y-m-d H:i:s'),
            'error_code' => null,
            'error_message' => null,
            ]
        );

        try {
            $privateUserId = (int) ($job['private_user_id'] ?? 0);
            $filters = is_array($job['filters'] ?? null) ? $job['filters'] : [];
            $exportType = (string) ($job['export_type'] ?? 'full');

            // Exécuter l'export
            $exportResult = $this->exportService->exportToZip($privateUserId, $filters, $exportType);

            if (!($exportResult['ok'] ?? false)) {
                $errorCode = (string) ($exportResult['error_code'] ?? 'export_failed');
                $this->updateJob(
                    $jobUid, [
                    'status' => self::STATUS_FAILED,
                    'error_code' => $errorCode,
                    'error_message' => $errorCode,
                    'progress_percent' => 100,
                    'completed_at' => date('Y-m-d H:i:s'),
                    ]
                );

                return [
                    'success' => false,
                    'error' => $errorCode,
                    'job_uid' => $jobUid,
                ];
            }

            // Vérifier si on doit chiffrer
            $encrypted = false;
            $finalPath = (string) ($exportResult['zip_path'] ?? '');

            if ($this->canEncryptZip() && $this->shouldEncryptExport($exportType)) {
                $encryptedPath = $this->encryptZip($finalPath);
                if ($encryptedPath !== null) {
                    $encrypted = true;
                    $finalPath = $encryptedPath;
                    // Supprimer l'archive non chiffrée
                    @unlink($exportResult['zip_path']);
                }
            }

            // Calculer le checksum
            $fileSha256 = hash_file('sha256', $finalPath) ?: '';
            $fileSize = is_file($finalPath) ? (int) filesize($finalPath) : 0;

            // Mettre à jour le job avec les résultats
            $resultData = [
                'original_filename' => basename($exportResult['zip_path'] ?? ''),
                'file_count' => $exportResult['file_count'] ?? 0,
                'encrypted' => $encrypted,
            ];

            $this->updateJob(
                $jobUid, [
                'status' => self::STATUS_COMPLETED,
                'progress_percent' => 100,
                'file_path' => $finalPath,
                'file_size' => $fileSize,
                'file_sha256' => $fileSha256,
                'result_json' => json_encode($resultData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'completed_at' => date('Y-m-d H:i:s'),
                ]
            );

            return [
                'success' => true,
                'job_uid' => $jobUid,
                'status' => self::STATUS_COMPLETED,
                'file_path' => $finalPath,
                'file_size' => $fileSize,
                'file_sha256' => $fileSha256,
                'encrypted' => $encrypted,
            ];
        } catch (\Throwable $e) {
            $this->updateJob(
                $jobUid, [
                'status' => self::STATUS_FAILED,
                'error_code' => 'exception',
                'error_message' => $e->getMessage(),
                'progress_percent' => 100,
                'completed_at' => date('Y-m-d H:i:s'),
                ]
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'job_uid' => $jobUid,
            ];
        }
    }

    /**
     * Annule un job.
     */
    public function cancelJob(string $jobUid): bool
    {
        $job = $this->getJob($jobUid);
        if ($job === null) {
            return false;
        }

        $currentStatus = (string) ($job['status'] ?? '');
        if ($currentStatus === self::STATUS_COMPLETED || $currentStatus === self::STATUS_CANCELLED) {
            return false;
        }

        return $this->updateJob(
            $jobUid, [
            'status' => self::STATUS_CANCELLED,
            'completed_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * Supprime un job et ses fichiers associés.
     */
    public function deleteJob(string $jobUid): bool
    {
        $job = $this->getJob($jobUid);
        if ($job === null) {
            return false;
        }

        // Supprimer le fichier d'export s'il existe
        $filePath = (string) ($job['file_path'] ?? '');
        if ($filePath !== '' && is_file($filePath)) {
            @unlink($filePath);
        }

        // Supprimer l'entrée de la base
        try {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'DELETE FROM `%s` WHERE `job_uid` = :job_uid',
                    $this->table()
                )
            );
            return $statement->execute(['job_uid' => $jobUid]);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Nettoie les jobs expirés.
     *
     * @param int $ttlSeconds Durée de vie maximale des jobs en secondes
     */
    public function cleanupExpiredJobs(int $ttlSeconds = 86400): int
    {
        $this->ensureSchema();

        $deleted = 0;
        $threshold = date('Y-m-d H:i:s', time() - $ttlSeconds);

        try {
            // Trouver les jobs à supprimer
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s` WHERE `created_at` < :threshold AND `status` IN (\'completed\', \'failed\', \'cancelled\')',
                    $this->table()
                )
            );
            $statement->execute(['threshold' => $threshold]);
            $jobs = $statement->fetchAll(\PDO::FETCH_ASSOC);

            if (!is_array($jobs)) {
                return 0;
            }

            foreach ($jobs as $job) {
                $this->deleteJob((string) ($job['job_uid'] ?? ''));
                $deleted++;
            }
        } catch (\Throwable) {
            // Ignorer les erreurs de nettoyage
        }

        return $deleted;
    }

    /**
     * Vérifie si le chiffrement ZIP est disponible.
     */
    public function canEncryptZip(): bool
    {
        return class_exists(\ZipArchive::class)
            && defined(\ZipArchive::class . '::EM_AES_256')
            && is_int(\ZipArchive::EM_AES_256);
    }

    /**
     * Chiffre une archive ZIP avec AES-256.
     *
     * @param  string $zipPath  Chemin vers le fichier ZIP à
     *                          chiffrer
     * @param  string $password Mot de passe de chiffrement
     * @return string|null Chemin vers le fichier chiffré, ou null en cas d'échec
     */
    public function encryptZip(string $zipPath, string $password = ''): ?string
    {
        if (!$this->canEncryptZip()) {
            return null;
        }

        if (!is_file($zipPath) || !is_readable($zipPath)) {
            return null;
        }

        // Si aucun mot de passe fourni, essayer d'en obtenir un depuis la configuration
        if ($password === '') {
            $password = $this->getEncryptionPassword();
            if ($password === '') {
                return null; // Pas de mot de passe configuré
            }
        }

        $encryptedPath = $zipPath . '.encrypted';

        try {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                return null;
            }

            // Créer une nouvelle archive chiffrée
            $encryptedZip = new \ZipArchive();
            if ($encryptedZip->open($encryptedPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                return null;
            }

            // Copier le contenu avec chiffrement
            $success = true;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    $success = false;
                    break;
                }

                $name = $stat['name'];
                $content = $zip->getFromName($name);

                if ($content === false) {
                    $success = false;
                    break;
                }

                // Ajouter le fichier avec chiffrement
                if (!$encryptedZip->addFromString($name, $content)) {
                    $success = false;
                    break;
                }
            }

            $zip->close();

            if (!$success) {
                $encryptedZip->close();
                @unlink($encryptedPath);
                return null;
            }

            // Appliquer le chiffrement AES-256
            if (!$encryptedZip->setEncryptionIndex(0, \ZipArchive::EM_AES_256, $password)) {
                $encryptedZip->close();
                @unlink($encryptedPath);
                return null;
            }

            $encryptedZip->close();

            // Vérifier que le fichier chiffré existe
            if (!is_file($encryptedPath) || filesize($encryptedPath) === 0) {
                @unlink($encryptedPath);
                return null;
            }

            return $encryptedPath;
        } catch (\Throwable) {
            if (is_file($encryptedPath)) {
                @unlink($encryptedPath);
            }
            return null;
        }
    }

    /**
     * Détermine si un type d'export doit être chiffré.
     */
    private function shouldEncryptExport(string $exportType): bool
    {
        // Les exports sensibles sont chiffrés par défaut
        $sensitiveTypes = ['full', 'financial', 'private', 'complete'];
        return in_array(strtolower($exportType), $sensitiveTypes, true);
    }

    /**
     * Obtient le mot de passe de chiffrement depuis la configuration.
     */
    private function getEncryptionPassword(): string
    {
        if (function_exists('app_config')) {
            $config = app_config('private.document_hub', []);
            return is_array($config) ? (string) ($config['encryption_password'] ?? '') : '';
        }
        return '';
    }

    /**
     * Génère un UID unique pour un job.
     */
    private function generateJobUid(): string
    {
        return 'doc-exp-' . date('Ymd') . '-' . bin2hex(random_bytes(8));
    }

    /**
     * Décode un JSON de manière sécurisée.
     *
     * @return array<string, mixed>
     */
    private function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
