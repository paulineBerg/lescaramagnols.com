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
 * Garbage collector du hub documentaire. Par défaut, produit un rapport sans
 * suppression runtime. Les suppressions exigent --apply et une procédure
 * validée hors cron régulier.
 */

$arguments = array_slice($argv ?? [], 1);
$apply = in_array('--apply', $arguments, true) || in_array('--no-dry-run', $arguments, true);
$dryRun = !$apply || in_array('--dry-run', $arguments, true);
$deleteUnreferenced = !$dryRun && in_array('--delete-unreferenced', $arguments, true);
$jsonOutput = in_array('--json', $arguments, true);
$help = in_array('--help', $arguments, true) || in_array('-h', $arguments, true);

if ($help) {
    echo "Usage: php backend/core/tools/document_hub_gc.php [--dry-run] [--apply] [--delete-unreferenced] [--json]\n";
    echo "Par défaut: rapport seul, aucune suppression runtime.\n";
    echo "--apply purge les temporaires expirés; --delete-unreferenced exige aussi --apply.\n";
    exit(0);
}

try {
    $database = editorial_database();
    $collector = new DocumentGarbageCollector(
        new DocumentHubRepository($database),
        DocumentStorageService::fromAppConfig()
    );
} catch (\Throwable $exception) {
    $report = [
        'success' => false,
        'dry_run' => $dryRun,
        'mode' => $dryRun ? 'dry-run' : ($deleteUnreferenced ? 'delete-unreferenced' : 'apply'),
        'delete_unreferenced_requested' => in_array('--delete-unreferenced', $arguments, true),
        'error' => sanitizeDocumentHubGcError($exception->getMessage()),
        'generated_at' => date('c'),
    ];

    if ($jsonOutput) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    } else {
        fwrite(STDERR, '[ERROR] Document Hub GC indisponible: ' . $report['error'] . "\n");
    }

    exit(1);
}

// Initialiser le service de notification
$notifier = null;
try {
    $notifier = DocumentHubCronNotificationService::fromAppConfig();
    $jobInfo = ['code' => 'document_hub_garbage_collection', 'name' => 'Document Hub Garbage Collection'];
    $notifier->notifyJobStarted($jobInfo, $dryRun ? 'dry-run' : ($deleteUnreferenced ? 'delete-unreferenced' : 'apply'));
} catch (\Throwable) {
    // Service de notification non disponible, continuer sans.
}

$report = $collector->run($deleteUnreferenced, 86400, 3600, $dryRun);
$report['success'] = true;
$report['mode'] = $dryRun ? 'dry-run' : ($deleteUnreferenced ? 'delete-unreferenced' : 'apply');
$report['delete_unreferenced_requested'] = in_array('--delete-unreferenced', $arguments, true);
if ($dryRun && $report['delete_unreferenced_requested']) {
    $report['warning'] = '--delete-unreferenced ignoré en dry-run; utiliser --apply uniquement avec procédure validée.';
}
$report['generated_at'] = date('c');

// Notifier la fin
if ($notifier !== null) {
    try {
        $jobInfo = ['code' => 'document_hub_garbage_collection', 'name' => 'Document Hub Garbage Collection'];
        $hasDeletions = !$dryRun && $deleteUnreferenced && $report['deleted_objects'] > 0;
        $exitCode = 0;
        if ($hasDeletions) {
            $notifier->notifyAlert('gc_deletion', 'Suppression d\'objets non référencés', sprintf('%d objets supprimés', $report['deleted_objects']));
        }
        $notifier->notifyJobSuccess($jobInfo, ['exit_code' => $exitCode, 'stdout_text' => '', 'duration_ms' => 0]);
    } catch (\Throwable) {
        // La notification ne doit jamais casser le rapport GC.
    }
}

if ($jsonOutput) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

echo "Garbage collector documentaire — mode {$report['mode']}\n";
printf("Quarantaine éligible : %d fichier(s)\n", $report['quarantine_eligible']);
printf("Exports temporaires éligibles : %d fichier(s)\n", $report['exports_eligible']);
printf("Quarantaine purgée : %d fichier(s)\n", $report['quarantine_purged']);
printf("Exports temporaires purgés : %d fichier(s)\n", $report['exports_purged']);
printf("Objets non référencés : %d\n", count($report['unreferenced_objects']));
printf("Objets non référencés trop récents : %d\n", $report['young_unreferenced_objects']);
printf("Objets supprimés : %d\n", $report['deleted_objects']);
if ($dryRun) {
    echo "Aucune suppression : dry-run par défaut du cron régulier.\n";
} elseif (!$deleteUnreferenced && $report['unreferenced_objects'] !== []) {
    echo "Aucune suppression d'objet : --delete-unreferenced exige une procédure validée.\n";
}

exit(0);

function sanitizeDocumentHubGcError(string $message): string
{
    return (string) preg_replace('#/home/[A-Za-z0-9._-]+(?:/[^\s"\'<>]*)?#', '[path]', $message);
}
