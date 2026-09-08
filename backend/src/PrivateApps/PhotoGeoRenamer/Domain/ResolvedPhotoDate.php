<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Domain;

final class ResolvedPhotoDate
{
    public function __construct(
        public readonly ?\DateTimeImmutable $takenAt,
        public readonly string $source
    ) {
    }

    public function timestamp(): ?int
    {
        return $this->takenAt instanceof \DateTimeImmutable ? $this->takenAt->getTimestamp() : null;
    }

    public function sqlValue(): ?string
    {
        return $this->takenAt instanceof \DateTimeImmutable
            ? $this->takenAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')
            : null;
    }
}
