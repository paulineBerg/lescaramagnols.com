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
    return send_notification_email_with_error($to, $subject, $message, $attachments, $config)['sent'];
}

/**
 * @param array<int, array{path?: string, content?: string, name?: string, mime?: string}> $attachments
 * @param array<string, mixed>|null $config
 * @return array{sent: bool, error: string|null}
 */
function send_notification_email_with_error(string $to, string $subject, string $message, array $attachments = [], ?array $config = null): array
{
    try {
        $mailer = new Mailer($config ?? app_config('mail'));
        $mailer->send($to, $subject, $message, $attachments);
        return ['sent' => true, 'error' => null];
    } catch (Throwable $e) {
        $error = sanitize_mailer_error_message($e->getMessage());
        error_log('[mailer] ' . $error);

        return ['sent' => false, 'error' => $error];
    }
}

function sanitize_mailer_error_message(string $message): string
{
    $message = preg_replace('#(smtps?://)([^/@\\s:]+):([^/@\\s]+)@#i', '$1[redacted]:[redacted]@', $message) ?? $message;
    $message = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email redacted]', $message) ?? $message;
    $message = preg_replace('/([?&](?:password|passwd|token|secret|apikey|api_key)=)[^\\s&]+/i', '$1[redacted]', $message) ?? $message;
    $message = preg_replace('/\\b(?:password|passwd|token|secret|apikey|api_key)\\s*[:=]\\s*[^\\s,;]+/i', '[redacted]', $message) ?? $message;

    return trim($message) !== '' ? $message : '[redacted]';
}

/**
 * Envoi limite a l'espace prive: utilise la configuration SMTP private.mail.
 *
 * @param array<int, array{path?: string, content?: string, name?: string, mime?: string}> $attachments
 */
function send_private_email(string $to, string $subject, string $message, array $attachments = []): bool
{
    $privateMailConfig = app_config('private.mail', []);

    return is_array($privateMailConfig)
        ? send_private_email_with_config($to, $subject, $message, $attachments, $privateMailConfig)
        : false;
}

/**
 * Envoi SMTP prive avec une configuration fournie explicitement.
 *
 * @param array<int, array{path?: string, content?: string, name?: string, mime?: string}> $attachments
 * @param array<string, mixed> $privateMailConfig
 */
function send_private_email_with_config(
    string $to,
    string $subject,
    string $message,
    array $attachments,
    array $privateMailConfig
): bool {
    private_mail_set_last_error(null);
    if (!is_array($privateMailConfig) || empty($privateMailConfig['enabled'])) {
        private_mail_set_last_error('private mail disabled');
        return false;
    }
    $configuredTransport = strtolower(trim((string) ($privateMailConfig['transport'] ?? 'smtp')));
    $isConfiguredLocalTransport = in_array($configuredTransport, ['native', 'sendmail'], true);
    if (
        !$isConfiguredLocalTransport
        && trim((string) ($privateMailConfig['smtp_user'] ?? '')) !== ''
        && (string) ($privateMailConfig['smtp_password'] ?? '') === ''
    ) {
        private_mail_set_last_error('private mail smtp password missing');
        return false;
    }

    $networkFallbackAllowed = $isConfiguredLocalTransport;
    foreach (private_mail_delivery_configs($privateMailConfig) as $mailConfig) {
        $isLocalTransport = in_array((string) ($mailConfig['transport'] ?? ''), ['native', 'sendmail'], true);
        if ($isLocalTransport && !$networkFallbackAllowed) {
            return false;
        }

        if ((string) ($mailConfig['transport'] ?? '') === 'native' && $attachments === []) {
            $sent = send_private_email_with_php_mail($to, $subject, $message, $mailConfig);
            if ($sent) {
                private_mail_set_last_error(null);
                return true;
            }

            private_mail_set_last_error('php mail returned false');
            continue;
        }

        $result = send_notification_email_with_error($to, $subject, $message, $attachments, $mailConfig);
        if ($result['sent']) {
            private_mail_set_last_error(null);
            return true;
        }
        private_mail_set_last_error($result['error']);

        if ($isLocalTransport) {
            continue;
        }

        if (!private_mail_error_allows_transport_fallback((string) ($result['error'] ?? ''))) {
            return false;
        }

        $networkFallbackAllowed = true;
    }

    return false;
}

/**
 * Envoi direct via mail() pour les hebergements mutualises qui refusent SMTP et sendmail explicite.
 *
 * @param array<string, mixed> $config
 */
function send_private_email_with_php_mail(string $to, string $subject, string $message, array $config): bool
{
    if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }

    $from = trim((string) ($config['from_address'] ?? ''));
    if ($from === '' || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }

    $fromName = trim((string) ($config['from_name'] ?? 'Les Caramagnols'));
    $replyTo = trim((string) ($config['reply_to'] ?? ''));
    $encodedSubject = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($subject, 'UTF-8')
        : '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedFromName = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($fromName, 'UTF-8')
        : '=?UTF-8?B?' . base64_encode($fromName) . '?=';

    $headers = [
        sprintf('From: %s <%s>', $encodedFromName, $from),
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    return mail($to, $encodedSubject, $message, implode("\r\n", $headers));
}

function private_mail_set_last_error(?string $error): void
{
    $GLOBALS['private_mail_last_error'] = $error !== null && trim($error) !== '' ? trim($error) : null;
}

function private_mail_last_error(): ?string
{
    return is_string($GLOBALS['private_mail_last_error'] ?? null) ? $GLOBALS['private_mail_last_error'] : null;
}

/**
 * @param array<string, mixed> $config
 * @return array<int, array<string, mixed>>
 */
function private_mail_delivery_configs(array $config): array
{
    $configs = [$config];
    $host = strtolower(trim((string) ($config['smtp_host'] ?? '')));
    $port = (int) ($config['smtp_port'] ?? 0);
    $encryption = strtolower(trim((string) ($config['smtp_encryption'] ?? '')));

    if ($host === 'ssl0.ovh.net' && $port === 465 && $encryption === 'ssl') {
        $fallback = $config;
        $fallback['smtp_port'] = 587;
        $fallback['smtp_encryption'] = 'tls';
        $configs[] = $fallback;
    }

    if ($host === 'ssl0.ovh.net' && in_array($port, [465, 587], true)) {
        $fallback = $config;
        $fallback['transport'] = 'native';
        $fallback['smtp_user'] = '';
        $fallback['smtp_password'] = '';
        $configs[] = $fallback;

        $fallback = $config;
        $fallback['transport'] = 'sendmail';
        $fallback['smtp_user'] = '';
        $fallback['smtp_password'] = '';
        $configs[] = $fallback;
    }

    return $configs;
}

function private_mail_error_allows_transport_fallback(string $error): bool
{
    $error = strtolower($error);

    foreach (['connection refused', 'timed out', 'unable to connect', 'could not be established with host'] as $needle) {
        if (str_contains($error, $needle)) {
            return true;
        }
    }

    return false;
}
