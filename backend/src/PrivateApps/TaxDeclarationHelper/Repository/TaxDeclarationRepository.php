<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\TaxDeclarationHelper\Repository;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class TaxDeclarationRepository
{
    private const YEAR_MIN = 2000;
    private const YEAR_MAX = 2100;
    private const MANUAL_STATUSES = ['draft', 'validated', 'cancelled'];
    private const ACTIVABLE_SOURCES = ['real_estate_rental' => 'Locations immobilieres'];
    private bool $schemaReady = false;

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function yearsTable(): string
    {
        return $this->database->table('tax_years');
    }

    public function sourcesTable(): string
    {
        return $this->database->table('tax_income_sources');
    }

    public function manualEntriesTable(): string
    {
        return $this->database->table('tax_manual_income_entries');
    }

    public function sourceActivationsTable(): string
    {
        return $this->database->table('tax_source_activations');
    }

    public function summariesTable(): string
    {
        return $this->database->table('tax_annual_summaries');
    }

    public function summaryLinesTable(): string
    {
        return $this->database->table('tax_summary_lines');
    }

    public function exportLogsTable(): string
    {
        return $this->database->table('tax_export_logs');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findOrCreateYear(int $privateUserId, int $year): ?array
    {
        if ($privateUserId <= 0 || !$this->validYear($year)) {
            return null;
        }

        $existing = $this->findYear($privateUserId, $year);
        if (is_array($existing)) {
            return $existing;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s` (`private_user_id`, `year`, `status`) VALUES (:private_user_id, :year, :status)',
                    $this->yearsTable()
                )
            );
            $statement->execute([
                'private_user_id' => $privateUserId,
                'year' => $year,
                'status' => 'draft',
            ]);
        } catch (\Throwable) {
            return null;
        }

        return $this->findYear($privateUserId, $year);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findYear(int $privateUserId, int $year): ?array
    {
        if ($privateUserId <= 0 || !$this->validYear($year)) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s` WHERE `private_user_id` = :private_user_id AND `year` = :year LIMIT 1',
                    $this->yearsTable()
                )
            );
            $statement->execute(['private_user_id' => $privateUserId, 'year' => $year]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listYearsForUser(int $privateUserId, int $limit = 10): array
    {
        if ($privateUserId <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s` WHERE `private_user_id` = :private_user_id ORDER BY `year` DESC LIMIT :limit',
                    $this->yearsTable()
                )
            );
            $statement->bindValue(':private_user_id', $privateUserId, PDO::PARAM_INT);
            $statement->bindValue(':limit', max(1, min($limit, 50)), PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return $this->normalizeRows($rows);
    }

    public function isYearLocked(int $privateUserId, int $year): bool
    {
        $row = $this->findYear($privateUserId, $year);
        return is_array($row) && ($row['status'] ?? '') === 'locked';
    }

    public function lockYear(int $privateUserId, int $year, int $actorPrivateUserId): bool
    {
        if ($privateUserId <= 0 || $actorPrivateUserId <= 0 || !$this->validYear($year)) {
            return false;
        }

        $this->findOrCreateYear($privateUserId, $year);

        try {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `status` = :status,
                         `locked_at` = :locked_at,
                         `locked_by_private_user_id` = :actor,
                         `updated_at` = :updated_at
                     WHERE `private_user_id` = :private_user_id AND `year` = :year',
                    $this->yearsTable()
                )
            );
            $statement->execute([
                'status' => 'locked',
                'locked_at' => date('Y-m-d H:i:s'),
                'actor' => $actorPrivateUserId,
                'updated_at' => date('Y-m-d H:i:s'),
                'private_user_id' => $privateUserId,
                'year' => $year,
            ]);

            return $statement->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function unlockYear(int $privateUserId, int $year, int $actorPrivateUserId): bool
    {
        if ($privateUserId <= 0 || $actorPrivateUserId <= 0 || !$this->validYear($year)) {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `status` = :status,
                         `unlocked_at` = :unlocked_at,
                         `unlocked_by_private_user_id` = :actor,
                         `updated_at` = :updated_at
                     WHERE `private_user_id` = :private_user_id AND `year` = :year AND `status` = :locked_status',
                    $this->yearsTable()
                )
            );
            $statement->execute([
                'status' => 'draft',
                'unlocked_at' => date('Y-m-d H:i:s'),
                'actor' => $actorPrivateUserId,
                'updated_at' => date('Y-m-d H:i:s'),
                'private_user_id' => $privateUserId,
                'year' => $year,
                'locked_status' => 'locked',
            ]);

            return $statement->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function createManualEntry(
        int $privateUserId,
        int $year,
        string $label,
        float $amount,
        string $category,
        string $status,
        int $actorPrivateUserId,
        ?string $notes = null
    ): ?array {
        $label = $this->normalizeText($label, 160);
        $category = $this->normalizeText($category, 64);
        $status = strtolower(trim($status));
        $notes = $notes !== null ? $this->normalizeText($notes, 2000) : null;
        $amount = round($amount, 2);

        if (
            $privateUserId <= 0
            || $actorPrivateUserId <= 0
            || !$this->validYear($year)
            || $label === ''
            || $category === ''
            || $amount < 0
            || !in_array($status, self::MANUAL_STATUSES, true)
            || $this->isYearLocked($privateUserId, $year)
        ) {
            return null;
        }

        try {
            $this->ensureSchema();
            $this->ensureSource('manual', 'Revenus manuels');
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`private_user_id`, `year`, `source_code`, `label`, `amount`, `category`, `status`, `notes`, `created_by_private_user_id`)
                     VALUES
                        (:private_user_id, :year, :source_code, :label, :amount, :category, :status, :notes, :created_by)',
                    $this->manualEntriesTable()
                )
            );
            $statement->execute([
                'private_user_id' => $privateUserId,
                'year' => $year,
                'source_code' => 'manual',
                'label' => $label,
                'amount' => $amount,
                'category' => $category,
                'status' => $status,
                'notes' => $notes !== '' ? $notes : null,
                'created_by' => $actorPrivateUserId,
            ]);

            return $this->findManualEntryById((int) $this->database->pdo()->lastInsertId());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findManualEntryById(int $entryId): ?array
    {
        if ($entryId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf('SELECT * FROM `%s` WHERE `id` = :id LIMIT 1', $this->manualEntriesTable())
            );
            $statement->execute(['id' => $entryId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listManualEntries(int $privateUserId, int $year): array
    {
        if ($privateUserId <= 0 || !$this->validYear($year)) {
            return [];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s`
                     WHERE `private_user_id` = :private_user_id AND `year` = :year
                     ORDER BY `created_at` DESC, `id` DESC',
                    $this->manualEntriesTable()
                )
            );
            $statement->execute(['private_user_id' => $privateUserId, 'year' => $year]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return $this->normalizeRows($rows);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function listSourceActivations(int $privateUserId, int $year): array
    {
        if ($privateUserId <= 0 || !$this->validYear($year)) {
            return [];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s`
                     WHERE `private_user_id` = :private_user_id AND `year` = :year
                     ORDER BY `source_code` ASC',
                    $this->sourceActivationsTable()
                )
            );
            $statement->execute(['private_user_id' => $privateUserId, 'year' => $year]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $activations = [];
        foreach ($this->normalizeRows($rows) as $row) {
            $sourceCode = is_string($row['sourceCode'] ?? null) ? $row['sourceCode'] : '';
            if ($sourceCode !== '') {
                $activations[$sourceCode] = $row;
            }
        }

        return $activations;
    }

    public function isSourceActive(int $privateUserId, int $year, string $sourceCode): bool
    {
        $sourceCode = $this->normalizeText($sourceCode, 80);
        if ($privateUserId <= 0 || !$this->validYear($year) || !$this->validActivableSource($sourceCode)) {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT `is_enabled` FROM `%s`
                     WHERE `private_user_id` = :private_user_id
                       AND `year` = :year
                       AND `source_code` = :source_code
                     LIMIT 1',
                    $this->sourceActivationsTable()
                )
            );
            $statement->execute([
                'private_user_id' => $privateUserId,
                'year' => $year,
                'source_code' => $sourceCode,
            ]);
            $value = $statement->fetchColumn();
        } catch (\Throwable) {
            return false;
        }

        return is_numeric($value) && (int) $value === 1;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function setSourceActivation(
        int $privateUserId,
        int $year,
        string $sourceCode,
        bool $enabled,
        int $actorPrivateUserId
    ): ?array {
        $sourceCode = $this->normalizeText($sourceCode, 80);
        if (
            $privateUserId <= 0
            || $actorPrivateUserId <= 0
            || !$this->validYear($year)
            || !$this->validActivableSource($sourceCode)
            || $this->isYearLocked($privateUserId, $year)
        ) {
            return null;
        }

        try {
            $this->ensureSchema();
            $this->ensureSource($sourceCode, self::ACTIVABLE_SOURCES[$sourceCode]);
            $this->findOrCreateYear($privateUserId, $year);
            $now = date('Y-m-d H:i:s');
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`private_user_id`, `year`, `source_code`, `is_enabled`, `enabled_at`,
                         `enabled_by_private_user_id`, `disabled_at`, `disabled_by_private_user_id`, `updated_at`)
                     VALUES
                        (:private_user_id, :year, :source_code, :is_enabled, :enabled_at,
                         :enabled_by, :disabled_at, :disabled_by, :updated_at)
                     ON DUPLICATE KEY UPDATE
                        `is_enabled` = VALUES(`is_enabled`),
                        `enabled_at` = VALUES(`enabled_at`),
                        `enabled_by_private_user_id` = VALUES(`enabled_by_private_user_id`),
                        `disabled_at` = VALUES(`disabled_at`),
                        `disabled_by_private_user_id` = VALUES(`disabled_by_private_user_id`),
                        `updated_at` = VALUES(`updated_at`)',
                    $this->sourceActivationsTable()
                )
            );
            $statement->execute([
                'private_user_id' => $privateUserId,
                'year' => $year,
                'source_code' => $sourceCode,
                'is_enabled' => $enabled ? 1 : 0,
                'enabled_at' => $enabled ? $now : null,
                'enabled_by' => $enabled ? $actorPrivateUserId : null,
                'disabled_at' => $enabled ? null : $now,
                'disabled_by' => $enabled ? null : $actorPrivateUserId,
                'updated_at' => $now,
            ]);
        } catch (\Throwable) {
            return null;
        }

        return $this->listSourceActivations($privateUserId, $year)[$sourceCode] ?? null;
    }

    /**
     * @param array<string, mixed> $totals
     * @param array<int, array<string, mixed>> $lines
     * @return array<string, mixed>|null
     */
    public function saveSummary(int $privateUserId, int $year, array $totals, array $lines, int $actorPrivateUserId): ?array
    {
        if ($privateUserId <= 0 || $actorPrivateUserId <= 0 || !$this->validYear($year) || $this->isYearLocked($privateUserId, $year)) {
            return null;
        }

        try {
            $this->ensureSchema();
            $this->ensureSource('manual', 'Revenus manuels');
            $this->ensureSource('real_estate_rental', 'Locations immobilieres');
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();
            $payload = json_encode($totals, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($payload)) {
                $payload = '{}';
            }

            $statement = $pdo->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`private_user_id`, `year`, `status`, `totals_payload`, `generated_by_private_user_id`, `generated_at`)
                     VALUES
                        (:private_user_id, :year, :status, :totals_payload, :generated_by, :generated_at)
                     ON DUPLICATE KEY UPDATE
                        `status` = VALUES(`status`),
                        `totals_payload` = VALUES(`totals_payload`),
                        `generated_by_private_user_id` = VALUES(`generated_by_private_user_id`),
                        `generated_at` = VALUES(`generated_at`),
                        `updated_at` = VALUES(`generated_at`)',
                    $this->summariesTable()
                )
            );
            $statement->execute([
                'private_user_id' => $privateUserId,
                'year' => $year,
                'status' => 'generated',
                'totals_payload' => $payload,
                'generated_by' => $actorPrivateUserId,
                'generated_at' => date('Y-m-d H:i:s'),
            ]);
            $summaryId = $this->summaryId($privateUserId, $year);
            if ($summaryId <= 0) {
                $pdo->rollBack();
                return null;
            }

            $delete = $pdo->prepare(sprintf('DELETE FROM `%s` WHERE `tax_annual_summary_id` = :summary_id', $this->summaryLinesTable()));
            $delete->execute(['summary_id' => $summaryId]);

            foreach ($lines as $line) {
                $this->insertSummaryLine($summaryId, $line);
            }

            $pdo->commit();

            return $this->findSummary($privateUserId, $year);
        } catch (\Throwable) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSummary(int $privateUserId, int $year): ?array
    {
        if ($privateUserId <= 0 || !$this->validYear($year)) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s` WHERE `private_user_id` = :private_user_id AND `year` = :year LIMIT 1',
                    $this->summariesTable()
                )
            );
            $statement->execute(['private_user_id' => $privateUserId, 'year' => $year]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSummaryLines(int $summaryId): array
    {
        if ($summaryId <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s` WHERE `tax_annual_summary_id` = :summary_id ORDER BY `id` ASC',
                    $this->summaryLinesTable()
                )
            );
            $statement->execute(['summary_id' => $summaryId]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return $this->normalizeRows($rows);
    }

    /**
     * @param array<string, mixed> $summary
     */
    public function createExportLog(int $privateUserId, int $year, string $format, array $summary, int $actorPrivateUserId): bool
    {
        $format = strtolower(trim($format));
        if ($privateUserId <= 0 || $actorPrivateUserId <= 0 || !$this->validYear($year) || !in_array($format, ['csv', 'pdf'], true)) {
            return false;
        }

        try {
            $this->ensureSchema();
            $payload = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($payload)) {
                $payload = '{}';
            }
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s` (`private_user_id`, `year`, `format`, `summary_payload`, `exported_by_private_user_id`)
                     VALUES (:private_user_id, :year, :format, :summary_payload, :exported_by)',
                    $this->exportLogsTable()
                )
            );

            return $statement->execute([
                'private_user_id' => $privateUserId,
                'year' => $year,
                'format' => $format,
                'summary_payload' => $payload,
                'exported_by' => $actorPrivateUserId,
            ]);
        } catch (\Throwable) {
            return false;
        }
    }

    public function countExportLogs(int $privateUserId, int $year, string $format): int
    {
        $format = strtolower(trim($format));
        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT COUNT(*) FROM `%s` WHERE `private_user_id` = :private_user_id AND `year` = :year AND `format` = :format',
                    $this->exportLogsTable()
                )
            );
            $statement->execute(['private_user_id' => $privateUserId, 'year' => $year, 'format' => $format]);

            return (int) $statement->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        $this->database->ensureReady();
        $pdo = $this->database->pdo();
        $pdo->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `private_user_id` INT NOT NULL,
                `year` SMALLINT NOT NULL,
                `status` ENUM("draft", "locked") NOT NULL DEFAULT "draft",
                `locked_at` DATETIME NULL,
                `locked_by_private_user_id` INT NULL,
                `unlocked_at` DATETIME NULL,
                `unlocked_by_private_user_id` INT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_tax_years_user_year` (`private_user_id`, `year`),
                KEY `idx_tax_years_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $this->yearsTable()
        ));
        $pdo->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `code` VARCHAR(80) NOT NULL,
                `label` VARCHAR(160) NOT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_tax_income_sources_code` (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $this->sourcesTable()
        ));
        $pdo->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `private_user_id` INT NOT NULL,
                `year` SMALLINT NOT NULL,
                `source_code` VARCHAR(80) NOT NULL,
                `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `enabled_at` DATETIME NULL,
                `enabled_by_private_user_id` INT NULL,
                `disabled_at` DATETIME NULL,
                `disabled_by_private_user_id` INT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_tax_source_activations_user_year_source` (`private_user_id`, `year`, `source_code`),
                KEY `idx_tax_source_activations_source` (`source_code`, `is_enabled`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $this->sourceActivationsTable()
        ));
        $pdo->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `private_user_id` INT NOT NULL,
                `year` SMALLINT NOT NULL,
                `source_code` VARCHAR(80) NOT NULL DEFAULT "manual",
                `label` VARCHAR(160) NOT NULL,
                `amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
                `category` VARCHAR(64) NOT NULL,
                `status` ENUM("draft", "validated", "cancelled") NOT NULL DEFAULT "draft",
                `notes` TEXT NULL,
                `created_by_private_user_id` INT NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `idx_tax_manual_entries_user_year` (`private_user_id`, `year`, `status`),
                KEY `idx_tax_manual_entries_source` (`source_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $this->manualEntriesTable()
        ));
        $pdo->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `private_user_id` INT NOT NULL,
                `year` SMALLINT NOT NULL,
                `status` ENUM("draft", "generated", "locked") NOT NULL DEFAULT "generated",
                `totals_payload` JSON NULL,
                `generated_by_private_user_id` INT NOT NULL,
                `generated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_tax_annual_summaries_user_year` (`private_user_id`, `year`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $this->summariesTable()
        ));
        $pdo->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `tax_annual_summary_id` INT NOT NULL,
                `source_code` VARCHAR(80) NOT NULL,
                `source_label` VARCHAR(160) NOT NULL,
                `line_type` ENUM("income", "expense", "control", "document") NOT NULL,
                `label` VARCHAR(190) NOT NULL,
                `amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
                `source_reference` VARCHAR(190) NOT NULL,
                `metadata_payload` JSON NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_tax_summary_lines_summary` (`tax_annual_summary_id`),
                KEY `idx_tax_summary_lines_source` (`source_code`, `line_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $this->summaryLinesTable()
        ));
        $pdo->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `private_user_id` INT NOT NULL,
                `year` SMALLINT NOT NULL,
                `format` ENUM("csv", "pdf") NOT NULL,
                `summary_payload` JSON NULL,
                `exported_by_private_user_id` INT NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_tax_export_logs_user_year` (`private_user_id`, `year`, `format`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $this->exportLogsTable()
        ));

        $this->ensureSource('manual', 'Revenus manuels');
        $this->ensureSource('real_estate_rental', 'Locations immobilieres');
        $this->schemaReady = true;
    }

    private function ensureSource(string $code, string $label): void
    {
        $code = $this->normalizeText($code, 80);
        $label = $this->normalizeText($label, 160);
        if ($code === '' || $label === '') {
            return;
        }

        $statement = $this->database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s` (`code`, `label`, `is_active`)
                 VALUES (:code, :label, 1)
                 ON DUPLICATE KEY UPDATE `label` = VALUES(`label`), `is_active` = 1',
                $this->sourcesTable()
            )
        );
        $statement->execute(['code' => $code, 'label' => $label]);
    }

    private function validActivableSource(string $sourceCode): bool
    {
        return array_key_exists($sourceCode, self::ACTIVABLE_SOURCES);
    }

    /**
     * @param array<string, mixed> $line
     */
    private function insertSummaryLine(int $summaryId, array $line): void
    {
        $metadata = is_array($line['metadata'] ?? null) ? $line['metadata'] : [];
        $payload = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            $payload = '{}';
        }

        $statement = $this->database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`tax_annual_summary_id`, `source_code`, `source_label`, `line_type`, `label`, `amount`, `source_reference`, `metadata_payload`)
                 VALUES
                    (:summary_id, :source_code, :source_label, :line_type, :label, :amount, :source_reference, :metadata_payload)',
                $this->summaryLinesTable()
            )
        );
        $statement->execute([
            'summary_id' => $summaryId,
            'source_code' => $this->normalizeText((string) ($line['sourceCode'] ?? 'unknown'), 80),
            'source_label' => $this->normalizeText((string) ($line['sourceLabel'] ?? 'Source'), 160),
            'line_type' => $this->lineType((string) ($line['lineType'] ?? 'income')),
            'label' => $this->normalizeText((string) ($line['label'] ?? 'Ligne'), 190),
            'amount' => is_numeric($line['amount'] ?? null) ? round((float) $line['amount'], 2) : 0.0,
            'source_reference' => $this->normalizeText((string) ($line['sourceReference'] ?? 'unknown'), 190),
            'metadata_payload' => $payload,
        ]);
    }

    private function summaryId(int $privateUserId, int $year): int
    {
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'SELECT `id` FROM `%s` WHERE `private_user_id` = :private_user_id AND `year` = :year LIMIT 1',
                $this->summariesTable()
            )
        );
        $statement->execute(['private_user_id' => $privateUserId, 'year' => $year]);
        $value = $statement->fetchColumn();

        return is_numeric($value) ? (int) $value : 0;
    }

    private function validYear(int $year): bool
    {
        return $year >= self::YEAR_MIN && $year <= self::YEAR_MAX;
    }

    private function normalizeText(string $value, int $maxLength): string
    {
        return trim(sanitize_text_field($value, $maxLength));
    }

    private function lineType(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['income', 'expense', 'control', 'document'], true) ? $value : 'income';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            if (is_string($key)) {
                $normalized[$this->camelKey($key)] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $normalized[] = $this->normalizeRow($row);
            }
        }

        return $normalized;
    }

    private function camelKey(string $key): string
    {
        return preg_replace_callback(
            '/_([a-z0-9])/',
            static fn (array $matches): string => strtoupper((string) $matches[1]),
            strtolower(trim($key))
        ) ?? $key;
    }
}
