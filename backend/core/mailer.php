<?php
declare(strict_types=1);

use Caramagnols\Mailer\Mailer;

/**
 * Passerelle legacy : envoie un email via Symfony Mailer configure par .env.
 *
 * @param array<int, array{path?: string, content?: string, name?: string, mime?: string}> $attachments
 * @param array<string, mixed>|null $config
 */
function send_notification_email(string $to, string $subject, string $message, array $attachments = [], ?array $config = null): bool
{
    try {
        $mailer = new Mailer($config ?? app_config('mail'));
        $mailer->send($to, $subject, $message, $attachments);
        return true;
    } catch (Throwable $e) {
        error_log('[mailer] ' . $e->getMessage());
        return false;
    }
}

/**
 * Envoi limite a l'espace prive: utilise la configuration SMTP private.mail.
 *
 * @param array<int, array{path?: string, content?: string, name?: string, mime?: string}> $attachments
 */
function send_private_email(string $to, string $subject, string $message, array $attachments = []): bool
{
    $privateMailConfig = app_config('private.mail', []);
    if (!is_array($privateMailConfig) || empty($privateMailConfig['enabled'])) {
        return false;
    }
    if (trim((string) ($privateMailConfig['smtp_user'] ?? '')) !== '' && (string) ($privateMailConfig['smtp_password'] ?? '') === '') {
        return false;
    }

    return send_notification_email($to, $subject, $message, $attachments, $privateMailConfig);
}
