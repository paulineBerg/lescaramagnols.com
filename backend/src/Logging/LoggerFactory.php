<?php

declare(strict_types=1);

namespace Caramagnols\Logging;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

class LoggerFactory
{
    private const DEFAULT_ROTATION_MAX_BYTES = 5_242_880; // 5 MiB
    private const DEFAULT_RETENTION_FILES = 14;

    public function __construct(
        private readonly string $logDir,
        private readonly string $env = 'development',
        private readonly ?SqlLogStore $sqlLogStore = null
    ) {
    }

    public function create(string $channel = 'app'): Logger
    {
        $logger = new Logger($channel);

        $level = $this->env === 'production' ? Level::Info : Level::Debug;
        $path = rtrim($this->logDir, '/');
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $logFile = $path . '/' . $channel . '.log';
        $this->rotateFileIfNeeded($logFile, $this->rotationMaxBytes(), $this->retentionFiles());

        $logger->pushHandler(new StreamHandler($logFile, $level));

        if ($this->sqlLogStore instanceof SqlLogStore) {
            $logger->pushHandler(new SqlLogHandler($this->sqlLogStore, $level));
        }

        return $logger;
    }

    private function rotationMaxBytes(): int
    {
        $configured = function_exists('app_config')
            ? (int) app_config('logging.rotation_max_bytes', self::DEFAULT_ROTATION_MAX_BYTES)
            : self::DEFAULT_ROTATION_MAX_BYTES;

        return max(262_144, min(104_857_600, $configured));
    }

    private function retentionFiles(): int
    {
        $configured = function_exists('app_config')
            ? (int) app_config('logging.retention_files', self::DEFAULT_RETENTION_FILES)
            : self::DEFAULT_RETENTION_FILES;

        return max(2, min(90, $configured));
    }

    private function rotateFileIfNeeded(string $logFile, int $maxBytes, int $retentionFiles): void
    {
        try {
            if (!is_file($logFile)) {
                return;
            }

            $currentSize = @filesize($logFile);
            if (!is_int($currentSize) || $currentSize < $maxBytes) {
                return;
            }

            $oldestFile = $logFile . '.' . $retentionFiles;
            if (is_file($oldestFile)) {
                @unlink($oldestFile);
            }

            for ($index = $retentionFiles - 1; $index >= 1; $index--) {
                $source = $logFile . '.' . $index;
                $target = $logFile . '.' . ($index + 1);

                if (is_file($source)) {
                    @rename($source, $target);
                }
            }

            @rename($logFile, $logFile . '.1');
        } catch (\Throwable) {
            // La rotation ne doit jamais bloquer l'application.
        }
    }
}
