<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents;

use Caramagnols\Logging\AppEventLogger;

final class PrivateDocumentStorage
{
    private const DOCUMENT_ID_RANDOM_BYTES = 16;
    private const MAX_DOCUMENT_ID_LENGTH = 64;
    private const MAX_EXTENSION_LENGTH = 16;
    private const MAX_NAME_LENGTH = 255;
    private const DIRECTORY_PERMISSIONS = 0700;
    private const FILE_PERMISSIONS = 0600;
    private const DEFAULT_MAX_UPLOAD_BYTES = 20 * 1024 * 1024;

    private const DEFAULT_ALLOWED_EXTENSIONS = [
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'txt',
    ];

    private const DEFAULT_ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'text/plain',
    ];

    private string $storageDirectoryPath;
    private string $uploadsDirectoryPath;
    private string $exportsDirectoryPath;
    private readonly int $directoryPermissions;
    private readonly int $filePermissions;

    /** @var array<int, string> */
    private array $allowedExtensions;
    /** @var array<int, string> */
    private array $allowedMimeTypes;
    private readonly int $maxUploadBytes;
    private ?string $uploadError = null;

    /**
     * Mode legacy : lecture depuis l'ancien chemin, écriture bloquée.
     * Activé automatiquement si le nouveau chemin n'existe pas mais que l'ancien existe.
     */
    private bool $legacyMode = false;

    /**
     * Chemin legacy pour la compatibilité pendant la migration.
     */
    private const LEGACY_STORAGE_ROOT = ROOT_PATH . '/private';
    private const LEGACY_STORAGE_DIR = 'storage';
    private const LEGACY_UPLOADS_DIR = 'uploads';
    private const LEGACY_EXPORTS_DIR = 'exports';

    public function __construct(
        string $storageRootPath,
        string $storageDirectory,
        string $uploadsDirectory,
        string $exportsDirectory,
        int $maxUploadBytes = self::DEFAULT_MAX_UPLOAD_BYTES,
        array $allowedExtensions = self::DEFAULT_ALLOWED_EXTENSIONS,
        array $allowedMimeTypes = self::DEFAULT_ALLOWED_MIME_TYPES,
        private readonly ?AppEventLogger $eventLogger = null,
        int $directoryPermissions = self::DIRECTORY_PERMISSIONS,
        int $filePermissions = self::FILE_PERMISSIONS,
        private readonly ?PrivateDocumentScanner $scanner = null
    ) {
        $storageRootPath = trim($storageRootPath);
        if ($storageRootPath === '') {
            $storageRootPath = ROOT_PATH . '/private';
        }

        $storageDirectory = $this->sanitizeDirectoryName($storageDirectory, 'storage');
        $uploadsDirectory = $this->sanitizeDirectoryName($uploadsDirectory, 'uploads');
        $exportsDirectory = $this->sanitizeDirectoryName($exportsDirectory, 'exports');

        // Tentative de résoudre le chemin principal
        $this->storageDirectoryPath = $this->normalizeDirectoryPath($storageRootPath . '/' . $storageDirectory);
        $this->uploadsDirectoryPath = $this->normalizeDirectoryPath($this->storageDirectoryPath . '/' . $uploadsDirectory);
        $this->exportsDirectoryPath = $this->normalizeDirectoryPath($this->storageDirectoryPath . '/' . $exportsDirectory);

        // Vérifier si le chemin principal existe, sinon essayer le fallback legacy
        $this->checkLegacyFallback(
            $storageRootPath,
            $storageDirectory,
            $uploadsDirectory,
            $exportsDirectory
        );
        $this->maxUploadBytes = max(1, $maxUploadBytes);
        $this->directoryPermissions = $this->normalizePermissions(
            $directoryPermissions,
            self::DIRECTORY_PERMISSIONS,
            0700
        );
        $this->filePermissions = $this->normalizePermissions(
            $filePermissions,
            self::FILE_PERMISSIONS,
            0600
        );
        $this->allowedExtensions = $this->normalizeExtensions($allowedExtensions);
        $this->allowedMimeTypes = $this->normalizeMimeTypes($allowedMimeTypes);

        if ($this->allowedExtensions === []) {
            $this->allowedExtensions = self::DEFAULT_ALLOWED_EXTENSIONS;
        }

        if ($this->allowedMimeTypes === []) {
            $this->allowedMimeTypes = self::DEFAULT_ALLOWED_MIME_TYPES;
        }

        $this->ensureStorageDirectories();
    }

    /**
     * Vérifie si le chemin principal existe, sinon bascule vers le chemin legacy.
     * Le mode legacy permet la lecture mais bloque l'écriture.
     */
    private function checkLegacyFallback(
        string $storageRootPath,
        string $storageDirectory,
        string $uploadsDirectory,
        string $exportsDirectory
    ): void {
        $appEnv = function_exists('app_config') ? strtolower((string) app_config('env', '')) : '';
        if (!in_array($appEnv, ['production', 'prod', 'live'], true)) {
            return;
        }

        // Chemin principal pour uploads
        $mainUploadsPath = $this->normalizeDirectoryPath(
            $storageRootPath . '/' . $this->sanitizeDirectoryName($storageDirectory, 'storage') . '/' . $uploadsDirectory
        );

        // Chemin legacy pour uploads
        $legacyStorageRoot = self::LEGACY_STORAGE_ROOT;
        $legacyStorageDir = self::LEGACY_STORAGE_DIR;
        $legacyUploadsDir = self::LEGACY_UPLOADS_DIR;
        $legacyUploadsPath = $this->normalizeDirectoryPath(
            $legacyStorageRoot . '/' . $legacyStorageDir . '/' . $legacyUploadsDir
        );

        // Si le chemin principal n'existe pas mais que le legacy existe, basculer
        if (!is_dir($mainUploadsPath) && is_dir($legacyUploadsPath)) {
            $this->storageDirectoryPath = $this->normalizeDirectoryPath(
                $legacyStorageRoot . '/' . $legacyStorageDir
            );
            $this->uploadsDirectoryPath = $this->normalizeDirectoryPath(
                $this->storageDirectoryPath . '/' . $legacyUploadsDir
            );
            $this->exportsDirectoryPath = $this->normalizeDirectoryPath(
                $this->storageDirectoryPath . '/' . self::LEGACY_EXPORTS_DIR
            );
            $this->legacyMode = true;

            $this->logEvent('private.documents.legacy_mode_activated', [
                'main_path' => $mainUploadsPath,
                'legacy_path' => $legacyUploadsPath,
            ], 'warning');
        }
    }

    /**
     * Retourne true si on est en mode legacy (lecture seule).
     */
    public function isLegacyMode(): bool
    {
        return $this->legacyMode;
    }

    public static function fromAppConfig(?AppEventLogger $eventLogger = null): self
    {
        $documentConfig = is_array(app_config('private.documents', [])) ? (array) app_config('private.documents') : [];
        $storageRootPath = is_string($documentConfig['storage_root_path'] ?? null)
            ? trim((string) $documentConfig['storage_root_path'])
            : ROOT_PATH . '/private';
        $storageDirectory = is_string($documentConfig['storage_directory'] ?? null)
            ? (string) $documentConfig['storage_directory']
            : 'storage';
        $uploadsDirectory = is_string($documentConfig['uploads_directory'] ?? null)
            ? (string) $documentConfig['uploads_directory']
            : 'uploads';
        $exportsDirectory = is_string($documentConfig['exports_directory'] ?? null)
            ? (string) $documentConfig['exports_directory']
            : 'exports';
        $maxUploadBytes = is_numeric($documentConfig['max_upload_bytes'] ?? null)
            ? (int) $documentConfig['max_upload_bytes']
            : self::DEFAULT_MAX_UPLOAD_BYTES;
        $directoryPermissions = is_numeric($documentConfig['directory_permissions'] ?? null)
            ? (int) $documentConfig['directory_permissions']
            : self::DIRECTORY_PERMISSIONS;
        $filePermissions = is_numeric($documentConfig['file_permissions'] ?? null)
            ? (int) $documentConfig['file_permissions']
            : self::FILE_PERMISSIONS;
        $scanCommand = is_string($documentConfig['scan_command'] ?? null)
            ? trim((string) $documentConfig['scan_command'])
            : '';
        $scanTimeoutSeconds = is_numeric($documentConfig['scan_timeout_seconds'] ?? null)
            ? (int) $documentConfig['scan_timeout_seconds']
            : 30;

        return new self(
            $storageRootPath,
            $storageDirectory,
            $uploadsDirectory,
            $exportsDirectory,
            $maxUploadBytes,
            is_array($documentConfig['allowed_extensions'] ?? null) ? $documentConfig['allowed_extensions'] : [],
            is_array($documentConfig['allowed_mime_types'] ?? null) ? $documentConfig['allowed_mime_types'] : [],
            $eventLogger,
            $directoryPermissions,
            $filePermissions,
            $scanCommand !== '' ? new PrivateDocumentScanner($scanCommand, $scanTimeoutSeconds) : null
        );
    }

    public function uploadError(): ?string
    {
        return $this->uploadError;
    }

    public function storageDirectory(): string
    {
        return $this->storageDirectoryPath;
    }

    public function uploadsDirectory(): string
    {
        return $this->uploadsDirectoryPath;
    }

    public function exportsDirectory(): string
    {
        return $this->exportsDirectoryPath;
    }

    public function maxUploadBytes(): int
    {
        return $this->maxUploadBytes;
    }

    public function allowedExtensions(): array
    {
        return $this->allowedExtensions;
    }

    public function allowedMimeTypes(): array
    {
        return $this->allowedMimeTypes;
    }

    public function generateDocumentId(): string
    {
        try {
            $random = bin2hex(random_bytes(self::DOCUMENT_ID_RANDOM_BYTES));
            $candidate = trim($random);
            if ($candidate === '') {
                return '';
            }

            return substr($candidate, 0, self::MAX_DOCUMENT_ID_LENGTH);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @param array{name:string,tmp_name:string,size:int|string,error:int, type?:string} $uploadedFile
     * @return array{tmpPath:string,originalName:string,extension:string,mimeType:string,sizeBytes:int}|null
     */
    public function validateUploadedFile(array $uploadedFile): ?array
    {
        $this->uploadError = null;

        $errorCode = is_numeric($uploadedFile['error'] ?? null)
            ? (int) $uploadedFile['error']
            : 0;
        if ($errorCode !== UPLOAD_ERR_OK) {
            $this->uploadError = 'upload_error';
            return null;
        }

        $rawOriginalName = is_string($uploadedFile['name'] ?? null) ? (string) $uploadedFile['name'] : '';
        $originalName = $this->normalizeOriginalName($rawOriginalName);
        if ($originalName === '') {
            $this->uploadError = 'invalid_original_name';
            return null;
        }

        $tmpName = is_string($uploadedFile['tmp_name'] ?? null) ? trim((string) $uploadedFile['tmp_name']) : '';
        if ($tmpName === '' || !is_file($tmpName) || !is_readable($tmpName)) {
            $this->uploadError = 'invalid_tmp_file';
            return null;
        }

        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!$this->isAllowedExtension($extension)) {
            $this->uploadError = 'invalid_extension';
            return null;
        }

        $sizeBytes = is_numeric($uploadedFile['size'] ?? null) ? (int) $uploadedFile['size'] : 0;
        $currentSize = @filesize($tmpName);
        if (is_int($currentSize)) {
            $sizeBytes = $currentSize;
        }

        if ($sizeBytes <= 0 || $sizeBytes > $this->maxUploadBytes) {
            $this->uploadError = 'invalid_size';
            return null;
        }

        $mimeType = $this->detectMimeType($tmpName);
        if ($mimeType === '') {
            $providedMimeType = is_string($uploadedFile['type'] ?? null) ? trim((string) $uploadedFile['type']) : '';
            $mimeType = strtolower($providedMimeType);
        }
        if (!$this->isAllowedMimeType($mimeType)) {
            $this->uploadError = 'invalid_mime';
            return null;
        }

        return [
            'tmpPath' => $tmpName,
            'originalName' => $originalName,
            'extension' => $extension,
            'mimeType' => $mimeType,
            'sizeBytes' => $sizeBytes,
        ];
    }

    /**
     * @param array{tmpPath:string,originalName:string,extension:string,mimeType:string,sizeBytes:int} $metadata
     * @return array{documentId:string,storagePath:string,originalName:string,extension:string,mimeType:string,sizeBytes:int,scanStatus:string,scanExitCode:int|null,scanDurationMs:int|null,scanError:string,scannedAt:string|null}|null
     */
    public function storeUploadedFile(array $metadata, string $documentId): ?array
    {
        if ($this->legacyMode) {
            $this->uploadError = 'legacy_mode_readonly';
            return null;
        }

        $documentId = $this->normalizeDocumentId($documentId);
        if ($documentId === '') {
            $this->uploadError = 'invalid_document_id';
            return null;
        }

        $tmpPath = is_string($metadata['tmpPath'] ?? null) ? trim((string) $metadata['tmpPath']) : '';
        if ($tmpPath === '' || !is_file($tmpPath) || !is_readable($tmpPath)) {
            $this->uploadError = 'invalid_tmp_file';
            return null;
        }

        $originalName = $this->normalizeOriginalName((string) ($metadata['originalName'] ?? ''));
        if ($originalName === '') {
            $this->uploadError = 'invalid_original_name';
            return null;
        }

        $extension = is_string($metadata['extension'] ?? null) ? strtolower(trim((string) $metadata['extension'])) : '';
        if ($extension === '' || !preg_match('/\A[a-z0-9]{1,' . self::MAX_EXTENSION_LENGTH . '}\z/', $extension)) {
            $this->uploadError = 'invalid_extension';
            return null;
        }

        if (!$this->isAllowedExtension($extension)) {
            $this->uploadError = 'invalid_extension';
            return null;
        }

        $mimeType = strtolower(trim((string) ($metadata['mimeType'] ?? '')));
        if (!$this->isAllowedMimeType($mimeType)) {
            $this->uploadError = 'invalid_mime';
            return null;
        }

        $sizeBytes = is_numeric($metadata['sizeBytes'] ?? null) ? (int) $metadata['sizeBytes'] : 0;
        if ($sizeBytes <= 0 || $sizeBytes > $this->maxUploadBytes) {
            $this->uploadError = 'invalid_size';
            return null;
        }

        $storagePath = $this->buildStoragePath($documentId, $extension);
        if (!preg_match('/\A[a-z0-9._-]+\/[a-z0-9]{2}\/[a-z0-9]{2}\/[a-z0-9._-]+\.[a-z0-9]{1,16}\z/', $storagePath)) {
            $this->uploadError = 'invalid_storage_path';
            return null;
        }

        $absolutePath = $this->absolutePath($storagePath);
        if ($absolutePath === null) {
            $this->uploadError = 'invalid_storage_path';
            return null;
        }

        $targetDir = dirname($absolutePath);
        if (!is_dir($targetDir) && !@mkdir($targetDir, $this->directoryPermissions, true) && !is_dir($targetDir)) {
            $this->uploadError = 'storage_unavailable';
            return null;
        }
        $targetParentDir = dirname($targetDir);
        if (str_starts_with($targetParentDir, $this->uploadsDirectoryPath)) {
            @chmod($targetParentDir, $this->directoryPermissions);
        }
        @chmod($targetDir, $this->directoryPermissions);

        $moved = false;
        if (is_uploaded_file($tmpPath)) {
            $moved = @move_uploaded_file($tmpPath, $absolutePath);
        }

        if (!$moved) {
            $moved = @copy($tmpPath, $absolutePath);
        }

        if (!$moved || !is_file($absolutePath)) {
            $this->uploadError = 'write_failed';
            return null;
        }

        @chmod($absolutePath, $this->filePermissions);
        $scanResult = $this->scanStoredFile($absolutePath, $originalName, $mimeType);
        $this->uploadError = null;
        $this->logEvent('private.documents.uploaded', [
            'document_id' => $documentId,
            'size_bytes' => $sizeBytes,
            'scan_status' => $scanResult->status(),
        ]);

        return [
            'documentId' => $documentId,
            'storagePath' => $storagePath,
            'originalName' => $originalName,
            'extension' => $extension,
            'mimeType' => $mimeType,
            'sizeBytes' => $sizeBytes,
            'scanStatus' => $scanResult->status(),
            'scanExitCode' => $scanResult->exitCode(),
            'scanDurationMs' => $scanResult->durationMs(),
            'scanError' => $scanResult->error(),
            'scannedAt' => $scanResult->scannedAt(),
        ];
    }

    /**
     * @return array{documentId:string,storagePath:string,originalName:string,extension:string,mimeType:string,sizeBytes:int,sha256Hash:string}|null
     */
    public function storeGeneratedDocument(
        string $content,
        string $documentId,
        string $originalName,
        string $extension = 'pdf',
        string $mimeType = 'application/pdf'
    ): ?array {
        $this->uploadError = null;

        if ($this->legacyMode) {
            $this->uploadError = 'legacy_mode_readonly';
            return null;
        }

        $documentId = $this->normalizeDocumentId($documentId);
        $originalName = $this->normalizeOriginalName($originalName);
        $extension = strtolower(trim($extension));
        $mimeType = strtolower(trim($mimeType));
        $sizeBytes = strlen($content);

        if ($documentId === '') {
            $this->uploadError = 'invalid_document_id';
            return null;
        }
        if ($originalName === '') {
            $this->uploadError = 'invalid_original_name';
            return null;
        }
        if ($extension === '' || !preg_match('/\A[a-z0-9]{1,' . self::MAX_EXTENSION_LENGTH . '}\z/', $extension)) {
            $this->uploadError = 'invalid_extension';
            return null;
        }
        if (!$this->isAllowedExtension($extension)) {
            $this->uploadError = 'invalid_extension';
            return null;
        }
        if (!$this->isAllowedMimeType($mimeType)) {
            $this->uploadError = 'invalid_mime';
            return null;
        }
        if ($sizeBytes <= 0 || $sizeBytes > $this->maxUploadBytes) {
            $this->uploadError = 'invalid_size';
            return null;
        }

        $storagePath = $this->buildStoragePath($documentId, $extension);
        $absolutePath = $this->absolutePath($storagePath);
        if ($absolutePath === null) {
            $this->uploadError = 'invalid_storage_path';
            return null;
        }

        $targetDir = dirname($absolutePath);
        if (!is_dir($targetDir) && !@mkdir($targetDir, $this->directoryPermissions, true) && !is_dir($targetDir)) {
            $this->uploadError = 'storage_unavailable';
            return null;
        }
        @chmod($targetDir, $this->directoryPermissions);

        if (@file_put_contents($absolutePath, $content, LOCK_EX) === false) {
            $this->uploadError = 'write_failed';
            return null;
        }
        @chmod($absolutePath, $this->filePermissions);
        $sha256Hash = hash('sha256', $content);
        $this->uploadError = null;
        $this->logEvent('private.documents.generated', [
            'document_id' => $documentId,
            'size_bytes' => $sizeBytes,
            'mime_type' => $mimeType,
        ]);

        return [
            'documentId' => $documentId,
            'storagePath' => $storagePath,
            'originalName' => $originalName,
            'extension' => $extension,
            'mimeType' => $mimeType,
            'sizeBytes' => $sizeBytes,
            'sha256Hash' => $sha256Hash,
        ];
    }

    public function absolutePath(string $storagePath): ?string
    {
        $storagePath = trim(str_replace('\\', '/', (string) $storagePath));
        if (!preg_match('/\A[a-z0-9._\/-]+\z/i', $storagePath)) {
            return null;
        }

        if (str_contains($storagePath, '..')) {
            return null;
        }

        if (preg_match('/\Auploads\/([a-z0-9._\/-]+)\z/i', $storagePath, $matches) !== 1) {
            return null;
        }

        $relativePath = $matches[1];

        return $this->uploadsDirectoryPath . '/' . ltrim($relativePath, '/');
    }

    public function deleteStoredDocument(string $storagePath, ?string $documentId = null): bool
    {
        if ($this->legacyMode) {
            return false;
        }

        $absolutePath = $this->absolutePath($storagePath);
        if ($absolutePath === null || !is_file($absolutePath)) {
            return false;
        }

        $deleted = @unlink($absolutePath);
        if ($deleted) {
            $this->logEvent('private.documents.deleted', [
                'document_id' => $this->normalizeDocumentId((string) ($documentId ?? '')),
            ]);
        }

        return $deleted;
    }

    public function deleteStoredDocumentByDocumentId(string $documentId): bool
    {
        if ($this->legacyMode) {
            return false;
        }

        $documentId = $this->normalizeDocumentId($documentId);
        if ($documentId === '' || !is_dir($this->uploadsDirectoryPath)) {
            return false;
        }

        $deleted = false;
        $pattern = '/\A' . preg_quote($documentId, '/') . '\.[a-z0-9]{1,' . self::MAX_EXTENSION_LENGTH . '}\z/i';
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->uploadsDirectoryPath, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                    continue;
                }

                if (preg_match($pattern, $file->getFilename()) !== 1) {
                    continue;
                }

                $path = str_replace('\\', '/', $file->getPathname());
                if (!str_starts_with($path, $this->uploadsDirectoryPath . '/')) {
                    continue;
                }

                $deleted = @unlink($path) || $deleted;
            }
        } catch (\Throwable) {
            return $deleted;
        }

        if ($deleted) {
            $this->logEvent('private.documents.deleted', [
                'document_id' => $documentId,
            ]);
        }

        return $deleted;
    }

    private function buildStoragePath(string $documentId, string $extension): string
    {
        $hash = hash('sha256', $documentId . '|' . (string) time());
        return sprintf('uploads/%s/%s/%s.%s', substr($hash, 0, 2), substr($hash, 2, 2), $documentId, $extension);
    }

    private function ensureStorageDirectories(): void
    {
        if (!is_dir($this->storageDirectoryPath) && !@mkdir($this->storageDirectoryPath, $this->directoryPermissions, true) && !is_dir($this->storageDirectoryPath)) {
            return;
        }

        if (!is_dir($this->uploadsDirectoryPath) && !@mkdir($this->uploadsDirectoryPath, $this->directoryPermissions, true) && !is_dir($this->uploadsDirectoryPath)) {
            return;
        }

        if (!is_dir($this->exportsDirectoryPath) && !@mkdir($this->exportsDirectoryPath, $this->directoryPermissions, true) && !is_dir($this->exportsDirectoryPath)) {
            return;
        }

        @chmod($this->storageDirectoryPath, $this->directoryPermissions);
        @chmod($this->uploadsDirectoryPath, $this->directoryPermissions);
        @chmod($this->exportsDirectoryPath, $this->directoryPermissions);
    }

    private function normalizePermissions(int $permissions, int $default, int $required): int
    {
        return ($permissions & $required) === $required ? $permissions : $default;
    }

    private function normalizeDirectoryPath(string $path): string
    {
        $normalized = rtrim(str_replace('\\', '/', trim($path)), '/');
        return $normalized !== '' ? $normalized : ROOT_PATH . '/private';
    }

    private function sanitizeDirectoryName(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        $value = trim($value, '/');
        $value = preg_replace('/[^a-z0-9._-]/i', '', $value);
        $value = $value !== '' ? $value : $fallback;

        return trim($value, '/');
    }

    private function normalizeOriginalName(string $name): string
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name));
        if ($name === '') {
            return '';
        }

        $name = str_replace(["\r", "\n", "\t", '/'], ' ', $name);
        $name = trim($name);
        if ($name === '' || preg_match('/\A\.\.?\z/', $name) === 1 || str_contains($name, "\\")) {
            return '';
        }

        $name = preg_replace('/\.{2,}/', '.', $name);
        if (!is_string($name)) {
            return '';
        }

        if (strlen($name) > self::MAX_NAME_LENGTH) {
            return '';
        }

        $base = basename($name);
        if ($base !== $name && strpos($base, '..') !== false) {
            return '';
        }

        $base = preg_replace('/[\\/]/', '_', $base);
        $base = trim((string) $base);

        return $base !== '' ? $base : '';
    }

    private function normalizeExtensions(array $extensions): array
    {
        $normalized = [];
        foreach ($extensions as $extension) {
            $normalizedExtension = strtolower(trim((string) $extension));
            if (!preg_match('/\A[a-z0-9]{1,' . self::MAX_EXTENSION_LENGTH . '}\z/', $normalizedExtension)) {
                continue;
            }

            if (!in_array($normalizedExtension, $normalized, true)) {
                $normalized[] = $normalizedExtension;
            }
        }

        return $normalized;
    }

    private function normalizeMimeTypes(array $mimeTypes): array
    {
        $normalized = [];
        foreach ($mimeTypes as $mimeType) {
            $normalizedMimeType = strtolower(trim((string) $mimeType));
            if (!preg_match('/\A[a-z0-9.+-]+\/[a-z0-9.+-]+\z/i', $normalizedMimeType)) {
                continue;
            }

            if (!in_array($normalizedMimeType, $normalized, true)) {
                $normalized[] = $normalizedMimeType;
            }
        }

        return $normalized;
    }

    private function isAllowedExtension(string $extension): bool
    {
        return in_array(strtolower($extension), $this->allowedExtensions, true);
    }

    private function isAllowedMimeType(string $mimeType): bool
    {
        if (!preg_match('/\A[a-z0-9.+-]+\/[a-z0-9.+-]+\z/i', $mimeType)) {
            return false;
        }

        return in_array(strtolower($mimeType), $this->allowedMimeTypes, true);
    }

    private function detectMimeType(string $tmpPath): string
    {
        if (!is_file($tmpPath) || !is_readable($tmpPath)) {
            return '';
        }

        $mimeType = '';
        if (extension_loaded('fileinfo')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = $finfo->file($tmpPath);
                if (is_string($detected) && $detected !== '') {
                    $mimeType = strtolower(trim($detected));
                }
            }
        }

        if ($mimeType === '') {
            $mimeType = is_string($_SERVER['CONTENT_TYPE'] ?? null) ? strtolower(trim((string) $_SERVER['CONTENT_TYPE'])) : '';
        }

        return $mimeType;
    }

    private function normalizeDocumentId(string $documentId): string
    {
        $documentId = trim($documentId);
        if (preg_match('/\A[a-zA-Z0-9._-]{1,' . self::MAX_DOCUMENT_ID_LENGTH . '}\z/', $documentId) !== 1) {
            return '';
        }

        return $documentId;
    }

    private function scanStoredFile(string $absolutePath, string $originalName, string $mimeType): PrivateDocumentScanResult
    {
        if (!$this->scanner instanceof PrivateDocumentScanner || !$this->scanner->configured()) {
            return PrivateDocumentScanResult::cleanNoScanner();
        }

        $result = $this->scanner->scan($absolutePath, $originalName, $mimeType);
        $this->logEvent(
            'private.documents.scan.completed',
            [
                'scan_status' => $result->status(),
                'scan_exit_code' => $result->exitCode(),
                'scan_duration_ms' => $result->durationMs(),
                'scan_error' => $result->error(),
            ],
            $result->status() === PrivateDocumentScanResult::STATUS_CLEAN ? 'info' : 'warning'
        );

        return $result;
    }

    private function logEvent(string $event, array $context, string $level = 'info'): void
    {
        if (!($this->eventLogger instanceof AppEventLogger)) {
            return;
        }

        $this->eventLogger->security($event, $context, $level);
    }
}
