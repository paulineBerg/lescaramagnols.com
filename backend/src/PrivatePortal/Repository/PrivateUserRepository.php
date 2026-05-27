<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Repository;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class PrivateUserRepository
{
    private const ALLOWED_STATUSES = ['invited', 'active', 'suspended', 'disabled', 'deleted'];

    private bool $privateSchemaReady = false;

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function table(): string
    {
        return $this->database->table('private_users');
    }

    public function findByEmail(string $email): ?array
    {
        $email = $this->normalizeEmail($email);
        if ($email === '') {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf('SELECT * FROM `%s` WHERE `email` = :email LIMIT 1', $this->table())
            );
            $statement->execute(['email' => $email]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $row : null;
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf('SELECT * FROM `%s` WHERE `id` = :id LIMIT 1', $this->table())
            );
            $statement->execute(['id' => $id]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $row : null;
    }

    /**
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function listMembers(?string $statusFilter = null, string $search = '', int $limit = 200): array
    {
        $statusFilter = is_string($statusFilter) ? $this->normalizeOptionalStatus($statusFilter) : '';
        $search = trim((string) $search);
        $limit = max(1, min(500, $limit));
        $query = sprintf('SELECT `id`, `email`, `status`, `created_at`, `updated_at`, `last_login_at` FROM `%s`', $this->table());
        $conditions = [];
        $params = [];

        if ($statusFilter !== '') {
            $query .= ' WHERE `status` = :status';
            $params['status'] = $statusFilter;
            $conditions[] = 'status';
        }

        if ($search !== '') {
            $safeSearch = $this->normalizeSearch($search);
            $query .= $conditions !== [] ? ' AND ' : ' WHERE ';
            $query .= "`email` LIKE :search ESCAPE '\\\\'";
            $params['search'] = '%' . $safeSearch . '%';
        }

        $query .= ' ORDER BY `updated_at` DESC, `id` DESC LIMIT :limit';

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare($query);
            foreach ($params as $key => $value) {
                $statement->bindValue(':' . $key, $value, PDO::PARAM_STR);
            }
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $members = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = $this->toIntId($row['id'] ?? null);
            $email = is_string($row['email'] ?? null) ? strtolower(trim($row['email'])) : '';
            $status = is_string($row['status'] ?? null) ? $this->normalizeStatus($row['status']) : '';
            if ($id === null || $email === '') {
                continue;
            }

            $members[] = [
                'id' => $id,
                'email' => $email,
                'status' => $status,
                'createdAt' => is_string($row['created_at'] ?? null) ? $row['created_at'] : '',
                'updatedAt' => is_string($row['updated_at'] ?? null) ? $row['updated_at'] : '',
                'lastLoginAt' => is_string($row['last_login_at'] ?? null) ? $row['last_login_at'] : '',
            ];
        }

        return $members;
    }

    public function findActiveByEmail(string $email): ?array
    {
        $user = $this->findByEmail($email);
        if (!is_array($user) || strtolower((string) ($user['status'] ?? '')) !== 'active') {
            return null;
        }

        return $user;
    }

    public function create(string $email, string $passwordHash, string $status = 'invited'): ?int
    {
        $email = $this->normalizeEmail($email);
        if ($email === '' || $passwordHash === '' || !$this->isSupportedStatus($status)) {
            return null;
        }

        $now = $this->currentDateTime();

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s` (`email`, `password_hash`, `status`, `created_at`, `updated_at`)
                     VALUES (:email, :password_hash, :status, :created_at, :updated_at)',
                    $this->table()
                )
            );
            $statement->execute([
                'email' => $email,
                'password_hash' => $passwordHash,
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $insertId = (int) $this->database->pdo()->lastInsertId();
        } catch (\Throwable) {
            return null;
        }

        return $insertId > 0 ? $insertId : null;
    }

    public function ensureInvitedUser(string $email, string $passwordHash): ?int
    {
        $email = $this->normalizeEmail($email);
        if ($email === '' || $passwordHash === '') {
            return null;
        }

        $existing = $this->findByEmail($email);
        if (is_array($existing)) {
            $status = $this->normalizeStatus((string) ($existing['status'] ?? ''));

            return $status === 'deleted' ? null : $this->toIntId($existing['id']);
        }

        return $this->create($email, $passwordHash, 'invited');
    }

    public function createInviteToken(int $userId, string $email, ?int $adminId = null): ?string
    {
        $email = $this->normalizeEmail($email);
        if ($userId <= 0 || $email === '') {
            return null;
        }

        $token = $this->randomToken();
        if ($token === '') {
            return null;
        }
        $tokenHash = $this->hashSecret($token);

        $now = $this->currentDateTime();
        $expiresAt = date(
            'Y-m-d H:i:s',
            time() + (max(1, (int) app_config('private.invite_token_ttl_hours', 168)) * 3600)
        );

        try {
            $this->ensureSchema();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();

            $cancelStatement = $pdo->prepare(
                sprintf(
                    'UPDATE `%s` SET `status` = :cancelled WHERE `email` = :email AND `status` = :pending',
                    $this->inviteTable()
                )
            );
            $cancelStatement->execute([
                'cancelled' => 'cancelled',
                'email' => $email,
                'pending' => 'pending',
            ]);

            $statement = $pdo->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`private_user_id`, `email`, `token_hash`, `invited_by_admin_id`, `status`, `requested_at`, `expires_at`)
                     VALUES
                        (:user_id, :email, :token_hash, :admin_id, :status, :requested_at, :expires_at)',
                    $this->inviteTable()
                )
            );
            $statement->execute([
                'user_id' => $userId,
                'email' => $email,
                'token_hash' => $tokenHash,
                'admin_id' => $adminId,
                'status' => 'pending',
                'requested_at' => $now,
                'expires_at' => $expiresAt,
            ]);

            $pdo->commit();

            return $token;
        } catch (\Throwable) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return null;
        }
    }

    public function createPasswordResetToken(int $userId, ?string $requestIp = null, ?string $userAgent = null): ?string
    {
        if ($userId <= 0) {
            return null;
        }

        $token = $this->randomToken();
        if ($token === '') {
            return null;
        }
        $tokenHash = $this->hashSecret($token);

        $now = $this->currentDateTime();
        $expiresAt = date(
            'Y-m-d H:i:s',
            time() + (max(1, (int) app_config('private.password_reset_token_ttl_minutes', 30)) * 60)
        );
        $requestIp = is_string($requestIp) && trim($requestIp) !== '' ? substr(trim($requestIp), 0, 39) : null;
        $userAgentHash = is_string($userAgent) && trim($userAgent) !== ''
            ? hash('sha256', trim($userAgent))
            : null;

        try {
            $this->ensureSchema();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();

            $expireStatement = $pdo->prepare(
                sprintf(
                    'UPDATE `%s` SET `used_at` = :used_at WHERE `private_user_id` = :user_id AND `used_at` IS NULL',
                    $this->passwordResetTable()
                )
            );
            $expireStatement->execute([
                'used_at' => $now,
                'user_id' => $userId,
            ]);

            $statement = $pdo->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`private_user_id`, `token_hash`, `created_at`, `expires_at`, `requested_at_ip`, `request_user_agent_hash`)
                     VALUES
                        (:user_id, :token_hash, :created_at, :expires_at, :requested_at_ip, :user_agent_hash)',
                    $this->passwordResetTable()
                )
            );
            $statement->execute([
                'user_id' => $userId,
                'token_hash' => $tokenHash,
                'created_at' => $now,
                'expires_at' => $expiresAt,
                'requested_at_ip' => $requestIp,
                'user_agent_hash' => $userAgentHash,
            ]);

            $pdo->commit();

            return $token;
        } catch (\Throwable) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activateByInviteToken(
        string $token,
        string $passwordHash,
        ?string $ip = null,
        ?string $userAgent = null
    ): ?array {
        $invite = $this->pendingInviteByToken($token);
        if ($invite === null || $passwordHash === '') {
            return null;
        }

        $inviteId = $this->toIntId($invite['id'] ?? null);
        $userId = $this->toIntId($invite['private_user_id'] ?? null);
        if ($inviteId === null || $userId === null) {
            return null;
        }

        $now = $this->currentDateTime();
        $ipHash = $this->normalizeIpHash((string) $ip);
        $userAgentHash = is_string($userAgent) && trim($userAgent) !== ''
            ? hash('sha256', trim($userAgent))
            : null;

        try {
            $this->ensureSchema();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();

            $userStatement = $pdo->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `password_hash` = :password_hash,
                         `status` = :status,
                         `updated_at` = :updated_at
                     WHERE `id` = :id AND `status` = :invited',
                    $this->table()
                )
            );
            $userStatement->execute([
                'password_hash' => $passwordHash,
                'status' => 'active',
                'updated_at' => $now,
                'id' => $userId,
                'invited' => 'invited',
            ]);

            if ($userStatement->rowCount() < 1) {
                $pdo->rollBack();

                return null;
            }

            $inviteStatement = $pdo->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `status` = :status,
                         `used_at` = :used_at,
                         `ip_hash` = :ip_hash,
                         `user_agent_hash` = :user_agent_hash
                     WHERE `id` = :id AND `status` = :pending',
                    $this->inviteTable()
                )
            );
            $inviteStatement->execute([
                'status' => 'accepted',
                'used_at' => $now,
                'ip_hash' => $ipHash !== '' ? hash('sha256', $ipHash) : null,
                'user_agent_hash' => $userAgentHash,
                'id' => $inviteId,
                'pending' => 'pending',
            ]);

            $pdo->commit();
        } catch (\Throwable) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return null;
        }

        return $this->findById($userId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resetPasswordByToken(string $token, string $passwordHash, ?string $ip = null): ?array
    {
        $reset = $this->pendingPasswordResetByToken($token);
        if ($reset === null || $passwordHash === '') {
            return null;
        }

        $resetId = $this->toIntId($reset['id'] ?? null);
        $userId = $this->toIntId($reset['private_user_id'] ?? null);
        if ($resetId === null || $userId === null) {
            return null;
        }

        $now = $this->currentDateTime();
        $ip = is_string($ip) && trim($ip) !== '' ? substr(trim($ip), 0, 39) : null;

        try {
            $this->ensureSchema();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();

            $userStatement = $pdo->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `password_hash` = :password_hash,
                         `status` = :active,
                         `updated_at` = :updated_at
                     WHERE `id` = :id AND `status` IN (\'active\', \'suspended\')',
                    $this->table()
                )
            );
            $userStatement->execute([
                'password_hash' => $passwordHash,
                'active' => 'active',
                'updated_at' => $now,
                'id' => $userId,
            ]);

            if ($userStatement->rowCount() < 1) {
                $pdo->rollBack();

                return null;
            }

            $resetStatement = $pdo->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `used_at` = :used_at,
                         `used_at_ip` = :used_at_ip
                     WHERE `id` = :id AND `used_at` IS NULL',
                    $this->passwordResetTable()
                )
            );
            $resetStatement->execute([
                'used_at' => $now,
                'used_at_ip' => $ip,
                'id' => $resetId,
            ]);

            $pdo->commit();
        } catch (\Throwable) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return null;
        }

        return $this->findById($userId);
    }

    public function activateWithPassword(int $userId, string $passwordHash): bool
    {
        if ($userId <= 0 || $passwordHash === '') {
            return false;
        }

        return $this->setPasswordHash($userId, $passwordHash) && $this->updateStatus($userId, 'active');
    }

    public function setPasswordHash(int $userId, string $passwordHash): bool
    {
        if ($userId <= 0 || $passwordHash === '') {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s` SET `password_hash` = :password_hash, `updated_at` = :updated_at WHERE `id` = :id',
                    $this->table()
                )
            );
            $statement->execute([
                'password_hash' => $passwordHash,
                'updated_at' => $this->currentDateTime(),
                'id' => $userId,
            ]);

            return $statement->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function updateStatus(int $userId, string $status): bool
    {
        if ($userId <= 0 || !$this->isSupportedStatus($status)) {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s` SET `status` = :status, `updated_at` = :updated_at WHERE `id` = :id',
                    $this->table()
                )
            );
            $statement->execute([
                'status' => $status,
                'updated_at' => $this->currentDateTime(),
                'id' => $userId,
            ]);

            return $statement->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function anonymize(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $passwordHash = $this->randomTokenHash();
        if ($passwordHash === '') {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `email` = :email,
                         `password_hash` = :password_hash,
                         `status` = :status,
                         `updated_at` = :updated_at,
                         `last_login_at` = NULL,
                         `last_login_ip` = NULL,
                         `mfa_enabled` = 0,
                         `mfa_secret_encrypted` = NULL
                     WHERE `id` = :id',
                    $this->table()
                )
            );
            $statement->execute([
                'email' => 'deleted-private-user-' . $userId . '@private.invalid',
                'password_hash' => $passwordHash,
                'status' => 'deleted',
                'updated_at' => $this->currentDateTime(),
                'id' => $userId,
            ]);

            return $statement->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function recordLogin(int $userId, string $ip): void
    {
        if ($userId <= 0) {
            return;
        }

        $ipHash = $this->normalizeIpHash($ip);

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s` SET `last_login_at` = :last_login_at, `last_login_ip` = :last_login_ip, `updated_at` = :updated_at WHERE `id` = :id',
                    $this->table()
                )
            );
            $statement->execute([
                'last_login_at' => $this->currentDateTime(),
                'last_login_ip' => $ipHash,
                'updated_at' => $this->currentDateTime(),
                'id' => $userId,
            ]);
        } catch (\Throwable) {
            return;
        }
    }

    public function isStatusActive(string $status): bool
    {
        return $this->normalizeStatus($status) === 'active';
    }

    public function consumeMfaBackupCode(int $userId, string $code): bool
    {
        $code = trim($code);
        if ($userId <= 0 || $code === '') {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT `id`, `code_hash`
                     FROM `%s`
                     WHERE `private_user_id` = :user_id AND `used_at` IS NULL
                     ORDER BY `id` ASC
                     LIMIT 50',
                    $this->mfaBackupCodeTable()
                )
            );
            $statement->execute(['user_id' => $userId]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                if (!is_array($row) || !is_string($row['code_hash'] ?? null)) {
                    continue;
                }

                if (!password_verify($code, (string) $row['code_hash'])) {
                    continue;
                }

                $codeId = $this->toIntId($row['id'] ?? null);
                if ($codeId === null) {
                    return false;
                }

                $update = $this->database->pdo()->prepare(
                    sprintf(
                        'UPDATE `%s` SET `used_at` = :used_at WHERE `id` = :id AND `used_at` IS NULL',
                        $this->mfaBackupCodeTable()
                    )
                );
                $update->execute([
                    'used_at' => $this->currentDateTime(),
                    'id' => $codeId,
                ]);

                return $update->rowCount() > 0;
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    private function currentDateTime(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function inviteTable(): string
    {
        return $this->database->table('private_user_invites');
    }

    private function passwordResetTable(): string
    {
        return $this->database->table('private_password_resets');
    }

    private function mfaBackupCodeTable(): string
    {
        return $this->database->table('private_mfa_backup_codes');
    }

    private function ensureSchema(): void
    {
        if ($this->privateSchemaReady) {
            return;
        }

        $this->database->ensureReady();
        $pdo = $this->database->pdo();
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `email` VARCHAR(254) NOT NULL,
                    `password_hash` VARCHAR(255) NOT NULL,
                    `status` ENUM(\'invited\', \'active\', \'suspended\', \'disabled\', \'deleted\') NOT NULL DEFAULT \'invited\',
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `last_login_at` DATETIME NULL,
                    `last_login_ip` VARCHAR(64) NULL,
                    `mfa_enabled` TINYINT(1) NOT NULL DEFAULT 0,
                    `mfa_secret_encrypted` VARBINARY(255) NULL,
                    UNIQUE KEY `uq_private_users_email` (`email`),
                    KEY `idx_private_users_status` (`status`),
                    KEY `idx_private_users_updated_at` (`updated_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->table()
            )
        );
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `private_user_id` INT NULL,
                    `email` VARCHAR(254) NOT NULL,
                    `token_hash` VARCHAR(255) NOT NULL,
                    `invited_by_admin_id` INT NULL,
                    `status` ENUM(\'pending\', \'accepted\', \'cancelled\', \'expired\') NOT NULL DEFAULT \'pending\',
                    `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `used_at` DATETIME NULL,
                    `expires_at` DATETIME NOT NULL,
                    `attempts_count` INT NOT NULL DEFAULT 0,
                    `ip_hash` VARCHAR(64) NULL,
                    `user_agent_hash` VARCHAR(255) NULL,
                    UNIQUE KEY `uq_private_user_invites_token` (`token_hash`),
                    KEY `idx_private_user_invites_user` (`private_user_id`),
                    KEY `idx_private_user_invites_email_status` (`email`, `status`),
                    KEY `idx_private_user_invites_expires_at` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->inviteTable()
            )
        );
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `private_user_id` INT NOT NULL,
                    `token_hash` VARCHAR(255) NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `expires_at` DATETIME NOT NULL,
                    `used_at` DATETIME NULL,
                    `requested_at_ip` VARCHAR(39) NULL,
                    `used_at_ip` VARCHAR(39) NULL,
                    `request_user_agent_hash` VARCHAR(255) NULL,
                    UNIQUE KEY `uq_private_password_resets_token` (`token_hash`),
                    KEY `idx_private_password_resets_user` (`private_user_id`),
                    KEY `idx_private_password_resets_expires_at` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->passwordResetTable()
            )
        );
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `private_user_id` INT NOT NULL,
                    `code_hash` VARCHAR(255) NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `used_at` DATETIME NULL,
                    KEY `idx_private_mfa_backup_codes_user` (`private_user_id`),
                    KEY `idx_private_mfa_backup_codes_used` (`used_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->mfaBackupCodeTable()
            )
        );

        $this->privateSchemaReady = true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pendingInviteByToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s`
                     WHERE `status` = :status AND `expires_at` >= :now
                     ORDER BY `requested_at` DESC
                     LIMIT 200',
                    $this->inviteTable()
                )
            );
            $statement->execute([
                'status' => 'pending',
                'now' => $this->currentDateTime(),
            ]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

            return $this->matchingTokenRow($rows, $token);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pendingPasswordResetByToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s`
                     WHERE `used_at` IS NULL AND `expires_at` >= :now
                     ORDER BY `created_at` DESC
                     LIMIT 200',
                    $this->passwordResetTable()
                )
            );
            $statement->execute(['now' => $this->currentDateTime()]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

            return $this->matchingTokenRow($rows, $token);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<string, mixed>|null
     */
    private function matchingTokenRow(array $rows, string $token): ?array
    {
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['token_hash'] ?? null)) {
                continue;
            }

            if (password_verify($token, (string) $row['token_hash'])) {
                return $row;
            }
        }

        return null;
    }

    private function toIntId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $parsed = (int) $value;
        return $parsed > 0 ? $parsed : null;
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return in_array($normalized, self::ALLOWED_STATUSES, true) ? $normalized : 'invited';
    }

    private function normalizeOptionalStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return in_array($normalized, self::ALLOWED_STATUSES, true) ? $normalized : '';
    }

    private function isSupportedStatus(string $status): bool
    {
        return in_array(strtolower(trim($status)), self::ALLOWED_STATUSES, true);
    }

    private function normalizeEmail(string $email): string
    {
        $normalized = trim($email);
        if ($normalized === '') {
            return '';
        }

        $normalized = strtolower($normalized);
        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return '';
        }

        return $normalized;
    }

    private function normalizeIpHash(string $ip): string
    {
        $normalized = trim($ip);
        if ($normalized === '') {
            return '';
        }

        return $normalized;
    }

    private function normalizeSearch(string $search): string
    {
        $normalized = trim((string) $search);
        if (function_exists('mb_strtolower')) {
            $normalized = mb_strtolower($normalized, 'UTF-8');
        }
        $normalized = str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $normalized
        );

        return function_exists('mb_substr')
            ? mb_substr($normalized, 0, 254, 'UTF-8')
            : substr($normalized, 0, 254);
    }

    private function randomTokenHash(): string
    {
        $token = $this->randomToken();
        if ($token === '') {
            return '';
        }

        return $this->hashSecret($token);
    }

    private function randomToken(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (\Throwable) {
            return '';
        }
    }

    private function hashSecret(string $secret): string
    {
        $hash = password_hash($secret, PASSWORD_ARGON2ID);

        return is_string($hash) ? $hash : '';
    }
}
