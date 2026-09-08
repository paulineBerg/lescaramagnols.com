<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Domain;

final class PhotoCommune
{
    public function __construct(
        public readonly string $key,
        public readonly string $name
    ) {
    }
}
