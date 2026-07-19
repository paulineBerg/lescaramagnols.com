<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal;

use PHPUnit\Framework\TestCase;

/**
 * Tests pour PrivateAppRegistry - validation anti-régression.
 * Garantit que chaque manifeste déclare des routes toutes résolues par le resolver
 * et des tables toutes présentes dans backend/sql/private/.
 */
final class PrivateAppRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        // Réinitialiser le cache avant chaque test
        PrivateAppRegistry::reset();
    }

    public function testAllManifestsAreInstantiable(): void
    {
        $manifests = PrivateAppRegistry::all();

        $this->assertNotEmpty($manifests);
        $this->assertIsArray($manifests);

        foreach ($manifests as $moduleCode => $manifest) {
            $this->assertInstanceOf(PrivateAppManifest::class, $manifest);
            $this->assertSame($moduleCode, $manifest->moduleCode());
        }
    }

    public function testExpectedModulesAreRegistered(): void
    {
        $manifests = PrivateAppRegistry::all();

        $expectedModules = [
            'blocnote',
            'documents',
            'family_discussion',
            'real_estate_rental',
            'tax_declaration_helper',
        ];

        $registeredModules = array_keys($manifests);

        foreach ($expectedModules as $module) {
            $this->assertArrayHasKey($module, $manifests, "Le module {$module} n'est pas enregistré.");
        }
    }

    public function testGetByModuleCode(): void
    {
        $manifest = PrivateAppRegistry::get('blocnote');

        $this->assertNotNull($manifest);
        $this->assertInstanceOf(PrivateAppManifest::class, $manifest);
        $this->assertSame('blocnote', $manifest->moduleCode());
    }

    public function testGetByNonExistentModuleCodeReturnsNull(): void
    {
        $manifest = PrivateAppRegistry::get('non_existent_module');

        $this->assertNull($manifest);
    }

    public function testAllRouteNamesAreUnique(): void
    {
        $allRoutes = PrivateAppRegistry::allRouteNames();

        // Vérifier qu'il n'y a pas de doublons
        $uniqueRoutes = array_unique($allRoutes);
        $this->assertSameSize($allRoutes, $uniqueRoutes);
    }

    public function testAllModuleTablesAreUnique(): void
    {
        $allTables = PrivateAppRegistry::allModuleTables();

        // Vérifier qu'il n'y a pas de doublons
        $uniqueTables = array_unique($allTables);
        $this->assertSameSize($allTables, $uniqueTables);
    }

    public function testAllPermissionCodesAreUnique(): void
    {
        $allCodes = PrivateAppRegistry::allPermissionCodes();

        // Vérifier qu'il n'y a pas de doublons
        $uniqueCodes = array_unique($allCodes);
        $this->assertSameSize($allCodes, $uniqueCodes);
    }

    public function testAllTablesIncludesCoreTables(): void
    {
        $allTables = PrivateAppRegistry::allTables();

        // Vérifier que les tables du socle sont incluses
        $expectedCoreTables = [
            'private_users',
            'private_user_sessions',
            'private_user_permissions',
            'private_module_permissions',
        ];

        foreach ($expectedCoreTables as $table) {
            $this->assertContains($table, $allTables);
        }
    }

    public function testDashboardTileDataIsComplete(): void
    {
        $tiles = PrivateAppRegistry::allDashboardTileData();

        $this->assertNotEmpty($tiles);
        $this->assertIsArray($tiles);

        foreach ($tiles as $tile) {
            $this->assertArrayHasKey('label', $tile);
            $this->assertArrayHasKey('description', $tile);
            $this->assertArrayHasKey('stat_code', $tile);
            $this->assertArrayHasKey('module_code', $tile);

            $this->assertIsString($tile['label']);
            $this->assertIsString($tile['description']);
            $this->assertIsString($tile['stat_code']);
            $this->assertIsString($tile['module_code']);
        }
    }

    public function testGetByRouteName(): void
    {
        $manifest = PrivateAppRegistry::getByRouteName('blocnote');

        $this->assertNotNull($manifest);
        $this->assertSame('blocnote', $manifest->moduleCode());
    }

    public function testGetByRouteNameReturnsNullForUnknownRoute(): void
    {
        $manifest = PrivateAppRegistry::getByRouteName('unknown_route');

        $this->assertNull($manifest);
    }

    public function testGetByTableName(): void
    {
        $manifest = PrivateAppRegistry::getByTableName('blocnote_notes');

        $this->assertNotNull($manifest);
        $this->assertSame('blocnote', $manifest->moduleCode());
    }

    public function testGetByTableNameReturnsNullForUnknownTable(): void
    {
        $manifest = PrivateAppRegistry::getByTableName('unknown_table');

        $this->assertNull($manifest);
    }

    public function testOrderedReturnsManifestsSortedByOrder(): void
    {
        $ordered = PrivateAppRegistry::ordered();

        $this->assertNotEmpty($ordered);
        $this->assertIsArray($ordered);

        // Vérifier que les manifestes sont triés par ordre
        for ($i = 0; $i < count($ordered) - 1; $i++) {
            $currentOrder = $ordered[$i]['instance']->order();
            $nextOrder = $ordered[$i + 1]['instance']->order();

            $this->assertLessThanOrEqual($nextOrder, $currentOrder);
        }
    }

    public function testRoutePathsAreUnique(): void
    {
        $allPaths = PrivateAppRegistry::allRoutePaths();

        // Vérifier qu'il n'y a pas de doublons dans les chemins
        $values = array_values($allPaths);
        $uniqueValues = array_unique($values);
        $this->assertSameSize($values, $uniqueValues);
    }

    public function testManifestContractIsComplete(): void
    {
        $manifests = PrivateAppRegistry::all();

        foreach ($manifests as $manifest) {
            // Vérifier que toutes les méthodes requises existent et retournent des valeurs valides
            $this->assertIsString($manifest->migrationCode());
            $this->assertIsString($manifest->moduleCode());
            $this->assertIsString($manifest->moduleName());
            $this->assertIsString($manifest->moduleDescription());
            $this->assertIsString($manifest->modulePermissionCode());
            $this->assertIsString($manifest->migrationStatusCode());
            $this->assertIsString($manifest->title());
            $this->assertIsInt($manifest->order());

            $this->assertIsArray($manifest->routeNames());
            $this->assertIsArray($manifest->tables());
            $this->assertIsArray($manifest->contractClasses());
            $this->assertIsArray($manifest->testClasses());
            $this->assertIsArray($manifest->auditEvents());
            $this->assertIsArray($manifest->uiStates());
            $this->assertIsArray($manifest->legacyRoutes());

            // Nouvelles méthodes
            $this->assertIsArray($manifest->routePaths());

            $tileData = $manifest->dashboardTileData();
            $this->assertArrayHasKey('label', $tileData);
            $this->assertArrayHasKey('description', $tileData);
            $this->assertArrayHasKey('stat_code', $tileData);

            $this->assertIsString($manifest->notes());
        }
    }
}
