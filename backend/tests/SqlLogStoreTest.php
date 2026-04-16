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
