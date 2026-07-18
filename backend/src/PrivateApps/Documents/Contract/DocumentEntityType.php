<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Contract;

/**
 * Type d'entité métier pouvant recevoir des documents.
 * Le code est namespacé par module (« rental.property », « user.personal »)
 * et validé contre le registre avant toute création de lien.
 */
final class DocumentEntityType
{
    private const CODE_PATTERN = '/\A[a-z0-9_]+\.[a-z0-9_]+\z/';

    public function __construct(
        public readonly string $code,
        public readonly string $moduleCode,
        public readonly string $label
    ) {
        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            throw new \InvalidArgumentException(
                'Code de type d\'entité documentaire invalide (attendu "module.entite") : ' . $code
            );
        }
    }
}
