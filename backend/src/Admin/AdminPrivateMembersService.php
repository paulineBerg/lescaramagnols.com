<?php

declare(strict_types=1);

namespace Caramagnols\Admin;

use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PrivatePortal\Operations\PrivateDataProtectionService;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;

final class AdminPrivateMembersService
{
    /** @var array<int, string> */
    private const ALLOWED_STATUS_FILTERS = ['invited', 'active', 'suspended', 'disabled'];

    /** @var array<int, string> */
    private const ALLOWED_ACTIONS = ['invite', 'resend', 'suspend', 'reactivate', 'delete_suspended', 'reset', 'modules'];

    public function __construct(
        private readonly PrivateUserRepository $privateUserRepository,
        private readonly PrivateModulePermissionRepository $modulePermissionRepository,
        private readonly ?AppEventLogger $eventLogger = null,
        private ?PrivateDataProtectionService $dataProtectionService = null
    ) {
    }

    /**
     * @return array{
     *   statusFilter: string,
     *   searchQuery: string,
     *   members: array<int, array<string, mixed>>,
     *   stats: array<string, int>,
     *   memberEmailChoices: array<int, string>,
     *   moduleRegistry: array<int, array<string, mixed>>
     * }
     */
    public function listMembersViewModel(?string $statusFilter = null, string $searchQuery = ''): array
    {
        $normalizedStatusFilter = $this->normalizeStatusFilter($statusFilter);
        $normalizedSearchQuery = trim((string) $searchQuery);

        $members = $this->privateUserRepository->listMembers(
            $normalizedStatusFilter === '' ? null : $normalizedStatusFilter,
            $normalizedSearchQuery
        );
        $members = array_values(array_filter(
            $members,
            fn (array $member): bool => $this->normalizeStatusFilter((string) ($member['status'] ?? '')) !== ''
        ));
        $members = array_map(
            fn (array $member): array => $this->memberView($member),
            $members
        );
        $allMembers = array_values(array_filter(
            $this->privateUserRepository->listMembers(null, ''),
            fn (array $member): bool => $this->normalizeStatusFilter((string) ($member['status'] ?? '')) !== ''
        ));
        $memberEmailChoices = [];
        foreach ($allMembers as $member) {
            $email = is_string($member['email'] ?? null) ? trim((string) $member['email']) : '';
            if ($email !== '' && !in_array($email, $memberEmailChoices, true)) {
                $memberEmailChoices[] = $email;
            }
        }
        usort($memberEmailChoices, static fn (string $left, string $right): int => strcasecmp($left, $right));

        return [
            'statusFilter' => $normalizedStatusFilter,
            'searchQuery' => $normalizedSearchQuery,
            'members' => $members,
            'stats' => $this->membersStats($members),
            'memberEmailChoices' => $memberEmailChoices,
            'moduleRegistry' => $this->modulePermissionRepository->listRegistryModuleStates(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: ?string, error: ?string}
     */
    public function handleAction(
        array $payload,
        ?string $actorIdentifier = null,
        ?string $clientIp = null,
        ?string $userAgent = null
    ): array {
        $action = $this->normalizeAction($payload['private_member_action'] ?? null);
        if ($action === '') {
            return $this->result(false, null, 'Action privée inconnue.');
        }

        return match ($action) {
            'invite' => $this->invite($payload, $actorIdentifier),
            'resend' => $this->resend($payload, $actorIdentifier),
            'suspend' => $this->suspend($payload, $actorIdentifier),
            'reactivate' => $this->reactivate($payload, $actorIdentifier),
            'delete_suspended' => $this->deleteSuspended($payload, $actorIdentifier),
            'reset' => $this->resetPassword($payload, $actorIdentifier, $clientIp, $userAgent),
            'modules' => $this->assignModules($payload, $actorIdentifier),
            default => $this->result(false, null, 'Action privée inconnue.'),
        };
    }

    /**
     * @param array<int, array<string, mixed>> $members
     * @return array<string, int>
     */
    private function membersStats(array $members): array
    {
        $stats = array_fill_keys(self::ALLOWED_STATUS_FILTERS, 0);
        $stats['total'] = 0;

        foreach ($members as $member) {
            if (!is_array($member)) {
                continue;
            }

            $stats['total']++;
            $status = $this->normalizeStatusFilter((string) ($member['status'] ?? ''));
            if ($status !== '' && array_key_exists($status, $stats)) {
                $stats[$status]++;
            }
        }

        return $stats;
    }

    /**
     * @param array<string, mixed> $member
     * @return array<string, mixed>
     */
    private function memberView(array $member): array
    {
        $id = is_numeric($member['id'] ?? null) ? (int) $member['id'] : 0;
        $member['moduleStates'] = $this->modulePermissionRepository->listModuleStatesForUser($id);
        $member['moduleDataCounts'] = $this->modulePermissionRepository->moduleDataCountsForUser($id);
        $member['deletionBackup'] = $id > 0 ? $this->dataProtectionService()->latestDeletionBackupForUser($id) : null;

        return $member;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, filename?: string, content?: string, contentType?: string, error?: string}
     */
    public function downloadDeletionBackup(array $payload, ?string $actorIdentifier = null): array
    {
        $member = $this->memberFromPayload($payload);
        if ($member === null) {
            return ['success' => false, 'error' => 'Compte privé introuvable.'];
        }

        $userId = (int) $member['id'];
        $email = (string) $member['email'];
        $download = $this->dataProtectionService()->deletionBackupDownloadForUser($userId);
        if (!is_array($download)) {
            return ['success' => false, 'error' => 'Aucune sauvegarde disponible pour ce compte.'];
        }

        $this->logAction('admin.private.deletion_backup_downloaded', $actorIdentifier, $userId, $email);

        return [
            'success' => true,
            'filename' => is_string($download['filename'] ?? null) ? (string) $download['filename'] : 'private-account-backup.zip',
            'content' => is_string($download['content'] ?? null) ? (string) $download['content'] : '',
            'contentType' => is_string($download['contentType'] ?? null) ? (string) $download['contentType'] : 'application/zip',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: ?string, error: ?string}
     */
    private function invite(array $payload, ?string $actorIdentifier): array
    {
        $email = $this->normalizeEmail($payload['email'] ?? null);
        if ($email === '') {
            return $this->result(false, null, 'Adresse email invalide.');
        }

        $existing = $this->privateUserRepository->findByEmail($email);
        if (is_array($existing)) {
            return $this->result(false, null, 'Un compte privé existe déjà pour cette adresse.');
        }

        $passwordHash = $this->placeholderPasswordHash();
        if ($passwordHash === '') {
            return $this->result(false, null, 'Impossible de préparer le compte invité.');
        }

        $userId = $this->privateUserRepository->create($email, $passwordHash, 'invited');
        if ($userId === null) {
            return $this->result(false, null, 'Impossible de créer le compte invité.');
        }

        $token = $this->privateUserRepository->createInviteToken($userId, $email);
        if ($token === null) {
            $this->logAction('admin.private.invite_token_failed', $actorIdentifier, $userId, $email, 'warning');

            return $this->result(false, null, 'Compte invité créé, mais jeton d’invitation non généré.');
        }

        $this->sendActivationEmail($email, $token, $actorIdentifier, $userId);
        $this->logAction('admin.private.member_invited', $actorIdentifier, $userId, $email);

        return $this->result(true, 'Invitation privée enregistrée.', null);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: ?string, error: ?string}
     */
    private function resend(array $payload, ?string $actorIdentifier): array
    {
        $member = $this->memberFromPayload($payload);
        if ($member === null) {
            return $this->result(false, null, 'Compte privé introuvable.');
        }

        $status = $this->normalizeStatusFilter((string) ($member['status'] ?? ''));
        if ($status !== 'invited') {
            return $this->result(false, null, 'Le renvoi d’invitation est réservé aux comptes invités.');
        }

        $userId = (int) $member['id'];
        $email = (string) $member['email'];
        $token = $this->privateUserRepository->createInviteToken($userId, $email);
        if ($token === null) {
            $this->logAction('admin.private.invite_resend_failed', $actorIdentifier, $userId, $email, 'warning');

            return $this->result(false, null, 'Impossible de générer un nouveau jeton d’invitation.');
        }

        $this->sendActivationEmail($email, $token, $actorIdentifier, $userId);
        $this->logAction('admin.private.invite_resent', $actorIdentifier, $userId, $email);

        return $this->result(true, 'Renvoi d’invitation enregistré.', null);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: ?string, error: ?string}
     */
    private function suspend(array $payload, ?string $actorIdentifier): array
    {
        $member = $this->memberFromPayload($payload);
        if ($member === null) {
            return $this->result(false, null, 'Compte privé introuvable.');
        }

        $status = $this->normalizeStatusFilter((string) ($member['status'] ?? ''));
        $userId = (int) $member['id'];
        $email = (string) $member['email'];
        if ($status !== 'suspended' && !$this->privateUserRepository->updateStatus($userId, 'suspended')) {
            $this->logAction('admin.private.member_suspend_failed', $actorIdentifier, $userId, $email, 'warning');

            return $this->result(false, null, 'Impossible de suspendre le compte privé.');
        }

        $this->logAction('admin.private.member_suspended', $actorIdentifier, $userId, $email);
        $emailSent = $this->sendTemplateEmail(
            $email,
            'member_suspended_subject',
            'Compte privé suspendu',
            'member_suspended_body',
            "Bonjour,\n\nVotre compte privé {{site_name}} a été suspendu. Vous ne pouvez plus vous connecter tant qu’il n’est pas réactivé.\n\nPour toute question, vous pouvez écrire à {{reply_to}}.",
            'admin.private.member_suspended_notification',
            $actorIdentifier,
            $userId
        );

        return $this->result(
            true,
            $emailSent ? 'Compte privé suspendu. Email envoyé.' : 'Compte privé suspendu, mais l’email n’a pas pu être envoyé.',
            null
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: ?string, error: ?string}
     */
    private function reactivate(array $payload, ?string $actorIdentifier): array
    {
        $member = $this->memberFromPayload($payload);
        if ($member === null) {
            return $this->result(false, null, 'Compte privé introuvable.');
        }

        $status = $this->normalizeStatusFilter((string) ($member['status'] ?? ''));
        $userId = (int) $member['id'];
        $email = (string) $member['email'];
        if ($status !== 'suspended') {
            return $this->result(false, null, 'Seul un compte suspendu peut être réactivé.');
        }
        if (is_array($this->dataProtectionService()->latestDeletionBackupForUser($userId))) {
            return $this->result(false, null, 'La suppression de ce compte est déjà programmée. Les données ont été purgées.');
        }

        if (!$this->privateUserRepository->updateStatus($userId, 'active')) {
            $this->logAction('admin.private.member_reactivate_failed', $actorIdentifier, $userId, $email, 'warning');

            return $this->result(false, null, 'Impossible de réactiver le compte privé.');
        }

        $this->logAction('admin.private.member_reactivated', $actorIdentifier, $userId, $email);
        $emailSent = $this->sendTemplateEmail(
            $email,
            'member_reactivated_subject',
            'Compte privé réactivé',
            'member_reactivated_body',
            "Bonjour,\n\nVotre compte privé {{site_name}} a été réactivé. Vous pouvez de nouveau vous connecter à l’espace privé : {{login_url}}\n\nPour toute question, vous pouvez écrire à {{reply_to}}.",
            'admin.private.member_reactivated_notification',
            $actorIdentifier,
            $userId
        );

        return $this->result(
            true,
            $emailSent ? 'Compte privé réactivé. Email envoyé.' : 'Compte privé réactivé, mais l’email n’a pas pu être envoyé.',
            null
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: ?string, error: ?string}
     */
    private function deleteSuspended(array $payload, ?string $actorIdentifier): array
    {
        $member = $this->memberFromPayload($payload);
        if ($member === null) {
            return $this->result(false, null, 'Compte privé introuvable.');
        }

        $status = $this->normalizeStatusFilter((string) ($member['status'] ?? ''));
        $userId = (int) $member['id'];
        $email = (string) $member['email'];
        if ($status !== 'suspended') {
            return $this->result(false, null, 'La suppression est réservée aux comptes suspendus.');
        }

        if ((string) ($payload['private_member_delete_confirm'] ?? '') !== '1') {
            return $this->result(false, null, 'Merci de confirmer la suppression avant de continuer.');
        }

        try {
            $deletion = $this->dataProtectionService()->deleteSuspendedAccountWithBackup($userId, $actorIdentifier ?? 'admin', 30);
        } catch (\Throwable) {
            $this->logAction('admin.private.member_delete_suspended_failed', $actorIdentifier, $userId, $email, 'warning');

            return $this->result(false, null, 'Impossible de supprimer le compte privé suspendu.');
        }

        $success = (bool) ($deletion['success'] ?? $deletion['ok'] ?? false);
        if (!$success) {
            $error = is_string($deletion['error'] ?? null) && trim((string) $deletion['error']) !== ''
                ? (string) $deletion['error']
                : 'Impossible de supprimer le compte privé suspendu.';
            $this->logAction('admin.private.member_delete_suspended_failed', $actorIdentifier, $userId, $email, 'warning');

            return $this->result(false, null, $error);
        }

        $this->logAction(
            'admin.private.member_deletion_scheduled_with_backup',
            $actorIdentifier,
            $userId,
            $email,
            'info',
            [
                'backup_path' => is_string($deletion['backupPath'] ?? null) ? (string) $deletion['backupPath'] : null,
                'delete_after' => is_string($deletion['deleteAfter'] ?? null) ? (string) $deletion['deleteAfter'] : null,
            ]
        );
        $deleteAfter = is_string($deletion['deleteAfter'] ?? null) ? (string) $deletion['deleteAfter'] : '';
        $deleteAfterLabel = strtotime($deleteAfter) !== false ? date('d/m/Y', (int) strtotime($deleteAfter)) : 'dans 30 jours';
        $emailSent = $this->sendTemplateEmail(
            $email,
            'member_deletion_scheduled_subject',
            'Suppression programmée de votre compte privé',
            'member_deletion_scheduled_body',
            "Bonjour,\n\nLa suppression de votre compte privé {{site_name}} a été programmée.\n\nUne sauvegarde ZIP des données a été créée, puis les données rattachées au compte ont été purgées. Le compte suspendu et la sauvegarde seront supprimés définitivement le {{delete_after}}.\n\nUn email de rappel sera envoyé après 20 jours.\n\nPour toute question, vous pouvez écrire à {{reply_to}}.",
            'admin.private.member_deletion_scheduled_notification',
            $actorIdentifier,
            $userId,
            ['delete_after' => $deleteAfterLabel]
        );

        return $this->result(
            true,
            $emailSent
                ? 'Sauvegarde créée, données purgées, suppression du compte programmée. Email envoyé.'
                : 'Sauvegarde créée, données purgées, suppression du compte programmée, mais l’email n’a pas pu être envoyé.',
            null
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: ?string, error: ?string}
     */
    private function resetPassword(
        array $payload,
        ?string $actorIdentifier,
        ?string $clientIp,
        ?string $userAgent
    ): array {
        $member = $this->memberFromPayload($payload);
        if ($member === null) {
            return $this->result(false, null, 'Compte privé introuvable.');
        }

        $userId = (int) $member['id'];
        $email = (string) $member['email'];
        $token = $this->privateUserRepository->createPasswordResetToken($userId, $clientIp, $userAgent);
        if ($token === null) {
            $this->logAction('admin.private.password_reset_failed', $actorIdentifier, $userId, $email, 'warning');

            return $this->result(false, null, 'Impossible de générer un jeton de réinitialisation.');
        }

        $emailSent = $this->sendPasswordResetEmail($email, $token, $actorIdentifier, $userId);
        if (!$emailSent) {
            return $this->result(false, null, 'La demande de réinitialisation a été créée, mais l’email n’a pas pu être envoyé.');
        }

        $this->logAction('admin.private.password_reset_requested', $actorIdentifier, $userId, $email);

        return $this->result(true, 'Demande de réinitialisation envoyée.', null);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: ?string, error: ?string}
     */
    private function assignModules(array $payload, ?string $actorIdentifier): array
    {
        $member = $this->memberFromPayload($payload);
        if ($member === null) {
            return $this->result(false, null, 'Compte privé introuvable.');
        }

        $rawCodes = $this->rawModuleCodes($payload['modules'] ?? []);
        $validCodes = $this->modulePermissionRepository->validModuleCodesFromPayload($rawCodes);
        if (count($rawCodes) !== count($validCodes)) {
            return $this->result(false, null, 'Un module demandé est inconnu.');
        }

        $userId = (int) $member['id'];
        $email = (string) $member['email'];
        $blockedRevocations = $this->modulePermissionRepository->blockedModuleRevocations($userId, $validCodes);
        if ($blockedRevocations !== []) {
            $names = array_map(
                static fn (array $module): string => $module['name'],
                $blockedRevocations
            );

            return $this->result(
                false,
                null,
                'Impossible de retirer un module contenant encore des informations : ' . implode(', ', array_filter($names)) . '.'
            );
        }

        if (!$this->modulePermissionRepository->setUserModules($userId, $validCodes, $actorIdentifier)) {
            $this->logAction('admin.private.modules_update_failed', $actorIdentifier, $userId, $email, 'warning');

            return $this->result(false, null, 'Impossible de mettre à jour les modules privés.');
        }

        $this->logAction(
            'admin.private.modules_updated',
            $actorIdentifier,
            $userId,
            $email,
            'info',
            ['modules' => $validCodes]
        );

        return $this->result(true, 'Modules privés mis à jour.', null);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{id: int, email: string, status: string}|null
     */
    private function memberFromPayload(array $payload): ?array
    {
        $userId = $this->positiveId($payload['private_user_id'] ?? $payload['member_id'] ?? null);
        if ($userId === null) {
            return null;
        }

        $member = $this->privateUserRepository->findById($userId);
        if (!is_array($member)) {
            return null;
        }

        $email = is_string($member['email'] ?? null) ? trim((string) $member['email']) : '';
        $status = is_string($member['status'] ?? null) ? $this->normalizeStatusFilter((string) $member['status']) : '';
        if ($email === '' || $status === '') {
            return null;
        }

        return [
            'id' => $userId,
            'email' => $email,
            'status' => $status,
        ];
    }

    private function normalizeStatusFilter(?string $statusFilter): string
    {
        $normalized = strtolower(trim((string) $statusFilter));

        return in_array($normalized, self::ALLOWED_STATUS_FILTERS, true) ? $normalized : '';
    }

    private function normalizeAction(mixed $action): string
    {
        $normalized = strtolower(trim((string) $action));

        return in_array($normalized, self::ALLOWED_ACTIONS, true) ? $normalized : '';
    }

    private function normalizeEmail(mixed $email): string
    {
        $normalized = strtolower(trim((string) $email));
        if ($normalized === '' || !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return '';
        }

        return $normalized;
    }

    private function placeholderPasswordHash(): string
    {
        try {
            $secret = bin2hex(random_bytes(32));
        } catch (\Throwable) {
            return '';
        }

        $hash = password_hash($secret, PASSWORD_ARGON2ID);

        return is_string($hash) ? $hash : '';
    }

    private function positiveId(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    /**
     * @return array<int, string>
     */
    private function rawModuleCodes(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $codes = [];
        foreach ($value as $moduleCode) {
            if (!is_scalar($moduleCode)) {
                continue;
            }

            $code = strtolower(trim((string) $moduleCode));
            if ($code === '') {
                continue;
            }

            $codes[$code] = $code;
        }

        return array_values($codes);
    }

    private function sendActivationEmail(string $email, string $token, ?string $actorIdentifier, int $userId): void
    {
        $url = app_url(private_portal_url('activate') . '/' . rawurlencode($token));
        $message = $this->renderPrivateMailTemplate($this->privateMailTemplate(
            'admin_invite_body',
            "Bonjour,\n\nVotre espace privé est prêt.\n\nIdentifiant de connexion : {{email}}\nLien d'activation : {{activation_url}}\n\nChoisissez votre mot de passe depuis ce lien sécurisé."
        ), $email, ['activation_url' => $url]);

        $this->sendPrivateEmail(
            $email,
            $this->renderPrivateMailTemplate(
                $this->privateMailSubject('admin_invite_subject', 'Activation de votre espace privé'),
                $email,
                ['activation_url' => $url]
            ),
            $this->plainTextToHtml($message),
            'admin.private.invite_email',
            $actorIdentifier,
            $userId
        );
    }

    private function sendPasswordResetEmail(string $email, string $token, ?string $actorIdentifier, int $userId): bool
    {
        $url = app_url(private_portal_url('password_reset') . '/' . rawurlencode($token));
        $message = $this->renderPrivateMailTemplate($this->privateMailTemplate(
            'password_reset_body',
            "Bonjour,\n\nRéinitialisez votre mot de passe avec ce lien sécurisé : {{reset_url}}"
        ), $email, ['reset_url' => $url]);

        return $this->sendPrivateEmail(
            $email,
            $this->renderPrivateMailTemplate(
                $this->privateMailSubject('password_reset_subject', 'Réinitialisation de votre espace privé'),
                $email,
                ['reset_url' => $url]
            ),
            $this->plainTextToHtml($message),
            'admin.private.password_reset_email',
            $actorIdentifier,
            $userId
        );
    }

    private function sendPrivateEmail(
        string $email,
        string $subject,
        string $html,
        string $event,
        ?string $actorIdentifier,
        int $userId
    ): bool {
        $mailConfig = app_config('private.mail', []);
        if (!is_array($mailConfig) || !($mailConfig['enabled'] ?? false)) {
            $this->logAction($event . '_failed', $actorIdentifier, $userId, $email, 'warning');

            return false;
        }

        if (!function_exists('send_private_email')) {
            $mailerPath = ROOT_PATH . '/core/mailer.php';
            if (is_file($mailerPath)) {
                require_once $mailerPath;
            }
        }

        $sent = function_exists('send_private_email')
            ? send_private_email($email, $subject, $html)
            : false;

        $this->logAction(
            $sent ? $event . '_sent' : $event . '_failed',
            $actorIdentifier,
            $userId,
            $email,
            $sent ? 'info' : 'warning'
        );

        return $sent;
    }

    /**
     * @param array<string, string> $variables
     */
    private function sendTemplateEmail(
        string $email,
        string $subjectKey,
        string $subjectFallback,
        string $bodyKey,
        string $bodyFallback,
        string $event,
        ?string $actorIdentifier,
        int $userId,
        array $variables = []
    ): bool {
        return $this->sendPrivateEmail(
            $email,
            $this->renderPrivateMailTemplate($this->privateMailSubject($subjectKey, $subjectFallback), $email, $variables),
            $this->plainTextToHtml($this->renderPrivateMailTemplate($this->privateMailTemplate($bodyKey, $bodyFallback), $email, $variables)),
            $event,
            $actorIdentifier,
            $userId
        );
    }

    private function privateMailSubject(string $key, string $fallback): string
    {
        $subject = $this->privateMailTemplate($key, $fallback);

        return sanitize_text_field($subject, 180);
    }

    private function privateMailTemplate(string $key, string $fallback = ''): string
    {
        $template = app_config('private.mail.templates.' . $key, $fallback);

        return is_scalar($template) ? (string) $template : $fallback;
    }

    /**
     * @param array<string, string> $variables
     */
    private function renderPrivateMailTemplate(string $template, string $email, array $variables = []): string
    {
        $commonVariables = [
            'email' => $email,
            'today' => date('d/m/Y'),
            'login_url' => app_url(private_portal_url('login')),
            'private_url' => app_url(private_portal_url('login')),
            'site_name' => (string) app_config('site.name', 'Les Caramagnols'),
            'reply_to' => (string) app_config('private.mail.reply_to', 'private@lescaramagnols.com'),
        ];
        $replacements = [];
        foreach (array_merge($commonVariables, $variables) as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string) $value;
        }

        return strtr($template, $replacements);
    }

    private function plainTextToHtml(string $message): string
    {
        $message = sanitize_text_field($message, 4000);

        return '<p>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'), false) . '</p>';
    }

    private function dataProtectionService(): PrivateDataProtectionService
    {
        if (!$this->dataProtectionService instanceof PrivateDataProtectionService) {
            $this->dataProtectionService = new PrivateDataProtectionService(editorial_database());
        }

        return $this->dataProtectionService;
    }

    /**
     * @return array{success: bool, message: ?string, error: ?string}
     */
    private function result(bool $success, ?string $message, ?string $error): array
    {
        return [
            'success' => $success,
            'message' => $message,
            'error' => $error,
        ];
    }

    /**
     * @param array<string, mixed> $extraContext
     */
    private function logAction(
        string $event,
        ?string $actorIdentifier,
        int $userId,
        string $email,
        string $level = 'info',
        array $extraContext = []
    ): void {
        if (!$this->eventLogger instanceof AppEventLogger) {
            return;
        }

        $this->eventLogger->security(
            $event,
            array_merge(
                [
                    'actor' => AppEventLogger::maskIdentifier($actorIdentifier),
                    'private_user_id' => $userId,
                    'private_user_email' => AppEventLogger::maskEmail($email),
                ],
                $extraContext
            ),
            $level
        );
    }
}
