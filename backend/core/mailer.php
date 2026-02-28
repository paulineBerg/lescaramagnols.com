<?php
declare(strict_types=1);

use Caramagnols\Mailer\Mailer;

/**
 * Passerelle legacy : envoie un email via Symfony Mailer configuré par .env.
 */
function send_notification_email(string $to, string $subject, string $message): bool
{
    try {
        $mailer = new Mailer(app_config('mail'));
        $mailer->send($to, $subject, $message);
        return true;
    } catch (Throwable $e) {
        error_log('[mailer] ' . $e->getMessage());
        return false;
    }
}
