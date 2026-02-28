<?php

declare(strict_types=1);

namespace Caramagnols\Mailer;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class Mailer
{
    private MailerInterface $mailer;

    public function __construct(array $config)
    {
        $dsn = $this->buildDsn($config);
        $this->mailer = new \Symfony\Component\Mailer\Mailer(Transport::fromDsn($dsn));
    }

    public function send(string $to, string $subject, string $html): void
    {
        $email = (new Email())
            ->from($this->fromAddress())
            ->to($to)
            ->subject($subject)
            ->html($html);

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
            return sprintf('%s://%s:%s@%s:%d', $scheme, $user, $password, $host, $port);
        }

        return sprintf('%s://%s:%d', $scheme, $host, $port);
    }

    private function fromAddress(): string
    {
        $from = $_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@example.com';
        $name = $_ENV['MAIL_FROM_NAME'] ?? 'Les Caramagnols';
        return sprintf('%s <%s>', $name, $from);
    }
}
