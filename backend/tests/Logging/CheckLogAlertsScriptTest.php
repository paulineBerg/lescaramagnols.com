<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\Logging;

use PHPUnit\Framework\TestCase;

final class CheckLogAlertsScriptTest extends TestCase
{
    private string $logDir = '';

    protected function tearDown(): void
    {
        if ($this->logDir === '' || !is_dir($this->logDir)) {
            return;
        }

        foreach (glob($this->logDir . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->logDir);
    }

    public function testPrivateOperationalAlertsHaveSeverityAndDoNotExposeSecrets(): void
    {
        $this->logDir = sys_get_temp_dir() . '/caramagnols-v5-log-alerts-' . bin2hex(random_bytes(6));
        mkdir($this->logDir, 0700, true);

        file_put_contents(
            $this->logDir . '/security.log',
            implode(PHP_EOL, [
                'security.WARNING: private.login.rejected {"identifier":"f***@example.com"}',
                'security.WARNING: private.csrf.rejected {"csrf_token":"raw-secret-token"}',
                'security.WARNING: private.discussion.rate_limited {"ip":"127.0.0.1"}',
                'security.WARNING: private.discussion.stream_failed {"conversation_id":12}',
                'security.WARNING: private.discussion.client_decrypt_failed {"message_id":34}',
                'security.ERROR: private.discussion.invite_email_failed {"recipient":"f***@example.com"}',
                'security.ERROR: ops.backup.failed {"password":"raw-db-password"}',
                'security.WARNING: private.backup.completed {"warning":"backup_recommended_size_exceeded"}',
                'security.CRITICAL: private.discussion.retention.failed {"error":"fixture"}',
                'security.ERROR: private.account_deletion_backups_purge.failed {"error":"fixture"}',
            ]) . PHP_EOL
        );
        file_put_contents(
            $this->logDir . '/content.log',
            implode(PHP_EOL, [
                'content.WARNING: private.discussion.media_scan.completed {"blocked":1}',
                'content.ERROR: cron.job.failed {"job":"purge_private_discussions"}',
            ]) . PHP_EOL
        );

        $script = dirname(__DIR__, 2) . '/core/tools/check_log_alerts.php';
        $command = sprintf(
            '%s %s --json --strict --since-minutes=60 --log-dir=%s '
            . '--private-login-fail-threshold=1 --private-csrf-threshold=1 '
            . '--private-rate-limit-threshold=1 --private-email-failed-threshold=1 '
            . '--private-backup-failed-threshold=1 --private-backup-warning-threshold=1 '
            . '--private-purge-failed-threshold=1 --private-discussion-stream-threshold=1 '
            . '--private-discussion-scan-threshold=1 --private-discussion-retention-threshold=1 '
            . '--private-discussion-decrypt-threshold=1 --private-discussion-rate-limit-threshold=1 '
            . '--cron-failed-threshold=1 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($script),
            escapeshellarg($this->logDir)
        );

        $output = [];
        $status = 0;
        exec($command, $output, $status);
        $rawOutput = implode(PHP_EOL, $output);
        $payload = json_decode($rawOutput, true);

        self::assertSame(2, $status);
        self::assertIsArray($payload);
        self::assertSame('critical', $payload['overall_severity'] ?? null);
        self::assertSame(1, $payload['counts']['private_login_failed'] ?? null);
        self::assertSame(1, $payload['counts']['private_csrf_rejected'] ?? null);
        self::assertSame(1, $payload['counts']['private_rate_limited'] ?? null);
        self::assertSame(1, $payload['counts']['private_email_failed'] ?? null);
        self::assertSame(1, $payload['counts']['private_backup_failed'] ?? null);
        self::assertSame(1, $payload['counts']['private_backup_warning'] ?? null);
        self::assertSame(1, $payload['counts']['private_purge_failed'] ?? null);
        self::assertSame(1, $payload['counts']['private_discussion_stream_failed'] ?? null);
        self::assertSame(1, $payload['counts']['private_discussion_scan_failed'] ?? null);
        self::assertSame(1, $payload['counts']['private_discussion_retention_failed'] ?? null);
        self::assertSame(1, $payload['counts']['private_discussion_decrypt_failed'] ?? null);
        self::assertSame(1, $payload['counts']['private_discussion_rate_limited'] ?? null);
        self::assertSame(1, $payload['summary']['private']['discussion']['stream_failed'] ?? null);
        self::assertSame(1, $payload['counts']['cron_failed'] ?? null);
        self::assertStringNotContainsString('raw-secret-token', $rawOutput);
        self::assertStringNotContainsString('raw-db-password', $rawOutput);
    }

    public function testNonStrictModeKeepsZeroExitCodeWhenAlertsAreDetected(): void
    {
        $this->logDir = sys_get_temp_dir() . '/caramagnols-v5-log-alerts-' . bin2hex(random_bytes(6));
        mkdir($this->logDir, 0700, true);

        file_put_contents(
            $this->logDir . '/content.log',
            'content.ERROR: cron.job.failed {"job":"purge_private_discussions"}' . PHP_EOL
        );

        $script = dirname(__DIR__, 2) . '/core/tools/check_log_alerts.php';
        $command = sprintf(
            '%s %s --json --since-minutes=60 --log-dir=%s --cron-failed-threshold=1 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($script),
            escapeshellarg($this->logDir)
        );

        $output = [];
        $status = 0;
        exec($command, $output, $status);
        $payload = json_decode(implode(PHP_EOL, $output), true);

        self::assertSame(0, $status);
        self::assertIsArray($payload);
        self::assertSame('error', $payload['overall_severity'] ?? null);
        self::assertSame('cron_failed', $payload['alerts'][0]['metric'] ?? null);
    }
}
