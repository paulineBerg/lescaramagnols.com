<?php

declare(strict_types=1);

namespace Caramagnols\Logging;

use Caramagnols\Database\EditorialDatabase;
use DateTimeImmutable;
use DateTimeInterface;
use PDO;

final class SqlLogStore
{
    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function insert(
        string $channel,
        string $level,
        string $event,
        array $context = [],
        ?DateTimeInterface $createdAt = null
    ): bool {
        try {
            $this->database->ensureReady();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s` (`channel`, `level`, `event`, `context_json`, `created_at`)
                     VALUES (:channel, :level, :event, :context_json, :created_at)',
                    $this->database->table('log_entries')
                )
            );

            $statement->execute([
                'channel' => $this->sanitizeChannel($channel),
                'level' => $this->sanitizeLevel($level),
                'event' => $this->trimText($event, 191),
                'context_json' => $context === [] ? null : $this->encodeJson($context),
                'created_at' => ($createdAt ?? new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function isAvailable(): bool
    {
        try {
            $this->database->ensureReady();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array{id: int, channel: string, level: string, event: string, context: array<string, mixed>, contextJson: string, createdAt: string}>
     */
    public function listEntries(array $filters = [], int $limit = 200): array
    {
        try {
            $this->database->ensureReady();
            $query = $this->buildFilterQuery($filters);
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT `id`, `channel`, `level`, `event`, `context_json`, `created_at`
                     FROM `%s`%s
                     ORDER BY `created_at` DESC, `id` DESC
                     LIMIT %d',
                    $this->database->table('log_entries'),
                    $query['where'],
                    min(500, max(1, $limit))
                )
            );
            $statement->execute($query['params']);

            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows)) {
                return [];
            }

            return array_map(function (array $row): array {
                $context = $this->decodeJson(is_string($row['context_json'] ?? null) ? $row['context_json'] : null);

                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'channel' => (string) ($row['channel'] ?? ''),
                    'level' => (string) ($row['level'] ?? ''),
                    'event' => (string) ($row['event'] ?? ''),
                    'context' => $context,
                    'contextJson' => $context === [] ? '' : $this->encodeJson($context, true),
                    'createdAt' => (string) ($row['created_at'] ?? ''),
                ];
            }, $rows);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function countEntries(array $filters = []): int
    {
        try {
            $this->database->ensureReady();
            $query = $this->buildFilterQuery($filters);
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT COUNT(*) FROM `%s`%s',
                    $this->database->table('log_entries'),
                    $query['where']
                )
            );
            $statement->execute($query['params']);
            $count = $statement->fetchColumn();

            return is_numeric($count) ? (int) $count : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return array<int, string>
     */
    public function listChannels(): array
    {
        return $this->distinctValues('channel');
    }

    /**
     * @return array<int, string>
     */
    public function listLevels(): array
    {
        return $this->distinctValues('level');
    }

    /**
     * @param array<int, int|string> $ids
     */
    public function deleteByIds(array $ids): int
    {
        $normalizedIds = array_values(
            array_unique(
                array_filter(
                    array_map(static fn (int|string $id): int => (int) $id, $ids),
                    static fn (int $id): bool => $id > 0
                )
            )
        );

        if ($normalizedIds === []) {
            return 0;
        }

        try {
            $this->database->ensureReady();
            $placeholders = implode(', ', array_fill(0, count($normalizedIds), '?'));
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'DELETE FROM `%s` WHERE `id` IN (%s)',
                    $this->database->table('log_entries'),
                    $placeholders
                )
            );
            $statement->execute($normalizedIds);

            return $statement->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function deleteByFilters(array $filters): int
    {
        try {
            $this->database->ensureReady();
            $query = $this->buildFilterQuery($filters);
            if ($query['where'] === '') {
                return 0;
            }

            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'DELETE FROM `%s`%s',
                    $this->database->table('log_entries'),
                    $query['where']
                )
            );
            $statement->execute($query['params']);

            return $statement->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return array<int, string>
     */
    private function distinctValues(string $column): array
    {
        try {
            $this->database->ensureReady();
            $statement = $this->database->pdo()->query(
                sprintf(
                    'SELECT DISTINCT `%1$s` FROM `%2$s` WHERE `%1$s` <> \'\' ORDER BY `%1$s` ASC',
                    $column,
                    $this->database->table('log_entries')
                )
            );
            $values = $statement === false ? [] : $statement->fetchAll(PDO::FETCH_COLUMN);
            if (!is_array($values)) {
                return [];
            }

            return array_values(array_map('strval', $values));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{where: string, params: array<string, mixed>}
     */
    private function buildFilterQuery(array $filters): array
    {
        $where = [];
        $params = [];

        $channel = $this->sanitizeChannel((string) ($filters['channel'] ?? ''));
        if ($channel !== '') {
            $where[] = '`channel` = :channel';
            $params['channel'] = $channel;
        }

        $level = $this->sanitizeLevel((string) ($filters['level'] ?? ''));
        if ($level !== '') {
            $where[] = '`level` = :level';
            $params['level'] = $level;
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $searchValue = '%' . $this->trimText($search, 120) . '%';
            $where[] = '(`event` LIKE :search_event OR `context_json` LIKE :search_context)';
            $params['search_event'] = $searchValue;
            $params['search_context'] = $searchValue;
        }

        $dateFrom = $this->normalizeDateBoundary($filters['date_from'] ?? null, false);
        if ($dateFrom !== null) {
            $where[] = '`created_at` >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $dateTo = $this->normalizeDateBoundary($filters['date_to'] ?? null, true);
        if ($dateTo !== null) {
            $where[] = '`created_at` <= :date_to';
            $params['date_to'] = $dateTo;
        }

        return [
            'where' => $where === [] ? '' : ' WHERE ' . implode(' AND ', $where),
            'params' => $params,
        ];
    }

    private function sanitizeChannel(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_-]+/', '', $value) ?? '';

        return $this->trimText($value, 32);
    }

    private function sanitizeLevel(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z]+/', '', $value) ?? '';

        return $this->trimText($value, 16);
    }

    private function trimText(string $value, int $maxLength): string
    {
        $value = trim($value);

        return function_exists('mb_substr')
            ? mb_substr($value, 0, $maxLength)
            : substr($value, 0, $maxLength);
    }

    private function normalizeDateBoundary(mixed $value, bool $endOfDay): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        if (!$date instanceof DateTimeImmutable) {
            return null;
        }

        if ($endOfDay) {
            $date = $date->setTime(23, 59, 59);
        }

        return $date->format('Y-m-d H:i:s');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodeJson(array $payload, bool $pretty = false): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return (string) json_encode($payload, $flags);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
