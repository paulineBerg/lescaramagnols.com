<?php

declare(strict_types=1);

namespace Caramagnols\Database;

use PDO;
use RuntimeException;

final class EditorialSchemaManager
{
    /**
     * @param array<int, string>|null $migrationFiles
     */
    public function __construct(
        private readonly string $tablePrefix,
        private readonly ?array $migrationFiles = null
    ) {
    }

    public function ensureSchema(PDO $pdo): void
    {
        $this->ensureMetadataTable($pdo);

        $currentVersion = $this->currentVersion($pdo);
        foreach ($this->migrationFiles() as $version => $filePath) {
            if ($version <= $currentVersion) {
                continue;
            }

            $this->applyMigration($pdo, $version, $filePath);
            $currentVersion = $version;
        }
    }

    private function ensureMetadataTable(PDO $pdo): void
    {
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `schema_key` VARCHAR(64) NOT NULL,
                    `version` INT UNSIGNED NOT NULL,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`schema_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                $this->table('editorial_schema_meta')
            )
        );
    }

    private function currentVersion(PDO $pdo): int
    {
        $statement = $pdo->prepare(
            sprintf(
                'SELECT `version` FROM `%s` WHERE `schema_key` = :schema_key',
                $this->table('editorial_schema_meta')
            )
        );
        $statement->execute(['schema_key' => 'editorial']);
        $value = $statement->fetchColumn();

        return is_numeric($value) ? (int) $value : 0;
    }

    private function applyMigration(PDO $pdo, int $version, string $filePath): void
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException(sprintf('Migration SQL introuvable : %s', $filePath));
        }

        $sql = file_get_contents($filePath);
        if ($sql === false) {
            throw new RuntimeException(sprintf('Impossible de lire la migration SQL : %s', $filePath));
        }

        $statements = $this->splitStatements($this->replaceTableTokens($sql));
        if ($statements === []) {
            return;
        }

        try {
            foreach ($statements as $statement) {
                $pdo->exec($statement);
            }

            $upsert = $pdo->prepare(
                sprintf(
                    'INSERT INTO `%s` (`schema_key`, `version`)
                     VALUES (:schema_key, :version)
                     ON DUPLICATE KEY UPDATE `version` = VALUES(`version`)',
                    $this->table('editorial_schema_meta')
                )
            );
            $upsert->execute([
                'schema_key' => 'editorial',
                'version' => $version,
            ]);
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                sprintf('Migration SQL version %d impossible : %s', $version, $exception->getMessage()),
                previous: $exception
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function migrationFiles(): array
    {
        if ($this->migrationFiles !== null) {
            return $this->migrationFiles;
        }

        $files = [];

        foreach (glob(ROOT_PATH . '/sql/editorial/*.sql') ?: [] as $filePath) {
            $basename = basename($filePath);
            if (preg_match('/^(\d+)_.*\.sql$/', $basename, $matches) !== 1) {
                continue;
            }

            $files[(int) $matches[1]] = $filePath;
        }

        ksort($files);

        return $files;
    }

    private function replaceTableTokens(string $sql): string
    {
        return (string) preg_replace_callback(
            '/\{\{table:([a-zA-Z0-9_]+)\}\}/',
            fn (array $matches): string => sprintf('`%s`', $this->table((string) $matches[1])),
            $sql
        );
    }

    /**
     * @return array<int, string>
     */
    private function splitStatements(string $sql): array
    {
        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];

        return array_values(
            array_filter(
                array_map(static fn (string $statement): string => trim($statement), $statements),
                static fn (string $statement): bool => $statement !== ''
            )
        );
    }

    private function table(string $name): string
    {
        return $this->tablePrefix . ltrim($name, '_');
    }
}
