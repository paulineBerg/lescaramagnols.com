<?php

declare(strict_types=1);

use Caramagnols\Logging\AppEventLogger;
use Caramagnols\Logging\LoggerFactory;
use Caramagnols\Logging\SqlLogStore;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class SqlLogStoreTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testInsertListAndFilterEntriesFromSql(): void
    {
        $store = new SqlLogStore($this->editorialSqlDatabase());

        $this->assertTrue(
            $store->insert(
                'security',
                'warning',
                'admin.login.failed',
                ['actor' => 'ad***@example.com', 'ip' => '127.0.0.1'],
                new \DateTimeImmutable('2026-03-18 10:30:00')
            )
        );
        $this->assertTrue(
            $store->insert(
                'content',
                'info',
                'admin.pages.saved',
                ['slug' => 'accueil'],
                new \DateTimeImmutable('2026-03-18 11:00:00')
            )
        );

        $all = $store->listEntries([], 10);
        $securityOnly = $store->listEntries(['channel' => 'security'], 10);
        $search = $store->listEntries(['q' => 'accueil'], 10);

        $this->assertCount(2, $all);
        $this->assertSame('admin.pages.saved', $all[0]['event'] ?? null);
        $this->assertCount(1, $securityOnly);
        $this->assertSame('warning', $securityOnly[0]['level'] ?? null);
        $this->assertSame('127.0.0.1', $securityOnly[0]['context']['ip'] ?? null);
        $this->assertCount(1, $search);
        $this->assertSame('content', $search[0]['channel'] ?? null);
        $this->assertContains('security', $store->listChannels());
        $this->assertContains('warning', $store->listLevels());
    }

    public function testDeleteEntriesByIdAndByFilters(): void
    {
        $store = new SqlLogStore($this->editorialSqlDatabase());

        $store->insert('security', 'warning', 'admin.login.failed', ['ip' => '127.0.0.1'], new \DateTimeImmutable('2026-03-18 09:00:00'));
        $store->insert('security', 'info', 'admin.logs.viewed', ['page' => 'logs'], new \DateTimeImmutable('2026-03-18 10:00:00'));
        $store->insert('content', 'info', 'admin.pages.saved', ['slug' => 'accueil'], new \DateTimeImmutable('2026-03-18 11:00:00'));

        $this->assertCount(3, $store->listEntries([], 10));
        $securityEntries = $store->listEntries(['channel' => 'security'], 10);
        $this->assertCount(2, $securityEntries);

        $deletedById = $store->deleteByIds([(int) ($securityEntries[0]['id'] ?? 0)]);
        $remainingAfterDelete = $store->countEntries([]);
        $deletedFiltered = $store->deleteByFilters(['channel' => 'security']);

        $this->assertSame(1, $deletedById);
        $this->assertSame(2, $remainingAfterDelete);
        $this->assertSame(1, $deletedFiltered);
        $this->assertSame(1, $store->countEntries([]));
        $this->assertSame('content', $store->listEntries([], 10)[0]['channel'] ?? null);
    }

    public function testPurgeOlderThanKeepsRecentAndSensitiveLogsLonger(): void
    {
        $store = new SqlLogStore($this->editorialSqlDatabase());
        $now = new \DateTimeImmutable('2026-04-28 12:00:00');

        $store->insert('content', 'info', 'old.content', [], new \DateTimeImmutable('2026-01-01 10:00:00'));
        $store->insert('content', 'info', 'recent.content', [], new \DateTimeImmutable('2026-04-20 10:00:00'));
        $store->insert('security', 'warning', 'kept.security', [], new \DateTimeImmutable('2026-01-01 10:00:00'));
        $store->insert('security', 'error', 'old.security', [], new \DateTimeImmutable('2025-01-01 10:00:00'));

        $dryRun = $store->purgeOlderThan(30, 365, true, $now);

        $this->assertSame(1, $dryRun['regularMatched']);
        $this->assertSame(1, $dryRun['sensitiveMatched']);
        $this->assertSame(0, $dryRun['deleted']);
        $this->assertSame(4, $store->countEntries([]));

        $result = $store->purgeOlderThan(30, 365, false, $now);

        $this->assertSame(1, $result['regularDeleted']);
        $this->assertSame(1, $result['sensitiveDeleted']);
        $this->assertSame(2, $result['deleted']);
        $this->assertSame(2, $store->countEntries([]));
        $this->assertSame(1, $store->countEntries(['channel' => 'content']));
        $this->assertSame(1, $store->countEntries(['channel' => 'security']));
        $this->assertSame('recent.content', $store->listEntries(['channel' => 'content'], 10)[0]['event'] ?? null);
        $this->assertSame('kept.security', $store->listEntries(['channel' => 'security'], 10)[0]['event'] ?? null);
    }

    public function testAppEventLoggerAlsoWritesIntoSqlThroughLoggerFactory(): void
    {
        $store = new SqlLogStore($this->editorialSqlDatabase());
        $logDir = sys_get_temp_dir() . '/caramagnols-sql-logger-' . bin2hex(random_bytes(6));
        mkdir($logDir, 0777, true);

        try {
            $logger = new AppEventLogger(new LoggerFactory($logDir, 'test', $store));
            $logger->security('admin.login.failed', ['ip' => '127.0.0.1'], 'warning');

            $entries = $store->listEntries(['channel' => 'security'], 10);

            $this->assertCount(1, $entries);
            $this->assertSame('admin.login.failed', $entries[0]['event'] ?? null);
            $this->assertSame('warning', $entries[0]['level'] ?? null);
            $this->assertSame('127.0.0.1', $entries[0]['context']['ip'] ?? null);
        } finally {
            foreach (glob($logDir . '/*') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($logDir);
        }
    }
}
