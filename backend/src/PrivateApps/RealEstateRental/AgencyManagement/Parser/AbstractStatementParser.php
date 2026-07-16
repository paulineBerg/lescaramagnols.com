<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Parser;

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import\AgencyLineCategoryGuesser;

abstract class AbstractStatementParser
{
    protected AgencyLineCategoryGuesser $categoryGuesser;

    public function __construct(?AgencyLineCategoryGuesser $categoryGuesser = null)
    {
        $this->categoryGuesser = $categoryGuesser ?? new AgencyLineCategoryGuesser();
    }

    /**
     * @return array<int, string>
     */
    protected function lines(string $text): array
    {
        return array_values(array_filter(
            array_map(static fn (string $line): string => trim($line), preg_split('/\R/u', $text) ?: []),
            static fn (string $line): bool => $line !== ''
        ));
    }

    /**
     * @return array<int, float>
     */
    protected function amounts(string $line): array
    {
        preg_match_all('/-?\d{1,3}(?:[ \x{00A0}]?\d{3})*(?:[,.]\d{2})/u', $line, $matches);
        $amounts = [];
        foreach ($matches[0] as $match) {
            $amounts[] = $this->amount($match);
        }

        return $amounts;
    }

    protected function amount(string $value): float
    {
        $value = str_replace(["\xc2\xa0", ' '], '', trim($value));
        $value = str_replace(',', '.', $value);
        return round((float) $value, 2);
    }

    protected function dateFrToSql(string $value): ?string
    {
        if (preg_match('/\A(\d{2})\/(\d{2})\/(\d{4})\z/', trim($value), $matches) !== 1) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', (int) $matches[3], (int) $matches[2], (int) $matches[1]);
    }

    protected function lineHash(string $line): string
    {
        return hash('sha256', trim($line));
    }

    protected function sourcePageFromLine(string $line, int $currentPage): int
    {
        if (preg_match('/Page\s+-?\s*-?(\d+)/iu', $line, $matches) === 1) {
            return max(1, (int) $matches[1]);
        }

        return $currentPage;
    }
}
