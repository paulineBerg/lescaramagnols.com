<?php

declare(strict_types=1);

namespace Caramagnols\Logging;

final class LogAlertsNotificationGate
{
    public function __construct(private readonly string $stateFile)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $alerts
     * @return array{
     *     enabled: bool,
     *     should_notify: bool,
     *     suppressed: bool,
     *     reason: ?string,
     *     fingerprint: ?string,
     *     fingerprint_keys: array<int, string>,
     *     repeat_minutes: int,
     *     last_notified_at: ?string,
     *     next_notify_at: ?string
     * }
     */
    public function evaluate(array $alerts, string $notifyOn, int $repeatMinutes, ?int $now = null): array
    {
        $now ??= time();
        $repeatMinutes = max(0, min(10080, $repeatMinutes));
        $notifyOn = strtolower(trim($notifyOn));
        $base = [
            'enabled' => $notifyOn === 'alerts' && $repeatMinutes > 0 && trim($this->stateFile) !== '',
            'should_notify' => $alerts !== [],
            'suppressed' => false,
            'reason' => null,
            'fingerprint' => null,
            'fingerprint_keys' => [],
            'repeat_minutes' => $repeatMinutes,
            'last_notified_at' => null,
            'next_notify_at' => null,
        ];

        if ($alerts === []) {
            $this->clearState();

            return $base + ['should_notify' => false];
        }

        [$fingerprint, $fingerprintKeys] = $this->fingerprint($alerts);
        $base['fingerprint'] = $fingerprint;
        $base['fingerprint_keys'] = $fingerprintKeys;
        if ($notifyOn !== 'alerts' || $repeatMinutes === 0 || trim($this->stateFile) === '') {
            return $base;
        }

        $state = $this->readState();
        $lastFingerprint = is_string($state['fingerprint'] ?? null) ? (string) $state['fingerprint'] : '';
        $lastNotifiedAt = is_numeric($state['last_notified_at'] ?? null) ? (int) $state['last_notified_at'] : 0;
        $nextNotifyAt = $lastNotifiedAt > 0 ? $lastNotifiedAt + ($repeatMinutes * 60) : 0;
        if ($lastFingerprint === $fingerprint && $lastNotifiedAt > 0) {
            $base['last_notified_at'] = date('c', $lastNotifiedAt);
            $base['next_notify_at'] = date('c', $nextNotifyAt);
        }

        if ($lastFingerprint === $fingerprint && $lastNotifiedAt > 0 && $now < $nextNotifyAt) {
            $this->writeState($fingerprint, $fingerprintKeys, $alerts, $state, $now, false);

            return array_merge($base, [
                'should_notify' => false,
                'suppressed' => true,
                'reason' => 'repeat_window',
            ]);
        }

        $this->writeState($fingerprint, $fingerprintKeys, $alerts, $state, $now, true);

        return array_merge($base, [
            'last_notified_at' => date('c', $now),
            'next_notify_at' => date('c', $now + ($repeatMinutes * 60)),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $alerts
     * @return array{0: string, 1: array<int, string>}
     */
    private function fingerprint(array $alerts): array
    {
        $items = [];
        foreach ($alerts as $alert) {
            $metric = strtolower(trim((string) ($alert['metric'] ?? '')));
            if ($metric === '') {
                continue;
            }

            $severity = strtolower(trim((string) ($alert['severity'] ?? 'warning')));
            if (!in_array($severity, ['info', 'warning', 'error', 'critical'], true)) {
                $severity = 'warning';
            }

            $items[] = sprintf('%s:%s:%d', $metric, $severity, max(0, (int) ($alert['threshold'] ?? 0)));
        }

        sort($items, SORT_STRING);
        $source = implode('|', $items);

        return [hash('sha256', $source), $items];
    }

    /**
     * @return array<string, mixed>
     */
    private function readState(): array
    {
        if (trim($this->stateFile) === '' || !is_file($this->stateFile) || !is_readable($this->stateFile)) {
            return [];
        }

        $json = file_get_contents($this->stateFile);
        if (!is_string($json) || trim($json) === '') {
            return [];
        }

        $state = json_decode($json, true);

        return is_array($state) ? $state : [];
    }

    /**
     * @param array<int, string> $fingerprintKeys
     * @param array<int, array<string, mixed>> $alerts
     * @param array<string, mixed> $previousState
     */
    private function writeState(
        string $fingerprint,
        array $fingerprintKeys,
        array $alerts,
        array $previousState,
        int $now,
        bool $notified
    ): void {
        if (trim($this->stateFile) === '') {
            return;
        }

        $sameFingerprint = ($previousState['fingerprint'] ?? null) === $fingerprint;
        $firstSeenAt = $sameFingerprint && is_numeric($previousState['first_seen_at'] ?? null)
            ? (int) $previousState['first_seen_at']
            : $now;
        $lastNotifiedAt = $notified
            ? $now
            : (is_numeric($previousState['last_notified_at'] ?? null) ? (int) $previousState['last_notified_at'] : 0);
        $suppressedCount = $notified
            ? 0
            : (is_numeric($previousState['suppressed_count'] ?? null) ? (int) $previousState['suppressed_count'] + 1 : 1);
        $state = [
            'fingerprint' => $fingerprint,
            'fingerprint_keys' => $fingerprintKeys,
            'first_seen_at' => $firstSeenAt,
            'last_seen_at' => $now,
            'last_notified_at' => $lastNotifiedAt,
            'suppressed_count' => $suppressedCount,
            'alerts' => array_map(
                static fn (array $alert): array => [
                    'metric' => (string) ($alert['metric'] ?? ''),
                    'threshold' => (int) ($alert['threshold'] ?? 0),
                    'severity' => (string) ($alert['severity'] ?? 'warning'),
                ],
                $alerts
            ),
        ];

        $directory = dirname($this->stateFile);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }

        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return;
        }

        @file_put_contents($this->stateFile, $json . PHP_EOL, LOCK_EX);
    }

    private function clearState(): void
    {
        if (trim($this->stateFile) !== '' && is_file($this->stateFile)) {
            @unlink($this->stateFile);
        }
    }
}
