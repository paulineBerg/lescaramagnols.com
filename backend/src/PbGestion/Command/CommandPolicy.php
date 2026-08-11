<?php

declare(strict_types=1);

namespace Caramagnols\PbGestion\Command;

final class CommandPolicy
{
    private const ALLOWED_COMMANDS = [
        'module.enable' => ['module'],
        'module.disable' => ['module'],
        'scan.start' => ['network_token', 'scan_mode'],
        'scan.stop' => ['network_token'],
        'monitoring.pause' => ['duration_seconds', 'reason'],
        'monitoring.resume' => [],
        'policy.apply' => ['policy_uid'],
        'details.prepare' => ['detail_uid', 'purpose'],
        'backup.start' => ['backup_kind'],
        'backup.verify' => ['backup_uid'],
        'update.check' => [],
    ];

    private const FORBIDDEN_COMMANDS = [
        'agent.revoke',
        'archive.prepare',
        'archive.upload',
        'shell.execute',
        'script.run',
        'url.fetch',
        'file.download',
        'process.start',
    ];

    private const FORBIDDEN_PAYLOAD_KEYS = [
        'url',
        'uri',
        'href',
        'script',
        'shell',
        'command',
        'cmd',
        'executable',
        'binary',
        'process',
        'download',
        'port',
        'ports',
        'host',
        'hostname',
        'ip',
        'mac',
        'path',
    ];

    /**
     * @return array<int, string>
     */
    public function allowedCommands(): array
    {
        return array_keys(self::ALLOWED_COMMANDS);
    }

    public function isAllowed(string $type): bool
    {
        return isset(self::ALLOWED_COMMANDS[$this->normalizeType($type)]);
    }

    public function isForbidden(string $type): bool
    {
        return in_array($this->normalizeType($type), self::FORBIDDEN_COMMANDS, true);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, error?: string}
     */
    public function validate(string $type, array $payload): array
    {
        $type = $this->normalizeType($type);
        if (!$this->isAllowed($type)) {
            return ['ok' => false, 'error' => 'unknown_command'];
        }

        $allowedKeys = self::ALLOWED_COMMANDS[$type];
        foreach ($payload as $key => $value) {
            $normalizedKey = strtolower(trim((string) $key));
            if (!in_array($normalizedKey, $allowedKeys, true)) {
                return ['ok' => false, 'error' => 'extra_field'];
            }

            if (is_array($value) && $this->containsForbiddenNestedKey($value)) {
                return ['ok' => false, 'error' => 'sensitive_field'];
            }
        }

        return match ($type) {
            'module.enable', 'module.disable' => $this->validateEnum($payload, 'module', [
                'network',
                'posture',
                'backup',
            ]),
            'scan.start' => $this->validateScanStart($payload),
            'scan.stop' => $this->validateToken($payload, 'network_token'),
            'monitoring.pause' => $this->validatePause($payload),
            'policy.apply' => $this->validateUid($payload, 'policy_uid'),
            'details.prepare' => $this->validateDetailPrepare($payload),
            'backup.start' => $this->validateEnum($payload, 'backup_kind', ['snapshot', 'external']),
            'backup.verify' => $this->validateUid($payload, 'backup_uid'),
            default => ['ok' => true],
        };
    }

    private function normalizeType(string $type): string
    {
        return strtolower(trim($type));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateToken(array $payload, string $key): array
    {
        $value = is_string($payload[$key] ?? null) ? trim((string) $payload[$key]) : '';
        if ($value === '' || preg_match('/\A[a-zA-Z0-9._:-]{12,96}\z/', $value) !== 1) {
            return ['ok' => false, 'error' => 'invalid_token'];
        }

        return ['ok' => true];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $allowed
     */
    private function validateEnum(array $payload, string $key, array $allowed): array
    {
        $value = is_string($payload[$key] ?? null) ? strtolower(trim((string) $payload[$key])) : '';

        return in_array($value, $allowed, true)
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'invalid_' . $key];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateUid(array $payload, string $key): array
    {
        $value = is_string($payload[$key] ?? null) ? strtolower(trim((string) $payload[$key])) : '';

        return preg_match('/\A[a-f0-9]{32}\z/', $value) === 1
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'invalid_uid'];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateScanStart(array $payload): array
    {
        $token = $this->validateToken($payload, 'network_token');
        if (($token['ok'] ?? false) !== true) {
            return $token;
        }

        $mode = is_string($payload['scan_mode'] ?? null) ? strtolower(trim((string) $payload['scan_mode'])) : 'passive';
        if (!in_array($mode, ['passive', 'active_limited'], true)) {
            return ['ok' => false, 'error' => 'invalid_scan_mode'];
        }

        return ['ok' => true];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validatePause(array $payload): array
    {
        $duration = is_numeric($payload['duration_seconds'] ?? null) ? (int) $payload['duration_seconds'] : 0;
        if ($duration < 60 || $duration > 86400) {
            return ['ok' => false, 'error' => 'invalid_duration'];
        }

        $reason = is_string($payload['reason'] ?? null) ? trim((string) $payload['reason']) : '';
        if ($reason !== '' && mb_strlen($reason) > 160) {
            return ['ok' => false, 'error' => 'invalid_reason'];
        }

        return ['ok' => true];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateDetailPrepare(array $payload): array
    {
        $uid = $this->validateUid($payload, 'detail_uid');
        if (($uid['ok'] ?? false) !== true) {
            return $uid;
        }

        $purpose = is_string($payload['purpose'] ?? null) ? strtolower(trim((string) $payload['purpose'])) : '';
        if (!in_array($purpose, ['support', 'security_review', 'recovery'], true)) {
            return ['ok' => false, 'error' => 'invalid_purpose'];
        }

        return ['ok' => true];
    }

    /**
     * @param array<string|int, mixed> $payload
     */
    private function containsForbiddenNestedKey(array $payload): bool
    {
        foreach ($payload as $key => $value) {
            if (in_array(strtolower(trim((string) $key)), self::FORBIDDEN_PAYLOAD_KEYS, true)) {
                return true;
            }

            if (is_array($value) && $this->containsForbiddenNestedKey($value)) {
                return true;
            }
        }

        return false;
    }
}
