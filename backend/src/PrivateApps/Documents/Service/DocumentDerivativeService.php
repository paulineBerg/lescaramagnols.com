<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Service;

use Caramagnols\PrivateApps\Documents\Repository\DocumentHubRepository;

/**
 * Service de génération de dérivés (miniatures/aperçus) pour le hub documentaire.
 *
 * Capacités :
 * - Miniatures image : 2048 px (aperçu), 320-400 px (liste)
 * - Format JPEG qualité 82-85
 * - Jamais d'agrandissement au-delà de la résolution source
 * - Correction d'orientation dans le dérivé seul (si exif disponible)
 * - Dégradation explicite si GD absent
 */
final class DocumentDerivativeService
{
    private const PREVIEW_SIZE = 2048;
    private const LIST_SIZE = 400;
    private const JPEG_QUALITY = 85;
    private const JPEG_QUALITY_LIST = 82;

    private const DERIVATIVE_TYPE_PREVIEW = 'preview';
    private const DERIVATIVE_TYPE_THUMB = 'thumb';

    private readonly bool $gdAvailable;
    private readonly bool $exifAvailable;

    public function __construct(
        private readonly DocumentStorageService $storage,
        private readonly DocumentHubRepository $repository
    ) {
        $this->gdAvailable = extension_loaded('gd');
        $this->exifAvailable = extension_loaded('exif');
    }

    public function isAvailable(): bool
    {
        return $this->gdAvailable;
    }

    public function capabilities(): array
    {
        return [
            'gd' => $this->gdAvailable,
            'exif' => $this->exifAvailable,
        ];
    }

    /**
     * @param array<string, mixed> $object
     * @return array{generated: array<string, string>, skipped: array<string, string>, errors: array<string, string>}
     */
    public function generateDerivatives(array $object): array
    {
        if (!$this->gdAvailable) {
            return [
                'generated' => [],
                'skipped' => ['preview' => 'gd_unavailable', 'thumb' => 'gd_unavailable'],
                'errors' => [],
            ];
        }

        $sha256 = (string) ($object['sha256'] ?? '');
        if ($sha256 === '') {
            return [
                'generated' => [],
                'skipped' => [],
                'errors' => ['preview' => 'missing_sha256', 'thumb' => 'missing_sha256'],
            ];
        }

        $storageKey = (string) ($object['storage_key'] ?? '');
        if ($storageKey === '') {
            return [
                'generated' => [],
                'skipped' => [],
                'errors' => ['preview' => 'missing_storage_key', 'thumb' => 'missing_storage_key'],
            ];
        }

        $absolutePath = $this->storage->absolutePathForKey($storageKey);
        if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return [
                'generated' => [],
                'skipped' => [],
                'errors' => ['preview' => 'file_not_found', 'thumb' => 'file_not_found'],
            ];
        }

        $mimeType = strtolower((string) ($object['mime_type'] ?? ''));
        if (!self::isImageMimeType($mimeType)) {
            return [
                'generated' => [],
                'skipped' => ['preview' => 'not_image', 'thumb' => 'not_image'],
                'errors' => [],
            ];
        }

        $generated = [];
        $errors = [];

        $previewResult = $this->generateImageDerivative(
            $absolutePath,
            $sha256,
            self::DERIVATIVE_TYPE_PREVIEW,
            self::PREVIEW_SIZE,
            self::JPEG_QUALITY
        );
        if ($previewResult !== null) {
            $generated['preview'] = $previewResult;
        } else {
            $errors['preview'] = 'generation_failed';
        }

        $thumbResult = $this->generateImageDerivative(
            $absolutePath,
            $sha256,
            self::DERIVATIVE_TYPE_THUMB,
            self::LIST_SIZE,
            self::JPEG_QUALITY_LIST
        );
        if ($thumbResult !== null) {
            $generated['thumb'] = $thumbResult;
        } else {
            $errors['thumb'] = 'generation_failed';
        }

        return [
            'generated' => $generated,
            'skipped' => [],
            'errors' => $errors,
        ];
    }

    private function generateImageDerivative(
        string $sourcePath,
        string $sha256,
        string $derivativeType,
        int $maxSize,
        int $quality
    ): ?string {
        if (!$this->gdAvailable) {
            return null;
        }

        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return null;
        }

        $width = (int) $imageInfo[0];
        $height = (int) $imageInfo[1];
        $type = (int) $imageInfo[2];

        if ($width <= $maxSize && $height <= $maxSize) {
            return null;
        }

        $ratio = min($maxSize / $width, $maxSize / $height);
        $newWidth = (int) ceil($width * $ratio);
        $newHeight = (int) ceil($height * $ratio);

        $sourceImage = $this->createImageFromFile($sourcePath, $type);
        if ($sourceImage === null) {
            return null;
        }

        if ($this->exifAvailable && $derivativeType === self::DERIVATIVE_TYPE_PREVIEW) {
            $sourceImage = $this->applyOrientationCorrection($sourceImage, $sourcePath);
        }

        $destinationImage = imagecreatetruecolor($newWidth, $newHeight);
        if ($destinationImage === false) {
            imagedestroy($sourceImage);
            return null;
        }

        $hasTransparency = $type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF;
        if ($hasTransparency) {
            imagealphablending($destinationImage, false);
            imagesavealpha($destinationImage, true);
            $transparent = imagecolorallocatealpha($destinationImage, 0, 0, 0, 127);
            imagefill($destinationImage, 0, 0, $transparent);
        }

        imagecopyresampled(
            $destinationImage,
            $sourceImage,
            0, 0, 0, 0,
            $newWidth,
            $newHeight,
            $width,
            $height
        );

        $derivativeKey = $this->deriveStorageKey($sha256, $derivativeType, 'jpg');
        $derivativePath = $this->storage->rootPath() . '/derivatives/' . $derivativeKey;

        $derivativeDir = dirname($derivativePath);
        if (!is_dir($derivativeDir)) {
            if (!@mkdir($derivativeDir, 0700, true)) {
                imagedestroy($sourceImage);
                imagedestroy($destinationImage);
                return null;
            }
        }

        $success = imagejpeg($destinationImage, $derivativePath, $quality);

        imagedestroy($sourceImage);
        imagedestroy($destinationImage);

        if (!$success || !is_file($derivativePath)) {
            @unlink($derivativePath);
            return null;
        }

        @chmod($derivativePath, 0600);

        return $derivativeKey;
    }

    private function createImageFromFile(string $path, int $type): ?\GdImage
    {
        if (!$this->gdAvailable) {
            return null;
        }

        return match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path) ?: null,
            IMAGETYPE_PNG => imagecreatefrompng($path) ?: null,
            IMAGETYPE_GIF => imagecreatefromgif($path) ?: null,
            IMAGETYPE_WEBP => imagecreatefromwebp($path) ?: null,
            default => null,
        };
    }

    private function applyOrientationCorrection(\GdImage $image, string $path): \GdImage
    {
        if (!$this->exifAvailable) {
            return $image;
        }

        try {
            $exif = @exif_read_data($path);
            if (!is_array($exif)) {
                return $image;
            }

            $orientation = (int) ($exif['Orientation'] ?? 0);
            if ($orientation <= 0 || $orientation > 8) {
                return $image;
            }

            $rotated = match ($orientation) {
                2 => imagerotate($image, 0, 1),
                3 => imagerotate($image, 180, 0),
                4 => imagerotate($image, 0, 0),
                5 => imagerotate($image, 90, 1),
                6 => imagerotate($image, 270, 0),
                7 => imagerotate($image, 90, 0),
                8 => imagerotate($image, 270, 1),
                default => $image,
            };

            if ($rotated !== false) {
                imagedestroy($image);
                return $rotated;
            }
        } catch (\Throwable) {
        }

        return $image;
    }

    private function deriveStorageKey(string $sha256, string $derivativeType, string $extension): string
    {
        $prefix = substr($sha256, 0, 2) . '/' . substr($sha256, 2, 2);
        $suffix = $derivativeType . '-' . $sha256 . '.' . $extension;

        return 'derivatives/' . $prefix . '/' . $suffix;
    }

    private static function isImageMimeType(string $mime): bool
    {
        $imageMimes = [
            'image/jpeg',
            'image/pjpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/heic',
            'image/heif',
            'image/tiff',
        ];

        return in_array(strtolower($mime), $imageMimes, true);
    }

    /**
     * @param array<string, mixed> $object
     */
    public function removeDerivatives(array $object): int
    {
        if (!$this->gdAvailable) {
            return 0;
        }

        $sha256 = (string) ($object['sha256'] ?? '');
        if ($sha256 === '') {
            return 0;
        }

        $basePath = $this->storage->rootPath() . '/derivatives/' .
                    substr($sha256, 0, 2) . '/' . substr($sha256, 2, 2) . '/' . $sha256;

        $count = 0;

        foreach (['preview', 'thumb'] as $type) {
            foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                $path = $basePath . '-' . $type . '.' . $ext;
                if (is_file($path)) {
                    @unlink($path);
                    $count++;
                }
            }
        }

        return $count;
    }

    public function cleanupOrphanedDerivatives(): int
    {
        if (!$this->gdAvailable) {
            return 0;
        }

        $derivativesPath = $this->storage->rootPath() . '/derivatives';
        if (!is_dir($derivativesPath)) {
            return 0;
        }

        $count = 0;
        $validSha256s = [];

        try {
            $objects = $this->repository->allObjects(PHP_INT_MAX);
            foreach ($objects as $object) {
                $validSha256s[] = (string) ($object['sha256'] ?? '');
            }
        } catch (\Throwable) {
            return 0;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($derivativesPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $filename = $file->getFilename();
            $path = $file->getPathname();

            if (preg_match('/([a-f0-9]{64})/', $filename, $matches) === 1) {
                $sha256 = $matches[1];
                if (!in_array($sha256, $validSha256s, true)) {
                    @unlink($path);
                    $count++;
                }
            }
        }

        return $count;
    }
}
