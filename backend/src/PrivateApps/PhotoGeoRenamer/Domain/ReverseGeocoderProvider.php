<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Domain;

interface ReverseGeocoderProvider
{
    public function reverse(float $latitude, float $longitude): ?ResolvedPlace;
}
