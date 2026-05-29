<?php

declare(strict_types=1);

namespace Caramagnols\Cron;

use Caramagnols\Logging\AppEventLogger;
use DateTimeImmutable;

final class CronScheduler
{
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
    public function run(?DateTimeImmutable $now = null, bool $dryRun = false, ?string $onlyJobCode = null): array
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
                'started_at' => $now->format('Y-m-d H:i:s'),
                'jobs_checked' => 0,
                'jobs_due' => 0,
                'jobs_executed' => 0,
                'runs' => [],
            ];
        }

        try {
            return $this->runWithLock($now, $dryRun, $onlyJobCode);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function runWithLock(DateTimeImmutable $now, bool $dryRun, ?string $onlyJobCode): array
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
                if (in_array($status, ['failed', 'timeout'], true)) {
                    ++$failed;
                }

                $level = in_array($status, ['success', 'dry_run'], true) ? 'info' : 'warning';
                $this->logger->content('cron.job.' . (string) ($run['status'] ?? 'completed'), [
                    'job_code' => $code,
                    'job_name' => (string) ($job['name'] ?? ''),
                    'status' => (string) ($run['status'] ?? ''),
                    'exit_code' => $run['exit_code'] ?? null,
                    'duration_ms' => $run['duration_ms'] ?? null,
                    'message' => (string) ($run['message'] ?? ''),
                ], $level);
            } catch (\Throwable $exception) {
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
                    'message' => $exception->getMessage(),
                ];
                $runs[] = $run;
                ++$failed;
                if (!$dryRun && $code !== '') {
                    $this->repository->recordRun($run);
                    $this->repository->updateJobExecution($code, 'failed', null, 0, $now, null);
                }

                $this->logger->content('cron.job.failed', [
                    'job_code' => $code,
                    'job_name' => (string) ($job['name'] ?? ''),
                    'error' => $exception->getMessage(),
                ], 'error');
            }
        }

        $finishedAt = new DateTimeImmutable();
        $durationMs = (int) round((microtime(true) - $startedMicrotime) * 1000);
        $result = [
            'success' => true,
            'locked' => false,
            'dry_run' => $dryRun,
            'started_at' => $startedAt->format('Y-m-d H:i:s'),
            'finished_at' => $finishedAt->format('Y-m-d H:i:s'),
            'duration_ms' => $durationMs,
            'jobs_checked' => $checked,
            'jobs_due' => $due,
            'jobs_executed' => $executed,
            'jobs_failed' => $failed,
            'runs' => $runs,
        ];

        if (!$dryRun) {
            $this->repository->saveSchedulerState([
                'status' => $failed > 0 ? 'failed' : 'idle',
                'started_at' => $startedAt->format('Y-m-d H:i:s'),
                'finished_at' => $finishedAt->format('Y-m-d H:i:s'),
                'duration_ms' => $durationMs,
                'jobs_checked' => $checked,
                'jobs_due' => $due,
                'jobs_executed' => $executed,
                'jobs_failed' => $failed,
                'last_error' => $failed > 0 ? sprintf('%d job(s) cron en échec.', $failed) : null,
            ]);
        }

        $this->logger->content('cron.scheduler.completed', [
            'dry_run' => $dryRun,
            'duration_ms' => $durationMs,
            'jobs_checked' => $checked,
            'jobs_due' => $due,
            'jobs_executed' => $executed,
            'jobs_failed' => $failed,
        ]);
        if ($failed > 0) {
            $this->logger->content('cron.scheduler.failed', [
                'dry_run' => $dryRun,
                'duration_ms' => $durationMs,
                'jobs_checked' => $checked,
                'jobs_due' => $due,
                'jobs_executed' => $executed,
                'jobs_failed' => $failed,
            ], 'error');
        }

        return $result;
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
