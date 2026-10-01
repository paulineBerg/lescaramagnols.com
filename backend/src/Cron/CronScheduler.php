<?php

declare(strict_types=1);

namespace Caramagnols\Cron;

use Caramagnols\Logging\AppEventLogger;
use DateTimeImmutable;

final class CronScheduler
{
    /** @var array<int, string> */
    private const FAILURE_STATUSES = ['failed', 'timeout'];

    public function __construct(
        private readonly CronJobRepository $repository,
        private readonly CronJobRunner $runner,
        private readonly AppEventLogger $logger,
        private readonly string $lockPath
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(
        ?DateTimeImmutable $now = null,
        bool $dryRun = false,
        ?string $onlyJobCode = null,
        bool $strict = false
    ): array
    {
        $now ??= new DateTimeImmutable();

        if (!is_dir(dirname($this->lockPath))) {
            @mkdir(dirname($this->lockPath), 0775, true);
        }

        $lock = fopen($this->lockPath, 'c');
        if (!is_resource($lock)) {
            throw new \RuntimeException(sprintf('Impossible d’ouvrir le verrou Cron Center: %s', $this->lockPath));
        }

        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            $this->logger->content('cron.scheduler.locked', [
                'now' => $now->format('Y-m-d H:i:s'),
            ], 'warning');

            return [
                'success' => false,
                'locked' => true,
                'dry_run' => $dryRun,
                'strict' => $strict,
                'status' => 'locked',
                'started_at' => $now->format('Y-m-d H:i:s'),
                'jobs_checked' => 0,
                'jobs_due' => 0,
                'jobs_executed' => 0,
                'jobs_failed' => 0,
                'jobs_warning' => 0,
                'jobs_critical_failed' => 0,
                'warnings' => 0,
                'critical_failed' => 0,
                'runs' => [],
            ];
        }

        try {
            return $this->runWithLock($now, $dryRun, $onlyJobCode, $strict);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function runWithLock(DateTimeImmutable $now, bool $dryRun, ?string $onlyJobCode, bool $strict): array
    {
        $startedAt = new DateTimeImmutable();
        $startedMicrotime = microtime(true);
        if (!$dryRun) {
            $this->repository->ensureDefaults();
            $this->repository->saveSchedulerState([
                'status' => 'running',
                'started_at' => $startedAt->format('Y-m-d H:i:s'),
                'finished_at' => null,
                'last_error' => null,
            ]);
        }

        $this->logger->content('cron.scheduler.started', [
            'dry_run' => $dryRun,
            'strict' => $strict,
            'job' => $onlyJobCode,
            'now' => $now->format('Y-m-d H:i:s'),
        ]);

        $jobs = $onlyJobCode !== null && trim($onlyJobCode) !== ''
            ? array_values(array_filter(
                [$this->repository->findJob($onlyJobCode)],
                static fn (?array $job): bool => is_array($job)
            ))
            : $this->repository->listJobs(false);

        $runs = [];
        $checked = 0;
        $due = 0;
        $executed = 0;
        $failed = 0;
        $warnings = 0;
        $criticalFailed = 0;

        foreach ($jobs as $job) {
            $checked++;
            $code = (string) ($job['code'] ?? '');
            $schedule = (string) ($job['schedule_expression'] ?? '');

            try {
                $expression = CronExpression::parse($schedule);
                $scheduledAt = $onlyJobCode !== null ? $now : $expression->previousRunBeforeOrAt($now);
                $nextRun = $expression->nextRunAfter($now);
                if (!$dryRun) {
                    $this->repository->updateJobNextRun($code, $nextRun);
                }

                if ($scheduledAt === null || ($onlyJobCode === null && $this->alreadyRanForSchedule($job, $scheduledAt))) {
                    continue;
                }

                $due++;
                $this->logger->content('cron.job.started', [
                    'job_code' => $code,
                    'job_name' => (string) ($job['name'] ?? ''),
                    'script_path' => (string) ($job['script_path'] ?? ''),
                    'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
                    'dry_run' => $dryRun,
                ]);

                $run = $this->runner->run($job, $scheduledAt, $dryRun);
                $run = $this->annotateRun($job, $run, $strict);
                $runs[] = $run;

                if (!$dryRun) {
                    $this->repository->recordRun($run);
                    $this->repository->updateJobExecution(
                        $code,
                        (string) ($run['status'] ?? 'failed'),
                        is_int($run['exit_code'] ?? null) ? $run['exit_code'] : null,
                        is_int($run['duration_ms'] ?? null) ? $run['duration_ms'] : null,
                        new DateTimeImmutable((string) ($run['started_at'] ?? 'now')),
                        $nextRun
                    );
                }

                $executed++;
                $status = (string) ($run['status'] ?? '');
                if ($this->isFailureStatus($status)) {
                    ++$failed;
                    if (($run['failure_severity'] ?? 'critical') === 'warning') {
                        ++$warnings;
                    } else {
                        ++$criticalFailed;
                    }
                }

                $level = in_array($status, ['success', 'dry_run'], true)
                    ? 'info'
                    : (($run['failure_severity'] ?? 'critical') === 'warning' ? 'warning' : 'error');
                $this->logger->content('cron.job.' . (string) ($run['status'] ?? 'completed'), [
                    'job_code' => $code,
                    'job_name' => (string) ($job['name'] ?? ''),
                    'status' => (string) ($run['status'] ?? ''),
                    'severity' => (string) ($run['severity'] ?? 'critical'),
                    'failure_severity' => $run['failure_severity'] ?? null,
                    'exit_code' => $run['exit_code'] ?? null,
                    'duration_ms' => $run['duration_ms'] ?? null,
                    'message' => (string) ($run['message'] ?? ''),
                ], $level);
            } catch (\Throwable $exception) {
                $errorMessage = $this->sanitizeMessage($exception->getMessage());
                $run = [
                    'job_code' => $code,
                    'job_name' => (string) ($job['name'] ?? $code),
                    'status' => 'failed',
                    'scheduled_at' => null,
                    'started_at' => $now->format('Y-m-d H:i:s'),
                    'finished_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                    'duration_ms' => 0,
                    'exit_code' => null,
                    'stdout_text' => '',
                    'stderr_text' => '',
                    'message' => $errorMessage,
                ];
                $run = $this->annotateRun($job, $run, $strict);
                $runs[] = $run;
                ++$failed;
                if (($run['failure_severity'] ?? 'critical') === 'warning') {
                    ++$warnings;
                } else {
                    ++$criticalFailed;
                }
                if (!$dryRun && $code !== '') {
                    $this->repository->recordRun($run);
                    $this->repository->updateJobExecution($code, 'failed', null, 0, $now, null);
                }

                $level = ($run['failure_severity'] ?? 'critical') === 'warning' ? 'warning' : 'error';
                $this->logger->content('cron.job.failed', [
                    'job_code' => $code,
                    'job_name' => (string) ($job['name'] ?? ''),
                    'severity' => (string) ($run['severity'] ?? 'critical'),
                    'failure_severity' => $run['failure_severity'] ?? null,
                    'error' => $errorMessage,
                ], $level);
            }
        }

        $finishedAt = new DateTimeImmutable();
        $durationMs = (int) round((microtime(true) - $startedMicrotime) * 1000);
        $status = $criticalFailed > 0 ? 'failed' : ($warnings > 0 ? 'degraded' : 'success');
        $result = [
            'success' => true,
            'locked' => false,
            'dry_run' => $dryRun,
            'strict' => $strict,
            'status' => $status,
            'started_at' => $startedAt->format('Y-m-d H:i:s'),
            'finished_at' => $finishedAt->format('Y-m-d H:i:s'),
            'duration_ms' => $durationMs,
            'jobs_checked' => $checked,
            'jobs_due' => $due,
            'jobs_executed' => $executed,
            'jobs_failed' => $failed,
            'jobs_warning' => $warnings,
            'jobs_critical_failed' => $criticalFailed,
            'warnings' => $warnings,
            'critical_failed' => $criticalFailed,
            'runs' => $runs,
        ];

        if (!$dryRun) {
            $this->repository->saveSchedulerState([
                'status' => $criticalFailed > 0 ? 'failed' : ($warnings > 0 ? 'degraded' : 'idle'),
                'started_at' => $startedAt->format('Y-m-d H:i:s'),
                'finished_at' => $finishedAt->format('Y-m-d H:i:s'),
                'duration_ms' => $durationMs,
                'jobs_checked' => $checked,
                'jobs_due' => $due,
                'jobs_executed' => $executed,
                'jobs_failed' => $failed,
                'jobs_warning' => $warnings,
                'jobs_critical_failed' => $criticalFailed,
                'warnings' => $warnings,
                'critical_failed' => $criticalFailed,
                'last_warning' => $warnings > 0 ? sprintf('%d job(s) cron en avertissement.', $warnings) : null,
                'last_error' => $criticalFailed > 0 ? sprintf('%d job(s) cron critique(s) en échec.', $criticalFailed) : null,
            ]);
        }

        $this->logger->content('cron.scheduler.completed', [
            'dry_run' => $dryRun,
            'strict' => $strict,
            'status' => $status,
            'duration_ms' => $durationMs,
            'jobs_checked' => $checked,
            'jobs_due' => $due,
            'jobs_executed' => $executed,
            'jobs_failed' => $failed,
            'jobs_warning' => $warnings,
            'jobs_critical_failed' => $criticalFailed,
        ]);
        if ($criticalFailed > 0) {
            $this->logger->content('cron.scheduler.failed', [
                'dry_run' => $dryRun,
                'strict' => $strict,
                'duration_ms' => $durationMs,
                'jobs_checked' => $checked,
                'jobs_due' => $due,
                'jobs_executed' => $executed,
                'jobs_failed' => $failed,
                'jobs_warning' => $warnings,
                'jobs_critical_failed' => $criticalFailed,
            ], 'error');
        } elseif ($warnings > 0) {
            $this->logger->content('cron.scheduler.degraded', [
                'dry_run' => $dryRun,
                'strict' => $strict,
                'duration_ms' => $durationMs,
                'jobs_checked' => $checked,
                'jobs_due' => $due,
                'jobs_executed' => $executed,
                'jobs_warning' => $warnings,
            ], 'warning');
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $run
     * @return array<string, mixed>
     */
    private function annotateRun(array $job, array $run, bool $strict): array
    {
        $severity = $this->jobSeverity($job, $strict);
        $run['severity'] = $severity;

        $status = (string) ($run['status'] ?? '');
        if ($this->isFailureStatus($status)) {
            $run['failure_severity'] = $severity;
        }

        return $run;
    }

    /**
     * @param array<string, mixed> $job
     */
    private function jobSeverity(array $job, bool $strict): string
    {
        if ($strict) {
            return 'critical';
        }

        $code = (string) ($job['code'] ?? '');
        $scriptPath = str_replace('\\', '/', (string) ($job['script_path'] ?? ''));
        if (str_starts_with($code, 'document_hub_') || str_starts_with($scriptPath, 'core/tools/document_hub_')) {
            return 'warning';
        }

        return 'critical';
    }

    private function isFailureStatus(string $status): bool
    {
        return in_array($status, self::FAILURE_STATUSES, true);
    }

    private function sanitizeMessage(string $message): string
    {
        return (string) preg_replace('#/home/[A-Za-z0-9._-]+(?:/[^\s"\'<>]*)?#', '[path]', $message);
    }

    /**
     * @param array<string, mixed> $job
     */
    private function alreadyRanForSchedule(array $job, DateTimeImmutable $scheduledAt): bool
    {
        $lastRunAt = trim((string) ($job['last_run_at'] ?? ''));
        if ($lastRunAt === '') {
            return false;
        }

        $lastRun = strtotime($lastRunAt);
        if (!is_int($lastRun)) {
            return false;
        }

        return $lastRun >= $scheduledAt->getTimestamp();
    }
}
