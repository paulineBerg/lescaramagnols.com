<?php

declare(strict_types=1);

namespace Caramagnols\Logging;

final class PrivateSecurityAlertReportBuilder
{
    /**
     * @param array<int, array<string, mixed>> $entries
     * @param array<string, int> $thresholds
     * @return array{generated_at:string,since_minutes:int,counts:array<string,int>,thresholds:array<string,int>,alerts:array<int,array{metric:string,count:int,threshold:int}>}
     */
    public function build(array $entries, int $sinceMinutes = 60, array $thresholds = []): array
    {
        $thresholds = array_merge([
            'login_failed' => 10,
            'http_403' => 30,
            'http_429' => 10,
            'http_5xx' => 3,
        ], $thresholds);
        $counts = ['login_failed' => 0, 'http_403' => 0, 'http_429' => 0, 'http_5xx' => 0];

        foreach ($entries as $entry) {
            $event = strtolower((string) ($entry['event'] ?? ''));
            $context = is_array($entry['context'] ?? null) ? $entry['context'] : [];
            $status = is_numeric($context['status'] ?? null) ? (int) $context['status'] : 0;
            if (str_contains($event, 'login') && (str_contains($event, 'failed') || str_contains($event, 'rejected'))) {
                ++$counts['login_failed'];
            }
            if ($status === 403 || str_contains($event, '403') || str_contains($event, 'access_denied')) {
                ++$counts['http_403'];
            }
            if ($status === 429 || str_contains($event, '429') || str_contains($event, 'rate_limit')) {
                ++$counts['http_429'];
            }
            if ($status >= 500 || str_contains($event, '5xx') || str_contains($event, 'server_error')) {
                ++$counts['http_5xx'];
            }
        }

        $alerts = [];
        foreach ($counts as $metric => $count) {
            $threshold = $thresholds[$metric] ?? 1;
            if ($count >= $threshold) {
                $alerts[] = ['metric' => $metric, 'count' => $count, 'threshold' => $threshold];
            }
        }

        return [
            'generated_at' => date('c'),
            'since_minutes' => max(1, $sinceMinutes),
            'counts' => $counts,
            'thresholds' => $thresholds,
            'alerts' => $alerts,
        ];
    }
}
