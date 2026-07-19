<?php

declare(strict_types=1);

namespace Caramagnols\Database;

use PDO;

final class EditorialDatabase
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly DatabaseConfig $config,
        private readonly string $tablePrefix,
        private readonly EditorialSchemaManager $schemaManager,
        private readonly PdoConnectionFactory $connectionFactory = new PdoConnectionFactory()
    ) {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $this->pdo = $this->connectionFactory->connect($this->config);

        return $this->pdo;
    }

    public function ensureReady(): void
    {
        $this->schemaManager->ensureSchema($this->pdo());
    }

    public function table(string $name): string
    {
        return $this->tablePrefix . ltrim($name, '_');
    }
}
