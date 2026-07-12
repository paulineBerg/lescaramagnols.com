<?php

declare(strict_types=1);

use Caramagnols\PrivatePortal\Http\PrivateRouteResolver;
use Caramagnols\PrivatePortal\Operations\PrivateMigrationService;
use Caramagnols\PrivatePortal\Operations\PrivateModuleMigrationPlanService;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';

final class PrivateModuleMigrationPlanTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testPhaseM5DeclaresAllMigrationUnitsAsReadyForControlledSwitch(): void
    {
        $registry = new PrivateModuleRegistry();
        $service = new PrivateModuleMigrationPlanService(
            $registry,
            new PrivateRouteResolver('private'),
            new PrivateMigrationService($this->editorialSqlDatabase(), $registry)
        );

        $readiness = $service->readiness();

        $this->assertTrue((bool) ($readiness['success'] ?? false));
        $this->assertTrue((bool) ($readiness['ready'] ?? false));
        $modules = is_array($readiness['modules'] ?? null) ? $readiness['modules'] : [];
        $this->assertSame(
            [
                'private_core',
                'documents',
                'family_discussion',
                'real_estate_rental',
                'agency_imports',
                'tax_declaration_helper',
            ],
            array_keys($modules)
        );

        foreach ($modules as $module) {
            $this->assertIsArray($module);
            $this->assertTrue((bool) ($module['ready'] ?? false), (string) ($module['code'] ?? 'unknown'));
            $checklist = is_array($module['checklist'] ?? null) ? $module['checklist'] : [];
            $this->assertNotEmpty($checklist);
            foreach ($checklist as $check => $passed) {
                $this->assertTrue((bool) $passed, sprintf('%s:%s', (string) ($module['code'] ?? 'unknown'), (string) $check));
            }
            $contractClasses = is_array($module['contractClasses'] ?? null) ? $module['contractClasses'] : [];
            $this->assertSame([], $contractClasses['missing'] ?? null);
            $this->assertNotEmpty($module['routes'] ?? []);
            $this->assertNotEmpty($module['tables'] ?? []);
            $this->assertNotEmpty($module['testClasses'] ?? []);
        }
    }

    public function testAgencyImportsRemainAControlledSubModuleOfRealEstateRental(): void
    {
        $service = new PrivateModuleMigrationPlanService(
            new PrivateModuleRegistry(),
            new PrivateRouteResolver('private')
        );

        $readiness = $service->readiness('agency_imports');

        $this->assertTrue((bool) ($readiness['success'] ?? false));
        $modules = is_array($readiness['modules'] ?? null) ? $readiness['modules'] : [];
        $agency = is_array($modules['agency_imports'] ?? null) ? $modules['agency_imports'] : [];
        $this->assertSame('real_estate_rental', $agency['permissionModule'] ?? null);
        $this->assertSame('real_estate_rental', $agency['migrationStatusModule'] ?? null);
        $this->assertContains('/private/locations/agence', $agency['routes'] ?? []);
        $this->assertContains('/private/locations/agence/imports', $agency['routes'] ?? []);
        $this->assertContains('rental_agencies', $agency['tables'] ?? []);
        $this->assertContains('rental_agency_imported_documents', $agency['tables'] ?? []);
        $this->assertContains('rental_agency_unit_mappings', $agency['tables'] ?? []);
    }

    public function testExistingPrivateAppsAreConnectedThroughManifests(): void
    {
        $registry = new PrivateModuleRegistry();
        $manifests = $registry->privateAppManifests();

        $manifestCodes = array_map(
            static fn (\Caramagnols\PrivatePortal\PrivateAppManifest $manifest): string => $manifest->moduleCode(),
            $manifests
        );

        $this->assertContains('discussions', $manifestCodes);
        $this->assertContains('real_estate_rental', $manifestCodes);
        $this->assertContains('tax_declaration_helper', $manifestCodes);

        $this->assertContains('family_discussion', array_map(
            static fn (\Caramagnols\PrivatePortal\PrivateAppManifest $manifest): string => $manifest->migrationCode(),
            $manifests
        ));
        $this->assertContains('real_estate_rental', array_map(
            static fn (\Caramagnols\PrivatePortal\PrivateAppManifest $manifest): string => $manifest->migrationCode(),
            $manifests
        ));
        $this->assertContains('agency_imports', array_map(
            static fn (\Caramagnols\PrivatePortal\PrivateAppManifest $manifest): string => $manifest->migrationCode(),
            $manifests
        ));
        $this->assertContains('tax_declaration_helper', array_map(
            static fn (\Caramagnols\PrivatePortal\PrivateAppManifest $manifest): string => $manifest->migrationCode(),
            $manifests
        ));

        $service = new PrivateModuleMigrationPlanService(
            $registry,
            new PrivateRouteResolver('private')
        );
        $plans = $service->plans();

        $this->assertArrayHasKey('family_discussion', $plans);
        $this->assertArrayHasKey('real_estate_rental', $plans);
        $this->assertArrayHasKey('agency_imports', $plans);
        $this->assertArrayHasKey('tax_declaration_helper', $plans);
    }
}
