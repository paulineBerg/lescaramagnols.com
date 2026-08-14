<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\NetworkSecurity;

final class PrivateAppManifest implements \Caramagnols\PrivatePortal\PrivateAppManifest
{
    public function migrationCode(): string
    {
        return 'network_security';
    }

    public function moduleCode(): string
    {
        return 'network_security';
    }

    public function moduleName(): string
    {
        return 'Sécurité réseau';
    }

    public function moduleDescription(): string
    {
        return 'Pilotage des agents locaux, couverture, alertes, sauvegardes et synthèses de sécurité.';
    }

    public function modulePermissionCode(): string
    {
        return 'network_security';
    }

    public function migrationStatusCode(): string
    {
        return 'network_security';
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
            'network_security_dashboard',
            'network_security_coverage',
            'network_security_networks',
            'network_security_devices',
            'network_security_computers',
            'network_security_alerts',
            'network_security_scans',
            'network_security_backups',
            'network_security_agents',
            'network_security_settings',
            'network_security_help',
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
            'Caramagnols\\PrivateApps\\NetworkSecurity\\Http\\NetworkSecurityController',
            'Caramagnols\\PbGestion\\Persistence\\PbGestionRepository',
            'Caramagnols\\PbGestion\\Protocol\\AgentRequestAuthenticator',
            'Caramagnols\\PbGestion\\Command\\CommandPolicy',
            'Caramagnols\\LocalAgentPlatform\\Installer\\LocalAgentInstaller',
            'Caramagnols\\SecurityCenter\\Network\\SecurityNetworkService',
            'Caramagnols\\SecurityCenter\\Dashboard\\CoverageCalculator',
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
            'LocalAgentPortalControllerTest',
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
            'pbgestion.enrollment.created',
            'pbgestion.installer.downloaded',
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
        return [
            'pbgestion_dashboard',
            'pbgestion_coverage',
            'pbgestion_networks',
            'pbgestion_devices',
            'pbgestion_computers',
            'pbgestion_alerts',
            'pbgestion_scans',
            'pbgestion_backups',
            'pbgestion_agents',
            'pbgestion_settings',
            'pbgestion_help',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function routePaths(): array
    {
        return [
            'network_security_dashboard' => 'securite-reseau',
            'network_security_coverage' => 'securite-reseau/couverture',
            'network_security_networks' => 'securite-reseau/reseaux',
            'network_security_devices' => 'securite-reseau/appareils',
            'network_security_computers' => 'securite-reseau/ordinateurs',
            'network_security_alerts' => 'securite-reseau/alertes',
            'network_security_scans' => 'securite-reseau/scans',
            'network_security_backups' => 'securite-reseau/sauvegardes',
            'network_security_agents' => 'securite-reseau/agents-installation',
            'network_security_settings' => 'securite-reseau/parametres',
            'network_security_help' => 'securite-reseau/aide',
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
            'stat_code' => 'private.network_security.agent_count',
        ];
    }

    public function notes(): string
    {
        return 'Webapp privée dédiée à la sécurité réseau ; l’agent local PbGestion reste le protocole d’exécution partagé.';
    }
}
