<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Operations;

use Caramagnols\PrivatePortal\Http\PrivateRouteResolver;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;

final class PrivateModuleMigrationPlanService
{
    public function __construct(
        private readonly PrivateModuleRegistry $moduleRegistry,
        private readonly PrivateRouteResolver $routeResolver,
        private readonly ?PrivateMigrationService $migrationService = null
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function plans(): array
    {
        $plans = [
            'private_core' => [
                'order' => 1,
                'title' => 'PrivateCore',
                'permissionModule' => 'dashboard',
                'migrationStatusModule' => 'dashboard',
                'description' => 'Comptes famille, permissions, sessions, activation, reset, audit et operations RGPD.',
                'routeNames' => [
                    'login',
                    'dashboard',
                    'logout',
                    'activate',
                    'password_forgot',
                    'password_reset',
                    'privacy_export',
                    'ops_backup',
                ],
                'tables' => [
                    'private_users',
                    'private_user_invites',
                    'private_password_resets',
                    'private_sessions',
                    'private_mfa_backup_codes',
                    'private_user_mail_settings',
                    'private_modules',
                    'private_user_module_permissions',
                ],
                'contractClasses' => [
                    'Caramagnols\\PrivatePortal\\Repository\\PrivateUserRepository',
                    'Caramagnols\\PrivatePortal\\Repository\\PrivateUserMailSettingsRepository',
                    'Caramagnols\\PrivatePortal\\Repository\\PrivateModulePermissionRepository',
                    'Caramagnols\\PrivatePortal\\Security\\PrivateAuth',
                    'Caramagnols\\PrivatePortal\\Security\\PrivateSession',
                    'Caramagnols\\PrivatePortal\\Security\\PrivatePortalSecurityGuard',
                    'Caramagnols\\PrivatePortal\\Operations\\PrivateDataProtectionService',
                ],
                'testClasses' => [
                    'PrivatePortalSecurityTest',
                    'PrivatePortalMembersTest',
                    'PrivatePortalModuleAssignmentTest',
                    'PrivatePortalPhaseCoverageTest',
                    'PrivacyOperationsTest',
                ],
                'auditEvents' => [
                    'private.login.rejected',
                    'private.logout.rejected',
                    'admin.private.member_modules_updated',
                    'admin.private.deletion_backup_downloaded',
                ],
                'uiStates' => ['empty', 'error', 'success'],
                'legacyRoutes' => ['dashboard.php redirected to /private/dashboard'],
                'notes' => 'Source de verite actuelle : PHP prive moderne sous backend/src/PrivatePortal.',
            ],
            'documents' => [
                'order' => 2,
                'title' => 'Documents',
                'permissionModule' => 'documents',
                'migrationStatusModule' => 'documents',
                'description' => 'Categories, stockage hors webroot, upload, download controle, suppression et retention.',
                'routeNames' => [
                    'documents',
                    'files',
                    'files_upload',
                    'files_categories',
                    'files_delete',
                ],
                'tables' => [
                    'private_document_categories',
                    'private_documents',
                ],
                'contractClasses' => [
                    'Caramagnols\\PrivatePortal\\Documents\\PrivateDocumentRepository',
                    'Caramagnols\\PrivatePortal\\Documents\\PrivateDocumentStorage',
                ],
                'testClasses' => [
                    'PrivatePortalStorageTest',
                    'PrivacyOperationsTest',
                ],
                'auditEvents' => [
                    'private.documents.uploaded',
                    'private.documents.deleted',
                    'private.documents.downloaded',
                ],
                'uiStates' => ['empty', 'error', 'success'],
                'legacyRoutes' => ['no direct public file route'],
                'notes' => 'Les fichiers restent hors webroot et les chemins publics ne sont pas persistants.',
            ],
            'family_discussion' => [
                'order' => 3,
                'title' => 'FamilyDiscussion',
                'permissionModule' => 'discussions',
                'migrationStatusModule' => 'discussions',
                'description' => 'Conversations, membres, messages, fichiers, cles, lectures et retention.',
                'routeNames' => [
                    'discussion_index',
                    'discussion_new',
                    'discussion_api_conversations',
                    'discussion_api_crypto_devices',
                    'discussion_files',
                ],
                'tables' => [
                    'discussion_conversations',
                    'discussion_conversation_members',
                    'discussion_messages',
                    'discussion_message_reads',
                    'discussion_message_attachments',
                    'discussion_conversation_keys',
                    'discussion_crypto_devices',
                    'discussion_retention_runs',
                ],
                'contractClasses' => [
                    'Caramagnols\\PrivateApps\\FamilyDiscussion\\Repository\\DiscussionRepository',
                    'Caramagnols\\PrivateApps\\FamilyDiscussion\\Service\\DiscussionService',
                    'Caramagnols\\PrivateApps\\FamilyDiscussion\\Service\\DiscussionAccessPolicy',
                    'Caramagnols\\PrivateApps\\FamilyDiscussion\\Attachment\\DiscussionAttachmentStorage',
                    'Caramagnols\\PrivateApps\\FamilyDiscussion\\Retention\\DiscussionRetentionService',
                ],
                'testClasses' => [
                    'FamilyDiscussionModuleTest',
                    'PrivatePortalPhaseCoverageTest',
                ],
                'auditEvents' => [
                    'private.discussions.message_created',
                    'private.discussions.message_deleted',
                    'private.discussions.attachment_deleted',
                    'private.discussions.retention_run',
                ],
                'uiStates' => ['empty', 'error', 'success'],
                'legacyRoutes' => ['discussion PHP routes stay behind PrivateRouteResolver'],
                'notes' => 'La retention cible reste 60 jours pour les messages/fichiers expirables.',
            ],
            'real_estate_rental' => [
                'order' => 4,
                'title' => 'RealEstateRental',
                'permissionModule' => 'real_estate_rental',
                'migrationStatusModule' => 'real_estate_rental',
                'description' => 'Biens, lots, membres, locataires, baux, loyers, paiements, charges, documents et rapports.',
                'routeNames' => [
                    'rental_dashboard',
                    'rental_properties',
                    'rental_units',
                    'rental_property_members',
                    'rental_tenants',
                    'rental_leases',
                    'rental_rents',
                    'rental_payments',
                    'rental_expenses',
                    'rental_regularizations',
                    'rental_documents',
                    'rental_summary',
                    'rental_export_csv',
                    'rental_export_pdf',
                    'rental_export_zip',
                ],
                'tables' => [
                    'rental_properties',
                    'rental_units',
                    'rental_property_members',
                    'rental_tenants',
                    'rental_leases',
                    'rental_rents',
                    'rental_payments',
                    'rental_payment_requests',
                    'rental_expenses',
                    'rental_documents',
                    'rental_generated_documents',
                    'rental_charge_regularizations',
                    'rental_export_logs',
                ],
                'contractClasses' => [
                    'Caramagnols\\PrivateApps\\RealEstateRental\\Repository\\RentalPropertyRepository',
                    'Caramagnols\\PrivateApps\\RealEstateRental\\Repository\\RentalUnitRepository',
                    'Caramagnols\\PrivateApps\\RealEstateRental\\Repository\\RentalLifecycleRepository',
                    'Caramagnols\\PrivateApps\\RealEstateRental\\Repository\\RentalPropertyMemberRepository',
                    'Caramagnols\\PrivateApps\\RealEstateRental\\Service\\RentalDashboardService',
                    'Caramagnols\\PrivateApps\\RealEstateRental\\Service\\RentalExportService',
                    'Caramagnols\\PrivateApps\\RealEstateRental\\Service\\RentalAnnualSummaryService',
                    'Caramagnols\\PrivateApps\\RealEstateRental\\Service\\RentalPaymentRequestService',
                    'Caramagnols\\PrivateApps\\RealEstateRental\\Service\\RentalReceiptService',
                    'Caramagnols\\PrivateApps\\RealEstateRental\\Service\\ChargeRegularizationService',
                ],
                'testClasses' => [
                    'RealEstateRentalModuleTest',
                    'RentalLifecycleTest',
                    'RentalPaymentRequestServiceTest',
                    'RentalReceiptServiceTest',
                    'RentalExpenseCategoryTest',
                    'ChargeRegularizationServiceTest',
                    'RentalDashboardServiceTest',
                    'TaxSummaryServiceTest',
                    'RentalExportServiceTest',
                    'PrivatePortalPhaseCoverageTest',
                ],
                'auditEvents' => [
                    'private.rental.property_created',
                    'private.rental.unit_created',
                    'private.rental.document_uploaded',
                    'private.rental_charge_regularization.generated',
                    'private.rental.export_created',
                ],
                'uiStates' => ['empty', 'error', 'success'],
                'legacyRoutes' => ['rental PHP routes stay behind PrivateRouteResolver'],
                'notes' => 'Les exports restent regenerables depuis les tables locatives source.',
            ],
            'agency_imports' => [
                'order' => 5,
                'title' => 'AgencyImports',
                'permissionModule' => 'real_estate_rental',
                'migrationStatusModule' => 'real_estate_rental',
                'description' => 'Imports agence, documents detectes, lignes, anomalies, mapping et revue humaine.',
                'routeNames' => [
                    'rental_agency_imports',
                    'rental_agency_review',
                ],
                'tables' => [
                    'rental_agency_import_batches',
                    'rental_agency_imported_documents',
                    'rental_agency_statements',
                    'rental_agency_statement_lines',
                    'rental_agency_import_issues',
                    'rental_agency_unit_mappings',
                    'rental_agency_line_mappings',
                ],
                'contractClasses' => [
                    'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Import\\AgencyImportService',
                    'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Import\\AgencyImportPreviewService',
                    'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Domain\\AgencyFiscalReviewPolicy',
                    'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Repository\\AgencyImportRepository',
                    'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Repository\\AgencyMappingRepository',
                    'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Service\\AgencyAdvancedReconciliationService',
                    'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Service\\AgencyStatementValidationService',
                    'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Import\\AgencySensitiveDataMasker',
                ],
                'testClasses' => [
                    'PrivatePortalPhaseCoverageTest',
                    'AgencyDocumentClassifierTest',
                    'AgencyImportPreviewServiceTest',
                    'AgencyImportRepositoryTest',
                    'AgencyImportServiceTest',
                    'AgencyAdvancedReconciliationServiceTest',
                    'AgencyFiscalReviewPolicyTest',
                    'AgencySensitiveDataMaskerTest',
                    'AgencyStatementValidationServiceTest',
                    'AgencyTaxBridgeNormalizerTest',
                ],
                'auditEvents' => [
                    'private.rental.agency_imported',
                    'private.rental.agency_reviewed',
                    'private.rental.agency_issue_created',
                    'private.rental_agency_import.imported',
                    'private.rental_agency_import.document_deleted',
                    'private.rental_agency_import.agency_created',
                    'private.rental_agency_import.unit_mapping_created',
                    'private.rental_agency_import.unit_mapping_deleted',
                    'private.rental_agency_review.property_updated',
                    'private.rental_agency_review.line_reviewed',
                ],
                'uiStates' => ['empty', 'error', 'success'],
                'legacyRoutes' => ['agency imports inherit real_estate_rental permission'],
                'notes' => 'Sous-module fonctionnel rattache a la permission real_estate_rental.',
            ],
            'tax_declaration_helper' => [
                'order' => 6,
                'title' => 'TaxDeclarationHelper',
                'permissionModule' => 'tax_declaration_helper',
                'migrationStatusModule' => 'tax_declaration_helper',
                'description' => 'Sources validees, activation manuelle, controles, synthese fiscale et exports.',
                'routeNames' => [
                    'tax_dashboard',
                    'tax_year',
                    'tax_manual_entries',
                    'tax_controls',
                    'tax_documents',
                    'tax_export',
                ],
                'tables' => [
                    'tax_years',
                    'tax_income_sources',
                    'tax_source_activations',
                    'tax_manual_income_entries',
                    'tax_annual_summaries',
                    'tax_summary_lines',
                    'tax_export_logs',
                ],
                'contractClasses' => [
                    'Caramagnols\\PrivateApps\\TaxDeclarationHelper\\Repository\\TaxDeclarationRepository',
                    'Caramagnols\\PrivateApps\\TaxDeclarationHelper\\Service\\TaxDeclarationSummaryService',
                    'Caramagnols\\PrivatePortal\\TaxDeclarationHelper\\Source\\RentalTaxDataSource',
                    'Caramagnols\\PrivatePortal\\TaxDeclarationHelper\\ValueObject\\AnnualRentalIncome',
                    'Caramagnols\\PrivatePortal\\TaxDeclarationHelper\\ValueObject\\AnnualDeductibleExpenses',
                    'Caramagnols\\PrivatePortal\\TaxDeclarationHelper\\ValueObject\\MissingTaxDocument',
                ],
                'testClasses' => [
                    'TaxDeclarationHelperModuleTest',
                    'TaxDeclarationHelperRoutesTest',
                    'PrivatePortalTaxBridgeTest',
                    'PrivatePortalPhaseCoverageTest',
                ],
                'auditEvents' => [
                    'private.tax.source_updated',
                    'private.tax.summary_generated',
                    'private.tax.year_locked',
                    'private.tax.export_created',
                ],
                'uiStates' => ['empty', 'error', 'success'],
                'legacyRoutes' => ['tax helper routes stay behind PrivateRouteResolver'],
                'notes' => 'Le module reste une aide non officielle, sans remplacer la declaration fiscale.',
            ],
        ];

        uasort(
            $plans,
            static fn (array $left, array $right): int => ((int) $left['order']) <=> ((int) $right['order'])
        );

        return $plans;
    }

    /**
     * @return array<string, mixed>
     */
    public function readiness(?string $moduleCode = null): array
    {
        $plans = $this->plans();
        if ($moduleCode !== null && trim($moduleCode) !== '') {
            $normalized = strtolower(trim($moduleCode));
            if (!isset($plans[$normalized])) {
                return [
                    'success' => false,
                    'ready' => false,
                    'error' => 'unknown_migration_module',
                ];
            }
            $plans = [$normalized => $plans[$normalized]];
        }

        $modules = [];
        foreach ($plans as $code => $plan) {
            $modules[$code] = $this->evaluatePlan($code, $plan);
        }

        $ready = array_reduce(
            $modules,
            static fn (bool $carry, array $module): bool => $carry && ($module['ready'] ?? false) === true,
            true
        );

        return [
            'success' => true,
            'ready' => $ready,
            'modules' => $modules,
        ];
    }

    /**
     * @param array<string, mixed> $plan
     *
     * @return array<string, mixed>
     */
    private function evaluatePlan(string $code, array $plan): array
    {
        $permissionModule = (string) ($plan['permissionModule'] ?? '');
        $migrationStatusModule = (string) ($plan['migrationStatusModule'] ?? $permissionModule);
        $routes = $this->resolveRoutes($plan);
        $contractClasses = $this->classStatus($plan['contractClasses'] ?? []);
        $status = $this->migrationService instanceof PrivateMigrationService
            ? $this->migrationService->moduleStatus($migrationStatusModule)
            : [
                'success' => true,
                'status' => PrivateMigrationService::STATUS_PHP_SOURCE,
                'canDoubleWrite' => false,
            ];

        $checklist = [
            'apiContract' => $routes !== [] && $contractClasses['missing'] === [],
            'sqlSchemaAndMigration' => $this->stringListIsNotEmpty($plan['tables'] ?? []),
            'legacyReadOrImport' => $this->migrationService === null || $this->migrationService->readLegacyModel($migrationStatusModule)['success'] === true,
            'permissionsByAction' => $permissionModule !== '' && $this->moduleRegistry->moduleCode($permissionModule) !== null,
            'domainUnitTests' => $this->stringListIsNotEmpty($plan['testClasses'] ?? []),
            'httpAuthPermissionCsrfTests' => $this->hasHttpCoverage((array) ($plan['testClasses'] ?? [])),
            'formAndFileValidationTests' => $this->stringListIsNotEmpty($plan['testClasses'] ?? []),
            'sensitiveActionAudit' => $this->stringListIsNotEmpty($plan['auditEvents'] ?? []),
            'uiStates' => $this->containsAll($plan['uiStates'] ?? [], ['empty', 'error', 'success']),
            'smokeHttp' => $routes !== [],
            'reconciliationBeforeSwitch' => $this->stringListIsNotEmpty($plan['tables'] ?? []),
            'reversibleSwitch' => ($status['success'] ?? false) === true
                && in_array((string) ($status['status'] ?? ''), $this->allowedStatuses(), true)
                && ($status['canDoubleWrite'] ?? false) === false,
            'legacyRouteHandled' => $this->stringListIsNotEmpty($plan['legacyRoutes'] ?? []),
        ];

        return [
            'code' => $code,
            'title' => (string) ($plan['title'] ?? $code),
            'permissionModule' => $permissionModule,
            'migrationStatusModule' => $migrationStatusModule,
            'status' => $status['status'] ?? PrivateMigrationService::STATUS_PHP_SOURCE,
            'ready' => !in_array(false, $checklist, true),
            'checklist' => $checklist,
            'routes' => $routes,
            'tables' => array_values((array) ($plan['tables'] ?? [])),
            'contractClasses' => $contractClasses,
            'testClasses' => array_values((array) ($plan['testClasses'] ?? [])),
            'auditEvents' => array_values((array) ($plan['auditEvents'] ?? [])),
            'notes' => (string) ($plan['notes'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $plan
     *
     * @return array<string, string>
     */
    private function resolveRoutes(array $plan): array
    {
        $routes = [];
        foreach ((array) ($plan['routeNames'] ?? []) as $routeName) {
            if (!is_string($routeName) || trim($routeName) === '') {
                continue;
            }
            $routes[$routeName] = $this->routeResolver->canonicalPath($routeName);
        }

        return $routes;
    }

    /**
     * @param mixed $classes
     *
     * @return array{existing: array<int, string>, missing: array<int, string>}
     */
    private function classStatus(mixed $classes): array
    {
        $existing = [];
        $missing = [];
        foreach ((array) $classes as $className) {
            if (!is_string($className) || trim($className) === '') {
                continue;
            }
            if (class_exists($className) || interface_exists($className)) {
                $existing[] = $className;
            } else {
                $missing[] = $className;
            }
        }

        return [
            'existing' => $existing,
            'missing' => $missing,
        ];
    }

    /**
     * @param mixed $value
     */
    private function stringListIsNotEmpty(mixed $value): bool
    {
        foreach ((array) $value as $item) {
            if (is_string($item) && trim($item) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $haystack
     * @param array<int, string> $needles
     */
    private function containsAll(mixed $haystack, array $needles): bool
    {
        $values = array_values(array_filter((array) $haystack, 'is_string'));
        foreach ($needles as $needle) {
            if (!in_array($needle, $values, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int|string, mixed> $testClasses
     */
    private function hasHttpCoverage(array $testClasses): bool
    {
        $knownHttpCoverage = [
            'PrivatePortalPhaseCoverageTest',
            'PrivatePortalFrontControllerTest',
            'PrivatePortalStorageTest',
            'TaxDeclarationHelperRoutesTest',
            'FamilyDiscussionModuleTest',
            'RealEstateRentalModuleTest',
        ];

        foreach ($testClasses as $testClass) {
            if (is_string($testClass) && in_array($testClass, $knownHttpCoverage, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function allowedStatuses(): array
    {
        if ($this->migrationService instanceof PrivateMigrationService) {
            return $this->migrationService->allowedStatuses();
        }

        return [
            PrivateMigrationService::STATUS_PHP_SOURCE,
            PrivateMigrationService::STATUS_MIGRATING,
            PrivateMigrationService::STATUS_NEW_SOURCE,
            PrivateMigrationService::STATUS_RETIRED,
        ];
    }
}
