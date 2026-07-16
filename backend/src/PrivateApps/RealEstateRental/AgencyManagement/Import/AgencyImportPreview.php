<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import;

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyImportIssue;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Parser\AgencyParserResult;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\ExtractedTextResult;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\PdfMetadata;

final class AgencyImportPreview
{
    /**
     * @param array<int, AgencyImportIssue> $issues
     */
    public function __construct(
        public readonly string $sourcePath,
        public readonly string $filename,
        public readonly ?string $mimeType,
        public readonly ?int $fileSize,
        public readonly ?string $sha256,
        public readonly ?PdfMetadata $pdfMetadata,
        public readonly ExtractedTextResult $textExtraction,
        public readonly ClassifiedAgencyDocument $classification,
        public readonly ?AgencyParserResult $parserResult,
        public readonly string $maskedTextPreview,
        public readonly array $issues = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sourcePathHash' => hash('sha256', $this->sourcePath),
            'filename' => $this->filename,
            'mimeType' => $this->mimeType,
            'fileSize' => $this->fileSize,
            'sha256' => $this->sha256,
            'pdfMetadata' => $this->pdfMetadata === null ? null : [
                'pages' => $this->pdfMetadata->pages,
                'fileSize' => $this->pdfMetadata->fileSize,
                'pdfVersion' => $this->pdfMetadata->pdfVersion,
                'encrypted' => $this->pdfMetadata->encrypted,
                'title' => $this->pdfMetadata->title,
                'creator' => $this->pdfMetadata->creator,
                'producer' => $this->pdfMetadata->producer,
                'status' => $this->pdfMetadata->status,
            ],
            'textExtraction' => [
                'status' => $this->textExtraction->status,
                'textLength' => mb_strlen($this->textExtraction->text, 'UTF-8'),
                'exitCode' => $this->textExtraction->exitCode,
            ],
            'classification' => $this->classification->toArray(),
            'parserResult' => $this->parserResult?->toArray(),
            'maskedTextPreview' => $this->maskedTextPreview,
            'issues' => array_map(static fn (AgencyImportIssue $issue): array => $issue->toArray(), $this->issues),
        ];
    }
}
