<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Service;

/**
 * Résultat de validation d'un fichier candidat à l'import.
 */
final class DocumentValidationResult
{
    private function __construct(
        public readonly bool $valid,
        public readonly string $errorCode,
        public readonly string $originalName,
        public readonly string $extension,
        public readonly string $mimeType,
        public readonly int $sizeBytes,
        public readonly string $scanStatus
    ) {
    }

    public static function rejected(string $errorCode, string $originalName = ''): self
    {
        return new self(false, $errorCode, $originalName, '', '', 0, '');
    }

    public static function accepted(
        string $originalName,
        string $extension,
        string $mimeType,
        int $sizeBytes,
        string $scanStatus
    ): self {
        return new self(true, '', $originalName, $extension, $mimeType, $sizeBytes, $scanStatus);
    }
}
