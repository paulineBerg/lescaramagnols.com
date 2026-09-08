<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Domain;

final class SequenceReservation
{
    /**
     * @param array<int, int> $numbers
     */
    public function __construct(
        public readonly string $communeKey,
        public readonly string $communeName,
        public readonly int $firstNumber,
        public readonly int $lastNumber,
        public readonly array $numbers
    ) {
    }
}
