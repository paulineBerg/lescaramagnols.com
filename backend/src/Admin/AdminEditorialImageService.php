<?php

declare(strict_types=1);

namespace Caramagnols\Admin;

use Caramagnols\Http\PublicUrlNormalizer;

final class AdminEditorialImageService
{
    private const ALLOWED_MIME_TO_EXTENSION = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/avif' => 'avif',
    ];

    public function __construct(
        private readonly string $publicDirectory,
        private readonly int $maxUploadBytes = 6291456
    ) {
    }

    /**
     * @param array<string, mixed> $file
     * @return array{src: string, width: int|null, height: int|null, mime: string}
     */
    public function upload(array $file, string $scope, ?string $slugHint = null): array
    {
        $validated = $this->validateUploadedImage($file);
        $scopeToken = $this->slugify($scope, 'editorial');
        $filenameBase = $this->buildFilenameBase(
            $scopeToken,
            (string) $slugHint,
            (string) ($validated['originalName'] ?? '')
        );
        [$relativeDirectory, $targetDirectory] = $this->resolveTargetDirectory($scopeToken);
        $filename = $this->buildUniqueFilename($filenameBase, (string) $validated['extension']);
        $targetPath = rtrim($targetDirectory, '/\\') . DIRECTORY_SEPARATOR . $filename;

        $tmpName = (string) $validated['tmpName'];
        $moved = move_uploaded_file($tmpName, $targetPath);
        if (!$moved && PHP_SAPI === 'cli') {
            $moved = @rename($tmpName, $targetPath);
            if (!$moved) {
                $moved = @copy($tmpName, $targetPath);
                if ($moved) {
                    @unlink($tmpName);
                }
            }
        }

        if (!$moved) {
            throw new \RuntimeException('Impossible de deplacer le fichier image uploade.');
        }

        @chmod($targetPath, 0644);

        return [
            'src' => $relativeDirectory . '/' . $filename,
            'width' => $validated['width'],
            'height' => $validated['height'],
            'mime' => (string) $validated['mimeType'],
        ];
    }

    /**
     * Upload image with automatic resize and WebP conversion.
     *
     * @param array<string, mixed> $file
     * @return array{src: string, width: int|null, height: int|null, mime: string}
     */
    public function uploadWebp(
        array $file,
        string $scope,
        ?string $slugHint = null,
        int $maxWidth = 2048,
        int $maxHeight = 2048,
        int $quality = 82
    ): array {
        $validated = $this->validateUploadedImage($file);
        $tmpName = (string) $validated['tmpName'];
        $mimeType = (string) $validated['mimeType'];

        if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp')) {
            throw new \RuntimeException('La conversion WebP n est pas disponible sur le serveur (extension GD requise).');
        }

        $sourceImage = $this->createImageResourceFromFile($tmpName, $mimeType);
        if (!$this->isGdImageResource($sourceImage)) {
            throw new \RuntimeException('Impossible de lire ce format pour conversion WebP. Utilisez JPG, PNG, GIF ou WebP.');
        }

        $sourceWidth = max(1, (int) imagesx($sourceImage));
        $sourceHeight = max(1, (int) imagesy($sourceImage));
        [$targetWidth, $targetHeight] = $this->fitInsideBox($sourceWidth, $sourceHeight, $maxWidth, $maxHeight);

        $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($targetImage === false) {
            $this->destroyGdImage($sourceImage);
            throw new \RuntimeException('Impossible de preparer la conversion image.');
        }

        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);
        $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
        if ($transparent !== false) {
            imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        $resampled = imagecopyresampled(
            $targetImage,
            $sourceImage,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        if (!$resampled) {
            imagedestroy($targetImage);
            $this->destroyGdImage($sourceImage);
            throw new \RuntimeException('Impossible de redimensionner limage.');
        }

        $scopeToken = $this->slugify($scope, 'editorial');
        $filenameBase = $this->buildFilenameBase(
            $scopeToken,
            (string) $slugHint,
            (string) ($validated['originalName'] ?? '')
        );
        [$relativeDirectory, $targetDirectory] = $this->resolveTargetDirectory($scopeToken);
        $filename = $this->buildUniqueFilename($filenameBase, 'webp');
        $targetPath = rtrim($targetDirectory, '/\\') . DIRECTORY_SEPARATOR . $filename;
        $normalizedQuality = max(30, min(100, $quality));

        $written = imagewebp($targetImage, $targetPath, $normalizedQuality);
        imagedestroy($targetImage);
        $this->destroyGdImage($sourceImage);

        if (!$written) {
            throw new \RuntimeException('Impossible d ecrire le fichier WebP.');
        }

        @chmod($targetPath, 0644);

        return [
            'src' => $relativeDirectory . '/' . $filename,
            'width' => $targetWidth,
            'height' => $targetHeight,
            'mime' => 'image/webp',
        ];
    }

    /**
     * @return array<int, array{src: string, width: int|null, height: int|null, mime: string}>
     */
    public function listUploads(string $scope = 'media', int $limit = 120): array
    {
        $scopeToken = $this->slugify($scope, 'editorial');
        $baseDirectory = rtrim($this->publicDirectory, '/\\') . '/uploads/editorial/' . $scopeToken;
        if (!is_dir($baseDirectory)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $publicRoot = str_replace('\\', '/', rtrim($this->publicDirectory, '/\\'));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];
        $entries = [];

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            $extension = strtolower((string) $fileInfo->getExtension());
            if (!in_array($extension, $allowedExtensions, true)) {
                continue;
            }

            $absolutePath = str_replace('\\', '/', (string) $fileInfo->getPathname());
            $relativePath = str_starts_with($absolutePath, $publicRoot)
                ? substr($absolutePath, strlen($publicRoot))
                : '';
            if (!is_string($relativePath) || $relativePath === '') {
                continue;
            }

            $relativePath = '/' . ltrim($relativePath, '/');
            $dimensions = @getimagesize((string) $fileInfo->getPathname());
            $width = is_array($dimensions) ? $this->normalizeDimension($dimensions[0], 1, 8192) : null;
            $height = is_array($dimensions) ? $this->normalizeDimension($dimensions[1], 1, 8192) : null;
            $mime = is_array($dimensions) && is_string($dimensions['mime'])
                ? strtolower((string) $dimensions['mime'])
                : (self::extensionToMime($extension) ?? 'application/octet-stream');

            $entries[] = [
                'src' => $relativePath,
                'width' => $width,
                'height' => $height,
                'mime' => $mime,
                'mtime' => $fileInfo->getMTime(),
            ];
        }

        usort(
            $entries,
            static fn (array $left, array $right): int => ((int) $right['mtime']) <=> ((int) $left['mtime'])
        );

        $entries = array_slice($entries, 0, max(0, $limit));

        return array_values(
            array_map(
                static fn (array $entry): array => [
                    'src' => (string) $entry['src'],
                    'width' => isset($entry['width']) ? (is_int($entry['width']) ? $entry['width'] : null) : null,
                    'height' => isset($entry['height']) ? (is_int($entry['height']) ? $entry['height'] : null) : null,
                    'mime' => (string) $entry['mime'],
                ],
                $entries
            )
        );
    }

    /**
     * @param array<string, mixed> $image
     * @return array<string, mixed>|null
     */
    public static function sanitizeImageMetadata(array $image): ?array
    {
        $src = self::sanitizePublicImageSource((string) ($image['src'] ?? ''));
        if ($src === '') {
            return null;
        }

        $alt = self::sanitizeInlineText((string) ($image['alt'] ?? ''), 280);
        $title = self::sanitizeInlineText((string) ($image['title'] ?? ''), 280);
        $caption = self::sanitizeInlineText((string) ($image['caption'] ?? ''), 600);
        $width = self::normalizeStaticDimension($image['width'] ?? null, 1, 8192);
        $height = self::normalizeStaticDimension($image['height'] ?? null, 1, 8192);

        $normalized = [
            'src' => $src,
            'alt' => $alt,
        ];

        if ($title !== '') {
            $normalized['title'] = $title;
        }

        if ($caption !== '') {
            $normalized['caption'] = $caption;
        }

        if ($width !== null) {
            $normalized['width'] = $width;
        }

        if ($height !== null) {
            $normalized['height'] = $height;
        }

        return $normalized;
    }

    public static function sanitizePublicImageSource(string $src): string
    {
        return PublicUrlNormalizer::normalizeImageSource($src, null, false);
    }

    private static function sanitizeInlineText(string $value, int $maxLength): string
    {
        $value = trim($value);

        if (function_exists('sanitize_text_field')) {
            return sanitize_text_field($value, $maxLength);
        }

        if (function_exists('mb_substr')) {
            $value = (string) mb_substr($value, 0, $maxLength);
        } else {
            $value = substr($value, 0, $maxLength);
        }

        return trim(strip_tags($value));
    }

    private function slugify(string $value, string $fallback): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9-]+/i', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : $fallback;
    }

    private function normalizeDimension(mixed $value, int $min, int $max): ?int
    {
        return self::normalizeStaticDimension($value, $min, $max);
    }

    /**
     * @param array<string, mixed> $file
     * @return array{
     *   tmpName: string,
     *   size: int,
     *   mimeType: string,
     *   extension: string,
     *   width: int|null,
     *   height: int|null,
     *   originalName: string
     * }
     */
    private function validateUploadedImage(array $file): array
    {
        $errorCode = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            throw new \RuntimeException('Aucun fichier image transmis.');
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->uploadErrorMessage($errorCode));
        }

        $tmpName = is_string($file['tmp_name'] ?? null) ? trim((string) $file['tmp_name']) : '';
        if ($tmpName === '' || !is_file($tmpName)) {
            throw new \RuntimeException('Le fichier temporaire est introuvable.');
        }

        $size = is_numeric($file['size'] ?? null) ? (int) $file['size'] : (int) filesize($tmpName);
        if ($size <= 0) {
            throw new \RuntimeException('Le fichier image est vide.');
        }

        if ($size > $this->maxUploadBytes) {
            throw new \RuntimeException(
                sprintf('Le fichier image depasse la taille maximale autorisee (%d Mo).', max(1, (int) floor($this->maxUploadBytes / 1048576)))
            );
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = strtolower((string) $finfo->file($tmpName));
        $extension = self::ALLOWED_MIME_TO_EXTENSION[$mimeType] ?? null;
        if (!is_string($extension)) {
            throw new \RuntimeException('Format image non supporte. Utilisez JPG, PNG, WebP, GIF ou AVIF.');
        }

        $dimensions = @getimagesize($tmpName);
        $width = is_array($dimensions) ? $this->normalizeDimension($dimensions[0], 1, 8192) : null;
        $height = is_array($dimensions) ? $this->normalizeDimension($dimensions[1], 1, 8192) : null;
        $originalName = is_string($file['name'] ?? null) ? (string) $file['name'] : '';

        return [
            'tmpName' => $tmpName,
            'size' => $size,
            'mimeType' => $mimeType,
            'extension' => $extension,
            'width' => $width,
            'height' => $height,
            'originalName' => $originalName,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveTargetDirectory(string $scopeToken): array
    {
        $relativeDirectory = '/uploads/editorial/' . $scopeToken . '/' . date('Y') . '/' . date('m');
        $targetDirectory = rtrim($this->publicDirectory, '/\\') . $relativeDirectory;

        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new \RuntimeException('Impossible de creer le dossier de destination des images.');
        }

        return [$relativeDirectory, $targetDirectory];
    }

    private function buildFilenameBase(string $scopeToken, string $slugHint, string $originalName): string
    {
        $hintToken = $this->slugify($slugHint, '');
        $originalToken = $this->slugify((string) pathinfo($originalName, PATHINFO_FILENAME), 'image');
        $filenameBase = trim(
            implode('-', array_filter([$scopeToken, $hintToken, $originalToken], static fn (string $part): bool => $part !== '')),
            '-'
        );

        if ($filenameBase === '') {
            $filenameBase = 'editorial-image';
        }

        if (function_exists('mb_substr')) {
            $filenameBase = (string) mb_substr($filenameBase, 0, 72);
        } else {
            $filenameBase = substr($filenameBase, 0, 72);
        }

        return $filenameBase;
    }

    private function buildUniqueFilename(string $filenameBase, string $extension): string
    {
        return $filenameBase . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    }

    /**
     * @return \GdImage|false|null
     */
    private function createImageResourceFromFile(string $filePath, string $mimeType): mixed
    {
        return match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($filePath),
            'image/png' => @imagecreatefrompng($filePath),
            'image/gif' => @imagecreatefromgif($filePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($filePath) : null,
            'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($filePath) : null,
            default => null,
        };
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function fitInsideBox(int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        $width = max(1, $width);
        $height = max(1, $height);
        $maxWidth = max(1, min(8192, $maxWidth));
        $maxHeight = max(1, min(8192, $maxHeight));

        $ratio = min($maxWidth / $width, $maxHeight / $height, 1.0);
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));

        return [$targetWidth, $targetHeight];
    }

    private static function extensionToMime(string $extension): ?string
    {
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            default => null,
        };
    }

    private function isGdImageResource(mixed $value): bool
    {
        return $value instanceof \GdImage || is_resource($value);
    }

    private function destroyGdImage(mixed $value): void
    {
        if ($this->isGdImageResource($value)) {
            imagedestroy($value);
        }
    }

    private static function normalizeStaticDimension(mixed $value, int $min, int $max): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (!is_numeric($value)) {
            return null;
        }

        $normalized = (int) $value;
        if ($normalized < $min) {
            return null;
        }

        if ($normalized > $max) {
            $normalized = $max;
        }

        return $normalized;
    }

    private function uploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Le fichier image depasse la taille autorisee.',
            UPLOAD_ERR_PARTIAL => 'Le fichier image na ete transfere que partiellement.',
            UPLOAD_ERR_NO_TMP_DIR => 'Le serveur ne dispose pas de dossier temporaire pour lupload.',
            UPLOAD_ERR_CANT_WRITE => 'Le serveur ne peut pas ecrire le fichier uploade.',
            UPLOAD_ERR_EXTENSION => 'Lupload a ete bloque par une extension PHP.',
            default => 'Erreur technique pendant lupload de limage.',
        };
    }
}
