<?php

declare(strict_types=1);

namespace Caramagnols\Logging;

final class LogAlertsNotifier
{
    /** @var callable(string, string, string): bool */
    private $emailSender;

    /** @var callable(string, array<string, mixed>, int): array{success: bool, status: int, error: ?string} */
    private $webhookSender;

    /**
     * @param callable(string, string, string): bool $emailSender
     * @param callable(string, array<string, mixed>, int): array{success: bool, status: int, error: ?string} $webhookSender
     */
    public function __construct(callable $emailSender, callable $webhookSender)
    {
        $this->emailSender = $emailSender;
        $this->webhookSender = $webhookSender;
    }

    /**
     * @param array{
     *   generated_at: string,
     *   since_minutes: int,
     *   counts: array<string, int>,
     *   thresholds: array<string, int>,
     *   alerts: array<int, array{metric: string, count: int, threshold: int}>
     * } $report
     * @param array{
     *   notify_on: string,
     *   webhook_url: string,
     *   webhook_timeout: int,
     *   email_recipients: array<int, string>,
     *   email_subject_prefix: string,
     *   app_env: string,
     *   base_url: string
     * } $config
     * @return array{
     *   enabled: bool,
     *   notify_on: string,
     *   triggered: bool,
     *   has_error: bool,
     *   channels: array{
     *     webhook: array<string, mixed>,
     *     email: array<string, mixed>
     *   }
     * }
     */
    public function notify(array $report, array $config): array
    {
        $notifyOn = strtolower(trim((string) ($config['notify_on'] ?? 'alerts')));
        if (!in_array($notifyOn, ['alerts', 'always'], true)) {
            $notifyOn = 'alerts';
        }

        $alerts = is_array($report['alerts'] ?? null) ? $report['alerts'] : [];
        $triggered = $notifyOn === 'always' || $alerts !== [];
        $webhookUrl = trim((string) ($config['webhook_url'] ?? ''));
        $webhookTimeout = max(2, min(20, (int) ($config['webhook_timeout'] ?? 8)));
        $emailRecipients = is_array($config['email_recipients'] ?? null) ? $config['email_recipients'] : [];

        $webhook = [
            'configured' => $webhookUrl !== '',
            'attempted' => false,
            'success' => false,
            'status' => 0,
            'error' => null,
        ];
        $email = [
            'configured' => $emailRecipients !== [],
            'attempted' => false,
            'success' => false,
            'sent' => [],
            'failed' => [],
        ];

        if ($triggered && $webhookUrl !== '') {
            $webhook['attempted'] = true;
            $webhookPayload = $this->buildWebhookPayload($report, $config);
            $result = ($this->webhookSender)($webhookUrl, $webhookPayload, $webhookTimeout);
            $webhook['success'] = (bool) ($result['success'] ?? false);
            $webhook['status'] = (int) ($result['status'] ?? 0);
            $webhook['error'] = is_string($result['error'] ?? null) ? (string) $result['error'] : null;
        }

        if ($triggered && $emailRecipients !== []) {
            $email['attempted'] = true;
            $subject = $this->buildEmailSubject($report, $config);
            $html = $this->buildEmailHtml($report, $config);

            foreach ($emailRecipients as $recipient) {
                $recipient = trim((string) $recipient);
                if ($recipient === '') {
                    continue;
                }

                $ok = (bool) ($this->emailSender)($recipient, $subject, $html);
                if ($ok) {
                    $email['sent'][] = $recipient;
                } else {
                    $email['failed'][] = $recipient;
                }
            }

            $email['success'] = $email['failed'] === [];
        }

        $enabled = $webhook['configured'] || $email['configured'];
        $hasError = ($webhook['configured'] && $webhook['attempted'] && !$webhook['success'])
            || ($email['configured'] && $email['attempted'] && !$email['success']);

        return [
            'enabled' => $enabled,
            'notify_on' => $notifyOn,
            'triggered' => $triggered,
            'has_error' => $hasError,
            'channels' => [
                'webhook' => $webhook,
                'email' => $email,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $report
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function buildWebhookPayload(array $report, array $config): array
    {
        $alerts = is_array($report['alerts'] ?? null) ? $report['alerts'] : [];

        return [
            'source' => 'caramagnols.check_log_alerts',
            'severity' => $alerts === [] ? 'ok' : 'warning',
            'generated_at' => (string) ($report['generated_at'] ?? date('c')),
            'app_env' => (string) ($config['app_env'] ?? ''),
            'base_url' => (string) ($config['base_url'] ?? ''),
            'report' => $report,
        ];
    }

    /**
     * @param array<string, mixed> $report
     * @param array<string, mixed> $config
     */
    private function buildEmailSubject(array $report, array $config): string
    {
        $prefix = trim((string) ($config['email_subject_prefix'] ?? '[caramagnols]'));
        if ($prefix === '') {
            $prefix = '[caramagnols]';
        }

        $alerts = is_array($report['alerts'] ?? null) ? $report['alerts'] : [];
        $status = $alerts === [] ? 'OK' : 'ALERT';
        $sinceMinutes = (int) ($report['since_minutes'] ?? 0);

        return sprintf('%s check-log-alerts %s (%d min)', $prefix, $status, $sinceMinutes);
    }

    /**
     * @param array<string, mixed> $report
     * @param array<string, mixed> $config
     */
    private function buildEmailHtml(array $report, array $config): string
    {
        $baseUrl = htmlspecialchars((string) ($config['base_url'] ?? ''), ENT_QUOTES, 'UTF-8');
        $appEnv = htmlspecialchars((string) ($config['app_env'] ?? ''), ENT_QUOTES, 'UTF-8');
        $generatedAt = htmlspecialchars((string) ($report['generated_at'] ?? ''), ENT_QUOTES, 'UTF-8');
        $sinceMinutes = (int) ($report['since_minutes'] ?? 0);
        $alerts = is_array($report['alerts'] ?? null) ? $report['alerts'] : [];
        $counts = is_array($report['counts'] ?? null) ? $report['counts'] : [];
        $thresholds = is_array($report['thresholds'] ?? null) ? $report['thresholds'] : [];

        $html = '<h2>Rapport check-log-alerts</h2>';
        $html .= '<p><strong>Site:</strong> ' . $baseUrl . '<br>';
        $html .= '<strong>Environnement:</strong> ' . $appEnv . '<br>';
        $html .= '<strong>Fenetre:</strong> ' . $sinceMinutes . ' min<br>';
        $html .= '<strong>Genere le:</strong> ' . $generatedAt . '</p>';

        $html .= '<h3>Compteurs</h3><ul>';
        foreach ($counts as $metric => $count) {
            $label = htmlspecialchars((string) $metric, ENT_QUOTES, 'UTF-8');
            $threshold = (int) ($thresholds[$metric] ?? 0);
            $html .= sprintf(
                '<li><code>%s</code>: %d (seuil=%d)</li>',
                $label,
                (int) $count,
                $threshold
            );
        }
        $html .= '</ul>';

        if ($alerts === []) {
            $html .= '<p><strong>Aucune alerte declenchee.</strong></p>';
        } else {
            $html .= '<h3>Alertes declenchees</h3><ul>';
            foreach ($alerts as $alert) {
                $metric = htmlspecialchars((string) ($alert['metric'] ?? ''), ENT_QUOTES, 'UTF-8');
                $count = (int) ($alert['count'] ?? 0);
                $threshold = (int) ($alert['threshold'] ?? 0);
                $html .= sprintf('<li><code>%s</code>: %d >= %d</li>', $metric, $count, $threshold);
            }
            $html .= '</ul>';
        }

        return $html;
    }
}
