<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests;

use Caramagnols\Admin\AdminCronCenterService;
use Caramagnols\Cron\CronJobRepository;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\Logging\LoggerFactory;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

final class AdminCronCenterServiceTest extends TestCase
{
    use EditorialSqlTestTrait;

    private string $logDir;

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__) . '/core/bootstrap.php';
    }

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/caramagnols-admin-cron-logs-' . bin2hex(random_bytes(6));
        mkdir($this->logDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
        $this->removeDirectoryRecursively($this->logDir);
    }

    public function testManualTestRunsJobAndStoresHistory(): void
    {
        $repository = new CronJobRepository($this->editorialSqlDatabase());
        $repository->saveJob([
            'code' => 'manual_check',
            'name' => 'Manual check',
            'description' => 'Safe manual test fixture.',
            'script_path' => 'core/tools/check_vite_assets.php',
            'arguments' => ['args' => ['--help']],
            'schedule_expression' => '* * * * *',
            'status' => 'active',
            'timeout_seconds' => 30,
        ]);

        $service = new AdminCronCenterService(
            $repository,
            new AppEventLogger(new LoggerFactory($this->logDir, 'test'))
        );

        $result = $service->handle([
            'settings_action' => 'cron_test',
            'cron_job_code' => 'manual_check',
        ], 'admin@example.com');

        $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
        $this->assertStringContainsString('Test manuel exécuté', (string) ($result['message'] ?? ''));

        $job = $repository->findJob('manual_check');
        $this->assertIsArray($job);
        $this->assertSame('success', $job['last_status'] ?? null);
        $this->assertSame(0, $job['last_exit_code'] ?? null);

        $runs = $repository->recentRuns(5);
        $this->assertSame('manual_check', $runs[0]['job_code'] ?? null);
        $this->assertSame('success', $runs[0]['status'] ?? null);
        $this->assertStringContainsString('Usage:', (string) ($runs[0]['stdout_text'] ?? ''));
    }

    public function testDefaultJobsIncludePrivateCronActions(): void
    {
        $repository = new CronJobRepository($this->editorialSqlDatabase());
        $repository->ensureDefaults();

        $discussionPurge = $repository->findJob('purge_private_discussions');
        $this->assertIsArray($discussionPurge);
        $this->assertSame('core/tools/purge_private_discussions.php', $discussionPurge['script_path'] ?? null);
        $this->assertSame('45 3 * * *', $discussionPurge['schedule_expression'] ?? null);

        $accountDeletion = $repository->findJob('purge_private_account_deletion_backups');
        $this->assertIsArray($accountDeletion);
        $this->assertSame('core/tools/purge_private_account_deletion_backups.php', $accountDeletion['script_path'] ?? null);
        $this->assertSame('55 3 * * *', $accountDeletion['schedule_expression'] ?? null);
    }

    public function testManualTestReportsFailingCronJobForAlerting(): void
    {
        $repository = new CronJobRepository($this->editorialSqlDatabase());
        $repository->saveJob([
            'code' => 'failing_private_probe',
            'name' => 'Failing private probe',
            'description' => 'Safe failure fixture for cron alerting.',
            'script_path' => 'core/tools/purge_private_account_deletion_backups.php',
            'arguments' => ['args' => ['--unknown-v3']],
            'schedule_expression' => '* * * * *',
            'status' => 'active',
            'timeout_seconds' => 30,
        ]);

        $service = new AdminCronCenterService(
            $repository,
            new AppEventLogger(new LoggerFactory($this->logDir, 'test'))
        );

        $result = $service->handle([
            'settings_action' => 'cron_test',
            'cron_job_code' => 'failing_private_probe',
        ], 'admin@example.com');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Test manuel terminé en erreur', (string) ($result['error'] ?? ''));

        $runs = $repository->recentRuns(5);
        $this->assertSame('failing_private_probe', $runs[0]['job_code'] ?? null);
        $this->assertSame('failed', $runs[0]['status'] ?? null);
        $this->assertSame(1, (int) ($runs[0]['exit_code'] ?? 0));

        $contentLog = (string) file_get_contents($this->logDir . '/content.log');
        $this->assertStringContainsString('cron.job.failed', $contentLog);
        $this->assertStringContainsString('cron.scheduler.failed', $contentLog);
    }

    private function removeDirectoryRecursively(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (glob(rtrim($directory, '/') . '/*') ?: [] as $path) {
            if (is_dir($path)) {
                $this->removeDirectoryRecursively($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
