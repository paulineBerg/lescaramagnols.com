<?php

declare(strict_types=1);

/**
 * Script de sauvegarde dédié pour le Document Hub.
 *
 * Usage:
 *   php backend/core/tools/document_hub_backup.php [--target=/chemin/vers/dossier] [--json] [--dry-run] [--include-derivatives]
 *
 * Options:
 *   --target        Dossier de destination (défaut: private/storage/backups/document-hub)
 *   --json         Sortie au format JSON
 *   --dry-run      Mode simulation, aucune écriture
 *   --include-derivatives  Inclure les fichiers dérivés (miniatures)
 *   --quiet, -q     Mode silencieux
 *   --help, -h      Affiche cette aide
 */

require_once __DIR__ . '/../bootstrap.php';

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivateApps\Documents\Repository\DocumentHubRepository;
use Caramagnols\PrivateApps\Documents\Repository\DocumentTaxonomyRepository;
use Caramagnols\PrivateApps\Documents\Service\DocumentHubBackupExtension;
use Caramagnols\PrivateApps\Documents\Service\DocumentHubCronNotificationService;
use Caramagnols\PrivateApps\Documents\Service\DocumentStorageService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$arguments = $argv ?? [];
array_shift($arguments); // Supprimer le nom du script

$options = [
    'target' => null,
    'json' => false,
    'dry-run' => false,
    'include-derivatives' => false,
    'quiet' => false,
    'help' => false,
];

$i = 0;
while ($i < count($arguments)) {
    $arg = $arguments[$i];

    if (isset($options[$arg])) {
        $options[$arg] = true;
        $i++;
        continue;
    }

    if (str_starts_with($arg, '--')) {
        $optionName = substr($arg, 2);
        if (isset($options[$optionName])) {
            $options[$optionName] = true;
            $i++;
            continue;
        }

        // Options avec valeurs
        if (str_contains($optionName, '=')) {
            [$name, $value] = explode('=', $optionName, 2);
            if (isset($options[$name])) {
                $options[$name] = $value;
                $i++;
                continue;
            }
        }
    }

    // Arguments avec valeurs séparées
    if ($arg === '--target' && isset($arguments[$i + 1])) {
        $options['target'] = $arguments[$i + 1];
        $i += 2;
        continue;
    }

    $i++;
}

if ($options['help']) {
    echo "Usage: php backend/core/tools/document_hub_backup.php [OPTIONS]\n\n";
    echo "Options:\n";
    echo "  --target=DIRECTORY       Dossier de destination\n";
    echo "  --json                  Sortie au format JSON\n";
    echo "  --dry-run               Mode simulation\n";
    echo "  --include-derivatives   Inclure les fichiers dérivés\n";
    echo "  --quiet, -q             Mode silencieux\n";
    echo "  --help, -h              Affiche cette aide\n";
    exit(0);
}

// Déterminer le dossier de destination
if ($options['target'] === null) {
    $config = function_exists('app_config') ? app_config('private.document_hub', []) : [];
    $storageRoot = is_string($config['storage_root_path'] ?? null)
        ? $config['storage_root_path']
        : ROOT_PATH . '/private/storage';
    $options['target'] = $storageRoot . '/backups/document-hub/' . date('Ymd');
}

// Normaliser le chemin
$target = rtrim(str_replace('\\', '/', trim($options['target'])), '/');

$database = editorial_database();
$hubRepository = new DocumentHubRepository($database);
$taxonomyRepository = new DocumentTaxonomyRepository($database);
$storage = DocumentStorageService::fromAppConfig();
$backupExtension = new DocumentHubBackupExtension($database, $storage, $hubRepository);

// Initialiser le service de notification
$notifier = null;
try {
    $notifier = DocumentHubCronNotificationService::fromAppConfig();
    $jobInfo = ['code' => 'document_hub_backup', 'name' => 'Document Hub Backup'];
    $notifier->notifyJobStarted($jobInfo, $options['dry-run'] ? 'dry-run' : 'normal');
} catch (\Throwable $e) {
    // Service de notification non disponible, continuer sans
}

// Exécuter la sauvegarde
$report = $backupExtension->createDocumentBackup($target, $options['include-derivatives']);

// Si dry-run, marquer comme tel
if ($options['dry-run']) {
    $report['mode'] = 'dry-run';
    $report['note'] = 'Aucune écriture effectuée en mode dry-run';
}

// Notifier la fin
if ($notifier !== null) {
    $jobInfo = ['code' => 'document_hub_backup', 'name' => 'Document Hub Backup'];
    $exitCode = $report['success'] ? 0 : 1;
    if ($report['success']) {
        $notifier->notifyJobSuccess($jobInfo, ['exit_code' => $exitCode, 'stdout_text' => '', 'duration_ms' => ($report['duration_seconds'] ?? 0) * 1000]);
    } else {
        $notifier->notifyJobFailure($jobInfo, ['exit_code' => $exitCode, 'stdout_text' => '', 'duration_ms' => ($report['duration_seconds'] ?? 0) * 1000, 'stderr_text' => $report['error'] ?? '']);
    }
}

// Sortie
if ($options['json']) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit($report['success'] ? 0 : 1);
}

if (!$options['quiet']) {
    echo "Document Hub Backup Report\n";
    echo str_repeat('=', 60) . "\n";

    if (isset($report['mode'])) {
        echo "Mode: {$report['mode']}\n";
    }
    echo "Target: {$target}\n";
    echo "Status: " . ($report['success'] ? 'SUCCESS' : 'FAILED') . "\n\n";

    if ($report['success']) {
        echo "Objects count: " . ($report['objects_count'] ?? 0) . "\n";
        echo "Objects size: " . formatBytes($report['objects_size_bytes'] ?? 0) . "\n";
        echo "Files backed up: " . ($report['files_backed_up'] ?? 0) . "\n";
        echo "Duration: " . ($report['duration_seconds'] ?? 0) . " seconds\n";
        echo "Manifest: " . ($report['manifest_path'] ?? '') . "\n";
        if (isset($report['checksums_path'])) {
            echo "Checksums: " . $report['checksums_path'] . "\n";
        }
        echo "Checksum: " . ($report['manifest_checksum'] ?? '') . "\n";
    } else {
        echo "Error: " . ($report['error'] ?? 'unknown') . "\n";
    }
    echo "\n";
}

exit($report['success'] ? 0 : 1);

/**
 * Formate des octets en taille lisible.
 */
function formatBytes(int $bytes, int $precision = 2): string
{
    if ($bytes === 0) {
        return '0 bytes';
    }

    $units = ['bytes', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));

    return round($bytes, $precision) . ' ' . $units[$pow];
}
