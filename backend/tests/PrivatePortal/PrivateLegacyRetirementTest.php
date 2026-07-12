<?php

declare(strict_types=1);

use Caramagnols\PrivatePortal\Http\PrivateRouteResolver;
use Caramagnols\PrivatePortal\Operations\PrivateLegacyRetirementService;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';

final class PrivateLegacyRetirementTest extends TestCase
{
    public function testPhaseM6InventoryClassifiesEveryRemainingPrivateRoute(): void
    {
        $service = new PrivateLegacyRetirementService(
            new PrivateRouteResolver('private'),
            new PrivateModuleRegistry()
        );

        $inventory = $service->inventory();

        $this->assertTrue((bool) ($inventory['success'] ?? false));
        $this->assertTrue((bool) ($inventory['ready'] ?? false));
        $routes = is_array($inventory['routes'] ?? null) ? $inventory['routes'] : [];
        $this->assertNotEmpty($routes);

        $allowedStatuses = $service->allowedStatuses();
        foreach ($routes as $route) {
            $this->assertIsArray($route);
            $this->assertContains((string) ($route['status'] ?? ''), $allowedStatuses);
            $this->assertNotSame('privacy_anonymize', $route['handler'] ?? null);
        }
    }

    public function testPhaseM6BlocksRemovedAnonymizationRouteAndRedirectsLegacyEntrypoints(): void
    {
        $service = new PrivateLegacyRetirementService(
            new PrivateRouteResolver('private'),
            new PrivateModuleRegistry()
        );
        $inventory = $service->inventory();
        $blockedRoutes = is_array($inventory['blockedLegacyRoutes'] ?? null) ? $inventory['blockedLegacyRoutes'] : [];
        $routes = is_array($inventory['routes'] ?? null) ? $inventory['routes'] : [];

        $blockedPaths = array_map(static fn (array $route): string => (string) ($route['path'] ?? ''), $blockedRoutes);
        $this->assertContains('/private/privacy/anonymize', $blockedPaths);

        $activePaths = array_map(static fn (array $route): string => (string) ($route['path'] ?? ''), $routes);
        $this->assertNotContains('/private/privacy/anonymize', $activePaths);

        $redirects = [];
        foreach ($routes as $route) {
            if (($route['status'] ?? '') === PrivateLegacyRetirementService::STATUS_REDIRECTED) {
                $redirects[(string) ($route['path'] ?? '')] = true;
            }
        }
        $this->assertArrayHasKey('/private/login/index.php', $redirects);
        $this->assertArrayHasKey('/private/dashboard.php', $redirects);
    }

    public function testPhaseM6KeepsOnlyKnownTemplatesPermissionsAndControlledFileEndpoints(): void
    {
        $service = new PrivateLegacyRetirementService(
            new PrivateRouteResolver('private'),
            new PrivateModuleRegistry()
        );
        $inventory = $service->inventory();

        $this->assertSame([], $inventory['obsoletePermissions'] ?? null);
        $this->assertSame([], $inventory['obsoleteTemplates'] ?? null);
        $runbook = is_array($inventory['runbook'] ?? null) ? $inventory['runbook'] : [];
        $this->assertTrue((bool) ($runbook['backupBeforeDelete'] ?? false));
        $this->assertTrue((bool) ($runbook['tagBeforeDelete'] ?? false));
        $this->assertTrue((bool) ($runbook['noDirectPrivateFileEndpoint'] ?? false));
        $this->assertTrue((bool) ($runbook['legacyRouteResolutionBlocked'] ?? false));

        $fileEndpoints = is_array($inventory['fileEndpoints'] ?? null) ? $inventory['fileEndpoints'] : [];
        $this->assertNotEmpty($fileEndpoints);
        foreach ($fileEndpoints as $endpoint) {
            $this->assertTrue((bool) ($endpoint['controlled'] ?? false));
        }
    }
}
