<?php

declare(strict_types=1);

use Caramagnols\PrivatePortal\Http\PrivateRouteResolver;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class PrivateRouteResolverTest extends TestCase
{
    public function testCanonicalPathsPreserveConfiguredBasePathCase(): void
    {
        $resolver = new PrivateRouteResolver(' private-4h6F1c ');

        $this->assertSame('/private-4h6F1c', $resolver->basePath());
        $this->assertSame('/private-4h6F1c/login', $resolver->canonicalPath('login'));
        $this->assertSame('/private-4h6F1c/dashboard', $resolver->canonicalPath('dashboard'));
        $this->assertSame('/private-4h6F1c/parametres', $resolver->canonicalPath('member_settings'));
        $this->assertSame('/private-4h6F1c/documents', $resolver->canonicalPath('documents'));
        $this->assertSame('/private-4h6F1c/blocnote', $resolver->canonicalPath('blocnote'));
        $this->assertSame('/private-4h6F1c/files/categories', $resolver->canonicalPath('files_categories'));
        $this->assertSame('/private-4h6F1c/locations', $resolver->canonicalPath('rental_dashboard'));
        $this->assertSame('/private-4h6F1c/locations/bailleurs', $resolver->canonicalPath('rental_lessors'));
        $this->assertSame('/private-4h6F1c/locations/locataires', $resolver->canonicalPath('rental_tenants'));
        $this->assertSame('/private-4h6F1c/locations/regularisations', $resolver->canonicalPath('rental_regularizations'));
        $this->assertSame('/private-4h6F1c/locations/agences', $resolver->canonicalPath('rental_agencies'));
        $this->assertSame('/private-4h6F1c/locations/imports', $resolver->canonicalPath('rental_agency_imports'));
    }

    public function testPhaseM1RouteDefinitionsMatchDocumentedContracts(): void
    {
        $resolver = new PrivateRouteResolver('private');

        $actual = [];
        foreach ($resolver->routeDefinitions() as $definition) {
            $path = (string) ($definition['path'] ?? '');
            $methods = array_values(array_map('strval', $definition['methods'] ?? []));
            $handler = is_array($definition['handler'] ?? null) ? $definition['handler'] : [];
            $type = (string) ($handler['type'] ?? '');
            $handlerKey = $type;

            if ($type === 'private') {
                $handlerKey .= ':' . (string) ($handler['page'] ?? '');
            } elseif ($type === 'redirect') {
                $handlerKey .= ':' . (string) ($handler['location'] ?? '');
            }

            $actual[$path] = [
                'methods' => $methods,
                'handler' => $handlerKey,
            ];
        }

        $expected = [
            '/private' => ['methods' => ['GET'], 'handler' => 'redirect:/private/login'],
            '/private/login' => ['methods' => ['GET', 'POST'], 'handler' => 'private:login'],
            '/private/login/index.php' => ['methods' => ['GET'], 'handler' => 'redirect:/private/login'],
            '/private/dashboard' => ['methods' => ['GET'], 'handler' => 'private:dashboard'],
            '/private/parametres' => ['methods' => ['GET', 'POST'], 'handler' => 'private:member_settings'],
            '/private/documents' => ['methods' => ['GET'], 'handler' => 'private:documents'],
            '/private/blocnote' => ['methods' => ['GET', 'POST'], 'handler' => 'private:blocnote'],
            '/private/dashboard.php' => ['methods' => ['GET'], 'handler' => 'redirect:/private/dashboard'],
            '/private/logout' => ['methods' => ['GET', 'POST'], 'handler' => 'private:logout'],
            '/private/activate/{token:[A-Za-z0-9._-]+}' => ['methods' => ['GET', 'POST'], 'handler' => 'private:activate'],
            '/private/password/forgot' => ['methods' => ['GET', 'POST'], 'handler' => 'private:password_forgot'],
            '/private/password/reset/{token:[A-Za-z0-9._-]+}' => ['methods' => ['GET', 'POST'], 'handler' => 'private:password_reset'],
            '/private/files/{documentId:[A-Za-z0-9._-]+}' => ['methods' => ['GET'], 'handler' => 'private:files'],
            '/private/files/upload' => ['methods' => ['POST'], 'handler' => 'private:files_upload'],
            '/private/files/categories' => ['methods' => ['POST'], 'handler' => 'private:files_categories'],
            '/private/files/{documentId:[A-Za-z0-9._-]+}/delete' => ['methods' => ['POST'], 'handler' => 'private:files_delete'],
            '/private/locations' => ['methods' => ['GET'], 'handler' => 'private:rental_dashboard'],
            '/private/locations/bailleurs' => ['methods' => ['GET', 'POST'], 'handler' => 'private:rental_lessors'],
            '/private/locations/biens' => ['methods' => ['GET', 'POST'], 'handler' => 'private:rental_properties'],
            '/private/locations/biens/{propertyId:[0-9]+}/archive' => ['methods' => ['POST'], 'handler' => 'private:rental_property_archive'],
            '/private/locations/lots' => ['methods' => ['GET', 'POST'], 'handler' => 'private:rental_units'],
            '/private/locations/lots/{unitId:[0-9]+}/archive' => ['methods' => ['POST'], 'handler' => 'private:rental_unit_archive'],
            '/private/locations/membres' => ['methods' => ['GET', 'POST'], 'handler' => 'private:rental_property_members'],
            '/private/locations/locataires' => ['methods' => ['GET', 'POST'], 'handler' => 'private:rental_tenants'],
            '/private/locations/baux' => ['methods' => ['GET', 'POST'], 'handler' => 'private:rental_leases'],
            '/private/locations/paiements' => ['methods' => ['GET', 'POST'], 'handler' => 'private:rental_payments'],
            '/private/locations/loyers' => ['methods' => ['GET', 'POST'], 'handler' => 'private:rental_rents'],
            '/private/locations/charges' => ['methods' => ['GET', 'POST'], 'handler' => 'private:rental_expenses'],
            '/private/locations/regularisations' => ['methods' => ['GET', 'POST'], 'handler' => 'private:rental_regularizations'],
            '/private/locations/documents' => ['methods' => ['GET', 'POST'], 'handler' => 'private:rental_documents'],
            '/private/locations/agences' => ['methods' => ['GET'], 'handler' => 'private:rental_agencies'],
            '/private/locations/imports' => ['methods' => ['GET', 'POST'], 'handler' => 'private:rental_agency_imports'],
            '/private/locations/revue' => ['methods' => ['GET', 'POST'], 'handler' => 'private:rental_agency_review'],
            '/private/locations/documents/{documentId:[A-Za-z0-9._-]+}' => ['methods' => ['GET'], 'handler' => 'private:rental_document_file'],
            '/private/locations/regularisations/{documentId:[A-Za-z0-9._-]+}' => ['methods' => ['GET'], 'handler' => 'private:rental_regularization_file'],
            '/private/locations/synthese' => ['methods' => ['GET'], 'handler' => 'private:rental_summary'],
            '/private/locations/export.csv' => ['methods' => ['GET'], 'handler' => 'private:rental_export_csv'],
            '/private/locations/export.pdf' => ['methods' => ['GET'], 'handler' => 'private:rental_export_pdf'],
            '/private/locations/export.zip' => ['methods' => ['GET'], 'handler' => 'private:rental_export_zip'],
            '/private/impots' => ['methods' => ['GET'], 'handler' => 'private:tax_dashboard'],
            '/private/impots/{year:[0-9]{4}}' => ['methods' => ['GET', 'POST'], 'handler' => 'private:tax_year'],
            '/private/impots/{year:[0-9]{4}}/revenus-manuels' => ['methods' => ['GET', 'POST'], 'handler' => 'private:tax_manual_entries'],
            '/private/impots/{year:[0-9]{4}}/controle' => ['methods' => ['GET'], 'handler' => 'private:tax_controls'],
            '/private/impots/{year:[0-9]{4}}/documents' => ['methods' => ['GET', 'POST'], 'handler' => 'private:tax_documents'],
            '/private/impots/{year:[0-9]{4}}/export' => ['methods' => ['GET'], 'handler' => 'private:tax_export'],
            '/private/discussions' => ['methods' => ['GET', 'POST'], 'handler' => 'private:discussion_index'],
            '/private/discussions/new' => ['methods' => ['GET', 'POST'], 'handler' => 'private:discussion_new'],
            '/private/discussions/{conversationId:[0-9]+}' => ['methods' => ['GET', 'POST'], 'handler' => 'private:discussion_conversation'],
            '/private/discussions/api/conversations' => ['methods' => ['GET', 'POST'], 'handler' => 'private:discussion_api_conversations'],
            '/private/discussions/api/conversations/{conversationId:[0-9]+}/messages' => ['methods' => ['GET', 'POST'], 'handler' => 'private:discussion_api_messages'],
            '/private/discussions/api/crypto/devices' => ['methods' => ['GET', 'POST'], 'handler' => 'private:discussion_api_crypto_devices'],
            '/private/discussions/api/conversations/{conversationId:[0-9]+}/keys' => ['methods' => ['GET', 'POST'], 'handler' => 'private:discussion_api_conversation_keys'],
            '/private/discussions/api/conversations/{conversationId:[0-9]+}/members' => ['methods' => ['POST'], 'handler' => 'private:discussion_api_members'],
            '/private/discussions/api/conversations/{conversationId:[0-9]+}/leave' => ['methods' => ['POST'], 'handler' => 'private:discussion_api_leave'],
            '/private/discussions/api/conversations/{conversationId:[0-9]+}/read' => ['methods' => ['POST'], 'handler' => 'private:discussion_api_read'],
            '/private/discussions/files/{attachmentId:[A-Za-z0-9._-]+}' => ['methods' => ['GET'], 'handler' => 'private:discussion_file'],
            '/private/discussions/files/{attachmentId:[A-Za-z0-9._-]+}/preview' => ['methods' => ['GET'], 'handler' => 'private:discussion_file_preview'],
            '/private/privacy/export' => ['methods' => ['GET'], 'handler' => 'private:privacy_export'],
            '/private/ops/backup' => ['methods' => ['GET'], 'handler' => 'private:ops_backup'],
        ];

        foreach ($expected as $path => $definition) {
            $this->assertArrayHasKey($path, $actual);
            $this->assertSame($definition, $actual[$path]);
        }
    }

    public function testCanonicalPathsSanitizeConfiguredBasePath(): void
    {
        $resolver = new PrivateRouteResolver(' /Private Portal 2026! ');

        $this->assertSame('/Private-Portal-2026', $resolver->basePath());
        $this->assertSame('/Private-Portal-2026/login', $resolver->canonicalPath('login'));
    }
}
