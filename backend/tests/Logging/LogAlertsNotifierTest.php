<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\Logging;

use Caramagnols\Logging\LogAlertsNotifier;
use PHPUnit\Framework\TestCase;

final class LogAlertsNotifierTest extends TestCase
{
    public function testDoesNotNotifyWhenNoAlertAndNotifyOnAlerts(): void
    {
        $webhookCalls = 0;
        $emailCalls = 0;

        $notifier = new LogAlertsNotifier(
            static function (string $to, string $subject, string $html) use (&$emailCalls): bool {
                $emailCalls++;
                return true;
            },
            static function (string $url, array $payload, int $timeout) use (&$webhookCalls): array {
                $webhookCalls++;
                return ['success' => true, 'status' => 200, 'error' => null];
            }
        );

        $report = $this->baseReport([]);
        $result = $notifier->notify($report, $this->baseConfig('alerts'));

        $this->assertFalse((bool) $result['triggered']);
        $this->assertFalse((bool) $result['has_error']);
        $this->assertSame(0, $webhookCalls);
        $this->assertSame(0, $emailCalls);
    }

    public function testNotifiesWebhookAndEmailWhenAlertIsTriggered(): void
    {
        $webhookCalls = 0;
        $emailCalls = 0;

        $notifier = new LogAlertsNotifier(
            static function (string $to, string $subject, string $html) use (&$emailCalls): bool {
                $emailCalls++;
                return true;
            },
            static function (string $url, array $payload, int $timeout) use (&$webhookCalls): array {
                $webhookCalls++;
                return ['success' => true, 'status' => 202, 'error' => null];
            }
        );

        $report = $this->baseReport([
            ['metric' => 'login_failed', 'count' => 12, 'threshold' => 10],
        ]);
        $result = $notifier->notify($report, $this->baseConfig('alerts'));

        $this->assertTrue((bool) $result['triggered']);
        $this->assertFalse((bool) $result['has_error']);
        $this->assertSame(1, $webhookCalls);
        $this->assertSame(2, $emailCalls);
        $this->assertTrue((bool) $result['channels']['webhook']['success']);
        $this->assertTrue((bool) $result['channels']['email']['success']);
    }

    public function testNotificationErrorIsExposedWhenChannelFails(): void
    {
        $notifier = new LogAlertsNotifier(
            static function (string $to, string $subject, string $html): bool {
                return false;
            },
            static function (string $url, array $payload, int $timeout): array {
                return ['success' => false, 'status' => 500, 'error' => 'Webhook KO'];
            }
        );

        $report = $this->baseReport([
            ['metric' => 'http_429', 'count' => 20, 'threshold' => 10],
        ]);
        $result = $notifier->notify($report, $this->baseConfig('always'));

        $this->assertTrue((bool) $result['triggered']);
        $this->assertTrue((bool) $result['has_error']);
        $this->assertFalse((bool) $result['channels']['webhook']['success']);
        $this->assertFalse((bool) $result['channels']['email']['success']);
    }

    /**
     * @param array<int, array{metric: string, count: int, threshold: int}> $alerts
     * @return array{
     *   generated_at: string,
     *   since_minutes: int,
     *   counts: array<string, int>,
     *   thresholds: array<string, int>,
     *   alerts: array<int, array{metric: string, count: int, threshold: int}>
     * }
     */
    private function baseReport(array $alerts): array
    {
        return [
            'generated_at' => '2026-03-21T12:00:00+01:00',
            'since_minutes' => 60,
            'counts' => [
                'login_failed' => 0,
                'rate_limited' => 0,
                'http_403' => 0,
                'http_429' => 0,
            ],
            'thresholds' => [
                'login_failed' => 10,
                'rate_limited' => 6,
                'http_403' => 30,
                'http_429' => 10,
            ],
            'alerts' => $alerts,
        ];
    }

    /**
     * @return array{
     *   notify_on: string,
     *   webhook_url: string,
     *   webhook_timeout: int,
     *   email_recipients: array<int, string>,
     *   email_subject_prefix: string,
     *   app_env: string,
     *   base_url: string
     * }
     */
    private function baseConfig(string $notifyOn): array
    {
        return [
            'notify_on' => $notifyOn,
            'webhook_url' => 'https://ops.example.com/hooks/log-alerts',
            'webhook_timeout' => 8,
            'email_recipients' => ['ops1@example.com', 'ops2@example.com'],
            'email_subject_prefix' => '[caramagnols]',
            'app_env' => 'production',
            'base_url' => 'https://lescaramagnols.com',
        ];
    }
}
