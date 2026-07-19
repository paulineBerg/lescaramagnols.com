<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Security;

use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;

final class PrivateMfaVerifier
{
    public function requiresMfa(?array $user): bool
    {
        if (!is_array($user)) {
            return false;
        }

        return (bool) app_config('private.mfa_totp_enabled', false)
            && $this->truthy($user['mfa_enabled'] ?? null)
            && $this->secret($user) !== '';
    }

    public function verify(?array $user, ?string $code, PrivateUserRepository $repository): bool
    {
        $code = trim((string) $code);
        if (!$this->requiresMfa($user) || $code === '') {
            return false;
        }

        if ($this->verifyTotp($this->secret($user), $code)) {
            return true;
        }

        $userId = is_numeric($user['id'] ?? null) ? (int) $user['id'] : 0;

        return (bool) app_config('private.mfa_backup_codes_enabled', true)
            && $userId > 0
            && $repository->consumeMfaBackupCode($userId, $code);
    }

    private function verifyTotp(string $secret, string $code): bool
    {
        if (!preg_match('/^[0-9]{6}$/', $code)) {
            return false;
        }

        $period = max(30, (int) app_config('private.mfa_totp_period_seconds', 30));
        $drift = max(0, min(3, (int) app_config('private.mfa_totp_allowed_drift_steps', 1)));
        $counter = intdiv(time(), $period);

        for ($offset = -$drift; $offset <= $drift; $offset++) {
            $expected = $this->totpCode($secret, $counter + $offset);
            if ($expected !== null && hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    private function totpCode(string $secret, int $counter): ?string
    {
        $secretBinary = $this->base32Decode($secret);
        if ($secretBinary === null) {
            return null;
        }

        $time = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $time, $secretBinary, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = (
            ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;

        return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $encoded): ?string
    {
        $encoded = strtoupper(preg_replace('/[\s\-=]+/', '', trim($encoded)) ?? '');
        if ($encoded === '' || preg_match('/^[A-Z2-7]+$/', $encoded) !== 1) {
            return null;
        }

        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($encoded) as $char) {
            $value = strpos($alphabet, $char);
            if ($value === false) {
                return null;
            }

            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) < 8) {
                continue;
            }

            $decoded .= chr(bindec($byte));
        }

        return $decoded !== '' ? $decoded : null;
    }

    private function secret(array $user): string
    {
        $secret = $user['mfa_secret_encrypted'] ?? '';

        return is_string($secret) ? trim($secret) : '';
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes'], true);
    }
}
