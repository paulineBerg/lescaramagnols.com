<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Service;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivateApps\Documents\Contract\DocumentEntityRef;
use Caramagnols\PrivateApps\Documents\Contract\DocumentEntityResolver;
use Caramagnols\PrivateApps\Documents\Registry\DocumentIntegrationRegistry;
use Caramagnols\PrivateApps\Documents\Repository\DocumentHubRepository;

/**
 * Rattachements documents <-> entités métier. Toute création de lien vérifie :
 * type contrôlé par le registre, existence réelle de l'entité et autorisation
 * de l'utilisateur sur l'entité. L'autorisation d'accéder à un document se
 * vérifie sur le document logique et ses liens, jamais sur l'objet physique.
 */
final class DocumentLinkService
{
    /** @var array<string, DocumentEntityResolver|null> */
    private array $resolverCache = [];

    /** @var array<string, bool> mémo autorisation par (utilisateur, type, id) pour éviter les N+1 sur les listes */
    private array $accessCache = [];

    public function __construct(
        private readonly DocumentHubRepository $repository,
        private readonly EditorialDatabase $database
    ) {
    }

    /**
     * @return string|null code d'erreur, null en cas de succès
     */
    public function attach(int $documentId, DocumentEntityRef $ref, int $privateUserId): ?string
    {
        $resolver = $this->resolverFor($ref->entityType);
        if ($resolver === null) {
            return 'unknown_entity_type';
        }

        if (!$resolver->entityExists($ref->entityType, $ref->entityId)) {
            return 'entity_not_found';
        }

        if (!$resolver->userCanAccessEntity($privateUserId, $ref->entityType, $ref->entityId)) {
            return 'entity_forbidden';
        }

        return $this->repository->addLink($documentId, $ref, $privateUserId) ? null : 'link_failed';
    }

    /**
     * @return string|null code d'erreur, null en cas de succès
     */
    public function detach(int $documentId, DocumentEntityRef $ref, int $privateUserId): ?string
    {
        $resolver = $this->resolverFor($ref->entityType);
        if ($resolver === null) {
            return 'unknown_entity_type';
        }

        if (!$resolver->userCanAccessEntity($privateUserId, $ref->entityType, $ref->entityId)) {
            return 'entity_forbidden';
        }

        $links = $this->repository->linksForDocument($documentId);
        if (count($links) <= 1) {
            // Un document doit garder au moins un rattachement ; utiliser la
            // corbeille pour le retirer complètement.
            return 'last_link';
        }

        return $this->repository->removeLink($documentId, $ref) ? null : 'link_not_found';
    }

    /**
     * L'utilisateur peut-il accéder à ce document (lecture/téléchargement) ?
     * Vrai s'il en est le créateur ou s'il a accès à au moins une entité liée.
     */
    public function userCanAccessDocument(array $document, int $privateUserId): bool
    {
        if ((int) ($document['created_by'] ?? 0) === $privateUserId) {
            return true;
        }

        $documentId = (int) ($document['id'] ?? 0);
        if ($documentId <= 0) {
            return false;
        }

        $links = is_array($document['links'] ?? null)
            ? $document['links']
            : $this->repository->linksForDocument($documentId);

        foreach ($links as $link) {
            $entityType = is_string($link['entity_type'] ?? null) ? (string) $link['entity_type'] : '';
            $entityId = is_string($link['entity_id'] ?? null) ? (string) $link['entity_id'] : (string) ($link['entity_id'] ?? '');
            if ($entityType === '' || $entityId === '') {
                continue;
            }

            if ($this->cachedEntityAccess($privateUserId, $entityType, $entityId)) {
                return true;
            }
        }

        return false;
    }

    private function cachedEntityAccess(int $privateUserId, string $entityType, string $entityId): bool
    {
        $cacheKey = $privateUserId . '|' . $entityType . '|' . $entityId;
        if (!array_key_exists($cacheKey, $this->accessCache)) {
            $resolver = $this->resolverFor($entityType);
            $this->accessCache[$cacheKey] = $resolver !== null
                && $resolver->userCanAccessEntity($privateUserId, $entityType, $entityId);
        }

        return $this->accessCache[$cacheKey];
    }

    /**
     * Libellés lisibles des liens d'un document pour l'UI/les exports.
     *
     * @param array<int, array<string, mixed>> $links
     * @return array<int, array{entity_type: string, entity_id: string, link_role: string, label: string}>
     */
    public function describeLinks(array $links): array
    {
        $described = [];
        foreach ($links as $link) {
            $entityType = (string) ($link['entity_type'] ?? '');
            $entityId = (string) ($link['entity_id'] ?? '');
            $resolver = $entityType !== '' ? $this->resolverFor($entityType) : null;
            $described[] = [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'link_role' => (string) ($link['link_role'] ?? DocumentEntityRef::DEFAULT_ROLE),
                'label' => $resolver !== null ? $resolver->entityLabel($entityType, $entityId) : '',
            ];
        }

        return $described;
    }

    public function resolverFor(string $entityType): ?DocumentEntityResolver
    {
        if (!array_key_exists($entityType, $this->resolverCache)) {
            $this->resolverCache[$entityType] = DocumentIntegrationRegistry::resolverForEntityType($entityType, $this->database);
        }

        return $this->resolverCache[$entityType];
    }
}
