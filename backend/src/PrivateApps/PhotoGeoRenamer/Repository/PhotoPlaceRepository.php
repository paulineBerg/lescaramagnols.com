<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Repository;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\PhotoCommuneNormalizer;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\PhotoGeoCacheKey;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\ResolvedPlace;
use PDO;

final class PhotoPlaceRepository
{
    private bool $schemaReady = false;

    public function __construct(
        private readonly EditorialDatabase $database,
        private readonly PhotoGeoCacheKey $cacheKey = new PhotoGeoCacheKey(),
        private readonly PhotoCommuneNormalizer $communeNormalizer = new PhotoCommuneNormalizer()
    ) {
    }

    public function find(float $latitude, float $longitude): ?ResolvedPlace
    {
        $this->ensureSchema();
        $statement = $this->database->pdo()->prepare(
            sprintf('SELECT * FROM `%s` WHERE `geo_key` = :geo_key LIMIT 1', $this->database->table('photo_geo_places'))
        );
        $statement->execute(['geo_key' => $this->cacheKey->forCoordinates($latitude, $longitude)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return ResolvedPlace::fromParts(
            (float) ($row['latitude'] ?? $latitude),
            (float) ($row['longitude'] ?? $longitude),
            $row['commune_name'] ?? null,
            $row['country_code'] ?? null,
            $row['admin_code'] ?? null,
            $row['postal_code'] ?? null,
            $row['department_code'] ?? null,
            $row['department_name'] ?? ($row['department'] ?? null),
            $row['region_code'] ?? null,
            $row['region_name'] ?? ($row['region'] ?? null),
            $row['provider'] ?? null,
            is_string($row['resolved_at'] ?? null) ? (string) $row['resolved_at'] : null
        );
    }

    public function save(ResolvedPlace $place): void
    {
        $this->ensureSchema();
        $commune = $this->communeNormalizer->normalize($place->communeName);
        if ($commune === null) {
            return;
        }

        $communeKey = $this->communeKey($place, $commune->key);
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`geo_key`, `latitude`, `longitude`, `commune_key`, `commune_name`, `postal_code`,
                     `department`, `region`, `country_code`, `admin_code`, `department_code`, `department_name`,
                     `region_code`, `region_name`, `provider`, `resolved_at`, `created_at`, `updated_at`)
                 VALUES
                    (:geo_key, :latitude, :longitude, :commune_key, :commune_name, :postal_code,
                     :department, :region, :country_code, :admin_code, :department_code, :department_name,
                     :region_code, :region_name, :provider, :resolved_at, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    `commune_key` = VALUES(`commune_key`),
                    `commune_name` = VALUES(`commune_name`),
                    `postal_code` = VALUES(`postal_code`),
                    `department` = VALUES(`department`),
                    `region` = VALUES(`region`),
                    `country_code` = VALUES(`country_code`),
                    `admin_code` = VALUES(`admin_code`),
                    `department_code` = VALUES(`department_code`),
                    `department_name` = VALUES(`department_name`),
                    `region_code` = VALUES(`region_code`),
                    `region_name` = VALUES(`region_name`),
                    `provider` = VALUES(`provider`),
                    `resolved_at` = VALUES(`resolved_at`),
                    `updated_at` = VALUES(`updated_at`)',
                $this->database->table('photo_geo_places')
            )
        );
        $now = gmdate('Y-m-d H:i:s');
        $statement->execute([
            'geo_key' => $this->cacheKey->forCoordinates($place->latitude, $place->longitude),
            'latitude' => $place->latitude,
            'longitude' => $place->longitude,
            'commune_key' => $communeKey,
            'commune_name' => $commune->name,
            'postal_code' => $place->postalCode,
            'department' => $place->departmentName,
            'region' => $place->regionName,
            'country_code' => $place->countryCode,
            'admin_code' => $place->adminCode,
            'department_code' => $place->departmentCode,
            'department_name' => $place->departmentName,
            'region_code' => $place->regionCode,
            'region_name' => $place->regionName,
            'provider' => $place->provider,
            'resolved_at' => $place->resolvedAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        $this->database->ensureReady();
        $pdo = $this->database->pdo();
        $table = $this->database->table('photo_geo_places');
        $pdo->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `geo_key` VARCHAR(64) NOT NULL,
                `latitude` DECIMAL(10,7) NOT NULL,
                `longitude` DECIMAL(10,7) NOT NULL,
                `commune_key` VARCHAR(160) NOT NULL,
                `commune_name` VARCHAR(160) NOT NULL,
                `postal_code` VARCHAR(16) NULL,
                `department` VARCHAR(120) NULL,
                `region` VARCHAR(120) NULL,
                `country_code` CHAR(2) NULL,
                `admin_code` VARCHAR(80) NULL,
                `department_code` VARCHAR(16) NULL,
                `department_name` VARCHAR(120) NULL,
                `region_code` VARCHAR(16) NULL,
                `region_name` VARCHAR(120) NULL,
                `provider` VARCHAR(80) NOT NULL,
                `resolved_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_photo_geo_places_geo_key` (`geo_key`),
                KEY `idx_photo_geo_places_commune` (`commune_key`),
                KEY `idx_photo_geo_places_admin_code` (`admin_code`),
                KEY `idx_photo_geo_places_country_code` (`country_code`),
                KEY `idx_photo_geo_places_updated` (`updated_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $table
        ));

        $this->ensureColumn($pdo, $table, 'admin_code', '`admin_code` VARCHAR(80) NULL');
        $this->ensureColumn($pdo, $table, 'department_code', '`department_code` VARCHAR(16) NULL');
        $this->ensureColumn($pdo, $table, 'department_name', '`department_name` VARCHAR(120) NULL');
        $this->ensureColumn($pdo, $table, 'region_code', '`region_code` VARCHAR(16) NULL');
        $this->ensureColumn($pdo, $table, 'region_name', '`region_name` VARCHAR(120) NULL');
        $this->ensureColumn($pdo, $table, 'resolved_at', '`resolved_at` DATETIME NULL');
        $this->ensureIndex($pdo, $table, 'idx_photo_geo_places_admin_code', '`admin_code`');
        $this->ensureIndex($pdo, $table, 'idx_photo_geo_places_country_code', '`country_code`');
        $this->schemaReady = true;
    }

    private function communeKey(ResolvedPlace $place, string $fallbackKey): string
    {
        if ($place->countryCode !== null && $place->adminCode !== null) {
            return mb_substr($place->countryCode . ':' . $place->adminCode, 0, 160);
        }

        return $fallbackKey;
    }

    private function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        try {
            $statement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND COLUMN_NAME = :column'
            );
            $statement->execute(['table' => $table, 'column' => $column]);
            if ((int) $statement->fetchColumn() > 0) {
                return;
            }

            $pdo->exec(sprintf('ALTER TABLE `%s` ADD COLUMN %s', $table, $definition));
        } catch (\Throwable) {
            return;
        }
    }

    private function ensureIndex(PDO $pdo, string $table, string $index, string $columns): void
    {
        try {
            $statement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND INDEX_NAME = :index_name'
            );
            $statement->execute(['table' => $table, 'index_name' => $index]);
            if ((int) $statement->fetchColumn() > 0) {
                return;
            }

            $pdo->exec(sprintf('ALTER TABLE `%s` ADD INDEX `%s` (%s)', $table, $index, $columns));
        } catch (\Throwable) {
            return;
        }
    }
}
