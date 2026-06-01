<?php

declare(strict_types=1);

namespace Caramagnols\Admin;

final class AdminRecoveryService
{
    public const DEFAULT_KEY_COUNT = 10;

    private const KEY_PREFIX = 'CAR-REC';
    private const KEY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private readonly string $adminOverridePath)
    {
    }

    /**
     * @return array<int, array{label: string, key: string}>
     */
    public function generatePlainKeys(int $count = self::DEFAULT_KEY_COUNT): array
    {
        $count = max(1, min(50, $count));
        $keys = [];
        $seen = [];

        while (count($keys) < $count) {
            $key = self::KEY_PREFIX . '-' . $this->groupCharacters($this->randomRecoveryToken());
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $keys[] = [
                'label' => 'recovery-' . str_pad((string) (count($keys) + 1), 2, '0', STR_PAD_LEFT),
                'key' => $key,
            ];
        }

        return $keys;
    }

    /**
     * @param array<int, array{label?: string, key?: string}|string> $plainKeys
     */
    public function installPlainKeys(array $plainKeys): int
    {
        $normalizedKeys = $this->normalizePlainKeys($plainKeys);
        if ($normalizedKeys === []) {
            throw new \InvalidArgumentException('Aucune cle de recuperation valide.');
        }

        $override = $this->readOverride();
        $now = date('c');
        $override['recovery_keys'] = array_map(
            static fn (array $entry): array => [
                'label' => $entry['label'],
                'hash' => password_hash($entry['key'], PASSWORD_DEFAULT),
                'created_at' => $now,
                'used_at' => null,
            ],
            $normalizedKeys
        );

        $this->writeOverride($override);

        return count($normalizedKeys);
    }

    public function hasUsableRecoveryKey(): bool
    {
        foreach ($this->recoveryKeyEntries($this->readOverride()) as $entry) {
            if (!$this->entryIsUsed($entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{success: bool, error: string}
     */
    public function recover(string $identifier, string $recoveryKey, string $newPassword): array
    {
        $override = $this->readOverride();
        $expectedIdentifier = $this->expectedIdentifier($override);
        if ($expectedIdentifier === '' || !hash_equals($expectedIdentifier, $this->normalizeIdentifier($identifier))) {
            return ['success' => false, 'error' => 'invalid_credentials'];
        }

        if (strlen($newPassword) < 12) {
            return ['success' => false, 'error' => 'weak_password'];
        }

        $normalizedKey = $this->normalizeSubmittedKey($recoveryKey);
        if ($normalizedKey === '') {
            return ['success' => false, 'error' => 'invalid_credentials'];
        }

        $entries = $this->recoveryKeyEntries($override);
        foreach ($entries as $index => $entry) {
            if ($this->entryIsUsed($entry)) {
                continue;
            }

            $hash = is_string($entry['hash'] ?? null) ? (string) $entry['hash'] : '';
            if ($hash === '' || !password_verify($normalizedKey, $hash)) {
                continue;
            }

            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            if (!is_string($passwordHash) || $passwordHash === '') {
                return ['success' => false, 'error' => 'write_failed'];
            }

            $entries[$index]['used_at'] = date('c');
            $override['password_hash'] = $passwordHash;
            $override['totp_enabled'] = false;
            $override['recovery_keys'] = $entries;

            try {
                $this->writeOverride($override);
            } catch (\RuntimeException) {
                return ['success' => false, 'error' => 'write_failed'];
            }

            return ['success' => true, 'error' => ''];
        }

        return ['success' => false, 'error' => 'invalid_credentials'];
    }

    /**
     * @param array<int, array{label?: string, key?: string}|string> $plainKeys
     * @return array<int, array{label: string, key: string}>
     */
    private function normalizePlainKeys(array $plainKeys): array
    {
        $normalized = [];
        $seen = [];

        foreach ($plainKeys as $index => $entry) {
            $label = 'recovery-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $key = '';
            if (is_array($entry)) {
                $label = is_string($entry['label'] ?? null) && trim((string) $entry['label']) !== ''
                    ? trim((string) $entry['label'])
                    : $label;
                $key = is_string($entry['key'] ?? null) ? (string) $entry['key'] : '';
            } elseif (is_string($entry)) {
                $key = $entry;
            }

            $key = $this->normalizeSubmittedKey($key);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = [
                'label' => preg_replace('/[^A-Za-z0-9_.-]+/', '-', $label) ?: 'recovery',
                'key' => $key,
            ];
        }

        return $normalized;
    }

    private function normalizeSubmittedKey(string $key): string
    {
        $key = strtoupper(trim($key));
        if (preg_match('/CAR-REC(?:-[A-Z2-9]{4}){8}/', $key, $match) === 1) {
            return $match[0];
        }

        $compact = preg_replace('/[^A-Z2-9]+/', '', $key) ?? '';
        if (str_starts_with($compact, 'CARREC')) {
            $body = substr($compact, 6);
            if (strlen($body) === 32) {
                return self::KEY_PREFIX . '-' . $this->groupCharacters($body);
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $override
     * @return array<int, array<string, mixed>>
     */
    private function recoveryKeyEntries(array $override): array
    {
        $entries = is_array($override['recovery_keys'] ?? null) ? $override['recovery_keys'] : [];

        return array_values(array_filter($entries, static fn (mixed $entry): bool => is_array($entry)));
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function entryIsUsed(array $entry): bool
    {
        return is_string($entry['used_at'] ?? null) && trim((string) $entry['used_at']) !== '';
    }

    /**
     * @param array<string, mixed> $override
     */
    private function expectedIdentifier(array $override): string
    {
        return $this->normalizeIdentifier((string) (
            $override['identifier']
            ?? $override['email']
            ?? (function_exists('admin_configured_identifier') ? admin_configured_identifier() : '')
        ));
    }

    private function normalizeIdentifier(string $identifier): string
    {
        return strtolower(trim($identifier));
    }

    /**
     * @return array<string, mixed>
     */
    private function readOverride(): array
    {
        if (!is_file($this->adminOverridePath)) {
            return [];
        }

        $data = require $this->adminOverridePath;

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeOverride(array $data): void
    {
        $directory = dirname($this->adminOverridePath);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Dossier de configuration admin inaccessible.');
        }

        $temporaryPath = $this->adminOverridePath . '.tmp.' . bin2hex(random_bytes(6));
        $content = "<?php\n\nreturn " . var_export($data, true) . ";\n";
        if (file_put_contents($temporaryPath, $content, LOCK_EX) === false) {
            throw new \RuntimeException('Ecriture de la configuration admin impossible.');
        }

        @chmod($temporaryPath, 0600);
        if (!rename($temporaryPath, $this->adminOverridePath)) {
            @unlink($temporaryPath);
            throw new \RuntimeException('Remplacement de la configuration admin impossible.');
        }

        @chmod($this->adminOverridePath, 0600);
    }

    private function randomRecoveryToken(): string
    {
        $alphabetLength = strlen(self::KEY_ALPHABET);
        $token = '';
        for ($i = 0; $i < 32; ++$i) {
            $token .= self::KEY_ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $token;
    }

    private function groupCharacters(string $value): string
    {
        return implode('-', str_split($value, 4));
    }
}
