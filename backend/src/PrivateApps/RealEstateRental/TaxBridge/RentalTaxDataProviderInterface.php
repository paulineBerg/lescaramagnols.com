<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\TaxBridge;

use Caramagnols\PrivateApps\TaxDeclarationHelper\ValueObject\AnnualDeductibleExpenses;
use Caramagnols\PrivateApps\TaxDeclarationHelper\ValueObject\AnnualRentalIncome;
use Caramagnols\PrivateApps\TaxDeclarationHelper\ValueObject\MissingTaxDocument;

interface RentalTaxDataProviderInterface
{
    /**
     * @param array<int, int> $propertyIds
     */
    public function annualRentalIncome(int $year, array $propertyIds): AnnualRentalIncome;

    /**
     * @param array<int, int> $propertyIds
     */
    public function annualDeductibleExpenses(int $year, array $propertyIds): AnnualDeductibleExpenses;

    /**
     * @param array<int, int> $propertyIds
     * @return array<int, MissingTaxDocument>
     */
    public function missingTaxDocuments(int $year, array $propertyIds): array;

    /**
     * @param array<int, int> $propertyIds
     * @return array<int, string>
     */
    public function controls(int $year, array $propertyIds): array;
}
