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
        return 'Renommage contrôlé de photos locales avec compteurs globaux par commune.';
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
            'photo_geo_renamer_history',
            'photo_geo_renamer_counters',
            'photo_geo_renamer_agents',
            'photo_geo_renamer_help',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function tables(): array
    {
        return [
            'photo_geo_sequences',
            'photo_geo_batches',
            'photo_geo_operations',
            'photo_geo_places',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function contractClasses(): array
    {
        return [
            'Caramagnols\\PrivateApps\\PhotoGeoRenamer\\Http\\PhotoGeoRenamerController',
            'Caramagnols\\PrivateApps\\PhotoGeoRenamer\\Domain\\PhotoRenamePlanner',
            'Caramagnols\\PrivateApps\\PhotoGeoRenamer\\Domain\\PhotoCommuneNormalizer',
            'Caramagnols\\PrivateApps\\PhotoGeoRenamer\\Domain\\PhotoDateResolver',
            'Caramagnols\\PrivateApps\\PhotoGeoRenamer\\Domain\\PhotoPathPolicy',
            'Caramagnols\\PrivateApps\\PhotoGeoRenamer\\Repository\\PhotoSequenceRepository',
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
            'PhotoSequenceRepositoryTest',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function auditEvents(): array
    {
        return [
            'photo_geo.analysis.completed',
            'photo_geo.sequence.reserved',
            'photo_geo.rename.started',
            'photo_geo.rename.completed',
            'photo_geo.rename.partial',
            'photo_geo.rename.failed',
            'photo_geo.rollback.started',
            'photo_geo.rollback.completed',
            'photo_geo.rollback.failed',
            'photo_geo.target_conflict',
            'private.module.access_denied',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function uiStates(): array
    {
        return ['empty', 'loading', 'ready', 'partial', 'error', 'success', 'offline'];
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
            'photo_geo_renamer_history' => 'photo-rename/historique',
            'photo_geo_renamer_counters' => 'photo-rename/compteurs',
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
            'description' => 'Renommage local avec compteurs globaux par commune',
            'stat_code' => 'private.photo_geo_renamer.agent_count',
        ];
    }

    public function notes(): string
    {
        return 'Webapp privée dédiée au renommage photo ; OVH réserve les compteurs globaux, PbGestion manipule les fichiers localement après consentement.';
    }
}
