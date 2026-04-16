<?php

declare(strict_types=1);

namespace Caramagnols\Admin\Settings;

final class AdminLogAlertsSettingsManager
{
    private const ALLOWED_NOTIFY_ON = ['alerts', 'always'];

    public function __construct(private readonly string $defaultNotifyOn = 'alerts')
    {
    }

    /**
     * @param mixed $configuredNotifyOn
     * @return array{notifyOn: string}
     */
    public function configured(mixed $configuredNotifyOn): array
    {
        $notifyOn = $this->normalizeNotifyOn($configuredNotifyOn);

        return [
            'notifyOn' => $notifyOn ?? $this->defaultNotifyOn,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array{notifyOn: string} $fallback
     * @return array{notifyOn: string}
     */
    public function form(array $payload, array $fallback): array
    {
        $fallbackNotifyOn = $this->normalizeNotifyOn($fallback['notifyOn'] ?? null) ?? $this->defaultNotifyOn;
        $notifyOn = $fallbackNotifyOn;

        if (array_key_exists('notifyOn', $payload) && is_scalar($payload['notifyOn'])) {
            $postedNotifyOn = strtolower(trim((string) $payload['notifyOn']));
            $notifyOn = $postedNotifyOn !== '' ? $postedNotifyOn : $fallbackNotifyOn;
        }

        return [
            'notifyOn' => $notifyOn,
        ];
    }

    /**
     * @param array{notifyOn: string} $logAlerts
     * @return array{data: array{notifyOn: string}, error: string|null}
     */
    public function normalizeConfig(array $logAlerts): array
    {
        $notifyOn = $this->normalizeNotifyOn($logAlerts['notifyOn'] ?? null);
        if ($notifyOn === null) {
            return ['data' => [], 'error' => 'Le mode de notification des alertes logs est invalide.'];
        }

        return [
            'data' => [
                'notifyOn' => $notifyOn,
            ],
            'error' => null,
        ];
    }

    private function normalizeNotifyOn(mixed $notifyOn): ?string
    {
        if (!is_scalar($notifyOn)) {
            return null;
        }

        $normalized = strtolower(trim((string) $notifyOn));
        if (!in_array($normalized, self::ALLOWED_NOTIFY_ON, true)) {
            return null;
        }

        return $normalized;
    }
}
