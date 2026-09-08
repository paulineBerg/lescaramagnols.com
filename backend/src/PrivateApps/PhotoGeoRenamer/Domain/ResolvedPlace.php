<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Domain;

final class ResolvedPlace
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly string $communeName,
        public readonly ?string $countryCode,
        public readonly ?string $adminCode,
        public readonly ?string $postalCode,
        public readonly ?string $departmentCode,
        public readonly ?string $departmentName,
        public readonly ?string $regionCode,
        public readonly ?string $regionName,
        public readonly string $provider,
        public readonly string $resolvedAt
    ) {
    }

    public static function fromParts(
        float $latitude,
        float $longitude,
        mixed $communeName,
        mixed $countryCode,
        mixed $adminCode,
        mixed $postalCode,
        mixed $departmentCode,
        mixed $departmentName,
        mixed $regionCode,
        mixed $regionName,
        mixed $provider,
        ?string $resolvedAt = null
    ): ?self {
        $commune = self::short($communeName, 160);
        $providerName = self::short($provider, 80) ?? 'unknown';
        if ($commune === null) {
            return null;
        }

        return new self(
            $latitude,
            $longitude,
            $commune,
            self::countryCode($countryCode),
            self::short($adminCode, 80),
            self::short($postalCode, 16),
            self::short($departmentCode, 16),
            self::short($departmentName, 120),
            self::short($regionCode, 16),
            self::short($regionName, 120),
            $providerName,
            $resolvedAt ?? gmdate('Y-m-d H:i:s')
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'commune_name' => $this->communeName,
            'country_code' => $this->countryCode,
            'admin_code' => $this->adminCode,
            'postal_code' => $this->postalCode,
            'department_code' => $this->departmentCode,
            'department_name' => $this->departmentName,
            'region_code' => $this->regionCode,
            'region_name' => $this->regionName,
            'provider' => $this->provider,
            'resolved_at' => $this->resolvedAt,
        ];
    }

    private static function short(mixed $value, int $max): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $short = mb_substr(trim((string) $value), 0, $max);

        return $short !== '' ? $short : null;
    }

    private static function countryCode(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $country = strtoupper(trim($value));

        return preg_match('/\A[A-Z]{2}\z/', $country) === 1 ? $country : null;
    }
}
