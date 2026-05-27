<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\TaxDeclarationHelper\ValueObject;

final class MissingTaxDocument
{
    public function __construct(
        public readonly int $year,
        public readonly string $documentType,
        public readonly string $label,
        public readonly string $severity,
        public readonly string $sourceReference
    ) {
    }

    public function isBlocking(): bool
    {
        return $this->severity === 'blocking';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'year' => $this->year,
            'documentType' => $this->documentType,
            'label' => $this->label,
            'severity' => $this->severity,
            'sourceReference' => $this->sourceReference,
            'blocking' => $this->isBlocking(),
        ];
    }
}
