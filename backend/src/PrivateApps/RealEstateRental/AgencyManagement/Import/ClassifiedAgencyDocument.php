<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import;

final class ClassifiedAgencyDocument
{
    /**
     * @param array<int, string> $reasons
     */
    public function __construct(
        public readonly string $documentType,
        public readonly string $parserProfile,
        public readonly float $confidence,
        public readonly array $reasons = []
    ) {
    }

    public function isKnown(): bool
    {
        return $this->documentType !== 'unknown';
    }

    /**
     * @return array{documentType:string,parserProfile:string,confidence:float,reasons:array<int, string>}
     */
    public function toArray(): array
    {
        return [
            'documentType' => $this->documentType,
            'parserProfile' => $this->parserProfile,
            'confidence' => $this->confidence,
            'reasons' => $this->reasons,
        ];
    }
}
