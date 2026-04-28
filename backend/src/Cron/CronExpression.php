<?php

declare(strict_types=1);

namespace Caramagnols\Cron;

use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;

final class CronExpression
{
    /** @var array<int, array<int, int>> */
    private array $fields;

    private function __construct(
        private readonly string $expression,
        private readonly bool $dayOfMonthWildcard,
        private readonly bool $dayOfWeekWildcard,
        array $fields
    ) {
        $this->fields = $fields;
    }

    public static function parse(string $expression): self
    {
        $normalized = preg_replace('/\s+/', ' ', trim($expression)) ?? '';
        $parts = explode(' ', $normalized);
        if (count($parts) !== 5) {
            throw new RuntimeException('Expression cron invalide: 5 champs sont attendus.');
        }

        $dayOfMonthWildcard = trim($parts[2]) === '*';
        $dayOfWeekWildcard = trim($parts[4]) === '*';

        return new self(
            $normalized,
            $dayOfMonthWildcard,
            $dayOfWeekWildcard,
            [
                0 => self::parseField($parts[0], 0, 59, false),
                1 => self::parseField($parts[1], 0, 23, false),
                2 => self::parseField($parts[2], 1, 31, false),
                3 => self::parseField($parts[3], 1, 12, false),
                4 => self::parseField($parts[4], 0, 7, true),
            ]
        );
    }

    public static function isValid(string $expression): bool
    {
        try {
            self::parse($expression);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function expression(): string
    {
        return $this->expression;
    }

    public function matches(DateTimeInterface $date): bool
    {
        $minute = (int) $date->format('i');
        $hour = (int) $date->format('G');
        $dayOfMonth = (int) $date->format('j');
        $month = (int) $date->format('n');
        $dayOfWeek = (int) $date->format('w');

        if (
            !in_array($minute, $this->fields[0], true)
            || !in_array($hour, $this->fields[1], true)
            || !in_array($month, $this->fields[3], true)
        ) {
            return false;
        }

        $matchesDayOfMonth = in_array($dayOfMonth, $this->fields[2], true);
        $matchesDayOfWeek = in_array($dayOfWeek, $this->fields[4], true);

        if (!$this->dayOfMonthWildcard && !$this->dayOfWeekWildcard) {
            return $matchesDayOfMonth || $matchesDayOfWeek;
        }

        return $matchesDayOfMonth && $matchesDayOfWeek;
    }

    public function previousRunBeforeOrAt(DateTimeImmutable $date): ?DateTimeImmutable
    {
        $candidate = $date->setTime((int) $date->format('H'), (int) $date->format('i'), 0);
        for ($i = 0; $i <= 525600; $i++) {
            if ($this->matches($candidate)) {
                return $candidate;
            }

            $candidate = $candidate->modify('-1 minute');
        }

        return null;
    }

    public function nextRunAfter(DateTimeImmutable $date): ?DateTimeImmutable
    {
        $candidate = $date
            ->setTime((int) $date->format('H'), (int) $date->format('i'), 0)
            ->modify('+1 minute');

        for ($i = 0; $i <= 525600; $i++) {
            if ($this->matches($candidate)) {
                return $candidate;
            }

            $candidate = $candidate->modify('+1 minute');
        }

        return null;
    }

    public function humanSummary(): string
    {
        $parts = explode(' ', $this->expression);
        if (($parts[0] ?? '') === '*/5' && ($parts[1] ?? '') === '*' && ($parts[2] ?? '') === '*' && ($parts[3] ?? '') === '*' && ($parts[4] ?? '') === '*') {
            return 'Toutes les 5 minutes';
        }

        if (($parts[0] ?? '') === '*/15' && ($parts[1] ?? '') === '*' && ($parts[2] ?? '') === '*' && ($parts[3] ?? '') === '*' && ($parts[4] ?? '') === '*') {
            return 'Toutes les 15 minutes';
        }

        if (($parts[2] ?? '') === '*' && ($parts[3] ?? '') === '*' && ($parts[4] ?? '') === '*') {
            return sprintf('Tous les jours à %02d:%02d', (int) ($parts[1] ?? 0), (int) ($parts[0] ?? 0));
        }

        return $this->expression;
    }

    /**
     * @return array<int, int>
     */
    private static function parseField(string $field, int $min, int $max, bool $weekday): array
    {
        $field = trim($field);
        if ($field === '') {
            throw new RuntimeException('Champ cron vide.');
        }

        $values = [];
        foreach (explode(',', $field) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                throw new RuntimeException('Segment cron vide.');
            }

            foreach (self::parseSegment($segment, $min, $max, $weekday) as $value) {
                if ($weekday && $value === 7) {
                    $value = 0;
                }

                $values[$value] = $value;
            }
        }

        if ($values === []) {
            throw new RuntimeException('Champ cron sans valeur.');
        }

        ksort($values);

        return array_values($values);
    }

    /**
     * @return array<int, int>
     */
    private static function parseSegment(string $segment, int $min, int $max, bool $weekday): array
    {
        $step = 1;
        $range = $segment;

        if (str_contains($segment, '/')) {
            [$range, $rawStep] = array_pad(explode('/', $segment, 2), 2, '');
            if (!ctype_digit($rawStep) || (int) $rawStep < 1) {
                throw new RuntimeException('Pas cron invalide.');
            }

            $step = (int) $rawStep;
        }

        if ($range === '*') {
            $start = $min;
            $end = $weekday ? 6 : $max;
            $values = range($start, $end);
        } elseif (str_contains($range, '-')) {
            [$rawStart, $rawEnd] = array_pad(explode('-', $range, 2), 2, '');
            if (!ctype_digit($rawStart) || !ctype_digit($rawEnd)) {
                throw new RuntimeException('Plage cron invalide.');
            }

            $start = (int) $rawStart;
            $end = (int) $rawEnd;
            if ($start < $min || $start > $max || $end < $min || $end > $max) {
                throw new RuntimeException('Valeur cron hors limites.');
            }

            if ($weekday) {
                $normalizedStart = $start === 7 ? 0 : $start;
                $normalizedEnd = $end === 7 ? 0 : $end;
                if ($start === $end) {
                    $values = [$normalizedStart];
                } elseif ($start <= $end && $end !== 7) {
                    $values = range($normalizedStart, $normalizedEnd);
                } elseif ($start <= $end && $end === 7) {
                    $values = array_merge(range($normalizedStart, 6), [0]);
                } else {
                    $values = array_merge(range($normalizedStart, 6), range(0, $normalizedEnd));
                }
            } else {
                if ($start > $end) {
                    throw new RuntimeException('Valeur cron hors limites.');
                }

                $values = range($start, $end);
            }
        } elseif (ctype_digit($range)) {
            $start = (int) $range;
            if ($start < $min || $start > $max) {
                throw new RuntimeException('Valeur cron hors limites.');
            }

            $values = [$weekday && $start === 7 ? 0 : $start];
        } else {
            throw new RuntimeException('Valeur cron invalide.');
        }

        $selected = [];
        foreach (array_values($values) as $index => $value) {
            if ($index % $step === 0) {
                $selected[] = $value;
            }
        }

        return $selected;
    }
}
