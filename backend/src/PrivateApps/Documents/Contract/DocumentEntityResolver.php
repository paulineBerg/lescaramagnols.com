<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Contract;

/**
 * Résolution d'entités d'un module : existence, autorisation d'accès et libellé.
 * Chaque module propriétaire d'entités documentaires fournit son implémentation ;
 * le hub ne crée jamais de lien sans passer par ce contrat.
 */
interface DocumentEntityResolver
{
    /**
     * Codes de types d'entités pris en charge par ce resolver.
     *
     * @return array<int, string>
     */
    public function supportedEntityTypes(): array;

    /**
     * L'entité existe-t-elle réellement ?
     */
    public function entityExists(string $entityType, string $entityId): bool;

    /**
     * L'utilisateur privé a-t-il accès à cette entité (lecture des documents liés) ?
     * L'autorisation porte toujours sur l'entité métier, jamais sur l'objet physique partagé.
     */
    public function userCanAccessEntity(int $privateUserId, string $entityType, string $entityId): bool;

    /**
     * Libellé lisible pour l'UI et les exports (ex. « Villa Carena »). Chaîne vide si inconnu.
     */
    public function entityLabel(string $entityType, string $entityId): string;
}
