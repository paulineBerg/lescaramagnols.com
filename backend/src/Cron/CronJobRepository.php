<?php

declare(strict_types=1);

namespace Caramagnols\Cron;

use Caramagnols\Database\EditorialDatabase;
use DateTimeImmutable;
use DateTimeInterface;
use PDO;

final class CronJobRepository
{
    private const HISTORY_LIMIT_PER_JOB = 100;

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function ensureDefaults(): void
    {
        $this->database->ensureReady();
        $now = $this->now();
        foreach ($this->defaultJobs() as $job) {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT IGNORE INTO `%s`
                     (`code`, `name`, `description`, `script_path`, `arguments_json`, `schedule_expression`,
                      `status`, `timeout_seconds`, `created_at`, `updated_at`)
                     VALUES
                     (:code, :name, :description, :script_path, :arguments_json, :schedule_expression,
                      :status, :timeout_seconds, :created_at, :updated_at)',
                    $this->database->table('cron_jobs')
                )
            );
            $statement->execute([
                'code' => $job['code'],
                'name' => $job['name'],
                'description' => $job['description'],
                'script_path' => $job['script_path'],
                'arguments_json' => $this->encodeJson($job['arguments']),
                'schedule_expression' => $job['schedule_expression'],
                'status' => 'active',
                'timeout_seconds' => $job['timeout_seconds'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    public function defaultJobCodes(): array
    {
        return array_map(static fn (array $job): string => $job['code'], $this->defaultJobs());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listJobs(bool $includeInactive = true): array
    {
        $this->database->ensureReady();
        $sql = sprintf(
            'SELECT * FROM `%s`%s ORDER BY `code` ASC',
            $this->database->table('cron_jobs'),
            $includeInactive ? '' : " WHERE `status` = 'active'"
        );
        $statement = $this->database->pdo()->query($sql);
        $rows = $statement === false ? [] : $statement->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(fn (array $row): array => $this->normalizeRow($row), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findJob(string $code): ?array
    {
        $this->database->ensureReady();
        $statement = $this->database->pdo()->prepare(
            sprintf('SELECT * FROM `%s` WHERE `code` = :code LIMIT 1', $this->database->table('cron_jobs'))
        );
        $statement->execute(['code' => $code]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /**
     * @param array<string, mixed> $job
     */
    public function saveJob(array $job): void
    {
        $this->database->ensureReady();
        $now = $this->now();
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                 (`code`, `name`, `description`, `script_path`, `arguments_json`, `schedule_expression`,
                  `status`, `timeout_seconds`, `next_run_at`, `created_at`, `updated_at`)
                 VALUES
                 (:code, :name, :description, :script_path, :arguments_json, :schedule_expression,
                  :status, :timeout_seconds, :next_run_at, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    `name` = VALUES(`name`),
                    `description` = VALUES(`description`),
                    `script_path` = VALUES(`script_path`),
                    `arguments_json` = VALUES(`arguments_json`),
                    `schedule_expression` = VALUES(`schedule_expression`),
                    `status` = VALUES(`status`),
                    `timeout_seconds` = VALUES(`timeout_seconds`),
                    `next_run_at` = VALUES(`next_run_at`),
                    `updated_at` = VALUES(`updated_at`)',
                $this->database->table('cron_jobs')
            )
        );

        $statement->execute([
            'code' => (string) $job['code'],
            'name' => (string) $job['name'],
            'description' => (string) ($job['description'] ?? ''),
            'script_path' => (string) $job['script_path'],
            'arguments_json' => $this->encodeJson($job['arguments'] ?? ['args' => []]),
            'schedule_expression' => (string) $job['schedule_expression'],
            'status' => (string) $job['status'],
            'timeout_seconds' => (int) $job['timeout_seconds'],
            'next_run_at' => is_string($job['next_run_at'] ?? null) ? $job['next_run_at'] : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function setJobStatus(string $code, string $status): bool
    {
        $this->database->ensureReady();
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'UPDATE `%s` SET `status` = :status, `updated_at` = :updated_at WHERE `code` = :code',
                $this->database->table('cron_jobs')
            )
        );
        $statement->execute([
            'code' => $code,
            'status' => $status,
            'updated_at' => $this->now(),
        ]);

        return $statement->rowCount() > 0;
    }

    public function deleteJob(string $code): bool
    {
        $this->database->ensureReady();
        $statement = $this->database->pdo()->prepare(
            sprintf('DELETE FROM `%s` WHERE `code` = :code', $this->database->table('cron_jobs'))
        );
        $statement->execute(['code' => $code]);

        return $statement->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $run
     */
    public function recordRun(array $run): void
    {
        $this->database->ensureReady();
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                 (`job_code`, `job_name`, `status`, `scheduled_at`, `started_at`, `finished_at`, `duration_ms`,
                  `exit_code`, `stdout_text`, `stderr_text`, `message`, `created_at`)
                 VALUES
                 (:job_code, :job_name, :status, :scheduled_at, :started_at, :finished_at, :duration_ms,
                  :exit_code, :stdout_text, :stderr_text, :message, :created_at)',
                $this->database->table('cron_runs')
            )
        );

        $statement->execute([
            'job_code' => (string) $run['job_code'],
            'job_name' => (string) $run['job_name'],
            'status' => (string) $run['status'],
            'scheduled_at' => $run['scheduled_at'] ?? null,
            'started_at' => (string) $run['started_at'],
            'finished_at' => $run['finished_at'] ?? null,
            'duration_ms' => $run['duration_ms'] ?? null,
            'exit_code' => $run['exit_code'] ?? null,
            'stdout_text' => $this->truncate((string) ($run['stdout_text'] ?? ''), 20000),
            'stderr_text' => $this->truncate((string) ($run['stderr_text'] ?? ''), 20000),
            'message' => $this->truncate((string) ($run['message'] ?? ''), 2000),
            'created_at' => $this->now(),
        ]);

        $this->cleanupRunHistory((string) $run['job_code']);
    }

    public function updateJobExecution(
        string $code,
        string $status,
        ?int $exitCode,
        ?int $durationMs,
        ?DateTimeInterface $lastRunAt,
        ?DateTimeInterface $nextRunAt
    ): void {
        $this->database->ensureReady();
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `last_run_at` = :last_run_at,
                     `last_status` = :last_status,
                     `last_exit_code` = :last_exit_code,
                     `last_duration_ms` = :last_duration_ms,
                     `next_run_at` = :next_run_at,
                     `updated_at` = :updated_at
                 WHERE `code` = :code',
                $this->database->table('cron_jobs')
            )
        );
        $statement->execute([
            'code' => $code,
            'last_run_at' => $lastRunAt?->format('Y-m-d H:i:s'),
            'last_status' => $status,
            'last_exit_code' => $exitCode,
            'last_duration_ms' => $durationMs,
            'next_run_at' => $nextRunAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->now(),
        ]);
    }

    public function updateJobNextRun(string $code, ?DateTimeInterface $nextRunAt): void
    {
        $this->database->ensureReady();
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'UPDATE `%s` SET `next_run_at` = :next_run_at, `updated_at` = :updated_at WHERE `code` = :code',
                $this->database->table('cron_jobs')
            )
        );
        $statement->execute([
            'code' => $code,
            'next_run_at' => $nextRunAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->now(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentRuns(int $limit = 30): array
    {
        $this->database->ensureReady();
        $statement = $this->database->pdo()->query(
            sprintf(
                'SELECT * FROM `%s` ORDER BY `started_at` DESC, `id` DESC LIMIT %d',
                $this->database->table('cron_runs'),
                max(1, min(200, $limit))
            )
        );
        $rows = $statement === false ? [] : $statement->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static fn (array $row): array => $row, $rows);
    }

    /**
     * @return array<string, mixed>
     */
    public function schedulerState(): array
    {
        $this->database->ensureReady();
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'SELECT `value_json`, `updated_at` FROM `%s` WHERE `state_key` = :state_key',
                $this->database->table('cron_scheduler_state')
            )
        );
        $statement->execute(['state_key' => 'main']);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return [];
        }

        $decoded = $this->decodeJson(is_string($row['value_json'] ?? null) ? $row['value_json'] : null);
        $decoded['updated_at'] = (string) ($row['updated_at'] ?? '');

        return $decoded;
    }

    /**
     * @param array<string, mixed> $state
     */
    public function saveSchedulerState(array $state): void
    {
        $this->database->ensureReady();
        $now = $this->now();
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s` (`state_key`, `value_json`, `updated_at`)
                 VALUES (:state_key, :value_json, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    `value_json` = VALUES(`value_json`),
                    `updated_at` = VALUES(`updated_at`)',
                $this->database->table('cron_scheduler_state')
            )
        );
        $statement->execute([
            'state_key' => 'main',
            'value_json' => $this->encodeJson($state),
            'updated_at' => $now,
        ]);
    }

    /**
     * @return array<int, array{code: string, name: string, description: string, script_path: string, arguments: array{args: array<int, string>}, schedule_expression: string, timeout_seconds: int}>
     */
    private function defaultJobs(): array
    {
        return [
            [
                'code' => 'publish_scheduled_blog_articles',
                'name' => 'Publication articles planifiés',
                'description' => 'Bascule les articles blog scheduled arrivés à échéance en published.',
                'script_path' => 'core/tools/publish_scheduled_blog_articles.php',
                'arguments' => ['args' => []],
                'schedule_expression' => '*/5 * * * *',
                'timeout_seconds' => 300,
            ],
            [
                'code' => 'backup_production',
                'name' => 'Backup production',
                'description' => 'Archive le backend production et crée un dump SQL compressé.',
                'script_path' => 'core/tools/backup_production.php',
                'arguments' => ['args' => ['--quiet']],
                'schedule_expression' => '25 2 * * *',
                'timeout_seconds' => 1800,
            ],
            [
                'code' => 'check_log_alerts',
                'name' => 'Alertes logs',
                'description' => 'Analyse les logs applicatifs et notifie les seuils configurés.',
                'script_path' => 'core/tools/check_log_alerts.php',
                'arguments' => ['args' => ['--strict']],
                'schedule_expression' => '*/15 * * * *',
                'timeout_seconds' => 300,
            ],
            [
                'code' => 'purge_sql_logs',
                'name' => 'Purge logs SQL',
                'description' => 'Supprime les entrées SQL anciennes des journaux admin selon la rétention configurée.',
                'script_path' => 'core/tools/purge_sql_logs.php',
                'arguments' => ['args' => ['--days=90', '--keep-sensitive-days=365']],
                'schedule_expression' => '40 3 * * *',
                'timeout_seconds' => 300,
            ],
            [
                'code' => 'purge_private_discussions',
                'name' => 'Purge discussions privées',
                'description' => 'Purge les messages et fichiers FamilyDiscussion arrivés au terme de la rétention 60 jours.',
                'script_path' => 'core/tools/purge_private_discussions.php',
                'arguments' => ['args' => ['--quiet']],
                'schedule_expression' => '45 3 * * *',
                'timeout_seconds' => 300,
            ],
            [
                'code' => 'purge_web_development_previews',
                'name' => 'Purge previews WebDevelopment',
                'description' => 'Nettoie les tickets et sessions de prévisualisation WebDevelopment expirés, révoqués ou consommés.',
                'script_path' => 'core/tools/purge_web_development_previews.php',
                'arguments' => ['args' => ['--quiet']],
                'schedule_expression' => '50 3 * * *',
                'timeout_seconds' => 300,
            ],
            [
                'code' => 'purge_private_account_deletion_backups',
                'name' => 'Suppressions comptes privés',
                'description' => 'Avertit à J+20 puis supprime les données, le compte et la sauvegarde après 30 jours de rétention.',
                'script_path' => 'core/tools/purge_private_account_deletion_backups.php',
                'arguments' => ['args' => ['--quiet']],
                'schedule_expression' => '55 3 * * *',
                'timeout_seconds' => 300,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $arguments = $this->decodeJson(is_string($row['arguments_json'] ?? null) ? $row['arguments_json'] : null);
        $args = is_array($arguments['args'] ?? null) ? $arguments['args'] : [];
        $arguments['args'] = array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $args),
            static fn (string $value): bool => $value !== ''
        ));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'code' => (string) ($row['code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'script_path' => (string) ($row['script_path'] ?? ''),
            'arguments' => $arguments,
            'arguments_json' => $this->encodeJson($arguments, true),
            'schedule_expression' => (string) ($row['schedule_expression'] ?? ''),
            'status' => (string) ($row['status'] ?? 'inactive'),
            'timeout_seconds' => (int) ($row['timeout_seconds'] ?? 300),
            'last_run_at' => $row['last_run_at'] !== null ? (string) $row['last_run_at'] : '',
            'last_status' => $row['last_status'] !== null ? (string) $row['last_status'] : '',
            'last_exit_code' => $row['last_exit_code'] !== null ? (int) $row['last_exit_code'] : null,
            'last_duration_ms' => $row['last_duration_ms'] !== null ? (int) $row['last_duration_ms'] : null,
            'next_run_at' => $row['next_run_at'] !== null ? (string) $row['next_run_at'] : '',
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodeJson(array $payload, bool $pretty = false): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $encoded = json_encode($payload, $flags);

        return is_string($encoded) ? $encoded : '{}';
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(?string $json): array
    {
        if (!is_string($json) || trim($json) === '') {
            return ['args' => []];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : ['args' => []];
    }

    private function cleanupRunHistory(string $jobCode): void
    {
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'DELETE FROM `%1$s`
                 WHERE `job_code` = :job_code
                   AND `id` NOT IN (
                     SELECT `id` FROM (
                       SELECT `id`
                       FROM `%1$s`
                       WHERE `job_code` = :inner_job_code
                       ORDER BY `started_at` DESC, `id` DESC
                       LIMIT %2$d
                     ) AS keep_runs
                   )',
                $this->database->table('cron_runs'),
                self::HISTORY_LIMIT_PER_JOB
            )
        );
        $statement->execute([
            'job_code' => $jobCode,
            'inner_job_code' => $jobCode,
        ]);
    }

    private function truncate(string $value, int $maxLength): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
    }

    private function now(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
