<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\Logging;

use Caramagnols\Logging\PrivateSecurityAlertReportBuilder;
use Caramagnols\PrivatePortal\Operations\PrivateAuditRetentionService;
use PHPUnit\Framework\TestCase;

final class PrivateOperationsLoggingTest extends TestCase
{
    private string $tempDir = '';

    protected function tearDown(): void
    {
        if ($this->tempDir !== '' && is_dir($this->tempDir)) {
            foreach (glob($this->tempDir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->tempDir);
        }
    }

    public function testPrivateSecurityAlertReportCounts403429And5xx(): void
    {
        $builder = new PrivateSecurityAlertReportBuilder();
        $report = $builder->build([
            ['event' => 'private.login.rejected', 'context' => []],
            ['event' => 'private.module.access_denied', 'context' => ['status' => 403]],
            ['event' => 'private.rate_limit', 'context' => ['status' => 429]],
            ['event' => 'private.server_error', 'context' => ['status' => 500]],
        ], 30, ['login_failed' => 1, 'http_403' => 1, 'http_429' => 1, 'http_5xx' => 1]);

        $this->assertSame(1, $report['counts']['login_failed']);
        $this->assertSame(1, $report['counts']['http_403']);
        $this->assertSame(1, $report['counts']['http_429']);
        $this->assertSame(1, $report['counts']['http_5xx']);
        $this->assertCount(4, $report['alerts']);
    }

    public function testPrivateAuditRetentionPurgesOldLogFiles(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/caramagnols-phase9-retention-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0700, true);
        $old = $this->tempDir . '/security.log.9';
        $new = $this->tempDir . '/security.log';
        file_put_contents($old, 'old');
        file_put_contents($new, 'new');
        touch($old, time() - 20 * 86400);

        $service = new PrivateAuditRetentionService();
        $dryRun = $service->purgeFiles($this->tempDir, 10, true);
        $this->assertSame(1, $dryRun['matched']);
        $this->assertSame(0, $dryRun['deleted']);
        $this->assertFileExists($old);

        $result = $service->purgeFiles($this->tempDir, 10, false);
        $this->assertSame(1, $result['deleted']);
        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($new);
    }
}
