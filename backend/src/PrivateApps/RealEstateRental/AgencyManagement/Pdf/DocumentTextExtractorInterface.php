<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf;

interface DocumentTextExtractorInterface
{
    public function supports(string $path, string $mimeType): bool;

    public function extract(string $path): ExtractedTextResult;
}
