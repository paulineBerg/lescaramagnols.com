<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Service;

use Caramagnols\Logging\AppEventLogger;

/**
 * Service de notification pour les jobs cron du Document Hub.
 *
 * Gère:
 * - Logging centralisé via AppEventLogger
 * - Envoi d'emails pour les échecs et alertes
 * - Journalisation des résultats de jobs
 */
final class DocumentHubCronNotificationService
{
    private const CHANNEL = 'cron.document_hub';
    private const EMAIL_SUBJECT_PREFIX = '[Document Hub] ';

    private readonly bool $emailEnabled;
    private readonly string $emailRecipient;
    private readonly ?AppEventLogger $logger;

    /**
     * @param AppEventLogger|null $logger Logger optionnel (peut être null si non configuré)
     * @param array<string, mixed> $config Configuration depuis app_config('private.document_hub.notifications')
     */
    public function __construct(
        ?AppEventLogger $logger = null,
        array $config = []
    ) {
        $this->logger = $logger;

        // Configuration email
        $this->emailEnabled = (bool) ($config['email_enabled'] ?? false);
        $this->emailRecipient = (string) ($config['email_recipient'] ?? '');
    }

    /**
     * Crée une instance à partir de la configuration globale.
     */
    public static function fromAppConfig(): self
    {
        $logger = null;
        if (function_exists('app_config') && class_exists(\Caramagnols\Logging\AppEventLogger::class)) {
            try {
                $loggerFactory = new \Caramagnols\Logging\LoggerFactory(
                    (string) (app_config('log.dir') ?? '/tmp/logs'),
                    (string) (app_config('env') ?? 'development')
                );
                $logger = new AppEventLogger($loggerFactory);
            } catch (\Throwable) {
                // Logger non disponible, continuer sans
            }
        }

        $config = function_exists('app_config') ? app_config('private.document_hub.notifications', []) : [];
        if (!is_array($config)) {
            $config = [];
        }

        return new self($logger, $config);
    }

    /**
     * Notifie le début d'un job cron.
     *
     * @param array<string, mixed> $job Définition du job
     */
    public function notifyJobStarted(array $job, string $mode = 'normal'): void
    {
        $jobCode = (string) ($job['code'] ?? 'unknown');
        $jobName = (string) ($job['name'] ?? $jobCode);

        $context = [
            'job_code' => $jobCode,
            'job_name' => $jobName,
            'mode' => $mode,
            'timestamp' => date('c'),
        ];

        $this->log('job_started', $context, 'info');
    }

    /**
     * Notifie la fin réussie d'un job cron.
     *
     * @param array<string, mixed> $job Définition du job
     * @param array<string, mixed> $result Résultat de l'exécution
     */
    public function notifyJobSuccess(array $job, array $result): void
    {
        $jobCode = (string) ($job['code'] ?? 'unknown');
        $jobName = (string) ($job['name'] ?? $jobCode);
        $durationMs = (int) ($result['duration_ms'] ?? 0);
        $output = (string) ($result['stdout_text'] ?? '');

        $context = [
            'job_code' => $jobCode,
            'job_name' => $jobName,
            'status' => 'success',
            'duration_ms' => $durationMs,
            'duration_human' => $this->formatDuration($durationMs),
            'exit_code' => $result['exit_code'] ?? null,
            'output_length' => strlen($output),
        ];

        $this->log('job_success', $context, 'info');

        // Ne pas notifier par email pour les succès en mode normal (trop bruyant)
        // Seulement pour les échecs et alertes
    }

    /**
     * Notifie un échec de job cron.
     *
     * @param array<string, mixed> $job Définition du job
     * @param array<string, mixed> $result Résultat de l'exécution
     */
    public function notifyJobFailure(array $job, array $result): void
    {
        $jobCode = (string) ($job['code'] ?? 'unknown');
        $jobName = (string) ($job['name'] ?? $jobCode);
        $durationMs = (int) ($result['duration_ms'] ?? 0);
        $exitCode = $result['exit_code'] ?? null;
        $errorOutput = (string) ($result['stderr_text'] ?? ($result['stdout_text'] ?? ''));

        $context = [
            'job_code' => $jobCode,
            'job_name' => $jobName,
            'status' => 'failure',
            'duration_ms' => $durationMs,
            'duration_human' => $this->formatDuration($durationMs),
            'exit_code' => $exitCode,
            'error_output' => substr($errorOutput, 0, 1000), // Limiter la taille
        ];

        $this->log('job_failure', $context, 'error');

        // Envoyer un email pour les échecs
        $this->sendAlertEmail(
            self::EMAIL_SUBJECT_PREFIX . 'ÉCHEC: ' . $jobName,
            $this->formatFailureEmail($job, $result)
        );
    }

    /**
     * Notifie une alerte (ex: job bloqué, incohérence détectée).
     */
    public function notifyAlert(string $type, string $title, string $message, array $context = []): void
    {
        $fullContext = array_merge($context, [
            'alert_type' => $type,
            'alert_title' => $title,
            'timestamp' => date('c'),
        ]);

        $this->log('alert_' . $type, $fullContext, 'warning');

        // Envoyer email pour les alertes
        $this->sendAlertEmail(
            self::EMAIL_SUBJECT_PREFIX . 'ALERTE: ' . $title,
            "Type: {$type}\n\nMessage: {$message}\n\nContexte:\n" . print_r($context, true)
        );
    }

    /**
     * Notifie un résumé quotidien des jobs cron.
     *
     * @param array<string, mixed> $summary Résumé des exécutions
     */
    public function notifyDailySummary(array $summary): void
    {
        $context = [
            'summary' => $summary,
            'timestamp' => date('c'),
        ];

        $this->log('daily_summary', $context, 'info');

        // Envoyer email de résumé si des échecs ou alertes
        $hasFailures = ($summary['failed_jobs'] ?? 0) > 0 || ($summary['error_count'] ?? 0) > 0;
        if ($hasFailures) {
            $this->sendAlertEmail(
                self::EMAIL_SUBJECT_PREFIX . 'Résumé Quotidien (avec erreurs)',
                $this->formatDailySummaryEmail($summary)
            );
        }
    }

    /**
     * Log un événement.
     */
    private function log(string $event, array $context, string $level = 'info'): void
    {
        if ($this->logger === null) {
            // Si pas de logger, afficher sur stderr
            file_put_contents('php://stderr', '[' . date('c') . '] ' . self::CHANNEL . '.' . $event . ': ' . json_encode($context) . "\n", FILE_APPEND);
            return;
        }

        try {
            match ($level) {
                'debug' => $this->logger->content($event, $context, 'debug'),
                'info' => $this->logger->content($event, $context, 'info'),
                'warning' => $this->logger->content($event, $context, 'warning'),
                'error' => $this->logger->content($event, $context, 'error'),
                default => $this->logger->content($event, $context, 'info'),
            };
        } catch (\Throwable) {
            // Ne pas bloquer si le logging échoue
        }
    }

    /**
     * Envoie un email d'alerte.
     */
    private function sendAlertEmail(string $subject, string $body): void
    {
        if (!$this->emailEnabled || $this->emailRecipient === '') {
            return;
        }

        // Utiliser mail() si disponible, sinon éviter les erreurs
        if (!function_exists('mail')) {
            return;
        }

        $headers = [
            'From: Document Hub <noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '>' ,
            'X-Priority: 1',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        @mail(
            $this->emailRecipient,
            $subject,
            $body,
            implode("\r\n", $headers)
        );
    }

    /**
     * Formate la durée en secondes en format lisible.
     */
    private function formatDuration(int $milliseconds): string
    {
        if ($milliseconds < 1000) {
            return $milliseconds . 'ms';
        }

        $seconds = (int) ($milliseconds / 1000);
        if ($seconds < 60) {
            return $seconds . 's';
        }

        $minutes = (int) ($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return sprintf('%dm %ds', $minutes, $remainingSeconds);
        }

        $hours = (int) ($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return sprintf('%dh %dm %ds', $hours, $remainingMinutes, $remainingSeconds);
    }

    /**
     * Formate le corps de l'email pour un échec de job.
     */
    private function formatFailureEmail(array $job, array $result): string
    {
        $lines = [
            'Document Hub Cron Job Failure Notification',
            str_repeat('=', 60),
            '',
            'Job Code: ' . ($job['code'] ?? 'N/A'),
            'Job Name: ' . ($job['name'] ?? 'N/A'),
            'Scheduled: ' . ($result['scheduled_at'] ?? 'N/A'),
            'Started: ' . ($result['started_at'] ?? 'N/A'),
            'Finished: ' . ($result['finished_at'] ?? 'N/A'),
            'Duration: ' . $this->formatDuration((int) ($result['duration_ms'] ?? 0)),
            'Exit Code: ' . ($result['exit_code'] ?? 'N/A'),
            '',
            'Error Output:',
            str_repeat('-', 60),
            (string) ($result['stderr_text'] ?? ($result['stdout_text'] ?? 'No error output')),
            '',
            'Standard Output:',
            str_repeat('-', 60),
            substr((string) ($result['stdout_text'] ?? ''), 0, 2000),
            '',
            str_repeat('=', 60),
            'Please investigate and fix this issue.',
            'Generated at: ' . date('Y-m-d H:i:s'),
        ];

        return implode("\n", $lines);
    }

    /**
     * Formate le corps de l'email pour le résumé quotidien.
     */
    private function formatDailySummaryEmail(array $summary): string
    {
        $lines = [
            'Document Hub Daily Summary',
            str_repeat('=', 60),
            '',
            'Date: ' . date('Y-m-d'),
            'Total Jobs: ' . ($summary['total_jobs'] ?? 0),
            'Successful: ' . ($summary['successful_jobs'] ?? 0),
            'Failed: ' . ($summary['failed_jobs'] ?? 0),
            'Errors: ' . ($summary['error_count'] ?? 0),
            '',
        ];

        if (!empty($summary['failed_jobs_list'])) {
            $lines[] = 'Failed Jobs:';
            $lines[] = str_repeat('-', 60);
            foreach ($summary['failed_jobs_list'] as $jobCode => $details) {
                $lines[] = sprintf("  - %s: exit code %d", $jobCode, $details['exit_code'] ?? 0);
            }
            $lines[] = '';
        }

        if (!empty($summary['warnings'])) {
            $lines[] = 'Warnings:';
            $lines[] = str_repeat('-', 60);
            foreach ($summary['warnings'] as $warning) {
                $lines[] = '  - ' . $warning;
            }
            $lines[] = '';
        }

        $lines[] = str_repeat('=', 60);

        return implode("\n", $lines);
    }
}
