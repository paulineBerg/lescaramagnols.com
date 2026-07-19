<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivateApps\Documents\Contract\DocumentEntityResolver;

/**
 * Resolver des entités locatives : toutes les entités remontent à un bien
 * (rental_property_id) ; l'accès est accordé aux membres actifs du bien.
 */
final class RentalDocumentEntityResolver implements DocumentEntityResolver
{
    /**
     * type -> [table, colonne du bien (null = la table est celle des biens), colonne libellé]
     *
     * @var array<string, array{0: string, 1: ?string, 2: string}>
     */
    private const ENTITY_TABLES = [
        RentalDocumentIntegration::ENTITY_PROPERTY => ['rental_properties', null, 'name'],
        RentalDocumentIntegration::ENTITY_UNIT => ['rental_units', 'rental_property_id', 'label'],
        RentalDocumentIntegration::ENTITY_TENANT => ['rental_tenants', 'rental_property_id', 'full_name'],
        RentalDocumentIntegration::ENTITY_LEASE => ['rental_leases', 'rental_property_id', 'id'],
        RentalDocumentIntegration::ENTITY_EXPENSE => ['rental_expenses', 'rental_property_id', 'label'],
        RentalDocumentIntegration::ENTITY_REGULARIZATION => ['rental_charge_regularizations', 'rental_property_id', 'id'],
    ];

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function supportedEntityTypes(): array
    {
        return array_keys(self::ENTITY_TABLES);
    }

    public function entityExists(string $entityType, string $entityId): bool
    {
        $mapping = self::ENTITY_TABLES[$entityType] ?? null;
        if ($mapping === null || !ctype_digit($entityId)) {
            return false;
        }

        try {
            $statement = $this->database->pdo()->prepare(sprintf(
                'SELECT COUNT(*) FROM `%s` WHERE `id` = :id',
                $this->database->table($mapping[0])
            ));
            $statement->execute(['id' => (int) $entityId]);

            return (int) $statement->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function userCanAccessEntity(int $privateUserId, string $entityType, string $entityId): bool
    {
        $propertyId = $this->propertyIdFor($entityType, $entityId);
        if ($propertyId === null) {
            return false;
        }

        try {
            $statement = $this->database->pdo()->prepare(sprintf(
                'SELECT COUNT(*) FROM `%s`
                 WHERE `rental_property_id` = :property_id
                   AND `private_user_id` = :user_id
                   AND `is_active` = 1
                   AND `status` = \'active\'',
                $this->database->table('rental_property_members')
            ));
            $statement->execute(['property_id' => $propertyId, 'user_id' => $privateUserId]);

            return (int) $statement->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function entityLabel(string $entityType, string $entityId): string
    {
        $mapping = self::ENTITY_TABLES[$entityType] ?? null;
        if ($mapping === null || !ctype_digit($entityId)) {
            return '';
        }

        try {
            $statement = $this->database->pdo()->prepare(sprintf(
                'SELECT `%s` FROM `%s` WHERE `id` = :id LIMIT 1',
                $mapping[2],
                $this->database->table($mapping[0])
            ));
            $statement->execute(['id' => (int) $entityId]);
            $value = $statement->fetchColumn();

            if ($mapping[2] === 'id') {
                return $value !== false ? sprintf('#%d', (int) $value) : '';
            }

            return is_string($value) ? $value : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private function propertyIdFor(string $entityType, string $entityId): ?int
    {
        $mapping = self::ENTITY_TABLES[$entityType] ?? null;
        if ($mapping === null || !ctype_digit($entityId)) {
            return null;
        }

        if ($mapping[1] === null) {
            return $this->entityExists($entityType, $entityId) ? (int) $entityId : null;
        }

        try {
            $statement = $this->database->pdo()->prepare(sprintf(
                'SELECT `%s` FROM `%s` WHERE `id` = :id LIMIT 1',
                $mapping[1],
                $this->database->table($mapping[0])
            ));
            $statement->execute(['id' => (int) $entityId]);
            $propertyId = $statement->fetchColumn();

            return is_numeric($propertyId) && (int) $propertyId > 0 ? (int) $propertyId : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
