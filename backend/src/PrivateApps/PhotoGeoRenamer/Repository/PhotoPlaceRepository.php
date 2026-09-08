<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Repository;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\PhotoCommuneNormalizer;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\PhotoGeoCacheKey;
use PDO;

final class PhotoPlaceRepository
{
    public function __construct(
        private readonly EditorialDatabase $database,
        private readonly PhotoGeoCacheKey $cacheKey = new PhotoGeoCacheKey(),
        private readonly PhotoCommuneNormalizer $communeNormalizer = new PhotoCommuneNormalizer()
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(float $latitude, float $longitude): ?array
    {
        $statement = $this->database->pdo()->prepare(
            sprintf('SELECT * FROM `%s` WHERE `geo_key` = :geo_key LIMIT 1', $this->database->table('photo_geo_places'))
        );
        $statement->execute(['geo_key' => $this->cacheKey->forCoordinates($latitude, $longitude)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $place
     */
    public function save(float $latitude, float $longitude, array $place, string $provider): void
    {
        $commune = $this->communeNormalizer->normalize($place['commune_name'] ?? $place['city'] ?? null);
        if ($commune === null) {
            return;
        }

        $statement = $this->database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`geo_key`, `latitude`, `longitude`, `commune_key`, `commune_name`, `postal_code`,
                     `department`, `region`, `country_code`, `provider`, `created_at`, `updated_at`)
                 VALUES
                    (:geo_key, :latitude, :longitude, :commune_key, :commune_name, :postal_code,
                     :department, :region, :country_code, :provider, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    `commune_key` = VALUES(`commune_key`),
                    `commune_name` = VALUES(`commune_name`),
                    `postal_code` = VALUES(`postal_code`),
                    `department` = VALUES(`department`),
                    `region` = VALUES(`region`),
                    `country_code` = VALUES(`country_code`),
                    `provider` = VALUES(`provider`),
                    `updated_at` = VALUES(`updated_at`)',
                $this->database->table('photo_geo_places')
            )
        );
        $now = gmdate('Y-m-d H:i:s');
        $statement->execute([
            'geo_key' => $this->cacheKey->forCoordinates($latitude, $longitude),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'commune_key' => $commune->key,
            'commune_name' => $commune->name,
            'postal_code' => $this->short($place['postal_code'] ?? null, 16),
            'department' => $this->short($place['department'] ?? null, 120),
            'region' => $this->short($place['region'] ?? null, 120),
            'country_code' => $this->countryCode($place['country_code'] ?? null),
            'provider' => mb_substr(trim($provider), 0, 80) ?: 'unknown',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function short(mixed $value, int $max): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $value = mb_substr(trim((string) $value), 0, $max);

        return $value !== '' ? $value : null;
    }

    private function countryCode(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = strtoupper(trim($value));

        return preg_match('/\A[A-Z]{2}\z/', $value) === 1 ? $value : null;
    }
}
