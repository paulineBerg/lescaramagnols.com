<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal;

interface PrivateAppManifest
{
    public function migrationCode(): string;

    public function moduleCode(): string;

    public function moduleName(): string;

    public function moduleDescription(): string;

    public function modulePermissionCode(): string;

    public function migrationStatusCode(): string;

    public function title(): string;

    public function order(): int;

    /**
     * @return array<int, string>
     */
    public function routeNames(): array;

    /**
     * @return array<int, string>
     */
    public function tables(): array;

    /**
     * @return array<int, string>
     */
    public function contractClasses(): array;

    /**
     * @return array<int, string>
     */
    public function testClasses(): array;

    /**
     * @return array<int, string>
     */
    public function auditEvents(): array;

    /**
     * @return array<int, string>
     */
    public function uiStates(): array;

    /**
     * @return array<int, string>
     */
    public function legacyRoutes(): array;

    /**
     * Chemins canoniques des routes (nom -> chemin relatif au base path).
     * Permet de remplacer le match de PrivateRouteResolver::canonicalPath().
     *
     * @return array<string, string>
     */
    public function routePaths(): array;

    /**
     * Données de tuile pour le dashboard (libelle, description, code de stat).
     * Permet de remplacer le tableau en dur de templates/private/dashboard.php.
     *
     * @return array{label: string, description: string, stat_code: string}
     */
    public function dashboardTileData(): array;

    public function notes(): string;
}
