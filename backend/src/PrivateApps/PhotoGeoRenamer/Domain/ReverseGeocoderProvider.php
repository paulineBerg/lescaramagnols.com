<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Domain;

interface ReverseGeocoderProvider
{
    /**
     * @return array{city?: string, department?: string, region?: string, country?: string}
     */
    public function reverse(float $latitude, float $longitude): array;
}
