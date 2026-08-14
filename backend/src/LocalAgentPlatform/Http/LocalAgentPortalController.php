<?php

declare(strict_types=1);

namespace Caramagnols\LocalAgentPlatform\Http;

use Caramagnols\Http\Request;
use Caramagnols\Http\Response;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\PhotoRenamePlanner;
use Caramagnols\PbGestion\Persistence\PbGestionRepository;
use Caramagnols\LocalAgentPlatform\Installer\LocalAgentInstaller;
use Caramagnols\PrivatePortal\Http\PrivateResponseHeaders;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivatePortalSecurityGuard;

final class LocalAgentPortalController
{
    private const CSRF = 'private_pbgestion';
    private const MODULE_NETWORK_SECURITY = 'network_security';
    private const MODULE_PHOTO_GEO_RENAMER = 'photo_geo_renamer';

    /**
     * @param \Closure(string, array<string, mixed>): Response $render
     */
    public function __construct(
        private readonly PrivateAuth $auth,
        private readonly PrivatePortalSecurityGuard $securityGuard,
        private readonly PrivateUserRepository $privateUserRepository,
        private readonly PrivateModulePermissionRepository $modulePermissionRepository,
        private readonly PbGestionRepository $repository,
        private readonly \Closure $render,
        private readonly ?AppEventLogger $eventLogger = null
    ) {
    }

    public function handle(string $page, Request $request): Response
    {
        $app = $this->appForPage($page);
        $userId = $this->requireModuleUser($request, $app);
        if ($userId instanceof Response) {
            return $userId;
        }

        $view = $this->viewForPage($page, $app);
        $query = $request->query();
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : null;
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : null;
        $oneTimeEnrollment = null;

        if ($request->method() === 'POST') {
            if (!$this->securityGuard->validateCsrf($request, self::CSRF)) {
                return $this->renderPbGestion($userId, $view, null, 'invalid_request', null, null, $app);
            }

            $body = $request->body();
            $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : '';
            if (!$this->actionAllowedForModule($action, (string) $app['moduleCode'])) {
                return $this->renderPbGestion($userId, $view, null, 'invalid_request', null, null, $app);
            }

            if ($action === 'download_agent_installer') {
                return $this->downloadAgentInstaller($request, $userId, $body, $app);
            }

            if ($action === 'create_enrollment') {
                $oneTimeEnrollment = $this->repository->createEnrollmentToken(
                    $userId,
                    is_string($body['location_label'] ?? null) ? (string) $body['location_label'] : ''
                );
                $this->log('pbgestion.enrollment.created', ['private_user_id' => $userId], 'info');

                return $this->renderPbGestion($userId, 'agents', 'enrollment_created', null, $oneTimeEnrollment, null, $app);
            }

            if ($action === 'photo_restricted_preview') {
                $preview = $this->restrictedPhotoPreview($body);
                if ($preview === null) {
                    return $this->renderPbGestion($userId, 'photos', null, 'restricted_preview_empty', null, null, $app);
                }

                return $this->renderPbGestion($userId, 'photos', 'restricted_preview_ready', null, null, $preview, $app);
            }

            if ($action === 'queue_command') {
                $queued = $this->handleQueueCommand($userId, $body, (string) $app['moduleCode']);

                return $this->redirect($this->url($view, (string) $app['moduleCode']) . (($queued['ok'] ?? false) ? '?notice=command_queued' : '?error=command_rejected'));
            }

            if ($action === 'revoke_agent') {
                $agentId = $this->positiveInt($body['agent_id'] ?? null);
                if ($agentId > 0 && $this->repository->revokeAgent($userId, $agentId, 'private_user')) {
                    $this->log('pbgestion.agent.revoked', ['private_user_id' => $userId, 'agent_id' => $agentId], 'warning');

                    return $this->redirect($this->url('agents', (string) $app['moduleCode']) . '?notice=agent_revoked');
                }

                return $this->redirect($this->url('agents', (string) $app['moduleCode']) . '?error=agent_revoke_failed');
            }

            if ($action === 'purge_details') {
                $purged = $this->repository->purgeExpiredDetails(false, 100);

                return $this->renderPbGestion($userId, 'settings', 'details_purged_' . $purged, null, null, null, $app);
            }

            return $this->renderPbGestion($userId, $view, null, 'invalid_request', null, null, $app);
        }

        return $this->renderPbGestion($userId, $view, $notice, $error, $oneTimeEnrollment, null, $app);
    }

    /**
     * @param array<string, mixed> $app
     */
    private function requireModuleUser(Request $request, array $app): int|Response
    {
        $required = $this->securityGuard->requireAuthenticated(
            $request,
            private_portal_url('login'),
            strtoupper($request->method()) !== 'GET'
        );
        if ($required !== null) {
            return $required;
        }

        $userId = $this->currentPrivateUserId();
        $moduleCode = (string) $app['moduleCode'];
        if ($userId === null || !$this->hasAppAccess($userId, $moduleCode)) {
            $this->log('private.module.access_denied', [
                'module' => $moduleCode,
                'identifier' => AppEventLogger::maskIdentifier((string) $this->auth->currentIdentifier()),
            ], 'warning');

            return $this->redirect(private_portal_url('login'));
        }

        return $userId;
    }

    private function hasAppAccess(int $userId, string $moduleCode): bool
    {
        return $this->modulePermissionRepository->userHasExplicitModuleAccess($userId, $moduleCode);
    }

    /**
     * @param array<string, mixed> $app
     */
    private function renderPbGestion(
        int $userId,
        string $view,
        ?string $notice,
        ?string $error,
        ?array $oneTimeEnrollment,
        ?array $restrictedPhotoPreview = null,
        ?array $app = null
    ): Response {
        $app ??= $this->appForPage('');
        $dashboard = $this->repository->dashboardForOwner($userId);

        return ($this->render)('modules/pbgestion/index', [
            'privatePageTitle' => (string) $app['title'],
            'privateUserIdentifier' => is_string($this->auth->currentIdentifier()) ? (string) $this->auth->currentIdentifier() : '',
            'privateModules' => $this->privateModuleNamesForUser($userId),
            'privateNavigationModuleCodes' => $this->privateModuleCodesForUser($userId),
            'privateTopNavEnabled' => false,
            'privateDashboardLogoutUrl' => private_portal_url('logout'),
            'privateLogoutCsrfToken' => csrf_token('private_logout'),
            'pbgestion' => [
                'view' => $view,
                'csrfToken' => csrf_token(self::CSRF),
                'urls' => $this->urls((string) $app['moduleCode']),
                'app' => $app,
                'dashboard' => $dashboard,
                'oneTimeEnrollment' => $oneTimeEnrollment,
                'restrictedPhotoPreview' => $restrictedPhotoPreview,
            ],
            'notice' => $this->notice($notice),
            'errorMessage' => $this->error($error),
        ]);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $app
     */
    private function downloadAgentInstaller(Request $request, int $userId, array $body, array $app): Response
    {
        $hasConsent = ($body['installer_consent'] ?? null) === '1'
            && mb_strtoupper($this->shortBodyText($body, 'installer_confirmation', 16)) === 'INSTALLER';
        if (!$hasConsent) {
            return $this->renderPbGestion($userId, 'agents', null, 'installer_consent_required', null, null, $app);
        }

        $locationLabel = $this->shortBodyText($body, 'location_label', 160);
        $oneTimeEnrollment = $this->repository->createEnrollmentToken($userId, $locationLabel);
        $displayName = $locationLabel !== '' ? $locationLabel : 'PbGestion Agent';
        $script = (new LocalAgentInstaller())->buildPowerShellScript(
            $oneTimeEnrollment,
            rtrim(app_url('', $request), '/'),
            $displayName
        );
        $this->log('pbgestion.installer.downloaded', ['private_user_id' => $userId], 'warning');

        return PrivateResponseHeaders::apply(new Response(200, [
            'Content-Type' => 'application/x-powershell; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="pbgestion-agent-install.ps1"',
            'Content-Length' => (string) strlen($script),
        ], $script));
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>|null
     */
    private function restrictedPhotoPreview(array $body): ?array
    {
        $rows = preg_split('/\R+/', $this->shortBodyText($body, 'restricted_items', 12000)) ?: [];
        $photos = [];
        $selectedNames = [];
        foreach ($rows as $row) {
            $columns = str_getcsv((string) $row, ';');
            $currentName = $this->shortText(is_string($columns[0] ?? null) ? (string) $columns[0] : '', 180);
            if ($currentName === '') {
                continue;
            }

            $photos[] = [
                'current_name' => $currentName,
                'city' => $this->shortText(is_string($columns[1] ?? null) ? (string) $columns[1] : '', 120),
                'taken_at' => $this->shortText(is_string($columns[2] ?? null) ? (string) $columns[2] : '', 40),
            ];
            $selectedNames[] = $currentName;
        }

        if ($photos === []) {
            return null;
        }

        $blocks = [];
        $prefix = $this->shortBodyText($body, 'text_before', 80);
        $suffix = $this->shortBodyText($body, 'text_after', 80);
        if ($prefix !== '') {
            $blocks[] = ['type' => 'text', 'value' => $prefix];
        }
        foreach (['city', 'date', 'counter'] as $block) {
            $blocks[] = ['type' => $block, 'value' => ''];
        }
        if ($suffix !== '') {
            $blocks[] = ['type' => 'text', 'value' => $suffix];
        }

        $batchUid = bin2hex(random_bytes(16));
        $preview = (new PhotoRenamePlanner())->preview(
            $photos,
            $selectedNames,
            $blocks,
            $selectedNames,
            $this->shortBodyText($body, 'separator', 1) ?: '-',
            1,
            $this->positiveInt($body['counter_digits'] ?? null) ?: 3,
            $this->shortBodyText($body, 'sort_order', 32) ?: 'manual',
            $batchUid
        );

        return [
            'batch_uid' => $batchUid,
            'input_count' => count($photos),
            'mode' => 'restricted',
            'preview' => $preview,
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool}
     */
    private function handleQueueCommand(int $userId, array $body, string $moduleCode): array
    {
        $agentId = $this->positiveInt($body['agent_id'] ?? null);
        $type = is_string($body['command_type'] ?? null) ? (string) $body['command_type'] : '';
        $payload = [];
        $networkToken = is_string($body['network_token'] ?? null) ? trim((string) $body['network_token']) : '';
        if ($networkToken !== '') {
            $payload['network_token'] = $networkToken;
        }
        $module = is_string($body['module'] ?? null) ? trim((string) $body['module']) : '';
        if ($module !== '') {
            $payload['module'] = $module;
        }
        if ($type === 'details.prepare') {
            $payload['detail_uid'] = bin2hex(random_bytes(16));
            $payload['purpose'] = 'support';
        }
        if ($type === 'monitoring.pause') {
            $payload['duration_seconds'] = 3600;
            $payload['reason'] = 'Pause demandee depuis le BO Private.';
        }
        if ($type === 'backup.start') {
            $payload['backup_kind'] = 'external';
        }
        if ($type === 'backup.verify') {
            $payload['backup_uid'] = bin2hex(random_bytes(16));
        }
        if ($type === 'policy.apply') {
            $payload['policy_uid'] = bin2hex(random_bytes(16));
        }
        if ($type === 'scan.start') {
            $payload['scan_mode'] = 'active_limited';
        }
        if (str_starts_with($type, 'photo.')) {
            $payload = $this->photoCommandPayload($type, $body);
        }

        if ($agentId <= 0 || $type === '' || !$this->commandAllowedForModule($type, $moduleCode)) {
            return ['ok' => false];
        }

        $result = $this->repository->queueCommand(
            $userId,
            $agentId,
            $type,
            $payload,
            hash('sha256', $userId . '|' . $agentId . '|' . $type . '|' . json_encode($payload, JSON_UNESCAPED_SLASHES)),
            (string) $this->auth->currentIdentifier()
        );

        return ['ok' => ($result['ok'] ?? false) === true];
    }

    private function commandAllowedForModule(string $type, string $moduleCode): bool
    {
        if ($moduleCode === self::MODULE_PHOTO_GEO_RENAMER) {
            return str_starts_with($type, 'photo.');
        }

        if ($moduleCode === self::MODULE_NETWORK_SECURITY) {
            return !str_starts_with($type, 'photo.');
        }

        return false;
    }

    private function actionAllowedForModule(string $action, string $moduleCode): bool
    {
        if (in_array($action, ['download_agent_installer', 'create_enrollment', 'queue_command', 'revoke_agent'], true)) {
            return in_array($moduleCode, [self::MODULE_NETWORK_SECURITY, self::MODULE_PHOTO_GEO_RENAMER], true);
        }

        if ($action === 'photo_restricted_preview') {
            return $moduleCode === self::MODULE_PHOTO_GEO_RENAMER;
        }

        if ($action === 'purge_details') {
            return $moduleCode === self::MODULE_NETWORK_SECURITY;
        }

        return false;
    }

    private function currentPrivateUserId(): ?int
    {
        $identifier = $this->auth->currentIdentifier();
        if (!is_string($identifier) || trim($identifier) === '') {
            return null;
        }

        $user = $this->privateUserRepository->findByEmail($identifier);
        if (!is_array($user)) {
            return null;
        }

        $status = is_string($user['status'] ?? null) ? strtolower(trim((string) $user['status'])) : '';
        $id = is_numeric($user['id'] ?? null) ? (int) $user['id'] : 0;

        return $status === 'active' && $id > 0 ? $id : null;
    }

    /**
     * @return array<int, string>
     */
    private function privateModuleNamesForUser(int $userId): array
    {
        return array_values(array_map(
            static fn (array $module): string => (string) $module['name'],
            $this->modulePermissionRepository->activeModulesForUser($userId)
        ));
    }

    /**
     * @return array<int, string>
     */
    private function privateModuleCodesForUser(int $userId): array
    {
        return array_values(array_map(
            static fn (array $module): string => (string) $module['code'],
            $this->modulePermissionRepository->activeModulesForUser($userId)
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function appForPage(string $page): array
    {
        if (
            str_starts_with($page, 'photo_geo_renamer')
            || $page === 'pbgestion_photos'
        ) {
            return [
                'kind' => 'photo',
                'moduleCode' => self::MODULE_PHOTO_GEO_RENAMER,
                'title' => 'Photo rename',
                'navLabel' => 'Navigation Photo rename',
            ];
        }

        return [
            'kind' => 'security',
            'moduleCode' => self::MODULE_NETWORK_SECURITY,
            'title' => 'Sécurité réseau',
            'navLabel' => 'Navigation Sécurité réseau',
        ];
    }

    /**
     * @param array<string, mixed> $app
     */
    private function viewForPage(string $page, array $app): string
    {
        if ((string) $app['moduleCode'] === self::MODULE_PHOTO_GEO_RENAMER) {
            return match ($page) {
                'photo_geo_renamer_agents' => 'agents',
                'photo_geo_renamer_help' => 'help',
                default => 'photos',
            };
        }

        return match ($page) {
            'network_security_coverage', 'security_center_coverage', 'pbgestion_coverage' => 'coverage',
            'network_security_networks', 'security_center_networks', 'pbgestion_networks' => 'networks',
            'network_security_devices', 'security_center_devices', 'pbgestion_devices' => 'devices',
            'network_security_computers', 'security_center_computers', 'pbgestion_computers' => 'computers',
            'network_security_alerts', 'security_center_alerts', 'pbgestion_alerts' => 'alerts',
            'network_security_scans', 'security_center_scans', 'pbgestion_scans' => 'scans',
            'network_security_backups', 'security_center_backups', 'pbgestion_backups' => 'backups',
            'network_security_agents', 'security_center_agents', 'pbgestion_agents' => 'agents',
            'network_security_settings', 'security_center_settings', 'pbgestion_settings' => 'settings',
            'network_security_help', 'security_center_help', 'pbgestion_help' => 'help',
            default => 'overview',
        };
    }

    /**
     * @return array<string, string>
     */
    private function urls(string $moduleCode): array
    {
        if ($moduleCode === self::MODULE_PHOTO_GEO_RENAMER) {
            return [
                'overview' => private_portal_url('photo_geo_renamer_dashboard'),
                'photos' => private_portal_url('photo_geo_renamer_dashboard'),
                'agents' => private_portal_url('photo_geo_renamer_agents'),
                'help' => private_portal_url('photo_geo_renamer_help'),
            ];
        }

        return [
            'overview' => private_portal_url('network_security_dashboard'),
            'coverage' => private_portal_url('network_security_coverage'),
            'networks' => private_portal_url('network_security_networks'),
            'devices' => private_portal_url('network_security_devices'),
            'computers' => private_portal_url('network_security_computers'),
            'alerts' => private_portal_url('network_security_alerts'),
            'scans' => private_portal_url('network_security_scans'),
            'backups' => private_portal_url('network_security_backups'),
            'agents' => private_portal_url('network_security_agents'),
            'settings' => private_portal_url('network_security_settings'),
            'help' => private_portal_url('network_security_help'),
        ];
    }

    private function url(string $view, string $moduleCode): string
    {
        $urls = $this->urls($moduleCode);

        return $urls[$view] ?? $urls['overview'];
    }

    private function notice(?string $key): ?string
    {
        if ($key !== null && str_starts_with($key, 'details_purged_')) {
            return 'Détails temporaires purgés: ' . substr($key, strlen('details_purged_')) . '.';
        }

        return match ($key) {
            'enrollment_created' => 'Code d’appairage créé. Il est valable 10 minutes et affiché une seule fois.',
            'restricted_preview_ready' => 'Aperçu restreint généré. Aucun fichier local n’a été lu ou renommé.',
            'command_queued' => 'Commande enregistrée. L’agent la récupérera lors de son prochain contact.',
            'agent_revoked' => 'Agent révoqué. Les commandes en attente ont été annulées.',
            default => null,
        };
    }

    private function error(?string $key): ?string
    {
        return match ($key) {
            'invalid_request' => 'Requête invalide.',
            'installer_consent_required' => 'Téléchargement refusé: confirmez explicitement l’installation locale avant de générer l’installeur.',
            'restricted_preview_empty' => 'Aucune photo valide à prévisualiser en mode restreint.',
            'command_rejected' => 'La commande a été refusée par la politique du module.',
            'agent_revoke_failed' => 'L’agent n’a pas pu être révoqué.',
            default => null,
        };
    }

    private function positiveInt(mixed $value): int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : 0;
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function photoCommandPayload(string $type, array $body): array
    {
        if ($type === 'photo.roots.list') {
            return [];
        }

        if ($type === 'photo.folder.scan') {
            return [
                'root_uid' => $this->shortBodyText($body, 'root_uid', 64),
                'relative_dir' => $this->shortBodyText($body, 'relative_dir', 240),
                'include_subdirectories' => ($body['include_subdirectories'] ?? null) === '1',
            ];
        }

        if ($type === 'photo.rename.preview') {
            $items = preg_split('/\R+/', $this->shortBodyText($body, 'items', 12000)) ?: [];
            $items = array_values(array_filter(array_map('trim', $items), static fn (string $item): bool => $item !== ''));
            $template = [];
            $prefix = $this->shortBodyText($body, 'text_before', 80);
            $suffix = $this->shortBodyText($body, 'text_after', 80);
            if ($prefix !== '') {
                $template[] = ['type' => 'text', 'value' => $prefix];
            }
            foreach (['city', 'date', 'counter'] as $block) {
                $template[] = ['type' => $block, 'value' => ''];
            }
            if ($suffix !== '') {
                $template[] = ['type' => 'text', 'value' => $suffix];
            }

            return [
                'batch_uid' => bin2hex(random_bytes(16)),
                'root_uid' => $this->shortBodyText($body, 'root_uid', 64),
                'relative_dir' => $this->shortBodyText($body, 'relative_dir', 240),
                'items' => $items,
                'template' => $template,
                'separator' => $this->shortBodyText($body, 'separator', 1) ?: '-',
                'counter_digits' => $this->positiveInt($body['counter_digits'] ?? null) ?: 3,
                'sort_order' => $this->shortBodyText($body, 'sort_order', 32) ?: 'chronological',
                'conflict_strategy' => 'block',
            ];
        }

        if ($type === 'photo.rename.execute' || $type === 'photo.rename.rollback_execute') {
            return [
                'batch_uid' => $this->shortBodyText($body, 'batch_uid', 32),
                'preview_uid' => $this->shortBodyText($body, 'preview_uid', 32),
            ];
        }

        if ($type === 'photo.rename.rollback_preview') {
            return ['batch_uid' => $this->shortBodyText($body, 'batch_uid', 32)];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function shortBodyText(array $body, string $key, int $maxLength): string
    {
        return $this->shortText(is_string($body[$key] ?? null) ? (string) $body[$key] : '', $maxLength);
    }

    private function shortText(string $value, int $maxLength): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return mb_substr($value, 0, $maxLength);
    }

    private function redirect(string $url): Response
    {
        return PrivateResponseHeaders::apply(new Response(302, ['Location' => $url], ''));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $event, array $context, string $level): void
    {
        $logger = $this->eventLogger ?? (function_exists('app_event_logger') ? app_event_logger() : null);
        if (!$logger instanceof AppEventLogger) {
            return;
        }

        $logger->security($event, $context, $level);
    }
}
