<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Service;

/**
 * Politique documentaire unique : formats acceptés, correspondances MIME,
 * limites de taille par famille et limites de décodage.
 * Seul endroit du dépôt où ces règles sont définies ; surchargées par
 * app_config('private.document_hub') si présent.
 */
final class DocumentPolicy
{
    public const FAMILY_PDF = 'pdf';
    public const FAMILY_IMAGE = 'image';
    public const FAMILY_TIFF = 'tiff';
    public const FAMILY_OFFICE = 'office';
    public const FAMILY_TEXT = 'text';

    private const MB = 1048576;

    /** @var array<string, string> extension -> famille */
    private const EXTENSION_FAMILIES = [
        'pdf' => self::FAMILY_PDF,
        'jpg' => self::FAMILY_IMAGE,
        'jpeg' => self::FAMILY_IMAGE,
        'png' => self::FAMILY_IMAGE,
        'webp' => self::FAMILY_IMAGE,
        'heic' => self::FAMILY_IMAGE,
        'heif' => self::FAMILY_IMAGE,
        'tif' => self::FAMILY_TIFF,
        'tiff' => self::FAMILY_TIFF,
        'docx' => self::FAMILY_OFFICE,
        'odt' => self::FAMILY_OFFICE,
        'xlsx' => self::FAMILY_OFFICE,
        'ods' => self::FAMILY_OFFICE,
        'csv' => self::FAMILY_TEXT,
        'txt' => self::FAMILY_TEXT,
    ];

    /** @var array<string, array<int, string>> extension -> MIME acceptés (déterminés par contenu) */
    private const EXTENSION_MIME_TYPES = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'heic' => ['image/heic', 'image/heif', 'application/octet-stream'],
        'heif' => ['image/heif', 'image/heic', 'application/octet-stream'],
        'tif' => ['image/tiff'],
        'tiff' => ['image/tiff'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
        ],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'txt' => ['text/plain'],
    ];

    /** @var array<string, int> famille -> octets max par fichier */
    private const DEFAULT_FAMILY_MAX_BYTES = [
        self::FAMILY_PDF => 25 * self::MB,
        self::FAMILY_IMAGE => 15 * self::MB,
        self::FAMILY_TIFF => 30 * self::MB,
        self::FAMILY_OFFICE => 15 * self::MB,
        self::FAMILY_TEXT => 5 * self::MB,
    ];

    private const DEFAULT_BATCH_MAX_BYTES = 100 * self::MB;
    private const DEFAULT_MAX_IMAGE_PIXELS = 40_000_000;

    /** @var array<string, int> */
    private array $familyMaxBytes;
    private int $batchMaxBytes;
    private int $maxImagePixels;

    /**
     * @param array<string, int> $familyMaxBytes
     */
    public function __construct(
        array $familyMaxBytes = [],
        int $batchMaxBytes = self::DEFAULT_BATCH_MAX_BYTES,
        int $maxImagePixels = self::DEFAULT_MAX_IMAGE_PIXELS
    ) {
        $this->familyMaxBytes = self::DEFAULT_FAMILY_MAX_BYTES;
        foreach ($familyMaxBytes as $family => $maxBytes) {
            if (isset($this->familyMaxBytes[$family]) && is_int($maxBytes) && $maxBytes > 0) {
                $this->familyMaxBytes[$family] = $maxBytes;
            }
        }

        $this->batchMaxBytes = $batchMaxBytes > 0 ? $batchMaxBytes : self::DEFAULT_BATCH_MAX_BYTES;
        $this->maxImagePixels = $maxImagePixels > 0 ? $maxImagePixels : self::DEFAULT_MAX_IMAGE_PIXELS;
    }

    public static function fromAppConfig(): self
    {
        $config = function_exists('app_config') ? app_config('private.document_hub', []) : [];
        $config = is_array($config) ? $config : [];

        $familyMaxBytes = [];
        if (is_array($config['family_max_bytes'] ?? null)) {
            foreach ((array) $config['family_max_bytes'] as $family => $maxBytes) {
                if (is_string($family) && is_numeric($maxBytes)) {
                    $familyMaxBytes[$family] = (int) $maxBytes;
                }
            }
        }

        return new self(
            $familyMaxBytes,
            is_numeric($config['batch_max_bytes'] ?? null) ? (int) $config['batch_max_bytes'] : self::DEFAULT_BATCH_MAX_BYTES,
            is_numeric($config['max_image_pixels'] ?? null) ? (int) $config['max_image_pixels'] : self::DEFAULT_MAX_IMAGE_PIXELS
        );
    }

    /**
     * @return array<int, string>
     */
    public function allowedExtensions(): array
    {
        return array_keys(self::EXTENSION_FAMILIES);
    }

    public function isAllowedExtension(string $extension): bool
    {
        return isset(self::EXTENSION_FAMILIES[strtolower(trim($extension))]);
    }

    public function familyForExtension(string $extension): ?string
    {
        return self::EXTENSION_FAMILIES[strtolower(trim($extension))] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function allowedMimeTypesForExtension(string $extension): array
    {
        return self::EXTENSION_MIME_TYPES[strtolower(trim($extension))] ?? [];
    }

    public function maxBytesForExtension(string $extension): int
    {
        $family = $this->familyForExtension($extension);
        if ($family === null) {
            return 0;
        }

        return $this->familyMaxBytes[$family] ?? 0;
    }

    public function batchMaxBytes(): int
    {
        return $this->batchMaxBytes;
    }

    public function maxImagePixels(): int
    {
        return $this->maxImagePixels;
    }

    public function isImageExtension(string $extension): bool
    {
        $family = $this->familyForExtension($extension);

        return $family === self::FAMILY_IMAGE || $family === self::FAMILY_TIFF;
    }

    public function isOfficeContainerExtension(string $extension): bool
    {
        return $this->familyForExtension($extension) === self::FAMILY_OFFICE;
    }

    /**
     * Résumé lisible des limites effectives (page d'administration).
     *
     * @return array<string, int>
     */
    public function effectiveLimits(): array
    {
        return $this->familyMaxBytes + [
            'batch' => $this->batchMaxBytes,
            'max_image_pixels' => $this->maxImagePixels,
        ];
    }
}
