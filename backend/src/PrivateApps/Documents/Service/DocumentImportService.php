<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Service;

use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PrivateApps\Documents\Contract\DocumentEntityRef;
use Caramagnols\PrivateApps\Documents\Contract\DocumentImportProfile;
use Caramagnols\PrivateApps\Documents\Registry\DocumentIntegrationRegistry;
use Caramagnols\PrivateApps\Documents\Repository\DocumentHubRepository;
use Caramagnols\PrivateApps\Documents\Repository\DocumentTaxonomyRepository;

/**
 * Orchestration complète d'un import : profil déclaratif -> job -> validation
 * -> quarantaine -> SHA-256 -> déduplication -> promotion atomique -> document
 * logique -> rattachements -> classification. Chaque fichier a son résultat ;
 * aucun succès n'est annoncé sans document réellement créé.
 */
final class DocumentImportService
{
    public function __construct(
        private readonly DocumentPolicy $policy,
        private readonly DocumentValidationService $validation,
        private readonly DocumentStorageService $storage,
        private readonly DocumentHubRepository $repository,
        private readonly DocumentTaxonomyRepository $taxonomy,
        private readonly DocumentClassificationService $classification,
        private readonly DocumentLinkService $links,
        private readonly ?AppEventLogger $eventLogger = null
    ) {
    }

    /**
     * Importe un lot de fichiers pour un profil donné.
     *
     * @param array<int, array{name: string, tmp_name: string, size: int|string, error: int}> $files
     *        Fichiers normalisés (une entrée par fichier).
     * @param array<int, DocumentEntityRef> $entityRefs Rattachements du contexte (déjà connus par l'onglet).
     * @param array<string, mixed> $meta category_code, title, description, document_date (AAAA-MM-JJ), fiscal_year.
     * @return array<int, array{ok: bool, original_name: string, error_code: string, document_uid: string, category_code: string, deduplicated: bool}>
     */
    public function importBatch(
        int $privateUserId,
        string $profileCode,
        array $files,
        array $entityRefs,
        array $meta = []
    ): array {
        $profile = DocumentIntegrationRegistry::profile($profileCode);
        if ($profile === null) {
            return [$this->failure('', 'unknown_profile')];
        }

        if (count($files) > 1 && !$profile->allowMultiple) {
            return [$this->failure('', 'multiple_not_allowed')];
        }

        $refError = $this->validateEntityRefs($profile, $entityRefs, $privateUserId);
        if ($refError !== null) {
            return [$this->failure('', $refError)];
        }

        $totalBytes = 0;
        foreach ($files as $file) {
            $totalBytes += is_numeric($file['size'] ?? null) ? (int) $file['size'] : 0;
        }
        if ($totalBytes > $this->policy->batchMaxBytes()) {
            return [$this->failure('', 'batch_too_large')];
        }

        $this->taxonomy->seedSystemCategories();

        $results = [];
        foreach ($files as $file) {
            $results[] = $this->importOne($privateUserId, $profile, $file, $entityRefs, $meta);
        }

        return $results;
    }

    /**
     * @param array{name: string, tmp_name: string, size: int|string, error: int} $file
     * @param array<int, DocumentEntityRef> $entityRefs
     * @param array<string, mixed> $meta
     * @return array{ok: bool, original_name: string, error_code: string, document_uid: string, category_code: string, deduplicated: bool}
     */
    private function importOne(
        int $privateUserId,
        DocumentImportProfile $profile,
        array $file,
        array $entityRefs,
        array $meta
    ): array {
        $rawName = is_string($file['name'] ?? null) ? (string) $file['name'] : '';
        $originalName = $this->validation->normalizeOriginalName($rawName);
        $primaryRef = $entityRefs[0] ?? null;

        $jobId = $this->repository->createImportJob(
            $privateUserId,
            $profile->importSource,
            $profile->code,
            $primaryRef instanceof DocumentEntityRef ? $primaryRef->entityType : '',
            $primaryRef instanceof DocumentEntityRef ? $primaryRef->entityId : '',
            $originalName !== '' ? $originalName : $rawName
        );

        $uploadError = is_numeric($file['error'] ?? null) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($uploadError !== UPLOAD_ERR_OK) {
            return $this->reject($jobId, $originalName, 'upload_error');
        }

        $tmpPath = is_string($file['tmp_name'] ?? null) ? trim((string) $file['tmp_name']) : '';
        if ($tmpPath === '' || !is_file($tmpPath)) {
            return $this->reject($jobId, $originalName, 'invalid_tmp_file');
        }

        // Validation complète sur le fichier temporaire, avant tout stockage.
        $validated = $this->validation->validateFile($tmpPath, $rawName);
        if (!$validated->valid) {
            return $this->reject($jobId, $originalName, $validated->errorCode);
        }

        // Quarantaine puis empreinte en flux.
        $quarantinePath = $this->storage->moveToQuarantine($tmpPath, true);
        if ($quarantinePath === null) {
            return $this->reject($jobId, $originalName, 'quarantine_failed', DocumentHubRepository::JOB_STATUS_FAILED);
        }

        $sha256 = $this->storage->sha256File($quarantinePath);
        if ($sha256 === null) {
            @unlink($quarantinePath);

            return $this->reject($jobId, $originalName, 'hash_failed', DocumentHubRepository::JOB_STATUS_FAILED);
        }

        $existingObject = $this->repository->findObjectBySha256($sha256);
        $deduplicated = is_array($existingObject);

        $storageKey = $this->storage->promoteFromQuarantine($quarantinePath, $sha256);
        if ($storageKey === null) {
            @unlink($quarantinePath);

            return $this->reject($jobId, $originalName, 'storage_failed', DocumentHubRepository::JOB_STATUS_FAILED);
        }

        $object = $this->repository->findOrCreateObject(
            $sha256,
            $validated->mimeType,
            $validated->extension,
            $storageKey,
            $validated->sizeBytes,
            $validated->scanStatus
        );
        if ($object === null) {
            // L'objet physique reste en place ; il sera repris par un import
            // ultérieur du même contenu ou signalé par le contrôle d'intégrité.
            return $this->reject($jobId, $originalName, 'database_failed', DocumentHubRepository::JOB_STATUS_FAILED);
        }

        $userCategory = is_string($meta['category_code'] ?? null) ? (string) $meta['category_code'] : '';
        $classified = $this->classification->classify($profile, $userCategory, $validated->originalName);
        $categoryCode = $classified['confidence'] >= DocumentClassificationService::SUGGEST_THRESHOLD
            ? $classified['category_code']
            : DocumentTaxonomyRepository::INBOX_CODE;

        $documentDate = $this->normalizeDate($meta['document_date'] ?? null);
        $fiscalYear = is_numeric($meta['fiscal_year'] ?? null) ? (int) $meta['fiscal_year'] : null;
        if (($fiscalYear === null || $fiscalYear <= 0) && $documentDate !== null) {
            $fiscalYear = (int) substr($documentDate, 0, 4);
        }

        $document = $this->repository->createDocument(
            (int) $object['id'],
            $categoryCode,
            $validated->originalName,
            is_string($meta['title'] ?? null) ? trim((string) $meta['title']) : '',
            is_string($meta['description'] ?? null) ? trim((string) $meta['description']) : '',
            $documentDate,
            $fiscalYear !== null && $fiscalYear > 1900 && $fiscalYear < 2200 ? $fiscalYear : null,
            $privateUserId
        );
        if ($document === null) {
            return $this->reject($jobId, $originalName, 'database_failed', DocumentHubRepository::JOB_STATUS_FAILED);
        }

        $documentId = (int) $document['id'];
        foreach ($entityRefs as $ref) {
            $this->repository->addLink($documentId, $ref, $privateUserId);
        }

        if ($jobId !== null) {
            $this->repository->finishImportJob(
                $jobId,
                DocumentHubRepository::JOB_STATUS_READY,
                $documentId,
                null,
                $classified['source'],
                $classified['confidence']
            );
        }

        $this->logEvent('private.document_hub.imported', [
            'private_user_id' => $privateUserId,
            'profile' => $profile->code,
            'document_uid' => (string) $document['document_uid'],
            'size_bytes' => $validated->sizeBytes,
            'deduplicated' => $deduplicated,
            'category_code' => $categoryCode,
            'classification_source' => $classified['source'],
        ]);

        return [
            'ok' => true,
            'original_name' => $validated->originalName,
            'error_code' => '',
            'document_uid' => (string) $document['document_uid'],
            'category_code' => $categoryCode,
            'deduplicated' => $deduplicated,
        ];
    }

    /**
     * @param array<int, DocumentEntityRef> $entityRefs
     * @return string|null code d'erreur
     */
    private function validateEntityRefs(DocumentImportProfile $profile, array $entityRefs, int $privateUserId): ?string
    {
        if ($entityRefs === [] && $profile->contextEntityType !== '') {
            return 'missing_context';
        }

        $hasContextType = $profile->contextEntityType === '';
        foreach ($entityRefs as $ref) {
            if (!$ref instanceof DocumentEntityRef) {
                return 'invalid_context';
            }

            $error = $this->linkPrecheck($ref, $privateUserId);
            if ($error !== null) {
                return $error;
            }

            if ($ref->entityType === $profile->contextEntityType) {
                $hasContextType = true;
            }
        }

        return $hasContextType ? null : 'missing_context';
    }

    private function linkPrecheck(DocumentEntityRef $ref, int $privateUserId): ?string
    {
        $resolver = $this->links->resolverFor($ref->entityType);
        if ($resolver === null) {
            return 'unknown_entity_type';
        }

        if (!$resolver->entityExists($ref->entityType, $ref->entityId)) {
            return 'entity_not_found';
        }

        if (!$resolver->userCanAccessEntity($privateUserId, $ref->entityType, $ref->entityId)) {
            return 'entity_forbidden';
        }

        return null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) !== 1) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year) ? $value : null;
    }

    /**
     * @return array{ok: bool, original_name: string, error_code: string, document_uid: string, category_code: string, deduplicated: bool}
     */
    private function reject(
        ?int $jobId,
        string $originalName,
        string $errorCode,
        string $jobStatus = DocumentHubRepository::JOB_STATUS_REJECTED
    ): array {
        if ($jobId !== null) {
            $this->repository->finishImportJob($jobId, $jobStatus, null, $errorCode);
        }

        $this->logEvent('private.document_hub.import_rejected', [
            'reason' => $errorCode,
        ], 'warning');

        return $this->failure($originalName, $errorCode);
    }

    /**
     * @return array{ok: bool, original_name: string, error_code: string, document_uid: string, category_code: string, deduplicated: bool}
     */
    private function failure(string $originalName, string $errorCode): array
    {
        return [
            'ok' => false,
            'original_name' => $originalName,
            'error_code' => $errorCode,
            'document_uid' => '',
            'category_code' => '',
            'deduplicated' => false,
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logEvent(string $event, array $context, string $level = 'info'): void
    {
        $logger = $this->eventLogger ?? (function_exists('app_event_logger') ? app_event_logger() : null);
        if ($logger === null) {
            return;
        }

        $logger->security($event, $context, $level);
    }
}
