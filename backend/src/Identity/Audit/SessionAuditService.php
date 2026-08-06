<?php

declare(strict_types=1);

namespace Caramagnols\Identity\Audit;

use Caramagnols\Logging\AppEventLogger;

final class SessionAuditService
{
    public function __construct(private readonly ?AppEventLogger $logger = null)
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function security(string $event, array $context = [], string $level = 'info'): void
    {
        $logger = $this->logger ?? (function_exists('app_event_logger') ? app_event_logger() : null);
        if (!$logger instanceof AppEventLogger) {
            return;
        }

        unset($context['token'], $context['secret'], $context['cookie'], $context['password']);
        $logger->security($event, $context, $level);
    }

    public function hashIdentifier(string $identifier): string
    {
        return hash('sha256', strtolower(trim($identifier)));
    }

    public function hashIp(?string $ip): string
    {
        $ip = is_string($ip) ? trim($ip) : '';

        return $ip === '' ? '' : hash('sha256', $ip);
    }

    public function hashUserAgent(?string $userAgent): string
    {
        $userAgent = is_string($userAgent) ? trim($userAgent) : '';

        return $userAgent === '' ? '' : hash('sha256', $userAgent);
    }
}
