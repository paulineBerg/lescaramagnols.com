<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\Logging;

use Caramagnols\Logging\LogAlertsNotificationGate;
use PHPUnit\Framework\TestCase;

final class LogAlertsNotificationGateTest extends TestCase
{
    private string $stateFile = '';

    protected function tearDown(): void
    {
        if ($this->stateFile !== '' && is_file($this->stateFile)) {
            @unlink($this->stateFile);
        }

        $directory = $this->stateFile !== '' ? dirname($this->stateFile) : '';
        if ($directory !== '' && is_dir($directory)) {
            @rmdir($directory);
        }
    }

    public function testSuppressesRepeatedAlertInsideRepeatWindow(): void
    {
        $gate = new LogAlertsNotificationGate($this->newStateFile());
        $alerts = [
            ['metric' => 'private_csrf_rejected', 'count' => 3, 'threshold' => 3, 'severity' => 'warning'],
        ];

        $first = $gate->evaluate($alerts, 'alerts', 180, 1_780_000_000);
        $second = $gate->evaluate(
            [
                ['metric' => 'private_csrf_rejected', 'count' => 8, 'threshold' => 3, 'severity' => 'warning'],
            ],
            'alerts',
            180,
            1_780_000_300
        );

        self::assertTrue($first['should_notify']);
        self::assertFalse($first['suppressed']);
        self::assertFalse($second['should_notify']);
        self::assertTrue($second['suppressed']);
        self::assertSame('repeat_window', $second['reason']);
        self::assertSame($first['fingerprint'], $second['fingerprint']);
    }

    public function testNotifiesImmediatelyWhenAlertFingerprintChanges(): void
    {
        $gate = new LogAlertsNotificationGate($this->newStateFile());
        $gate->evaluate(
            [
                ['metric' => 'private_csrf_rejected', 'count' => 3, 'threshold' => 3, 'severity' => 'warning'],
            ],
            'alerts',
            180,
            1_780_000_000
        );

        $decision = $gate->evaluate(
            [
                ['metric' => 'private_csrf_rejected', 'count' => 4, 'threshold' => 3, 'severity' => 'warning'],
                ['metric' => 'cron_failed', 'count' => 1, 'threshold' => 1, 'severity' => 'error'],
            ],
            'alerts',
            180,
            1_780_000_300
        );

        self::assertTrue($decision['should_notify']);
        self::assertFalse($decision['suppressed']);
        self::assertContains('cron_failed:error:1', $decision['fingerprint_keys']);
    }

    public function testAllowsReminderAfterRepeatWindow(): void
    {
        $gate = new LogAlertsNotificationGate($this->newStateFile());
        $alerts = [
            ['metric' => 'private_csrf_rejected', 'count' => 3, 'threshold' => 3, 'severity' => 'warning'],
        ];

        $gate->evaluate($alerts, 'alerts', 180, 1_780_000_000);
        $decision = $gate->evaluate($alerts, 'alerts', 180, 1_780_011_000);

        self::assertTrue($decision['should_notify']);
        self::assertFalse($decision['suppressed']);
    }

    public function testNotifyAlwaysBypassesSuppression(): void
    {
        $gate = new LogAlertsNotificationGate($this->newStateFile());
        $alerts = [
            ['metric' => 'private_csrf_rejected', 'count' => 3, 'threshold' => 3, 'severity' => 'warning'],
        ];

        $gate->evaluate($alerts, 'always', 180, 1_780_000_000);
        $decision = $gate->evaluate($alerts, 'always', 180, 1_780_000_300);

        self::assertTrue($decision['should_notify']);
        self::assertFalse($decision['suppressed']);
    }

    private function newStateFile(): string
    {
        $directory = sys_get_temp_dir() . '/caramagnols-alert-gate-' . bin2hex(random_bytes(6));
        mkdir($directory, 0700, true);
        $this->stateFile = $directory . '/state.json';

        return $this->stateFile;
    }
}
