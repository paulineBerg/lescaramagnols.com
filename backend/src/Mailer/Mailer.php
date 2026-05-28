<?php

declare(strict_types=1);

namespace Caramagnols\Mailer;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class Mailer
{
    private MailerInterface $mailer;
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $dsn = $this->buildDsn($config);
        $this->mailer = new \Symfony\Component\Mailer\Mailer(Transport::fromDsn($dsn));
    }

    /**
     * @param array<int, array{path?: string, content?: string, name?: string, mime?: string}> $attachments
     */
    public function send(string $to, string $subject, string $html, array $attachments = [], ?string $text = null): void
    {
        $email = (new Email())
            ->from($this->fromAddress())
            ->to($to)
            ->subject($subject);

        $replyTo = $this->replyToAddress();
        if ($replyTo !== null) {
            $email->replyTo($replyTo);
        }

        if ($text !== null && trim($text) !== '') {
            $email->text($text);
        }

        $email->html($html);

        foreach ($attachments as $attachment) {
            $name = isset($attachment['name']) ? trim((string) $attachment['name']) : null;
            $mime = isset($attachment['mime']) ? trim((string) $attachment['mime']) : null;
            $path = isset($attachment['path']) ? trim((string) $attachment['path']) : '';
            if ($path !== '') {
                $email->attachFromPath($path, $name !== '' ? $name : null, $mime !== '' ? $mime : null);
                continue;
            }

            $content = isset($attachment['content']) ? (string) $attachment['content'] : '';
            if ($content !== '') {
                $email->attach($content, $name !== '' ? $name : 'document', $mime !== '' ? $mime : null);
            }
        }

        $this->mailer->send($email);
    }

    private function buildDsn(array $config): string
    {
        $host = $config['smtp_host'] ?? 'localhost';
        $port = $config['smtp_port'] ?? 25;
        $user = $config['smtp_user'] ?? '';
        $password = $config['smtp_password'] ?? '';
        $encryption = $config['smtp_encryption'] ?? '';

        $scheme = match ($encryption) {
            'tls', 'starttls' => 'smtp',
            'ssl' => 'smtps',
            default => 'smtp'
        };

        if ($user !== '' && $password !== '') {
            return sprintf('%s://%s:%s@%s:%d', $scheme, rawurlencode($user), rawurlencode($password), $host, $port);
        }

        return sprintf('%s://%s:%d', $scheme, $host, $port);
    }

    private function fromAddress(): Address
    {
        $from = trim((string) ($this->config['from_address'] ?? ($_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@example.com')));
        $name = trim((string) ($this->config['from_name'] ?? ($_ENV['MAIL_FROM_NAME'] ?? 'Les Caramagnols')));

        return new Address($from !== '' ? $from : 'no-reply@example.com', $name !== '' ? $name : 'Les Caramagnols');
    }

    private function replyToAddress(): ?Address
    {
        $replyTo = trim((string) ($this->config['reply_to'] ?? ''));
        if ($replyTo === '' || filter_var($replyTo, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return new Address($replyTo);
    }
}
