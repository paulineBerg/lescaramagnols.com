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
$now = null;
$privateUserId = null;
$warningAfterDays = 20;

if (in_array('--help', $args, true)) {
    echo "Usage: php backend/core/tools/purge_private_account_deletion_backups.php [--dry-run] [--json] [--quiet] [--now=DATE|TIMESTAMP] [--user-id=ID] [--warning-after-days=20]\n";
    echo "Envoie les avertissements J+20, puis supprime les comptes et sauvegardes à J+30.\n";
    echo "--now permet une recette preproduction J+20/J+30 sans modifier l'horloge serveur.\n";
    echo "--user-id limite la commande a un compte de test donne.\n";
    exit(0);
}

try {
    foreach ($args as $arg) {
        if (in_array($arg, ['--dry-run', '--json', '--quiet'], true)) {
            continue;
        }

        if (str_starts_with($arg, '--now=')) {
            $value = trim(substr($arg, strlen('--now=')));
            $parsed = ctype_digit($value) ? (int) $value : strtotime($value);
            if ($value === '' || $parsed === false || $parsed <= 0) {
                throw new \InvalidArgumentException('Option --now invalide.');
            }
            $now = (int) $parsed;

            continue;
        }

        if (str_starts_with($arg, '--user-id=')) {
            $value = trim(substr($arg, strlen('--user-id=')));
            if ($value === '' || !ctype_digit($value) || (int) $value <= 0) {
                throw new \InvalidArgumentException('Option --user-id invalide.');
            }
            $privateUserId = (int) $value;

            continue;
        }

        if (str_starts_with($arg, '--warning-after-days=')) {
            $value = trim(substr($arg, strlen('--warning-after-days=')));
            if ($value === '' || !ctype_digit($value) || (int) $value <= 0) {
                throw new \InvalidArgumentException('Option --warning-after-days invalide.');
            }
            $warningAfterDays = (int) $value;

            continue;
        }

        throw new \InvalidArgumentException('Option inconnue: ' . $arg);
    }

    $service = new PrivateDataProtectionService(editorial_database());
    $warnings = $service->sendPendingDeletionWarnings($dryRun, $now, $warningAfterDays, $privateUserId);
    $purge = $service->cleanupExpiredDeletionBackups($dryRun, $now, $privateUserId);
    $result = [
        'warnings' => $warnings,
        'purge' => $purge,
        'scope' => [
            'now' => $now !== null ? date('c', $now) : null,
            'private_user_id' => $privateUserId,
            'warning_after_days' => $warningAfterDays,
        ],
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
            'scope_private_user_id' => $privateUserId,
            'scope_now' => $now !== null ? date('c', $now) : null,
        ]
    );

    if ($jsonOutput) {
        echo json_encode(
            ['ok' => true] + $result,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . PHP_EOL;
    } elseif (!$quiet && $dryRun) {
        echo sprintf(
            "%d avertissement(s) eligible(s), %d sauvegarde(s) eligible(s) a la purge.\n",
            (int) $warnings['matched'],
            (int) $purge['matched']
        );
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
