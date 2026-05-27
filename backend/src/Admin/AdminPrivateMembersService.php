<?php

declare(strict_types=1);

namespace Caramagnols\Admin;

use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;

final class AdminPrivateMembersService
{
    /** @var array<int, string> */
    private const ALLOWED_STATUS_FILTERS = ['invited', 'active', 'suspended', 'disabled', 'deleted'];

    /** @var array<int, string> */
    private const ALLOWED_ACTIONS = ['invite', 'resend', 'suspend', 'reset', 'anonymize', 'modules'];

    public function __construct(
        private readonly PrivateUserRepository $privateUserRepository,
        private readonly PrivateModulePermissionRepository $modulePermissionRepository,
        private readonly ?AppEventLogger $eventLogger = null
    ) {
    }

    /**
     * @return array{
     *   statusFilter: string,
     *   searchQuery: string,
     *   members: array<int, array<string, mixed>>,
     *   stats: array<string, int>,
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
        $members = array_map(
            fn (array $member): array => $this->memberView($member),
            $members
        );

        return [
            'statusFilter' => $normalizedStatusFilter,
            'searchQuery' => $normalizedSearchQuery,
            'members' => $members,
            'stats' => $this->membersStats($members),
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
            'reset' => $this->resetPassword($payload, $actorIdentifier, $clientIp, $userAgent),
            'anonymize' => $this->anonymize($payload, $actorIdentifier),
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

        return $member;
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
        if (is_array($existing) && $this->normalizeStatusFilter((string) ($existing['status'] ?? '')) !== 'deleted') {
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
        if ($status === 'deleted') {
            return $this->result(false, null, 'Un compte anonymisé ne peut pas être suspendu.');
        }

        $userId = (int) $member['id'];
        $email = (string) $member['email'];
        if ($status !== 'suspended' && !$this->privateUserRepository->updateStatus($userId, 'suspended')) {
            $this->logAction('admin.private.member_suspend_failed', $actorIdentifier, $userId, $email, 'warning');

            return $this->result(false, null, 'Impossible de suspendre le compte privé.');
        }

        $this->logAction('admin.private.member_suspended', $actorIdentifier, $userId, $email);

        return $this->result(true, 'Compte privé suspendu.', null);
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

        $status = $this->normalizeStatusFilter((string) ($member['status'] ?? ''));
        if ($status === 'deleted') {
            return $this->result(false, null, 'Un compte anonymisé ne peut pas recevoir de reset.');
        }

        $userId = (int) $member['id'];
        $email = (string) $member['email'];
        $token = $this->privateUserRepository->createPasswordResetToken($userId, $clientIp, $userAgent);
        if ($token === null) {
            $this->logAction('admin.private.password_reset_failed', $actorIdentifier, $userId, $email, 'warning');

            return $this->result(false, null, 'Impossible de générer un jeton de réinitialisation.');
        }

        $this->sendPasswordResetEmail($email, $token, $actorIdentifier, $userId);
        $this->logAction('admin.private.password_reset_requested', $actorIdentifier, $userId, $email);

        return $this->result(true, 'Réinitialisation privée enregistrée.', null);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: ?string, error: ?string}
     */
    private function anonymize(array $payload, ?string $actorIdentifier): array
    {
        $member = $this->memberFromPayload($payload);
        if ($member === null) {
            return $this->result(false, null, 'Compte privé introuvable.');
        }

        $status = $this->normalizeStatusFilter((string) ($member['status'] ?? ''));
        if ($status === 'deleted') {
            return $this->result(true, 'Compte privé déjà anonymisé.', null);
        }

        $userId = (int) $member['id'];
        $email = (string) $member['email'];
        if (!$this->privateUserRepository->anonymize($userId)) {
            $this->logAction('admin.private.member_anonymize_failed', $actorIdentifier, $userId, $email, 'warning');

            return $this->result(false, null, 'Impossible d’anonymiser le compte privé.');
        }

        $this->logAction('admin.private.member_anonymized', $actorIdentifier, $userId, $email);

        return $this->result(true, 'Compte privé anonymisé.', null);
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

        $status = $this->normalizeStatusFilter((string) ($member['status'] ?? ''));
        if ($status === 'deleted') {
            return $this->result(false, null, 'Un compte anonymisé ne peut pas recevoir de modules.');
        }

        $rawCodes = $this->rawModuleCodes($payload['modules'] ?? []);
        $validCodes = $this->modulePermissionRepository->validModuleCodesFromPayload($rawCodes);
        if (count($rawCodes) !== count($validCodes)) {
            return $this->result(false, null, 'Un module demandé est inconnu.');
        }

        $userId = (int) $member['id'];
        $email = (string) $member['email'];
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
        $url = private_portal_url('activate') . '/' . rawurlencode($token);
        $this->sendPrivateEmail(
            $email,
            'Activation de votre espace privé',
            sprintf(
                '<p>Bonjour,</p><p>Activez votre espace privé avec ce lien sécurisé :</p><p><a href="%1$s">%1$s</a></p>',
                htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
            ),
            'admin.private.invite_email',
            $actorIdentifier,
            $userId
        );
    }

    private function sendPasswordResetEmail(string $email, string $token, ?string $actorIdentifier, int $userId): void
    {
        $url = private_portal_url('password_reset') . '/' . rawurlencode($token);
        $this->sendPrivateEmail(
            $email,
            'Réinitialisation de votre espace privé',
            sprintf(
                '<p>Bonjour,</p><p>Réinitialisez votre mot de passe avec ce lien sécurisé :</p><p><a href="%1$s">%1$s</a></p>',
                htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
            ),
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
    ): void {
        $mailConfig = app_config('mail', []);
        if (!is_array($mailConfig) || $mailConfig === []) {
            $this->logAction($event . '_failed', $actorIdentifier, $userId, $email, 'warning');

            return;
        }

        if (!function_exists('send_notification_email')) {
            $mailerPath = ROOT_PATH . '/core/mailer.php';
            if (is_file($mailerPath)) {
                require_once $mailerPath;
            }
        }

        $sent = function_exists('send_notification_email')
            ? send_notification_email($email, $subject, $html)
            : false;

        $this->logAction(
            $sent ? $event . '_sent' : $event . '_failed',
            $actorIdentifier,
            $userId,
            $email,
            $sent ? 'info' : 'warning'
        );
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
