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
        $this->assertContains('/private/locations/imports', $agency['routes'] ?? []);
        $this->assertContains('rental_agency_imported_documents', $agency['tables'] ?? []);
        $this->assertContains('rental_agency_unit_mappings', $agency['tables'] ?? []);
    }
}
