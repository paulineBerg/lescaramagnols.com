<?php

declare(strict_types=1);

namespace Caramagnols\Logging;

use Monolog\Logger;

final class AppEventLogger
{
    /** @var array<string, Logger> */
    private array $channels = [];

    public function __construct(private readonly LoggerFactory $factory)
    {
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
            $logger = $this->logger($channel);
            $requestContext = function_exists('app_request_context_get') ? app_request_context_get() : [];
            $mergedContext = array_merge(is_array($requestContext) ? $requestContext : [], $context);
            $normalizedContext = $this->normalizeContext($mergedContext);

            match ($level) {
                'debug' => $logger->debug($event, $normalizedContext),
                'warning' => $logger->warning($event, $normalizedContext),
                'error' => $logger->error($event, $normalizedContext),
                default => $logger->info($event, $normalizedContext),
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
    private function normalizeContext(array $context): array
    {
        $normalized = [];

        foreach ($context as $key => $value) {
            $normalized[$key] = $this->normalizeValue($value);
        }

        return $normalized;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_null($value) || is_scalar($value)) {
            if (is_string($value)) {
                return function_exists('mb_substr') ? mb_substr($value, 0, 500) : substr($value, 0, 500);
            }

            return $value;
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $itemKey => $itemValue) {
                $normalized[(string) $itemKey] = $this->normalizeValue($itemValue);
            }

            return $normalized;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        if (is_object($value)) {
            return $value::class;
        }

        return gettype($value);
    }
}
