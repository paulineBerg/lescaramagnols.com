<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\TaxDeclarationHelper\Source;

use Caramagnols\PrivateApps\TaxDeclarationHelper\ValueObject\AnnualDeductibleExpenses;
use Caramagnols\PrivateApps\TaxDeclarationHelper\ValueObject\AnnualRentalIncome;
use Caramagnols\PrivateApps\TaxDeclarationHelper\ValueObject\MissingTaxDocument;

interface TaxDataSourceInterface
{
    public function code(): string;

    public function label(): string;

    /**
     * @param array<int, int> $scopeIds
     */
    public function annualRentalIncome(int $year, array $scopeIds): AnnualRentalIncome;

    /**
     * @param array<int, int> $scopeIds
     */
    public function annualDeductibleExpenses(int $year, array $scopeIds): AnnualDeductibleExpenses;

    /**
     * @param array<int, int> $scopeIds
     * @return array<int, MissingTaxDocument>
     */
    public function missingDocuments(int $year, array $scopeIds): array;

    /**
     * @param array<int, int> $scopeIds
     * @return array<int, string>
     */
    public function controls(int $year, array $scopeIds): array;
}
