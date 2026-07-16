<?php

declare(strict_types=1);

namespace Caramagnols\Cron;

use DateTimeImmutable;
use RuntimeException;

final class CronJobRunner
{
    public function __construct(
        private readonly string $rootPath,
        private readonly string $phpBinary,
        private readonly string $lockDirectory
    ) {
    }

    /**
     * @param array<string, mixed> $job
     * @param DateTimeImmutable|null $scheduledAt
     * @return array<string, mixed>
     */
    public function run(array $job, ?DateTimeImmutable $scheduledAt = null, bool $dryRun = false): array
    {
        $code = (string) ($job['code'] ?? '');
        $name = (string) ($job['name'] ?? $code);
        $scriptPath = $this->normalizeScriptPath((string) ($job['script_path'] ?? ''));
        $args = $this->argumentsFromJob($job);
        $timeoutSeconds = max(5, min(3600, (int) ($job['timeout_seconds'] ?? 300)));
        $startedAt = new DateTimeImmutable();

        if ($dryRun) {
            return [
                'job_code' => $code,
                'job_name' => $name,
                'status' => 'dry_run',
                'scheduled_at' => $scheduledAt?->format('Y-m-d H:i:s'),
                'started_at' => $startedAt->format('Y-m-d H:i:s'),
                'finished_at' => $startedAt->format('Y-m-d H:i:s'),
                'duration_ms' => 0,
                'exit_code' => 0,
                'stdout_text' => '',
                'stderr_text' => '',
                'message' => 'Dry-run: job non exécuté.',
                'command' => array_merge([$this->phpBinary, $scriptPath], $args),
            ];
        }

        if (!is_dir($this->lockDirectory) && !mkdir($this->lockDirectory, 0775, true) && !is_dir($this->lockDirectory)) {
            throw new RuntimeException(sprintf('Impossible de créer le dossier de verrous cron: %s', $this->lockDirectory));
        }

        $lockPath = $this->lockDirectory . '/cron-job-' . $code . '.lock';
        $lock = fopen($lockPath, 'c');
        if (!is_resource($lock)) {
            throw new RuntimeException(sprintf('Impossible d’ouvrir le verrou du job cron: %s', $code));
        }

        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            return [
                'job_code' => $code,
                'job_name' => $name,
                'status' => 'skipped',
                'scheduled_at' => $scheduledAt?->format('Y-m-d H:i:s'),
                'started_at' => $startedAt->format('Y-m-d H:i:s'),
                'finished_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'duration_ms' => 0,
                'exit_code' => null,
                'stdout_text' => '',
                'stderr_text' => '',
                'message' => 'Job déjà en cours.',
            ];
        }

        try {
            return $this->runProcess($code, $name, $scriptPath, $args, $timeoutSeconds, $startedAt, $scheduledAt);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @param array<int, string> $args
     * @return array<string, mixed>
     */
    private function runProcess(
        string $code,
        string $name,
        string $scriptPath,
        array $args,
        int $timeoutSeconds,
        DateTimeImmutable $startedAt,
        ?DateTimeImmutable $scheduledAt
    ): array {
        $command = array_merge([$this->phpBinary, $scriptPath], $args);
        $exitCodePath = $this->lockDirectory . '/cron-exit-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', $code)
            . '-' . bin2hex(random_bytes(6)) . '.status';
        $processCommand = $this->commandWithExitCapture($command, $exitCodePath);
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($processCommand, $descriptors, $pipes, $this->rootPath);
        if (!is_resource($process)) {
            @unlink($exitCodePath);
            throw new RuntimeException(sprintf('Impossible de lancer le job cron: %s', $code));
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $timedOut = false;
        $exitCode = null;
        $startedMicrotime = microtime(true);

        while (true) {
            $stdout .= $this->readPipe($pipes[1]);
            $stderr .= $this->readPipe($pipes[2]);

            if (feof($pipes[1]) && feof($pipes[2])) {
                break;
            }

            if ((microtime(true) - $startedMicrotime) >= $timeoutSeconds) {
                $timedOut = true;
                $this->terminateProcess($process);
                break;
            }

            usleep(100000);
        }

        $stdout .= $this->readPipe($pipes[1]);
        $stderr .= $this->readPipe($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $closedExitCode = proc_close($process);
        if ($exitCode === null) {
            $exitCode = $this->capturedExitCode($exitCodePath);
        }
        if ($exitCode === null && $closedExitCode >= 0) {
            $exitCode = $closedExitCode;
        }
        @unlink($exitCodePath);
        $finishedAt = new DateTimeImmutable();
        $durationMs = (int) round((microtime(true) - $startedMicrotime) * 1000);

        $status = 'success';
        $message = 'Job terminé.';
        if ($timedOut) {
            $status = 'timeout';
            $message = sprintf('Timeout après %d seconde(s).', $timeoutSeconds);
            $exitCode = null;
        } elseif ($exitCode === null) {
            $status = 'failed';
            $message = 'Code retour indisponible.';
        } elseif ($exitCode !== 0) {
            $status = 'failed';
            $message = sprintf('Code retour %d.', $exitCode);
        }

        return [
            'job_code' => $code,
            'job_name' => $name,
            'status' => $status,
            'scheduled_at' => $scheduledAt?->format('Y-m-d H:i:s'),
            'started_at' => $startedAt->format('Y-m-d H:i:s'),
            'finished_at' => $finishedAt->format('Y-m-d H:i:s'),
            'duration_ms' => $durationMs,
            'exit_code' => $exitCode,
            'stdout_text' => $this->truncate($stdout, 20000),
            'stderr_text' => $this->truncate($stderr, 20000),
            'message' => $message,
            'command' => $command,
        ];
    }

    /**
     * @param array<int, string> $command
     * @return array<int, string>
     */
    private function commandWithExitCapture(array $command, string $exitCodePath): array
    {
        $shellCommand = implode(' ', array_map('escapeshellarg', $command))
            . '; __caramagnols_status=$?; '
            . 'printf "%s" "$__caramagnols_status" > ' . escapeshellarg($exitCodePath)
            . '; exit "$__caramagnols_status"';

        return ['/bin/sh', '-c', $shellCommand];
    }

    private function capturedExitCode(string $exitCodePath): ?int
    {
        if (!is_file($exitCodePath)) {
            return null;
        }

        $content = trim((string) file_get_contents($exitCodePath));

        return preg_match('/^\d+$/', $content) === 1 ? (int) $content : null;
    }

    /**
     * @param resource $process
     */
    private function terminateProcess($process): void
    {
        proc_terminate($process);
        usleep(200000);

        $status = proc_get_status($process);
        if (is_array($status) && $status['running'] === true) {
            proc_terminate($process, 9);
        }
    }

    private function normalizeScriptPath(string $scriptPath): string
    {
        $scriptPath = str_replace('\\', '/', trim($scriptPath));
        $scriptPath = ltrim($scriptPath, '/');
        if (str_starts_with($scriptPath, $this->rootPath . '/')) {
            $scriptPath = substr($scriptPath, strlen($this->rootPath) + 1);
        }

        if (
            !str_starts_with($scriptPath, 'core/tools/')
            || !str_ends_with($scriptPath, '.php')
            || str_contains($scriptPath, '..')
        ) {
            throw new RuntimeException('Chemin de script cron refusé.');
        }

        if (!CronScriptPolicy::isAllowed($this->rootPath, $scriptPath)) {
            throw new RuntimeException(sprintf('Script cron non autorisé: %s', $scriptPath));
        }

        $absolute = $this->rootPath . '/' . $scriptPath;
        if (!is_file($absolute) || !is_readable($absolute)) {
            throw new RuntimeException(sprintf('Script cron introuvable: %s', $scriptPath));
        }

        return $absolute;
    }

    /**
     * @param array<string, mixed> $job
     * @return array<int, string>
     */
    private function argumentsFromJob(array $job): array
    {
        $arguments = is_array($job['arguments'] ?? null) ? $job['arguments'] : [];
        $args = is_array($arguments['args'] ?? null) ? $arguments['args'] : [];

        return array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $args),
            static fn (string $value): bool => $value !== ''
        ));
    }

    /**
     * @param resource $pipe
     */
    private function readPipe($pipe): string
    {
        $output = '';
        while (!feof($pipe)) {
            $chunk = fread($pipe, 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }

            $output .= $chunk;
        }

        return $output;
    }

    private function truncate(string $value, int $maxLength): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
    }
}
