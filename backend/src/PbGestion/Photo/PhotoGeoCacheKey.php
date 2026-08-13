<?php

declare(strict_types=1);

namespace Caramagnols\PbGestion\Photo;

final class PhotoGeoCacheKey
{
    public function forCoordinates(float $latitude, float $longitude, int $precision = 3): string
    {
        $precision = max(1, min(5, $precision));

        return number_format(round($latitude, $precision), $precision, '.', '')
            . ':'
            . number_format(round($longitude, $precision), $precision, '.', '');
    }
}
