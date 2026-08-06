<?php
/**
 * Script pour configurer les jobs cron du Document Hub dans la table cron_jobs.
 * Ces jobs sont nécessaires pour les phases 11 et 12 du plan d'implémentation.
 *
 * Usage:
 *   php backend/core/tools/configure_document_hub_cron_jobs.php [--dry-run] [--json]
 *
 * Options:
 *   --dry-run    Mode simulation, affiche ce qui serait ajouté sans écrire
 *   --json       Sortie au format JSON
 *   --force      Remplacer les jobs existants avec les mêmes codes
 */

require_once __DIR__ . '/../bootstrap.php';

use Caramagnols\Cron\CronJobRepository;
use Caramagnols\Database\EditorialDatabase;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$arguments = $argv ?? [];
array_shift($arguments);

$options = [
    'dry-run' => false,
    'json' => false,
    'force' => false,
    'help' => false,
];

foreach ($arguments as $arg) {
    $arg = trim($arg);
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
    echo "Usage: php backend/core/tools/configure_document_hub_cron_jobs.php [OPTIONS]\n\n";
    echo "Configures cron jobs for Document Hub maintenance and backup.\n\n";
    echo "Options:\n";
    echo "  --dry-run    Dry run mode, show what would be added\n";
    echo "  --json       JSON output format\n";
    echo "  --force      Replace existing jobs with same codes\n";
    echo "  --help, -h   Show this help message\n";
    exit(0);
}

$database = editorial_database();
$jobRepository = new CronJobRepository($database);

// Définition des jobs cron pour le Document Hub
$documentHubJobs = [
    [
        'code' => 'document_hub_maintenance',
        'name' => 'Maintenance Document Hub',
        'description' => 'Exécute l\'intégrité, la garbage collection report-only et la purge de corbeille en simulation pour le Document Hub.',
        'script_path' => 'core/tools/document_hub_maintenance.php',
        'arguments' => ['args' => ['--json', '--dry-run']],
        'schedule_expression' => '30 2 * * *', // Tous les jours à 2h30
        'timeout_seconds' => 1800, // 30 minutes
        'status' => 'active',
    ],
    [
        'code' => 'document_hub_integrity_check',
        'name' => 'Vérification Intégrité Document Hub',
        'description' => 'Vérifie quotidiennement l\'intégrité des objets et documents sans modification ni recalcul SHA-256 complet.',
        'script_path' => 'core/tools/document_hub_integrity.php',
        'arguments' => ['args' => ['--json']],
        'schedule_expression' => '15 3 * * *', // Tous les jours à 3h15
        'timeout_seconds' => 600, // 10 minutes
        'status' => 'active',
    ],
    [
        'code' => 'document_hub_garbage_collection',
        'name' => 'Garbage Collection Document Hub',
        'description' => 'Nettoyage report-only des fichiers temporaires expirés et inventaire des objets non référencés.',
        'script_path' => 'core/tools/document_hub_gc.php',
        'arguments' => ['args' => ['--json']],
        'schedule_expression' => '45 2 * * *', // Tous les jours à 2h45
        'timeout_seconds' => 900, // 15 minutes
        'status' => 'active',
    ],
    [
        'code' => 'document_hub_backup',
        'name' => 'Backup Document Hub',
        'description' => 'Sauvegarde des tables et fichiers du Document Hub. Génère une archive complète avec manifestes et checksums.',
        'script_path' => 'core/tools/document_hub_backup.php',
        'arguments' => ['args' => ['--json']],
        'schedule_expression' => '0 1 * * *', // Tous les jours à 1h00
        'timeout_seconds' => 3600, // 1 heure
        'status' => 'active',
    ],
];

$report = [
    'started_at' => date('c'),
    'mode' => $options['dry-run'] ? 'dry-run' : 'live',
    'jobs_to_add' => [],
    'jobs_added' => 0,
    'jobs_skipped' => 0,
    'jobs_replaced' => 0,
    'errors' => [],
];

// Vérifier les jobs existants
$existingJobs = [];
try {
    $allJobs = $jobRepository->listJobs(true);
    foreach ($allJobs as $job) {
        $existingJobs[$job['code']] = $job;
    }
} catch (\Throwable $e) {
    $report['errors'][] = 'Failed to list existing jobs: ' . $e->getMessage();
}

// Traiter chaque job
foreach ($documentHubJobs as $jobDefinition) {
    $code = $jobDefinition['code'];

    if (isset($existingJobs[$code])) {
        if ($options['force']) {
            // Remplacer le job existant
            if ($options['dry-run']) {
                $report['jobs_to_add'][] = [
                    'action' => 'replace',
                    'code' => $code,
                    'current' => $existingJobs[$code],
                    'new' => $jobDefinition,
                ];
                $report['jobs_replaced']++;
            } else {
                try {
                    $jobRepository->saveJob($jobDefinition);
                    $report['jobs_replaced']++;
                    $report['jobs_to_add'][] = [
                        'action' => 'replaced',
                        'code' => $code,
                    ];
                } catch (\Throwable $e) {
                    $report['errors'][] = "Failed to update job {$code}: " . $e->getMessage();
                }
            }
        } else {
            // Sauter le job existant
            $report['jobs_skipped']++;
            $report['jobs_to_add'][] = [
                'action' => 'skipped',
                'code' => $code,
                'reason' => 'already_exists',
                'current' => $existingJobs[$code],
            ];
        }
    } else {
        // Ajouter un nouveau job
        if ($options['dry-run']) {
            $report['jobs_to_add'][] = [
                'action' => 'add',
                'code' => $code,
                'job' => $jobDefinition,
            ];
            $report['jobs_added']++;
        } else {
            try {
                $jobRepository->saveJob($jobDefinition);
                $report['jobs_added']++;
                $report['jobs_to_add'][] = [
                    'action' => 'added',
                    'code' => $code,
                ];
            } catch (\Throwable $e) {
                $report['errors'][] = "Failed to add job {$code}: " . $e->getMessage();
            }
        }
    }
}

$report['finished_at'] = date('c');

// Sortie
if ($options['json']) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit(!empty($report['errors']) ? 1 : 0);
}

echo "Document Hub Cron Jobs Configuration Report\n";
echo str_repeat('=', 70) . "\n";
echo "Mode: " . $report['mode'] . "\n";
echo "Started: " . $report['started_at'] . "\n";
echo "Finished: " . $report['finished_at'] . "\n\n";

if ($options['dry-run']) {
    echo "DRY-RUN: No changes made to database\n\n";
}

echo "Summary:\n";
echo "  Jobs added: " . $report['jobs_added'] . "\n";
echo "  Jobs skipped (already exist): " . $report['jobs_skipped'] . "\n";
echo "  Jobs replaced: " . $report['jobs_replaced'] . "\n";
echo "  Errors: " . count($report['errors']) . "\n\n";

if (!empty($report['errors'])) {
    echo "Errors:\n";
    foreach ($report['errors'] as $error) {
        echo "  - {$error}\n";
    }
    echo "\n";
}

echo "Jobs processed:\n";
foreach ($report['jobs_to_add'] as $jobInfo) {
    $action = $jobInfo['action'];
    $code = $jobInfo['code'];

    echo "  [{$action}] {$code}\n";

    if ($action === 'skipped' && isset($jobInfo['current'])) {
        $current = $jobInfo['current'];
        echo "    Current: " . ($current['schedule_expression'] ?? 'N/A') . " - " . ($current['status'] ?? 'N/A') . "\n";
    } elseif ($action === 'add' || $action === 'replace') {
        $job = $jobInfo['job'] ?? $jobInfo['new'] ?? [];
        echo "    Schedule: " . ($job['schedule_expression'] ?? 'N/A') . "\n";
        echo "    Script: " . ($job['script_path'] ?? 'N/A') . "\n";
        echo "    Timeout: " . ($job['timeout_seconds'] ?? 0) . "s\n";
    }
    echo "\n";
}

if ($report['jobs_added'] > 0 || $report['jobs_replaced'] > 0) {
    echo "\n✅ Configuration completed successfully!\n";
    echo "   Run 'composer cron-center' to verify the jobs are active.\n";
} elseif ($report['jobs_skipped'] > 0) {
    echo "\nℹ️  All jobs already exist. Use --force to replace them.\n";
}

exit(!empty($report['errors']) ? 1 : 0);
