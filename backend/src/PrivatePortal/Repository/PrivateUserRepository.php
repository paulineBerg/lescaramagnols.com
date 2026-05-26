<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Repository;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class PrivateUserRepository
{
    private const ALLOWED_STATUSES = ['invited', 'active', 'suspended', 'disabled', 'deleted'];

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
            $this->database->ensureReady();
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
            $this->database->ensureReady();
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
            $this->database->ensureReady();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s` (`email`, `password_hash`, `status`, `created_at`, `updated_at`) VALUES (:email, :password_hash, :status, :now, :now)',
                    $this->table()
                )
            );
            $statement->execute([
                'email' => $email,
                'password_hash' => $passwordHash,
                'status' => $status,
                'now' => $now,
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
            $this->database->ensureReady();
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
            $this->database->ensureReady();
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

    public function recordLogin(int $userId, string $ip): void
    {
        if ($userId <= 0) {
            return;
        }

        $ipHash = $this->normalizeIpHash($ip);

        try {
            $this->database->ensureReady();
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

    private function currentDateTime(): string
    {
        return date('Y-m-d H:i:s');
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

    private function isSupportedStatus(string $status): bool
    {
        return in_array($this->normalizeStatus($status), self::ALLOWED_STATUSES, true);
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
}
