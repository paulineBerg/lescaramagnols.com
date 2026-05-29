<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Caramagnols\Logging\LogAlertsNotifier;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit être exécutée en CLI.\n");
    exit(1);
}

$options = parse_cli_options(array_slice($argv, 1));
$sinceMinutes = max(1, min(10080, (int) ($options['since-minutes'] ?? 15)));
$strict = isset($options['strict']);
$jsonOutput = isset($options['json']);
$notifyOn = normalize_notify_on((string) ($options['notify-on'] ?? app_config('site.log_alerts.notify_on', env('LOG_ALERTS_NOTIFY_ON', 'alerts'))));
$webhookUrl = trim((string) ($options['webhook-url'] ?? env('LOG_ALERTS_WEBHOOK_URL', '')));
$webhookTimeout = max(2, min(20, (int) ($options['webhook-timeout'] ?? env('LOG_ALERTS_WEBHOOK_TIMEOUT', 8))));
$emailRecipients = parse_recipient_list((string) ($options['email-to'] ?? env('LOG_ALERTS_EMAIL_TO', '')));
$emailSubjectPrefix = trim((string) ($options['email-subject-prefix'] ?? env('LOG_ALERTS_EMAIL_SUBJECT_PREFIX', '[caramagnols]')));
$failOnNotifyError = to_bool($options['fail-on-notify-error'] ?? env('LOG_ALERTS_FAIL_ON_NOTIFY_ERROR', false), false);

$thresholds = [
    'login_failed' => max(1, (int) ($options['login-fail-threshold'] ?? 10)),
    'rate_limited' => max(1, (int) ($options['rate-limit-threshold'] ?? 6)),
    'http_403' => max(1, (int) ($options['http-403-threshold'] ?? 30)),
    'http_429' => max(1, (int) ($options['http-429-threshold'] ?? 10)),
    'cron_failed' => max(1, (int) ($options['cron-failed-threshold'] ?? 1)),
];

$logDir = ROOT_PATH . '/data/logs';
$sinceTimestamp = time() - ($sinceMinutes * 60);

$counts = [
    'login_failed' => 0,
    'rate_limited' => 0,
    'http_403' => 0,
    'http_429' => 0,
    'cron_failed' => 0,
];

read_log_file($logDir . '/security.log', $sinceTimestamp, static function (string $line) use (&$counts): void {
    if (str_contains($line, 'admin.login.failed')) {
        $counts['login_failed']++;
    }

    if (str_contains($line, 'rate_limited')) {
        $counts['rate_limited']++;
    }
});

read_log_file($logDir . '/access.log', $sinceTimestamp, static function (string $line) use (&$counts): void {
    if (preg_match('/"status":\s*403\b/', $line) === 1) {
        $counts['http_403']++;
    }

    if (preg_match('/"status":\s*429\b/', $line) === 1) {
        $counts['http_429']++;
    }
});

read_log_file($logDir . '/content.log', $sinceTimestamp, static function (string $line) use (&$counts): void {
    if (str_contains($line, 'cron.job.failed') || str_contains($line, 'cron.scheduler.failed')) {
        $counts['cron_failed']++;
    }
});

$alerts = [];
foreach ($thresholds as $metric => $threshold) {
    if ($counts[$metric] >= $threshold) {
        $alerts[] = [
            'metric' => $metric,
            'count' => $counts[$metric],
            'threshold' => $threshold,
        ];
    }
}

$payload = [
    'generated_at' => date('c'),
    'since_minutes' => $sinceMinutes,
    'counts' => $counts,
    'thresholds' => $thresholds,
    'alerts' => $alerts,
];

$notifier = new LogAlertsNotifier(
    static function (string $recipient, string $subject, string $html): bool {
        require_once __DIR__ . '/../mailer.php';
        if (!function_exists('send_notification_email')) {
            return false;
        }

        return send_notification_email($recipient, $subject, $html);
    },
    static function (string $url, array $webhookPayload, int $timeout): array {
        return post_webhook_notification($url, $webhookPayload, $timeout);
    }
);

$notificationReport = $notifier->notify($payload, [
    'notify_on' => $notifyOn,
    'webhook_url' => $webhookUrl,
    'webhook_timeout' => $webhookTimeout,
    'email_recipients' => $emailRecipients,
    'email_subject_prefix' => $emailSubjectPrefix,
    'app_env' => (string) env('APP_ENV', ''),
    'base_url' => (string) app_config('base_url', ''),
]);

$payload['notification'] = $notificationReport;

if ($jsonOutput) {
    fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
} else {
    fwrite(STDOUT, sprintf("Alertes logs (fenêtre: %d min)\n", $sinceMinutes));
    fwrite(STDOUT, sprintf("- admin.login.failed: %d (seuil=%d)\n", $counts['login_failed'], $thresholds['login_failed']));
    fwrite(STDOUT, sprintf("- rate_limited: %d (seuil=%d)\n", $counts['rate_limited'], $thresholds['rate_limited']));
    fwrite(STDOUT, sprintf("- http 403: %d (seuil=%d)\n", $counts['http_403'], $thresholds['http_403']));
    fwrite(STDOUT, sprintf("- http 429: %d (seuil=%d)\n", $counts['http_429'], $thresholds['http_429']));
    fwrite(STDOUT, sprintf("- cron failed: %d (seuil=%d)\n", $counts['cron_failed'], $thresholds['cron_failed']));

    if ($alerts === []) {
        fwrite(STDOUT, "Aucune alerte déclenchée.\n");
    } else {
        fwrite(STDOUT, "Alertes déclenchées:\n");
        foreach ($alerts as $alert) {
            fwrite(
                STDOUT,
                sprintf(
                    "  - %s: %d >= %d\n",
                    (string) $alert['metric'],
                    (int) $alert['count'],
                    (int) $alert['threshold']
                )
            );
        }
    }

    render_notification_report($notificationReport, $notifyOn);
}

$exitCode = 0;
if ($strict && $alerts !== []) {
    $exitCode = 2;
}

if ($failOnNotifyError && ($notificationReport['has_error'] ?? false) === true && $exitCode === 0) {
    $exitCode = 3;
}

exit($exitCode);

/**
 * @param callable(string): void $onLine
 */
function read_log_file(string $path, int $sinceTimestamp, callable $onLine): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $handle = fopen($path, 'r');
    if (!is_resource($handle)) {
        return;
    }

    try {
        while (($line = fgets($handle)) !== false) {
            if (!is_string($line) || trim($line) === '') {
                continue;
            }

            $timestamp = parse_log_timestamp($line);
            if ($timestamp !== null && $timestamp < $sinceTimestamp) {
                continue;
            }

            $onLine($line);
        }
    } finally {
        fclose($handle);
    }
}

function parse_log_timestamp(string $line): ?int
{
    if (preg_match('/^\[([^\]]+)\]/', $line, $matches) !== 1) {
        return null;
    }

    $timestamp = strtotime((string) $matches[1]);

    return is_int($timestamp) ? $timestamp : null;
}

/**
 * @param array<int, string> $arguments
 * @return array<string, string|true>
 */
function parse_cli_options(array $arguments): array
{
    $options = [];

    foreach ($arguments as $argument) {
        if (!is_string($argument) || !str_starts_with($argument, '--')) {
            continue;
        }

        $parts = explode('=', substr($argument, 2), 2);
        if (!isset($parts[1])) {
            $options[$parts[0]] = true;
            continue;
        }

        $options[$parts[0]] = $parts[1];
    }

    return $options;
}

function normalize_notify_on(string $rawValue): string
{
    $normalized = strtolower(trim($rawValue));
    if (in_array($normalized, ['alerts', 'always'], true)) {
        return $normalized;
    }

    return 'alerts';
}

/**
 * @return array<int, string>
 */
function parse_recipient_list(string $rawList): array
{
    $parts = preg_split('/[\s,;]+/', trim($rawList), -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($parts)) {
        return [];
    }

    $recipients = [];
    foreach ($parts as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate === '' || filter_var($candidate, FILTER_VALIDATE_EMAIL) === false) {
            continue;
        }

        if (!in_array($candidate, $recipients, true)) {
            $recipients[] = $candidate;
        }
    }

    return $recipients;
}

function to_bool(mixed $value, bool $default): bool
{
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value)) {
        return ((int) $value) !== 0;
    }

    if (!is_string($value)) {
        return $default;
    }

    $normalized = strtolower(trim($value));
    if ($normalized === '') {
        return $default;
    }

    if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    return $default;
}

/**
 * @param array<string, mixed> $notificationReport
 */
function render_notification_report(array $notificationReport, string $notifyOn): void
{
    fwrite(STDOUT, sprintf("Canal ops (notify-on=%s)\n", $notifyOn));

    $triggered = (bool) ($notificationReport['triggered'] ?? false);
    if (!$triggered) {
        fwrite(STDOUT, "- notifications: non declenchees (aucune alerte)\n");
        return;
    }

    $channels = is_array($notificationReport['channels'] ?? null) ? $notificationReport['channels'] : [];

    $webhook = is_array($channels['webhook'] ?? null) ? $channels['webhook'] : [];
    if (!empty($webhook['configured'])) {
        fwrite(
            STDOUT,
            sprintf(
                "- webhook: %s (status=%d%s)\n",
                !empty($webhook['success']) ? 'OK' : 'KO',
                (int) ($webhook['status'] ?? 0),
                !empty($webhook['error']) ? ', error=' . (string) $webhook['error'] : ''
            )
        );
    } else {
        fwrite(STDOUT, "- webhook: non configure\n");
    }

    $email = is_array($channels['email'] ?? null) ? $channels['email'] : [];
    if (!empty($email['configured'])) {
        $sent = is_array($email['sent'] ?? null) ? $email['sent'] : [];
        $failed = is_array($email['failed'] ?? null) ? $email['failed'] : [];
        fwrite(
            STDOUT,
            sprintf(
                "- email: %s (sent=%d, failed=%d)\n",
                !empty($email['success']) ? 'OK' : 'KO',
                count($sent),
                count($failed)
            )
        );
    } else {
        fwrite(STDOUT, "- email: non configure\n");
    }
}

/**
 * @param array<string, mixed> $payload
 * @return array{success: bool, status: int, error: ?string}
 */
function post_webhook_notification(string $url, array $payload, int $timeout): array
{
    $trimmedUrl = trim($url);
    if ($trimmedUrl === '' || filter_var($trimmedUrl, FILTER_VALIDATE_URL) === false) {
        return [
            'success' => false,
            'status' => 0,
            'error' => 'URL webhook invalide.',
        ];
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return [
            'success' => false,
            'status' => 0,
            'error' => 'Payload webhook invalide.',
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => "Content-Type: application/json\r\n"
                . "User-Agent: Caramagnols-LogAlerts/1.0\r\n",
            'content' => $json,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $response = @file_get_contents($trimmedUrl, false, $context);
    $headers = is_array($http_response_header) ? $http_response_header : [];
    $status = http_status_from_headers($headers);

    if ($status >= 200 && $status < 300) {
        return [
            'success' => true,
            'status' => $status,
            'error' => null,
        ];
    }

    $error = $response === false
        ? 'Requete webhook en erreur.'
        : sprintf('Webhook retourne HTTP %d.', $status);

    return [
        'success' => false,
        'status' => $status,
        'error' => $error,
    ];
}

/**
 * @param array<int, string> $headers
 */
function http_status_from_headers(array $headers): int
{
    $status = 0;
    foreach ($headers as $line) {
        if (!is_string($line)) {
            continue;
        }

        if (preg_match('#^HTTP/\S+\s+(\d{3})#i', trim($line), $matches) === 1) {
            $status = (int) $matches[1];
        }
    }

    return $status;
}
