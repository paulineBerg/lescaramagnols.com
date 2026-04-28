<?php

declare(strict_types=1);

namespace Caramagnols\Support;

final class PhpCliBinary
{
    public static function detect(
        mixed $configured = null,
        mixed $environment = null,
        ?string $phpBinary = PHP_BINARY,
        ?string $phpSapi = PHP_SAPI
    ): string {
        $candidates = [
            $configured,
            $environment,
            $phpSapi === 'cli' ? $phpBinary : null,
            '/usr/local/php8.3/bin/php',
            '/usr/local/php8.2/bin/php',
            '/usr/local/php8.1/bin/php',
            '/usr/bin/php',
            'php',
        ];

        foreach ($candidates as $candidate) {
            $normalized = self::normalize($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return 'php';
    }

    public static function normalize(mixed $binary): ?string
    {
        $binary = trim(str_replace('\\', '/', (string) $binary));
        if ($binary === '') {
            return null;
        }

        if (preg_match('/[\x00-\x20\x7F]/', $binary) === 1 || strlen($binary) > 240) {
            return null;
        }

        if (preg_match('#^[A-Za-z0-9._/-]+$#', $binary) !== 1) {
            return null;
        }

        $basename = strtolower(basename($binary));
        if (str_contains($basename, 'php-fpm')) {
            return null;
        }

        return $binary;
    }
}
