<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Caramagnols\PrivateApps\Documents\Repository\DocumentHubRepository;
use Caramagnols\PrivateApps\Documents\Service\DocumentGarbageCollector;
use Caramagnols\PrivateApps\Documents\Service\DocumentHubCronNotificationService;
use Caramagnols\PrivateApps\Documents\Service\DocumentStorageService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

/**
 * Garbage collector du hub documentaire : purge des temporaires expirés et
 * inventaire des objets non référencés. La suppression physique des objets
 * exige --delete-unreferenced (à ne lancer qu'après sauvegarde vérifiée).
 */

$arguments = array_slice($argv ?? [], 1);
$deleteUnreferenced = in_array('--delete-unreferenced', $arguments, true);
$jsonOutput = in_array('--json', $arguments, true);
$help = in_array('--help', $arguments, true) || in_array('-h', $arguments, true);

if ($help) {
    echo "Usage: php backend/core/tools/document_hub_gc.php [--delete-unreferenced] [--json]\n";
    echo "Purge quarantaine/exports expirés ; liste les objets non référencés.\n";
    echo "--delete-unreferenced supprime physiquement ces objets (sauvegarde préalable obligatoire).\n";
    exit(0);
}

$database = editorial_database();
$collector = new DocumentGarbageCollector(
    new DocumentHubRepository($database),
    DocumentStorageService::fromAppConfig()
);

// Initialiser le service de notification
$notifier = null;
try {
    $notifier = DocumentHubCronNotificationService::fromAppConfig();
    $jobInfo = ['code' => 'document_hub_garbage_collection', 'name' => 'Document Hub Garbage Collection'];
    $notifier->notifyJobStarted($jobInfo, $deleteUnreferenced ? 'delete-unreferenced' : 'report-only');
} catch (\Throwable $e) {
    // Service de notification non disponible, continuer sans
}

$report = $collector->run($deleteUnreferenced);
$report['mode'] = $deleteUnreferenced ? 'delete-unreferenced' : 'report-only';
$report['generated_at'] = date('c');

// Notifier la fin
if ($notifier !== null) {
    $jobInfo = ['code' => 'document_hub_garbage_collection', 'name' => 'Document Hub Garbage Collection'];
    $hasDeletions = $deleteUnreferenced && $report['deleted_objects'] > 0;
    $exitCode = 0;
    if ($hasDeletions) {
        $notifier->notifyAlert('gc_deletion', 'Suppression d\'objets non référencés', sprintf('%d objets supprimés', $report['deleted_objects']));
    }
    $notifier->notifyJobSuccess($jobInfo, ['exit_code' => $exitCode, 'stdout_text' => '', 'duration_ms' => 0]);
}

if ($jsonOutput) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

echo "Garbage collector documentaire — mode {$report['mode']}\n";
printf("Quarantaine purgée : %d fichier(s)\n", $report['quarantine_purged']);
printf("Exports temporaires purgés : %d fichier(s)\n", $report['exports_purged']);
printf("Objets non référencés : %d\n", count($report['unreferenced_objects']));
printf("Objets non référencés trop récents : %d\n", $report['young_unreferenced_objects']);
printf("Objets supprimés : %d\n", $report['deleted_objects']);
if (!$deleteUnreferenced && $report['unreferenced_objects'] !== []) {
    echo "Aucune suppression : relancer avec --delete-unreferenced après sauvegarde.\n";
}

exit(0);
