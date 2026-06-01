<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Repository;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyDocumentType;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyFiscalReviewPolicy;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyImportBatch;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyImportedDocument;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyImportIssue;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyStatement;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyStatementLine;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyStatementLineDraft;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import\AgencyImportPreview;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Parser\AgencyParserResult;
use PDO;

final class AgencyImportRepository
{
    private const BATCH_STATUSES = ['draft', 'review', 'validated', 'cancelled'];
    private bool $schemaReady = false;

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function batchesTable(): string
    {
        return $this->database->table('rental_agency_import_batches');
    }

    public function documentsTable(): string
    {
        return $this->database->table('rental_agency_imported_documents');
    }

    public function statementsTable(): string
    {
        return $this->database->table('rental_agency_statements');
    }

    public function linesTable(): string
    {
        return $this->database->table('rental_agency_statement_lines');
    }

    public function issuesTable(): string
    {
        return $this->database->table('rental_agency_import_issues');
    }

    public function unitMappingsTable(): string
    {
        return $this->database->table('rental_agency_unit_mappings');
    }

    public function agenciesTable(): string
    {
        return $this->database->table('rental_agencies');
    }

    public function createBatch(
        int $createdByPrivateUserId,
        ?string $agencyName = null,
        ?string $sourceDirectory = null,
        int $fileCount = 0,
        int $ignoredFileCount = 0,
        int $duplicateFileCount = 0,
        string $status = 'draft',
        ?string $notes = null
    ): ?AgencyImportBatch {
        $status = $this->normalizeStatus($status, self::BATCH_STATUSES, 'draft');
        if ($createdByPrivateUserId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`created_by_private_user_id`, `agency_name`, `status`, `source_directory`,
                         `file_count`, `ignored_file_count`, `duplicate_file_count`, `notes`)
                     VALUES
                        (:created_by, :agency_name, :status, :source_directory,
                         :file_count, :ignored_file_count, :duplicate_file_count, :notes)',
                    $this->batchesTable()
                )
            );
            $statement->execute([
                'created_by' => $createdByPrivateUserId,
                'agency_name' => $this->nullableText($agencyName, 120),
                'status' => $status,
                'source_directory' => $this->nullableText($sourceDirectory, 255),
                'file_count' => max(0, $fileCount),
                'ignored_file_count' => max(0, $ignoredFileCount),
                'duplicate_file_count' => max(0, $duplicateFileCount),
                'notes' => $this->nullableText($notes, 2000),
            ]);

            return $this->findBatchById((int) $this->database->pdo()->lastInsertId());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $details
     */
    public function createAgency(int $createdByPrivateUserId, string $agencyName, array $details = []): bool
    {
        $agencyName = $this->requiredText($agencyName, 120);
        $agencyDetails = $this->normalizeAgencyDetails($details);
        if ($createdByPrivateUserId <= 0 || $agencyName === '') {
            return false;
        }
        if ($agencyDetails === null) {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`created_by_private_user_id`, `name`, `legal_name`, `contact_title`, `postal_address`,
                         `phone`, `email`, `advisor_name`, `advisor_title`, `advisor_phone`, `advisor_email`,
                         `notes`, `updated_at`)
                     VALUES
                        (:created_by, :name, :legal_name, :contact_title, :postal_address,
                         :phone, :email, :advisor_name, :advisor_title, :advisor_phone, :advisor_email,
                         :notes, :updated_at)
                     ON DUPLICATE KEY UPDATE
                        `legal_name` = COALESCE(VALUES(`legal_name`), `legal_name`),
                        `contact_title` = COALESCE(VALUES(`contact_title`), `contact_title`),
                        `postal_address` = COALESCE(VALUES(`postal_address`), `postal_address`),
                        `phone` = COALESCE(VALUES(`phone`), `phone`),
                        `email` = COALESCE(VALUES(`email`), `email`),
                        `advisor_name` = COALESCE(VALUES(`advisor_name`), `advisor_name`),
                        `advisor_title` = COALESCE(VALUES(`advisor_title`), `advisor_title`),
                        `advisor_phone` = COALESCE(VALUES(`advisor_phone`), `advisor_phone`),
                        `advisor_email` = COALESCE(VALUES(`advisor_email`), `advisor_email`),
                        `notes` = COALESCE(VALUES(`notes`), `notes`),
                        `updated_at` = VALUES(`updated_at`)',
                    $this->agenciesTable()
                )
            );

            return $statement->execute($this->agencyStatementPayload($createdByPrivateUserId, $agencyName, $agencyDetails));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $details
     */
    public function updateAgencyForUser(int $createdByPrivateUserId, int $agencyId, string $agencyName, array $details): bool
    {
        $agencyName = $this->requiredText($agencyName, 120);
        $agencyDetails = $this->normalizeAgencyDetails($details);
        if ($createdByPrivateUserId <= 0 || $agencyId <= 0 || $agencyName === '' || $agencyDetails === null) {
            return false;
        }

        try {
            $this->ensureSchema();
            $previousName = $this->agencyNameForUser($createdByPrivateUserId, $agencyId);
            if ($previousName === null) {
                return false;
            }

            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `name` = :name,
                         `legal_name` = :legal_name,
                         `contact_title` = :contact_title,
                         `postal_address` = :postal_address,
                         `phone` = :phone,
                         `email` = :email,
                         `advisor_name` = :advisor_name,
                         `advisor_title` = :advisor_title,
                         `advisor_phone` = :advisor_phone,
                         `advisor_email` = :advisor_email,
                         `notes` = :notes,
                         `updated_at` = :updated_at
                     WHERE `id` = :id
                       AND `created_by_private_user_id` = :created_by',
                    $this->agenciesTable()
                )
            );
            $payload = $this->agencyStatementPayload($createdByPrivateUserId, $agencyName, $agencyDetails);
            $payload['id'] = $agencyId;
            $statement->execute($payload);

            if (strcasecmp($previousName, $agencyName) !== 0) {
                $this->renameAgencyReferences($createdByPrivateUserId, $previousName, $agencyName);
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAgencies(int $createdByPrivateUserId, int $limit = 100): array
    {
        if ($createdByPrivateUserId <= 0 || $limit <= 0) {
            return [];
        }

        $agencies = [];
        foreach ($this->listAgencyRows($createdByPrivateUserId) as $row) {
            $agency = $this->agencyFromRow($row);
            $name = (string) ($agency['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $agencies[mb_strtolower($name, 'UTF-8')] = array_merge($agency, $this->emptyAgencyStats());
        }

        foreach ($this->listAgencyBatchStats($createdByPrivateUserId) as $row) {
            $name = $this->nullableText($row['agency_name'] ?? null, 120);
            if ($name === null) {
                continue;
            }
            $key = mb_strtolower($name, 'UTF-8');
            if (!isset($agencies[$key])) {
                continue;
            }

            $agencies[$key] = array_merge($agencies[$key], $this->agencyStatsFromRow($row));
        }

        $result = array_values($agencies);
        usort($result, static fn (array $left, array $right): int => strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? '')));

        return array_slice($result, 0, max(1, min(300, $limit)));
    }

    public function createUnitMapping(
        int $createdByPrivateUserId,
        string $agencyName,
        string $matchText,
        int $rentalPropertyId,
        int $rentalUnitId
    ): bool {
        $agencyName = $this->requiredText($agencyName, 120);
        $matchText = $this->requiredText($matchText, 160);
        if (
            $createdByPrivateUserId <= 0
            || $agencyName === ''
            || $matchText === ''
            || !$this->canMapUnit($createdByPrivateUserId, $rentalPropertyId, $rentalUnitId)
        ) {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`created_by_private_user_id`, `agency_name`, `match_text`, `rental_property_id`,
                         `rental_unit_id`, `is_active`)
                     VALUES
                        (:created_by, :agency_name, :match_text, :property_id, :unit_id, 1)
                     ON DUPLICATE KEY UPDATE
                        `rental_property_id` = VALUES(`rental_property_id`),
                        `rental_unit_id` = VALUES(`rental_unit_id`),
                        `is_active` = 1',
                    $this->unitMappingsTable()
                )
            );

            return $statement->execute([
                'created_by' => $createdByPrivateUserId,
                'agency_name' => $agencyName,
                'match_text' => $matchText,
                'property_id' => $rentalPropertyId,
                'unit_id' => $rentalUnitId,
            ]);
        } catch (\Throwable) {
            return false;
        }
    }

    public function deleteUnitMappingForUser(int $createdByPrivateUserId, int $mappingId): bool
    {
        if ($createdByPrivateUserId <= 0 || $mappingId <= 0) {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'DELETE FROM `%s`
                     WHERE `id` = :id
                       AND `created_by_private_user_id` = :created_by',
                    $this->unitMappingsTable()
                )
            );
            $statement->execute([
                'id' => $mappingId,
                'created_by' => $createdByPrivateUserId,
            ]);

            return $statement->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function deleteAgencyForUser(int $createdByPrivateUserId, int $agencyId): bool
    {
        if ($createdByPrivateUserId <= 0 || $agencyId <= 0) {
            return false;
        }

        try {
            $this->ensureSchema();
            $agencyName = $this->agencyNameForUser($createdByPrivateUserId, $agencyId);
            if ($agencyName === null) {
                return false;
            }

            $pdo = $this->database->pdo();
            $pdo->beginTransaction();

            $statement = $pdo->prepare(
                sprintf(
                    'DELETE FROM `%s`
                     WHERE `id` = :id
                       AND `created_by_private_user_id` = :created_by',
                    $this->agenciesTable()
                )
            );
            $statement->execute([
                'id' => $agencyId,
                'created_by' => $createdByPrivateUserId,
            ]);
            $deleted = $statement->rowCount() > 0;

            if ($deleted) {
                $statement = $pdo->prepare(
                    sprintf(
                        'DELETE FROM `%s`
                         WHERE `created_by_private_user_id` = :created_by
                           AND LOWER(TRIM(`agency_name`)) = LOWER(:agency_name)',
                        $this->unitMappingsTable()
                    )
                );
                $statement->execute([
                    'created_by' => $createdByPrivateUserId,
                    'agency_name' => $agencyName,
                ]);
            }

            $pdo->commit();

            return $deleted;
        } catch (\Throwable) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listUnitMappings(int $createdByPrivateUserId, int $limit = 200): array
    {
        if ($createdByPrivateUserId <= 0 || $limit <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT m.*,
                            p.`name` AS `property_name`,
                            p.`address` AS `property_address`,
                            u.`label` AS `unit_label`,
                            u.`unit_type`
                     FROM `%s` m
                     INNER JOIN `%s` p ON p.`id` = m.`rental_property_id`
                     INNER JOIN `%s` u ON u.`id` = m.`rental_unit_id`
                     WHERE m.`created_by_private_user_id` = :created_by
                       AND m.`is_active` = 1
                     ORDER BY m.`agency_name` ASC, m.`match_text` ASC, m.`id` ASC
                     LIMIT :limit',
                    $this->unitMappingsTable(),
                    $this->database->table('rental_properties'),
                    $this->database->table('rental_units')
                )
            );
            $statement->bindValue(':created_by', $createdByPrivateUserId, PDO::PARAM_INT);
            $statement->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return array_map(
            fn (array $row): array => $this->unitMappingFromRow($row),
            $this->normalizeRows($rows)
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listAgencyRows(int $createdByPrivateUserId): array
    {
        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT *
                     FROM `%s`
                     WHERE `created_by_private_user_id` = :created_by
                     ORDER BY `name` ASC
                     LIMIT 300',
                    $this->agenciesTable()
                )
            );
            $statement->execute(['created_by' => $createdByPrivateUserId]);

            return $this->normalizeRows($statement->fetchAll(PDO::FETCH_ASSOC));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listAgencyBatchStats(int $createdByPrivateUserId): array
    {
        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT `agency_name`,
                            COUNT(*) AS `batch_count`,
                            COALESCE(SUM(`file_count`), 0) AS `file_count`,
                            COALESCE(SUM(`ignored_file_count`), 0) AS `ignored_file_count`,
                            COALESCE(SUM(`duplicate_file_count`), 0) AS `duplicate_file_count`,
                            MIN(`created_at`) AS `created_at`,
                            MAX(`created_at`) AS `last_activity_at`
                     FROM `%s`
                     WHERE `created_by_private_user_id` = :created_by
                       AND `agency_name` IS NOT NULL
                       AND TRIM(`agency_name`) <> ""
                     GROUP BY `agency_name`
                     ORDER BY `agency_name` ASC
                     LIMIT 300',
                    $this->batchesTable()
                )
            );
            $statement->execute(['created_by' => $createdByPrivateUserId]);

            return $this->normalizeRows($statement->fetchAll(PDO::FETCH_ASSOC));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function agencyFromRow(array $row): array
    {
        return [
            'id' => is_numeric($row['id'] ?? null) ? (int) $row['id'] : null,
            'name' => $this->nullableStringFromRow($row['name'] ?? null) ?? '',
            'legalName' => $this->nullableStringFromRow($row['legal_name'] ?? null),
            'contactTitle' => $this->nullableStringFromRow($row['contact_title'] ?? null),
            'postalAddress' => $this->nullableStringFromRow($row['postal_address'] ?? null),
            'phone' => $this->nullableStringFromRow($row['phone'] ?? null),
            'email' => $this->nullableStringFromRow($row['email'] ?? null),
            'advisorName' => $this->nullableStringFromRow($row['advisor_name'] ?? null),
            'advisorTitle' => $this->nullableStringFromRow($row['advisor_title'] ?? null),
            'advisorPhone' => $this->nullableStringFromRow($row['advisor_phone'] ?? null),
            'advisorEmail' => $this->nullableStringFromRow($row['advisor_email'] ?? null),
            'notes' => $this->nullableStringFromRow($row['notes'] ?? null),
            'createdAt' => $this->nullableStringFromRow($row['created_at'] ?? null),
            'updatedAt' => $this->nullableStringFromRow($row['updated_at'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyAgencyDetails(): array
    {
        return [
            'legalName' => null,
            'contactTitle' => null,
            'postalAddress' => null,
            'phone' => null,
            'email' => null,
            'advisorName' => null,
            'advisorTitle' => null,
            'advisorPhone' => null,
            'advisorEmail' => null,
            'notes' => null,
            'createdAt' => null,
            'updatedAt' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyAgencyStats(): array
    {
        return [
            'batchCount' => 0,
            'fileCount' => 0,
            'ignoredFileCount' => 0,
            'duplicateFileCount' => 0,
            'lastActivityAt' => null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function agencyStatsFromRow(array $row): array
    {
        return [
            'batchCount' => is_numeric($row['batch_count'] ?? null) ? (int) $row['batch_count'] : 0,
            'fileCount' => is_numeric($row['file_count'] ?? null) ? (int) $row['file_count'] : 0,
            'ignoredFileCount' => is_numeric($row['ignored_file_count'] ?? null)
                ? (int) $row['ignored_file_count']
                : 0,
            'duplicateFileCount' => is_numeric($row['duplicate_file_count'] ?? null)
                ? (int) $row['duplicate_file_count']
                : 0,
            'createdAt' => $this->nullableStringFromRow($row['created_at'] ?? null),
            'lastActivityAt' => $this->nullableStringFromRow($row['last_activity_at'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $details
     * @return array<string, string|null>|null
     */
    private function normalizeAgencyDetails(array $details): ?array
    {
        $email = $this->emailOrNull($details['email'] ?? null);
        $advisorEmail = $this->emailOrNull($details['advisor_email'] ?? null);
        if ($email === false || $advisorEmail === false) {
            return null;
        }

        return [
            'legal_name' => $this->nullableText($details['legal_name'] ?? null, 190),
            'contact_title' => $this->nullableText($details['contact_title'] ?? null, 120),
            'postal_address' => $this->nullableText($details['postal_address'] ?? null, 500),
            'phone' => $this->nullableText($details['phone'] ?? null, 80),
            'email' => $email,
            'advisor_name' => $this->nullableText($details['advisor_name'] ?? null, 160),
            'advisor_title' => $this->nullableText($details['advisor_title'] ?? null, 120),
            'advisor_phone' => $this->nullableText($details['advisor_phone'] ?? null, 80),
            'advisor_email' => $advisorEmail,
            'notes' => $this->nullableText($details['notes'] ?? null, 2000),
        ];
    }

    /**
     * @param array<string, string|null> $agencyDetails
     * @return array<string, mixed>
     */
    private function agencyStatementPayload(int $createdByPrivateUserId, string $agencyName, array $agencyDetails): array
    {
        return [
            'created_by' => $createdByPrivateUserId,
            'name' => $agencyName,
            'legal_name' => $agencyDetails['legal_name'] ?? null,
            'contact_title' => $agencyDetails['contact_title'] ?? null,
            'postal_address' => $agencyDetails['postal_address'] ?? null,
            'phone' => $agencyDetails['phone'] ?? null,
            'email' => $agencyDetails['email'] ?? null,
            'advisor_name' => $agencyDetails['advisor_name'] ?? null,
            'advisor_title' => $agencyDetails['advisor_title'] ?? null,
            'advisor_phone' => $agencyDetails['advisor_phone'] ?? null,
            'advisor_email' => $agencyDetails['advisor_email'] ?? null,
            'notes' => $agencyDetails['notes'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function emailOrNull(mixed $value): string|false|null
    {
        $email = $this->nullableText($value, 254);
        if ($email === null) {
            return null;
        }

        $email = strtolower($email);
        return filter_var($email, FILTER_VALIDATE_EMAIL) === false ? false : $email;
    }

    private function agencyNameForUser(int $createdByPrivateUserId, int $agencyId): ?string
    {
        if ($createdByPrivateUserId <= 0 || $agencyId <= 0) {
            return null;
        }

        $statement = $this->database->pdo()->prepare(
            sprintf(
                'SELECT `name`
                 FROM `%s`
                 WHERE `id` = :id
                   AND `created_by_private_user_id` = :created_by
                 LIMIT 1',
                $this->agenciesTable()
            )
        );
        $statement->execute([
            'id' => $agencyId,
            'created_by' => $createdByPrivateUserId,
        ]);
        $name = $statement->fetchColumn();

        return is_scalar($name) && trim((string) $name) !== '' ? (string) $name : null;
    }

    private function renameAgencyReferences(int $createdByPrivateUserId, string $previousName, string $newName): void
    {
        foreach ([$this->batchesTable(), $this->unitMappingsTable()] as $table) {
            try {
                $statement = $this->database->pdo()->prepare(
                    sprintf(
                        'UPDATE `%s`
                         SET `agency_name` = :new_name
                         WHERE `created_by_private_user_id` = :created_by
                           AND LOWER(TRIM(`agency_name`)) = LOWER(:previous_name)',
                        $table
                    )
                );
                $statement->execute([
                    'new_name' => $newName,
                    'created_by' => $createdByPrivateUserId,
                    'previous_name' => $previousName,
                ]);
            } catch (\Throwable) {
                continue;
            }
        }
    }

    public function findBatchById(int $batchId): ?AgencyImportBatch
    {
        if ($batchId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf('SELECT * FROM `%s` WHERE `id` = :id LIMIT 1', $this->batchesTable())
            );
            $statement->execute(['id' => $batchId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? AgencyImportBatch::fromDatabaseRow($row) : null;
    }

    /**
     * @return array<int, AgencyImportBatch>
     */
    public function listRecentBatches(int $createdByPrivateUserId, int $limit = 50): array
    {
        if ($createdByPrivateUserId <= 0 || $limit <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s`
                     WHERE `created_by_private_user_id` = :created_by
                       AND (`file_count` > 0 OR `ignored_file_count` > 0 OR `duplicate_file_count` > 0)
                     ORDER BY `created_at` DESC, `id` DESC
                     LIMIT :limit',
                    $this->batchesTable()
                )
            );
            $statement->bindValue(':created_by', $createdByPrivateUserId, PDO::PARAM_INT);
            $statement->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($rows)) {
            return [];
        }

        $batches = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $batch = AgencyImportBatch::fromDatabaseRow($row);
            if ($batch instanceof AgencyImportBatch) {
                $batches[] = $batch;
            }
        }

        return $batches;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRecentDocumentsForUser(int $createdByPrivateUserId, int $limit = 50): array
    {
        if ($createdByPrivateUserId <= 0 || $limit <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT d.*,
                            b.`agency_name` AS `batch_agency_name`,
                            b.`created_by_private_user_id`,
                            s.`id` AS `statement_id`,
                            s.`statement_period_start`,
                            s.`statement_period_end`,
                            COUNT(DISTINCT l.`id`) AS `statement_line_count`,
                            COUNT(DISTINCT CASE WHEN i.`resolved_at` IS NULL THEN i.`id` ELSE NULL END) AS `open_issue_count`
                     FROM `%s` d
                     INNER JOIN `%s` b ON b.`id` = d.`batch_id`
                     LEFT JOIN `%s` s ON s.`imported_document_id` = d.`id`
                     LEFT JOIN `%s` l ON l.`statement_id` = s.`id`
                     LEFT JOIN `%s` i ON i.`imported_document_id` = d.`id`
                     WHERE b.`created_by_private_user_id` = :created_by
                     GROUP BY d.`id`, b.`agency_name`, b.`created_by_private_user_id`,
                              s.`id`, s.`statement_period_start`, s.`statement_period_end`
                     ORDER BY d.`created_at` DESC, d.`id` DESC
                     LIMIT :limit',
                    $this->documentsTable(),
                    $this->batchesTable(),
                    $this->statementsTable(),
                    $this->linesTable(),
                    $this->issuesTable()
                )
            );
            $statement->bindValue(':created_by', $createdByPrivateUserId, PDO::PARAM_INT);
            $statement->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
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

            $document = AgencyImportedDocument::fromDatabaseRow($row);
            if (!$document instanceof AgencyImportedDocument) {
                continue;
            }

            $item = $document->toArray();
            $item['batchAgencyName'] = is_string($row['batch_agency_name'] ?? null)
                ? trim((string) $row['batch_agency_name'])
                : null;
            $item['statementId'] = is_numeric($row['statement_id'] ?? null) ? (int) $row['statement_id'] : null;
            $item['statementPeriodStart'] = is_string($row['statement_period_start'] ?? null)
                ? (string) $row['statement_period_start']
                : null;
            $item['statementPeriodEnd'] = is_string($row['statement_period_end'] ?? null)
                ? (string) $row['statement_period_end']
                : null;
            $item['statementLineCount'] = is_numeric($row['statement_line_count'] ?? null)
                ? (int) $row['statement_line_count']
                : 0;
            $item['openIssueCount'] = is_numeric($row['open_issue_count'] ?? null)
                ? (int) $row['open_issue_count']
                : 0;
            $documents[] = $item;
        }

        return $documents;
    }

    public function persistPreview(
        int $batchId,
        AgencyImportPreview $preview,
        ?string $privateDocumentId = null,
        ?string $detectedAgency = null,
        ?string $storagePath = null
    ): ?AgencyImportedDocument {
        if ($batchId <= 0 || !$this->isSha256((string) $preview->sha256)) {
            return null;
        }

        if ($this->findImportedDocumentBySha256((string) $preview->sha256) instanceof AgencyImportedDocument) {
            return null;
        }

        $batch = $this->findBatchById($batchId);
        if (!$batch instanceof AgencyImportBatch) {
            return null;
        }

        try {
            $this->ensureSchema();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();
            $importedDocument = $this->insertImportedDocument(
                $batchId,
                $preview,
                $privateDocumentId,
                $detectedAgency,
                $storagePath
            );
            if (!$importedDocument instanceof AgencyImportedDocument) {
                $pdo->rollBack();
                return null;
            }

            if ($preview->parserResult instanceof AgencyParserResult) {
                $statement = $this->insertStatement($importedDocument->id, $preview->parserResult, $detectedAgency);
                if ($statement instanceof AgencyStatement) {
                    $this->insertStatementLines($statement->id, $importedDocument->id, $preview->parserResult->statementLines);
                    $this->autoAssignStatementLinesFromLeases(
                        $statement->id,
                        $batch->createdByPrivateUserId,
                        $detectedAgency ?? $batch->agencyName
                    );
                }
            }

            foreach ($preview->issues as $issue) {
                $this->insertIssue($importedDocument->id, $issue);
            }

            $pdo->commit();

            return $this->findImportedDocumentById($importedDocument->id);
        } catch (\Throwable) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return null;
        }
    }

    public function findImportedDocumentById(int $documentId): ?AgencyImportedDocument
    {
        if ($documentId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf('SELECT * FROM `%s` WHERE `id` = :id LIMIT 1', $this->documentsTable())
            );
            $statement->execute(['id' => $documentId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? AgencyImportedDocument::fromDatabaseRow($row) : null;
    }

    public function deleteImportedDocumentForUser(
        int $createdByPrivateUserId,
        int $importedDocumentId
    ): ?AgencyImportedDocument {
        if ($createdByPrivateUserId <= 0 || $importedDocumentId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();

            $statement = $pdo->prepare(
                sprintf(
                    'SELECT d.*
                     FROM `%s` d
                     INNER JOIN `%s` b ON b.`id` = d.`batch_id`
                     WHERE d.`id` = :document_id
                       AND b.`created_by_private_user_id` = :created_by
                     LIMIT 1',
                    $this->documentsTable(),
                    $this->batchesTable()
                )
            );
            $statement->execute([
                'document_id' => $importedDocumentId,
                'created_by' => $createdByPrivateUserId,
            ]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            $document = is_array($row) ? AgencyImportedDocument::fromDatabaseRow($row) : null;
            if (!$document instanceof AgencyImportedDocument) {
                $pdo->rollBack();
                return null;
            }

            $this->deleteRowsByImportedDocumentId($this->issuesTable(), $document->id);
            $this->deleteRowsByImportedDocumentId($this->linesTable(), $document->id);
            $this->deleteRowsByImportedDocumentId($this->statementsTable(), $document->id);
            $this->deleteRowsById($this->documentsTable(), $document->id);
            $this->decrementBatchFileCount($document->batchId);

            $pdo->commit();

            return $document;
        } catch (\Throwable) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return null;
        }
    }

    public function findImportedDocumentBySha256(string $sha256): ?AgencyImportedDocument
    {
        $sha256 = strtolower(trim($sha256));
        if (!$this->isSha256($sha256)) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf('SELECT * FROM `%s` WHERE `sha256` = :sha256 LIMIT 1', $this->documentsTable())
            );
            $statement->execute(['sha256' => $sha256]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? AgencyImportedDocument::fromDatabaseRow($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function reviewDocumentForUser(int $createdByPrivateUserId, int $importedDocumentId): ?array
    {
        if ($createdByPrivateUserId <= 0 || $importedDocumentId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT d.*,
                            b.`agency_name` AS `batch_agency_name`,
                            s.`id` AS `statement_id`,
                            s.`rental_property_id`,
                            s.`statement_period_start`,
                            s.`statement_period_end`,
                            s.`statement_date`,
                            s.`owner_account_reference`,
                            s.`status` AS `statement_status`
                     FROM `%s` d
                     INNER JOIN `%s` b ON b.`id` = d.`batch_id`
                     LEFT JOIN `%s` s ON s.`imported_document_id` = d.`id`
                     WHERE d.`id` = :document_id
                       AND b.`created_by_private_user_id` = :created_by
                     LIMIT 1',
                    $this->documentsTable(),
                    $this->batchesTable(),
                    $this->statementsTable()
                )
            );
            $statement->execute([
                'document_id' => $importedDocumentId,
                'created_by' => $createdByPrivateUserId,
            ]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($row)) {
            return null;
        }

        $document = AgencyImportedDocument::fromDatabaseRow($row);
        if (!$document instanceof AgencyImportedDocument) {
            return null;
        }

        $result = $document->toArray();
        $result['batchAgencyName'] = $this->nullableStringFromRow($row['batch_agency_name'] ?? null);
        $result['statementId'] = is_numeric($row['statement_id'] ?? null) ? (int) $row['statement_id'] : null;
        $result['rentalPropertyId'] = is_numeric($row['rental_property_id'] ?? null) ? (int) $row['rental_property_id'] : null;
        $result['statementPeriodStart'] = $this->nullableStringFromRow($row['statement_period_start'] ?? null);
        $result['statementPeriodEnd'] = $this->nullableStringFromRow($row['statement_period_end'] ?? null);
        $result['statementDate'] = $this->nullableStringFromRow($row['statement_date'] ?? null);
        $result['ownerAccountReference'] = $this->nullableStringFromRow($row['owner_account_reference'] ?? null);
        $result['statementStatus'] = $this->nullableStringFromRow($row['statement_status'] ?? null);
        $result['maskedTextPreview'] = $this->nullableStringFromRow($row['masked_text_preview'] ?? null);
        $result['lines'] = is_numeric($row['statement_id'] ?? null)
            ? array_map(
                static fn (AgencyStatementLine $line): array => $line->toArray(),
                $this->listStatementLines((int) $row['statement_id'])
            )
            : [];
        $result['issues'] = array_map(
            static fn (AgencyImportIssue $issue): array => $issue->toArray(),
            $this->listIssues($importedDocumentId)
        );

        return $result;
    }

    /**
     * @return array<int, AgencyImportIssue>
     */
    public function listIssues(int $importedDocumentId): array
    {
        if ($importedDocumentId <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s`
                     WHERE `imported_document_id` = :document_id
                       AND `resolved_at` IS NULL
                     ORDER BY FIELD(`severity`, "error", "warning", "info"), `id` ASC',
                    $this->issuesTable()
                )
            );
            $statement->execute(['document_id' => $importedDocumentId]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($rows)) {
            return [];
        }

        $issues = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $type = $this->requiredText((string) ($row['issue_type'] ?? ''), 80);
            $severity = $this->requiredText((string) ($row['severity'] ?? ''), 20);
            $message = $this->requiredText((string) ($row['message'] ?? ''), 255);
            if ($type === '' || $severity === '' || $message === '') {
                continue;
            }

            $issues[] = new AgencyImportIssue(
                $type,
                in_array($severity, ['info', 'warning', 'error'], true) ? $severity : AgencyImportIssue::SEVERITY_WARNING,
                $message,
                is_numeric($row['source_page'] ?? null) ? (int) $row['source_page'] : null
            );
        }

        return $issues;
    }

    public function updateStatementPropertyForDocument(
        int $createdByPrivateUserId,
        int $importedDocumentId,
        ?int $rentalPropertyId
    ): bool {
        if ($createdByPrivateUserId <= 0 || $importedDocumentId <= 0 || ($rentalPropertyId !== null && $rentalPropertyId <= 0)) {
            return false;
        }

        try {
            $this->ensureSchema();
            $pdo = $this->database->pdo();
            $statement = $pdo->prepare(
                sprintf(
                    'SELECT s.`id`, s.`rental_property_id`
                     FROM `%s` s
                     INNER JOIN `%s` d ON d.`id` = s.`imported_document_id`
                     INNER JOIN `%s` b ON b.`id` = d.`batch_id`
                     WHERE s.`imported_document_id` = :document_id
                       AND b.`created_by_private_user_id` = :created_by
                     LIMIT 1',
                    $this->statementsTable(),
                    $this->documentsTable(),
                    $this->batchesTable()
                )
            );
            $statement->execute([
                'document_id' => $importedDocumentId,
                'created_by' => $createdByPrivateUserId,
            ]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || !is_numeric($row['id'] ?? null)) {
                return false;
            }

            $statementId = (int) $row['id'];
            $previousPropertyId = is_numeric($row['rental_property_id'] ?? null)
                ? (int) $row['rental_property_id']
                : null;

            $pdo->beginTransaction();
            $statement = $pdo->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `rental_property_id` = :property_id
                     WHERE `id` = :statement_id',
                    $this->statementsTable()
                )
            );
            $this->bindNullableInt($statement, ':property_id', $rentalPropertyId);
            $statement->bindValue(':statement_id', $statementId, PDO::PARAM_INT);
            $statement->execute();
            $this->propagateStatementPropertyToDerivedLines($statementId, $previousPropertyId, $rentalPropertyId);
            $pdo->commit();

            return true;
        } catch (\Throwable) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return false;
        }
    }

    /**
     * @param array<string, mixed> $corrections
     */
    public function reviewStatementLine(
        int $createdByPrivateUserId,
        int $lineId,
        string $action,
        array $corrections = []
    ): ?AgencyStatementLine {
        $action = trim($action);
        if ($createdByPrivateUserId <= 0 || $lineId <= 0 || !in_array($action, ['validate', 'correct', 'ignore'], true)) {
            return null;
        }

        $existing = $this->findStatementLineForUser($createdByPrivateUserId, $lineId);
        if (!$existing instanceof AgencyStatementLine) {
            return null;
        }

        $mappedCategory = $existing->mappedCategory;
        $periodStart = $existing->periodStart;
        $periodEnd = $existing->periodEnd;
        $amount = $existing->amount;
        $debitAmount = $existing->debitAmount;
        $creditAmount = $existing->creditAmount;
        $rentalPropertyId = $existing->rentalPropertyId;
        $rentalUnitId = $existing->rentalUnitId;
        $mappingStatus = match ($action) {
            'validate' => 'validated',
            'ignore' => 'ignored',
            default => 'review',
        };

        if (in_array($action, ['correct', 'validate'], true)) {
            if (array_key_exists('rental_property_id', $corrections)) {
                $rentalPropertyId = $this->positiveIntOrNull($corrections['rental_property_id']);
            }
            if (array_key_exists('rental_unit_id', $corrections)) {
                $rentalUnitId = $this->positiveIntOrNull($corrections['rental_unit_id']);
            }
        }

        if (in_array($action, ['correct', 'validate'], true) && $corrections !== []) {
            $mappedCategory = $this->requiredText((string) ($corrections['mapped_category'] ?? $mappedCategory), 80);
            $periodStart = $this->dateOrNull($corrections['period_start'] ?? $periodStart);
            $periodEnd = $this->dateOrNull($corrections['period_end'] ?? $periodEnd);
            $amount = $this->amountOrNull($corrections['amount'] ?? $amount);
            $debitAmount = $this->amountOrNull($corrections['debit_amount'] ?? $debitAmount);
            $creditAmount = $this->amountOrNull($corrections['credit_amount'] ?? $creditAmount);
            if ($mappedCategory === '') {
                return null;
            }
        }

        $policy = new AgencyFiscalReviewPolicy();
        if (
            $action === 'validate'
            && $policy->requiresManualFiscalReview($mappedCategory)
            && !$policy->isManualReviewConfirmed($corrections['manual_fiscal_review_confirmed'] ?? false)
        ) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `rental_property_id` = :rental_property_id,
                         `rental_unit_id` = :rental_unit_id,
                         `mapped_category` = :mapped_category,
                         `period_start` = :period_start,
                         `period_end` = :period_end,
                         `amount` = :amount,
                         `debit_amount` = :debit_amount,
                         `credit_amount` = :credit_amount,
                         `mapping_status` = :mapping_status,
                         `confidence_status` = :confidence_status
                     WHERE `id` = :line_id',
                    $this->linesTable()
                )
            );
            $statement->execute([
                'rental_property_id' => $rentalPropertyId,
                'rental_unit_id' => $rentalUnitId,
                'mapped_category' => $mappedCategory,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'amount' => $amount,
                'debit_amount' => $debitAmount,
                'credit_amount' => $creditAmount,
                'mapping_status' => $mappingStatus,
                'confidence_status' => $mappingStatus === 'validated' ? 'validated' : 'review',
                'line_id' => $lineId,
            ]);
            $this->refreshReviewStatus($existing->importedDocumentId);
        } catch (\Throwable) {
            return null;
        }

        return $this->findStatementLineForUser($createdByPrivateUserId, $lineId);
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<int, array<string, mixed>>
     */
    public function listValidatedFiscalLines(int $year, array $propertyIds): array
    {
        $propertyIds = $this->normalizeIds($propertyIds);
        if ($year < 2000 || $year > 2100 || $propertyIds === []) {
            return [];
        }

        try {
            $this->ensureSchema();
            $placeholders = $this->placeholders('property_id', $propertyIds);
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT l.*,
                            COALESCE(l.`rental_property_id`, s.`rental_property_id`) AS `effective_rental_property_id`,
                            s.`statement_period_start`, s.`statement_period_end`, d.`filename`
                     FROM `%s` l
                     INNER JOIN `%s` s ON s.`id` = l.`statement_id`
                     INNER JOIN `%s` d ON d.`id` = l.`imported_document_id`
                     WHERE l.`mapping_status` = "validated"
                       AND COALESCE(l.`rental_property_id`, s.`rental_property_id`) IN (%s)
                       AND COALESCE(l.`period_start`, l.`period_end`, l.`line_date`) IS NOT NULL
                       AND YEAR(COALESCE(l.`period_start`, l.`period_end`, l.`line_date`)) = :year
                     ORDER BY COALESCE(l.`rental_property_id`, s.`rental_property_id`) ASC, l.`period_start` ASC, l.`id` ASC',
                    $this->linesTable(),
                    $this->statementsTable(),
                    $this->documentsTable(),
                    implode(',', $placeholders)
                )
            );
            foreach ($propertyIds as $index => $propertyId) {
                $statement->bindValue(':property_id' . $index, $propertyId, PDO::PARAM_INT);
            }
            $statement->bindValue(':year', $year, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $rows = $this->normalizeRows($rows);
        foreach ($rows as &$row) {
            if (is_numeric($row['effective_rental_property_id'] ?? null)) {
                $row['rental_property_id'] = (int) $row['effective_rental_property_id'];
            }
            unset($row['effective_rental_property_id']);
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<int, AgencyStatementLine>
     */
    public function listStatementLines(int $statementId): array
    {
        if ($statementId <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s`
                     WHERE `statement_id` = :statement_id
                     ORDER BY `source_page` ASC, `id` ASC',
                    $this->linesTable()
                )
            );
            $statement->execute(['statement_id' => $statementId]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($rows)) {
            return [];
        }

        $lines = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $line = AgencyStatementLine::fromDatabaseRow($row);
            if ($line instanceof AgencyStatementLine) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    public function findStatementByImportedDocumentId(int $importedDocumentId): ?AgencyStatement
    {
        if ($importedDocumentId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf('SELECT * FROM `%s` WHERE `imported_document_id` = :id LIMIT 1', $this->statementsTable())
            );
            $statement->execute(['id' => $importedDocumentId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? AgencyStatement::fromDatabaseRow($row) : null;
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
                    `created_by_private_user_id` INT NOT NULL,
                    `name` VARCHAR(120) NOT NULL,
                    `legal_name` VARCHAR(190) NULL,
                    `contact_title` VARCHAR(120) NULL,
                    `postal_address` VARCHAR(500) NULL,
                    `phone` VARCHAR(80) NULL,
                    `email` VARCHAR(254) NULL,
                    `advisor_name` VARCHAR(160) NULL,
                    `advisor_title` VARCHAR(120) NULL,
                    `advisor_phone` VARCHAR(80) NULL,
                    `advisor_email` VARCHAR(254) NULL,
                    `notes` TEXT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uq_rental_agencies_user_name` (`created_by_private_user_id`, `name`),
                    KEY `idx_rental_agencies_user_updated` (`created_by_private_user_id`, `updated_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->agenciesTable()
            )
        );
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `created_by_private_user_id` INT NOT NULL,
                    `agency_name` VARCHAR(120) NULL,
                    `status` ENUM("draft", "review", "validated", "cancelled") NOT NULL DEFAULT "draft",
                    `source_directory` VARCHAR(255) NULL,
                    `file_count` INT NOT NULL DEFAULT 0,
                    `ignored_file_count` INT NOT NULL DEFAULT 0,
                    `duplicate_file_count` INT NOT NULL DEFAULT 0,
                    `notes` TEXT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY `idx_rental_agency_import_batches_user` (`created_by_private_user_id`, `created_at`),
                    KEY `idx_rental_agency_import_batches_status` (`status`, `created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->batchesTable()
            )
        );
        $this->ensureIndex(
            $pdo,
            $this->batchesTable(),
            'idx_rental_agency_import_batches_agency',
            '`created_by_private_user_id`, `agency_name`'
        );
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `batch_id` INT NOT NULL,
                    `private_document_id` VARCHAR(64) NULL,
                    `storage_path` VARCHAR(255) NULL,
                    `filename` VARCHAR(255) NOT NULL,
                    `mime_type` VARCHAR(120) NULL,
                    `file_size` INT NULL,
                    `sha256` CHAR(64) NOT NULL,
                    `detected_document_type` VARCHAR(80) NOT NULL DEFAULT "unknown",
                    `detected_agency` VARCHAR(120) NULL,
                    `parser_profile` VARCHAR(120) NULL,
                    `classification_confidence` DECIMAL(4,2) NOT NULL DEFAULT 0.00,
                    `text_extraction_status` VARCHAR(64) NOT NULL DEFAULT "unsupported",
                    `contains_sensitive_data` TINYINT(1) NOT NULL DEFAULT 0,
                    `review_status` ENUM("pending", "review", "validated", "ignored", "duplicate") NOT NULL DEFAULT "pending",
                    `masked_text_preview` MEDIUMTEXT NULL,
                    `parser_payload` JSON NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uq_rental_agency_imported_documents_sha256` (`sha256`),
                    KEY `idx_rental_agency_imported_documents_batch` (`batch_id`, `review_status`),
                    KEY `idx_rental_agency_imported_documents_type` (`detected_document_type`, `review_status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->documentsTable()
            )
        );
        $this->ensureColumn(
            $pdo,
            $this->documentsTable(),
            'storage_path',
            '`storage_path` VARCHAR(255) NULL AFTER `private_document_id`'
        );
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `imported_document_id` INT NOT NULL,
                    `rental_property_id` INT NULL,
                    `agency_name` VARCHAR(120) NULL,
                    `parser_profile` VARCHAR(120) NOT NULL,
                    `statement_period_start` DATE NULL,
                    `statement_period_end` DATE NULL,
                    `statement_date` DATE NULL,
                    `statement_number` VARCHAR(120) NULL,
                    `owner_account_reference` VARCHAR(120) NULL,
                    `status` ENUM("draft", "review", "validated", "cancelled") NOT NULL DEFAULT "draft",
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY `idx_rental_agency_statements_document` (`imported_document_id`),
                    KEY `idx_rental_agency_statements_period` (`statement_period_start`, `statement_period_end`, `status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->statementsTable()
            )
        );
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `statement_id` INT NOT NULL,
                    `imported_document_id` INT NOT NULL,
                    `rental_property_id` INT NULL,
                    `rental_unit_id` INT NULL,
                    `source_page` INT NOT NULL DEFAULT 1,
                    `source_line_hash` CHAR(64) NOT NULL,
                    `line_date` DATE NULL,
                    `period_start` DATE NULL,
                    `period_end` DATE NULL,
                    `amount` DECIMAL(10,2) NULL,
                    `debit_amount` DECIMAL(10,2) NULL,
                    `credit_amount` DECIMAL(10,2) NULL,
                    `called_amount` DECIMAL(10,2) NULL,
                    `paid_amount` DECIMAL(10,2) NULL,
                    `owner_transfer_amount` DECIMAL(10,2) NULL,
                    `raw_label` VARCHAR(255) NOT NULL,
                    `mapped_category` VARCHAR(80) NOT NULL,
                    `mapping_status` ENUM("suggested", "review", "validated", "ignored") NOT NULL DEFAULT "review",
                    `property_label` VARCHAR(160) NULL,
                    `unit_label` VARCHAR(160) NULL,
                    `tenant_name` VARCHAR(160) NULL,
                    `confidence_status` VARCHAR(40) NOT NULL DEFAULT "review",
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY `idx_rental_agency_statement_lines_statement` (`statement_id`, `source_page`),
                    KEY `idx_rental_agency_statement_lines_document` (`imported_document_id`),
                    KEY `idx_rental_agency_statement_lines_property` (`rental_property_id`, `mapping_status`),
                    KEY `idx_rental_agency_statement_lines_unit` (`rental_unit_id`, `mapping_status`),
                    KEY `idx_rental_agency_statement_lines_category` (`mapped_category`, `mapping_status`),
                    UNIQUE KEY `uq_rental_agency_statement_line_hash` (`statement_id`, `source_line_hash`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->linesTable()
            )
        );
        $this->ensureColumn(
            $pdo,
            $this->linesTable(),
            'rental_property_id',
            '`rental_property_id` INT NULL AFTER `imported_document_id`'
        );
        $this->ensureColumn(
            $pdo,
            $this->linesTable(),
            'rental_unit_id',
            '`rental_unit_id` INT NULL AFTER `rental_property_id`'
        );
        $this->ensureIndex(
            $pdo,
            $this->linesTable(),
            'idx_rental_agency_statement_lines_property',
            '`rental_property_id`, `mapping_status`'
        );
        $this->ensureIndex(
            $pdo,
            $this->linesTable(),
            'idx_rental_agency_statement_lines_unit',
            '`rental_unit_id`, `mapping_status`'
        );
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `imported_document_id` INT NULL,
                    `issue_type` VARCHAR(80) NOT NULL,
                    `severity` ENUM("info", "warning", "error") NOT NULL DEFAULT "warning",
                    `message` VARCHAR(255) NOT NULL,
                    `source_page` INT NULL,
                    `resolved_at` DATETIME NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY `idx_rental_agency_import_issues_document` (`imported_document_id`, `severity`, `resolved_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->issuesTable()
            )
        );
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `created_by_private_user_id` INT NOT NULL,
                    `agency_name` VARCHAR(120) NOT NULL,
                    `match_text` VARCHAR(160) NOT NULL,
                    `rental_property_id` INT NOT NULL,
                    `rental_unit_id` INT NOT NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uq_rental_agency_unit_mapping` (`created_by_private_user_id`, `agency_name`, `match_text`),
                    KEY `idx_rental_agency_unit_mappings_unit` (`rental_property_id`, `rental_unit_id`, `is_active`),
                    KEY `idx_rental_agency_unit_mappings_agency` (`created_by_private_user_id`, `agency_name`, `is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->unitMappingsTable()
            )
        );

        $this->schemaReady = true;
    }

    private function insertImportedDocument(
        int $batchId,
        AgencyImportPreview $preview,
        ?string $privateDocumentId,
        ?string $detectedAgency,
        ?string $storagePath
    ): ?AgencyImportedDocument {
        $payload = $preview->parserResult instanceof AgencyParserResult
            ? $this->json($preview->parserResult->toArray())
            : null;

        $statement = $this->database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`batch_id`, `private_document_id`, `storage_path`, `filename`, `mime_type`, `file_size`, `sha256`,
                     `detected_document_type`, `detected_agency`, `parser_profile`, `classification_confidence`,
                     `text_extraction_status`, `contains_sensitive_data`, `review_status`, `masked_text_preview`, `parser_payload`)
                 VALUES
                    (:batch_id, :private_document_id, :storage_path, :filename, :mime_type, :file_size, :sha256,
                     :detected_document_type, :detected_agency, :parser_profile, :classification_confidence,
                     :text_extraction_status, :contains_sensitive_data, :review_status, :masked_text_preview, :parser_payload)',
                $this->documentsTable()
            )
        );
        $statement->execute([
            'batch_id' => $batchId,
            'private_document_id' => $this->nullableText($privateDocumentId, 64),
            'storage_path' => $this->storagePath($storagePath),
            'filename' => $this->requiredText($preview->filename, 255),
            'mime_type' => $this->nullableText($preview->mimeType, 120),
            'file_size' => $preview->fileSize !== null ? max(0, $preview->fileSize) : null,
            'sha256' => strtolower((string) $preview->sha256),
            'detected_document_type' => $this->documentType($preview->classification->documentType),
            'detected_agency' => $this->nullableText($detectedAgency, 120),
            'parser_profile' => $this->nullableText($preview->classification->parserProfile, 120),
            'classification_confidence' => max(0.0, min(1.0, $preview->classification->confidence)),
            'text_extraction_status' => $this->requiredText($preview->textExtraction->status, 64),
            'contains_sensitive_data' => $this->containsSensitiveData($preview) ? 1 : 0,
            'review_status' => $preview->classification->isKnown() ? 'review' : 'pending',
            'masked_text_preview' => $preview->maskedTextPreview,
            'parser_payload' => $payload,
        ]);

        return $this->findImportedDocumentById((int) $this->database->pdo()->lastInsertId());
    }

    private function insertStatement(
        int $importedDocumentId,
        AgencyParserResult $result,
        ?string $detectedAgency
    ): ?AgencyStatement {
        $fields = $result->extractedFields;
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`imported_document_id`, `agency_name`, `parser_profile`, `statement_period_start`,
                     `statement_period_end`, `statement_date`, `statement_number`, `owner_account_reference`, `status`)
                 VALUES
                    (:imported_document_id, :agency_name, :parser_profile, :period_start,
                     :period_end, :statement_date, :statement_number, :owner_account_reference, :status)',
                $this->statementsTable()
            )
        );
        $statement->execute([
            'imported_document_id' => $importedDocumentId,
            'agency_name' => $this->nullableText($detectedAgency ?? (string) ($fields['agencyName'] ?? ''), 120),
            'parser_profile' => $this->requiredText($result->parserProfile, 120),
            'period_start' => $this->dateOrNull($fields['periodStart'] ?? null),
            'period_end' => $this->dateOrNull($fields['periodEnd'] ?? null),
            'statement_date' => $this->dateOrNull($fields['statementDate'] ?? null),
            'statement_number' => $this->nullableText($fields['statementNumber'] ?? null, 120),
            'owner_account_reference' => $this->nullableText(
                $fields['ownerAccountReference'] ?? $fields['personalAccountReference'] ?? null,
                120
            ),
            'status' => 'draft',
        ]);

        $id = (int) $this->database->pdo()->lastInsertId();
        if ($id <= 0) {
            return null;
        }

        $select = $this->database->pdo()->prepare(
            sprintf('SELECT * FROM `%s` WHERE `id` = :id LIMIT 1', $this->statementsTable())
        );
        $select->execute(['id' => $id]);
        $row = $select->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? AgencyStatement::fromDatabaseRow($row) : null;
    }

    /**
     * @param array<int, AgencyStatementLineDraft> $lines
     */
    private function insertStatementLines(int $statementId, int $importedDocumentId, array $lines): int
    {
        $inserted = 0;
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'INSERT IGNORE INTO `%s`
                    (`statement_id`, `imported_document_id`, `source_page`, `source_line_hash`, `line_date`,
                     `period_start`, `period_end`, `amount`, `debit_amount`, `credit_amount`,
                     `called_amount`, `paid_amount`, `owner_transfer_amount`, `raw_label`, `mapped_category`,
                     `mapping_status`, `property_label`, `unit_label`, `tenant_name`, `confidence_status`)
                 VALUES
                    (:statement_id, :imported_document_id, :source_page, :source_line_hash, :line_date,
                     :period_start, :period_end, :amount, :debit_amount, :credit_amount,
                     :called_amount, :paid_amount, :owner_transfer_amount, :raw_label, :mapped_category,
                     :mapping_status, :property_label, :unit_label, :tenant_name, :confidence_status)',
                $this->linesTable()
            )
        );

        foreach ($lines as $line) {
            $statement->execute([
                'statement_id' => $statementId,
                'imported_document_id' => $importedDocumentId,
                'source_page' => max(1, $line->sourcePage),
                'source_line_hash' => $this->lineHash($line),
                'line_date' => $this->dateOrNull($line->lineDate),
                'period_start' => $this->dateOrNull($line->periodStart),
                'period_end' => $this->dateOrNull($line->periodEnd),
                'amount' => $line->amount,
                'debit_amount' => $line->debitAmount,
                'credit_amount' => $line->creditAmount,
                'called_amount' => $line->calledAmount,
                'paid_amount' => $line->paidAmount,
                'owner_transfer_amount' => $line->ownerTransferAmount,
                'raw_label' => $this->requiredText($line->rawLabel, 255),
                'mapped_category' => $this->requiredText($line->mappedCategory, 80),
                'mapping_status' => $line->mappedCategory === 'other' ? 'review' : 'suggested',
                'property_label' => $this->nullableText($line->propertyLabel, 160),
                'unit_label' => $this->nullableText($line->unitLabel, 160),
                'tenant_name' => $this->nullableText($line->tenantName, 160),
                'confidence_status' => $this->requiredText($line->confidenceStatus, 40),
            ]);
            $inserted += $statement->rowCount() > 0 ? 1 : 0;
        }

        return $inserted;
    }

    private function insertIssue(int $importedDocumentId, AgencyImportIssue $issue): bool
    {
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`imported_document_id`, `issue_type`, `severity`, `message`, `source_page`)
                 VALUES
                    (:imported_document_id, :issue_type, :severity, :message, :source_page)',
                $this->issuesTable()
            )
        );

        return $statement->execute([
            'imported_document_id' => $importedDocumentId,
            'issue_type' => $this->requiredText($issue->type, 80),
            'severity' => in_array($issue->severity, ['info', 'warning', 'error'], true) ? $issue->severity : 'warning',
            'message' => $this->requiredText($issue->message, 255),
            'source_page' => $issue->sourcePage,
        ]);
    }

    private function containsSensitiveData(AgencyImportPreview $preview): bool
    {
        $text = mb_substr($preview->textExtraction->text, 0, 4000, 'UTF-8');
        return trim($text) !== '' && $text !== $preview->maskedTextPreview;
    }

    private function findStatementLineForUser(int $createdByPrivateUserId, int $lineId): ?AgencyStatementLine
    {
        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT l.*
                     FROM `%s` l
                     INNER JOIN `%s` d ON d.`id` = l.`imported_document_id`
                     INNER JOIN `%s` b ON b.`id` = d.`batch_id`
                     WHERE l.`id` = :line_id
                       AND b.`created_by_private_user_id` = :created_by
                     LIMIT 1',
                    $this->linesTable(),
                    $this->documentsTable(),
                    $this->batchesTable()
                )
            );
            $statement->execute([
                'line_id' => $lineId,
                'created_by' => $createdByPrivateUserId,
            ]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? AgencyStatementLine::fromDatabaseRow($row) : null;
    }

    private function refreshReviewStatus(int $importedDocumentId): void
    {
        if ($importedDocumentId <= 0) {
            return;
        }

        try {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT
                        COUNT(*) AS total_lines,
                        SUM(CASE WHEN `mapping_status` = "validated" THEN 1 ELSE 0 END) AS validated_lines,
                        SUM(CASE WHEN `mapping_status` = "ignored" THEN 1 ELSE 0 END) AS ignored_lines
                     FROM `%s`
                     WHERE `imported_document_id` = :document_id',
                    $this->linesTable()
                )
            );
            $statement->execute(['document_id' => $importedDocumentId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            $total = is_array($row) && is_numeric($row['total_lines'] ?? null) ? (int) $row['total_lines'] : 0;
            $validated = is_array($row) && is_numeric($row['validated_lines'] ?? null) ? (int) $row['validated_lines'] : 0;
            $ignored = is_array($row) && is_numeric($row['ignored_lines'] ?? null) ? (int) $row['ignored_lines'] : 0;
            $reviewStatus = 'review';
            $statementStatus = 'review';
            if ($total > 0 && $ignored === $total) {
                $reviewStatus = 'ignored';
                $statementStatus = 'cancelled';
            } elseif ($total > 0 && ($validated + $ignored) === $total && $validated > 0) {
                $reviewStatus = 'validated';
                $statementStatus = 'validated';
            }

            $updateDocument = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s` SET `review_status` = :status WHERE `id` = :document_id',
                    $this->documentsTable()
                )
            );
            $updateDocument->execute(['status' => $reviewStatus, 'document_id' => $importedDocumentId]);

            $updateStatement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s` SET `status` = :status WHERE `imported_document_id` = :document_id',
                    $this->statementsTable()
                )
            );
            $updateStatement->execute(['status' => $statementStatus, 'document_id' => $importedDocumentId]);
        } catch (\Throwable) {
            return;
        }
    }

    private function propagateStatementPropertyToDerivedLines(
        int $statementId,
        ?int $previousPropertyId,
        ?int $rentalPropertyId
    ): void {
        if ($statementId <= 0) {
            return;
        }

        $propertyCondition = '`rental_property_id` IS NULL';
        if ($previousPropertyId !== null) {
            $propertyCondition .= ' OR `rental_property_id` = :previous_property_id';
        }

        $statement = $this->database->pdo()->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `rental_property_id` = :property_id
                 WHERE `statement_id` = :statement_id
                   AND `rental_unit_id` IS NULL
                   AND (%s)',
                $this->linesTable(),
                $propertyCondition
            )
        );
        $this->bindNullableInt($statement, ':property_id', $rentalPropertyId);
        $statement->bindValue(':statement_id', $statementId, PDO::PARAM_INT);
        if ($previousPropertyId !== null) {
            $statement->bindValue(':previous_property_id', $previousPropertyId, PDO::PARAM_INT);
        }
        $statement->execute();
    }

    private function bindNullableInt(\PDOStatement $statement, string $parameter, ?int $value): void
    {
        if ($value === null) {
            $statement->bindValue($parameter, null, PDO::PARAM_NULL);
            return;
        }

        $statement->bindValue($parameter, $value, PDO::PARAM_INT);
    }

    private function autoAssignStatementLinesFromLeases(
        int $statementId,
        int $privateUserId,
        ?string $agencyName = null
    ): void {
        if ($statementId <= 0 || $privateUserId <= 0) {
            return;
        }

        $this->autoAssignStatementLinesFromUnitMappings($statementId, $privateUserId, $agencyName);

        $candidates = $this->leaseUnitCandidatesForUser($privateUserId);
        if ($candidates === []) {
            return;
        }

        foreach ($this->statementLinesForAutoAssignment($statementId) as $line) {
            $lineId = is_numeric($line['id'] ?? null) ? (int) $line['id'] : 0;
            $tenantName = is_scalar($line['tenant_name'] ?? null) ? (string) $line['tenant_name'] : '';
            if ($lineId <= 0 || trim($tenantName) === '') {
                continue;
            }

            $candidate = $this->uniqueLeaseCandidateForTenant($tenantName, $candidates);
            if ($candidate === null) {
                continue;
            }

            $this->assignStatementLineRentalUnit(
                $statementId,
                $lineId,
                $candidate['rentalPropertyId'],
                $candidate['rentalUnitId']
            );
        }
    }

    private function autoAssignStatementLinesFromUnitMappings(
        int $statementId,
        int $privateUserId,
        ?string $agencyName
    ): void {
        $agencyName = $this->requiredText((string) $agencyName, 120);
        if ($statementId <= 0 || $privateUserId <= 0 || $agencyName === '') {
            return;
        }

        $candidates = $this->unitMappingCandidatesForAgency($privateUserId, $agencyName);
        if ($candidates === []) {
            return;
        }

        foreach ($this->statementLinesForAutoAssignment($statementId) as $line) {
            $lineId = is_numeric($line['id'] ?? null) ? (int) $line['id'] : 0;
            if ($lineId <= 0) {
                continue;
            }

            $candidate = $this->uniqueUnitMappingCandidateForLine($line, $candidates);
            if ($candidate === null) {
                continue;
            }

            $this->assignStatementLineRentalUnit(
                $statementId,
                $lineId,
                $candidate['rentalPropertyId'],
                $candidate['rentalUnitId']
            );
        }
    }

    /**
     * @return array<int, array{id:int, tenantName:string, normalizedTenantName:string, tenantNameSignature:string, rentalPropertyId:int, rentalUnitId:int}>
     */
    private function leaseUnitCandidatesForUser(int $privateUserId): array
    {
        try {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT l.`id`, l.`rental_property_id`, l.`rental_unit_id`, t.`full_name` AS `tenant_name`
                     FROM `%s` l
                     INNER JOIN `%s` t ON t.`id` = l.`rental_tenant_id`
                     INNER JOIN `%s` u ON u.`id` = l.`rental_unit_id`
                     INNER JOIN `%s` p ON p.`id` = l.`rental_property_id`
                     INNER JOIN `%s` m ON m.`rental_property_id` = l.`rental_property_id`
                     WHERE m.`private_user_id` = :private_user_id
                       AND m.`status` = "active"
                       AND m.`is_active` = 1
                       AND l.`status` IN ("draft", "validated")
                       AND t.`is_active` = 1
                       AND u.`is_active` = 1
                       AND p.`is_active` = 1
                     ORDER BY l.`id` ASC',
                    $this->database->table('rental_leases'),
                    $this->database->table('rental_tenants'),
                    $this->database->table('rental_units'),
                    $this->database->table('rental_properties'),
                    $this->database->table('rental_property_members')
                )
            );
            $statement->execute(['private_user_id' => $privateUserId]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $candidates = [];
        foreach ($this->normalizeRows($rows) as $row) {
            $id = is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0;
            $propertyId = is_numeric($row['rental_property_id'] ?? null) ? (int) $row['rental_property_id'] : 0;
            $unitId = is_numeric($row['rental_unit_id'] ?? null) ? (int) $row['rental_unit_id'] : 0;
            $tenantName = is_scalar($row['tenant_name'] ?? null) ? trim((string) $row['tenant_name']) : '';
            $normalizedTenantName = $this->normalizeTenantName($tenantName);
            if ($id <= 0 || $propertyId <= 0 || $unitId <= 0 || $normalizedTenantName === '') {
                continue;
            }

            $candidates[] = [
                'id' => $id,
                'tenantName' => $tenantName,
                'normalizedTenantName' => $normalizedTenantName,
                'tenantNameSignature' => $this->tenantNameSignature($normalizedTenantName),
                'rentalPropertyId' => $propertyId,
                'rentalUnitId' => $unitId,
            ];
        }

        return $candidates;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function statementLinesForAutoAssignment(int $statementId): array
    {
        try {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT `id`, `raw_label`, `property_label`, `unit_label`, `tenant_name`
                     FROM `%s`
                     WHERE `statement_id` = :statement_id
                       AND `rental_unit_id` IS NULL
                       AND (`tenant_name` IS NOT NULL OR `unit_label` IS NOT NULL OR `raw_label` IS NOT NULL)',
                    $this->linesTable()
                )
            );
            $statement->execute(['statement_id' => $statementId]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return $this->normalizeRows($rows);
    }

    /**
     * @param array<int, array{id:int, tenantName:string, normalizedTenantName:string, tenantNameSignature:string, rentalPropertyId:int, rentalUnitId:int}> $candidates
     * @return array{id:int, tenantName:string, normalizedTenantName:string, tenantNameSignature:string, rentalPropertyId:int, rentalUnitId:int}|null
     */
    private function uniqueLeaseCandidateForTenant(string $tenantName, array $candidates): ?array
    {
        $normalizedTenantName = $this->normalizeTenantName($tenantName);
        if ($normalizedTenantName === '') {
            return null;
        }

        $exactMatches = array_values(array_filter(
            $candidates,
            static fn (array $candidate): bool => $candidate['normalizedTenantName'] === $normalizedTenantName
        ));
        $candidate = $this->uniqueUnitCandidate($exactMatches);
        if ($candidate !== null) {
            return $candidate;
        }

        $signature = $this->tenantNameSignature($normalizedTenantName);
        if ($signature === '') {
            return null;
        }

        $signatureMatches = array_values(array_filter(
            $candidates,
            static fn (array $candidate): bool => $candidate['tenantNameSignature'] === $signature
        ));

        return $this->uniqueUnitCandidate($signatureMatches);
    }

    /**
     * @return array<int, array{id:int, agencyName:string, matchText:string, normalizedMatchText:string, matchSignature:string, rentalPropertyId:int, rentalUnitId:int}>
     */
    private function unitMappingCandidatesForAgency(int $privateUserId, string $agencyName): array
    {
        try {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT `id`, `agency_name`, `match_text`, `rental_property_id`, `rental_unit_id`
                     FROM `%s`
                     WHERE `created_by_private_user_id` = :private_user_id
                       AND LOWER(TRIM(`agency_name`)) = LOWER(:agency_name)
                       AND `is_active` = 1
                     ORDER BY LENGTH(`match_text`) DESC, `id` ASC',
                    $this->unitMappingsTable()
                )
            );
            $statement->execute([
                'private_user_id' => $privateUserId,
                'agency_name' => $agencyName,
            ]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $candidates = [];
        foreach ($this->normalizeRows($rows) as $row) {
            $id = is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0;
            $propertyId = is_numeric($row['rental_property_id'] ?? null) ? (int) $row['rental_property_id'] : 0;
            $unitId = is_numeric($row['rental_unit_id'] ?? null) ? (int) $row['rental_unit_id'] : 0;
            $matchText = is_scalar($row['match_text'] ?? null) ? trim((string) $row['match_text']) : '';
            $normalizedMatchText = $this->normalizeTenantName($matchText);
            if ($id <= 0 || $propertyId <= 0 || $unitId <= 0 || $normalizedMatchText === '') {
                continue;
            }

            $candidates[] = [
                'id' => $id,
                'agencyName' => is_scalar($row['agency_name'] ?? null) ? trim((string) $row['agency_name']) : '',
                'matchText' => $matchText,
                'normalizedMatchText' => $normalizedMatchText,
                'matchSignature' => $this->tenantNameSignature($normalizedMatchText),
                'rentalPropertyId' => $propertyId,
                'rentalUnitId' => $unitId,
            ];
        }

        return $candidates;
    }

    /**
     * @param array<string, mixed> $line
     * @param array<int, array{id:int, agencyName:string, matchText:string, normalizedMatchText:string, matchSignature:string, rentalPropertyId:int, rentalUnitId:int}> $candidates
     * @return array{id:int, agencyName:string, matchText:string, normalizedMatchText:string, matchSignature:string, rentalPropertyId:int, rentalUnitId:int}|null
     */
    private function uniqueUnitMappingCandidateForLine(array $line, array $candidates): ?array
    {
        $texts = [
            is_scalar($line['tenant_name'] ?? null) ? (string) $line['tenant_name'] : '',
            is_scalar($line['unit_label'] ?? null) ? (string) $line['unit_label'] : '',
            is_scalar($line['raw_label'] ?? null) ? (string) $line['raw_label'] : '',
            is_scalar($line['property_label'] ?? null) ? (string) $line['property_label'] : '',
        ];
        $lineVariants = [];
        foreach ($texts as $text) {
            $normalized = $this->normalizeTenantName($text);
            if ($normalized !== '') {
                $lineVariants[] = [
                    'normalized' => $normalized,
                    'signature' => $this->tenantNameSignature($normalized),
                ];
            }
        }

        if ($lineVariants === []) {
            return null;
        }

        $matches = [];
        foreach ($candidates as $candidate) {
            foreach ($lineVariants as $variant) {
                if (
                    $candidate['normalizedMatchText'] === $variant['normalized']
                    || (
                        $candidate['matchSignature'] !== ''
                        && $candidate['matchSignature'] === $variant['signature']
                    )
                    || (
                        mb_strlen($candidate['normalizedMatchText'], 'UTF-8') >= 4
                        && str_contains($variant['normalized'], $candidate['normalizedMatchText'])
                    )
                ) {
                    $matches[] = $candidate;
                    break;
                }
            }
        }

        return $this->uniqueUnitMappingCandidate($matches);
    }

    /**
     * @param array<int, array{id:int, agencyName:string, matchText:string, normalizedMatchText:string, matchSignature:string, rentalPropertyId:int, rentalUnitId:int}> $candidates
     * @return array{id:int, agencyName:string, matchText:string, normalizedMatchText:string, matchSignature:string, rentalPropertyId:int, rentalUnitId:int}|null
     */
    private function uniqueUnitMappingCandidate(array $candidates): ?array
    {
        if ($candidates === []) {
            return null;
        }

        $matchesByUnit = [];
        foreach ($candidates as $candidate) {
            $key = $candidate['rentalPropertyId'] . ':' . $candidate['rentalUnitId'];
            $matchesByUnit[$key] = $candidate;
        }

        if (count($matchesByUnit) !== 1) {
            return null;
        }

        $candidate = reset($matchesByUnit);
        return is_array($candidate) ? $candidate : null;
    }

    /**
     * @param array<int, array{id:int, tenantName:string, normalizedTenantName:string, tenantNameSignature:string, rentalPropertyId:int, rentalUnitId:int}> $candidates
     * @return array{id:int, tenantName:string, normalizedTenantName:string, tenantNameSignature:string, rentalPropertyId:int, rentalUnitId:int}|null
     */
    private function uniqueUnitCandidate(array $candidates): ?array
    {
        if ($candidates === []) {
            return null;
        }

        $matchesByUnit = [];
        foreach ($candidates as $candidate) {
            $key = $candidate['rentalPropertyId'] . ':' . $candidate['rentalUnitId'];
            $matchesByUnit[$key] = $candidate;
        }

        if (count($matchesByUnit) !== 1) {
            return null;
        }

        $candidate = reset($matchesByUnit);
        return is_array($candidate) ? $candidate : null;
    }

    private function assignStatementLineRentalUnit(
        int $statementId,
        int $lineId,
        int $rentalPropertyId,
        int $rentalUnitId
    ): void {
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `rental_property_id` = :property_id,
                     `rental_unit_id` = :unit_id
                 WHERE `id` = :line_id
                   AND `statement_id` = :statement_id
                   AND `rental_unit_id` IS NULL',
                $this->linesTable()
            )
        );
        $statement->execute([
            'property_id' => $rentalPropertyId,
            'unit_id' => $rentalUnitId,
            'line_id' => $lineId,
            'statement_id' => $statementId,
        ]);
    }

    private function normalizeTenantName(string $value): string
    {
        $value = (string) preg_replace('/\([^)]*\)/u', ' ', $value);
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'à' => 'a',
            'â' => 'a',
            'ä' => 'a',
            'ç' => 'c',
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'î' => 'i',
            'ï' => 'i',
            'ô' => 'o',
            'ö' => 'o',
            'ù' => 'u',
            'û' => 'u',
            'ü' => 'u',
        ]);
        $value = (string) preg_replace('/[^a-z0-9]+/u', ' ', $value);
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function tenantNameSignature(string $normalizedTenantName): string
    {
        $tokens = array_values(array_filter(
            explode(' ', $normalizedTenantName),
            static fn (string $token): bool => $token !== ''
        ));
        sort($tokens, SORT_STRING);

        return implode(' ', $tokens);
    }

    private function deleteRowsByImportedDocumentId(string $table, int $importedDocumentId): void
    {
        $statement = $this->database->pdo()->prepare(
            sprintf('DELETE FROM `%s` WHERE `imported_document_id` = :document_id', $table)
        );
        $statement->execute(['document_id' => $importedDocumentId]);
    }

    private function deleteRowsById(string $table, int $id): void
    {
        $statement = $this->database->pdo()->prepare(
            sprintf('DELETE FROM `%s` WHERE `id` = :id', $table)
        );
        $statement->execute(['id' => $id]);
    }

    private function decrementBatchFileCount(int $batchId): void
    {
        if ($batchId <= 0) {
            return;
        }

        $statement = $this->database->pdo()->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `file_count` = CASE WHEN `file_count` > 0 THEN `file_count` - 1 ELSE 0 END
                 WHERE `id` = :batch_id',
                $this->batchesTable()
            )
        );
        $statement->execute(['batch_id' => $batchId]);

        $statement = $this->database->pdo()->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `status` = "cancelled"
                 WHERE `id` = :batch_id
                   AND `file_count` = 0
                   AND `ignored_file_count` = 0
                   AND `duplicate_file_count` = 0',
                $this->batchesTable()
            )
        );
        $statement->execute(['batch_id' => $batchId]);
    }

    private function canMapUnit(int $privateUserId, int $rentalPropertyId, int $rentalUnitId): bool
    {
        if ($privateUserId <= 0 || $rentalPropertyId <= 0 || $rentalUnitId <= 0) {
            return false;
        }

        try {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT 1
                     FROM `%s` u
                     INNER JOIN `%s` p ON p.`id` = u.`rental_property_id`
                     INNER JOIN `%s` m ON m.`rental_property_id` = p.`id`
                     WHERE u.`id` = :unit_id
                       AND u.`rental_property_id` = :property_id
                       AND u.`is_active` = 1
                       AND p.`is_active` = 1
                       AND m.`private_user_id` = :private_user_id
                       AND m.`status` = "active"
                       AND m.`is_active` = 1
                     LIMIT 1',
                    $this->database->table('rental_units'),
                    $this->database->table('rental_properties'),
                    $this->database->table('rental_property_members')
                )
            );
            $statement->execute([
                'unit_id' => $rentalUnitId,
                'property_id' => $rentalPropertyId,
                'private_user_id' => $privateUserId,
            ]);

            return $statement->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function unitMappingFromRow(array $row): array
    {
        return [
            'id' => is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0,
            'agencyName' => $this->nullableStringFromRow($row['agency_name'] ?? null),
            'matchText' => $this->nullableStringFromRow($row['match_text'] ?? null),
            'rentalPropertyId' => is_numeric($row['rental_property_id'] ?? null)
                ? (int) $row['rental_property_id']
                : 0,
            'rentalUnitId' => is_numeric($row['rental_unit_id'] ?? null) ? (int) $row['rental_unit_id'] : 0,
            'propertyName' => $this->nullableStringFromRow($row['property_name'] ?? null),
            'propertyAddress' => $this->nullableStringFromRow($row['property_address'] ?? null),
            'unitLabel' => $this->nullableStringFromRow($row['unit_label'] ?? null),
            'unitType' => $this->nullableStringFromRow($row['unit_type'] ?? null),
            'createdAt' => $this->nullableStringFromRow($row['created_at'] ?? null),
            'updatedAt' => $this->nullableStringFromRow($row['updated_at'] ?? null),
        ];
    }

    private function storagePath(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $path = trim(str_replace('\\', '/', (string) $value));
        if ($path === '' || strlen($path) > 255 || str_contains($path, '..')) {
            return null;
        }

        return preg_match('/\Auploads\/[a-z0-9._\/-]+\z/i', $path) === 1 ? $path : null;
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
                   AND INDEX_NAME = :index_name'
            );
            $statement->execute(['table' => $table, 'index_name' => $index]);
            if ((int) $statement->fetchColumn() > 0) {
                return;
            }

            $pdo->exec(sprintf('ALTER TABLE `%s` ADD INDEX `%s` (%s)', $table, $index, $columns));
        } catch (\Throwable) {
            return;
        }
    }

    private function json(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : '{}';
    }

    private function documentType(string $documentType): string
    {
        return in_array($documentType, AgencyDocumentType::all(), true) ? $documentType : AgencyDocumentType::UNKNOWN;
    }

    private function lineHash(AgencyStatementLineDraft $line): string
    {
        return hash('sha256', implode('|', [
            $line->sourcePage,
            $line->sourceLineHash,
            $line->rawLabel,
            $line->amount,
            $line->periodStart,
            $line->periodEnd,
            $line->propertyLabel,
            $line->unitLabel,
            $line->tenantName,
        ]));
    }

    private function dateOrNull(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        return preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) === 1 ? $value : null;
    }

    private function amountOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace(["\xc2\xa0", ' '], '', trim($value));
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $value = (int) $value;
        return $value > 0 ? $value : null;
    }

    private function normalizeStatus(string $status, array $allowed, string $fallback): string
    {
        $status = trim($status);
        return in_array($status, $allowed, true) ? $status : $fallback;
    }

    private function nullableText(mixed $value, int $maxLength): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = $this->requiredText((string) $value, $maxLength);
        return $value === '' ? null : $value;
    }

    private function requiredText(string $value, int $maxLength): string
    {
        $value = trim(strip_tags($value));
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    private function isSha256(string $value): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/', strtolower(trim($value))) === 1;
    }

    /**
     * @param array<int, mixed> $ids
     * @return array<int, int>
     */
    private function normalizeIds(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $normalized[] = (int) $id;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, string>
     */
    private function placeholders(string $prefix, array $ids): array
    {
        $placeholders = [];
        foreach (array_keys($ids) as $index) {
            $placeholders[] = ':' . $prefix . $index;
        }

        return $placeholders;
    }

    /**
     * @param array<int, array<string, mixed>>|mixed $rows
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
                $normalized[] = $row;
            }
        }

        return $normalized;
    }

    private function nullableStringFromRow(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
