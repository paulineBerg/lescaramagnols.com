<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental;

final class PrivateAppManifest implements
    \Caramagnols\PrivatePortal\PrivateAppManifest,
    \Caramagnols\PrivateApps\Documents\Contract\ProvidesDocumentIntegration
{
    public function migrationCode(): string
    {
        return 'real_estate_rental';
    }

    public function moduleCode(): string
    {
        return 'real_estate_rental';
    }

    public function moduleName(): string
    {
        return 'Locations immobilières';
    }

    public function moduleDescription(): string
    {
        return 'Biens, lots, membres, locataires, baux, loyers, paiements, charges, documents et rapports.';
    }

    public function modulePermissionCode(): string
    {
        return 'real_estate_rental';
    }

    public function migrationStatusCode(): string
    {
        return 'real_estate_rental';
    }

    public function title(): string
    {
        return 'RealEstateRental';
    }

    public function order(): int
    {
        return 4;
    }

    /**
     * @return array<int, string>
     */
    public function routeNames(): array
    {
        return [
            'rental_dashboard',
            'rental_lessors',
            'rental_properties',
            'rental_units',
            'rental_property_members',
            'rental_tenants',
            'rental_leases',
            'rental_rents',
            'rental_payments',
            'rental_expenses',
            'rental_regularizations',
            'rental_documents',
            'rental_summary',
            'rental_export_csv',
            'rental_export_pdf',
            'rental_export_zip',
            'rental_agencies',
            'rental_agency_imports',
            'rental_agency_review',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function tables(): array
    {
        return [
            'rental_lessors',
            'rental_properties',
            'rental_units',
            'rental_property_members',
            'rental_tenants',
            'rental_leases',
            'rental_rents',
            'rental_payments',
            'rental_payment_requests',
            'rental_expenses',
            'rental_documents',
            'rental_generated_documents',
            'rental_charge_regularizations',
            'rental_export_logs',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function contractClasses(): array
    {
        return [
            'Caramagnols\\PrivateApps\\RealEstateRental\\Repository\\RentalLessorRepository',
            'Caramagnols\\PrivateApps\\RealEstateRental\\Repository\\RentalPropertyRepository',
            'Caramagnols\\PrivateApps\\RealEstateRental\\Repository\\RentalUnitRepository',
            'Caramagnols\\PrivateApps\\RealEstateRental\\Repository\\RentalLifecycleRepository',
            'Caramagnols\\PrivateApps\\RealEstateRental\\Repository\\RentalPropertyMemberRepository',
            'Caramagnols\\PrivateApps\\RealEstateRental\\Service\\RentalDashboardService',
            'Caramagnols\\PrivateApps\\RealEstateRental\\Service\\RentalExportService',
            'Caramagnols\\PrivateApps\\RealEstateRental\\Service\\RentalAnnualSummaryService',
            'Caramagnols\\PrivateApps\\RealEstateRental\\Service\\RentalPaymentRequestService',
            'Caramagnols\\PrivateApps\\RealEstateRental\\Service\\RentalReceiptService',
            'Caramagnols\\PrivateApps\\RealEstateRental\\Service\\ChargeRegularizationService',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function testClasses(): array
    {
        return [
            'RealEstateRentalModuleTest',
            'RentalLifecycleTest',
            'RentalPaymentRequestServiceTest',
            'RentalReceiptServiceTest',
            'RentalExpenseCategoryTest',
            'ChargeRegularizationServiceTest',
            'RentalDashboardServiceTest',
            'TaxSummaryServiceTest',
            'RentalExportServiceTest',
            'PrivatePortalPhaseCoverageTest',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function auditEvents(): array
    {
        return [
            'private.rental.property_created',
            'private.rental.unit_created',
            'private.rental.document_uploaded',
            'private.rental_charge_regularization.generated',
            'private.rental.export_created',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function uiStates(): array
    {
        return ['empty', 'error', 'success'];
    }

    /**
     * @return array<int, string>
     */
    public function legacyRoutes(): array
    {
        return ['rental PHP routes stay behind PrivateRouteResolver'];
    }

    /**
     * @return array<string, string>
     */
    public function routePaths(): array
    {
        return [
            'rental_dashboard' => 'locations',
            'rental_lessors' => 'locations/bailleurs',
            'rental_properties' => 'locations/biens',
            'rental_units' => 'locations/lots',
            'rental_property_members' => 'locations/membres',
            'rental_tenants' => 'locations/locataires',
            'rental_leases' => 'locations/baux',
            'rental_rents' => 'locations/loyers',
            'rental_payments' => 'locations/paiements',
            'rental_expenses' => 'locations/charges',
            'rental_regularizations' => 'locations/regularisations',
            'rental_documents' => 'locations/documents',
            'rental_summary' => 'locations/synthese',
            'rental_agencies' => 'locations/agences',
            'rental_agency_imports' => 'locations/imports',
            'rental_agency_review' => 'locations/revue',
        ];
    }

    /**
     * @return array{label: string, description: string, stat_code: string}
     */
    public function dashboardTileData(): array
    {
        return [
            'label' => 'Locations',
            'description' => 'Gestion complète de votre patrimoine locatif',
            'stat_code' => 'private.rental.property_count',
        ];
    }

    public function notes(): string
    {
        return 'Les exports restent regenerables depuis les tables locatives source.';
    }

    public function documentIntegration(): \Caramagnols\PrivateApps\Documents\Contract\DocumentIntegration
    {
        return new RentalDocumentIntegration();
    }
}
