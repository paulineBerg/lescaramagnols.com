<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivateApps\Documents\Contract\DocumentEntityResolver;
use Caramagnols\PrivateApps\Documents\Contract\DocumentEntityType;
use Caramagnols\PrivateApps\Documents\Contract\DocumentImportProfile;
use Caramagnols\PrivateApps\Documents\Contract\DocumentIntegration;

/**
 * Intégration documentaire du module RealEstateRental : types d'entités,
 * profils d'import par onglet et resolver d'autorisation fondé sur
 * l'appartenance active à un bien (rental_property_members).
 */
final class RentalDocumentIntegration implements DocumentIntegration
{
    public const ENTITY_PROPERTY = 'rental.property';
    public const ENTITY_UNIT = 'rental.unit';
    public const ENTITY_TENANT = 'rental.tenant';
    public const ENTITY_LEASE = 'rental.lease';
    public const ENTITY_EXPENSE = 'rental.expense';
    public const ENTITY_REGULARIZATION = 'rental.regularization';

    public const PROFILE_DOCUMENTS = 'rental.documents';
    public const PROFILE_LEASE_CONTRACT = 'rental.lease_contract';
    public const PROFILE_EXPENSE_PROOF = 'rental.expense_proof';
    public const PROFILE_TENANT_FILE = 'rental.tenant_file';
    public const PROFILE_INVENTORY = 'rental.inventory';

    public function moduleCode(): string
    {
        return 'real_estate_rental';
    }

    public function entityTypes(): array
    {
        return [
            new DocumentEntityType(self::ENTITY_PROPERTY, 'real_estate_rental', 'Bien'),
            new DocumentEntityType(self::ENTITY_UNIT, 'real_estate_rental', 'Logement'),
            new DocumentEntityType(self::ENTITY_TENANT, 'real_estate_rental', 'Locataire'),
            new DocumentEntityType(self::ENTITY_LEASE, 'real_estate_rental', 'Bail'),
            new DocumentEntityType(self::ENTITY_EXPENSE, 'real_estate_rental', 'Charge'),
            new DocumentEntityType(self::ENTITY_REGULARIZATION, 'real_estate_rental', 'Régularisation'),
        ];
    }

    public function importProfiles(): array
    {
        return [
            new DocumentImportProfile(
                self::PROFILE_DOCUMENTS,
                'real_estate_rental',
                'rental_documents_tab',
                self::ENTITY_PROPERTY,
                'inbox',
                [],
                ['property_id'],
                true
            ),
            new DocumentImportProfile(
                self::PROFILE_LEASE_CONTRACT,
                'real_estate_rental',
                'rental_leases_tab',
                self::ENTITY_LEASE,
                'leases.contract',
                ['leases.contract', 'leases.amendment', 'inventory.entry', 'inventory.exit', 'rents.deposit'],
                ['lease_id'],
                true
            ),
            new DocumentImportProfile(
                self::PROFILE_EXPENSE_PROOF,
                'real_estate_rental',
                'rental_expenses_tab',
                self::ENTITY_EXPENSE,
                'charges',
                [],
                ['expense_id'],
                true
            ),
            new DocumentImportProfile(
                self::PROFILE_TENANT_FILE,
                'real_estate_rental',
                'rental_tenants_tab',
                self::ENTITY_TENANT,
                'identity',
                ['identity', 'mail', 'leases.contract', 'other'],
                ['tenant_id'],
                true
            ),
            new DocumentImportProfile(
                self::PROFILE_INVENTORY,
                'real_estate_rental',
                'rental_inventory_tab',
                self::ENTITY_LEASE,
                'inventory',
                ['inventory', 'inventory.entry', 'inventory.exit'],
                ['lease_id'],
                true
            ),
        ];
    }

    public function createEntityResolver(EditorialDatabase $database): DocumentEntityResolver
    {
        return new RentalDocumentEntityResolver($database);
    }
}
