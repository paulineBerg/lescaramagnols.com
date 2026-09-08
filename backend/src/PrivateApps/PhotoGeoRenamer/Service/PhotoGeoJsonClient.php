<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Service;

interface PhotoGeoJsonClient
{
    /**
     * @return array<string, mixed>|array<int, mixed>|null
     */
    public function getJson(string $url): ?array;
}
