<?php

declare(strict_types=1);

namespace Caramagnols\SecurityCenter\Dashboard;

final class CoverageCalculator
{
    public function coverageState(?string $lastSeenAt, int $expectedIntervalSeconds = 300): string
    {
        $age = $this->ageSeconds($lastSeenAt);
        if ($age === null) {
            return 'interrupted';
        }

        if ($age <= $expectedIntervalSeconds * 2) {
            return 'complete';
        }

        if ($age <= $expectedIntervalSeconds * 6) {
            return 'partial';
        }

        return 'interrupted';
    }

    public function ageLabel(?string $dateTime): string
    {
        $age = $this->ageSeconds($dateTime);
        if ($age === null) {
            return 'jamais';
        }

        if ($age < 60) {
            return $age . ' s';
        }

        if ($age < 3600) {
            return (int) floor($age / 60) . ' min';
        }

        if ($age < 86400) {
            return (int) floor($age / 3600) . ' h';
        }

        return (int) floor($age / 86400) . ' j';
    }

    private function ageSeconds(?string $dateTime): ?int
    {
        if (!is_string($dateTime) || trim($dateTime) === '') {
            return null;
        }

        $timestamp = strtotime($dateTime . ' UTC');
        if ($timestamp === false) {
            $timestamp = strtotime($dateTime);
        }

        return $timestamp !== false ? max(0, time() - $timestamp) : null;
    }
}
