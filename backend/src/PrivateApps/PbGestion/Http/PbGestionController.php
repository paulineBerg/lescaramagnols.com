<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PbGestion\Http;

use Caramagnols\Http\Request;
use Caramagnols\Http\Response;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PbGestion\Persistence\PbGestionRepository;
use Caramagnols\PrivatePortal\Http\PrivateResponseHeaders;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivatePortalSecurityGuard;

final class PbGestionController
{
    private const CSRF = 'private_pbgestion';

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
        $userId = $this->requirePbGestionUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $view = $this->viewForPage($page);
        $query = $request->query();
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : null;
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : null;
        $oneTimeEnrollment = null;

        if ($request->method() === 'POST') {
            if (!$this->securityGuard->validateCsrf($request, self::CSRF)) {
                return $this->renderPbGestion($userId, $view, null, 'invalid_request', null);
            }

            $body = $request->body();
            $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : '';

            if ($action === 'create_enrollment') {
                $oneTimeEnrollment = $this->repository->createEnrollmentToken(
                    $userId,
                    is_string($body['location_label'] ?? null) ? (string) $body['location_label'] : ''
                );
                $this->log('pbgestion.enrollment.created', ['private_user_id' => $userId], 'info');

                return $this->renderPbGestion($userId, 'agents', 'enrollment_created', null, $oneTimeEnrollment);
            }

            if ($action === 'queue_command') {
                $queued = $this->handleQueueCommand($userId, $body);

                return $this->redirect($this->url($view) . (($queued['ok'] ?? false) ? '?notice=command_queued' : '?error=command_rejected'));
            }

            if ($action === 'revoke_agent') {
                $agentId = $this->positiveInt($body['agent_id'] ?? null);
                if ($agentId > 0 && $this->repository->revokeAgent($userId, $agentId, 'private_user')) {
                    $this->log('pbgestion.agent.revoked', ['private_user_id' => $userId, 'agent_id' => $agentId], 'warning');

                    return $this->redirect($this->url('agents') . '?notice=agent_revoked');
                }

                return $this->redirect($this->url('agents') . '?error=agent_revoke_failed');
            }

            if ($action === 'purge_details') {
                $purged = $this->repository->purgeExpiredDetails(false, 100);

                return $this->renderPbGestion($userId, 'settings', 'details_purged_' . $purged, null, null);
            }

            return $this->renderPbGestion($userId, $view, null, 'invalid_request', null);
        }

        return $this->renderPbGestion($userId, $view, $notice, $error, $oneTimeEnrollment);
    }

    private function requirePbGestionUser(Request $request): int|Response
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
        if ($userId === null || !$this->modulePermissionRepository->userHasExplicitModuleAccess($userId, 'pbgestion')) {
            $this->log('private.module.access_denied', [
                'module' => 'pbgestion',
                'identifier' => AppEventLogger::maskIdentifier((string) $this->auth->currentIdentifier()),
            ], 'warning');

            return $this->redirect(private_portal_url('login'));
        }

        return $userId;
    }

    private function renderPbGestion(
        int $userId,
        string $view,
        ?string $notice,
        ?string $error,
        ?array $oneTimeEnrollment
    ): Response {
        $dashboard = $this->repository->dashboardForOwner($userId);

        return ($this->render)('modules/pbgestion/index', [
            'privatePageTitle' => 'Sécurité réseau',
            'privateUserIdentifier' => is_string($this->auth->currentIdentifier()) ? (string) $this->auth->currentIdentifier() : '',
            'privateModules' => $this->privateModuleNamesForUser($userId),
            'privateNavigationModuleCodes' => $this->privateModuleCodesForUser($userId),
            'privateTopNavEnabled' => false,
            'privateDashboardLogoutUrl' => private_portal_url('logout'),
            'privateLogoutCsrfToken' => csrf_token('private_logout'),
            'pbgestion' => [
                'view' => $view,
                'csrfToken' => csrf_token(self::CSRF),
                'urls' => $this->urls(),
                'dashboard' => $dashboard,
                'oneTimeEnrollment' => $oneTimeEnrollment,
            ],
            'notice' => $this->notice($notice),
            'errorMessage' => $this->error($error),
        ]);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool}
     */
    private function handleQueueCommand(int $userId, array $body): array
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

        if ($agentId <= 0 || $type === '') {
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

    private function viewForPage(string $page): string
    {
        return match ($page) {
            'pbgestion_coverage' => 'coverage',
            'pbgestion_networks' => 'networks',
            'pbgestion_devices' => 'devices',
            'pbgestion_computers' => 'computers',
            'pbgestion_alerts' => 'alerts',
            'pbgestion_scans' => 'scans',
            'pbgestion_backups' => 'backups',
            'pbgestion_photos' => 'photos',
            'pbgestion_agents' => 'agents',
            'pbgestion_settings' => 'settings',
            'pbgestion_help' => 'help',
            default => 'overview',
        };
    }

    /**
     * @return array<string, string>
     */
    private function urls(): array
    {
        return [
            'overview' => private_portal_url('pbgestion_dashboard'),
            'coverage' => private_portal_url('pbgestion_coverage'),
            'networks' => private_portal_url('pbgestion_networks'),
            'devices' => private_portal_url('pbgestion_devices'),
            'computers' => private_portal_url('pbgestion_computers'),
            'alerts' => private_portal_url('pbgestion_alerts'),
            'scans' => private_portal_url('pbgestion_scans'),
            'backups' => private_portal_url('pbgestion_backups'),
            'photos' => private_portal_url('pbgestion_photos'),
            'agents' => private_portal_url('pbgestion_agents'),
            'settings' => private_portal_url('pbgestion_settings'),
            'help' => private_portal_url('pbgestion_help'),
        ];
    }

    private function url(string $view): string
    {
        $urls = $this->urls();

        return $urls[$view] ?? $urls['overview'];
    }

    private function notice(?string $key): ?string
    {
        if ($key !== null && str_starts_with($key, 'details_purged_')) {
            return 'Détails temporaires purgés: ' . substr($key, strlen('details_purged_')) . '.';
        }

        return match ($key) {
            'enrollment_created' => 'Code d’appairage créé. Il est valable 10 minutes et affiché une seule fois.',
            'command_queued' => 'Commande enregistrée. L’agent la récupérera lors de son prochain contact.',
            'agent_revoked' => 'Agent révoqué. Les commandes en attente ont été annulées.',
            default => null,
        };
    }

    private function error(?string $key): ?string
    {
        return match ($key) {
            'invalid_request' => 'Requête invalide.',
            'command_rejected' => 'La commande a été refusée par la politique Sécurité réseau.',
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
        $value = is_string($body[$key] ?? null) ? trim((string) $body[$key]) : '';
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
