<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Service;

use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\ResolvedPlace;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\ReverseGeocoderProvider;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Repository\PhotoPlaceRepository;

final class AdministrativePlaceResolver
{
    /**
     * @param iterable<ReverseGeocoderProvider> $providers
     */
    public function __construct(
        private readonly PhotoPlaceRepository $repository,
        private readonly iterable $providers,
        private readonly ?AppEventLogger $eventLogger = null
    ) {
    }

    public function resolve(float $latitude, float $longitude): ?ResolvedPlace
    {
        $cached = $this->repository->find($latitude, $longitude);
        if ($cached instanceof ResolvedPlace) {
            $this->log('photo_geo.geocode.cache_hit', ['provider' => $cached->provider], 'info');

            return $cached;
        }

        $this->log('photo_geo.geocode.cache_miss', [], 'info');
        foreach ($this->providers as $provider) {
            try {
                $place = $provider->reverse($latitude, $longitude);
            } catch (\Throwable $exception) {
                $this->log('photo_geo.geocode.provider_error', [
                    'provider' => $provider::class,
                    'error' => $exception::class,
                ], 'warning');
                continue;
            }

            if ($place instanceof ResolvedPlace) {
                $this->repository->save($place);
                $this->log('photo_geo.geocode.resolved', [
                    'provider' => $place->provider,
                    'country_code' => $place->countryCode,
                    'admin_code' => $place->adminCode,
                ], 'info');

                return $place;
            }
        }

        $this->log('photo_geo.geocode.unresolved', [], 'warning');

        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $event, array $context, string $level): void
    {
        if (!$this->eventLogger instanceof AppEventLogger) {
            return;
        }

        $this->eventLogger->security($event, $context, $level);
    }
}
