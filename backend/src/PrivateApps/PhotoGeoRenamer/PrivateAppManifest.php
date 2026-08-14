<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer;

final class PrivateAppManifest implements \Caramagnols\PrivatePortal\PrivateAppManifest
{
    public function migrationCode(): string
    {
        return 'photo_geo_renamer';
    }

    public function moduleCode(): string
    {
        return 'photo_geo_renamer';
    }

    public function moduleName(): string
    {
        return 'Photo rename';
    }

    public function moduleDescription(): string
    {
        return 'Prévisualisation et renommage contrôlé de photos locales à partir de métadonnées et de modèles.';
    }

    public function modulePermissionCode(): string
    {
        return 'photo_geo_renamer';
    }

    public function migrationStatusCode(): string
    {
        return 'photo_geo_renamer';
    }

    public function title(): string
    {
        return 'Photo rename';
    }

    public function order(): int
    {
        return 7;
    }

    /**
     * @return array<int, string>
     */
    public function routeNames(): array
    {
        return [
            'photo_geo_renamer_dashboard',
            'photo_geo_renamer_agents',
            'photo_geo_renamer_help',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function tables(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public function contractClasses(): array
    {
        return [
            'Caramagnols\\PrivateApps\\PhotoGeoRenamer\\Http\\PhotoGeoRenamerController',
            'Caramagnols\\PrivateApps\\PhotoGeoRenamer\\Domain\\PhotoRenamePlanner',
            'Caramagnols\\PrivateApps\\PhotoGeoRenamer\\Domain\\PhotoPathPolicy',
            'Caramagnols\\PbGestion\\Command\\CommandPolicy',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function testClasses(): array
    {
        return [
            'LocalAgentPortalControllerTest',
            'PhotoRenamePlannerTest',
            'PhotoPathPolicyTest',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function auditEvents(): array
    {
        return [
            'pbgestion.command.queued',
            'private.module.access_denied',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function uiStates(): array
    {
        return ['empty', 'partial', 'error', 'success'];
    }

    /**
     * @return array<int, string>
     */
    public function legacyRoutes(): array
    {
        return [
            'pbgestion_photos',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function routePaths(): array
    {
        return [
            'photo_geo_renamer_dashboard' => 'photo-rename',
            'photo_geo_renamer_agents' => 'photo-rename/agents-installation',
            'photo_geo_renamer_help' => 'photo-rename/aide',
        ];
    }

    /**
     * @return array{label: string, description: string, stat_code: string}
     */
    public function dashboardTileData(): array
    {
        return [
            'label' => 'Photo rename',
            'description' => 'Renommage local de photos avec aperçu et mode restreint',
            'stat_code' => 'private.photo_geo_renamer.agent_count',
        ];
    }

    public function notes(): string
    {
        return 'Webapp privée dédiée au renommage photo ; les commandes locales passent par l’agent PbGestion uniquement après consentement.';
    }
}
