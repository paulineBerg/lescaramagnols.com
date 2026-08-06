<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\TaxDeclarationHelper;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivateApps\Documents\Contract\DocumentEntityResolver;
use Caramagnols\PrivateApps\Documents\Contract\DocumentEntityType;
use Caramagnols\PrivateApps\Documents\Contract\DocumentImportProfile;
use Caramagnols\PrivateApps\Documents\Contract\DocumentIntegration;

final class TaxDocumentIntegration implements DocumentIntegration
{
    public const ENTITY_YEAR = 'tax.year';
    public const PROFILE_TAX_YEAR = 'tax.year_documents';

    public function moduleCode(): string
    {
        return 'tax_declaration_helper';
    }

    public function entityTypes(): array
    {
        return [
            new DocumentEntityType(self::ENTITY_YEAR, 'tax_declaration_helper', 'Année fiscale'),
        ];
    }

    public function importProfiles(): array
    {
        return [
            new DocumentImportProfile(
                self::PROFILE_TAX_YEAR,
                'tax_declaration_helper',
                'tax_documents_tab',
                self::ENTITY_YEAR,
                'tax',
                ['tax', 'tax.property_tax', 'tax.cfe', 'charges', 'works.invoice', 'bank', 'other', 'inbox'],
                ['tax_year'],
                true
            ),
        ];
    }

    public function createEntityResolver(EditorialDatabase $database): DocumentEntityResolver
    {
        return new class ($database) implements DocumentEntityResolver {
            public function __construct(private readonly EditorialDatabase $database)
            {
            }

            public function supportedEntityTypes(): array
            {
                return [TaxDocumentIntegration::ENTITY_YEAR];
            }

            public function entityExists(string $entityType, string $entityId): bool
            {
                $ref = $this->parseRef($entityType, $entityId);
                if ($ref === null) {
                    return false;
                }

                try {
                    $statement = $this->database->pdo()->prepare(sprintf(
                        'SELECT COUNT(*) FROM `%s` WHERE `id` = :id AND `status` = \'active\'',
                        $this->database->table('private_users')
                    ));
                    $statement->execute(['id' => $ref['user_id']]);

                    return (int) $statement->fetchColumn() > 0;
                } catch (\Throwable) {
                    return false;
                }
            }

            public function userCanAccessEntity(int $privateUserId, string $entityType, string $entityId): bool
            {
                $ref = $this->parseRef($entityType, $entityId);
                if ($ref === null || $ref['user_id'] !== $privateUserId) {
                    return false;
                }

                try {
                    $statement = $this->database->pdo()->prepare(sprintf(
                        'SELECT COUNT(*) FROM `%s` WHERE `id` = :id AND `status` = \'active\'',
                        $this->database->table('private_users')
                    ));
                    $statement->execute(['id' => $privateUserId]);

                    return (int) $statement->fetchColumn() > 0;
                } catch (\Throwable) {
                    return false;
                }
            }

            public function entityLabel(string $entityType, string $entityId): string
            {
                $ref = $this->parseRef($entityType, $entityId);
                if ($ref === null) {
                    return '';
                }

                return 'Année fiscale ' . $ref['year'];
            }

            /**
             * @return array{user_id: int, year: int}|null
             */
            private function parseRef(string $entityType, string $entityId): ?array
            {
                if ($entityType !== TaxDocumentIntegration::ENTITY_YEAR) {
                    return null;
                }

                if (preg_match('/\A([1-9][0-9]{0,10})-([0-9]{4})\z/', $entityId, $matches) !== 1) {
                    return null;
                }

                $userId = (int) $matches[1];
                $year = (int) $matches[2];
                if ($userId <= 0 || $year < 2000 || $year > 2100) {
                    return null;
                }

                return ['user_id' => $userId, 'year' => $year];
            }
        };
    }
}
