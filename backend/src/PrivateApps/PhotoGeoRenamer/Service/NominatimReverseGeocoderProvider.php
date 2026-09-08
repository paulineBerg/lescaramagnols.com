<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Service;

use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\ResolvedPlace;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\ReverseGeocoderProvider;

final class NominatimReverseGeocoderProvider implements ReverseGeocoderProvider
{
    public function __construct(private readonly PhotoGeoJsonClient $httpClient = new PhotoGeoHttpClient())
    {
    }

    public function reverse(float $latitude, float $longitude): ?ResolvedPlace
    {
        $url = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query([
            'format' => 'jsonv2',
            'lat' => number_format($latitude, 6, '.', ''),
            'lon' => number_format($longitude, 6, '.', ''),
            'zoom' => '10',
            'addressdetails' => '1',
            'accept-language' => 'fr',
        ]);
        $payload = $this->httpClient->getJson($url);
        if (!is_array($payload) || !is_array($payload['address'] ?? null)) {
            return null;
        }

        $address = $payload['address'];
        $commune = null;
        foreach (['city', 'town', 'village', 'municipality', 'hamlet', 'locality', 'county'] as $key) {
            if (is_string($address[$key] ?? null) && trim((string) $address[$key]) !== '') {
                $commune = (string) $address[$key];
                break;
            }
        }

        $adminCode = null;
        if (is_string($payload['osm_type'] ?? null) && is_numeric($payload['osm_id'] ?? null)) {
            $adminCode = strtolower((string) $payload['osm_type']) . ':' . (string) $payload['osm_id'];
        }

        return ResolvedPlace::fromParts(
            $latitude,
            $longitude,
            $commune,
            $address['country_code'] ?? null,
            $adminCode,
            $address['postcode'] ?? null,
            null,
            $address['county'] ?? null,
            null,
            $address['state'] ?? null,
            'nominatim.openstreetmap.org'
        );
    }
}
