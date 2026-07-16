<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Parser;

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyImportIssue;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyStatementLineDraft;

final class AgencyParserResult
{
    /**
     * @param array<string, mixed> $extractedFields
     * @param array<int, AgencyStatementLineDraft> $statementLines
     * @param array<int, AgencyImportIssue> $issues
     */
    public function __construct(
        public readonly string $documentType,
        public readonly string $parserProfile,
        public readonly float $confidence,
        public readonly array $extractedFields = [],
        public readonly array $statementLines = [],
        public readonly array $issues = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'documentType' => $this->documentType,
            'parserProfile' => $this->parserProfile,
            'confidence' => $this->confidence,
            'extractedFields' => $this->extractedFields,
            'statementLines' => array_map(static fn (AgencyStatementLineDraft $line): array => $line->toArray(), $this->statementLines),
            'issues' => array_map(static fn (AgencyImportIssue $issue): array => $issue->toArray(), $this->issues),
        ];
    }
}
