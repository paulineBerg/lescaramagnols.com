<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import;

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyImportBatch;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyImportedDocument;

final class AgencyImportResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?AgencyImportBatch $batch = null,
        public readonly ?AgencyImportedDocument $document = null,
        public readonly ?AgencyImportPreview $preview = null,
        public readonly ?string $error = null
    ) {
    }

    public function isImported(): bool
    {
        return $this->status === 'imported'
            && $this->batch instanceof AgencyImportBatch
            && $this->document instanceof AgencyImportedDocument;
    }
}
