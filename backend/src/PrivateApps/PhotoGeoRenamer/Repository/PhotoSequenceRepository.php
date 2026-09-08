<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Repository;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\PhotoCommuneNormalizer;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\SequenceReservation;
use PDO;
use RuntimeException;

final class PhotoSequenceRepository
{
    public function __construct(
        private readonly EditorialDatabase $database,
        private readonly PhotoCommuneNormalizer $communeNormalizer = new PhotoCommuneNormalizer()
    ) {
    }

    public function reserveForCommune(string $communeName, int $count): SequenceReservation
    {
        $reservations = $this->reserve([$communeName => $count]);
        $reservation = reset($reservations);
        if (!$reservation instanceof SequenceReservation) {
            throw new RuntimeException('Aucune reservation de sequence photo creee.');
        }

        return $reservation;
    }

    /**
     * @param array<string, int> $requests commune name => count
     * @return array<string, SequenceReservation> commune key => reservation
     */
    public function reserve(array $requests): array
    {
        $normalized = [];
        foreach ($requests as $communeName => $count) {
            $commune = $this->communeNormalizer->normalize((string) $communeName);
            $count = (int) $count;
            if ($commune === null || $count <= 0) {
                continue;
            }
            $normalized[$commune->key] = [
                'name' => $commune->name,
                'count' => ($normalized[$commune->key]['count'] ?? 0) + $count,
            ];
        }

        if ($normalized === []) {
            return [];
        }

        ksort($normalized);
        $pdo = $this->database->pdo();
        $started = !$pdo->inTransaction();
        if ($started) {
            $pdo->beginTransaction();
        }

        try {
            $reservations = [];
            foreach ($normalized as $communeKey => $request) {
                $reservations[$communeKey] = $this->reserveInsideTransaction(
                    $pdo,
                    $communeKey,
                    (string) $request['name'],
                    (int) $request['count']
                );
            }

            if ($started) {
                $pdo->commit();
            }

            return $reservations;
        } catch (\Throwable $exception) {
            if ($started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function initializeManualCounter(string $communeName, int $lastNumber): SequenceReservation
    {
        $commune = $this->communeNormalizer->normalize($communeName);
        if ($commune === null || $lastNumber < 0) {
            throw new RuntimeException('Compteur PhotoGeoRenamer invalide.');
        }

        $pdo = $this->database->pdo();
        $started = !$pdo->inTransaction();
        if ($started) {
            $pdo->beginTransaction();
        }

        try {
            $this->insertSequenceIfMissing($pdo, $commune->key, $commune->name);
            $row = $this->lockSequence($pdo, $commune->key);
            $current = (int) ($row['last_number'] ?? 0);
            $newLast = max($current, $lastNumber);

            $statement = $pdo->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `commune_name` = :commune_name,
                         `last_number` = :last_number,
                         `initialized_manually` = 1,
                         `updated_at` = :updated_at
                     WHERE `commune_key` = :commune_key',
                    $this->table('photo_geo_sequences')
                )
            );
            $statement->execute([
                'commune_name' => $commune->name,
                'last_number' => $newLast,
                'updated_at' => $this->now(),
                'commune_key' => $commune->key,
            ]);

            if ($started) {
                $pdo->commit();
            }

            return new SequenceReservation($commune->key, $commune->name, $newLast + 1, $newLast, []);
        } catch (\Throwable $exception) {
            if ($started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allSequences(): array
    {
        $statement = $this->database->pdo()->query(
            sprintf(
                'SELECT `commune_key`, `commune_name`, `last_number`, `initialized_manually`, `updated_at`
                 FROM `%s`
                 ORDER BY `commune_name` ASC',
                $this->table('photo_geo_sequences')
            )
        );
        $rows = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];

        return array_values(array_filter(is_array($rows) ? $rows : [], 'is_array'));
    }

    private function reserveInsideTransaction(PDO $pdo, string $communeKey, string $communeName, int $count): SequenceReservation
    {
        $this->insertSequenceIfMissing($pdo, $communeKey, $communeName);
        $row = $this->lockSequence($pdo, $communeKey);
        $current = (int) ($row['last_number'] ?? 0);
        $first = $current + 1;
        $last = $current + $count;

        $statement = $pdo->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `commune_name` = :commune_name,
                     `last_number` = :last_number,
                     `updated_at` = :updated_at
                 WHERE `commune_key` = :commune_key AND `last_number` = :current_last',
                $this->table('photo_geo_sequences')
            )
        );
        $statement->execute([
            'commune_name' => $communeName,
            'last_number' => $last,
            'updated_at' => $this->now(),
            'commune_key' => $communeKey,
            'current_last' => $current,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Reservation concurrente PhotoGeoRenamer non appliquee.');
        }

        return new SequenceReservation($communeKey, $communeName, $first, $last, range($first, $last));
    }

    private function insertSequenceIfMissing(PDO $pdo, string $communeKey, string $communeName): void
    {
        $statement = $pdo->prepare(
            sprintf(
                'INSERT INTO `%s` (`commune_key`, `commune_name`, `last_number`, `created_at`, `updated_at`)
                 VALUES (:commune_key, :commune_name, 0, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE `commune_name` = VALUES(`commune_name`)',
                $this->table('photo_geo_sequences')
            )
        );
        $now = $this->now();
        $statement->execute([
            'commune_key' => $communeKey,
            'commune_name' => $communeName,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function lockSequence(PDO $pdo, string $communeKey): array
    {
        $statement = $pdo->prepare(
            sprintf(
                'SELECT `commune_key`, `commune_name`, `last_number`
                 FROM `%s`
                 WHERE `commune_key` = :commune_key
                 LIMIT 1
                 FOR UPDATE',
                $this->table('photo_geo_sequences')
            )
        );
        $statement->execute(['commune_key' => $communeKey]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Sequence PhotoGeoRenamer introuvable apres creation.');
        }

        return $row;
    }

    private function table(string $name): string
    {
        return $this->database->table($name);
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
