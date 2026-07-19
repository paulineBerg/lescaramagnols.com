<?php

declare(strict_types=1);

namespace Caramagnols\Logging;

use Monolog\Logger;

final class AppEventLogger
{
    private const DEFAULT_SCHEMA_VERSION = 2;

    /** @var array<int, string> */
    private const PSR_LEVELS = [
        'debug',
        'info',
        'notice',
        'warning',
        'error',
        'critical',
        'alert',
        'emergency',
    ];

    /** @var array<string, Logger> */
    private array $channels = [];

    private readonly LogSanitizer $sanitizer;

    public function __construct(private readonly LoggerFactory $factory, ?LogSanitizer $sanitizer = null)
    {
        $this->sanitizer = $sanitizer ?? new LogSanitizer();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function security(string $event, array $context = [], string $level = 'info'): void
    {
        $this->write('security', $event, $context, $level);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function content(string $event, array $context = [], string $level = 'info'): void
    {
        $this->write('content', $event, $context, $level);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function access(string $event, array $context = [], string $level = 'info'): void
    {
        $this->write('access', $event, $context, $level);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $stream, string $event, array $context = [], string $level = 'info'): void
    {
        $this->write($this->normalizeStream($stream), $event, $context, $level);
    }

    public static function maskEmail(?string $email): string
    {
        if (!is_string($email) || $email === '' || !str_contains($email, '@')) {
            return '';
        }

        [$local, $domain] = explode('@', $email, 2);
        $firstLetter = function_exists('mb_substr') ? mb_substr($local, 0, 1) : substr($local, 0, 1);
        $length = function_exists('mb_strlen') ? mb_strlen($local) : strlen($local);
        $masked = $firstLetter . str_repeat('*', max(0, $length - 1));

        return $masked . '@' . $domain;
    }

    public static function maskIdentifier(?string $identifier): string
    {
        if (!is_string($identifier)) {
            return '';
        }

        $identifier = trim($identifier);
        if ($identifier === '') {
            return '';
        }

        if (str_contains($identifier, '@')) {
            return self::maskEmail($identifier);
        }

        $length = function_exists('mb_strlen') ? mb_strlen($identifier) : strlen($identifier);
        if ($length <= 2) {
            $head = function_exists('mb_substr') ? mb_substr($identifier, 0, 1) : substr($identifier, 0, 1);

            return $head . '*';
        }

        $head = function_exists('mb_substr') ? mb_substr($identifier, 0, 2) : substr($identifier, 0, 2);
        $tail = function_exists('mb_substr') ? mb_substr($identifier, -1) : substr($identifier, -1);

        return $head . str_repeat('*', max(1, $length - 3)) . $tail;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function write(string $channel, string $event, array $context, string $level): void
    {
        try {
            $normalizedLevel = $this->normalizeLevel($level);
            $normalizedEvent = $this->normalizeEvent($event);
            $logger = $this->logger($channel);
            $requestContext = function_exists('app_request_context_get') ? app_request_context_get() : [];
            $mergedContext = array_merge(
                [
                    'schema_version' => self::DEFAULT_SCHEMA_VERSION,
                    'stream' => $this->streamForChannel($channel),
                    'environment' => function_exists('app_config') ? (string) app_config('env', 'development') : 'development',
                ],
                is_array($requestContext) ? $requestContext : [],
                $context
            );
            $normalizedContext = $this->normalizeContext($mergedContext, $normalizedEvent);

            match ($normalizedLevel) {
                'debug' => $logger->debug($normalizedEvent, $normalizedContext),
                'info' => $logger->info($normalizedEvent, $normalizedContext),
                'notice' => $logger->notice($normalizedEvent, $normalizedContext),
                'warning' => $logger->warning($normalizedEvent, $normalizedContext),
                'error' => $logger->error($normalizedEvent, $normalizedContext),
                'critical' => $logger->critical($normalizedEvent, $normalizedContext),
                'alert' => $logger->alert($normalizedEvent, $normalizedContext),
                'emergency' => $logger->emergency($normalizedEvent, $normalizedContext),
                default => $logger->info($normalizedEvent, $normalizedContext),
            };
        } catch (\Throwable) {
            // Le logging ne doit jamais casser l'application.
        }
    }

    private function logger(string $channel): Logger
    {
        if (!isset($this->channels[$channel])) {
            $this->channels[$channel] = $this->factory->create($channel);
        }

        return $this->channels[$channel];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context, string $event): array
    {
        $normalized = $this->sanitizer->sanitizeContext($context);

        if (!isset($normalized['error_fingerprint'])) {
            $fingerprint = $this->errorFingerprint($event, $normalized);
            if ($fingerprint !== null) {
                $normalized['error_fingerprint'] = $fingerprint;
            }
        }

        return $normalized;
    }

    private function normalizeLevel(string $level): string
    {
        $level = strtolower(trim($level));

        return in_array($level, self::PSR_LEVELS, true) ? $level : 'info';
    }

    private function normalizeStream(string $stream): string
    {
        $stream = strtolower(trim($stream));
        $stream = preg_replace('/[^a-z0-9_-]+/', '_', $stream) ?? '';
        $stream = trim($stream, '_-');

        return $stream !== '' ? $stream : 'application';
    }

    private function normalizeEvent(string $event): string
    {
        $event = strtolower($this->sanitizer->sanitizeText($event, 191));
        $event = preg_replace('/[^a-z0-9_.-]+/', '.', $event) ?? '';
        $event = trim($event, '.-');

        return $event !== '' ? $event : 'application.event';
    }

    private function streamForChannel(string $channel): string
    {
        return match ($channel) {
            'security' => 'security',
            'content' => 'audit',
            'access' => 'application',
            default => $this->normalizeStream($channel),
        };
    }

    /**
     * @param array<string, mixed> $context
     */
    private function errorFingerprint(string $event, array $context): ?string
    {
        $errorClass = is_string($context['error_class'] ?? null)
            ? (string) $context['error_class']
            : (is_string($context['exception'] ?? null) ? (string) $context['exception'] : '');

        if ($errorClass === '' && !str_contains($event, 'failed') && !str_contains($event, 'error')) {
            return null;
        }

        $parts = [
            $event,
            $errorClass,
            is_scalar($context['error_code'] ?? null) ? (string) $context['error_code'] : '',
            is_scalar($context['module'] ?? null) ? (string) $context['module'] : '',
            is_scalar($context['operation'] ?? null) ? (string) $context['operation'] : '',
        ];

        return substr(hash('sha256', implode('|', $parts)), 0, 32);
    }
}
