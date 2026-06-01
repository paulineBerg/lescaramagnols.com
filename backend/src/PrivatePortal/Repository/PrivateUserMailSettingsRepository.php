<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Repository;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class PrivateUserMailSettingsRepository
{
    private const SECRET_MASK = '******';
    private const ENCRYPTION_CIPHER = 'aes-256-gcm';
    private const ALLOWED_ENCRYPTIONS = ['', 'ssl', 'tls', 'starttls'];

    private bool $schemaReady = false;

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function table(): string
    {
        return $this->database->table('private_user_mail_settings');
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsForUser(int $privateUserId): array
    {
        $row = $this->rowForUser($privateUserId);
        if (!is_array($row)) {
            return $this->defaultSettings();
        }

        return $this->settingsFromRow($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function mailConfigForUser(int $privateUserId): ?array
    {
        $row = $this->rowForUser($privateUserId);
        if (!is_array($row)) {
            return null;
        }

        $settings = $this->settingsFromRow($row);
        if (empty($settings['enabled']) || !$this->isComplete($settings)) {
            return null;
        }

        $password = null;
        if (is_string($row['smtp_password_ciphertext'] ?? null) && trim((string) $row['smtp_password_ciphertext']) !== '') {
            $password = $this->decryptSecret((string) $row['smtp_password_ciphertext']);
            if ($password === null) {
                return null;
            }
        }

        return [
            'enabled' => true,
            'smtp_host' => (string) $settings['smtpHost'],
            'smtp_port' => (int) $settings['smtpPort'],
            'smtp_user' => (string) $settings['smtpUser'],
            'smtp_password' => $password ?? '',
            'smtp_encryption' => (string) $settings['smtpEncryption'],
            'from_address' => (string) $settings['fromAddress'],
            'from_name' => (string) $settings['fromName'],
            'reply_to' => (string) $settings['replyTo'],
        ];
    }

    public function isConfiguredForUser(int $privateUserId): bool
    {
        return $this->mailConfigForUser($privateUserId) !== null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, error: string, settings: array<string, mixed>}
     */
    public function saveForUser(int $privateUserId, array $payload): array
    {
        if ($privateUserId <= 0) {
            return $this->saveResult(false, 'invalid_user', $this->defaultSettings());
        }

        $existingRow = $this->rowForUser($privateUserId);
        $existingSettings = is_array($existingRow) ? $this->settingsFromRow($existingRow) : $this->defaultSettings();
        $settings = $this->normalizeSettings($payload, $existingSettings);
        if ($settings['errors'] !== []) {
            return $this->saveResult(false, (string) $settings['errors'][0], $settings['data']);
        }

        $passwordCiphertext = is_array($existingRow) && is_string($existingRow['smtp_password_ciphertext'] ?? null)
            ? (string) $existingRow['smtp_password_ciphertext']
            : null;
        $password = $this->secretFormValue($payload['smtp_password'] ?? '');
        $clearPassword = $this->booleanValue($payload['clear_smtp_password'] ?? false);
        if ($clearPassword) {
            $passwordCiphertext = null;
        } elseif ($password !== '' && $password !== self::SECRET_MASK) {
            $passwordCiphertext = $this->encryptSecret($password);
            if ($passwordCiphertext === null) {
                return $this->saveResult(false, 'encryption_unavailable', $settings['data']);
            }
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`private_user_id`, `is_enabled`, `smtp_host`, `smtp_port`, `smtp_user`,
                         `smtp_password_ciphertext`, `smtp_encryption`, `from_address`, `from_name`,
                         `reply_to`, `updated_at`)
                     VALUES
                        (:user_id, :enabled, :smtp_host, :smtp_port, :smtp_user,
                         :smtp_password_ciphertext, :smtp_encryption, :from_address, :from_name,
                         :reply_to, :updated_at)
                     ON DUPLICATE KEY UPDATE
                        `is_enabled` = VALUES(`is_enabled`),
                        `smtp_host` = VALUES(`smtp_host`),
                        `smtp_port` = VALUES(`smtp_port`),
                        `smtp_user` = VALUES(`smtp_user`),
                        `smtp_password_ciphertext` = VALUES(`smtp_password_ciphertext`),
                        `smtp_encryption` = VALUES(`smtp_encryption`),
                        `from_address` = VALUES(`from_address`),
                        `from_name` = VALUES(`from_name`),
                        `reply_to` = VALUES(`reply_to`),
                        `updated_at` = VALUES(`updated_at`)',
                    $this->table()
                )
            );
            $statement->execute([
                'user_id' => $privateUserId,
                'enabled' => $settings['data']['enabled'] ? 1 : 0,
                'smtp_host' => $settings['data']['smtpHost'],
                'smtp_port' => $settings['data']['smtpPort'],
                'smtp_user' => $settings['data']['smtpUser'] !== '' ? $settings['data']['smtpUser'] : null,
                'smtp_password_ciphertext' => $passwordCiphertext,
                'smtp_encryption' => $settings['data']['smtpEncryption'],
                'from_address' => $settings['data']['fromAddress'],
                'from_name' => $settings['data']['fromName'],
                'reply_to' => $settings['data']['replyTo'] !== '' ? $settings['data']['replyTo'] : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            return $this->saveResult(false, 'save_failed', $settings['data']);
        }

        return $this->saveResult(true, '', $this->settingsForUser($privateUserId));
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{success: bool, error: string, settings: array<string, mixed>}
     */
    private function saveResult(bool $success, string $error, array $settings): array
    {
        return [
            'success' => $success,
            'error' => $error,
            'settings' => $settings,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rowForUser(int $privateUserId): ?array
    {
        if ($privateUserId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf('SELECT * FROM `%s` WHERE `private_user_id` = :user_id LIMIT 1', $this->table())
            );
            $statement->execute(['user_id' => $privateUserId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function settingsFromRow(array $row): array
    {
        return [
            'enabled' => (int) ($row['is_enabled'] ?? 0) === 1,
            'smtpHost' => is_string($row['smtp_host'] ?? null) ? (string) $row['smtp_host'] : '',
            'smtpPort' => is_numeric($row['smtp_port'] ?? null) ? (int) $row['smtp_port'] : 587,
            'smtpUser' => is_string($row['smtp_user'] ?? null) ? (string) $row['smtp_user'] : '',
            'smtpPasswordConfigured' => is_string($row['smtp_password_ciphertext'] ?? null) && trim((string) $row['smtp_password_ciphertext']) !== '',
            'smtpEncryption' => is_string($row['smtp_encryption'] ?? null) ? (string) $row['smtp_encryption'] : 'tls',
            'fromAddress' => is_string($row['from_address'] ?? null) ? (string) $row['from_address'] : '',
            'fromName' => is_string($row['from_name'] ?? null) ? (string) $row['from_name'] : '',
            'replyTo' => is_string($row['reply_to'] ?? null) ? (string) $row['reply_to'] : '',
            'updatedAt' => is_string($row['updated_at'] ?? null) ? (string) $row['updated_at'] : '',
            'configured' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultSettings(): array
    {
        return [
            'enabled' => true,
            'smtpHost' => '',
            'smtpPort' => 587,
            'smtpUser' => '',
            'smtpPasswordConfigured' => false,
            'smtpEncryption' => 'tls',
            'fromAddress' => '',
            'fromName' => function_exists('app_config') ? (string) app_config('site.name', 'Les Caramagnols') : 'Les Caramagnols',
            'replyTo' => '',
            'updatedAt' => '',
            'configured' => false,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $fallback
     * @return array{data: array<string, mixed>, errors: array<int, string>}
     */
    private function normalizeSettings(array $payload, array $fallback): array
    {
        $host = $this->sanitizeText((string) ($payload['smtp_host'] ?? ($fallback['smtpHost'] ?? '')), 190);
        $port = filter_var((string) ($payload['smtp_port'] ?? ($fallback['smtpPort'] ?? 587)), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);
        $user = $this->sanitizeText((string) ($payload['smtp_user'] ?? ($fallback['smtpUser'] ?? '')), 190);
        $encryption = strtolower(trim((string) ($payload['smtp_encryption'] ?? ($fallback['smtpEncryption'] ?? 'tls'))));
        $fromAddress = strtolower(trim((string) ($payload['from_address'] ?? ($fallback['fromAddress'] ?? ''))));
        $fromName = $this->sanitizeText((string) ($payload['from_name'] ?? ($fallback['fromName'] ?? 'Les Caramagnols')), 120);
        $replyTo = strtolower(trim((string) ($payload['reply_to'] ?? ($fallback['replyTo'] ?? ''))));

        $data = [
            'enabled' => $this->booleanValue($payload['enabled'] ?? true),
            'smtpHost' => $host,
            'smtpPort' => is_int($port) ? $port : 0,
            'smtpUser' => $user,
            'smtpPasswordConfigured' => (bool) ($fallback['smtpPasswordConfigured'] ?? false),
            'smtpEncryption' => in_array($encryption, self::ALLOWED_ENCRYPTIONS, true) ? $encryption : 'tls',
            'fromAddress' => $fromAddress,
            'fromName' => $fromName !== '' ? $fromName : 'Les Caramagnols',
            'replyTo' => $replyTo,
            'updatedAt' => (string) ($fallback['updatedAt'] ?? ''),
            'configured' => (bool) ($fallback['configured'] ?? false),
        ];

        $errors = [];
        if ($host === '' || preg_match('/\A[a-z0-9][a-z0-9.\-]*\z/i', $host) !== 1) {
            $errors[] = 'invalid_host';
        }
        if (!is_int($port)) {
            $errors[] = 'invalid_port';
        }
        if (!in_array($encryption, self::ALLOWED_ENCRYPTIONS, true)) {
            $errors[] = 'invalid_encryption';
        }
        if ($fromAddress === '' || filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'invalid_from_email';
        }
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'invalid_reply_to';
        }

        return ['data' => $data, 'errors' => $errors];
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function isComplete(array $settings): bool
    {
        $host = trim((string) ($settings['smtpHost'] ?? ''));
        $port = (int) ($settings['smtpPort'] ?? 0);
        $user = trim((string) ($settings['smtpUser'] ?? ''));
        $fromAddress = trim((string) ($settings['fromAddress'] ?? ''));
        if ($host === '' || $port <= 0 || $fromAddress === '' || filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        return $user === '' || !empty($settings['smtpPasswordConfigured']);
    }

    private function encryptSecret(string $secret): ?string
    {
        $secret = trim($secret);
        $key = $this->encryptionKey();
        if ($secret === '' || $key === null || !function_exists('openssl_encrypt')) {
            return null;
        }

        try {
            $iv = random_bytes(12);
        } catch (\Throwable) {
            return null;
        }
        $tag = '';
        $ciphertext = openssl_encrypt($secret, self::ENCRYPTION_CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if (!is_string($ciphertext) || strlen($tag) !== 16) {
            return null;
        }

        return 'v1:' . base64_encode($iv . $tag . $ciphertext);
    }

    private function decryptSecret(string $payload): ?string
    {
        $payload = trim($payload);
        $key = $this->encryptionKey();
        if ($payload === '' || $key === null || !str_starts_with($payload, 'v1:') || !function_exists('openssl_decrypt')) {
            return null;
        }

        $decoded = base64_decode(substr($payload, 3), true);
        if (!is_string($decoded) || strlen($decoded) <= 28) {
            return null;
        }

        $iv = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);
        $plain = openssl_decrypt($ciphertext, self::ENCRYPTION_CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);

        return is_string($plain) ? $plain : null;
    }

    private function encryptionKey(): ?string
    {
        $secret = function_exists('app_config')
            ? trim((string) app_config('private.mail.user_settings_encryption_key', ''))
            : '';
        if ($secret === '' && function_exists('env')) {
            $secret = trim((string) env('PRIVATE_MAIL_SETTINGS_ENCRYPTION_KEY', ''));
        }
        if ($secret === '' && function_exists('app_config')) {
            $secret = trim((string) app_config('private.discussions.attachment_encryption_key', ''));
        }

        if (str_starts_with($secret, 'base64:')) {
            $decoded = base64_decode(substr($secret, 7), true);
            if (is_string($decoded) && strlen($decoded) >= 32) {
                return substr($decoded, 0, 32);
            }
        }

        if (strlen($secret) < 32) {
            return null;
        }

        return hash('sha256', $secret, true);
    }

    private function secretFormValue(mixed $value): string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return strlen($value) > 512 ? substr($value, 0, 512) : $value;
    }

    private function booleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function sanitizeText(string $value, int $maxLength): string
    {
        if (function_exists('sanitize_text_field')) {
            return sanitize_text_field($value, $maxLength);
        }

        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? $value;
        $value = trim((string) preg_replace('/\s+/', ' ', $value));

        return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength, 'UTF-8') : substr($value, 0, $maxLength);
    }

    private function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        $this->database->ensureReady();
        $foreignKeyName = $this->schemaIdentifier('fk_' . $this->table() . '_user');
        $this->database->pdo()->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `private_user_id` INT NOT NULL,
                    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
                    `smtp_host` VARCHAR(190) NOT NULL,
                    `smtp_port` INT UNSIGNED NOT NULL DEFAULT 587,
                    `smtp_user` VARCHAR(190) NULL,
                    `smtp_password_ciphertext` TEXT NULL,
                    `smtp_encryption` VARCHAR(16) NOT NULL DEFAULT \'tls\',
                    `from_address` VARCHAR(254) NOT NULL,
                    `from_name` VARCHAR(120) NOT NULL,
                    `reply_to` VARCHAR(254) NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uq_private_user_mail_settings_user` (`private_user_id`),
                    KEY `idx_private_user_mail_settings_updated` (`updated_at`),
                    CONSTRAINT `%s`
                        FOREIGN KEY (`private_user_id`)
                        REFERENCES `%s` (`id`)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->table(),
                $foreignKeyName,
                $this->database->table('private_users')
            )
        );

        $this->schemaReady = true;
    }

    private function schemaIdentifier(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_]+/', '_', $value) ?? $value;
        $value = trim($value, '_');

        return substr($value !== '' ? $value : 'fk_private_user_mail_settings_user', 0, 64);
    }
}
