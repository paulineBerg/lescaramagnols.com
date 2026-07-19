<?php

declare(strict_types=1);

namespace Caramagnols\Logging;

use Caramagnols\Database\EditorialDatabase;
use DateTimeImmutable;
use DateTimeInterface;
use PDO;

final class SqlLogStore
{
    /** @var array<int, string> */
    private const SENSITIVE_CHANNELS = ['security'];

    /** @var array<int, string> */
    private const SENSITIVE_LEVELS = ['warning', 'error', 'critical', 'alert', 'emergency'];

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
            $sanitizer = new LogSanitizer();
            $context = $sanitizer->sanitizeContext($context);
            $createdAt ??= new DateTimeImmutable();
            $structured = $this->structuredColumns($channel, $level, $context);
            $this->database->ensureReady();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s` (
                        `channel`, `level`, `event`, `context_json`, `created_at`, `occurred_at`,
                        `stream`, `application`, `module`, `request_id`, `correlation_id`,
                        `error_class`, `error_fingerprint`, `http_status`, `duration_ms`,
                        `actor_type`, `actor_id`, `entity_type`, `entity_id`, `schema_version`
                     ) VALUES (
                        :channel, :level, :event, :context_json, :created_at, :occurred_at,
                        :stream, :application, :module, :request_id, :correlation_id,
                        :error_class, :error_fingerprint, :http_status, :duration_ms,
                        :actor_type, :actor_id, :entity_type, :entity_id, :schema_version
                     )',
                    $this->database->table('log_entries')
                )
            );

            $statement->execute([
                'channel' => $this->sanitizeChannel($channel),
                'level' => $this->sanitizeLevel($level),
                'event' => $this->trimText($event, 191),
                'context_json' => $context === [] ? null : $this->encodeJson($context),
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
                'occurred_at' => $createdAt->format('Y-m-d H:i:s'),
                'stream' => $structured['stream'],
                'application' => $structured['application'],
                'module' => $structured['module'],
                'request_id' => $structured['request_id'],
                'correlation_id' => $structured['correlation_id'],
                'error_class' => $structured['error_class'],
                'error_fingerprint' => $structured['error_fingerprint'],
                'http_status' => $structured['http_status'],
                'duration_ms' => $structured['duration_ms'],
                'actor_type' => $structured['actor_type'],
                'actor_id' => $structured['actor_id'],
                'entity_type' => $structured['entity_type'],
                'entity_id' => $structured['entity_id'],
                'schema_version' => $structured['schema_version'],
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
     * @return array<int, array{id: int, channel: string, level: string, event: string, context: array<string, mixed>, contextJson: string, createdAt: string, stream: string, application: string, module: string, requestId: string, correlationId: string, errorFingerprint: string}>
     */
    public function listEntries(array $filters = [], int $limit = 200, int $offset = 0): array
    {
        try {
            $this->database->ensureReady();
            $columns = $this->tableColumns();
            $query = $this->buildFilterQuery($filters, $columns);
            $normalizedLimit = min(500, max(1, $limit));
            $normalizedOffset = max(0, $offset);
            $orderColumn = in_array('occurred_at', $columns, true) ? 'occurred_at' : 'created_at';
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT `id`, `channel`, `level`, `event`, `context_json`, `created_at`,
                        %s AS `stream`, %s AS `application`, %s AS `module`,
                        %s AS `request_id`, %s AS `correlation_id`, %s AS `error_fingerprint`
                     FROM `%s`%s
                     ORDER BY `%s` DESC, `id` DESC
                     LIMIT %d OFFSET %d',
                    $this->selectColumnOrEmpty($columns, 'stream'),
                    $this->selectColumnOrEmpty($columns, 'application'),
                    $this->selectColumnOrEmpty($columns, 'module'),
                    $this->selectColumnOrEmpty($columns, 'request_id'),
                    $this->selectColumnOrEmpty($columns, 'correlation_id'),
                    $this->selectColumnOrEmpty($columns, 'error_fingerprint'),
                    $this->database->table('log_entries'),
                    $query['where'],
                    $orderColumn,
                    $normalizedLimit,
                    $normalizedOffset
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
                    'stream' => (string) ($row['stream'] ?? ''),
                    'application' => (string) ($row['application'] ?? ''),
                    'module' => (string) ($row['module'] ?? ''),
                    'requestId' => (string) ($row['request_id'] ?? ''),
                    'correlationId' => (string) ($row['correlation_id'] ?? ''),
                    'errorFingerprint' => (string) ($row['error_fingerprint'] ?? ''),
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
            $query = $this->buildFilterQuery($filters, $this->tableColumns());
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
            $query = $this->buildFilterQuery($filters, $this->tableColumns());
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
     * @return array{
     *     deleted: int,
     *     regularDeleted: int,
     *     sensitiveDeleted: int,
     *     regularMatched: int,
     *     sensitiveMatched: int,
     *     retentionDays: int,
     *     sensitiveRetentionDays: int,
     *     cutoff: string,
     *     sensitiveCutoff: string,
     *     dryRun: bool
     * }
     */
    public function purgeOlderThan(
        int $retentionDays,
        int $sensitiveRetentionDays,
        bool $dryRun = false,
        ?DateTimeInterface $now = null
    ): array {
        $retentionDays = $this->normalizeRetentionDays($retentionDays);
        $sensitiveRetentionDays = max($retentionDays, $this->normalizeRetentionDays($sensitiveRetentionDays));
        $referenceDate = DateTimeImmutable::createFromInterface($now ?? new DateTimeImmutable());
        $cutoff = $referenceDate->modify(sprintf('-%d days', $retentionDays))->format('Y-m-d H:i:s');
        $sensitiveCutoff = $referenceDate->modify(sprintf('-%d days', $sensitiveRetentionDays))->format('Y-m-d H:i:s');

        $this->database->ensureReady();

        $regularMatched = $this->countPurgeCandidates($cutoff, false);
        $sensitiveMatched = $this->countPurgeCandidates($sensitiveCutoff, true);
        $regularDeleted = 0;
        $sensitiveDeleted = 0;

        if (!$dryRun) {
            $regularDeleted = $this->deletePurgeCandidates($cutoff, false);
            $sensitiveDeleted = $this->deletePurgeCandidates($sensitiveCutoff, true);
        }

        return [
            'deleted' => $regularDeleted + $sensitiveDeleted,
            'regularDeleted' => $regularDeleted,
            'sensitiveDeleted' => $sensitiveDeleted,
            'regularMatched' => $regularMatched,
            'sensitiveMatched' => $sensitiveMatched,
            'retentionDays' => $retentionDays,
            'sensitiveRetentionDays' => $sensitiveRetentionDays,
            'cutoff' => $cutoff,
            'sensitiveCutoff' => $sensitiveCutoff,
            'dryRun' => $dryRun,
        ];
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

    private function countPurgeCandidates(string $cutoff, bool $sensitive): int
    {
        $query = $this->buildPurgeQuery(
            sprintf('SELECT COUNT(*) FROM `%s`', $this->database->table('log_entries')),
            $sensitive
        );
        $statement = $this->database->pdo()->prepare($query);
        $statement->execute(['cutoff' => $cutoff]);
        $count = $statement->fetchColumn();

        return is_numeric($count) ? (int) $count : 0;
    }

    private function deletePurgeCandidates(string $cutoff, bool $sensitive): int
    {
        $query = $this->buildPurgeQuery(
            sprintf('DELETE FROM `%s`', $this->database->table('log_entries')),
            $sensitive
        );
        $statement = $this->database->pdo()->prepare($query);
        $statement->execute(['cutoff' => $cutoff]);

        return $statement->rowCount();
    }

    private function buildPurgeQuery(string $prefix, bool $sensitive): string
    {
        $channels = $this->quoteValues(self::SENSITIVE_CHANNELS);
        $levels = $this->quoteValues(self::SENSITIVE_LEVELS);
        $sensitiveCondition = sprintf('(`channel` IN (%s) OR `level` IN (%s))', $channels, $levels);

        return sprintf(
            '%s WHERE `created_at` < :cutoff AND %s',
            $prefix,
            $sensitive ? $sensitiveCondition : 'NOT ' . $sensitiveCondition
        );
    }

    /**
     * @param array<int, string> $values
     */
    private function quoteValues(array $values): string
    {
        return implode(', ', array_map(
            function (string $value): string {
                $quoted = $this->database->pdo()->quote($value);

                return is_string($quoted) ? $quoted : "'" . str_replace("'", "''", $value) . "'";
            },
            $values
        ));
    }

    private function normalizeRetentionDays(int $days): int
    {
        return max(1, min(3650, $days));
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<int, string> $columns
     * @return array{where: string, params: array<string, mixed>}
     */
    private function buildFilterQuery(array $filters, array $columns): array
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

        $stream = $this->sanitizeChannel((string) ($filters['stream'] ?? ''));
        if ($stream !== '') {
            if (in_array('stream', $columns, true)) {
                $where[] = '`stream` = :stream';
                $params['stream'] = $stream;
            } else {
                $where[] = '0 = 1';
            }
        }

        foreach (['application', 'module', 'request_id', 'correlation_id', 'error_fingerprint'] as $field) {
            $value = $this->sanitizeStructuredText((string) ($filters[$field] ?? ''), $field === 'error_fingerprint' ? 64 : 191);
            if ($value === '') {
                continue;
            }

            if (in_array($field, $columns, true)) {
                $where[] = sprintf('`%s` = :%s', $field, $field);
                $params[$field] = $value;
            } else {
                $where[] = '0 = 1';
            }
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

    /**
     * @return array<int, string>
     */
    private function tableColumns(): array
    {
        try {
            $statement = $this->database->pdo()->query(
                sprintf('SHOW COLUMNS FROM `%s`', $this->database->table('log_entries'))
            );
            $columns = $statement === false ? [] : $statement->fetchAll(PDO::FETCH_COLUMN);
            if (!is_array($columns) || $columns === []) {
                return ['id', 'channel', 'level', 'event', 'context_json', 'created_at'];
            }

            return array_values(array_map('strval', $columns));
        } catch (\Throwable) {
            return ['id', 'channel', 'level', 'event', 'context_json', 'created_at'];
        }
    }

    /**
     * @param array<int, string> $columns
     */
    private function selectColumnOrEmpty(array $columns, string $column): string
    {
        return in_array($column, $columns, true) ? sprintf('`%s`', $column) : "''";
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

    /**
     * @param array<string, mixed> $context
     * @return array{
     *     stream: string,
     *     application: ?string,
     *     module: ?string,
     *     request_id: ?string,
     *     correlation_id: ?string,
     *     error_class: ?string,
     *     error_fingerprint: ?string,
     *     http_status: ?int,
     *     duration_ms: ?int,
     *     actor_type: ?string,
     *     actor_id: ?string,
     *     entity_type: ?string,
     *     entity_id: ?string,
     *     schema_version: int
     * }
     */
    private function structuredColumns(string $channel, string $level, array $context): array
    {
        $stream = $this->sanitizeChannel((string) ($context['stream'] ?? ''));
        if ($stream === '') {
            $stream = match ($this->sanitizeChannel($channel)) {
                'security' => 'security',
                'content' => 'audit',
                'access' => 'application',
                default => 'application',
            };
        }

        $fingerprint = $this->sanitizeStructuredText((string) ($context['error_fingerprint'] ?? ''), 64);
        if ($fingerprint === '' && in_array($this->sanitizeLevel($level), ['error', 'critical', 'alert', 'emergency'], true)) {
            $fingerprint = substr(hash('sha256', implode('|', [
                $channel,
                $level,
                (string) ($context['error_class'] ?? $context['exception'] ?? ''),
                (string) ($context['module'] ?? ''),
            ])), 0, 32);
        }

        return [
            'stream' => $stream,
            'application' => $this->nullableStructuredText($context['application'] ?? null, 64),
            'module' => $this->nullableStructuredText($context['module'] ?? null, 64),
            'request_id' => $this->nullableStructuredText($context['request_id'] ?? null, 128),
            'correlation_id' => $this->nullableStructuredText($context['correlation_id'] ?? null, 128),
            'error_class' => $this->nullableStructuredText($context['error_class'] ?? ($context['exception'] ?? null), 191),
            'error_fingerprint' => $fingerprint !== '' ? $fingerprint : null,
            'http_status' => $this->nullableInt($context['http_status'] ?? ($context['status'] ?? null), 100, 599),
            'duration_ms' => $this->nullableInt($context['duration_ms'] ?? null, 0, 3_600_000),
            'actor_type' => $this->nullableStructuredText($context['actor_type'] ?? null, 32),
            'actor_id' => $this->nullableStructuredText($context['actor_id'] ?? ($context['actor'] ?? null), 191),
            'entity_type' => $this->nullableStructuredText($context['entity_type'] ?? null, 64),
            'entity_id' => $this->nullableStructuredText($context['entity_id'] ?? null, 191),
            'schema_version' => max(1, min(999, (int) ($context['schema_version'] ?? 2))),
        ];
    }

    private function nullableStructuredText(mixed $value, int $maxLength): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = $this->sanitizeStructuredText((string) $value, $maxLength);

        return $text !== '' ? $text : null;
    }

    private function sanitizeStructuredText(string $value, int $maxLength): string
    {
        $value = (new LogSanitizer())->sanitizeText($value, $maxLength);

        return $this->trimText($value, $maxLength);
    }

    private function nullableInt(mixed $value, int $min, int $max): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $intValue = (int) $value;

        return $intValue >= $min && $intValue <= $max ? $intValue : null;
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
