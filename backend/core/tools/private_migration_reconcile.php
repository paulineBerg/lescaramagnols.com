#!/usr/bin/env php
<?php

declare(strict_types=1);

use Caramagnols\PrivatePortal\Operations\PrivateBackupService;
use Caramagnols\PrivatePortal\Operations\PrivateLegacyRetirementService;
use Caramagnols\PrivatePortal\Operations\PrivateMigrationDefinitionOfDoneService;
use Caramagnols\PrivatePortal\Operations\PrivateModuleMigrationPlanService;
use Caramagnols\PrivatePortal\Operations\PrivateMigrationService;
use Caramagnols\PrivatePortal\Operations\PrivateSecurityChecklistService;
use Caramagnols\PrivatePortal\Documents\PrivateDocumentStorage;
use Caramagnols\PrivatePortal\Http\PrivateRouteResolver;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;

require_once dirname(__DIR__) . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$args = array_slice($argv ?? [], 1);
$command = (string) ($args[0] ?? 'help');

if ($command === 'help' || in_array('--help', $args, true)) {
    fwrite(STDOUT, <<<TXT
Usage:
  php backend/core/tools/private_migration_reconcile.php snapshot [--files-root=/path] [--output=/path/snapshot.json]
  php backend/core/tools/private_migration_reconcile.php backup [--target-dir=/path] [--files-root=/path] [--output=/path/result.json]
  php backend/core/tools/private_migration_reconcile.php compare /path/left.json /path/right.json
  php backend/core/tools/private_migration_reconcile.php status [module] [status] [--actor=email] [--notes=texte]
  php backend/core/tools/private_migration_reconcile.php read-legacy module [--output=/path/model.json]
  php backend/core/tools/private_migration_reconcile.php import module /path/private-backup.json [--apply]
  php backend/core/tools/private_migration_reconcile.php verify-backup /path/private-backup.json|zip [--output=/path/verify.json]
  php backend/core/tools/private_migration_reconcile.php m5-plan [module] [--output=/path/plan.json]
  php backend/core/tools/private_migration_reconcile.php m6-retirement [--output=/path/inventory.json]
  php backend/core/tools/private_migration_reconcile.php security-checklist [--output=/path/security.json]
  php backend/core/tools/private_migration_reconcile.php migration-dod [--output=/path/dod.json]

Par defaut, l'import est un dry-run. L'option --apply est refusee si le module n'est pas au statut migrating.

TXT);
    exit(0);
}

try {
    $backupService = new PrivateBackupService(editorial_database());
    $moduleRegistry = new PrivateModuleRegistry();
    $migrationService = new PrivateMigrationService(editorial_database(), $moduleRegistry);

    if ($command === 'snapshot') {
        $snapshot = $backupService->reconciliationSnapshot(optionValue($args, '--files-root') ?? '');
        writeJsonResult($snapshot, optionValue($args, '--output'));
        exit(0);
    }

    if ($command === 'backup') {
        $storage = PrivateDocumentStorage::fromAppConfig();
        $targetDirectory = optionValue($args, '--target-dir') ?? $storage->exportsDirectory();
        $filesRoot = optionValue($args, '--files-root') ?? $storage->uploadsDirectory();
        $backup = $backupService->createBackup($targetDirectory, $filesRoot);
        writeJsonResult($backup, optionValue($args, '--output'));
        exit(($backup['success'] ?? false) === true ? 0 : 1);
    }

    if ($command === 'compare') {
        $left = readJsonFile((string) ($args[1] ?? ''));
        $right = readJsonFile((string) ($args[2] ?? ''));
        writeJsonResult($backupService->compareSnapshots($left, $right), null);
        exit(0);
    }

    if ($command === 'status') {
        $module = trim((string) ($args[1] ?? ''));
        $status = trim((string) ($args[2] ?? ''));
        if ($module === '') {
            writeJsonResult($migrationService->moduleStatuses(), null);
            exit(0);
        }
        if ($status === '') {
            writeJsonResult($migrationService->moduleStatus($module), null);
            exit(0);
        }
        writeJsonResult(
            $migrationService->setModuleStatus(
                $module,
                $status,
                optionValue($args, '--actor') ?? 'cli',
                optionValue($args, '--notes') ?? ''
            ),
            null
        );
        exit(0);
    }

    if ($command === 'read-legacy') {
        $module = (string) ($args[1] ?? '');
        writeJsonResult($migrationService->readLegacyModel($module), optionValue($args, '--output'));
        exit(0);
    }

    if ($command === 'import') {
        $module = (string) ($args[1] ?? '');
        $backupPath = (string) ($args[2] ?? '');
        $payload = readJsonFile($backupPath);
        writeJsonResult($migrationService->importBackupModule($module, $payload, !in_array('--apply', $args, true)), null);
        exit(0);
    }

    if ($command === 'verify-backup') {
        $path = (string) ($args[1] ?? '');
        $verification = $backupService->verifyBackup($path);
        $restore = $backupService->restoreBackup($path, true);
        writeJsonResult([
            'verification' => $verification,
            'restoreDryRun' => $restore,
        ], optionValue($args, '--output'));
        exit(empty($verification['valid']) || empty($restore['success']) ? 1 : 0);
    }

    if ($command === 'm5-plan') {
        $privateConfig = (array) app_config('private', []);
        $basePath = is_string($privateConfig['base_path'] ?? null) ? (string) $privateConfig['base_path'] : 'private';
        $planService = new PrivateModuleMigrationPlanService(
            $moduleRegistry,
            new PrivateRouteResolver($basePath),
            $migrationService
        );
        $readiness = $planService->readiness(is_string($args[1] ?? null) ? (string) $args[1] : null);
        writeJsonResult($readiness, optionValue($args, '--output'));
        exit(($readiness['success'] ?? false) === true && ($readiness['ready'] ?? false) === true ? 0 : 1);
    }

    if ($command === 'm6-retirement') {
        $privateConfig = (array) app_config('private', []);
        $basePath = is_string($privateConfig['base_path'] ?? null) ? (string) $privateConfig['base_path'] : 'private';
        $retirementService = new PrivateLegacyRetirementService(
            new PrivateRouteResolver($basePath),
            $moduleRegistry
        );
        $inventory = $retirementService->inventory();
        writeJsonResult($inventory, optionValue($args, '--output'));
        exit(($inventory['success'] ?? false) === true && ($inventory['ready'] ?? false) === true ? 0 : 1);
    }

    if ($command === 'security-checklist') {
        $privateConfig = (array) app_config('private', []);
        $basePath = is_string($privateConfig['base_path'] ?? null) ? (string) $privateConfig['base_path'] : 'private';
        $routeResolver = new PrivateRouteResolver($basePath);
        $planService = new PrivateModuleMigrationPlanService(
            $moduleRegistry,
            $routeResolver,
            $migrationService
        );
        $retirementService = new PrivateLegacyRetirementService(
            $routeResolver,
            $moduleRegistry
        );
        $checklist = (new PrivateSecurityChecklistService(
            $moduleRegistry,
            $routeResolver,
            $planService,
            $retirementService
        ))->checklist();
        writeJsonResult($checklist, optionValue($args, '--output'));
        exit(($checklist['success'] ?? false) === true && ($checklist['ready'] ?? false) === true ? 0 : 1);
    }

    if ($command === 'migration-dod') {
        $privateConfig = (array) app_config('private', []);
        $basePath = is_string($privateConfig['base_path'] ?? null) ? (string) $privateConfig['base_path'] : 'private';
        $routeResolver = new PrivateRouteResolver($basePath);
        $planService = new PrivateModuleMigrationPlanService(
            $moduleRegistry,
            $routeResolver,
            $migrationService
        );
        $retirementService = new PrivateLegacyRetirementService(
            $routeResolver,
            $moduleRegistry
        );
        $securityService = new PrivateSecurityChecklistService(
            $moduleRegistry,
            $routeResolver,
            $planService,
            $retirementService
        );
        $definitionOfDone = (new PrivateMigrationDefinitionOfDoneService(
            $moduleRegistry,
            $routeResolver,
            $planService,
            $retirementService,
            $securityService
        ))->checklist();
        writeJsonResult($definitionOfDone, optionValue($args, '--output'));
        exit(($definitionOfDone['success'] ?? false) === true && ($definitionOfDone['ready'] ?? false) === true ? 0 : 1);
    }

    throw new RuntimeException(sprintf('Commande inconnue: %s', $command));
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * @param array<int, string> $args
 */
function optionValue(array $args, string $name): ?string
{
    foreach ($args as $arg) {
        if (str_starts_with($arg, $name . '=')) {
            return substr($arg, strlen($name) + 1);
        }
    }

    return null;
}

/**
 * @return array<string, mixed>
 */
function readJsonFile(string $path): array
{
    $path = trim($path);
    if ($path === '' || !is_file($path) || !is_readable($path)) {
        throw new RuntimeException(sprintf('JSON introuvable ou illisible: %s', $path));
    }

    $content = file_get_contents($path);
    $decoded = is_string($content) ? json_decode($content, true) : null;
    if (!is_array($decoded)) {
        throw new RuntimeException(sprintf('JSON invalide: %s', $path));
    }

    return $decoded;
}

/**
 * @param mixed $payload
 */
function writeJsonResult(mixed $payload, ?string $outputPath): void
{
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        throw new RuntimeException('Encodage JSON impossible.');
    }

    if ($outputPath !== null && trim($outputPath) !== '') {
        $directory = dirname($outputPath);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Dossier de sortie indisponible: %s', $directory));
        }
        file_put_contents($outputPath, $json . PHP_EOL);
        fwrite(STDOUT, $outputPath . PHP_EOL);

        return;
    }

    fwrite(STDOUT, $json . PHP_EOL);
}
