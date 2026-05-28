#!/usr/bin/env php
<?php

declare(strict_types=1);

use Caramagnols\PrivatePortal\Operations\PrivateDataProtectionService;

require_once dirname(__DIR__) . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$args = array_slice($argv ?? [], 1);
$jsonOutput = in_array('--json', $args, true);
$quiet = in_array('--quiet', $args, true);
$dryRun = in_array('--dry-run', $args, true);

if (in_array('--help', $args, true)) {
    echo "Usage: php backend/core/tools/purge_private_account_deletion_backups.php [--dry-run] [--json] [--quiet]\n";
    echo "Envoie les avertissements J+20, puis supprime les comptes et sauvegardes à J+30.\n";
    exit(0);
}

try {
    $service = new PrivateDataProtectionService(editorial_database());
    $warnings = $service->sendPendingDeletionWarnings($dryRun);
    $purge = $service->cleanupExpiredDeletionBackups($dryRun);
    $result = [
        'warnings' => $warnings,
        'purge' => $purge,
    ];

    app_event_logger()->security(
        $dryRun ? 'private.account_deletion_backups_purge_dry_run' : 'private.account_deletion_backups_purged',
        [
            'warnings_matched' => $warnings['matched'],
            'warnings_sent' => $warnings['sent'],
            'purge_matched' => $purge['matched'],
            'purged' => $purge['purged'],
            'backup_deleted' => $purge['backup_deleted'],
            'errors' => ((int) $warnings['errors']) + ((int) $purge['errors']),
            'root' => $purge['root'],
        ]
    );

    if ($jsonOutput) {
        echo json_encode(
            ['ok' => true] + $result,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . PHP_EOL;
    } elseif (!$quiet) {
        echo sprintf(
            "%d avertissement(s) envoye(s), %d compte(s) purge(s), %d sauvegarde(s) supprimee(s).\n",
            (int) $warnings['sent'],
            (int) $purge['purged'],
            (int) $purge['backup_deleted']
        );
    }

    exit(0);
} catch (\Throwable $exception) {
    if ($jsonOutput) {
        echo json_encode(
            [
                'ok' => false,
                'error' => $exception->getMessage(),
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . PHP_EOL;
    } elseif (!$quiet) {
        fwrite(STDERR, 'Erreur purge sauvegardes suppression comptes prives: ' . $exception->getMessage() . PHP_EOL);
    }

    exit(1);
}
