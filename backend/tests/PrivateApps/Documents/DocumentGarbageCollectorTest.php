<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\Documents\Repository\DocumentHubRepository;
use Caramagnols\PrivateApps\Documents\Service\DocumentGarbageCollector;
use Caramagnols\PrivateApps\Documents\Service\DocumentStorageService;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../core/bootstrap.php';

final class DocumentGarbageCollectorTest extends TestCase
{
    use EditorialSqlTestTrait;

    private string $tmpRoot;

    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/caramagnols-document-gc-test-' . bin2hex(random_bytes(6));
        mkdir($this->tmpRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
        $this->removeDirectoryRecursively($this->tmpRoot);
    }

    public function testDryRunCountsExpiredFilesWithoutDeletingThem(): void
    {
        $storage = new DocumentStorageService($this->tmpRoot . '/document-hub');
        $quarantineFile = $storage->quarantineDirectory() . '/old.tmp';
        $exportFile = $storage->exportsTempDirectory() . '/old.zip';
        file_put_contents($quarantineFile, 'quarantine');
        file_put_contents($exportFile, 'export');
        touch($quarantineFile, time() - 7200);
        touch($exportFile, time() - 7200);

        $collector = new DocumentGarbageCollector(
            new DocumentHubRepository($this->editorialSqlDatabase()),
            $storage
        );

        $report = $collector->run(false, 3600, 3600, true);

        $this->assertTrue($report['dry_run']);
        $this->assertSame(1, $report['quarantine_eligible']);
        $this->assertSame(1, $report['exports_eligible']);
        $this->assertSame(0, $report['quarantine_purged']);
        $this->assertSame(0, $report['exports_purged']);
        $this->assertFileExists($quarantineFile);
        $this->assertFileExists($exportFile);
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
