<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Documents;

final class PrivateDocumentScanner
{
    private const MAX_OUTPUT_BYTES = 4096;

    public function __construct(
        private readonly string $command,
        private readonly int $timeoutSeconds = 30
    ) {
    }

    public function configured(): bool
    {
        return trim($this->command) !== '';
    }

    public function scan(string $path, string $originalName, string $mimeType): PrivateDocumentScanResult
    {
        if (!$this->configured()) {
            return PrivateDocumentScanResult::cleanNoScanner();
        }

        if (!is_file($path) || !is_readable($path)) {
            return PrivateDocumentScanResult::unavailable(null, 0, 'file_unreadable');
        }

        $arguments = $this->buildArguments($path, $originalName, $mimeType);
        if ($arguments === []) {
            return PrivateDocumentScanResult::unavailable(null, 0, 'invalid_scan_command');
        }

        $startedAt = microtime(true);
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = @proc_open($arguments, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            return PrivateDocumentScanResult::unavailable(null, 0, 'scanner_start_failed');
        }

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                stream_set_blocking($pipe, false);
            }
        }

        $stdout = '';
        $stderr = '';
        $exitCode = null;
        $timedOut = false;
        $deadline = $startedAt + max(1, min(120, $this->timeoutSeconds));

        while (true) {
            $stdout = $this->appendPipeOutput($pipes[1] ?? null, $stdout);
            $stderr = $this->appendPipeOutput($pipes[2] ?? null, $stderr);

            $status = proc_get_status($process);
            if (!($status['running'] ?? false)) {
                $rawExitCode = $status['exitcode'] ?? null;
                $exitCode = is_int($rawExitCode) && $rawExitCode >= 0 ? $rawExitCode : null;
                break;
            }

            if (microtime(true) >= $deadline) {
                $timedOut = true;
                @proc_terminate($process);
                usleep(100000);
                break;
            }

            usleep(10000);
        }

        $stdout = $this->appendPipeOutput($pipes[1] ?? null, $stdout);
        $stderr = $this->appendPipeOutput($pipes[2] ?? null, $stderr);

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $closedExitCode = proc_close($process);
        if ($exitCode === null && is_int($closedExitCode) && $closedExitCode >= 0) {
            $exitCode = $closedExitCode;
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        if ($timedOut) {
            return PrivateDocumentScanResult::unavailable(null, $durationMs, 'scan_timeout');
        }

        $error = $this->sanitizeScannerOutput($stderr !== '' ? $stderr : $stdout, $path, $originalName);
        if ($exitCode === 0) {
            return PrivateDocumentScanResult::clean(0, $durationMs);
        }

        if ($exitCode === 1) {
            return PrivateDocumentScanResult::infected(1, $durationMs, $error);
        }

        return PrivateDocumentScanResult::unavailable($exitCode, $durationMs, $error);
    }

    /**
     * @return array<int, string>
     */
    private function buildArguments(string $path, string $originalName, string $mimeType): array
    {
        $tokens = $this->tokenizeCommand($this->command);
        if ($tokens === []) {
            return [];
        }

        $hasFilePlaceholder = false;
        $arguments = [];
        foreach ($tokens as $token) {
            if (str_contains($token, '{file}')) {
                $hasFilePlaceholder = true;
            }

            $arguments[] = strtr($token, [
                '{file}' => $path,
                '{name}' => $originalName,
                '{mime}' => $mimeType,
            ]);
        }

        if (!$hasFilePlaceholder) {
            $arguments[] = $path;
        }

        return array_values(array_filter($arguments, static fn (string $argument): bool => $argument !== ''));
    }

    /**
     * @return array<int, string>
     */
    private function tokenizeCommand(string $command): array
    {
        $command = trim($command);
        if ($command === '') {
            return [];
        }

        $tokens = [];
        $current = '';
        $quote = null;
        $escaped = false;
        $length = strlen($command);

        for ($i = 0; $i < $length; ++$i) {
            $char = $command[$i];
            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                    continue;
                }

                $current .= $char;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if (ctype_space($char)) {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }
                continue;
            }

            $current .= $char;
        }

        if ($escaped || $quote !== null) {
            return [];
        }

        if ($current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }

    /**
     * @param resource|null $pipe
     */
    private function appendPipeOutput(mixed $pipe, string $current): string
    {
        if (!is_resource($pipe)) {
            return $current;
        }

        $chunk = stream_get_contents($pipe);
        if (!is_string($chunk) || $chunk === '') {
            return $current;
        }

        return substr($current . $chunk, -self::MAX_OUTPUT_BYTES);
    }

    private function sanitizeScannerOutput(string $output, string $path, string $originalName): string
    {
        $normalized = str_replace([$path, $originalName], ['[file]', '[name]'], $output);
        $normalized = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $normalized);
        $normalized = is_string($normalized) ? trim($normalized) : '';

        return substr($normalized, 0, 255);
    }
}
