<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Caramagnols\PrivateApps\Documents\Contract\DocumentEntityRef;
use Caramagnols\PrivateApps\Documents\PersonalDocumentIntegration;
use Caramagnols\PrivateApps\Documents\PrivateDocumentStorage;
use Caramagnols\PrivateApps\Documents\Repository\DocumentHubRepository;
use Caramagnols\PrivateApps\Documents\Repository\DocumentTaxonomyRepository;
use Caramagnols\PrivateApps\Documents\Service\DocumentClassificationService;
use Caramagnols\PrivateApps\Documents\Service\DocumentHubCronNotificationService;
use Caramagnols\PrivateApps\Documents\Service\DocumentStorageService;
use Caramagnols\PrivateApps\RealEstateRental\RentalDocumentIntegration;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

/**
 * Migration idempotente des documents legacy (private_documents, rental_documents)
 * vers la bibliothèque centrale. Dry-run par défaut ; --apply pour écrire.
 * Les fichiers legacy ne sont JAMAIS supprimés par cet outil : ils sont copiés
 * vers le stockage CAS. La suppression des anciens fichiers reste une étape
 * séparée, après sauvegarde vérifiée et directive explicite.
 */

$arguments = array_slice($argv ?? [], 1);
$apply = in_array('--apply', $arguments, true);
$jsonOutput = in_array('--json', $arguments, true);
$help = in_array('--help', $arguments, true) || in_array('-h', $arguments, true);

if ($help) {
    echo "Usage: php backend/core/tools/document_hub_migrate.php [--apply] [--json]\n";
    echo "Sans option : dry-run (aucune écriture). --apply : exécute la migration.\n";
    exit(0);
}

$database = editorial_database();
$pdo = $database->pdo();
$hubRepository = new DocumentHubRepository($database);
$taxonomyRepository = new DocumentTaxonomyRepository($database);
$classification = new DocumentClassificationService($taxonomyRepository);
$hubStorage = DocumentStorageService::fromAppConfig();
$legacyStorage = PrivateDocumentStorage::fromAppConfig();

// Initialiser le service de notification
$notifier = null;
try {
    $notifier = DocumentHubCronNotificationService::fromAppConfig();
    $jobInfo = ['code' => 'document_hub_migrate', 'name' => 'Document Hub Migration'];
    $notifier->notifyJobStarted($jobInfo, $apply ? 'apply' : 'dry-run');
} catch (\Throwable $e) {
    // Service de notification non disponible, continuer sans
}

if ($apply) {
    $hubRepository->ensureSchema();
    $taxonomyRepository->seedSystemCategories();
}

/** @var array<string, mixed> $report */
$report = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'started_at' => date('c'),
    'sources' => [],
    'totals' => ['inventoried' => 0, 'migrated' => 0, 'already_migrated' => 0, 'missing_files' => 0, 'errors' => 0],
];

/**
 * @param array<int, DocumentEntityRef> $entityRefs
 */
$migrateOne = static function (
    string $source,
    string $legacyStoragePath,
    string $originalName,
    string $title,
    string $categoryGuessInput,
    ?string $documentDate,
    int $createdBy,
    array $entityRefs
) use (
    $apply,
    $hubRepository,
    $hubStorage,
    $legacyStorage,
    $classification,
    &$report
): void {
    $report['totals']['inventoried']++;
    if (!isset($report['sources'][$source]) || !is_array($report['sources'][$source])) {
        $report['sources'][$source] = [
            'inventoried' => 0,
            'already_migrated' => 0,
            'would_migrate' => 0,
            'migrated' => 0,
            'missing_files' => [],
            'errors' => [],
        ];
    }
    $sourceReport = &$report['sources'][$source];
    $sourceReport['inventoried'] = ($sourceReport['inventoried'] ?? 0) + 1;

    $absolutePath = $legacyStorage->absolutePath($legacyStoragePath);
    if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
        $report['totals']['missing_files']++;
        $sourceReport['missing_files'][] = $legacyStoragePath;

        return;
    }

    $sha256 = hash_file('sha256', $absolutePath);
    if (!is_string($sha256)) {
        $report['totals']['errors']++;

        return;
    }

    // Idempotence : un document du hub pointant sur ce contenu et rattaché à la
    // même entité principale signifie que cette ligne legacy est déjà migrée.
    $primaryRef = $entityRefs[0] ?? null;
    $existingObject = $hubRepository->findObjectBySha256($sha256);
    if ($existingObject !== null && $primaryRef instanceof DocumentEntityRef) {
        $existingDocuments = $hubRepository->documentsForEntity($primaryRef->entityType, $primaryRef->entityId, 500);
        foreach ($existingDocuments as $existingDocument) {
            if ((string) ($existingDocument['sha256'] ?? '') === $sha256) {
                $report['totals']['already_migrated']++;
                $sourceReport['already_migrated'] = ($sourceReport['already_migrated'] ?? 0) + 1;

                return;
            }
        }
    }

    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    $mimeType = '';
    if (extension_loaded('fileinfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($absolutePath);
        $mimeType = is_string($detected) ? strtolower(trim($detected)) : '';
    }
    if ($mimeType === '') {
        $mimeType = 'application/octet-stream';
    }

    $classified = $classification->classify(null, $categoryGuessInput, $originalName !== '' ? $originalName : $categoryGuessInput);
    $categoryCode = $classified['confidence'] >= DocumentClassificationService::SUGGEST_THRESHOLD
        ? $classified['category_code']
        : DocumentTaxonomyRepository::INBOX_CODE;

    if (!$apply) {
        $report['totals']['migrated']++;
        $sourceReport['would_migrate'] = ($sourceReport['would_migrate'] ?? 0) + 1;

        return;
    }

    // Copie (jamais de déplacement) vers la quarantaine puis promotion CAS.
    $quarantinePath = $hubStorage->quarantineDirectory() . '/' . bin2hex(random_bytes(16)) . '.tmp';
    if (!@copy($absolutePath, $quarantinePath)) {
        $report['totals']['errors']++;
        $sourceReport['errors'][] = 'copy_failed:' . $legacyStoragePath;

        return;
    }
    @chmod($quarantinePath, 0600);

    $storageKey = $hubStorage->promoteFromQuarantine($quarantinePath, $sha256);
    if ($storageKey === null) {
        @unlink($quarantinePath);
        $report['totals']['errors']++;
        $sourceReport['errors'][] = 'promote_failed:' . $legacyStoragePath;

        return;
    }

    $sizeBytes = (int) filesize($absolutePath);
    $object = $hubRepository->findOrCreateObject($sha256, $mimeType, $extension, $storageKey, max(1, $sizeBytes), 'clean');
    if ($object === null) {
        $report['totals']['errors']++;
        $sourceReport['errors'][] = 'object_failed:' . $legacyStoragePath;

        return;
    }

    $fiscalYear = null;
    if (is_string($documentDate) && preg_match('/\A(\d{4})-\d{2}-\d{2}/', $documentDate, $matches) === 1) {
        $fiscalYear = (int) $matches[1];
    }

    $document = $hubRepository->createDocument(
        (int) $object['id'],
        $categoryCode,
        $originalName !== '' ? $originalName : basename($legacyStoragePath),
        $title,
        'Migré depuis ' . $source,
        is_string($documentDate) && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', substr($documentDate, 0, 10)) === 1
            ? substr($documentDate, 0, 10)
            : null,
        $fiscalYear,
        $createdBy
    );
    if ($document === null) {
        $report['totals']['errors']++;
        $sourceReport['errors'][] = 'document_failed:' . $legacyStoragePath;

        return;
    }

    foreach ($entityRefs as $ref) {
        $hubRepository->addLink((int) $document['id'], $ref, $createdBy);
    }

    $report['totals']['migrated']++;
    $sourceReport['migrated'] = ($sourceReport['migrated'] ?? 0) + 1;
};

// ---------------------------------------------------------------------------
// Source 1 : documents personnels (private_documents)
// ---------------------------------------------------------------------------
try {
    $statement = $pdo->query(sprintf(
        'SELECT d.*, c.`name` AS `category_name`
         FROM `%s` d
         LEFT JOIN `%s` c ON c.`id` = d.`category_id`
         WHERE d.`is_active` = 1',
        $database->table('private_documents'),
        $database->table('private_document_categories')
    ));
    $rows = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $exception) {
    $rows = [];
    $report['sources']['private_documents']['error'] = 'table_unavailable';
}

foreach (is_array($rows) ? $rows : [] as $row) {
    $userId = (int) ($row['private_user_id'] ?? 0);
    if ($userId <= 0) {
        continue;
    }

    $migrateOne(
        'private_documents',
        (string) ($row['storage_path'] ?? ''),
        (string) ($row['original_name'] ?? ''),
        '',
        (string) ($row['category_name'] ?? ''),
        is_string($row['uploaded_at'] ?? null) ? (string) $row['uploaded_at'] : null,
        $userId,
        [DocumentEntityRef::of(PersonalDocumentIntegration::ENTITY_PERSONAL, $userId)]
    );
}

// ---------------------------------------------------------------------------
// Source 2 : documents locatifs (rental_documents)
// ---------------------------------------------------------------------------
try {
    $statement = $pdo->query(sprintf(
        'SELECT * FROM `%s` WHERE `is_active` = 1',
        $database->table('rental_documents')
    ));
    $rows = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $exception) {
    $rows = [];
    $report['sources']['rental_documents']['error'] = 'table_unavailable';
}

foreach (is_array($rows) ? $rows : [] as $row) {
    $propertyId = (int) ($row['rental_property_id'] ?? 0);
    $userId = (int) ($row['uploaded_by_private_user_id'] ?? 0);
    if ($propertyId <= 0 || $userId <= 0) {
        continue;
    }

    $entityRefs = [DocumentEntityRef::of(RentalDocumentIntegration::ENTITY_PROPERTY, $propertyId)];
    if (is_numeric($row['rental_unit_id'] ?? null) && (int) $row['rental_unit_id'] > 0) {
        $entityRefs[] = DocumentEntityRef::of(RentalDocumentIntegration::ENTITY_UNIT, (int) $row['rental_unit_id']);
    }
    if (is_numeric($row['rental_lease_id'] ?? null) && (int) $row['rental_lease_id'] > 0) {
        $entityRefs[] = DocumentEntityRef::of(RentalDocumentIntegration::ENTITY_LEASE, (int) $row['rental_lease_id']);
    }
    if (is_numeric($row['rental_expense_id'] ?? null) && (int) $row['rental_expense_id'] > 0) {
        $entityRefs[] = DocumentEntityRef::of(RentalDocumentIntegration::ENTITY_EXPENSE, (int) $row['rental_expense_id']);
    }

    $migrateOne(
        'rental_documents',
        (string) ($row['storage_path'] ?? ''),
        (string) ($row['original_name'] ?? ''),
        trim((string) ($row['display_name'] ?? '')),
        (string) ($row['category'] ?? ''),
        is_string($row['uploaded_at'] ?? null) ? (string) $row['uploaded_at'] : null,
        $userId,
        $entityRefs
    );
}

$report['finished_at'] = date('c');

if ($jsonOutput) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit($report['totals']['errors'] > 0 ? 1 : 0);
}

echo "Migration documentaire — mode {$report['mode']}\n";
echo str_repeat('-', 60) . "\n";
foreach ($report['sources'] as $source => $data) {
    echo $source . ' : ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
}
echo str_repeat('-', 60) . "\n";
printf(
    "Inventoriés : %d | Migrés%s : %d | Déjà migrés : %d | Fichiers manquants : %d | Erreurs : %d\n",
    $report['totals']['inventoried'],
    $apply ? '' : ' (simulation)',
    $report['totals']['migrated'],
    $report['totals']['already_migrated'],
    $report['totals']['missing_files'],
    $report['totals']['errors']
);
if (!$apply) {
    echo "Aucune écriture effectuée. Relancer avec --apply après sauvegarde.\n";
}
echo "Les fichiers legacy ne sont jamais supprimés par cet outil.\n";

// Notifier la fin
if ($notifier !== null) {
    $jobInfo = ['code' => 'document_hub_migrate', 'name' => 'Document Hub Migration'];
    $exitCode = $report['totals']['errors'] > 0 ? 1 : 0;
    if ($report['totals']['errors'] > 0) {
        $notifier->notifyJobFailure($jobInfo, ['exit_code' => $exitCode, 'stdout_text' => '', 'duration_ms' => 0]);
    } else {
        $notifier->notifyJobSuccess($jobInfo, ['exit_code' => $exitCode, 'stdout_text' => '', 'duration_ms' => 0]);
    }
}

exit($report['totals']['errors'] > 0 ? 1 : 0);
