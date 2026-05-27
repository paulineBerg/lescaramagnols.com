<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import;

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyDocumentType;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyImportIssue;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Parser\AgencyParserInterface;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Parser\AgencyParserResult;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Parser\AsgManagementStatementParser;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Parser\IcsManagementReportParser;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\DocumentTextExtractorInterface;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\ExtractedTextResult;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\PdfMetadata;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\PdfMetadataExtractor;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\PopplerPdfTextExtractor;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Service\AgencyStatementValidationService;

final class AgencyImportPreviewService
{
    private DocumentTextExtractorInterface $textExtractor;
    private AgencyDocumentClassifier $classifier;
    private AgencySensitiveDataMasker $sensitiveDataMasker;
    private ?PdfMetadataExtractor $pdfMetadataExtractor;
    private AgencyStatementValidationService $validationService;

    /**
     * @var array<int, AgencyParserInterface>
     */
    private array $parsers;

    /**
     * @param iterable<AgencyParserInterface> $parsers
     */
    public function __construct(
        ?DocumentTextExtractorInterface $textExtractor = null,
        ?AgencyDocumentClassifier $classifier = null,
        ?AgencySensitiveDataMasker $sensitiveDataMasker = null,
        ?PdfMetadataExtractor $pdfMetadataExtractor = null,
        iterable $parsers = [],
        ?AgencyStatementValidationService $validationService = null
    ) {
        $this->textExtractor = $textExtractor ?? new PopplerPdfTextExtractor();
        $this->classifier = $classifier ?? new AgencyDocumentClassifier();
        $this->sensitiveDataMasker = $sensitiveDataMasker ?? new AgencySensitiveDataMasker();
        $this->pdfMetadataExtractor = $pdfMetadataExtractor ?? new PdfMetadataExtractor();
        $this->validationService = $validationService ?? new AgencyStatementValidationService();
        $this->parsers = $this->normalizeParsers($parsers);
    }

    public function preview(string $path, ?string $filename = null, ?string $mimeType = null): AgencyImportPreview
    {
        $filename = $filename !== null && trim($filename) !== '' ? trim($filename) : basename($path);
        $mimeType = $mimeType !== null && trim($mimeType) !== '' ? trim($mimeType) : $this->detectMimeType($path);
        $fileSize = is_file($path) ? filesize($path) : false;
        $sha256 = is_file($path) && is_readable($path) ? hash_file('sha256', $path) : false;
        $issues = [];

        if (!is_file($path) || !is_readable($path)) {
            $extraction = new ExtractedTextResult(
                ExtractedTextResult::STATUS_FAILED,
                '',
                null,
                'Unreadable document.'
            );
            $classification = new ClassifiedAgencyDocument(AgencyDocumentType::UNKNOWN, '', 0.0);
            $issues[] = new AgencyImportIssue(
                'unreadable_document',
                AgencyImportIssue::SEVERITY_ERROR,
                'Document illisible ou absent.'
            );

            return new AgencyImportPreview(
                $path,
                $filename,
                $mimeType,
                is_int($fileSize) ? $fileSize : null,
                is_string($sha256) ? $sha256 : null,
                null,
                $extraction,
                $classification,
                null,
                '',
                $issues
            );
        }

        $metadata = $this->extractPdfMetadata($path, $mimeType);
        if (!$this->textExtractor->supports($path, $mimeType ?? '')) {
            $extraction = new ExtractedTextResult(
                ExtractedTextResult::STATUS_UNSUPPORTED,
                '',
                null,
                'Unsupported document.'
            );
            $classification = new ClassifiedAgencyDocument(AgencyDocumentType::UNKNOWN, '', 0.0);
            $issues[] = new AgencyImportIssue(
                'unsupported_document',
                AgencyImportIssue::SEVERITY_WARNING,
                'Format non pris en charge par le parseur texte actuel.'
            );

            return new AgencyImportPreview(
                $path,
                $filename,
                $mimeType,
                is_int($fileSize) ? $fileSize : null,
                is_string($sha256) ? $sha256 : null,
                $metadata,
                $extraction,
                $classification,
                null,
                '',
                $issues
            );
        }

        $extraction = $this->textExtractor->extract($path);
        $classification = $this->classifier->classify($extraction->text, $filename);
        $parserResult = $this->parse($classification, $extraction->text, $metadata);
        $issues = array_merge($issues, $this->issuesForExtraction($extraction));

        if ($extraction->hasUsefulText() && $classification->isKnown() && $parserResult === null) {
            $issues[] = new AgencyImportIssue(
                'parser_not_available',
                AgencyImportIssue::SEVERITY_WARNING,
                'Document reconnu, mais aucun parseur detaille disponible.'
            );
        }

        if ($parserResult !== null) {
            $issues = array_merge($issues, $parserResult->issues);
            $issues = array_merge($issues, $this->validationService->validate($classification, $parserResult));
        }

        return new AgencyImportPreview(
            $path,
            $filename,
            $mimeType,
            is_int($fileSize) ? $fileSize : null,
            is_string($sha256) ? $sha256 : null,
            $metadata,
            $extraction,
            $classification,
            $parserResult,
            $this->maskedPreview($extraction->text),
            $issues
        );
    }

    /**
     * @param iterable<AgencyParserInterface> $parsers
     * @return array<int, AgencyParserInterface>
     */
    private function normalizeParsers(iterable $parsers): array
    {
        $normalized = [];
        foreach ($parsers as $parser) {
            $normalized[] = $parser;
        }

        if ($normalized !== []) {
            return $normalized;
        }

        return [
            new AsgManagementStatementParser(),
            new IcsManagementReportParser(),
        ];
    }

    private function detectMimeType(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($path);

        return is_string($mimeType) && $mimeType !== '' ? $mimeType : null;
    }

    private function extractPdfMetadata(string $path, ?string $mimeType): ?PdfMetadata
    {
        if ($this->pdfMetadataExtractor === null || !$this->isPdf($path, $mimeType)) {
            return null;
        }

        return $this->pdfMetadataExtractor->extract($path);
    }

    private function isPdf(string $path, ?string $mimeType): bool
    {
        return strtolower((string) $mimeType) === 'application/pdf'
            || strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
    }

    private function parse(
        ClassifiedAgencyDocument $classification,
        string $text,
        ?PdfMetadata $metadata
    ): ?AgencyParserResult {
        if (!$classification->isKnown() || mb_strlen(trim($text), 'UTF-8') < 20) {
            return null;
        }

        foreach ($this->parsers as $parser) {
            if ($parser->supports($classification)) {
                return $parser->parse($text, ['pdfMetadata' => $metadata?->toArray()]);
            }
        }

        return null;
    }

    /**
     * @return array<int, AgencyImportIssue>
     */
    private function issuesForExtraction(ExtractedTextResult $extraction): array
    {
        if ($extraction->status === ExtractedTextResult::STATUS_EXTRACTED) {
            return [];
        }

        if ($extraction->status === ExtractedTextResult::STATUS_NEEDS_OCR_OR_MANUAL_ENTRY) {
            return [
                new AgencyImportIssue(
                    'ocr_or_manual_entry_required',
                    AgencyImportIssue::SEVERITY_WARNING,
                    'Texte insuffisant : document a envoyer en OCR ou saisie manuelle.'
                ),
            ];
        }

        return [
            new AgencyImportIssue(
                'text_extraction_failed',
                AgencyImportIssue::SEVERITY_ERROR,
                'Extraction texte impossible.'
            ),
        ];
    }

    private function maskedPreview(string $text): string
    {
        $masked = $this->sensitiveDataMasker->mask($text);
        if (mb_strlen($masked, 'UTF-8') <= 4000) {
            return $masked;
        }

        return mb_substr($masked, 0, 4000, 'UTF-8');
    }
}
