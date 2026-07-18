<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Caramagnols\PrivateApps\Documents\Registry\DocumentIntegrationRegistry;
use Caramagnols\PrivateApps\Documents\Repository\DocumentHubRepository;
use Caramagnols\PrivateApps\Documents\Service\DocumentHubCronNotificationService;
use Caramagnols\PrivateApps\Documents\Service\DocumentStorageService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

/**
 * Contrôle d'intégrité du hub documentaire. Rapport seul : aucune correction
 * ni suppression automatique. Les corrections destructives passent par
 * document_hub_gc.php en mode explicite, après sauvegarde.
 */

$arguments = array_slice($argv ?? [], 1);
$verifyHashes = in_array('--verify-hashes', $arguments, true);
$jsonOutput = in_array('--json', $arguments, true);
$help = in_array('--help', $arguments, true) || in_array('-h', $arguments, true);

if ($help) {
    echo "Usage: php backend/core/tools/document_hub_integrity.php [--verify-hashes] [--json]\n";
    echo "Rapport d'intégrité SQL <-> stockage du hub documentaire (lecture seule).\n";
    exit(0);
}

$database = editorial_database();
$repository = new DocumentHubRepository($database);
$storage = DocumentStorageService::fromAppConfig();

// Initialiser le service de notification
$notifier = null;
try {
    $notifier = DocumentHubCronNotificationService::fromAppConfig();
    $jobInfo = ['code' => 'document_hub_integrity_check', 'name' => 'Document Hub Integrity Check'];
    $notifier->notifyJobStarted($jobInfo, 'normal');
} catch (\Throwable $e) {
    // Service de notification non disponible, continuer sans
}

$report = [
    'generated_at' => date('c'),
    'objects_without_file' => [],
    'files_without_object' => [],
    'hash_mismatches' => [],
    'links_unknown_entity_type' => [],
    'links_missing_entity' => [],
    'stuck_jobs' => 0,
    'quarantine_files' => 0,
    'objects_checked' => 0,
];

// Objets SQL sans fichier + empreintes.
$knownStorageKeys = [];
foreach ($repository->allObjects() as $object) {
    $report['objects_checked']++;
    $storageKey = (string) ($object['storage_key'] ?? '');
    $knownStorageKeys[$storageKey] = true;
    $absolutePath = $storage->absolutePathForKey($storageKey);

    if ($absolutePath === null || !is_file($absolutePath)) {
        $report['objects_without_file'][] = [
            'id' => (int) ($object['id'] ?? 0),
            'sha256' => (string) ($object['sha256'] ?? ''),
            'storage_key' => $storageKey,
        ];
        continue;
    }

    if ($verifyHashes) {
        $actual = hash_file('sha256', $absolutePath);
        if (!is_string($actual) || $actual !== (string) ($object['sha256'] ?? '')) {
            $report['hash_mismatches'][] = [
                'id' => (int) ($object['id'] ?? 0),
                'expected' => (string) ($object['sha256'] ?? ''),
                'actual' => is_string($actual) ? $actual : 'unreadable',
            ];
        }
    }
}

// Fichiers présents sans ligne SQL.
$objectsRoot = $storage->rootPath() . '/objects/sha256';
if (is_dir($objectsRoot)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($objectsRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        $relative = 'objects/sha256' . str_replace('\\', '/', substr($file->getPathname(), strlen($objectsRoot)));
        if (!isset($knownStorageKeys[$relative])) {
            $report['files_without_object'][] = $relative;
        }
    }
}

// Liens vers types inconnus ou entités absentes.
try {
    $statement = $database->pdo()->query(sprintf(
        'SELECT DISTINCT `entity_type`, `entity_id` FROM `%s`',
        $repository->linksTable()
    ));
    $links = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable) {
    $links = [];
}

foreach (is_array($links) ? $links : [] as $link) {
    $entityType = (string) ($link['entity_type'] ?? '');
    $entityId = (string) ($link['entity_id'] ?? '');
    $resolver = DocumentIntegrationRegistry::resolverForEntityType($entityType, $database);
    if ($resolver === null) {
        $report['links_unknown_entity_type'][] = $entityType;
        continue;
    }

    if (!$resolver->entityExists($entityType, $entityId)) {
        $report['links_missing_entity'][] = ['entity_type' => $entityType, 'entity_id' => $entityId];
    }
}
$report['links_unknown_entity_type'] = array_values(array_unique($report['links_unknown_entity_type']));

// Jobs bloqués (démarrés il y a plus d'une heure, jamais terminés).
try {
    $statement = $database->pdo()->query(sprintf(
        'SELECT COUNT(*) FROM `%s`
         WHERE `status` IN (\'quarantined\', \'validating\', \'processing\')
           AND `created_at` < DATE_SUB(NOW(), INTERVAL 1 HOUR)',
        $repository->jobsTable()
    ));
    $report['stuck_jobs'] = $statement !== false ? (int) $statement->fetchColumn() : 0;
} catch (Throwable) {
    $report['stuck_jobs'] = -1;
}

$quarantineEntries = @scandir($storage->quarantineDirectory());
$report['quarantine_files'] = is_array($quarantineEntries) ? max(0, count($quarantineEntries) - 2) : 0;

$hasFindings = $report['objects_without_file'] !== []
    || $report['files_without_object'] !== []
    || $report['hash_mismatches'] !== []
    || $report['links_unknown_entity_type'] !== []
    || $report['links_missing_entity'] !== []
    || $report['stuck_jobs'] > 0;

// Notifier la fin
if ($notifier !== null) {
    $jobInfo = ['code' => 'document_hub_integrity_check', 'name' => 'Document Hub Integrity Check'];
    $exitCode = $hasFindings ? 1 : 0;
    if ($hasFindings) {
        $notifier->notifyJobFailure($jobInfo, ['exit_code' => $exitCode, 'stdout_text' => '', 'duration_ms' => 0]);
    } else {
        $notifier->notifyJobSuccess($jobInfo, ['exit_code' => $exitCode, 'stdout_text' => '', 'duration_ms' => 0]);
    }
}

if ($jsonOutput) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
} else {
    echo "Contrôle d'intégrité du hub documentaire — " . $report['generated_at'] . "\n";
    echo str_repeat('-', 60) . "\n";
    printf("Objets vérifiés            : %d\n", $report['objects_checked']);
    printf("Objets SQL sans fichier    : %d\n", count($report['objects_without_file']));
    printf("Fichiers sans objet SQL    : %d\n", count($report['files_without_object']));
    printf("Empreintes invalides       : %s\n", $verifyHashes ? (string) count($report['hash_mismatches']) : 'non vérifiées (--verify-hashes)');
    printf("Types d'entité inconnus    : %d\n", count($report['links_unknown_entity_type']));
    printf("Liens vers entités absentes: %d\n", count($report['links_missing_entity']));
    printf("Jobs bloqués (>1h)         : %d\n", $report['stuck_jobs']);
    printf("Fichiers en quarantaine    : %d\n", $report['quarantine_files']);
    if ($hasFindings) {
        echo "\nDétails : relancer avec --json. Aucune correction automatique n'a été appliquée.\n";
    }
}

exit($hasFindings ? 1 : 0);
