<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PrivateApps\PhotoGeoRenamer;

use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\ResolvedPlace;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\ReverseGeocoderProvider;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Repository\PhotoPlaceRepository;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Service\AdministrativePlaceResolver;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Service\FrenchGovernmentCommuneProvider;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Service\GeoPlatformReverseGeocoderProvider;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Service\NominatimReverseGeocoderProvider;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Service\PhotoGeoJsonClient;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

final class AdministrativePlaceResolverTest extends TestCase
{
    use EditorialSqlTestTrait;

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 3) . '/core/bootstrap.php';
    }

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testResolvesAndCachesAdministrativePlaceAtFiveDecimalPrecision(): void
    {
        $database = $this->editorialSqlDatabase();
        $provider = new FakeReverseGeocoderProvider(ResolvedPlace::fromParts(
            43.272681,
            6.632803,
            'Saint-Tropez',
            'FR',
            '83119',
            '83990',
            '83',
            'Var',
            '93',
            'Provence-Alpes-Cote d Azur',
            'geo.api.gouv.fr',
            '2026-09-08 10:00:00'
        ));
        $resolver = new AdministrativePlaceResolver(new PhotoPlaceRepository($database), [$provider]);

        $place = $resolver->resolve(43.272681, 6.632803);
        $again = $resolver->resolve(43.272681, 6.632803);

        $this->assertSame('Saint-Tropez', $place?->communeName);
        $this->assertSame('83119', $place?->adminCode);
        $this->assertSame('geo.api.gouv.fr', $again?->provider);
        $this->assertSame(1, $provider->calls);
        $row = $database->pdo()->query(sprintf('SELECT * FROM `%s` LIMIT 1', $database->table('photo_geo_places')))?->fetch(\PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        $this->assertSame('43.27268:6.63280', $row['geo_key'] ?? null);
        $this->assertSame('FR:83119', $row['commune_key'] ?? null);
        $this->assertSame('83119', $row['admin_code'] ?? null);
    }

    public function testResolverContinuesAcrossProvidersWithoutCachingFailures(): void
    {
        $database = $this->editorialSqlDatabase();
        $emptyProvider = new FakeReverseGeocoderProvider(null);
        $fallbackProvider = new FakeReverseGeocoderProvider(ResolvedPlace::fromParts(
            48.856614,
            2.352222,
            'Paris',
            'FR',
            '75104',
            '75004',
            '75',
            'Paris',
            null,
            'Ile-de-France',
            'data.geopf.fr'
        ));
        $resolver = new AdministrativePlaceResolver(new PhotoPlaceRepository($database), [$emptyProvider, $fallbackProvider]);

        $place = $resolver->resolve(48.856614, 2.352222);
        $again = $resolver->resolve(48.856614, 2.352222);

        $this->assertSame('Paris', $place?->communeName);
        $this->assertSame('data.geopf.fr', $place?->provider);
        $this->assertSame(1, $emptyProvider->calls);
        $this->assertSame(1, $fallbackProvider->calls);
        $this->assertSame('Paris', $again?->communeName);
    }

    public function testRepositoryMigratesLegacyPlaceCacheTable(): void
    {
        $database = $this->editorialSqlDatabase();
        $table = $database->table('photo_geo_places');
        $database->pdo()->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        $database->pdo()->exec(sprintf(
            'CREATE TABLE `%s` (
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
                `provider` VARCHAR(80) NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_photo_geo_places_geo_key` (`geo_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $table
        ));

        $repository = new PhotoPlaceRepository($database);
        $repository->save(ResolvedPlace::fromParts(
            43.231381,
            6.518497,
            'Cogolin',
            'FR',
            '83042',
            '83310',
            '83',
            'Var',
            '93',
            'Provence-Alpes-Cote d Azur',
            'geo.api.gouv.fr'
        ));
        $place = $repository->find(43.231381, 6.518497);

        $this->assertSame('Cogolin', $place?->communeName);
        $this->assertSame('83042', $place?->adminCode);
        $this->assertSame('Var', $place?->departmentName);
    }

    public function testFrenchGovernmentProviderParsesOfficialCommunePayload(): void
    {
        $provider = new FrenchGovernmentCommuneProvider(new FakeJsonClient([
            [
                'nom' => 'Saint-Tropez',
                'code' => '83119',
                'codesPostaux' => ['83990'],
                'codeDepartement' => '83',
                'codeRegion' => '93',
                'departement' => ['code' => '83', 'nom' => 'Var'],
                'region' => ['code' => '93', 'nom' => 'Provence-Alpes-Cote d Azur'],
            ],
        ]));

        $place = $provider->reverse(43.272681, 6.632803);

        $this->assertSame('Saint-Tropez', $place?->communeName);
        $this->assertSame('FR', $place?->countryCode);
        $this->assertSame('83119', $place?->adminCode);
        $this->assertSame('geo.api.gouv.fr', $place?->provider);
    }

    public function testGeoPlatformProviderParsesReverseGeocodePayload(): void
    {
        $provider = new GeoPlatformReverseGeocoderProvider(new FakeJsonClient([
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'properties' => [
                        'city' => 'Paris',
                        'citycode' => '75104',
                        'postcode' => '75004',
                        'context' => '75, Paris, Ile-de-France',
                    ],
                ],
            ],
        ]));

        $place = $provider->reverse(48.856614, 2.352222);

        $this->assertSame('Paris', $place?->communeName);
        $this->assertSame('75104', $place?->adminCode);
        $this->assertSame('data.geopf.fr', $place?->provider);
    }

    public function testNominatimProviderParsesInternationalPayload(): void
    {
        $provider = new NominatimReverseGeocoderProvider(new FakeJsonClient([
            'osm_type' => 'relation',
            'osm_id' => 71525,
            'address' => [
                'city' => 'New York',
                'postcode' => '10007',
                'county' => 'New York County',
                'state' => 'New York',
                'country_code' => 'us',
            ],
        ]));

        $place = $provider->reverse(40.7128, -74.0060);

        $this->assertSame('New York', $place?->communeName);
        $this->assertSame('US', $place?->countryCode);
        $this->assertSame('relation:71525', $place?->adminCode);
        $this->assertSame('nominatim.openstreetmap.org', $place?->provider);
    }
}

final class FakeReverseGeocoderProvider implements ReverseGeocoderProvider
{
    public int $calls = 0;

    public function __construct(private readonly ?ResolvedPlace $place)
    {
    }

    public function reverse(float $latitude, float $longitude): ?ResolvedPlace
    {
        $this->calls++;

        return $this->place;
    }
}

final class FakeJsonClient implements PhotoGeoJsonClient
{
    /**
     * @param array<string, mixed>|array<int, mixed> $payload
     */
    public function __construct(private readonly array $payload)
    {
    }

    /**
     * @return array<string, mixed>|array<int, mixed>|null
     */
    public function getJson(string $url): ?array
    {
        return $this->payload;
    }
}
