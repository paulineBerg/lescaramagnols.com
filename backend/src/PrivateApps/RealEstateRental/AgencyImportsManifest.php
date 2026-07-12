<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental;

final class AgencyImportsManifest implements \Caramagnols\PrivatePortal\PrivateAppManifest
{
    public function migrationCode(): string
    {
        return 'agency_imports';
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
        return 'Imports agence, documents detectes, lignes, anomalies, mapping et revue humaine.';
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
        return 'AgencyImports';
    }

    public function order(): int
    {
        return 5;
    }

    /**
     * @return array<int, string>
     */
    public function routeNames(): array
    {
        return [
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
            'rental_agencies',
            'rental_agency_import_batches',
            'rental_agency_imported_documents',
            'rental_agency_statements',
            'rental_agency_statement_lines',
            'rental_agency_import_issues',
            'rental_agency_unit_mappings',
            'rental_agency_line_mappings',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function contractClasses(): array
    {
        return [
            'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Import\\AgencyImportService',
            'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Import\\AgencyImportPreviewService',
            'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Domain\\AgencyFiscalReviewPolicy',
            'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Repository\\AgencyImportRepository',
            'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Repository\\AgencyMappingRepository',
            'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Service\\AgencyAdvancedReconciliationService',
            'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Service\\AgencyStatementValidationService',
            'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Import\\AgencySensitiveDataMasker',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function testClasses(): array
    {
        return [
            'PrivatePortalPhaseCoverageTest',
            'AgencyDocumentClassifierTest',
            'AgencyImportPreviewServiceTest',
            'AgencyImportRepositoryTest',
            'AgencyImportServiceTest',
            'AgencyAdvancedReconciliationServiceTest',
            'AgencyFiscalReviewPolicyTest',
            'AgencySensitiveDataMaskerTest',
            'AgencyStatementValidationServiceTest',
            'AgencyTaxBridgeNormalizerTest',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function auditEvents(): array
    {
        return [
            'private.rental.agency_imported',
            'private.rental.agency_reviewed',
            'private.rental.agency_issue_created',
            'private.rental_agency_import.imported',
            'private.rental_agency_import.document_deleted',
            'private.rental_agency_import.agency_created',
            'private.rental_agency_import.agency_updated',
            'private.rental_agency_import.unit_mapping_created',
            'private.rental_agency_import.unit_mapping_deleted',
            'private.rental_agency_review.property_updated',
            'private.rental_agency_review.line_reviewed',
            'private.rental_agency_review.lines_bulk_saved',
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
        return ['agency imports inherit real_estate_rental permission'];
    }

    public function notes(): string
    {
        return 'Sous-module fonctionnel rattache a la permission real_estate_rental.';
    }
}
