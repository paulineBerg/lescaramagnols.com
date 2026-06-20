<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Caramagnols\Cron\CronJobRepository;
use Caramagnols\Cron\CronJobRunner;
use Caramagnols\Cron\CronScheduler;
use Caramagnols\Cron\CronCenterExitCode;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit être exécutée en CLI.\n");
    exit(1);
}

$options = parse_cli_options(array_slice($argv, 1));
$dryRun = isset($options['dry-run']);
$jsonOutput = isset($options['json']);
$quiet = isset($options['quiet']);
$failOnJobError = isset($options['strict']) || isset($options['fail-on-job-error']);
$jobCode = isset($options['job']) && is_string($options['job'])
    ? trim($options['job'])
    : null;
$now = null;

if (isset($options['now']) && is_string($options['now'])) {
    try {
        $now = new DateTimeImmutable($options['now']);
    } catch (Throwable $exception) {
        write_cron_center_error('Date --now invalide: ' . $exception->getMessage(), $jsonOutput);
        exit(2);
    }
}

try {
    $phpBinary = trim((string) env('PHP_CLI_BINARY', PHP_BINARY));
    if ($phpBinary === '') {
        $phpBinary = 'php';
    }

    $repository = new CronJobRepository(editorial_database());
    $runner = new CronJobRunner(ROOT_PATH, $phpBinary, ROOT_PATH . '/var/locks');
    $scheduler = new CronScheduler(
        $repository,
        $runner,
        app_event_logger(),
        ROOT_PATH . '/var/locks/cron-center.lock'
    );

    $result = $scheduler->run($now, $dryRun, $jobCode !== '' ? $jobCode : null);

    if ($jsonOutput) {
        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        exit(CronCenterExitCode::forResult($result, $failOnJobError));
    }

    if (!$quiet) {
        render_cron_center_result($result);
    }

    exit(CronCenterExitCode::forResult($result, $failOnJobError));
} catch (Throwable $exception) {
    write_cron_center_error($exception->getMessage(), $jsonOutput);
    exit(1);
}

/**
 * @param array<int, string> $arguments
 * @return array<string, string|true>
 */
function parse_cli_options(array $arguments): array
{
    $options = [];

    foreach ($arguments as $argument) {
        if (!is_string($argument) || !str_starts_with($argument, '--')) {
            continue;
        }

        $parts = explode('=', substr($argument, 2), 2);
        if (!isset($parts[1])) {
            $options[$parts[0]] = true;
            continue;
        }

        $options[$parts[0]] = $parts[1];
    }

    return $options;
}

/**
 * @param array<string, mixed> $result
 */
function render_cron_center_result(array $result): void
{
    if (($result['locked'] ?? false) === true) {
        fwrite(STDOUT, "Cron Center: coordination déjà en cours.\n");
        return;
    }

    fwrite(STDOUT, sprintf(
        "Cron Center%s\n",
        ($result['dry_run'] ?? false) === true ? ' (dry-run)' : ''
    ));
    fwrite(STDOUT, sprintf("- jobs vérifiés: %d\n", (int) ($result['jobs_checked'] ?? 0)));
    fwrite(STDOUT, sprintf("- jobs dus: %d\n", (int) ($result['jobs_due'] ?? 0)));
    fwrite(STDOUT, sprintf("- jobs exécutés: %d\n", (int) ($result['jobs_executed'] ?? 0)));

    $runs = is_array($result['runs'] ?? null) ? $result['runs'] : [];
    foreach ($runs as $run) {
        if (!is_array($run)) {
            continue;
        }

        fwrite(
            STDOUT,
            sprintf(
                "- %s: %s (%s)\n",
                (string) ($run['job_code'] ?? ''),
                (string) ($run['status'] ?? ''),
                (string) ($run['message'] ?? '')
            )
        );
    }
}

function write_cron_center_error(string $message, bool $jsonOutput): void
{
    if ($jsonOutput) {
        fwrite(STDOUT, json_encode([
            'success' => false,
            'error' => $message,
            'generated_at' => date('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        return;
    }

    fwrite(STDERR, "[ERROR] " . $message . PHP_EOL);
}
