<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Registry;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivateApps\Documents\Contract\DocumentEntityResolver;
use Caramagnols\PrivateApps\Documents\Contract\DocumentEntityType;
use Caramagnols\PrivateApps\Documents\Contract\DocumentImportProfile;
use Caramagnols\PrivateApps\Documents\Contract\DocumentIntegration;
use Caramagnols\PrivateApps\Documents\Contract\ProvidesDocumentIntegration;
use Caramagnols\PrivatePortal\PrivateAppRegistry;

/**
 * Registre des intégrations documentaires : consomme le PrivateAppRegistry
 * statique et retient les manifestes implémentant ProvidesDocumentIntegration.
 * Une webapp future rejoint le hub sans aucune modification ici.
 */
final class DocumentIntegrationRegistry
{
    /** @var array<string, DocumentIntegration>|null */
    private static ?array $integrations = null;

    /** @var array<string, DocumentEntityType>|null */
    private static ?array $entityTypes = null;

    /** @var array<string, DocumentImportProfile>|null */
    private static ?array $profiles = null;

    /**
     * @return array<string, DocumentIntegration> indexées par moduleCode
     */
    public static function all(): array
    {
        if (self::$integrations !== null) {
            return self::$integrations;
        }

        self::$integrations = [];
        foreach (PrivateAppRegistry::all() as $moduleCode => $manifest) {
            if (!$manifest instanceof ProvidesDocumentIntegration) {
                continue;
            }

            $integration = $manifest->documentIntegration();
            if ($integration->moduleCode() !== $moduleCode) {
                throw new \RuntimeException(sprintf(
                    'Intégration documentaire incohérente : le manifeste %s déclare le module %s.',
                    $moduleCode,
                    $integration->moduleCode()
                ));
            }

            self::$integrations[$moduleCode] = $integration;
        }

        self::validate(self::$integrations);

        return self::$integrations;
    }

    /**
     * @return array<string, DocumentEntityType> indexés par code de type
     */
    public static function entityTypes(): array
    {
        if (self::$entityTypes === null) {
            self::$entityTypes = [];
            foreach (self::all() as $integration) {
                foreach ($integration->entityTypes() as $entityType) {
                    self::$entityTypes[$entityType->code] = $entityType;
                }
            }
        }

        return self::$entityTypes;
    }

    /**
     * @return array<string, DocumentImportProfile> indexés par code de profil
     */
    public static function importProfiles(): array
    {
        if (self::$profiles === null) {
            self::$profiles = [];
            foreach (self::all() as $integration) {
                foreach ($integration->importProfiles() as $profile) {
                    self::$profiles[$profile->code] = $profile;
                }
            }
        }

        return self::$profiles;
    }

    public static function isKnownEntityType(string $entityTypeCode): bool
    {
        return isset(self::entityTypes()[$entityTypeCode]);
    }

    public static function profile(string $profileCode): ?DocumentImportProfile
    {
        return self::importProfiles()[$profileCode] ?? null;
    }

    /**
     * Resolver responsable d'un type d'entité, ou null si le type est inconnu.
     */
    public static function resolverForEntityType(string $entityTypeCode, EditorialDatabase $database): ?DocumentEntityResolver
    {
        $entityType = self::entityTypes()[$entityTypeCode] ?? null;
        if ($entityType === null) {
            return null;
        }

        $integration = self::all()[$entityType->moduleCode] ?? null;
        if ($integration === null) {
            return null;
        }

        $resolver = $integration->createEntityResolver($database);
        if (!in_array($entityTypeCode, $resolver->supportedEntityTypes(), true)) {
            return null;
        }

        return $resolver;
    }

    /**
     * @param array<string, DocumentIntegration> $integrations
     */
    private static function validate(array $integrations): void
    {
        $entityTypeCodes = [];
        $profileCodes = [];

        foreach ($integrations as $moduleCode => $integration) {
            foreach ($integration->entityTypes() as $entityType) {
                if ($entityType->moduleCode !== $moduleCode) {
                    throw new \RuntimeException(sprintf(
                        'Le type d\'entité %s appartient au module %s mais est déclaré par %s.',
                        $entityType->code,
                        $entityType->moduleCode,
                        $moduleCode
                    ));
                }

                if (isset($entityTypeCodes[$entityType->code])) {
                    throw new \RuntimeException('Type d\'entité documentaire dupliqué : ' . $entityType->code);
                }
                $entityTypeCodes[$entityType->code] = true;
            }

            foreach ($integration->importProfiles() as $profile) {
                if ($profile->moduleCode !== $moduleCode) {
                    throw new \RuntimeException(sprintf(
                        'Le profil %s appartient au module %s mais est déclaré par %s.',
                        $profile->code,
                        $profile->moduleCode,
                        $moduleCode
                    ));
                }

                if (isset($profileCodes[$profile->code])) {
                    throw new \RuntimeException('Profil d\'import documentaire dupliqué : ' . $profile->code);
                }
                $profileCodes[$profile->code] = true;
            }
        }

        // Les types de contexte des profils doivent exister dans le registre global.
        $knownTypes = [];
        foreach ($integrations as $integration) {
            foreach ($integration->entityTypes() as $entityType) {
                $knownTypes[$entityType->code] = true;
            }
        }

        foreach ($integrations as $integration) {
            foreach ($integration->importProfiles() as $profile) {
                if ($profile->contextEntityType !== '' && !isset($knownTypes[$profile->contextEntityType])) {
                    throw new \RuntimeException(sprintf(
                        'Le profil %s référence un type de contexte inconnu : %s.',
                        $profile->code,
                        $profile->contextEntityType
                    ));
                }
            }
        }
    }

    /**
     * Réinitialise le cache (tests).
     */
    public static function reset(): void
    {
        self::$integrations = null;
        self::$entityTypes = null;
        self::$profiles = null;
    }
}
