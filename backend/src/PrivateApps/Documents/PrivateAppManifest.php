<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents;

final class PrivateAppManifest implements
    \Caramagnols\PrivatePortal\PrivateAppManifest,
    \Caramagnols\PrivateApps\Documents\Contract\ProvidesDocumentIntegration
{
    public function migrationCode(): string
    {
        return 'documents';
    }

    public function moduleCode(): string
    {
        return 'documents';
    }

    public function moduleName(): string
    {
        return 'Documents';
    }

    public function moduleDescription(): string
    {
        return 'Gestion des documents privés avec catégories, upload sécurisé et scan antivirus.';
    }

    public function modulePermissionCode(): string
    {
        return 'documents';
    }

    public function migrationStatusCode(): string
    {
        return 'documents';
    }

    public function title(): string
    {
        return 'Documents';
    }

    public function order(): int
    {
        return 2;
    }

    /**
     * @return array<int, string>
     */
    public function routeNames(): array
    {
        return [
            'documents',
            'files',
            'files_upload',
            'files_categories',
            'files_delete',
            'documents_hub',
            'documents_hub_import',
            'documents_hub_file',
            'documents_hub_action',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function tables(): array
    {
        return [
            'private_documents',
            'private_document_categories',
            'private_document_objects',
            'private_document_library',
            'private_document_links',
            'private_document_versions',
            'private_document_derivatives',
            'private_document_import_jobs',
            'private_document_taxonomy',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function contractClasses(): array
    {
        return [
            'Caramagnols\\PrivateApps\\Documents\\PrivateDocumentRepository',
            'Caramagnols\\PrivateApps\\Documents\\PrivateDocumentStorage',
            'Caramagnols\\PrivateApps\\Documents\\Repository\\DocumentHubRepository',
            'Caramagnols\\PrivateApps\\Documents\\Repository\\DocumentTaxonomyRepository',
            'Caramagnols\\PrivateApps\\Documents\\Service\\DocumentImportService',
            'Caramagnols\\PrivateApps\\Documents\\Service\\DocumentStorageService',
            'Caramagnols\\PrivateApps\\Documents\\Service\\DocumentValidationService',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function testClasses(): array
    {
        return [
            'DocumentsControllerTest',
            'DocumentPolicyTest',
            'DocumentValidationServiceTest',
            'DocumentStorageServiceTest',
            'DocumentClassificationServiceTest',
            'DocumentHubImportTest',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function auditEvents(): array
    {
        return [
            'private.files.uploaded',
            'private.files.downloaded',
            'private.files.deleted',
            'private.files.category_created',
            'private.module.access_denied',
            'private.document_hub.imported',
            'private.document_hub.import_rejected',
            'private.document_hub.downloaded',
            'private.document_hub.link_added',
            'private.document_hub.link_removed',
            'private.document_hub.archived',
            'private.document_hub.trashed',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function uiStates(): array
    {
        return ['empty', 'error', 'success'];
    }

    /**
     * @return array<int, string>
     */
    public function legacyRoutes(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function routePaths(): array
    {
        return [
            'documents' => 'documents',
            'documents_hub' => 'documents/bibliotheque',
            'documents_hub_import' => 'documents/bibliotheque/importer',
            'documents_hub_file' => 'documents/bibliotheque/fichier',
            'documents_hub_action' => 'documents/bibliotheque/action',
        ];
    }

    /**
     * @return array{label: string, description: string, stat_code: string}
     */
    public function dashboardTileData(): array
    {
        return [
            'label' => 'Documents',
            'description' => 'Gestion de vos documents privés',
            'stat_code' => 'private.documents.count',
        ];
    }

    public function notes(): string
    {
        return 'Module extrait de PrivatePortal vers PrivateApps le 2026-07-17. Héberge la bibliothèque documentaire centrale partagée par tous les modules.';
    }

    public function documentIntegration(): \Caramagnols\PrivateApps\Documents\Contract\DocumentIntegration
    {
        return new PersonalDocumentIntegration();
    }
}
