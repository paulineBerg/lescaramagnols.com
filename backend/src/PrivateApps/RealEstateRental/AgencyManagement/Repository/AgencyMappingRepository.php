<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Repository;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyDocumentType;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyLineMapping;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import\DefaultAgencyLineMappings;
use PDO;

final class AgencyMappingRepository
{
    private bool $schemaReady = false;

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function table(): string
    {
        return $this->database->table('rental_agency_line_mappings');
    }

    public function seedDefaults(): int
    {
        $count = 0;
        foreach (DefaultAgencyLineMappings::all() as $mapping) {
            if ($this->upsert($mapping)) {
                ++$count;
            }
        }

        return $count;
    }

    public function upsert(AgencyLineMapping $mapping): bool
    {
        if (!$this->isValidMapping($mapping)) {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`raw_label_pattern`, `source_document_type`, `mapped_category`, `direction`,
                         `is_recoverable`, `is_tax_deductible_candidate`, `requires_review`,
                         `validation_hint`, `confidence`, `is_active`)
                     VALUES
                        (:raw_label_pattern, :source_document_type, :mapped_category, :direction,
                         :is_recoverable, :is_tax_deductible_candidate, :requires_review,
                         :validation_hint, :confidence, :is_active)
                     ON DUPLICATE KEY UPDATE
                         `mapped_category` = VALUES(`mapped_category`),
                         `direction` = VALUES(`direction`),
                         `is_recoverable` = VALUES(`is_recoverable`),
                         `is_tax_deductible_candidate` = VALUES(`is_tax_deductible_candidate`),
                         `requires_review` = VALUES(`requires_review`),
                         `validation_hint` = VALUES(`validation_hint`),
                         `confidence` = VALUES(`confidence`),
                         `is_active` = VALUES(`is_active`)',
                    $this->table()
                )
            );

            return $statement->execute([
                'raw_label_pattern' => $this->normalizeText($mapping->rawLabelPattern, 120),
                'source_document_type' => $this->normalizeSourceDocumentType($mapping->sourceDocumentType),
                'mapped_category' => $this->normalizeText($mapping->mappedCategory, 80),
                'direction' => $this->normalizeDirection($mapping->direction),
                'is_recoverable' => $mapping->recoverable ? 1 : 0,
                'is_tax_deductible_candidate' => $mapping->taxDeductibleCandidate ? 1 : 0,
                'requires_review' => $mapping->requiresReview ? 1 : 0,
                'validation_hint' => $this->normalizeText($mapping->validationHint, 255),
                'confidence' => max(0.0, min(1.0, $mapping->confidence)),
                'is_active' => $mapping->active ? 1 : 0,
            ]);
        } catch (\Throwable) {
            return false;
        }
    }

    public function findForLabel(string $rawLabel, string $sourceDocumentType = AgencyDocumentType::UNKNOWN): ?AgencyLineMapping
    {
        $rawLabel = $this->normalizeComparable($rawLabel);
        if ($rawLabel === '') {
            return null;
        }

        $sourceDocumentType = $this->normalizeSourceDocumentType($sourceDocumentType);
        foreach ($this->listActive() as $mapping) {
            if (
                $mapping->sourceDocumentType !== AgencyDocumentType::UNKNOWN
                && $mapping->sourceDocumentType !== $sourceDocumentType
            ) {
                continue;
            }

            if (str_contains($rawLabel, $this->normalizeComparable($mapping->rawLabelPattern))) {
                return $mapping;
            }
        }

        return null;
    }

    /**
     * @return array<int, AgencyLineMapping>
     */
    public function listActive(): array
    {
        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s`
                     WHERE `is_active` = 1
                     ORDER BY `priority` ASC, LENGTH(`raw_label_pattern`) DESC, `id` ASC',
                    $this->table()
                )
            );
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($rows)) {
            return [];
        }

        $mappings = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $mapping = AgencyLineMapping::fromDatabaseRow($row);
            if ($mapping instanceof AgencyLineMapping) {
                $mappings[] = $mapping;
            }
        }

        return $mappings;
    }

    public function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        $this->database->ensureReady();
        $this->database->pdo()->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `raw_label_pattern` VARCHAR(120) NOT NULL,
                    `source_document_type` VARCHAR(80) NOT NULL DEFAULT "unknown",
                    `mapped_category` VARCHAR(80) NOT NULL,
                    `direction` ENUM("income", "expense", "transfer", "liability", "balance", "neutral") NOT NULL DEFAULT "neutral",
                    `is_recoverable` TINYINT(1) NOT NULL DEFAULT 0,
                    `is_tax_deductible_candidate` TINYINT(1) NOT NULL DEFAULT 0,
                    `requires_review` TINYINT(1) NOT NULL DEFAULT 1,
                    `validation_hint` VARCHAR(255) NULL,
                    `confidence` DECIMAL(4,2) NOT NULL DEFAULT 0.50,
                    `priority` SMALLINT NOT NULL DEFAULT 100,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uq_rental_agency_line_mapping` (`raw_label_pattern`, `source_document_type`),
                    KEY `idx_rental_agency_line_mappings_category` (`mapped_category`, `is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->table()
            )
        );

        $this->schemaReady = true;
    }

    private function isValidMapping(AgencyLineMapping $mapping): bool
    {
        return $this->normalizeText($mapping->rawLabelPattern, 120) !== ''
            && $this->normalizeText($mapping->mappedCategory, 80) !== ''
            && $this->normalizeDirection($mapping->direction) !== ''
            && $mapping->confidence >= 0.0
            && $mapping->confidence <= 1.0;
    }

    private function normalizeSourceDocumentType(string $value): string
    {
        $value = $this->normalizeText($value, 80);
        if ($value === '') {
            return AgencyDocumentType::UNKNOWN;
        }

        return in_array($value, AgencyDocumentType::all(), true) ? $value : AgencyDocumentType::UNKNOWN;
    }

    private function normalizeDirection(string $value): string
    {
        $value = $this->normalizeText($value, 20);
        return in_array($value, ['income', 'expense', 'transfer', 'liability', 'balance', 'neutral'], true)
            ? $value
            : '';
    }

    private function normalizeText(string $value, int $maxLength): string
    {
        $value = trim(strip_tags($value));
        if ($value === '') {
            return '';
        }

        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    private function normalizeComparable(string $value): string
    {
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
            '’' => "'",
        ]);

        return preg_replace('/\s+/u', ' ', $value) ?: '';
    }
}
