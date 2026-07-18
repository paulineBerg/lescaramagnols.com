#!/usr/bin/env php
<?php

declare(strict_types=1);

use Caramagnols\Database\EditorialDatabase;

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit etre lancee en CLI.\n");
    exit(1);
}

$options = parse_cli_options(array_slice($argv, 1));
$dryRun = isset($options['dry-run']);
$jsonOutput = isset($options['json']);
$syncEditorial = !isset($options['no-editorial']);
$syncPrivate = !isset($options['no-private']);

if (isset($options['help'])) {
    render_usage();
    exit(0);
}

try {
    $database = editorial_database();
    $result = [
        'success' => true,
        'dry_run' => $dryRun,
        'generated_at' => date('c'),
        'editorial' => $syncEditorial ? sync_editorial_schema($database, $dryRun) : ['skipped' => true],
        'private' => $syncPrivate ? sync_private_schema($database, $dryRun) : ['skipped' => true],
    ];

    if ($jsonOutput) {
        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    } else {
        render_result($result);
    }

    exit(0);
} catch (Throwable $exception) {
    $payload = [
        'success' => false,
        'dry_run' => $dryRun,
        'generated_at' => date('c'),
        'error' => $exception->getMessage(),
    ];

    if ($jsonOutput) {
        fwrite(STDERR, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    } else {
        fwrite(STDERR, 'Schema deploy sync failed: ' . $exception->getMessage() . PHP_EOL);
    }

    exit(1);
}

/**
 * @param array<int, string> $arguments
 * @return array<string, string|true>
 */
function parse_cli_options(array $arguments): array
{
    $options = [];

    foreach ($arguments as $argument) {
        if (!is_string($argument) || !str_starts_with($argument, '--')) {
            continue;
        }

        $parts = explode('=', substr($argument, 2), 2);
        if (!isset($parts[1])) {
            $options[$parts[0]] = true;
            continue;
        }

        $options[$parts[0]] = $parts[1];
    }

    return $options;
}

function render_usage(): void
{
    fwrite(STDOUT, <<<TXT
Usage:
  php core/tools/sync_deploy_schema.php [--dry-run] [--json] [--no-editorial] [--no-private]

Description:
  Synchronise le schema SQL attendu par le code deploye.
  - editorial: applique les migrations versionnees backend/sql/editorial/*.sql
  - private: cree uniquement les tables manquantes declarees en CREATE TABLE IF NOT EXISTS dans backend/sql/private/*.sql

TXT);
}

/**
 * @return array<string, mixed>
 */
function sync_editorial_schema(EditorialDatabase $database, bool $dryRun): array
{
    $before = editorial_schema_status($database);

    if ($dryRun) {
        return $before + ['applied' => false];
    }

    $database->ensureReady();
    $after = editorial_schema_status($database);

    return $after + [
        'applied' => true,
        'previous_version' => $before['current_version'],
        'applied_versions' => $before['pending_versions'],
    ];
}

/**
 * @return array<string, mixed>
 */
function editorial_schema_status(EditorialDatabase $database): array
{
    $pdo = $database->pdo();
    $table = $database->table('editorial_schema_meta');
    $currentVersion = 0;

    if (table_exists($pdo, $table)) {
        $statement = $pdo->prepare(
            sprintf(
                'SELECT `version` FROM `%s` WHERE `schema_key` = :schema_key',
                quote_identifier($table)
            )
        );
        $statement->execute(['schema_key' => 'editorial']);
        $value = $statement->fetchColumn();
        $currentVersion = is_numeric($value) ? (int) $value : 0;
    }

    $schemaDir = (string) app_config('editorial.schema_dir', ROOT_PATH . '/sql/editorial');
    $migrations = editorial_schema_migration_files($schemaDir);
    $targetVersion = $migrations === [] ? 0 : max(array_keys($migrations));
    $pendingVersions = array_values(
        array_filter(
            array_keys($migrations),
            static fn (int $version): bool => $version > $currentVersion
        )
    );

    return [
        'current_version' => $currentVersion,
        'target_version' => $targetVersion,
        'pending_versions' => $pendingVersions,
        'pending_count' => count($pendingVersions),
    ];
}

/**
 * @return array<string, mixed>
 */
function sync_private_schema(EditorialDatabase $database, bool $dryRun): array
{
    $pdo = $database->pdo();
    $prefix = (string) app_config('database_prefix', 'car_');
    $statements = private_schema_statements(ROOT_PATH . '/sql/private', $prefix);
    $createStatements = array_values(
        array_filter($statements, static fn (array $statement): bool => $statement['kind'] === 'create_table')
    );
    $seedStatements = array_values(
        array_filter($statements, static fn (array $statement): bool => $statement['kind'] === 'seed')
    );
    $existing = [];
    $pending = [];

    foreach ($createStatements as $statement) {
        if (table_exists($pdo, $statement['table'])) {
            $existing[] = $statement['table'];
            continue;
        }

        $pending[] = $statement;
    }

    if ($dryRun) {
        return [
            'checked_count' => count($createStatements),
            'existing_count' => count($existing),
            'missing_count' => count($pending),
            'created_count' => 0,
            'created_tables' => [],
            'missing_tables' => array_values(array_map(static fn (array $item): string => $item['table'], $pending)),
            'seed_count' => count($seedStatements),
            'seed_applied_count' => 0,
        ];
    }

    $created = [];
    $remaining = $pending;
    $lastErrors = [];

    while ($remaining !== []) {
        $next = [];
        $progress = false;

        foreach ($remaining as $statement) {
            try {
                $pdo->exec($statement['sql']);

                if (!table_exists($pdo, $statement['table'])) {
                    $next[] = $statement;
                    $lastErrors[$statement['table']] = sprintf(
                        '%s: table not visible after CREATE TABLE',
                        $statement['file']
                    );
                    continue;
                }

                $created[] = $statement['table'];
                $progress = true;
            } catch (Throwable $exception) {
                $next[] = $statement;
                $lastErrors[$statement['table']] = sprintf(
                    '%s: %s',
                    $statement['file'],
                    $exception->getMessage()
                );
            }
        }

        if (!$progress && count($next) === count($remaining)) {
            $details = [];
            foreach ($next as $statement) {
                $details[] = sprintf(
                    '%s (%s)',
                    $statement['table'],
                    $lastErrors[$statement['table']] ?? $statement['file']
                );
            }

            throw new RuntimeException(
                'Impossible de creer certaines tables privees: ' . implode('; ', $details)
            );
        }

        $remaining = $next;
    }

    $seedApplied = 0;
    $seedSkipped = 0;
    foreach ($seedStatements as $statement) {
        if (!table_exists($pdo, $statement['table'])) {
            $seedSkipped++;
            continue;
        }

        $pdo->exec($statement['sql']);
        $seedApplied++;
    }

    return [
        'checked_count' => count($createStatements),
        'existing_count' => count($existing),
        'missing_count' => count($pending),
        'created_count' => count($created),
        'created_tables' => $created,
        'missing_tables' => [],
        'seed_count' => count($seedStatements),
        'seed_applied_count' => $seedApplied,
        'seed_skipped_count' => $seedSkipped,
    ];
}

/**
 * @return array<int, array{kind: string, file: string, table: string, sql: string}>
 */
function private_schema_statements(string $schemaDir, string $tablePrefix): array
{
    if (!preg_match('/^[a-zA-Z0-9_]*$/', $tablePrefix)) {
        throw new RuntimeException('Prefixe SQL invalide pour la synchronisation du schema prive.');
    }

    $files = glob(rtrim($schemaDir, '/') . '/*.sql') ?: [];
    sort($files);

    $statements = [];
    foreach ($files as $filePath) {
        $sql = file_get_contents($filePath);
        if ($sql === false) {
            throw new RuntimeException(sprintf('Impossible de lire le fichier SQL prive: %s', $filePath));
        }

        foreach (split_sql_statements(rewrite_private_table_prefix($sql, $tablePrefix)) as $statement) {
            $statement = strip_private_schema_comments($statement);
            if ($statement === '') {
                continue;
            }

            if (
                preg_match(
                    '/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([a-zA-Z0-9_]+)`?/i',
                    $statement,
                    $matches
                ) === 1
            ) {
                $statements[] = [
                    'kind' => 'create_table',
                    'file' => $filePath,
                    'table' => (string) $matches[1],
                    'sql' => $statement,
                ];

                continue;
            }

            if (preg_match('/^INSERT\s+IGNORE\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i', $statement, $matches) === 1) {
                $statements[] = [
                    'kind' => 'seed',
                    'file' => $filePath,
                    'table' => (string) $matches[1],
                    'sql' => $statement,
                ];

                continue;
            }

            throw new RuntimeException(
                sprintf('Instruction SQL privee non supportee dans %s: %s', $filePath, preview_sql($statement))
            );
        }
    }

    return $statements;
}

function strip_private_schema_comments(string $statement): string
{
    $lines = preg_split('/\r?\n/', $statement) ?: [];
    $kept = [];

    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if (str_starts_with($trimmed, '--')) {
            continue;
        }

        $kept[] = $line;
    }

    return trim(implode("\n", $kept));
}

function rewrite_private_table_prefix(string $sql, string $tablePrefix): string
{
    if ($tablePrefix === 'car_') {
        return $sql;
    }

    return (string) preg_replace('/\bcar_([a-zA-Z0-9_]+)\b/', $tablePrefix . '$1', $sql);
}

/**
 * @return array<int, string>
 */
function split_sql_statements(string $sql): array
{
    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];

    return array_values(
        array_filter(
            array_map(static fn (string $statement): string => trim($statement), $statements),
            static fn (string $statement): bool => $statement !== ''
        )
    );
}

function preview_sql(string $statement): string
{
    $preview = preg_replace('/\s+/', ' ', trim($statement)) ?? '';

    return strlen($preview) > 120 ? substr($preview, 0, 117) . '...' : $preview;
}

function table_exists(PDO $pdo, string $tableName): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
    );
    $statement->execute(['table' => $tableName]);

    return (int) $statement->fetchColumn() > 0;
}

function quote_identifier(string $identifier): string
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
        throw new RuntimeException(sprintf('Identifiant SQL invalide: %s', $identifier));
    }

    return str_replace('`', '``', $identifier);
}

/**
 * @param array<string, mixed> $result
 */
function render_result(array $result): void
{
    fwrite(STDOUT, "Schema deploy sync\n");
    fwrite(STDOUT, sprintf("- dry-run: %s\n", ($result['dry_run'] ?? false) ? 'yes' : 'no'));

    $editorial = is_array($result['editorial'] ?? null) ? $result['editorial'] : [];
    if (($editorial['skipped'] ?? false) === true) {
        fwrite(STDOUT, "- editorial: skipped\n");
    } else {
        fwrite(
            STDOUT,
            sprintf(
                "- editorial: current=%d target=%d pending=%d\n",
                (int) ($editorial['current_version'] ?? 0),
                (int) ($editorial['target_version'] ?? 0),
                (int) ($editorial['pending_count'] ?? 0)
            )
        );
    }

    $private = is_array($result['private'] ?? null) ? $result['private'] : [];
    if (($private['skipped'] ?? false) === true) {
        fwrite(STDOUT, "- private: skipped\n");
    } else {
        fwrite(
            STDOUT,
            sprintf(
                "- private: checked=%d existing=%d missing=%d created=%d\n",
                (int) ($private['checked_count'] ?? 0),
                (int) ($private['existing_count'] ?? 0),
                (int) ($private['missing_count'] ?? 0),
                (int) ($private['created_count'] ?? 0)
            )
        );

        $createdTables = is_array($private['created_tables'] ?? null) ? $private['created_tables'] : [];
        if ($createdTables !== []) {
            fwrite(STDOUT, '- private created tables: ' . implode(', ', $createdTables) . PHP_EOL);
        }
    }
}
