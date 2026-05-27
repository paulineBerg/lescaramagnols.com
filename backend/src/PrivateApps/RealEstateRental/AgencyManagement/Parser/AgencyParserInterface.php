<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Parser;

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import\ClassifiedAgencyDocument;

interface AgencyParserInterface
{
    public function supports(ClassifiedAgencyDocument $document): bool;

    /**
     * @param array<string, mixed> $metadata
     */
    public function parse(string $text, array $metadata = []): AgencyParserResult;
}
