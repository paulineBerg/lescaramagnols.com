<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Service;

use Caramagnols\PrivateApps\Documents\PrivateDocumentScanner;
use Caramagnols\PrivateApps\Documents\PrivateDocumentScanResult;

/**
 * Validation serveur d'un fichier candidat : extension autorisée, MIME réel,
 * signature magique, structure des conteneurs ZIP autorisés, absence de macro,
 * absence de chiffrement, tailles par famille, limite de pixels.
 * Ne se fie jamais au Content-Type du navigateur ni à l'extension seule.
 */
final class DocumentValidationService
{
    private const MAX_NAME_LENGTH = 255;
    private const TEXT_PROBE_BYTES = 65536;
    private const PDF_TRAILER_PROBE_BYTES = 16384;

    public function __construct(
        private readonly DocumentPolicy $policy,
        private readonly ?PrivateDocumentScanner $scanner = null
    ) {
    }

    /**
     * @param string $filePath Chemin lisible du fichier à inspecter (déjà côté serveur).
     * @param string $originalName Nom fourni par le client (métadonnée uniquement).
     */
    public function validateFile(string $filePath, string $originalName): DocumentValidationResult
    {
        $originalName = $this->normalizeOriginalName($originalName);
        if ($originalName === '') {
            return DocumentValidationResult::rejected('invalid_original_name');
        }

        if (!is_file($filePath) || !is_readable($filePath)) {
            return DocumentValidationResult::rejected('invalid_tmp_file', $originalName);
        }

        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!$this->policy->isAllowedExtension($extension)) {
            return DocumentValidationResult::rejected('forbidden_extension', $originalName);
        }

        $sizeBytes = filesize($filePath);
        if (!is_int($sizeBytes) || $sizeBytes <= 0) {
            return DocumentValidationResult::rejected('empty_file', $originalName);
        }

        $maxBytes = $this->policy->maxBytesForExtension($extension);
        if ($maxBytes <= 0 || $sizeBytes > $maxBytes) {
            return DocumentValidationResult::rejected('file_too_large', $originalName);
        }

        $mimeType = $this->detectMimeType($filePath);
        if ($mimeType === '' || !in_array($mimeType, $this->policy->allowedMimeTypesForExtension($extension), true)) {
            return DocumentValidationResult::rejected('mime_mismatch', $originalName);
        }

        if (!$this->hasValidMagicSignature($filePath, $extension)) {
            return DocumentValidationResult::rejected('invalid_signature', $originalName);
        }

        $structureError = $this->validateStructure($filePath, $extension);
        if ($structureError !== null) {
            return DocumentValidationResult::rejected($structureError, $originalName);
        }

        $scanStatus = $this->scanFile($filePath, $originalName, $mimeType);
        if ($scanStatus === PrivateDocumentScanResult::STATUS_INFECTED) {
            return DocumentValidationResult::rejected('infected', $originalName);
        }

        return DocumentValidationResult::accepted($originalName, $extension, $mimeType, $sizeBytes, $scanStatus);
    }

    public function normalizeOriginalName(string $name): string
    {
        $name = str_replace(["\r", "\n", "\t", '\\', '/'], ' ', $name);
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name));
        if ($name === '') {
            return '';
        }

        $name = trim((string) preg_replace('/\s+/', ' ', $name));
        if ($name === '' || preg_match('/\A\.\.?\z/', $name) === 1) {
            return '';
        }

        $name = (string) preg_replace('/\.{2,}/', '.', $name);
        if (strlen($name) > self::MAX_NAME_LENGTH) {
            return '';
        }

        return $name;
    }

    private function detectMimeType(string $filePath): string
    {
        if (!extension_loaded('fileinfo')) {
            return '';
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($filePath);

        return is_string($detected) ? strtolower(trim($detected)) : '';
    }

    private function hasValidMagicSignature(string $filePath, string $extension): bool
    {
        $head = (string) @file_get_contents($filePath, false, null, 0, 32);
        if ($head === '') {
            return false;
        }

        return match ($extension) {
            'pdf' => str_starts_with($head, '%PDF-'),
            'jpg', 'jpeg' => str_starts_with($head, "\xFF\xD8\xFF"),
            'png' => str_starts_with($head, "\x89PNG\r\n\x1a\n"),
            'webp' => str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP',
            'heic', 'heif' => $this->isHeifBrand($head),
            'tif', 'tiff' => str_starts_with($head, "II*\x00") || str_starts_with($head, "MM\x00*"),
            'docx', 'xlsx', 'odt', 'ods' => str_starts_with($head, 'PK'),
            'csv', 'txt' => true,
            default => false,
        };
    }

    private function isHeifBrand(string $head): bool
    {
        if (substr($head, 4, 4) !== 'ftyp') {
            return false;
        }

        $brand = substr($head, 8, 4);

        return in_array($brand, ['heic', 'heix', 'heim', 'heis', 'hevc', 'hevx', 'mif1', 'msf1'], true);
    }

    /**
     * @return string|null code d'erreur, null si la structure est valide
     */
    private function validateStructure(string $filePath, string $extension): ?string
    {
        if ($this->policy->isOfficeContainerExtension($extension)) {
            return $this->validateOfficeContainer($filePath, $extension);
        }

        if ($extension === 'pdf') {
            return $this->validatePdf($filePath);
        }

        if ($this->policy->isImageExtension($extension)) {
            return $this->validateImage($filePath, $extension);
        }

        if ($extension === 'csv' || $extension === 'txt') {
            return $this->validatePlainText($filePath);
        }

        return null;
    }

    private function validateOfficeContainer(string $filePath, string $extension): ?string
    {
        if (!class_exists(\ZipArchive::class)) {
            // Dégradation explicite : sans l'extension zip, impossible d'inspecter
            // le conteneur ; refus sûr plutôt qu'acceptation aveugle.
            return 'container_inspection_unavailable';
        }

        $zip = new \ZipArchive();
        if ($zip->open($filePath, \ZipArchive::RDONLY) !== true) {
            return 'unreadable_container';
        }

        try {
            $hasContentTypes = false;
            $declaredMime = '';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = (string) $zip->getNameIndex($i);
                $lowerEntry = strtolower($entryName);

                if (str_contains($lowerEntry, 'vbaproject')) {
                    return 'macro_detected';
                }

                if ($lowerEntry === 'encryptedpackage' || str_starts_with($lowerEntry, 'encryption')) {
                    return 'encrypted_document';
                }

                if ($lowerEntry === '[content_types].xml') {
                    $hasContentTypes = true;
                }

                if ($lowerEntry === 'mimetype') {
                    $declaredMime = strtolower(trim((string) $zip->getFromIndex($i, 256)));
                }
            }

            if (in_array($extension, ['docx', 'xlsx'], true) && !$hasContentTypes) {
                return 'invalid_container_structure';
            }

            if ($extension === 'odt' && $declaredMime !== 'application/vnd.oasis.opendocument.text') {
                return 'invalid_container_structure';
            }

            if ($extension === 'ods' && $declaredMime !== 'application/vnd.oasis.opendocument.spreadsheet') {
                return 'invalid_container_structure';
            }
        } finally {
            $zip->close();
        }

        return null;
    }

    private function validatePdf(string $filePath): ?string
    {
        $size = (int) filesize($filePath);
        $probeOffset = max(0, $size - self::PDF_TRAILER_PROBE_BYTES);
        $trailer = (string) @file_get_contents($filePath, false, null, $probeOffset, self::PDF_TRAILER_PROBE_BYTES);
        if ($trailer === '') {
            return 'unreadable_container';
        }

        if (str_contains($trailer, '/Encrypt')) {
            return 'encrypted_document';
        }

        return null;
    }

    private function validateImage(string $filePath, string $extension): ?string
    {
        $info = @getimagesize($filePath);
        if (!is_array($info)) {
            // HEIC/HEIF ne sont pas décodables par GD : signature déjà vérifiée,
            // la limite de pixels ne peut pas être contrôlée (capacité absente).
            if ($extension === 'heic' || $extension === 'heif') {
                return null;
            }

            return 'unreadable_image';
        }

        $width = is_numeric($info[0]) ? (int) $info[0] : 0;
        $height = is_numeric($info[1]) ? (int) $info[1] : 0;
        if ($width <= 0 || $height <= 0) {
            return 'unreadable_image';
        }

        if ($width * $height > $this->policy->maxImagePixels()) {
            return 'image_too_many_pixels';
        }

        return null;
    }

    private function validatePlainText(string $filePath): ?string
    {
        $probe = (string) @file_get_contents($filePath, false, null, 0, self::TEXT_PROBE_BYTES);
        if (str_contains($probe, "\x00")) {
            return 'binary_content_in_text';
        }

        return null;
    }

    private function scanFile(string $filePath, string $originalName, string $mimeType): string
    {
        if (!$this->scanner instanceof PrivateDocumentScanner || !$this->scanner->configured()) {
            return PrivateDocumentScanResult::cleanNoScanner()->status();
        }

        return $this->scanner->scan($filePath, $originalName, $mimeType)->status();
    }
}
