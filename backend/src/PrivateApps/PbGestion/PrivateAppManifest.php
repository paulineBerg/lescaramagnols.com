<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PbGestion;

final class PrivateAppManifest implements \Caramagnols\PrivatePortal\PrivateAppManifest
{
    public function migrationCode(): string
    {
        return 'pbgestion';
    }

    public function moduleCode(): string
    {
        return 'pbgestion';
    }

    public function moduleName(): string
    {
        return 'Sécurité réseau';
    }

    public function moduleDescription(): string
    {
        return 'Pilotage des agents locaux, couverture, alertes, sauvegardes et syntheses de securite.';
    }

    public function modulePermissionCode(): string
    {
        return 'pbgestion';
    }

    public function migrationStatusCode(): string
    {
        return 'pbgestion';
    }

    public function title(): string
    {
        return 'Sécurité réseau';
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
            'pbgestion_dashboard',
            'pbgestion_coverage',
            'pbgestion_networks',
            'pbgestion_devices',
            'pbgestion_computers',
            'pbgestion_alerts',
            'pbgestion_scans',
            'pbgestion_backups',
            'pbgestion_photos',
            'pbgestion_agents',
            'pbgestion_settings',
            'pbgestion_help',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function tables(): array
    {
        return [
            'pb_agents',
            'pb_agent_capabilities',
            'pb_agent_sync_state',
            'pb_agent_request_log',
            'pb_enrollment_tokens',
            'pb_commands',
            'pb_policies',
            'pb_agent_policy_state',
            'pb_backup_status',
            'pb_agent_versions',
            'security_networks',
            'security_network_collectors',
            'security_devices_current',
            'security_device_changes',
            'security_posture_current',
            'security_scan_summaries',
            'security_alerts',
            'security_detail_requests',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function contractClasses(): array
    {
        return [
            'Caramagnols\\PbGestion\\Persistence\\PbGestionRepository',
            'Caramagnols\\PbGestion\\Protocol\\AgentRequestAuthenticator',
            'Caramagnols\\PbGestion\\Command\\CommandPolicy',
            'Caramagnols\\PbGestion\\Photo\\PhotoRenamePlanner',
            'Caramagnols\\SecurityCenter\\Network\\SecurityNetworkService',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function testClasses(): array
    {
        return [
            'PbGestionRepositoryTest',
            'PbGestionAgentApiControllerTest',
            'PbGestionControllerTest',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function auditEvents(): array
    {
        return [
            'pbgestion.enrollment.claimed',
            'pbgestion.enrollment.rejected',
            'pbgestion.command.queued',
            'pbgestion.agent.revoked',
            'private.module.access_denied',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function uiStates(): array
    {
        return ['empty', 'partial', 'complete', 'interrupted', 'error', 'success'];
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
            'pbgestion_dashboard' => 'pbgestion',
            'pbgestion_coverage' => 'pbgestion/couverture',
            'pbgestion_networks' => 'pbgestion/reseaux',
            'pbgestion_devices' => 'pbgestion/appareils',
            'pbgestion_computers' => 'pbgestion/ordinateurs',
            'pbgestion_alerts' => 'pbgestion/alertes',
            'pbgestion_scans' => 'pbgestion/scans',
            'pbgestion_backups' => 'pbgestion/sauvegardes',
            'pbgestion_photos' => 'pbgestion/photos',
            'pbgestion_agents' => 'pbgestion/agents-installation',
            'pbgestion_settings' => 'pbgestion/parametres',
            'pbgestion_help' => 'pbgestion/aide',
        ];
    }

    /**
     * @return array{label: string, description: string, stat_code: string}
     */
    public function dashboardTileData(): array
    {
        return [
            'label' => 'Sécurité réseau',
            'description' => 'Couverture locale, agents, alertes et sauvegardes',
            'stat_code' => 'private.pbgestion.agent_count',
        ];
    }

    public function notes(): string
    {
        return 'Webapp Sécurité réseau livree cote Caramagnols; le depot agent Rust reste separe.';
    }
}
