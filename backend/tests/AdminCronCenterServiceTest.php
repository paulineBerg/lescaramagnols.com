<?php

declare(strict_types=1);

use Caramagnols\Admin\AdminCronCenterService;
use Caramagnols\Cron\CronJobRepository;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\Logging\LoggerFactory;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class AdminCronCenterServiceTest extends TestCase
{
    use EditorialSqlTestTrait;

    private string $logDir;

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
