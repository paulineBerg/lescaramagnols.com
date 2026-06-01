<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Http;

final class PrivateRouteResolver
{
    public function __construct(private readonly string $configuredBasePath)
    {
    }

    public function canonicalPath(string $page = 'login'): string
    {
        $basePath = $this->basePath();

        return match ($page) {
            'login' => $basePath . '/login',
            'dashboard' => $basePath . '/dashboard',
            'member_settings' => $basePath . '/parametres',
            'documents' => $basePath . '/documents',
            'blocnote' => $basePath . '/blocnote',
            'logout' => $basePath . '/logout',
            'session_ping' => $basePath . '/session/ping',
            'activate' => $basePath . '/activate',
            'password_forgot' => $basePath . '/password/forgot',
            'password_reset' => $basePath . '/password/reset',
            'files' => $basePath . '/files',
            'files_upload' => $basePath . '/files/upload',
            'files_categories' => $basePath . '/files/categories',
            'files_delete' => $basePath . '/files',
            'rental_dashboard' => $basePath . '/locations',
            'rental_lessors' => $basePath . '/locations/bailleurs',
            'rental_properties' => $basePath . '/rental-properties',
            'rental_units' => $basePath . '/rental-units',
            'rental_property_members' => $basePath . '/rental-property-members',
            'rental_tenants' => $basePath . '/locations/locataires',
            'rental_leases' => $basePath . '/leases',
            'rental_payments' => $basePath . '/payments',
            'rental_rents' => $basePath . '/rents',
            'rental_expenses' => $basePath . '/charges',
            'rental_regularizations' => $basePath . '/locations/regularisations',
            'rental_documents' => $basePath . '/locations/documents',
            'rental_agencies' => $basePath . '/locations/agence',
            'rental_agency_imports' => $basePath . '/locations/agence/imports',
            'rental_agency_review' => $basePath . '/locations/agence/documents-a-classer',
            'rental_summary' => $basePath . '/locations/summary',
            'rental_export_csv' => $basePath . '/locations/export.csv',
            'rental_export_pdf' => $basePath . '/locations/export.pdf',
            'rental_export_zip' => $basePath . '/locations/export.zip',
            'tax_dashboard' => $basePath . '/impots',
            'tax_year' => $basePath . '/impots',
            'tax_manual_entries' => $basePath . '/impots',
            'tax_controls' => $basePath . '/impots',
            'tax_documents' => $basePath . '/impots',
            'tax_export' => $basePath . '/impots',
            'discussion_index' => $basePath . '/discussions',
            'discussion_new' => $basePath . '/discussions/new',
            'discussion_api_conversations' => $basePath . '/discussions/api/conversations',
            'discussion_api_crypto_devices' => $basePath . '/discussions/api/crypto/devices',
            'discussion_api_client_events' => $basePath . '/discussions/api/client-events',
            'discussion_files' => $basePath . '/discussions/files',
            'privacy_export' => $basePath . '/privacy/export',
            'ops_backup' => $basePath . '/ops/backup',
            default => $basePath,
        };
    }

    public function basePath(): string
    {
        $normalized = trim((string) $this->configuredBasePath);
        $normalized = trim($normalized, '/');
        $normalized = preg_replace('/[^A-Za-z0-9_-]+/', '-', $normalized) ?: 'private';

        return '/' . trim($normalized, '-_');
    }

    /**
     * @return array<int, array{methods: array<int, string>, path: string, handler: array<string, mixed>}>
     */
    public function routeDefinitions(): array
    {
        $basePath = $this->basePath();
        $loginPath = $this->canonicalPath('login');
        $dashboardPath = $this->canonicalPath('dashboard');

        return [
            [
                'methods' => ['GET'],
                'path' => $basePath,
                'handler' => ['type' => 'redirect', 'location' => $loginPath, 'status' => 302],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $loginPath,
                'handler' => ['type' => 'private', 'page' => 'login'],
            ],
            [
                'methods' => ['GET'],
                'path' => $loginPath . '/index.php',
                'handler' => ['type' => 'redirect', 'location' => $loginPath, 'status' => 301],
            ],
            [
                'methods' => ['GET'],
                'path' => $dashboardPath,
                'handler' => ['type' => 'private', 'page' => 'dashboard'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('member_settings'),
                'handler' => ['type' => 'private', 'page' => 'member_settings'],
            ],
            [
                'methods' => ['GET'],
                'path' => $this->canonicalPath('documents'),
                'handler' => ['type' => 'private', 'page' => 'documents'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('blocnote'),
                'handler' => ['type' => 'private', 'page' => 'blocnote'],
            ],
            [
                'methods' => ['GET'],
                'path' => $dashboardPath . '.php',
                'handler' => ['type' => 'redirect', 'location' => $dashboardPath, 'status' => 301],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('logout'),
                'handler' => ['type' => 'private', 'page' => 'logout'],
            ],
            [
                'methods' => ['POST'],
                'path' => $this->canonicalPath('session_ping'),
                'handler' => ['type' => 'private', 'page' => 'session_ping'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('activate') . '/{token:[A-Za-z0-9._-]+}',
                'handler' => ['type' => 'private', 'page' => 'activate'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('password_forgot'),
                'handler' => ['type' => 'private', 'page' => 'password_forgot'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('password_reset') . '/{token:[A-Za-z0-9._-]+}',
                'handler' => ['type' => 'private', 'page' => 'password_reset'],
            ],
            [
                'methods' => ['GET'],
                'path' => $this->canonicalPath('files') . '/{documentId:[A-Za-z0-9._-]+}',
                'handler' => ['type' => 'private', 'page' => 'files'],
            ],
            [
                'methods' => ['POST'],
                'path' => $this->canonicalPath('files_upload'),
                'handler' => ['type' => 'private', 'page' => 'files_upload'],
            ],
            [
                'methods' => ['POST'],
                'path' => $this->canonicalPath('files_categories'),
                'handler' => ['type' => 'private', 'page' => 'files_categories'],
            ],
            [
                'methods' => ['POST'],
                'path' => $this->canonicalPath('files') . '/{documentId:[A-Za-z0-9._-]+}/delete',
                'handler' => ['type' => 'private', 'page' => 'files_delete'],
            ],
            [
                'methods' => ['GET'],
                'path' => $this->canonicalPath('rental_dashboard'),
                'handler' => ['type' => 'private', 'page' => 'rental_dashboard'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('rental_lessors'),
                'handler' => ['type' => 'private', 'page' => 'rental_lessors'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('rental_properties'),
                'handler' => ['type' => 'private', 'page' => 'rental_properties'],
            ],
            [
                'methods' => ['POST'],
                'path' => $this->canonicalPath('rental_properties') . '/{propertyId:[0-9]+}/archive',
                'handler' => ['type' => 'private', 'page' => 'rental_property_archive'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('rental_units'),
                'handler' => ['type' => 'private', 'page' => 'rental_units'],
            ],
            [
                'methods' => ['POST'],
                'path' => $this->canonicalPath('rental_units') . '/{unitId:[0-9]+}/archive',
                'handler' => ['type' => 'private', 'page' => 'rental_unit_archive'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('rental_property_members'),
                'handler' => ['type' => 'private', 'page' => 'rental_property_members'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('rental_tenants'),
                'handler' => ['type' => 'private', 'page' => 'rental_tenants'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('rental_leases'),
                'handler' => ['type' => 'private', 'page' => 'rental_leases'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('rental_payments'),
                'handler' => ['type' => 'private', 'page' => 'rental_payments'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('rental_rents'),
                'handler' => ['type' => 'private', 'page' => 'rental_rents'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('rental_expenses'),
                'handler' => ['type' => 'private', 'page' => 'rental_expenses'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('rental_regularizations'),
                'handler' => ['type' => 'private', 'page' => 'rental_regularizations'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('rental_documents'),
                'handler' => ['type' => 'private', 'page' => 'rental_documents'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('rental_agencies'),
                'handler' => ['type' => 'private', 'page' => 'rental_agencies'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('rental_agency_imports'),
                'handler' => ['type' => 'private', 'page' => 'rental_agency_imports'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('rental_agency_review'),
                'handler' => ['type' => 'private', 'page' => 'rental_agency_review'],
            ],
            [
                'methods' => ['GET'],
                'path' => $this->canonicalPath('rental_documents') . '/{documentId:[A-Za-z0-9._-]+}',
                'handler' => ['type' => 'private', 'page' => 'rental_document_file'],
            ],
            [
                'methods' => ['GET'],
                'path' => $this->canonicalPath('rental_regularizations') . '/{documentId:[A-Za-z0-9._-]+}',
                'handler' => ['type' => 'private', 'page' => 'rental_regularization_file'],
            ],
            [
                'methods' => ['GET'],
                'path' => $this->canonicalPath('rental_summary'),
                'handler' => ['type' => 'private', 'page' => 'rental_summary'],
            ],
            [
                'methods' => ['GET'],
                'path' => $this->canonicalPath('rental_export_csv'),
                'handler' => ['type' => 'private', 'page' => 'rental_export_csv'],
            ],
            [
                'methods' => ['GET'],
                'path' => $this->canonicalPath('rental_export_pdf'),
                'handler' => ['type' => 'private', 'page' => 'rental_export_pdf'],
            ],
            [
                'methods' => ['GET'],
                'path' => $this->canonicalPath('rental_export_zip'),
                'handler' => ['type' => 'private', 'page' => 'rental_export_zip'],
            ],
            [
                'methods' => ['GET'],
                'path' => $this->canonicalPath('tax_dashboard'),
                'handler' => ['type' => 'private', 'page' => 'tax_dashboard'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('tax_year') . '/{year:[0-9]{4}}',
                'handler' => ['type' => 'private', 'page' => 'tax_year'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('tax_manual_entries') . '/{year:[0-9]{4}}/revenus-manuels',
                'handler' => ['type' => 'private', 'page' => 'tax_manual_entries'],
            ],
            [
                'methods' => ['GET'],
                'path' => $this->canonicalPath('tax_controls') . '/{year:[0-9]{4}}/controle',
                'handler' => ['type' => 'private', 'page' => 'tax_controls'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('tax_documents') . '/{year:[0-9]{4}}/documents',
                'handler' => ['type' => 'private', 'page' => 'tax_documents'],
            ],
            [
                'methods' => ['GET'],
                'path' => $this->canonicalPath('tax_export') . '/{year:[0-9]{4}}/export',
                'handler' => ['type' => 'private', 'page' => 'tax_export'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('discussion_index'),
                'handler' => ['type' => 'private', 'page' => 'discussion_index'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('discussion_new'),
                'handler' => ['type' => 'private', 'page' => 'discussion_new'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('discussion_index') . '/{conversationId:[0-9]+}',
                'handler' => ['type' => 'private', 'page' => 'discussion_conversation'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('discussion_api_conversations'),
                'handler' => ['type' => 'private', 'page' => 'discussion_api_conversations'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('discussion_api_conversations') . '/{conversationId:[0-9]+}/messages',
                'handler' => ['type' => 'private', 'page' => 'discussion_api_messages'],
            ],
            [
                'methods' => ['GET'],
                'path' => $this->canonicalPath('discussion_api_conversations') . '/{conversationId:[0-9]+}/events',
                'handler' => ['type' => 'private', 'page' => 'discussion_api_events'],
            ],
            [
                'methods' => ['POST'],
                'path' => $this->canonicalPath('discussion_api_client_events'),
                'handler' => ['type' => 'private', 'page' => 'discussion_api_client_events'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('discussion_api_crypto_devices'),
                'handler' => ['type' => 'private', 'page' => 'discussion_api_crypto_devices'],
            ],
            [
                'methods' => ['GET', 'POST'],
                'path' => $this->canonicalPath('discussion_api_conversations') . '/{conversationId:[0-9]+}/keys',
                'handler' => ['type' => 'private', 'page' => 'discussion_api_conversation_keys'],
            ],
            [
                'methods' => ['POST'],
                'path' => $this->canonicalPath('discussion_api_conversations') . '/{conversationId:[0-9]+}/members',
                'handler' => ['type' => 'private', 'page' => 'discussion_api_members'],
            ],
            [
                'methods' => ['POST'],
                'path' => $this->canonicalPath('discussion_api_conversations') . '/{conversationId:[0-9]+}/leave',
                'handler' => ['type' => 'private', 'page' => 'discussion_api_leave'],
            ],
            [
                'methods' => ['POST'],
                'path' => $this->canonicalPath('discussion_api_conversations') . '/{conversationId:[0-9]+}/read',
                'handler' => ['type' => 'private', 'page' => 'discussion_api_read'],
            ],
            [
                'methods' => ['GET'],
                'path' => $this->canonicalPath('discussion_files') . '/{attachmentId:[A-Za-z0-9._-]+}',
                'handler' => ['type' => 'private', 'page' => 'discussion_file'],
            ],
            [
                'methods' => ['GET'],
                'path' => $this->canonicalPath('discussion_files') . '/{attachmentId:[A-Za-z0-9._-]+}/preview',
                'handler' => ['type' => 'private', 'page' => 'discussion_file_preview'],
            ],
            [
                'methods' => ['GET'],
                'path' => $this->canonicalPath('privacy_export'),
                'handler' => ['type' => 'private', 'page' => 'privacy_export'],
            ],
            [
                'methods' => ['GET'],
                'path' => $this->canonicalPath('ops_backup'),
                'handler' => ['type' => 'private', 'page' => 'ops_backup'],
            ],
        ];
    }
}
