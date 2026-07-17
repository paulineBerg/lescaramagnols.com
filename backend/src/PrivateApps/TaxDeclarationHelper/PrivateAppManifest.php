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
        return 'Aide à la déclaration fiscale';
    }

    public function moduleDescription(): string
    {
        return 'Outil d\'extraction et de synthèse des données fiscales depuis les modules locatifs.';
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
        return 5;
    }

    /**
     * @return array<int, string>
     */
    public function routeNames(): array
    {
        return [
            'tax_declaration',
            'tax_declaration_export',
            'tax_declaration_summary',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function tables(): array
    {
        return [
            'tax_declaration_snapshots',
            'tax_declaration_exports',
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
            'Caramagnols\\PrivateApps\\TaxDeclarationHelper\\Source\\RentalTaxDataSource',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function testClasses(): array
    {
        return [
            'TaxDeclarationSummaryServiceTest',
            'PrivatePortalPhaseCoverageTest',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function auditEvents(): array
    {
        return [
            'private.tax.declaration_generated',
            'private.tax.export_created',
            'private.tax.summary_calculated',
            'private.module.access_denied',
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
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function routePaths(): array
    {
        return [
            'tax_declaration' => 'fiscal',
            'tax_declaration_export' => 'fiscal/exporter',
            'tax_declaration_summary' => 'fiscal/synthese',
        ];
    }

    /**
     * @return array{label: string, description: string, stat_code: string}
     */
    public function dashboardTileData(): array
    {
        return [
            'label' => 'Fiscal',
            'description' => 'Aide à la déclaration fiscale',
            'stat_code' => 'private.tax.declaration_count',
        ];
    }

    public function notes(): string
    {
        return 'Module d\'aide à la déclaration fiscale, extrait de PrivatePortal le 2026-07-17.';
    }
}
