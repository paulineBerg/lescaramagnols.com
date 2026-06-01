<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Repository;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class RentalLessorRepository
{
    private bool $schemaReady = false;

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function table(): string
    {
        return $this->database->table('rental_lessors');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(int $privateUserId, int $limit = 200): array
    {
        if ($privateUserId <= 0 || $limit <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT *
                     FROM `%s`
                     WHERE `created_by_private_user_id` = :user_id
                       AND `is_active` = 1
                     ORDER BY `last_name` ASC, `first_name` ASC, `id` ASC
                     LIMIT :limit',
                    $this->table()
                )
            );
            $statement->bindValue(':user_id', $privateUserId, PDO::PARAM_INT);
            $statement->bindValue(':limit', min($limit, 500), PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return $this->normalizeRows($rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(int $lessorId, int $privateUserId): ?array
    {
        if ($lessorId <= 0 || $privateUserId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT *
                     FROM `%s`
                     WHERE `id` = :id
                       AND `created_by_private_user_id` = :user_id
                       AND `is_active` = 1
                     LIMIT 1',
                    $this->table()
                )
            );
            $statement->execute([
                'id' => $lessorId,
                'user_id' => $privateUserId,
            ]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function create(
        int $privateUserId,
        string $lastName,
        string $firstName,
        string $address,
        string $phone,
        string $email
    ): ?array {
        return $this->save(0, $privateUserId, $lastName, $firstName, $address, $phone, $email);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function update(
        int $lessorId,
        int $privateUserId,
        string $lastName,
        string $firstName,
        string $address,
        string $phone,
        string $email
    ): ?array {
        return $this->save($lessorId, $privateUserId, $lastName, $firstName, $address, $phone, $email);
    }

    public function deleteForUser(int $lessorId, int $privateUserId): bool
    {
        if ($lessorId <= 0 || $privateUserId <= 0) {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `is_active` = 0,
                         `archived_at` = :archived_at,
                         `updated_at` = :updated_at
                     WHERE `id` = :id
                       AND `created_by_private_user_id` = :user_id
                       AND `is_active` = 1',
                    $this->table()
                )
            );
            $now = date('Y-m-d H:i:s');
            $statement->execute([
                'archived_at' => $now,
                'updated_at' => $now,
                'id' => $lessorId,
                'user_id' => $privateUserId,
            ]);

            return $statement->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        $this->database->ensureReady();
        $this->database->pdo()->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `created_by_private_user_id` INT NOT NULL,
                    `last_name` VARCHAR(120) NOT NULL,
                    `first_name` VARCHAR(120) NULL,
                    `address` VARCHAR(500) NULL,
                    `phone` VARCHAR(80) NULL,
                    `email` VARCHAR(254) NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `archived_at` DATETIME NULL,
                    KEY `idx_rental_lessors_user_active` (`created_by_private_user_id`, `is_active`),
                    KEY `idx_rental_lessors_name` (`last_name`, `first_name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->table()
            )
        );

        $this->schemaReady = true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function save(
        int $lessorId,
        int $privateUserId,
        string $lastName,
        string $firstName,
        string $address,
        string $phone,
        string $email
    ): ?array {
        $lastName = $this->normalizeText($lastName, 120);
        $firstName = $this->normalizeText($firstName, 120);
        $address = $this->normalizeText($address, 500);
        $phone = $this->normalizeText($phone, 80);
        $email = strtolower($this->normalizeText($email, 254));

        if ($privateUserId <= 0 || ($lastName === '' && $firstName === '')) {
            return null;
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        try {
            $this->ensureSchema();
            if ($lessorId > 0) {
                $statement = $this->database->pdo()->prepare(
                    sprintf(
                        'UPDATE `%s`
                         SET `last_name` = :last_name,
                             `first_name` = :first_name,
                             `address` = :address,
                             `phone` = :phone,
                             `email` = :email,
                             `updated_at` = :updated_at
                         WHERE `id` = :id
                           AND `created_by_private_user_id` = :user_id
                           AND `is_active` = 1',
                        $this->table()
                    )
                );
                $statement->execute([
                    'last_name' => $lastName !== '' ? $lastName : $firstName,
                    'first_name' => $firstName !== '' ? $firstName : null,
                    'address' => $address !== '' ? $address : null,
                    'phone' => $phone !== '' ? $phone : null,
                    'email' => $email !== '' ? $email : null,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'id' => $lessorId,
                    'user_id' => $privateUserId,
                ]);

                return $statement->rowCount() >= 0 ? $this->findForUser($lessorId, $privateUserId) : null;
            }

            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`created_by_private_user_id`, `last_name`, `first_name`, `address`, `phone`, `email`)
                     VALUES
                        (:user_id, :last_name, :first_name, :address, :phone, :email)',
                    $this->table()
                )
            );
            $statement->execute([
                'user_id' => $privateUserId,
                'last_name' => $lastName !== '' ? $lastName : $firstName,
                'first_name' => $firstName !== '' ? $firstName : null,
                'address' => $address !== '' ? $address : null,
                'phone' => $phone !== '' ? $phone : null,
                'email' => $email !== '' ? $email : null,
            ]);
            $id = (int) $this->database->pdo()->lastInsertId();
        } catch (\Throwable) {
            return null;
        }

        return $id > 0 ? $this->findForUser($id, $privateUserId) : null;
    }

    private function normalizeText(string $value, int $maxLength): string
    {
        $value = sanitize_text_field($value, $maxLength);
        $value = trim((string) preg_replace('/\s+/', ' ', $value));

        return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength, 'UTF-8') : substr($value, 0, $maxLength);
    }

    /**
     * @param mixed $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $normalized[] = $this->normalizeRow($row);
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $lastName = is_string($row['last_name'] ?? null) ? trim((string) $row['last_name']) : '';
        $firstName = is_string($row['first_name'] ?? null) ? trim((string) $row['first_name']) : '';
        $fullName = trim($firstName . ' ' . $lastName);

        return [
            'id' => is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0,
            'createdByPrivateUserId' => is_numeric($row['created_by_private_user_id'] ?? null)
                ? (int) $row['created_by_private_user_id']
                : 0,
            'lastName' => $lastName,
            'firstName' => $firstName,
            'fullName' => $fullName !== '' ? $fullName : $lastName,
            'address' => is_string($row['address'] ?? null) ? trim((string) $row['address']) : '',
            'phone' => is_string($row['phone'] ?? null) ? trim((string) $row['phone']) : '',
            'email' => is_string($row['email'] ?? null) ? trim((string) $row['email']) : '',
            'isActive' => is_scalar($row['is_active'] ?? null) ? ((int) $row['is_active'] === 1) : true,
            'createdAt' => is_string($row['created_at'] ?? null) ? trim((string) $row['created_at']) : '',
            'updatedAt' => is_string($row['updated_at'] ?? null) ? trim((string) $row['updated_at']) : '',
            'archivedAt' => is_string($row['archived_at'] ?? null) ? trim((string) $row['archived_at']) : '',
        ];
    }
}
