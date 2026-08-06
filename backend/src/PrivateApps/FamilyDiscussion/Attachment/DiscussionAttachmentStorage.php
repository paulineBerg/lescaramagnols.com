<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\FamilyDiscussion\Attachment;

final class DiscussionAttachmentStorage
{
    private const ENCRYPTED_FILE_PREFIX = "CARADISCFILEv1\n";
    private const ENCRYPTION_CIPHER = 'aes-256-gcm';
    private const ENCRYPTION_IV_BYTES = 12;
    private const ENCRYPTION_TAG_BYTES = 16;
    private const DIRECTORY_PERMISSIONS = 0770;
    private const DEFAULT_MAX_UPLOAD_BYTES = 20 * 1024 * 1024;
    private const DEFAULT_ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt'];
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

    private string $rootPath;
    private readonly int $maxUploadBytes;
    /** @var array<int, string> */
    private array $allowedExtensions;
    /** @var array<int, string> */
    private array $allowedMimeTypes;
    private ?string $encryptionKey;
    private ?string $lastError = null;

    /**
     * @param array<int, string> $allowedExtensions
     * @param array<int, string> $allowedMimeTypes
     */
    public function __construct(
        string $storageRootPath,
        int $maxUploadBytes = self::DEFAULT_MAX_UPLOAD_BYTES,
        array $allowedExtensions = self::DEFAULT_ALLOWED_EXTENSIONS,
        array $allowedMimeTypes = self::DEFAULT_ALLOWED_MIME_TYPES,
        ?string $encryptionSecret = null,
        string $storageDirectory = 'storage'
    ) {
        $storageRootPath = trim(str_replace('\\', '/', $storageRootPath));
        if ($storageRootPath === '') {
            $this->assertProductionStorageRootConfigured($storageRootPath);
            $storageRootPath = ROOT_PATH . '/private';
        }

        $storageDirectory = $this->sanitizeDirectoryName($storageDirectory, 'storage');
        $this->rootPath = rtrim($storageRootPath, '/') . '/' . $storageDirectory . '/family-discussion';
        $this->assertProductionStoragePath($this->rootPath);

        $this->maxUploadBytes = max(1, $maxUploadBytes);
        $this->allowedExtensions = $this->normalizeExtensions($allowedExtensions);
        $this->allowedMimeTypes = $this->normalizeMimeTypes($allowedMimeTypes);
        if ($this->allowedExtensions === []) {
            $this->allowedExtensions = self::DEFAULT_ALLOWED_EXTENSIONS;
        }
        if ($this->allowedMimeTypes === []) {
            $this->allowedMimeTypes = self::DEFAULT_ALLOWED_MIME_TYPES;
        }
        $this->encryptionKey = $this->deriveEncryptionKey($this->resolveEncryptionSecret($encryptionSecret));

        $this->ensureRoot();
    }

    public static function fromAppConfig(): self
    {
        $discussionConfig = is_array(app_config('private.discussions', []))
            ? (array) app_config('private.discussions')
            : [];
        $documentConfig = is_array(app_config('private.documents', []))
            ? (array) app_config('private.documents')
            : [];

        $rootPath = is_string($discussionConfig['storage_root_path'] ?? null)
            ? (string) $discussionConfig['storage_root_path']
            : (is_string($documentConfig['storage_root_path'] ?? null) ? (string) $documentConfig['storage_root_path'] : ROOT_PATH . '/private');
        $storageDirectory = is_string($discussionConfig['storage_directory'] ?? null)
            ? (string) $discussionConfig['storage_directory']
            : (is_string($documentConfig['storage_directory'] ?? null) ? (string) $documentConfig['storage_directory'] : 'storage');
        $maxUploadBytes = is_numeric($discussionConfig['max_attachment_bytes'] ?? null)
            ? (int) $discussionConfig['max_attachment_bytes']
            : self::DEFAULT_MAX_UPLOAD_BYTES;
        $encryptionSecret = is_string($discussionConfig['attachment_encryption_key'] ?? null)
            ? (string) $discussionConfig['attachment_encryption_key']
            : null;

        return new self(
            $rootPath,
            $maxUploadBytes,
            is_array($discussionConfig['allowed_extensions'] ?? null) ? $discussionConfig['allowed_extensions'] : [],
            is_array($discussionConfig['allowed_mime_types'] ?? null) ? $discussionConfig['allowed_mime_types'] : [],
            $encryptionSecret,
            $storageDirectory
        );
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function isLegacyMode(): bool
    {
        return false;
    }

    private function assertProductionStorageRootConfigured(string $storageRootPath): void
    {
        if (!$this->isProductionEnvironment()) {
            return;
        }

        if (trim($storageRootPath) === '') {
            throw new \RuntimeException('PRIVATE_STORAGE_ROOT est obligatoire en production.');
        }
    }

    private function assertProductionStoragePath(string $path): void
    {
        if (!$this->isProductionEnvironment()) {
            return;
        }

        if ($this->isPathInsideRootPath($path)) {
            throw new \RuntimeException('Le stockage des discussions production ne peut pas être sous ROOT_PATH.');
        }
    }

    private function assertProductionStorageAvailable(): void
    {
        if ($this->isProductionEnvironment() && !is_dir($this->rootPath)) {
            throw new \RuntimeException('Le stockage des discussions production configuré est absent.');
        }
    }

    private function isProductionEnvironment(): bool
    {
        $appEnv = function_exists('app_config') ? strtolower((string) app_config('env', '')) : '';

        return in_array($appEnv, ['production', 'prod', 'live'], true);
    }

    private function isPathInsideRootPath(string $path): bool
    {
        $normalizedPath = $this->normalizePath($path);
        $rootPath = $this->normalizePath(ROOT_PATH);

        return $normalizedPath === $rootPath || str_starts_with($normalizedPath, $rootPath . '/');
    }

    private function normalizePath(string $path): string
    {
        $normalized = rtrim(str_replace('\\', '/', trim($path)), '/');

        return $normalized !== '' ? $normalized : '/';
    }

    public function uploadsDirectory(): string
    {
        return $this->rootPath . '/uploads';
    }

    public function generateAttachmentId(): string
    {
        try {
            return substr(bin2hex(random_bytes(16)), 0, 64);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @param array{name:string,tmp_name:string,size:int|string,error:int,type?:string} $uploadedFile
     * @return array{tmpPath:string,originalFilename:string,extension:string,mimeType:string,sizeBytes:int,sha256:string,width:?int,height:?int,isImage:bool}|null
     */
    public function validateUploadedFile(array $uploadedFile): ?array
    {
        $this->lastError = null;

        $errorCode = is_numeric($uploadedFile['error'] ?? null) ? (int) $uploadedFile['error'] : UPLOAD_ERR_NO_FILE;
        if ($errorCode !== UPLOAD_ERR_OK) {
            $this->lastError = 'upload_error';
            return null;
        }

        $tmpPath = is_string($uploadedFile['tmp_name'] ?? null) ? trim((string) $uploadedFile['tmp_name']) : '';
        if ($tmpPath === '' || !is_file($tmpPath) || !is_readable($tmpPath)) {
            $this->lastError = 'invalid_tmp_file';
            return null;
        }

        $originalFilename = $this->normalizeOriginalFilename((string) ($uploadedFile['name'] ?? ''));
        if ($originalFilename === '') {
            $this->lastError = 'invalid_original_name';
            return null;
        }

        $extension = strtolower((string) pathinfo($originalFilename, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions, true)) {
            $this->lastError = 'invalid_extension';
            return null;
        }

        $sizeBytes = is_numeric($uploadedFile['size'] ?? null) ? (int) $uploadedFile['size'] : 0;
        $realSize = @filesize($tmpPath);
        if (is_int($realSize)) {
            $sizeBytes = $realSize;
        }
        if ($sizeBytes <= 0 || $sizeBytes > $this->maxUploadBytes) {
            $this->lastError = 'invalid_size';
            return null;
        }

        $mimeType = $this->detectMimeType($tmpPath);
        if ($mimeType === '') {
            $mimeType = is_string($uploadedFile['type'] ?? null) ? strtolower(trim((string) $uploadedFile['type'])) : '';
        }
        if (!in_array($mimeType, $this->allowedMimeTypes, true)) {
            $this->lastError = 'invalid_mime';
            return null;
        }

        $sha256 = hash_file('sha256', $tmpPath);
        if ($sha256 === false) {
            $this->lastError = 'checksum_failed';
            return null;
        }

        $width = null;
        $height = null;
        $isImage = str_starts_with($mimeType, 'image/');
        if ($isImage) {
            $dimensions = @getimagesize($tmpPath);
            if (is_array($dimensions)) {
                $width = (int) $dimensions[0];
                $height = (int) $dimensions[1];
            }
        }

        return [
            'tmpPath' => $tmpPath,
            'originalFilename' => $originalFilename,
            'extension' => $extension,
            'mimeType' => $mimeType,
            'sizeBytes' => $sizeBytes,
            'sha256' => $sha256,
            'width' => $width,
            'height' => $height,
            'isImage' => $isImage,
        ];
    }

    /**
     * @param array{tmpPath:string,originalFilename:string,extension:string,mimeType:string,sizeBytes:int,sha256:string,width:?int,height:?int,isImage:bool} $metadata
     * @return array{attachmentId:string,storagePath:string,previewStoragePath:?string,originalFilename:string,mimeType:string,sizeBytes:int,sha256:string,width:?int,height:?int}|null
     */
    public function store(array $metadata, string $attachmentId): ?array
    {
        $this->lastError = null;
        $this->assertProductionStorageAvailable();

        $attachmentId = $this->normalizeAttachmentId($attachmentId);
        if ($attachmentId === '') {
            $this->lastError = 'invalid_attachment_id';
            return null;
        }

        $tmpPath = is_string($metadata['tmpPath'] ?? null) ? trim((string) $metadata['tmpPath']) : '';
        $extension = is_string($metadata['extension'] ?? null) ? strtolower(trim((string) $metadata['extension'])) : '';
        if ($tmpPath === '' || !is_file($tmpPath) || $extension === '' || !in_array($extension, $this->allowedExtensions, true)) {
            $this->lastError = 'invalid_file';
            return null;
        }

        $storagePath = $this->buildStoragePath($attachmentId, $extension);
        $absolutePath = $this->absolutePath($storagePath);
        if ($absolutePath === null) {
            $this->lastError = 'invalid_storage_path';
            return null;
        }

        $targetDir = dirname($absolutePath);
        if (!is_dir($targetDir) && !@mkdir($targetDir, self::DIRECTORY_PERMISSIONS, true) && !is_dir($targetDir)) {
            $this->lastError = 'storage_unavailable';
            return null;
        }

        $plainContent = file_get_contents($tmpPath);
        if (!is_string($plainContent)) {
            $this->lastError = 'read_failed';
            return null;
        }

        $storedContent = $this->encryptContent($plainContent, $storagePath);
        if ($storedContent === null) {
            $this->lastError = 'encryption_failed';
            return null;
        }

        $stored = file_put_contents($absolutePath, $storedContent, LOCK_EX);
        if ($stored === false || !is_file($absolutePath)) {
            $this->lastError = 'write_failed';
            return null;
        }
        @chmod($absolutePath, 0660);

        $previewStoragePath = !empty($metadata['isImage']) ? $storagePath : null;

        return [
            'attachmentId' => $attachmentId,
            'storagePath' => $storagePath,
            'previewStoragePath' => $previewStoragePath,
            'originalFilename' => (string) ($metadata['originalFilename'] ?? ''),
            'mimeType' => (string) ($metadata['mimeType'] ?? ''),
            'sizeBytes' => (int) ($metadata['sizeBytes'] ?? 0),
            'sha256' => (string) ($metadata['sha256'] ?? ''),
            'width' => is_int($metadata['width'] ?? null) ? $metadata['width'] : null,
            'height' => is_int($metadata['height'] ?? null) ? $metadata['height'] : null,
        ];
    }

    public function absolutePath(string $storagePath): ?string
    {
        $storagePath = trim(str_replace('\\', '/', $storagePath));
        if ($storagePath === '' || str_contains($storagePath, '..')) {
            return null;
        }

        if (preg_match('/\Afamily-discussion\/uploads\/[a-z0-9]{2}\/[a-z0-9]{2}\/[A-Za-z0-9._-]+\.[a-z0-9]{1,16}\z/', $storagePath) !== 1) {
            return null;
        }

        return $this->rootPath . '/' . substr($storagePath, strlen('family-discussion/'));
    }

    public function read(string $storagePath): ?string
    {
        $absolutePath = $this->absolutePath($storagePath);
        if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }

        $content = file_get_contents($absolutePath);
        if (!is_string($content)) {
            return null;
        }

        return $this->decryptContent($content, $storagePath);
    }

    public function isEncryptedStoredFile(string $storagePath): bool
    {
        $absolutePath = $this->absolutePath($storagePath);
        if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return false;
        }

        $handle = fopen($absolutePath, 'rb');
        if (!is_resource($handle)) {
            return false;
        }

        $prefix = fread($handle, strlen(self::ENCRYPTED_FILE_PREFIX));
        fclose($handle);

        return $prefix === self::ENCRYPTED_FILE_PREFIX;
    }

    public function delete(string $storagePath): bool
    {
        $this->assertProductionStorageAvailable();

        $absolutePath = $this->absolutePath($storagePath);
        if ($absolutePath === null || !is_file($absolutePath)) {
            return false;
        }

        return @unlink($absolutePath);
    }

    private function buildStoragePath(string $attachmentId, string $extension): string
    {
        $hash = hash('sha256', $attachmentId . '|' . time());

        return sprintf('family-discussion/uploads/%s/%s/%s.%s', substr($hash, 0, 2), substr($hash, 2, 2), $attachmentId, $extension);
    }

    private function encryptContent(string $plainContent, string $storagePath): ?string
    {
        if ($this->encryptionKey === null || !function_exists('openssl_encrypt')) {
            return null;
        }

        try {
            $iv = random_bytes(self::ENCRYPTION_IV_BYTES);
        } catch (\Throwable) {
            return null;
        }

        $tag = '';
        $ciphertext = openssl_encrypt(
            $plainContent,
            self::ENCRYPTION_CIPHER,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $storagePath,
            self::ENCRYPTION_TAG_BYTES
        );
        if (!is_string($ciphertext) || strlen($tag) !== self::ENCRYPTION_TAG_BYTES) {
            return null;
        }

        return self::ENCRYPTED_FILE_PREFIX . $iv . $tag . $ciphertext;
    }

    private function decryptContent(string $storedContent, string $storagePath): ?string
    {
        if (!str_starts_with($storedContent, self::ENCRYPTED_FILE_PREFIX)) {
            return $storedContent;
        }

        if ($this->encryptionKey === null || !function_exists('openssl_decrypt')) {
            return null;
        }

        $offset = strlen(self::ENCRYPTED_FILE_PREFIX);
        $minimumLength = $offset + self::ENCRYPTION_IV_BYTES + self::ENCRYPTION_TAG_BYTES;
        if (strlen($storedContent) < $minimumLength) {
            return null;
        }

        $iv = substr($storedContent, $offset, self::ENCRYPTION_IV_BYTES);
        $tag = substr($storedContent, $offset + self::ENCRYPTION_IV_BYTES, self::ENCRYPTION_TAG_BYTES);
        $ciphertext = substr($storedContent, $minimumLength);
        $plain = openssl_decrypt(
            $ciphertext,
            self::ENCRYPTION_CIPHER,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $storagePath
        );

        return is_string($plain) ? $plain : null;
    }

    private function resolveEncryptionSecret(?string $explicitSecret): string
    {
        $secret = trim((string) ($explicitSecret ?? ''));
        if ($secret === '' && function_exists('app_config')) {
            $configured = app_config('private.discussions.attachment_encryption_key', '');
            $secret = is_scalar($configured) ? trim((string) $configured) : '';
        }
        if ($secret === '' && function_exists('env')) {
            $secret = trim((string) env('PRIVATE_DISCUSSION_ATTACHMENT_ENCRYPTION_KEY', ''));
        }

        $normalizedEnv = function_exists('app_config') ? strtolower((string) app_config('env', 'development')) : 'development';
        if ($secret === '' && !in_array($normalizedEnv, ['production', 'prod', 'live'], true) && function_exists('app_config')) {
            $configured = app_config('admin.session_key', '');
            $secret = is_scalar($configured) ? trim((string) $configured) : '';
        }

        if ($secret === '' || in_array($secret, ['caramagnols_admin', 'change-this-admin-session-key'], true)) {
            if (in_array($normalizedEnv, ['production', 'prod', 'live'], true)) {
                return '';
            }

            return 'local-development-discussion-attachment-key|' . ROOT_PATH;
        }

        return $secret;
    }

    private function deriveEncryptionKey(string $secret): ?string
    {
        if ($secret === '') {
            return null;
        }

        if (str_starts_with($secret, 'base64:')) {
            $decoded = base64_decode(substr($secret, 7), true);
            if (is_string($decoded) && strlen($decoded) >= 32) {
                return substr($decoded, 0, 32);
            }
        }

        if (preg_match('/\A[a-f0-9]{64}\z/i', $secret) === 1) {
            $decoded = hex2bin($secret);
            if (is_string($decoded) && strlen($decoded) === 32) {
                return $decoded;
            }
        }

        return hash('sha256', $secret, true);
    }

    private function ensureRoot(): void
    {
        if (!is_dir($this->rootPath)) {
            @mkdir($this->rootPath, self::DIRECTORY_PERMISSIONS, true);
        }

        if (is_dir($this->rootPath)) {
            @chmod($this->rootPath, self::DIRECTORY_PERMISSIONS);
        }
    }

    private function normalizeAttachmentId(string $id): string
    {
        $id = trim($id);

        return preg_match('/\A[A-Za-z0-9._-]{1,64}\z/', $id) === 1 ? $id : '';
    }

    private function sanitizeDirectoryName(string $directory, string $fallback): string
    {
        $directory = trim(str_replace('\\', '/', $directory), '/');
        if ($directory === '' || str_contains($directory, '..') || preg_match('/\A[A-Za-z0-9._-]+\z/', $directory) !== 1) {
            return $fallback;
        }

        return $directory;
    }

    private function normalizeOriginalFilename(string $filename): string
    {
        $filename = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $filename));
        $filename = trim(str_replace(["\r", "\n", "\t", '/', '\\'], ' ', $filename));
        $filename = preg_replace('/\s+/', ' ', $filename);
        if (!is_string($filename) || $filename === '' || strlen($filename) > 255) {
            return '';
        }

        return $filename;
    }

    /**
     * @param array<int, string> $extensions
     * @return array<int, string>
     */
    private function normalizeExtensions(array $extensions): array
    {
        $normalized = [];
        foreach ($extensions as $extension) {
            $extension = strtolower(trim((string) $extension));
            if (preg_match('/\A[a-z0-9]{1,16}\z/', $extension) === 1 && !in_array($extension, $normalized, true)) {
                $normalized[] = $extension;
            }
        }

        return $normalized;
    }

    /**
     * @param array<int, string> $mimeTypes
     * @return array<int, string>
     */
    private function normalizeMimeTypes(array $mimeTypes): array
    {
        $normalized = [];
        foreach ($mimeTypes as $mimeType) {
            $mimeType = strtolower(trim((string) $mimeType));
            if (preg_match('/\A[a-z0-9.+-]+\/[a-z0-9.+-]+\z/i', $mimeType) === 1 && !in_array($mimeType, $normalized, true)) {
                $normalized[] = $mimeType;
            }
        }

        return $normalized;
    }

    private function detectMimeType(string $tmpPath): string
    {
        if (!is_file($tmpPath) || !is_readable($tmpPath) || !extension_loaded('fileinfo')) {
            return '';
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($tmpPath);

        return is_string($detected) ? strtolower(trim($detected)) : '';
    }
}
