<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\TaxDeclarationHelper\Source;

use Caramagnols\PrivateApps\RealEstateRental\TaxBridge\RentalTaxDataProviderInterface;
use Caramagnols\PrivatePortal\TaxDeclarationHelper\ValueObject\AnnualDeductibleExpenses;
use Caramagnols\PrivatePortal\TaxDeclarationHelper\ValueObject\AnnualRentalIncome;
use Caramagnols\PrivatePortal\TaxDeclarationHelper\ValueObject\MissingTaxDocument;

final class RentalTaxDataSource implements TaxDataSourceInterface
{
    public function __construct(private readonly RentalTaxDataProviderInterface $provider)
    {
    }

    public function code(): string
    {
        return 'real_estate_rental';
    }

    public function label(): string
    {
        return 'Locations immobilieres';
    }

    public function annualRentalIncome(int $year, array $scopeIds): AnnualRentalIncome
    {
        return $this->provider->annualRentalIncome($year, $scopeIds);
    }

    public function annualDeductibleExpenses(int $year, array $scopeIds): AnnualDeductibleExpenses
    {
        return $this->provider->annualDeductibleExpenses($year, $scopeIds);
    }

    public function missingDocuments(int $year, array $scopeIds): array
    {
        return $this->provider->missingTaxDocuments($year, $scopeIds);
    }

    public function controls(int $year, array $scopeIds): array
    {
        return $this->provider->controls($year, $scopeIds);
    }
}
