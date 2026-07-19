<?php

/**
 * Script de maintenance combiné pour le Document Hub.
 * Exécute : intégrité, garbage collection, purge de corbeille.
 *
 * Usage:
 *   php backend/core/tools/document_hub_maintenance.php [--dry-run] [--json] [--no-gc] [--no-integrity] [--no-trash]
 *
 * Options:
 *   --dry-run     Mode simulation, aucune écriture
 *   --json        Sortie au format JSON
 *   --no-gc       Désactive la garbage collection
 *   --no-integrity Désactive le contrôle d'intégrité
 *   --no-trash     Désactive la purge de corbeille
 *   --delete-unreferenced  Supprime les objets non référencés (necessite --no-dry-run)
 *   --help, -h     Affiche cette aide
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Caramagnols\PrivateApps\Documents\Repository\DocumentHubRepository;
use Caramagnols\PrivateApps\Documents\Repository\DocumentTaxonomyRepository;
use Caramagnols\PrivateApps\Documents\Service\DocumentGarbageCollector;
use Caramagnols\PrivateApps\Documents\Service\DocumentHubCronNotificationService;
use Caramagnols\PrivateApps\Documents\Service\DocumentStorageService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$arguments = $argv ?? [];
array_shift($arguments); // Supprimer le nom du script

$options = [
    'dry-run' => false,
    'json' => false,
    'no-gc' => false,
    'no-integrity' => false,
    'no-trash' => false,
    'delete-unreferenced' => false,
    'help' => false,
];

foreach ($arguments as $arg) {
    if (isset($options[$arg])) {
        $options[$arg] = true;
    } elseif (str_starts_with($arg, '--')) {
        $optionName = substr($arg, 2);
        if (isset($options[$optionName])) {
            $options[$optionName] = true;
        }
    }
}

if ($options['help']) {
    echo "Usage: php backend/core/tools/document_hub_maintenance.php [OPTIONS]\n\n";
    echo "Options:\n";
    echo "  --dry-run              Mode simulation, aucune écriture\n";
    echo "  --json                 Sortie au format JSON\n";
    echo "  --no-gc                Désactive la garbage collection\n";
    echo "  --no-integrity          Désactive le contrôle d'intégrité\n";
    echo "  --no-trash              Désactive la purge de corbeille\n";
    echo "  --delete-unreferenced   Supprime les objets non référencés (nécessite --no-dry-run)\n";
    echo "  --help, -h              Affiche cette aide\n";
    exit(0);
}

$database = editorial_database();
$hubRepository = new DocumentHubRepository($database);
$taxonomyRepository = new DocumentTaxonomyRepository($database);
$storage = DocumentStorageService::fromAppConfig();
$garbageCollector = new DocumentGarbageCollector($hubRepository, $storage);

// Initialiser le service de notification
$notifier = null;
try {
    $notifier = DocumentHubCronNotificationService::fromAppConfig();
    $jobInfo = ['code' => 'document_hub_maintenance', 'name' => 'Document Hub Maintenance'];
    $notifier->notifyJobStarted($jobInfo, $options['dry-run'] ? 'dry-run' : 'normal');
} catch (\Throwable $e) {
    // Service de notification non disponible, continuer sans
}

/**
 * @var array<string, mixed> $report
*/
$report = [
    'started_at' => date('c'),
    'mode' => $options['dry-run'] ? 'dry-run' : 'live',
    'sections' => [],
    'errors' => [],
    'warnings' => [],
];

// 1. Contrôle d'intégrité (si activé)
if (!$options['no-integrity']) {
    $report['sections']['integrity'] = runIntegrityCheck($hubRepository, $storage, $options);
}

// 2. Garbage Collection (si activé)
if (!$options['no-gc']) {
    $report['sections']['garbage_collection'] = runGarbageCollection(
        $garbageCollector,
        $options['dry-run'] ? false : $options['delete-unreferenced']
    );
}

// 3. Purge de corbeille (si activé)
if (!$options['no-trash']) {
    $report['sections']['trash_purge'] = purgeExpiredTrash(
        $hubRepository,
        $taxonomyRepository,
        $options['dry-run']
    );
}

$report['finished_at'] = date('c');

// Calculer la durée
$startTime = strtotime($report['started_at']);
$endTime = strtotime($report['finished_at']);
$report['duration_ms'] = ($endTime - $startTime) * 1000;

// Notifier la fin
if ($notifier !== null) {
    $jobInfo = ['code' => 'document_hub_maintenance', 'name' => 'Document Hub Maintenance'];
    if (!empty($report['errors'])) {
        $notifier->notifyJobFailure($jobInfo, ['exit_code' => 1, 'stdout_text' => '', 'duration_ms' => $report['duration_ms']]);
    } else {
        $notifier->notifyJobSuccess($jobInfo, ['exit_code' => 0, 'stdout_text' => '', 'duration_ms' => $report['duration_ms']]);
    }
}

// Sortie
if ($options['json']) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    $hasErrors = !empty($report['errors']);
    exit($hasErrors ? 1 : 0);
}

echo "Document Hub Maintenance Report\n";
echo str_repeat('-', 60) . "\n";
echo "Mode: " . $report['mode'] . "\n";
echo "Started: " . $report['started_at'] . "\n";
echo "Finished: " . $report['finished_at'] . "\n\n";

foreach ($report['sections'] as $sectionName => $sectionData) {
    echo ucfirst(str_replace('_', ' ', $sectionName)) . ":\n";
    echo str_repeat('-', 40) . "\n";
    foreach ($sectionData as $key => $value) {
        if (is_array($value)) {
            echo "  {$key}: " . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "  {$key}: {$value}\n";
        }
    }
    echo "\n";
}

if (!empty($report['warnings'])) {
    echo "Avertissements:\n";
    foreach ($report['warnings'] as $warning) {
        echo "  - {$warning}\n";
    }
    echo "\n";
}

if (!empty($report['errors'])) {
    echo "Erreurs:\n";
    foreach ($report['errors'] as $error) {
        echo "  - {$error}\n";
    }
    exit(1);
}

echo "Maintenance terminée avec succès.\n";
exit(0);

/**
 * Exécute le contrôle d'intégrité.
 */
function runIntegrityCheck(
    DocumentHubRepository $repository,
    DocumentStorageService $storage,
    array $options
): array {
    $report = [
        'checked' => 0,
        'objects_without_file' => 0,
        'files_without_object' => 0,
        'invalid_hashes' => 0,
        'documents_without_object' => 0,
        'orphan_links' => 0,
        'stuck_jobs' => 0,
    ];

    try {
        $pdo = $repository->database()->pdo();

        // 1. Vérifier les objets avec fichiers manquants
        $objects = $repository->allObjects();
        foreach ($objects as $object) {
            $report['checked']++;
            $storageKey = (string) ($object['storage_key'] ?? '');
            $absolutePath = $storage->absolutePathForKey($storageKey);
            if ($absolutePath === null || !is_file($absolutePath)) {
                $report['objects_without_file']++;
            }
        }

        // 2. Vérifier les fichiers sans objets (scan du stockage)
        $objectsPath = $storage->rootPath() . '/objects';
        if (is_dir($objectsPath)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($objectsPath, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            $allSha256s = [];
            foreach ($repository->allObjects() as $obj) {
                $allSha256s[] = (string) ($obj['sha256'] ?? '');
            }
            foreach ($files as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $path = $file->getPathname();
                if (preg_match('/objects\/sha256\/([a-f0-9]{2})\/([a-f0-9]{2})\/([a-f0-9]{64})/', $path, $m)) {
                    $sha256 = $m[1] . $m[2] . $m[3];
                    if (!in_array($sha256, $allSha256s, true)) {
                        $report['files_without_object']++;
                    }
                }
            }
        }

        // 3. Vérifier les documents sans objet
        $documents = $repository->listDocuments([], PHP_INT_MAX, 0);
        foreach ($documents as $doc) {
            $objectId = (int) ($doc['object_id'] ?? 0);
            if ($objectId <= 0) {
                $report['documents_without_object']++;
            } elseif ($repository->findObjectById($objectId) === null) {
                $report['documents_without_object']++;
            }
        }

        // 4. Vérifier les liens orphelins
        $linksTable = $repository->linksTable();
        $documentsTable = $repository->documentsTable();
        $stmt = $pdo->query(
            sprintf(
                'SELECT COUNT(*) as cnt FROM `%s` l LEFT JOIN `%s` d ON d.`id` = l.`document_id` WHERE d.`id` IS NULL',
                $linksTable, $documentsTable
            )
        );
        if ($stmt !== false) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $report['orphan_links'] = (int) ($row['cnt'] ?? 0);
        }

        // 5. Vérifier les jobs bloqués
        $jobsTable = $repository->jobsTable();
        $stmt = $pdo->query(
            sprintf(
                "SELECT * FROM `%s` WHERE `status` IN ('quarantined', 'validating', 'processing')",
                $jobsTable
            )
        );
        if ($stmt !== false) {
            $stuckJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($stuckJobs as $job) {
                $createdAt = $job['created_at'] ?? '';
                if ($createdAt !== '') {
                    $created = new \DateTime($createdAt);
                    $now = new \DateTime();
                    $diff = $now->diff($created);
                    $hours = (int) ($diff->h + ($diff->days * 24));
                    if ($hours > 24) {
                        $report['stuck_jobs']++;
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        $report['error'] = $e->getMessage();
    }

    return $report;
}

/**
 * Exécute la garbage collection.
 */
function runGarbageCollection(
    DocumentGarbageCollector $collector,
    bool $deleteUnreferenced
): array {
    return $collector->run($deleteUnreferenced);
}

/**
 * Purge les documents dans la corbeille dont la rétention est expirée.
 */
function purgeExpiredTrash(
    DocumentHubRepository $repository,
    DocumentTaxonomyRepository $taxonomy,
    bool $dryRun
): array {
    $report = [
        'purged_count' => 0,
        'checked_count' => 0,
        'errors' => [],
    ];

    try {
        $categories = $taxonomy->listActive();
        $categoryRetention = [];
        foreach ($categories as $category) {
            $code = (string) ($category['code'] ?? '');
            $retentionDays = $category['retention_days'] ?? null;
            if ($retentionDays !== null && $retentionDays > 0) {
                $categoryRetention[$code] = (int) $retentionDays;
            }
        }

        $trashedDocuments = $repository->listDocuments(['status' => 'trashed'], PHP_INT_MAX, 0);
        $now = new \DateTimeImmutable();
        $defaultGracePeriodDays = 30;

        foreach ($trashedDocuments as $document) {
            $report['checked_count']++;
            $documentId = (int) ($document['id'] ?? 0);
            if ($documentId <= 0) {
                continue;
            }

            $categoryCode = (string) ($document['category_code'] ?? 'inbox');
            $trashedAt = $document['trashed_at'] ?? null;
            if (empty($trashedAt)) {
                continue;
            }

            try {
                $trashedDate = new \DateTimeImmutable($trashedAt);
            } catch (\Exception) {
                continue;
            }

            $retentionDays = $categoryRetention[$categoryCode] ?? $defaultGracePeriodDays;
            $expirationDate = $trashedDate->add(new \DateInterval("P{$retentionDays}D"));
            $legalHold = (bool) ($document['legal_hold'] ?? false);

            if ($now >= $expirationDate && !$legalHold) {
                if (!$dryRun) {
                    if ($repository->transitionStatus($documentId, 'deleted')) {
                        $report['purged_count']++;
                    } else {
                        $report['errors'][] = "Failed to delete document {$documentId}";
                    }
                } else {
                    $report['purged_count']++;
                }
            }
        }
    } catch (\Throwable $e) {
        $report['errors'][] = 'Exception: ' . $e->getMessage();
    }

    return $report;
}
