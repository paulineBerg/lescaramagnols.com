<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PrivateApps\Documents;

use Caramagnols\PrivateApps\Documents\Registry\DocumentIntegrationRegistry;
use Caramagnols\PrivateApps\RealEstateRental\RentalDocumentIntegration;
use Caramagnols\PrivatePortal\PrivateAppRegistry;
use PHPUnit\Framework\TestCase;

final class DocumentIntegrationRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        PrivateAppRegistry::reset();
        DocumentIntegrationRegistry::reset();
    }

    protected function tearDown(): void
    {
        PrivateAppRegistry::reset();
        DocumentIntegrationRegistry::reset();
    }

    public function testModulesProvidingIntegrationAreDiscovered(): void
    {
        $integrations = DocumentIntegrationRegistry::all();

        self::assertArrayHasKey('documents', $integrations);
        self::assertArrayHasKey('real_estate_rental', $integrations);
    }

    public function testEntityTypesAreNamespacedAndUnique(): void
    {
        $entityTypes = DocumentIntegrationRegistry::entityTypes();

        self::assertArrayHasKey('user.personal', $entityTypes);
        self::assertArrayHasKey(RentalDocumentIntegration::ENTITY_PROPERTY, $entityTypes);
        self::assertArrayHasKey(RentalDocumentIntegration::ENTITY_LEASE, $entityTypes);
        foreach (array_keys($entityTypes) as $code) {
            self::assertMatchesRegularExpression('/\A[a-z0-9_]+\.[a-z0-9_]+\z/', $code);
        }
    }

    public function testImportProfilesReferenceKnownContextTypes(): void
    {
        $profiles = DocumentIntegrationRegistry::importProfiles();
        self::assertArrayHasKey(RentalDocumentIntegration::PROFILE_DOCUMENTS, $profiles);

        foreach ($profiles as $profile) {
            if ($profile->contextEntityType === '') {
                continue;
            }
            self::assertTrue(
                DocumentIntegrationRegistry::isKnownEntityType($profile->contextEntityType),
                sprintf('Le profil %s référence un type inconnu %s', $profile->code, $profile->contextEntityType)
            );
        }
    }

    public function testUnknownEntityTypeIsRejected(): void
    {
        self::assertFalse(DocumentIntegrationRegistry::isKnownEntityType('rental.unknown'));
        self::assertFalse(DocumentIntegrationRegistry::isKnownEntityType('no-namespace'));
        self::assertNull(DocumentIntegrationRegistry::profile('unknown.profile'));
    }
}
