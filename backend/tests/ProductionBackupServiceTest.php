<?php

declare(strict_types=1);

use Caramagnols\Backup\ProductionBackupService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class ProductionBackupServiceTest extends TestCase
{
    private string $tmpRoot;

    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/caramagnols-backup-test-' . bin2hex(random_bytes(6));
        mkdir($this->tmpRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectoryRecursively($this->tmpRoot);
    }

    public function testDryRunDescribesBackupWithoutWritingFiles(): void
    {
        $backendRoot = $this->tmpRoot . '/backend';
        $backupRoot = $this->tmpRoot . '/backups';
        mkdir($backendRoot, 0777, true);

        $service = new ProductionBackupService(
            $backendRoot,
            $backupRoot,
            [
                'host' => '127.0.0.1',
                'port' => 3306,
                'name' => 'caramagnols',
                'user' => 'caramagnols',
                'password' => 'secret',
                'charset' => 'utf8mb4',
            ],
            'car_',
            'tar',
            'mysqldump',
            7
        );

        $result = $service->run([
            'scope' => 'all',
            'dry_run' => true,
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['dry_run']);
        $this->assertSame('all', $result['scope']);
        $this->assertFalse(is_dir($backupRoot));
        $this->assertSame($backupRoot, $result['configuration']['backupRoot']);
        $this->assertSame(7, $result['configuration']['retentionDays']);
        $this->assertTrue($result['configuration']['backupRootOutsideRoot']);
    }

    public function testBackupRefusesTargetInsideBackend(): void
    {
        $backendRoot = $this->tmpRoot . '/backend';
        $backupRoot = $backendRoot . '/var/backups';
        mkdir($backendRoot, 0777, true);

        $service = new ProductionBackupService(
            $backendRoot,
            $backupRoot,
            [
                'name' => 'caramagnols',
                'user' => 'caramagnols',
                'password' => 'secret',
            ],
            'car_'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('le dossier de backup ne doit pas être dans le backend');

        $service->run([
            'scope' => 'files',
        ]);
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
