<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain;

final class AgencyLineMapping
{
    public function __construct(
        public readonly string $rawLabelPattern,
        public readonly string $sourceDocumentType,
        public readonly string $mappedCategory,
        public readonly string $direction,
        public readonly bool $recoverable,
        public readonly bool $taxDeductibleCandidate,
        public readonly bool $requiresReview,
        public readonly string $validationHint,
        public readonly float $confidence,
        public readonly bool $active = true,
        public readonly ?int $id = null
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromDatabaseRow(array $row): ?self
    {
        $rawLabelPattern = trim((string) ($row['raw_label_pattern'] ?? ''));
        $sourceDocumentType = trim((string) ($row['source_document_type'] ?? ''));
        $mappedCategory = trim((string) ($row['mapped_category'] ?? ''));
        $direction = trim((string) ($row['direction'] ?? ''));

        if ($rawLabelPattern === '' || $sourceDocumentType === '' || $mappedCategory === '' || $direction === '') {
            return null;
        }

        return new self(
            $rawLabelPattern,
            $sourceDocumentType,
            $mappedCategory,
            $direction,
            ((int) ($row['is_recoverable'] ?? 0)) === 1,
            ((int) ($row['is_tax_deductible_candidate'] ?? 0)) === 1,
            ((int) ($row['requires_review'] ?? 0)) === 1,
            trim((string) ($row['validation_hint'] ?? '')),
            is_numeric($row['confidence'] ?? null) ? round((float) $row['confidence'], 2) : 0.5,
            ((int) ($row['is_active'] ?? 1)) === 1,
            is_numeric($row['id'] ?? null) ? (int) $row['id'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'rawLabelPattern' => $this->rawLabelPattern,
            'sourceDocumentType' => $this->sourceDocumentType,
            'mappedCategory' => $this->mappedCategory,
            'direction' => $this->direction,
            'recoverable' => $this->recoverable,
            'taxDeductibleCandidate' => $this->taxDeductibleCandidate,
            'requiresReview' => $this->requiresReview,
            'validationHint' => $this->validationHint,
            'confidence' => $this->confidence,
            'active' => $this->active,
        ];
    }
}
