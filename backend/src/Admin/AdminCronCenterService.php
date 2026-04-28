<?php

declare(strict_types=1);

namespace Caramagnols\Admin;

use Caramagnols\Cron\CronExpression;
use Caramagnols\Cron\CronJobRepository;
use Caramagnols\Cron\CronJobRunner;
use Caramagnols\Cron\CronScheduler;
use Caramagnols\Cron\CronScriptPolicy;
use Caramagnols\Logging\AppEventLogger;
use DateTimeImmutable;

final class AdminCronCenterService
{
    public function __construct(
        private readonly CronJobRepository $repository,
        private readonly AppEventLogger $logger,
        private readonly string $rootPath = ROOT_PATH
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function viewModel(): array
    {
        try {
            $this->repository->ensureDefaults();
            $jobs = array_map(fn (array $job): array => $this->jobView($job), $this->repository->listJobs(true));
            $state = $this->repository->schedulerState();
            $recentRuns = $this->repository->recentRuns(12);

            return [
                'available' => true,
                'error' => null,
                'scheduler' => $this->schedulerView($state),
                'jobs' => $jobs,
                'recentRuns' => $recentRuns,
                'allowedScripts' => $this->allowedScripts(),
                'phpBinary' => $this->detectedPhpBinary(),
                'runnerPath' => $this->runnerPath(),
                'ovhCronCommand' => $this->ovhCronCommand(),
                'logsUrl' => function_exists('admin_url') ? admin_url('logs') . '?q=cron.' : '',
                'emptyJobForm' => [
                    'code' => '',
                    'name' => '',
                    'script_path' => 'core/tools/publish_scheduled_blog_articles.php',
                    'status' => 'active',
                    'schedule_expression' => '*/5 * * * *',
                    'description' => '',
                    'arguments_json' => "{\n  \"args\": []\n}",
                    'timeout_seconds' => 300,
                ],
            ];
        } catch (\Throwable $exception) {
            return [
                'available' => false,
                'error' => $exception->getMessage(),
                'scheduler' => [],
                'jobs' => [],
                'recentRuns' => [],
                'allowedScripts' => [],
                'phpBinary' => $this->detectedPhpBinary(),
                'runnerPath' => $this->runnerPath(),
                'ovhCronCommand' => $this->ovhCronCommand(),
                'logsUrl' => function_exists('admin_url') ? admin_url('logs') . '?q=cron.' : '',
                'emptyJobForm' => [],
            ];
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: string|null, error: string|null, view: array<string, mixed>}
     */
    public function handle(array $payload, ?string $actorIdentifier = null): array
    {
        $action = trim((string) ($payload['settings_action'] ?? ''));

        try {
            if ($action === 'cron_create' || $action === 'cron_save') {
                $jobPayload = is_array($payload['cron_job'] ?? null) ? $payload['cron_job'] : [];
                $normalized = $this->normalizeJobPayload($jobPayload);
                if ($normalized['error'] !== null) {
                    return $this->result(false, null, $normalized['error']);
                }

                $this->repository->ensureDefaults();
                $this->repository->saveJob($normalized['data']);
                $this->logger->security('admin.cron.job_saved', [
                    'actor' => AppEventLogger::maskIdentifier($actorIdentifier),
                    'job_code' => (string) $normalized['data']['code'],
                    'script_path' => (string) $normalized['data']['script_path'],
                    'schedule_expression' => (string) $normalized['data']['schedule_expression'],
                ]);

                return $this->result(true, 'Job cron enregistré.', null);
            }

            if ($action === 'cron_test') {
                $code = $this->normalizeCode((string) ($payload['cron_job_code'] ?? ''));
                if ($code === '') {
                    return $this->result(false, null, 'Code job invalide.');
                }

                $this->repository->ensureDefaults();
                $job = $this->repository->findJob($code);
                if ($job === null) {
                    return $this->result(false, null, 'Job cron introuvable.');
                }

                $this->logger->security('admin.cron.job_manual_test_requested', [
                    'actor' => AppEventLogger::maskIdentifier($actorIdentifier),
                    'job_code' => $code,
                    'script_path' => (string) ($job['script_path'] ?? ''),
                ]);

                return $this->manualTestResult(
                    $code,
                    $this->manualScheduler()->run(new DateTimeImmutable(), false, $code),
                    $actorIdentifier
                );
            }

            if ($action === 'cron_toggle') {
                $code = $this->normalizeCode((string) ($payload['cron_job_code'] ?? ''));
                $status = strtolower(trim((string) ($payload['cron_job_status'] ?? 'inactive')));
                $status = $status === 'active' ? 'active' : 'inactive';
                if ($code === '') {
                    return $this->result(false, null, 'Code job invalide.');
                }

                $this->repository->setJobStatus($code, $status);
                $this->logger->security('admin.cron.job_status_changed', [
                    'actor' => AppEventLogger::maskIdentifier($actorIdentifier),
                    'job_code' => $code,
                    'status' => $status,
                ]);

                return $this->result(true, $status === 'active' ? 'Job cron activé.' : 'Job cron désactivé.', null);
            }

            if ($action === 'cron_delete') {
                $code = $this->normalizeCode((string) ($payload['cron_job_code'] ?? ''));
                if ($code === '') {
                    return $this->result(false, null, 'Code job invalide.');
                }

                if (in_array($code, $this->repository->defaultJobCodes(), true)) {
                    $this->repository->setJobStatus($code, 'inactive');
                    $this->logger->security('admin.cron.default_job_disabled', [
                        'actor' => AppEventLogger::maskIdentifier($actorIdentifier),
                        'job_code' => $code,
                    ]);

                    return $this->result(true, 'Job cron par défaut désactivé.', null);
                }

                $deleted = $this->repository->deleteJob($code);
                if (!$deleted) {
                    return $this->result(false, null, 'Job cron introuvable.');
                }

                $this->logger->security('admin.cron.job_deleted', [
                    'actor' => AppEventLogger::maskIdentifier($actorIdentifier),
                    'job_code' => $code,
                ]);

                return $this->result(true, 'Job cron supprimé.', null);
            }
        } catch (\Throwable $exception) {
            $this->logger->security('admin.cron.action_failed', [
                'actor' => AppEventLogger::maskIdentifier($actorIdentifier),
                'action' => $action,
                'error' => $exception->getMessage(),
            ], 'error');

            return $this->result(false, null, 'Action Cron Center impossible: ' . $exception->getMessage());
        }

        return $this->result(false, null, 'Action Cron Center inconnue.');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{data: array<string, mixed>, error: string|null}
     */
    private function normalizeJobPayload(array $payload): array
    {
        $code = $this->normalizeCode((string) ($payload['code'] ?? ''));
        if ($code === '') {
            return ['data' => [], 'error' => 'Le code job doit contenir 2 à 64 caractères en minuscules, chiffres, tirets ou underscores.'];
        }

        $name = $this->trimText((string) ($payload['name'] ?? ''), 191);
        if ($name === '') {
            return ['data' => [], 'error' => 'Le nom du job est obligatoire.'];
        }

        $scriptPath = $this->normalizeScriptPath((string) ($payload['script_path'] ?? ''));
        if ($scriptPath === null) {
            return ['data' => [], 'error' => 'Le script doit être un fichier PHP autorisé dans backend/core/tools/.'];
        }

        $schedule = preg_replace('/\s+/', ' ', trim((string) ($payload['schedule_expression'] ?? ''))) ?? '';
        if (!CronExpression::isValid($schedule)) {
            return ['data' => [], 'error' => 'Expression cron invalide. Format attendu: minute heure jour mois jour_semaine.'];
        }

        $arguments = $this->normalizeArgumentsJson((string) ($payload['arguments_json'] ?? '{}'));
        if ($arguments['error'] !== null) {
            return ['data' => [], 'error' => $arguments['error']];
        }

        $timeout = filter_var((string) ($payload['timeout_seconds'] ?? '300'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 5, 'max_range' => 3600],
        ]);
        if ($timeout === false) {
            return ['data' => [], 'error' => 'Le timeout doit être compris entre 5 et 3600 secondes.'];
        }

        $status = strtolower(trim((string) ($payload['status'] ?? 'active')));
        $status = $status === 'inactive' ? 'inactive' : 'active';
        $nextRun = CronExpression::parse($schedule)->nextRunAfter(new DateTimeImmutable());

        return [
            'data' => [
                'code' => $code,
                'name' => $name,
                'description' => $this->trimText((string) ($payload['description'] ?? ''), 1000),
                'script_path' => $scriptPath,
                'arguments' => $arguments['data'],
                'schedule_expression' => $schedule,
                'status' => $status,
                'timeout_seconds' => (int) $timeout,
                'next_run_at' => $nextRun?->format('Y-m-d H:i:s'),
            ],
            'error' => null,
        ];
    }

    private function normalizeCode(string $code): string
    {
        $code = strtolower(trim($code));

        return preg_match('/^[a-z0-9][a-z0-9_-]{1,63}$/', $code) === 1 ? $code : '';
    }

    private function normalizeScriptPath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        if (str_starts_with($path, $this->rootPath . '/')) {
            $path = substr($path, strlen($this->rootPath) + 1);
        }
        $path = ltrim($path, '/');

        if (!str_starts_with($path, 'core/tools/') || !str_ends_with($path, '.php') || str_contains($path, '..')) {
            return null;
        }

        if (!is_file($this->rootPath . '/' . $path)) {
            return null;
        }

        return CronScriptPolicy::isAllowed($this->rootPath, $path) ? $path : null;
    }

    /**
     * @return array{data: array{args: array<int, string>}, error: string|null}
     */
    private function normalizeArgumentsJson(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return ['data' => ['args' => []], 'error' => null];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded) || array_is_list($decoded)) {
            return ['data' => ['args' => []], 'error' => 'Les arguments doivent être un objet JSON, par exemple {"args":["--quiet"]}.'];
        }

        $args = is_array($decoded['args'] ?? null) ? $decoded['args'] : [];
        $normalizedArgs = [];
        foreach ($args as $arg) {
            $value = trim((string) $arg);
            if ($value === '') {
                continue;
            }

            if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1 || strlen($value) > 160) {
                return ['data' => ['args' => []], 'error' => 'Un argument contient des caractères invalides ou dépasse 160 caractères.'];
            }

            $normalizedArgs[] = $value;
        }

        return ['data' => ['args' => $normalizedArgs], 'error' => null];
    }

    /**
     * @param array<string, mixed> $job
     * @return array<string, mixed>
     */
    private function jobView(array $job): array
    {
        $schedule = (string) ($job['schedule_expression'] ?? '');
        try {
            $expression = CronExpression::parse($schedule);
            $nextRun = $expression->nextRunAfter(new DateTimeImmutable());
            $summary = $expression->humanSummary();
            $validSchedule = true;
        } catch (\Throwable) {
            $nextRun = null;
            $summary = 'Expression invalide';
            $validSchedule = false;
        }

        $job['schedule_summary'] = $summary;
        $job['schedule_valid'] = $validSchedule;
        $job['next_run_display'] = $nextRun?->format('Y-m-d H:i:s') ?? '';
        $job['last_run_display'] = trim((string) ($job['last_run_at'] ?? ''));
        $job['status_label'] = ((string) ($job['status'] ?? 'inactive')) === 'active' ? 'Actif' : 'Inactif';
        $job['is_default'] = in_array((string) ($job['code'] ?? ''), $this->repository->defaultJobCodes(), true);

        return $job;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function schedulerView(array $state): array
    {
        return [
            'status' => (string) ($state['status'] ?? 'jamais lancé'),
            'startedAt' => (string) ($state['started_at'] ?? ''),
            'finishedAt' => (string) ($state['finished_at'] ?? ''),
            'jobsChecked' => (int) ($state['jobs_checked'] ?? 0),
            'jobsDue' => (int) ($state['jobs_due'] ?? 0),
            'jobsExecuted' => (int) ($state['jobs_executed'] ?? 0),
            'updatedAt' => (string) ($state['updated_at'] ?? ''),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedScripts(): array
    {
        return CronScriptPolicy::allowedScripts($this->rootPath);
    }

    private function detectedPhpBinary(): string
    {
        $binary = trim((string) env('PHP_CLI_BINARY', PHP_BINARY));

        return $binary !== '' ? $binary : 'php';
    }

    private function runnerPath(): string
    {
        return $this->rootPath . '/core/tools/run_cron_center.php';
    }

    private function ovhCronCommand(): string
    {
        return sprintf(
            '* * * * * %s %s --quiet >/dev/null 2>&1',
            escapeshellarg($this->detectedPhpBinary()),
            escapeshellarg($this->runnerPath())
        );
    }

    private function manualScheduler(): CronScheduler
    {
        return new CronScheduler(
            $this->repository,
            new CronJobRunner($this->rootPath, $this->detectedPhpBinary(), $this->rootPath . '/var/locks'),
            $this->logger,
            $this->rootPath . '/var/locks/cron-center.lock'
        );
    }

    /**
     * @param array<string, mixed> $schedulerResult
     * @return array{success: bool, message: string|null, error: string|null, view: array<string, mixed>}
     */
    private function manualTestResult(string $code, array $schedulerResult, ?string $actorIdentifier): array
    {
        if (($schedulerResult['locked'] ?? false) === true) {
            $this->logger->security('admin.cron.job_manual_test_locked', [
                'actor' => AppEventLogger::maskIdentifier($actorIdentifier),
                'job_code' => $code,
            ], 'warning');

            return $this->result(false, null, 'Coordination Cron Center déjà en cours.');
        }

        $runs = is_array($schedulerResult['runs'] ?? null) ? $schedulerResult['runs'] : [];
        $run = is_array($runs[0] ?? null) ? $runs[0] : null;
        if ($run === null) {
            $this->logger->security('admin.cron.job_manual_test_empty', [
                'actor' => AppEventLogger::maskIdentifier($actorIdentifier),
                'job_code' => $code,
            ], 'warning');

            return $this->result(false, null, 'Aucune exécution déclenchée pour ce job.');
        }

        $status = trim((string) ($run['status'] ?? 'failed'));
        $message = trim((string) ($run['message'] ?? ''));
        $level = $status === 'success' ? 'info' : 'warning';
        $this->logger->security('admin.cron.job_manual_test_completed', [
            'actor' => AppEventLogger::maskIdentifier($actorIdentifier),
            'job_code' => $code,
            'status' => $status,
            'exit_code' => $run['exit_code'] ?? null,
            'duration_ms' => $run['duration_ms'] ?? null,
            'message' => $message,
        ], $level);

        if ($status === 'success') {
            return $this->result(true, 'Test manuel exécuté pour le job cron ' . $code . '.', null);
        }

        if ($status === 'skipped') {
            return $this->result(false, null, 'Test manuel non lancé: ' . ($message !== '' ? $message : 'job déjà en cours.'));
        }

        return $this->result(
            false,
            null,
            'Test manuel terminé en erreur pour le job cron ' . $code . ': ' . ($message !== '' ? $message : $status)
        );
    }

    /**
     * @return array{success: bool, message: string|null, error: string|null, view: array<string, mixed>}
     */
    private function result(bool $success, ?string $message, ?string $error): array
    {
        return [
            'success' => $success,
            'message' => $message,
            'error' => $error,
            'view' => $this->viewModel(),
        ];
    }

    private function trimText(string $value, int $maxLength): string
    {
        $value = trim($value);

        return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
    }
}
