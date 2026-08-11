<?php

declare(strict_types=1);

namespace Caramagnols\SecurityCenter\Posture;

final class PostureNormalizer
{
    /**
     * @param array<string, mixed> $posture
     * @return array{posture_state: string, risk_level: string, summary: array<string, mixed>}
     */
    public function normalize(array $posture): array
    {
        $state = is_string($posture['posture_state'] ?? null) ? strtolower(trim((string) $posture['posture_state'])) : 'unknown';
        if (!in_array($state, ['healthy', 'attention', 'at_risk', 'unknown'], true)) {
            $state = 'unknown';
        }

        $risk = is_string($posture['risk_level'] ?? null) ? strtolower(trim((string) $posture['risk_level'])) : 'unknown';
        if (!in_array($risk, ['low', 'medium', 'high', 'critical', 'unknown'], true)) {
            $risk = 'unknown';
        }

        return [
            'posture_state' => $state,
            'risk_level' => $risk,
            'summary' => is_array($posture['summary'] ?? null) ? $posture['summary'] : [],
        ];
    }
}
