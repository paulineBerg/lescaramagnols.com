<?php

declare(strict_types=1);

use Caramagnols\Cron\CronJobRepository;
use Caramagnols\Cron\CronJobRunner;
use Caramagnols\Cron\CronScheduler;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\Logging\LoggerFactory;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class CronSchedulerPolicyTest extends TestCase
{
    use EditorialSqlTestTrait;

    private string $tmpRoot;

    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/caramagnols-cron-scheduler-test-' . bin2hex(random_bytes(6));
        mkdir($this->tmpRoot . '/core/tools', 0777, true);
        mkdir($this->tmpRoot . '/locks', 0777, true);
        mkdir($this->tmpRoot . '/logs', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
        $this->removeDirectoryRecursively($this->tmpRoot);
    }

    public function testDocumentHubFailureDegradesNonStrictRun(): void
    {
        $repository = new CronJobRepository($this->editorialSqlDatabase());
        $this->createFailingScript('document_hub_integrity.php', 7);
        $this->saveCronJob($repository, 'document_hub_integrity_check', 'core/tools/document_hub_integrity.php');

        $result = $this->scheduler($repository)->run(
            new DateTimeImmutable('2026-10-01 09:45:00'),
            false,
            'document_hub_integrity_check'
        );

        $this->assertSame(true, $result['success']);
        $this->assertSame('degraded', $result['status']);
        $this->assertSame(1, $result['jobs_failed']);
        $this->assertSame(1, $result['warnings']);
        $this->assertSame(0, $result['critical_failed']);
        $this->assertSame('warning', $result['runs'][0]['failure_severity'] ?? null);
    }

    public function testDocumentHubFailureIsCriticalInStrictRun(): void
    {
        $repository = new CronJobRepository($this->editorialSqlDatabase());
        $this->createFailingScript('document_hub_integrity.php', 7);
        $this->saveCronJob($repository, 'document_hub_integrity_check', 'core/tools/document_hub_integrity.php');

        $result = $this->scheduler($repository)->run(
            new DateTimeImmutable('2026-10-01 09:45:00'),
            false,
            'document_hub_integrity_check',
            true
        );

        $this->assertSame(true, $result['success']);
        $this->assertSame('failed', $result['status']);
        $this->assertSame(1, $result['jobs_failed']);
        $this->assertSame(0, $result['warnings']);
        $this->assertSame(1, $result['critical_failed']);
        $this->assertSame('critical', $result['runs'][0]['failure_severity'] ?? null);
    }

    public function testNonDocumentHubFailureStaysCritical(): void
    {
        $repository = new CronJobRepository($this->editorialSqlDatabase());
        $this->createFailingScript('check_env.php', 9);
        $this->saveCronJob($repository, 'check_env_custom', 'core/tools/check_env.php');

        $result = $this->scheduler($repository)->run(
            new DateTimeImmutable('2026-10-01 09:45:00'),
            false,
            'check_env_custom'
        );

        $this->assertSame('failed', $result['status']);
        $this->assertSame(0, $result['warnings']);
        $this->assertSame(1, $result['critical_failed']);
        $this->assertSame('critical', $result['runs'][0]['failure_severity'] ?? null);
    }

    private function createFailingScript(string $basename, int $exitCode): void
    {
        file_put_contents(
            $this->tmpRoot . '/core/tools/' . $basename,
            "<?php\nfwrite(STDERR, \"simulated failure\\n\");\nexit({$exitCode});\n"
        );
    }

    private function saveCronJob(CronJobRepository $repository, string $code, string $scriptPath): void
    {
        $repository->saveJob([
            'code' => $code,
            'name' => $code,
            'description' => 'Test cron job',
            'script_path' => $scriptPath,
            'arguments' => ['args' => []],
            'schedule_expression' => '* * * * *',
            'status' => 'active',
            'timeout_seconds' => 5,
        ]);
    }

    private function scheduler(CronJobRepository $repository): CronScheduler
    {
        return new CronScheduler(
            $repository,
            new CronJobRunner($this->tmpRoot, PHP_BINARY, $this->tmpRoot . '/locks'),
            new AppEventLogger(new LoggerFactory($this->tmpRoot . '/logs', 'test')),
            $this->tmpRoot . '/locks/cron-center.lock'
        );
    }

    private function removeDirectoryRecursively(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectoryRecursively($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
