<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Contract;

/**
 * Référence à une entité métier (type contrôlé + identifiant + rôle du lien).
 */
final class DocumentEntityRef
{
    public const DEFAULT_ROLE = 'attachment';

    public function __construct(
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly string $linkRole = self::DEFAULT_ROLE
    ) {
        if (trim($entityType) === '' || trim($entityId) === '') {
            throw new \InvalidArgumentException('Référence d\'entité documentaire incomplète.');
        }

        if (preg_match('/\A[A-Za-z0-9._-]{1,64}\z/', $entityId) !== 1) {
            throw new \InvalidArgumentException('Identifiant d\'entité documentaire invalide.');
        }

        if (preg_match('/\A[a-z0-9_]{1,32}\z/', $linkRole) !== 1) {
            throw new \InvalidArgumentException('Rôle de lien documentaire invalide.');
        }
    }

    public static function of(string $entityType, int|string $entityId, string $linkRole = self::DEFAULT_ROLE): self
    {
        return new self($entityType, (string) $entityId, $linkRole);
    }
}
