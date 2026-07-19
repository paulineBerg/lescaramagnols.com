<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Service;

use Caramagnols\PrivateApps\Documents\Repository\DocumentHubRepository;

/**
 * Purge prudente : fichiers temporaires expirés (quarantaine, exports) et
 * inventaire des objets non référencés. Par défaut, ne supprime jamais un
 * objet : la suppression physique exige le mode explicite et ne touche
 * jamais un objet encore référencé par un document ou une version.
 */
final class DocumentGarbageCollector
{
    public function __construct(
        private readonly DocumentHubRepository $repository,
        private readonly DocumentStorageService $storage
    ) {
    }

    /**
     * @return array{quarantine_purged: int, exports_purged: int, unreferenced_objects: array<int, array{id: int, sha256: string, storage_key: string}>, deleted_objects: int}
     */
    public function run(bool $deleteUnreferencedObjects = false, int $quarantineMaxAgeSeconds = 86400, int $exportsMaxAgeSeconds = 3600): array
    {
        $report = [
            'quarantine_purged' => $this->storage->purgeQuarantine($quarantineMaxAgeSeconds),
            'exports_purged' => $this->storage->purgeExpiredExports($exportsMaxAgeSeconds),
            'unreferenced_objects' => [],
            'deleted_objects' => 0,
        ];

        foreach ($this->repository->allObjects() as $object) {
            $objectId = (int) ($object['id'] ?? 0);
            if ($objectId <= 0) {
                continue;
            }

            if ($this->repository->objectReferenceCount($objectId) > 0) {
                continue;
            }

            $entry = [
                'id' => $objectId,
                'sha256' => (string) ($object['sha256'] ?? ''),
                'storage_key' => (string) ($object['storage_key'] ?? ''),
            ];
            $report['unreferenced_objects'][] = $entry;

            if ($deleteUnreferencedObjects) {
                // Revérification juste avant suppression pour limiter les courses.
                if ($this->repository->objectReferenceCount($objectId) === 0
                    && $this->storage->deleteUnreferencedObjectFile($entry['storage_key'])
                ) {
                    $report['deleted_objects']++;
                }
            }
        }

        return $report;
    }
}
