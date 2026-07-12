<?php

declare(strict_types=1);

namespace Caramagnols\Database;

use PDO;
use PDOException;
use RuntimeException;

final class PdoConnectionFactory
{
    public function connect(DatabaseConfig $config): PDO
    {
        if (!$config->isConfigured()) {
            throw new RuntimeException('Configuration SQL éditoriale incomplète.');
        }

        try {
            return new PDO(
                $config->dsn(),
                $config->user,
                $config->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Connexion SQL éditoriale impossible : ' . $exception->getMessage(),
                previous: $exception
            );
        }
    }
}
