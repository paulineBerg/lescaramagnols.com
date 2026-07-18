<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Contract;

/**
 * Profil d'import déclaratif : chaque emplacement d'import (onglet, écran) référence
 * un profil ; le composant d'import et le service central n'embarquent aucune
 * logique métier spécifique codée en dur.
 */
final class DocumentImportProfile
{
    /**
     * @param string $code Code unique du profil (ex. « rental.documents »).
     * @param string $moduleCode Code du module propriétaire.
     * @param string $importSource Source consignée dans les jobs d'import.
     * @param string $contextEntityType Type d'entité principal du contexte (code contrôlé).
     * @param string $defaultCategoryCode Catégorie proposée par défaut (taxonomie globale).
     * @param array<int, string> $allowedCategoryCodes Restriction éventuelle des catégories proposées ([] = toutes).
     * @param array<int, string> $requiredContextFields Champs de contexte obligatoires (ex. ['property_id']).
     * @param bool $allowMultiple Import multi-fichiers autorisé.
     */
    public function __construct(
        public readonly string $code,
        public readonly string $moduleCode,
        public readonly string $importSource,
        public readonly string $contextEntityType,
        public readonly string $defaultCategoryCode,
        public readonly array $allowedCategoryCodes = [],
        public readonly array $requiredContextFields = [],
        public readonly bool $allowMultiple = true
    ) {
        if (preg_match('/\A[a-z0-9_.]{3,96}\z/', $code) !== 1) {
            throw new \InvalidArgumentException('Code de profil d\'import invalide : ' . $code);
        }
    }
}
