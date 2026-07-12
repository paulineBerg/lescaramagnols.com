<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\TaxDeclarationHelper;

final class PrivateAppManifest implements \Caramagnols\PrivatePortal\PrivateAppManifest
{
    public function migrationCode(): string
    {
        return 'tax_declaration_helper';
    }

    public function moduleCode(): string
    {
        return 'tax_declaration_helper';
    }

    public function moduleName(): string
    {
        return 'Aide impôts';
    }

    public function moduleDescription(): string
    {
        return 'Sources validees, activation manuelle, controles, synthese fiscale et exports.';
    }

    public function modulePermissionCode(): string
    {
        return 'tax_declaration_helper';
    }

    public function migrationStatusCode(): string
    {
        return 'tax_declaration_helper';
    }

    public function title(): string
    {
        return 'TaxDeclarationHelper';
    }

    public function order(): int
    {
        return 6;
    }

    /**
     * @return array<int, string>
     */
    public function routeNames(): array
    {
        return [
            'tax_dashboard',
            'tax_year',
            'tax_manual_entries',
            'tax_controls',
            'tax_documents',
            'tax_export',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function tables(): array
    {
        return [
            'tax_years',
            'tax_income_sources',
            'tax_source_activations',
            'tax_manual_income_entries',
            'tax_annual_summaries',
            'tax_summary_lines',
            'tax_export_logs',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function contractClasses(): array
    {
        return [
            'Caramagnols\\PrivateApps\\TaxDeclarationHelper\\Repository\\TaxDeclarationRepository',
            'Caramagnols\\PrivateApps\\TaxDeclarationHelper\\Service\\TaxDeclarationSummaryService',
            'Caramagnols\\PrivatePortal\\TaxDeclarationHelper\\Source\\RentalTaxDataSource',
            'Caramagnols\\PrivatePortal\\TaxDeclarationHelper\\ValueObject\\AnnualRentalIncome',
            'Caramagnols\\PrivatePortal\\TaxDeclarationHelper\\ValueObject\\AnnualDeductibleExpenses',
            'Caramagnols\\PrivatePortal\\TaxDeclarationHelper\\ValueObject\\MissingTaxDocument',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function testClasses(): array
    {
        return [
            'TaxDeclarationHelperModuleTest',
            'TaxDeclarationHelperRoutesTest',
            'PrivatePortalTaxBridgeTest',
            'PrivatePortalPhaseCoverageTest',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function auditEvents(): array
    {
        return [
            'private.tax.source_updated',
            'private.tax.summary_generated',
            'private.tax.year_locked',
            'private.tax.export_created',
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
        return ['tax helper routes stay behind PrivateRouteResolver'];
    }

    public function notes(): string
    {
        return 'Le module reste une aide non officielle, sans remplacer la declaration fiscale.';
    }
}
