<?php

declare(strict_types=1);

namespace Caramagnols\PbGestion\Photo;

interface ReverseGeocoderProvider
{
    /**
     * @return array{city?: string, department?: string, region?: string, country?: string}
     */
    public function reverse(float $latitude, float $longitude): array;
}
