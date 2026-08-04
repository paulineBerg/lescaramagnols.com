<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\WebDevelopment;

final class PrivateAppManifest implements \Caramagnols\PrivatePortal\PrivateAppManifest
{
    public function migrationCode(): string
    {
        return 'web_development';
    }

    public function moduleCode(): string
    {
        return 'web_development';
    }

    public function moduleName(): string
    {
        return 'Web development';
    }

    public function moduleDescription(): string
    {
        return 'Gestion privee des projets web statiques, releases et previsualisations securisees.';
    }

    public function modulePermissionCode(): string
    {
        return 'web_development';
    }

    public function migrationStatusCode(): string
    {
        return 'web_development';
    }

    public function title(): string
    {
        return 'WebDevelopment';
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
            'web_development',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function tables(): array
    {
        return [
            'web_development_projects',
            'web_development_releases',
            'web_development_preview_tickets',
            'web_development_preview_sessions',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function contractClasses(): array
    {
        return [
            'Caramagnols\\PrivateApps\\WebDevelopment\\Http\\PreviewGatewayController',
            'Caramagnols\\PrivateApps\\WebDevelopment\\Repository\\WebDevelopmentProjectRepository',
            'Caramagnols\\PrivateApps\\WebDevelopment\\Repository\\PreviewTicketRepository',
            'Caramagnols\\PrivateApps\\WebDevelopment\\Repository\\PreviewSessionRepository',
            'Caramagnols\\PrivateApps\\WebDevelopment\\Service\\PreviewFileService',
            'Caramagnols\\PrivateApps\\WebDevelopment\\Security\\PreviewAccessGuard',
            'Caramagnols\\PrivateApps\\WebDevelopment\\Http\\PreviewOpenController',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function testClasses(): array
    {
        return [
            'PreviewGatewayControllerTest',
            'PreviewOpenControllerTest',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function auditEvents(): array
    {
        return [
            'private.web_development.preview_ticket_consumed',
            'private.web_development.preview_access_denied',
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
            'web_development' => 'web-development',
        ];
    }

    /**
     * @return array{label: string, description: string, stat_code: string}
     */
    public function dashboardTileData(): array
    {
        return [
            'label' => 'Web development',
            'description' => 'Projets web statiques et previsualisations privees',
            'stat_code' => 'private.web_development.project_count',
        ];
    }

    public function notes(): string
    {
        return 'Module prive dedie aux previews statiques servies par passerelle hors webroot.';
    }
}
