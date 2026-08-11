<?php

declare(strict_types=1);

namespace Caramagnols\SecurityCenter\Alert;

final class AlertDeduplicator
{
    public function logicalKey(string $source, string $subjectToken, string $type): string
    {
        $source = $this->normalizePart($source);
        $subjectToken = $this->normalizePart($subjectToken);
        $type = $this->normalizePart($type);

        return hash('sha256', implode('|', [$source, $subjectToken, $type]));
    }

    private function normalizePart(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9._:-]+/', '-', $normalized) ?? '';

        return trim($normalized, '-');
    }
}
