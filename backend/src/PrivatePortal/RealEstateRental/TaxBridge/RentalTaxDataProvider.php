<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\RealEstateRental\TaxBridge;

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Service\AgencyTaxBridgeNormalizer;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalAnnualSummaryService;
use Caramagnols\PrivateApps\RealEstateRental\TaxBridge\RentalTaxDataProvider as AppsRentalTaxDataProvider;
use Caramagnols\PrivatePortal\TaxDeclarationHelper\ValueObject\AnnualDeductibleExpenses;
use Caramagnols\PrivatePortal\TaxDeclarationHelper\ValueObject\AnnualRentalIncome;
use Caramagnols\PrivatePortal\TaxDeclarationHelper\ValueObject\MissingTaxDocument;

final class RentalTaxDataProvider implements RentalTaxDataProviderInterface
{
    private readonly AppsRentalTaxDataProvider $provider;

    public function __construct(
        RentalAnnualSummaryService $summaryService,
        RentalLifecycleRepository $lifecycleRepository,
        ?AgencyTaxBridgeNormalizer $agencyTaxBridgeNormalizer = null
    ) {
        $this->provider = new AppsRentalTaxDataProvider(
            $summaryService,
            $lifecycleRepository,
            $agencyTaxBridgeNormalizer
        );
    }

    public function annualRentalIncome(int $year, array $propertyIds): AnnualRentalIncome
    {
        return $this->provider->annualRentalIncome($year, $propertyIds);
    }

    public function annualDeductibleExpenses(int $year, array $propertyIds): AnnualDeductibleExpenses
    {
        return $this->provider->annualDeductibleExpenses($year, $propertyIds);
    }

    public function missingTaxDocuments(int $year, array $propertyIds): array
    {
        return $this->provider->missingTaxDocuments($year, $propertyIds);
    }

    public function controls(int $year, array $propertyIds): array
    {
        return $this->provider->controls($year, $propertyIds);
    }
}
