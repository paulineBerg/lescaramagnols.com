<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import;

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Repository\AgencyImportRepository;
use Caramagnols\PrivatePortal\Documents\PrivateDocumentStorage;

final class AgencyImportService
{
    public function __construct(
        private readonly AgencyImportRepository $repository,
        private readonly PrivateDocumentStorage $storage,
        private readonly ?AgencyImportPreviewService $previewService = null
    ) {
    }

    /**
     * @param array{name:string,tmp_name:string,size:int|string,error:int,type?:string} $uploadedFile
     */
    public function importUploadedFile(
        int $privateUserId,
        array $uploadedFile,
        ?string $agencyName = null,
        ?string $sourceDirectory = null
    ): AgencyImportResult {
        if ($privateUserId <= 0) {
            return new AgencyImportResult('failed', error: 'invalid_user');
        }

        $originalName = is_string($uploadedFile['name'] ?? null) ? trim((string) $uploadedFile['name']) : '';
        if ($this->isIgnoredWindowsSidecar($originalName)) {
            $batch = $this->repository->createBatch(
                $privateUserId,
                $agencyName,
                $sourceDirectory,
                1,
                1,
                0,
                'review',
                'Fichier annexe Windows ignore.'
            );

            return new AgencyImportResult('ignored', $batch, error: 'zone_identifier');
        }

        $metadata = $this->storage->validateUploadedFile($uploadedFile);
        if (!is_array($metadata)) {
            $batch = $this->repository->createBatch($privateUserId, $agencyName, $sourceDirectory, 1, 0, 0, 'draft');
            return new AgencyImportResult('failed', $batch, error: $this->storage->uploadError() ?? 'upload_invalid');
        }

        $sha256 = hash_file('sha256', $metadata['tmpPath']);

        if ($this->repository->findImportedDocumentBySha256($sha256) !== null) {
            $batch = $this->repository->createBatch($privateUserId, $agencyName, $sourceDirectory, 1, 0, 1, 'review');
            return new AgencyImportResult('duplicate', $batch, error: 'duplicate_sha256');
        }

        $documentId = $this->storage->generateDocumentId();
        $stored = $documentId !== '' ? $this->storage->storeUploadedFile($metadata, $documentId) : null;
        if (!is_array($stored)) {
            $batch = $this->repository->createBatch($privateUserId, $agencyName, $sourceDirectory, 1, 0, 0, 'draft');
            return new AgencyImportResult('failed', $batch, error: $this->storage->uploadError() ?? 'storage_failed');
        }

        $absolutePath = $this->storage->absolutePath((string) $stored['storagePath']);
        if ($absolutePath === null || !is_file($absolutePath)) {
            $this->storage->deleteStoredDocument((string) $stored['storagePath'], (string) $stored['documentId']);
            $batch = $this->repository->createBatch($privateUserId, $agencyName, $sourceDirectory, 1, 0, 0, 'draft');
            return new AgencyImportResult('failed', $batch, error: 'stored_file_missing');
        }

        $batch = $this->repository->createBatch($privateUserId, $agencyName, $sourceDirectory, 1, 0, 0, 'review');
        if (!$batch instanceof \Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyImportBatch) {
            $this->storage->deleteStoredDocument((string) $stored['storagePath'], (string) $stored['documentId']);
            return new AgencyImportResult('failed', error: 'batch_create_failed');
        }

        $preview = $this->previewService()->preview(
            $absolutePath,
            (string) $stored['originalName'],
            (string) $stored['mimeType']
        );
        $document = $this->repository->persistPreview(
            $batch->id,
            $preview,
            (string) $stored['documentId'],
            $agencyName,
            (string) $stored['storagePath']
        );

        if (!$document instanceof \Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyImportedDocument) {
            $this->storage->deleteStoredDocument((string) $stored['storagePath'], (string) $stored['documentId']);
            return new AgencyImportResult('failed', $batch, $document, $preview, 'persist_failed');
        }

        return new AgencyImportResult('imported', $batch, $document, $preview);
    }

    private function isIgnoredWindowsSidecar(string $originalName): bool
    {
        $normalized = strtolower(str_replace('\\', '/', trim($originalName)));
        return $normalized !== '' && str_ends_with($normalized, ':zone.identifier');
    }

    private function previewService(): AgencyImportPreviewService
    {
        return $this->previewService ?? new AgencyImportPreviewService();
    }
}
