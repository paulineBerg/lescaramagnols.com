<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Service;

use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\ResolvedPlace;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\ReverseGeocoderProvider;

final class GeoPlatformReverseGeocoderProvider implements ReverseGeocoderProvider
{
    public function __construct(private readonly PhotoGeoJsonClient $httpClient = new PhotoGeoHttpClient())
    {
    }

    public function reverse(float $latitude, float $longitude): ?ResolvedPlace
    {
        $url = 'https://data.geopf.fr/geocodage/reverse/?' . http_build_query([
            'lat' => number_format($latitude, 6, '.', ''),
            'lon' => number_format($longitude, 6, '.', ''),
            'limit' => '1',
        ]);
        $payload = $this->httpClient->getJson($url);
        if (!is_array($payload) || !is_array($payload['features'] ?? null) || !is_array($payload['features'][0]['properties'] ?? null)) {
            return null;
        }

        $properties = $payload['features'][0]['properties'];
        $context = $this->parseContext($properties['context'] ?? null);

        return ResolvedPlace::fromParts(
            $latitude,
            $longitude,
            $properties['city'] ?? null,
            'FR',
            $properties['citycode'] ?? null,
            $properties['postcode'] ?? null,
            $context['department_code'] ?? null,
            $context['department_name'] ?? null,
            null,
            $context['region_name'] ?? null,
            'data.geopf.fr'
        );
    }

    /**
     * @return array{department_code?: string, department_name?: string, region_name?: string}
     */
    private function parseContext(mixed $context): array
    {
        if (!is_string($context)) {
            return [];
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $context)), static fn (string $value): bool => $value !== ''));

        return [
            'department_code' => $parts[0] ?? null,
            'department_name' => $parts[1] ?? null,
            'region_name' => $parts[2] ?? null,
        ];
    }
}
