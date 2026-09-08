<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Service;

use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\ResolvedPlace;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\ReverseGeocoderProvider;

final class FrenchGovernmentCommuneProvider implements ReverseGeocoderProvider
{
    public function __construct(private readonly PhotoGeoJsonClient $httpClient = new PhotoGeoHttpClient())
    {
    }

    public function reverse(float $latitude, float $longitude): ?ResolvedPlace
    {
        $url = 'https://geo.api.gouv.fr/communes?' . http_build_query([
            'lat' => number_format($latitude, 6, '.', ''),
            'lon' => number_format($longitude, 6, '.', ''),
            'fields' => 'nom,code,codesPostaux,codeDepartement,codeRegion,departement,region',
            'format' => 'json',
            'geometry' => 'centre',
        ]);
        $payload = $this->httpClient->getJson($url);
        if (!is_array($payload) || !is_array($payload[0] ?? null)) {
            return null;
        }

        $row = $payload[0];
        $postCodes = is_array($row['codesPostaux'] ?? null) ? $row['codesPostaux'] : [];
        $department = is_array($row['departement'] ?? null) ? $row['departement'] : [];
        $region = is_array($row['region'] ?? null) ? $row['region'] : [];

        return ResolvedPlace::fromParts(
            $latitude,
            $longitude,
            $row['nom'] ?? null,
            'FR',
            $row['code'] ?? null,
            $postCodes[0] ?? null,
            $row['codeDepartement'] ?? ($department['code'] ?? null),
            $department['nom'] ?? null,
            $row['codeRegion'] ?? ($region['code'] ?? null),
            $region['nom'] ?? null,
            'geo.api.gouv.fr'
        );
    }
}
