<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import;

final class AgencyLineCategoryGuesser
{
    /**
     * @var array<int, \Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyLineMapping>
     */
    private array $mappings;

    public function __construct(?array $mappings = null)
    {
        $this->mappings = $mappings ?? DefaultAgencyLineMappings::all();
    }

    public function guess(string $rawLabel): string
    {
        $label = $this->normalize($rawLabel);

        foreach ($this->mappings as $mapping) {
            if (str_contains($label, $this->normalize($mapping->rawLabelPattern))) {
                return $mapping->mappedCategory;
            }
        }

        return 'other';
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'à' => 'a',
            'â' => 'a',
            'ä' => 'a',
            'ç' => 'c',
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'î' => 'i',
            'ï' => 'i',
            'ô' => 'o',
            'ö' => 'o',
            'ù' => 'u',
            'û' => 'u',
            'ü' => 'u',
        ]);
        return preg_replace('/\s+/u', ' ', $value) ?: '';
    }
}
