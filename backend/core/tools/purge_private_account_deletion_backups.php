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
$startedAt = microtime(true);
$startedAtIso = date('c');
$lockHandle = null;

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

    $lockHandle = acquire_private_account_deletion_cron_lock();
    if ($lockHandle === null) {
        $payload = [
            'ok' => true,
            'success' => true,
            'locked' => true,
            'started_at' => $startedAtIso,
            'finished_at' => date('c'),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'message' => 'Purge comptes privés déjà en cours.',
        ];

        app_event_logger()->security('private.account_deletion_backups_purge.locked', [
            'dry_run' => $dryRun,
            'scope_private_user_id' => $privateUserId,
            'scope_now' => $now !== null ? date('c', $now) : null,
        ], 'warning');

        if ($jsonOutput) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        } elseif (!$quiet) {
            echo "Purge comptes privés déjà en cours.\n";
        }

        exit(0);
    }

    app_event_logger()->security('private.account_deletion_backups_purge.started', [
        'dry_run' => $dryRun,
        'scope_private_user_id' => $privateUserId,
        'scope_now' => $now !== null ? date('c', $now) : null,
        'warning_after_days' => $warningAfterDays,
    ]);

    $service = new PrivateDataProtectionService(editorial_database());
    $warnings = $service->sendPendingDeletionWarnings($dryRun, $now, $warningAfterDays, $privateUserId);
    $purge = $service->cleanupExpiredDeletionBackups($dryRun, $now, $privateUserId);
    $errors = ((int) $warnings['errors']) + ((int) $purge['errors']);
    $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
    $result = [
        'ok' => $errors === 0,
        'success' => $errors === 0,
        'locked' => false,
        'started_at' => $startedAtIso,
        'finished_at' => date('c'),
        'duration_ms' => $durationMs,
        'warnings' => $warnings,
        'purge' => $purge,
        'scope' => [
            'now' => $now !== null ? date('c', $now) : null,
            'private_user_id' => $privateUserId,
            'warning_after_days' => $warningAfterDays,
        ],
    ];

    $eventName = $errors > 0
        ? 'private.account_deletion_backups_purge.failed'
        : ($dryRun ? 'private.account_deletion_backups_purge.dry_run' : 'private.account_deletion_backups_purge.completed');

    app_event_logger()->security(
        $eventName,
        [
            'duration_ms' => $durationMs,
            'dry_run' => $dryRun,
            'warnings_matched' => $warnings['matched'],
            'warnings_sent' => $warnings['sent'],
            'purge_matched' => $purge['matched'],
            'purged' => $purge['purged'],
            'backup_deleted' => $purge['backup_deleted'],
            'errors' => $errors,
            'root' => $purge['root'],
            'scope_private_user_id' => $privateUserId,
            'scope_now' => $now !== null ? date('c', $now) : null,
        ],
        $errors > 0 ? 'error' : 'info'
    );

    if ($jsonOutput) {
        echo json_encode(
            $result,
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

    release_private_account_deletion_cron_lock($lockHandle);
    exit($errors > 0 ? 2 : 0);
} catch (\Throwable $exception) {
    app_event_logger()->security('private.account_deletion_backups_purge.failed', [
        'dry_run' => $dryRun,
        'scope_private_user_id' => $privateUserId,
        'scope_now' => $now !== null ? date('c', $now) : null,
        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        'error' => $exception->getMessage(),
    ], 'error');

    if ($jsonOutput) {
        echo json_encode(
            [
                'ok' => false,
                'success' => false,
                'locked' => false,
                'started_at' => $startedAtIso,
                'finished_at' => date('c'),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => $exception->getMessage(),
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . PHP_EOL;
    } elseif (!$quiet) {
        fwrite(STDERR, 'Erreur purge sauvegardes suppression comptes prives: ' . $exception->getMessage() . PHP_EOL);
    }

    release_private_account_deletion_cron_lock($lockHandle);
    exit(1);
}

/**
 * @return resource|null
 */
function acquire_private_account_deletion_cron_lock()
{
    $directory = ROOT_PATH . '/var/locks';
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new \RuntimeException('Impossible de créer le dossier de verrous privés.');
    }

    $handle = fopen($directory . '/private-account-deletion-backups.lock', 'c');
    if (!is_resource($handle)) {
        throw new \RuntimeException('Impossible d’ouvrir le verrou de purge comptes privés.');
    }

    if (!flock($handle, LOCK_EX | LOCK_NB)) {
        fclose($handle);

        return null;
    }

    return $handle;
}

function release_private_account_deletion_cron_lock(mixed $handle): void
{
    if (!is_resource($handle)) {
        return;
    }

    flock($handle, LOCK_UN);
    fclose($handle);
}
