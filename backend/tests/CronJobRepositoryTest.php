<?php

declare(strict_types=1);

use Caramagnols\Cron\CronJobRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class CronJobRepositoryTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testEnsureDefaultsCreatesActiveJobs(): void
    {
        $repository = new CronJobRepository($this->editorialSqlDatabase());

        $repository->ensureDefaults();

        $jobs = $repository->listJobs();
        $codes = array_column($jobs, 'code');

        $this->assertContains('publish_scheduled_blog_articles', $codes);
        $this->assertContains('backup_production', $codes);
        $this->assertContains('check_log_alerts', $codes);
        $this->assertContains('purge_sql_logs', $codes);

        foreach ($jobs as $job) {
            $this->assertSame('active', $job['status']);
        }
    }

    public function testRunHistoryKeepsLastHundredEntriesPerJob(): void
    {
        $repository = new CronJobRepository($this->editorialSqlDatabase());
        $repository->ensureDefaults();
        $start = new DateTimeImmutable('2026-04-28 12:00:00');

        for ($index = 0; $index < 105; $index++) {
            $startedAt = $start->modify('+' . $index . ' minutes');
            $finishedAt = $startedAt->modify('+1 second');
            $repository->recordRun([
                'job_code' => 'publish_scheduled_blog_articles',
                'job_name' => 'Publication articles planifiés',
                'status' => 'success',
                'scheduled_at' => '2026-04-28 12:00:00',
                'started_at' => $startedAt->format('Y-m-d H:i:s'),
                'finished_at' => $finishedAt->format('Y-m-d H:i:s'),
                'duration_ms' => 25,
                'exit_code' => 0,
                'stdout_text' => '',
                'stderr_text' => '',
                'message' => 'ok ' . $index,
            ]);
        }

        $runs = $repository->recentRuns(150);

        $this->assertCount(100, $runs);
        $this->assertSame('ok 104', (string) ($runs[0]['message'] ?? ''));
    }
}
