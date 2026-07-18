<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivateApps\Documents\Contract\DocumentEntityResolver;
use Caramagnols\PrivateApps\Documents\Contract\DocumentEntityType;
use Caramagnols\PrivateApps\Documents\Contract\DocumentImportProfile;
use Caramagnols\PrivateApps\Documents\Contract\DocumentIntegration;

/**
 * Intégration documentaire du module Documents lui-même : l'espace personnel
 * d'un utilisateur est une entité (« user.personal ») dont il est le seul
 * membre autorisé.
 */
final class PersonalDocumentIntegration implements DocumentIntegration
{
    public const ENTITY_PERSONAL = 'user.personal';
    public const PROFILE_PERSONAL = 'user.personal_documents';

    public function moduleCode(): string
    {
        return 'documents';
    }

    public function entityTypes(): array
    {
        return [
            new DocumentEntityType(self::ENTITY_PERSONAL, 'documents', 'Espace personnel'),
        ];
    }

    public function importProfiles(): array
    {
        return [
            new DocumentImportProfile(
                self::PROFILE_PERSONAL,
                'documents',
                'personal_documents',
                self::ENTITY_PERSONAL,
                '',
                [],
                [],
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
                return [PersonalDocumentIntegration::ENTITY_PERSONAL];
            }

            public function entityExists(string $entityType, string $entityId): bool
            {
                if ($entityType !== PersonalDocumentIntegration::ENTITY_PERSONAL || !ctype_digit($entityId)) {
                    return false;
                }

                try {
                    $statement = $this->database->pdo()->prepare(sprintf(
                        'SELECT COUNT(*) FROM `%s` WHERE `id` = :id AND `status` = \'active\'',
                        $this->database->table('private_users')
                    ));
                    $statement->execute(['id' => (int) $entityId]);

                    return (int) $statement->fetchColumn() > 0;
                } catch (\Throwable) {
                    return false;
                }
            }

            public function userCanAccessEntity(int $privateUserId, string $entityType, string $entityId): bool
            {
                return $entityType === PersonalDocumentIntegration::ENTITY_PERSONAL
                    && ctype_digit($entityId)
                    && (int) $entityId === $privateUserId;
            }

            public function entityLabel(string $entityType, string $entityId): string
            {
                return 'Espace personnel';
            }
        };
    }
}
