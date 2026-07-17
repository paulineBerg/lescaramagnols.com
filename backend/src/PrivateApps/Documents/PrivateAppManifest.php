<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents;

final class PrivateAppManifest implements \Caramagnols\PrivatePortal\PrivateAppManifest
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
        ];
    }

    /**
     * @return array<int, string>
     */
    public function testClasses(): array
    {
        return [
            'DocumentsControllerTest',
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
            'files' => 'documents/fichiers',
            'files_upload' => 'documents/importer',
            'files_categories' => 'documents/categories',
            'files_delete' => 'documents/supprimer',
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
        return 'Module extrait de PrivatePortal vers PrivateApps le 2026-07-17.';
    }
}
