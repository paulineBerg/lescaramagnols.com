<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents;

final class PrivateDocumentScanResult
{
    public const STATUS_PENDING_SCAN = 'pending_scan';
    public const STATUS_CLEAN = 'clean';
    public const STATUS_INFECTED = 'infected';
    public const STATUS_SCAN_UNAVAILABLE = 'scan_unavailable';

    /** @var array<int, string> */
    public const STATUSES = [
        self::STATUS_PENDING_SCAN,
        self::STATUS_CLEAN,
        self::STATUS_INFECTED,
        self::STATUS_SCAN_UNAVAILABLE,
    ];

    public function __construct(
        private readonly string $status,
        private readonly ?int $exitCode,
        private readonly int $durationMs,
        private readonly string $error,
        private readonly ?string $scannedAt
    ) {
    }

    public static function cleanNoScanner(): self
    {
        return new self(self::STATUS_CLEAN, null, 0, '', null);
    }

    public static function clean(int $exitCode, int $durationMs): self
    {
        return new self(self::STATUS_CLEAN, $exitCode, max(0, $durationMs), '', date('Y-m-d H:i:s'));
    }

    public static function infected(?int $exitCode, int $durationMs, string $error): self
    {
        return new self(
            self::STATUS_INFECTED,
            $exitCode,
            max(0, $durationMs),
            self::normalizeError($error !== '' ? $error : 'scanner_refused_file'),
            date('Y-m-d H:i:s')
        );
    }

    public static function unavailable(?int $exitCode, int $durationMs, string $error): self
    {
        return new self(
            self::STATUS_SCAN_UNAVAILABLE,
            $exitCode,
            max(0, $durationMs),
            self::normalizeError($error !== '' ? $error : 'scanner_unavailable'),
            date('Y-m-d H:i:s')
        );
    }

    public static function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return in_array($normalized, self::STATUSES, true) ? $normalized : self::STATUS_CLEAN;
    }

    public static function isDownloadable(string $status): bool
    {
        return self::normalizeStatus($status) === self::STATUS_CLEAN;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function exitCode(): ?int
    {
        return $this->exitCode;
    }

    public function durationMs(): int
    {
        return $this->durationMs;
    }

    public function error(): string
    {
        return $this->error;
    }

    public function scannedAt(): ?string
    {
        return $this->scannedAt;
    }

    private static function normalizeError(string $error): string
    {
        $normalized = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $error);
        $normalized = is_string($normalized) ? trim($normalized) : '';

        return substr($normalized, 0, 255);
    }
}
