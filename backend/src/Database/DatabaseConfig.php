<?php

declare(strict_types=1);

namespace Caramagnols\Database;

final class DatabaseConfig
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $name,
        public readonly string $user,
        public readonly string $password,
        public readonly string $charset = 'utf8mb4'
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            trim((string) ($config['host'] ?? '127.0.0.1')),
            max(1, (int) ($config['port'] ?? 3306)),
            trim((string) ($config['name'] ?? '')),
            trim((string) ($config['user'] ?? '')),
            (string) ($config['password'] ?? ''),
            trim((string) ($config['charset'] ?? 'utf8mb4')) ?: 'utf8mb4'
        );
    }

    public function isConfigured(): bool
    {
        return $this->name !== '' && $this->user !== '';
    }

    public function dsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host,
            $this->port,
            $this->name,
            $this->charset
        );
    }
}
