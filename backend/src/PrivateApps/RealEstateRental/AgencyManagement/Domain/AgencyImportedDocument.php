<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain;

final class AgencyImportedDocument
{
    public function __construct(
        public readonly int $id,
        public readonly int $batchId,
        public readonly ?string $privateDocumentId,
        public readonly ?string $storagePath,
        public readonly string $filename,
        public readonly ?string $mimeType,
        public readonly ?int $fileSize,
        public readonly string $sha256,
        public readonly string $detectedDocumentType,
        public readonly ?string $detectedAgency,
        public readonly ?string $parserProfile,
        public readonly float $classificationConfidence,
        public readonly string $textExtractionStatus,
        public readonly bool $containsSensitiveData,
        public readonly string $reviewStatus,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromDatabaseRow(array $row): ?self
    {
        $id = is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0;
        $batchId = is_numeric($row['batch_id'] ?? null) ? (int) $row['batch_id'] : 0;
        $filename = trim((string) ($row['filename'] ?? ''));
        $sha256 = strtolower(trim((string) ($row['sha256'] ?? '')));
        $detectedDocumentType = trim((string) ($row['detected_document_type'] ?? ''));
        $textExtractionStatus = trim((string) ($row['text_extraction_status'] ?? ''));
        $reviewStatus = trim((string) ($row['review_status'] ?? ''));

        if (
            $id <= 0
            || $batchId <= 0
            || $filename === ''
            || !self::isSha256($sha256)
            || $detectedDocumentType === ''
            || $textExtractionStatus === ''
            || $reviewStatus === ''
        ) {
            return null;
        }

        return new self(
            $id,
            $batchId,
            self::nullableString($row['private_document_id'] ?? null),
            self::nullableString($row['storage_path'] ?? null),
            $filename,
            self::nullableString($row['mime_type'] ?? null),
            is_numeric($row['file_size'] ?? null) ? (int) $row['file_size'] : null,
            $sha256,
            $detectedDocumentType,
            self::nullableString($row['detected_agency'] ?? null),
            self::nullableString($row['parser_profile'] ?? null),
            is_numeric($row['classification_confidence'] ?? null) ? round((float) $row['classification_confidence'], 2) : 0.0,
            $textExtractionStatus,
            ((int) ($row['contains_sensitive_data'] ?? 0)) === 1,
            $reviewStatus,
            self::nullableString($row['created_at'] ?? null),
            self::nullableString($row['updated_at'] ?? null)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'batchId' => $this->batchId,
            'privateDocumentId' => $this->privateDocumentId,
            'storagePath' => $this->storagePath,
            'filename' => $this->filename,
            'mimeType' => $this->mimeType,
            'fileSize' => $this->fileSize,
            'sha256' => $this->sha256,
            'detectedDocumentType' => $this->detectedDocumentType,
            'detectedAgency' => $this->detectedAgency,
            'parserProfile' => $this->parserProfile,
            'classificationConfidence' => $this->classificationConfidence,
            'textExtractionStatus' => $this->textExtractionStatus,
            'containsSensitiveData' => $this->containsSensitiveData,
            'reviewStatus' => $this->reviewStatus,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    private static function isSha256(string $value): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/', $value) === 1;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
