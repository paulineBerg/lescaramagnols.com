<?php

declare(strict_types=1);

namespace Caramagnols\Logging;

final class LogSanitizer
{
    private const MAX_DEPTH = 5;
    private const MAX_ITEMS = 80;
    private const MAX_STRING_LENGTH = 500;

    /** @var array<int, string> */
    private const SENSITIVE_KEY_PARTS = [
        'password',
        'passwd',
        'mot_de_passe',
        'motdepasse',
        'secret',
        'token',
        'access_token',
        'refresh_token',
        'authorization',
        'auth',
        'cookie',
        'session',
        'csrf',
        'totp',
        'otp',
        'mfa',
        'api_key',
        'apikey',
        'private_key',
        'database_url',
        'dsn',
        'iban',
        'card_number',
        'carte_bancaire',
        'numero_carte',
        'hash',
    ];

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function sanitizeContext(array $context): array
    {
        $sanitized = [];
        $count = 0;

        foreach ($context as $key => $value) {
            if ($count >= self::MAX_ITEMS) {
                $sanitized['_truncated'] = true;
                break;
            }

            $normalizedKey = $this->sanitizeKey((string) $key);
            if ($normalizedKey === '') {
                continue;
            }

            $sanitized[$normalizedKey] = $this->sanitizeValue($value, $normalizedKey, 0);
            $count++;
        }

        return $sanitized;
    }

    public function sanitizeText(string $value, int $maxLength = self::MAX_STRING_LENGTH): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', ' ', $value) ?? '';
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $value = trim($value);

        return function_exists('mb_substr')
            ? mb_substr($value, 0, $maxLength)
            : substr($value, 0, $maxLength);
    }

    private function sanitizeValue(mixed $value, string $key, int $depth): mixed
    {
        if ($this->isSensitiveKey($key)) {
            return '[redacted]';
        }

        if ($depth >= self::MAX_DEPTH) {
            return '[truncated]';
        }

        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $this->sanitizeText($value);
        }

        if (is_array($value)) {
            $sanitized = [];
            $count = 0;

            foreach ($value as $itemKey => $itemValue) {
                if ($count >= self::MAX_ITEMS) {
                    $sanitized['_truncated'] = true;
                    break;
                }

                $normalizedKey = $this->sanitizeKey((string) $itemKey);
                if ($normalizedKey === '') {
                    continue;
                }

                $sanitized[$normalizedKey] = $this->sanitizeValue($itemValue, $normalizedKey, $depth + 1);
                $count++;
            }

            return $sanitized;
        }

        if ($value instanceof \Stringable) {
            return $this->sanitizeText((string) $value);
        }

        if (is_object($value)) {
            return $value::class;
        }

        return gettype($value);
    }

    private function sanitizeKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9_.-]+/', '_', $key) ?? '';
        $key = trim($key, '_.-');

        return $this->sanitizeText($key, 64);
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower(trim($key));
        if ($key === '') {
            return false;
        }

        foreach (self::SENSITIVE_KEY_PARTS as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
