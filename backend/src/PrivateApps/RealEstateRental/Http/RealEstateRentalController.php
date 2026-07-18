<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Http;

use Caramagnols\Http\Request;
use Caramagnols\Http\Response;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PrivateApps\Documents\PrivateDocumentStorage;
use Caramagnols\PrivateApps\Documents\Contract\DocumentEntityRef;
use Caramagnols\PrivateApps\Documents\Http\StreamedFileResponse;
use Caramagnols\PrivateApps\Documents\Repository\DocumentHubRepository;
use Caramagnols\PrivateApps\Documents\Repository\DocumentTaxonomyRepository;
use Caramagnols\PrivateApps\Documents\Service\DocumentClassificationService;
use Caramagnols\PrivateApps\Documents\Service\DocumentImportService;
use Caramagnols\PrivateApps\Documents\Service\DocumentLinkService;
use Caramagnols\PrivateApps\Documents\Service\DocumentPolicy;
use Caramagnols\PrivateApps\Documents\Service\DocumentStorageService;
use Caramagnols\PrivateApps\Documents\Service\DocumentValidationService;
use Caramagnols\PrivateApps\RealEstateRental\RentalDocumentIntegration;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyImportedDocument;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import\AgencyImportService;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Repository\AgencyImportRepository;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Repository\AgencyMappingRepository;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Service\AgencyAdvancedReconciliationService;
use Caramagnols\PrivatePortal\Http\PrivateResponseHeaders;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Service\AgencyStatementValidationService;
use Caramagnols\PrivateApps\RealEstateRental\Domain\RentalExpenseCategoryCatalog;
use Caramagnols\PrivateApps\RealEstateRental\Domain\RentalLeaseTypeCatalog;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLessorRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyMemberRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalUnitRepository;
use Caramagnols\PrivateApps\RealEstateRental\Service\ChargeRegularizationService;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalAnnualSummaryService;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalDashboardService;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalExportService;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalPaymentRequestService;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentPaymentStatusService;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentScheduleService;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalReceiptService;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivatePortalSecurityGuard;

final class RealEstateRentalController
{
    private const METHOD_GET = 'GET';
    private const METHOD_POST = 'POST';
    private const CSRF_RENTAL = 'private_rental';
    private const MAX_RENTAL_LIST = 200;

    public function __construct(
        private readonly PrivateAuth $auth,
        private readonly PrivatePortalSecurityGuard $securityGuard,
        private readonly PrivateUserRepository $privateUserRepository,
        private readonly PrivateModulePermissionRepository $modulePermissionRepository,
        private readonly RentalPropertyRepository $rentalPropertyRepository,
        private readonly RentalPropertyMemberRepository $rentalPropertyMemberRepository,
        private readonly RentalUnitRepository $rentalUnitRepository,
        private readonly RentalLifecycleRepository $rentalLifecycleRepository,
        private readonly RentalLessorRepository $rentalLessorRepository,
        private readonly AgencyImportRepository $agencyImportRepository,
        private readonly AgencyMappingRepository $agencyMappingRepository,
        private readonly RentalAnnualSummaryService $rentalAnnualSummaryService,
        private readonly RentalDashboardService $rentalDashboardService,
        private readonly RentalExportService $rentalExportService,
        private readonly RentalPaymentRequestService $rentalPaymentRequestService,
        private readonly RentalReceiptService $rentalReceiptService,
        private readonly ChargeRegularizationService $chargeRegularizationService,
        private readonly RentScheduleService $rentScheduleService,
        private readonly RentPaymentStatusService $rentPaymentStatusService,
        private readonly AgencyImportService $agencyImportService,
        private readonly \Closure $render,
        private readonly ?AppEventLogger $eventLogger = null,
        private readonly ?AgencyAdvancedReconciliationService $agencyAdvancedReconciliationService = null,
        private readonly ?PrivateDocumentStorage $privateDocumentStorage = null,
    ) {
    }

    private function privateDocumentStorage(): PrivateDocumentStorage
    {
        return $this->privateDocumentStorage ?? PrivateDocumentStorage::fromAppConfig($this->eventLogger);
    }

    private ?DocumentHubRepository $documentHubRepositoryInstance = null;
    private ?DocumentTaxonomyRepository $documentTaxonomyRepositoryInstance = null;
    private ?DocumentStorageService $documentHubStorageInstance = null;
    private ?DocumentLinkService $documentHubLinkServiceInstance = null;

    private function documentHubRepository(): DocumentHubRepository
    {
        return $this->documentHubRepositoryInstance ??= new DocumentHubRepository(editorial_database());
    }

    private function documentTaxonomyRepository(): DocumentTaxonomyRepository
    {
        return $this->documentTaxonomyRepositoryInstance ??= new DocumentTaxonomyRepository(editorial_database());
    }

    private function documentHubStorage(): DocumentStorageService
    {
        return $this->documentHubStorageInstance ??= DocumentStorageService::fromAppConfig();
    }

    private function documentHubLinkService(): DocumentLinkService
    {
        return $this->documentHubLinkServiceInstance ??= new DocumentLinkService(
            $this->documentHubRepository(),
            editorial_database()
        );
    }

    private function documentHubImportService(): DocumentImportService
    {
        $policy = DocumentPolicy::fromAppConfig();

        return new DocumentImportService(
            $policy,
            new DocumentValidationService($policy),
            $this->documentHubStorage(),
            $this->documentHubRepository(),
            $this->documentTaxonomyRepository(),
            new DocumentClassificationService($this->documentTaxonomyRepository()),
            $this->documentHubLinkService(),
            $this->eventLogger
        );
    }

    private function withPrivateHeaders(Response $response): Response
    {
        return PrivateResponseHeaders::apply($response);
    }

    private function yearFromRequest(Request $request): int
    {
        $query = $request->query();
        $year = is_numeric($query['year'] ?? null) ? (int) $query['year'] : (int) date('Y');
        if ($year < 2000 || $year > 2100) {
            return (int) date('Y');
        }

        return $year;
    }

    private function sanitizeDownloadFilename(string $filename): string
    {
        $filename = trim((string) preg_replace('/[\x00-\x1F\x7F]/', '', $filename));
        $filename = str_replace(["\r", "\n", "\t", '/', '\\'], ' ', $filename);
        $filename = trim(preg_replace('/\s+/', ' ', $filename));
        if ($filename === '') {
            return 'document';
        }

        return $filename;
    }

    public function handle(string $page, Request $request, array $routeParams = []): Response
    {
        return match ($page) {
            'rental_dashboard' => $this->handleRentalDashboard($request),
            'rental_properties_dashboard' => $this->handleRentalPropertiesDashboard($request),
            'rental_agency_dashboard' => $this->handleRentalAgencyDashboard($request),
            'rental_lessors' => $this->handleRentalLessors($request),
            'rental_properties' => $this->handleRentalProperties($request),
            'rental_property_archive' => $this->handleRentalPropertyArchive($request, (int) ($routeParams['propertyId'] ?? 0)),
            'rental_units' => $this->handleRentalUnits($request),
            'rental_unit_archive' => $this->handleRentalUnitArchive($request, (int) ($routeParams['unitId'] ?? 0)),
            'rental_property_members' => $this->handleRentalPropertyMembers($request),
            'rental_tenants' => $this->handleRentalTenants($request),
            'rental_leases' => $this->handleRentalLeases($request),
            'rental_rents' => $this->handleRentalRents($request),
            'rental_payments' => $this->handleRentalPayments($request),
            'rental_expenses' => $this->handleRentalExpenses($request),
            'rental_regularizations' => $this->handleRentalRegularizations($request),
            'rental_documents' => $this->handleRentalDocuments($request),
            'rental_agencies' => $this->redirect(private_portal_url('rental_agency_imports') . '?tab=agencies'),
            'rental_agency_imports' => $this->handleRentalAgencyImports($request),
            'rental_agency_review' => $this->handleRentalAgencyReview($request),
            'rental_document_file' => $this->handleRentalDocumentFile($request, (string) ($routeParams['documentId'] ?? '')),
            'rental_regularization_file' => $this->handleRentalRegularizationFile($request, (string) ($routeParams['documentId'] ?? '')),
            'rental_summary' => $this->handleRentalSummary($request),
            'rental_export' => $this->handleRentalExport($request, (string) ($routeParams['format'] ?? '')),
            default => throw new \InvalidArgumentException("Unknown rental page: {$page}"),
        };
    }

    private function redirect(string $url): Response
    {
        return new Response(302, ['Location' => $url], '');
    }

    private function render(string $template, array $viewModel = []): Response
    {
        return ($this->render)($template, $viewModel);
    }

    private function guard(): PrivatePortalSecurityGuard
    {
        return $this->securityGuard;
    }

    private function logEvent(string $event, array $context = []): void
    {
        if ($this->eventLogger === null) {
            return;
        }
        $this->eventLogger->security($event, $context, 'warning');
    }

    private function normalizeNumericId(mixed $value): int
    {
        if (!is_numeric($value)) {
            return 0;
        }
        $intValue = (int) $value;
        return $intValue > 0 ? $intValue : 0;
    }

    private function currentPrivateUserId(): ?int
    {
        $identifier = $this->auth->currentIdentifier();
        if (!is_string($identifier) || trim($identifier) === '') {
            return null;
        }
        $user = $this->privateUserRepository->findByEmail($identifier);
        if (!is_array($user)) {
            return null;
        }
        $status = is_string($user['status'] ?? null) ? strtolower(trim((string) $user['status'])) : '';
        $id = is_numeric($user['id'] ?? null) ? (int) $user['id'] : 0;
        if ($status !== 'active' || $id <= 0) {
            return null;
        }
        return $id;
    }

    private function requireRentalModuleUser(Request $request): int|Response
    {
        $required = $this->securityGuard->requireAuthenticated($request, private_portal_url('login'), true);
        if ($required !== null) {
            return $this->withPrivateHeaders($required);
        }

        $userId = $this->currentPrivateUserId();
        if ($userId === null) {
            return $this->redirect(private_portal_url('login'));
        }
        if (!$this->modulePermissionRepository->userHasModuleAccess($userId, 'real_estate_rental')) {
            return $this->handleModuleAccessDenied('real_estate_rental');
        }
        return $userId;
    }

    private function handleModuleAccessDenied(string $module): Response
    {
        $this->logEvent('private.module.access_denied', [
            'module' => $module,
            'identifier' => AppEventLogger::maskIdentifier((string) $this->auth->currentIdentifier()),
        ]);

        return $this->redirect(private_portal_url('login'));
    }

    private function authorizedPropertyIds(int $userId): array
    {
        return $this->rentalPropertyMemberRepository->activePropertyIdsForUser($userId);
    }

    private function rentalNotice(string $key): string
    {
        return match ($key) {
            'property_created' => 'Propriété créée.', 'property_updated' => 'Propriété mise à jour.',
            'property_archived' => 'Propriété archivée.', 'unit_created' => 'Bien locatif créé.',
            'unit_updated' => 'Bien locatif mis à jour.', 'unit_archived' => 'Bien locatif archivé.',
            'member_created' => 'Membre locatif ajouté.', 'member_updated' => 'Accès membre mis à jour.',
            'member_deleted' => 'Accès membre supprimé.', 'tenant_created' => 'Locataire créé.',
            'tenant_updated' => 'Locataire mis à jour.', 'tenant_deleted' => 'Locataire supprimé.',
            'lease_created' => 'Bail créé.', 'lease_updated' => 'Bail mis à jour.',
            'lease_adjusted' => 'Réajustement du bail appliqué.', 'lease_deleted' => 'Bail supprimé.',
            'rent_created' => 'Loyer créé.', 'rent_schedule_generated' => 'Échéancier généré.',
            'rent_schedule_existing' => 'Échéancier déjà à jour.', 'rent_schedule_partial' => 'Échéancier partiellement généré.',
            'rent_deleted' => 'Loyer supprimé.', 'payment_created' => 'Paiement locatif créé.',
            'payment_updated' => 'Paiement locatif corrigé.', 'payment_cancelled' => 'Paiement locatif annulé.',
            'payment_deleted' => 'Paiement locatif supprimé.', 'receipt_emailed' => 'Quittance envoyée par email.',
            'expense_created' => 'Charge locative créée.', 'expense_deleted' => 'Charge locative supprimée.',
            'regularization_generated' => 'Régularisation de charges générée.',
            'document_uploaded' => 'Document locatif envoyé.', 'document_deleted' => 'Document locatif supprimé.',
            'document_emailed' => 'Email locatif envoyé.', 'rental_data_purged' => 'Données locatives supprimées.',
            'agency_imported' => 'Document agence importé et préparé pour revue.',
            'agency_import_ignored' => 'Fichier annexe ignoré.', 'agency_document_deleted' => 'Document agence supprimé.',
            'agency_created' => 'Agence créée.', 'agency_unit_mapping_created' => 'Correspondance agence créée.',
            'agency_unit_mapping_deleted' => 'Correspondance agence supprimée.',
            'agency_statement_property_updated' => 'Rattachement du relevé mis à jour.',
            'agency_line_validated' => 'Ligne agence validée.', 'agency_line_corrected' => 'Ligne agence corrigée.',
            'agency_line_ignored' => 'Ligne agence ignorée.', 'lessor_created' => 'Bailleur créé.',
            'lessor_updated' => 'Bailleur mis à jour.', 'lessor_deleted' => 'Bailleur supprimé.',
            default => '',
        };
    }

    private function rentalError(string $key): string
    {
        return match ($key) {
            'rental_invalid_request' => 'Session expirée, veuillez recharger la page puis recommencer.',
            'rental_write_failed' => 'Les données locatives sont invalides ou incomplètes.',
            'rental_overpayment_requires_confirmation' => 'Surpaiement détecté : cochez la confirmation pour enregistrer.',
            'rental_archive_failed' => 'Archivage locatif impossible.',
            'property_forbidden' => "Vous n'avez pas le droit de modifier cette propriété.",
            'unit_forbidden' => "Vous n'avez pas le droit de modifier ce bien locatif.",
            'member_forbidden' => "Vous n'avez pas le droit de modifier cet accès.",
            'tenant_forbidden' => "Vous n'avez pas le droit de modifier ce locataire.",
            'lease_forbidden' => "Vous n'avez pas le droit de modifier ce bail.",
            'rent_forbidden' => "Vous n'avez pas le droit de modifier ce loyer.",
            'payment_forbidden' => "Vous n'avez pas le droit de modifier ce paiement.",
            'expense_forbidden' => "Vous n'avez pas le droit de modifier cette charge.",
            'document_forbidden' => "Vous n'avez pas le droit de modifier ce document.",
            'agency_forbidden' => "Vous n'avez pas le droit de modifier cette agence.",
            'no_property_selected' => 'Aucune propriété sélectionnée.',
            'no_unit_selected' => 'Aucun bien locatif sélectionné.',
            'no_tenant_selected' => 'Aucun locataire sélectionné.',
            'no_lease_selected' => 'Aucun bail sélectionné.',
            'tenant_required_for_unit' => 'Il faut créer un locataire pour ce bien locatif avant de créer un bail.',
            'lease_unit_unavailable' => 'Ce bien locatif est indisponible ou possède déjà un bail actif.',
            'rental_missing_file' => 'Aucun fichier reçu.',
            'rental_upload_failed' => "L'envoi du document a échoué.",
            'rental_purge_confirmation_required' => 'Saisissez SUPPRIMER pour confirmer.',
            'rental_delete_failed' => 'Suppression impossible.',
            'hub_forbidden_extension' => 'Format de fichier non autorisé.',
            'hub_mime_mismatch' => "Le contenu du fichier ne correspond pas à son extension.",
            'hub_invalid_signature' => 'Le fichier est corrompu ou son format est invalide.',
            'hub_macro_detected' => 'Les documents contenant des macros sont refusés.',
            'hub_encrypted_document' => 'Les fichiers protégés par mot de passe ou chiffrés sont refusés.',
            'hub_file_too_large' => 'Fichier trop volumineux.',
            'hub_batch_too_large' => 'Le lot dépasse la taille maximale autorisée.',
            'hub_image_too_many_pixels' => 'Image trop grande (nombre de pixels).',
            'hub_infected' => 'Fichier bloqué par le contrôle antivirus.',
            'hub_entity_forbidden' => "Vous n'avez pas accès à cet élément.",
            'hub_entity_not_found' => 'Élément métier introuvable.',
            default => str_starts_with($key, 'hub_') ? "L'import a échoué (" . substr($key, 4) . ').' : '',
        };
    }

    private function rentalBaseViewModel(string $title, string $notice, string $error): array
    {
        return [
            'privatePageTitle' => $title,
            'rentalCsrfToken' => csrf_token(self::CSRF_RENTAL),
            'rentalNotice' => $this->rentalNotice($notice),
            'rentalError' => $this->rentalError($error),
            'rentalUrls' => [
                'dashboard' => private_portal_url('rental_dashboard'),
                'propertiesDashboard' => private_portal_url('rental_properties_dashboard'),
                'agencyDashboard' => private_portal_url('rental_agency_dashboard'),
                'lessors' => private_portal_url('rental_lessors'),
                'properties' => private_portal_url('rental_properties'), 'units' => private_portal_url('rental_units'),
                'members' => private_portal_url('rental_property_members'), 'tenants' => private_portal_url('rental_tenants'),
                'leases' => private_portal_url('rental_leases'), 'rents' => private_portal_url('rental_rents'),
                'payments' => private_portal_url('rental_payments'), 'expenses' => private_portal_url('rental_expenses'),
                'regularizations' => private_portal_url('rental_regularizations'), 'documents' => private_portal_url('rental_documents'),
                'agencies' => private_portal_url('rental_agencies'), 'agencyImports' => private_portal_url('rental_agency_imports'),
                'agencyReview' => private_portal_url('rental_agency_review'), 'summary' => private_portal_url('rental_summary'),
                'exportCsv' => private_portal_url('rental_export_csv'), 'exportPdf' => private_portal_url('rental_export_pdf'),
                'exportZip' => private_portal_url('rental_export_zip'),
            ],
            'privateDashboardLogoutUrl' => private_portal_url('logout'),
            'privateLogoutCsrfToken' => csrf_token('private_logout'),
        ];
    }

    private function objectsToArrays(array $objects): array
    {
        return array_values(array_map(
            static fn ($object): array => is_object($object) && method_exists($object, 'toArray')
                ? $object->toArray()
                : (is_array($object) ? $object : []),
            $objects
        ));
    }

    private function rentalPropertyRepository(): RentalPropertyRepository { return $this->rentalPropertyRepository; }
    private function rentalPropertyMemberRepository(): RentalPropertyMemberRepository { return $this->rentalPropertyMemberRepository; }
    private function rentalUnitRepository(): RentalUnitRepository { return $this->rentalUnitRepository; }
    private function rentalLifecycleRepository(): RentalLifecycleRepository { return $this->rentalLifecycleRepository; }
    private function rentalLessorRepository(): RentalLessorRepository { return $this->rentalLessorRepository; }
    private function rentalAnnualSummaryService(): RentalAnnualSummaryService { return $this->rentalAnnualSummaryService; }
    private function rentalDashboardService(): RentalDashboardService { return $this->rentalDashboardService; }
    private function rentalExportService(): RentalExportService { return $this->rentalExportService; }
    private function rentalPaymentRequestService(): RentalPaymentRequestService { return $this->rentalPaymentRequestService; }
    private function rentalReceiptService(): RentalReceiptService { return $this->rentalReceiptService; }
    private function chargeRegularizationService(): ChargeRegularizationService { return $this->chargeRegularizationService; }
    private function rentScheduleService(): RentScheduleService { return $this->rentScheduleService; }
    private function rentPaymentStatusService(): RentPaymentStatusService { return $this->rentPaymentStatusService; }
    private function agencyImportRepository(): AgencyImportRepository { return $this->agencyImportRepository; }
    private function agencyMappingRepository(): AgencyMappingRepository { return $this->agencyMappingRepository; }
    private function agencyImportService(): AgencyImportService { return $this->agencyImportService; }
    private function agencyAdvancedReconciliationService(): AgencyAdvancedReconciliationService
    { return $this->agencyAdvancedReconciliationService ?? new AgencyAdvancedReconciliationService(); }

    private function canWriteProperty(int $propertyId, int $userId): bool
    {
        return $this->rentalPropertyMemberRepository()->canWrite($propertyId, $userId);
    }

    private function canDeleteProperty(int $propertyId, int $userId): bool
    {
        return $this->rentalPropertyMemberRepository()->canDelete($propertyId, $userId);
    }

    private function canWriteByPropertyId(int $propertyId, int $userId): bool
    {
        return $propertyId > 0 && $this->canWriteProperty($propertyId, $userId);
    }

    private function writableRentalPropertyIds(array $propertyIds, int $userId): array
    {
        $writable = [];
        foreach ($propertyIds as $propertyId) {
            if ($this->canWriteProperty($propertyId, $userId)) {
                $writable[] = $propertyId;
            }
        }
        return $writable;
    }

    private function forbiddenOrUnauthorized(string $location): Response
    {
        return $this->handleModuleAccessDenied('real_estate_rental');
    }

    private function rentalUnitDetailsFromBody(array $body): array
    {
        return [
            'tax_identifier' => is_string($body['tax_identifier'] ?? null) ? (string) $body['tax_identifier'] : null,
            'room_count' => $body['room_count'] ?? null,
            'designation' => is_string($body['designation'] ?? null) ? (string) $body['designation'] : null,
            'other_details' => is_string($body['other_details'] ?? null) ? (string) $body['other_details'] : null,
            'equipment_elements' => is_string($body['equipment_elements'] ?? null) ? (string) $body['equipment_elements'] : null,
            'heating_production_mode' => is_string($body['heating_production_mode'] ?? null) ? (string) $body['heating_production_mode'] : null,
            'hot_water_production_mode' => is_string($body['hot_water_production_mode'] ?? null) ? (string) $body['hot_water_production_mode'] : null,
            'sanitation' => is_string($body['sanitation'] ?? null) ? (string) $body['sanitation'] : null,
        ];
    }

    private function rentalTenantDetailsFromBody(array $body): array
    {
        return [
            'last_name' => is_string($body['last_name'] ?? null) ? (string) $body['last_name'] : null,
            'first_names' => is_string($body['first_names'] ?? null) ? (string) $body['first_names'] : null,
            'birth_date' => is_string($body['birth_date'] ?? null) ? (string) $body['birth_date'] : null,
            'birth_city' => is_string($body['birth_city'] ?? null) ? (string) $body['birth_city'] : null,
            'birth_country' => is_string($body['birth_country'] ?? null) ? (string) $body['birth_country'] : null,
            'nationality' => is_string($body['nationality'] ?? null) ? (string) $body['nationality'] : null,
            'occupation' => is_string($body['occupation'] ?? null) ? (string) $body['occupation'] : null,
            'postal_address' => is_string($body['postal_address'] ?? null) ? (string) $body['postal_address'] : null,
        ];
    }

    private function rentalTenantFullNameFromBody(array $body): string
    {
        $lastName = is_string($body['last_name'] ?? null) ? trim((string) $body['last_name']) : '';
        $firstNames = is_string($body['first_names'] ?? null) ? trim((string) $body['first_names']) : '';

        if ($lastName !== '' || $firstNames !== '') {
            return '';
        }

        return is_string($body['full_name'] ?? null) ? (string) $body['full_name'] : '';
    }

    private function appendRentalLeaseAdjustmentNote(
        ?string $existingNotes,
        string $month,
        float $monthlyRent,
        float $chargesProvision,
        string $freeNote
    ): string {
        $month = preg_match('/\A\d{4}-\d{2}\z/', trim($month)) === 1 ? trim($month) : date('Y-m');
        $sanitizedFreeNote = $this->sanitizeTextField($freeNote, 500);
        $line = sprintf(
            '[%s] Reajustement annuel: loyer %.2f EUR, provision charges %.2f EUR.',
            $month,
            round($monthlyRent, 2),
            round($chargesProvision, 2)
        );
        if ($sanitizedFreeNote !== '') {
            $line .= ' ' . $sanitizedFreeNote;
        }

        return $this->sanitizeTextField(trim((string) $existingNotes . "\n" . $line), 2000);
    }

    private function unitBelongsToProperty(int $unitId, int $propertyId): bool
    {
        if ($unitId <= 0 || $propertyId <= 0) {
            return false;
        }

        $unit = $this->rentalUnitRepository()->findById($unitId);
        return $unit !== null && $unit->rentalPropertyId === $propertyId;
    }

    private function tenantBelongsToUnit(int $tenantId, int $unitId, int $propertyId): bool
    {
        if ($tenantId <= 0 || $unitId <= 0 || $propertyId <= 0) {
            return false;
        }

        $tenant = $this->rentalLifecycleRepository()->findTenantById($tenantId);
        return is_array($tenant)
            && is_numeric($tenant['rentalPropertyId'] ?? null)
            && is_numeric($tenant['rentalUnitId'] ?? null)
            && (int) $tenant['rentalPropertyId'] === $propertyId
            && (int) $tenant['rentalUnitId'] === $unitId;
    }

    private function sanitizeTextField(string $value, int $maxLength): string
    {
        if (!function_exists('sanitize_text_field')) {
            return substr(trim($value), 0, $maxLength);
        }
        return sanitize_text_field($value, $maxLength);
    }

    private function rentalPeriodFromBody(array $body): array
    {
        $monthPicker = is_string($body['period_month_picker'] ?? null) ? trim((string) $body['period_month_picker']) : '';
        if (preg_match('/\A(\d{4})-(\d{2})\z/', $monthPicker, $matches) === 1) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];

            return [$year >= 2000 && $year <= 2100 ? $year : 0, $month >= 1 && $month <= 12 ? $month : 0];
        }

        $year = is_numeric($body['period_year'] ?? null) ? (int) $body['period_year'] : 0;
        $month = is_numeric($body['period_month'] ?? null) ? (int) $body['period_month'] : 0;

        return [$year >= 2000 && $year <= 2100 ? $year : 0, $month >= 1 && $month <= 12 ? $month : 0];
    }

    private function rentScheduleNotice(array $result): string
    {
        $created = (int) ($result['created'] ?? 0);
        $existing = (int) ($result['existing'] ?? 0);
        $skipped = (int) ($result['skipped'] ?? 0);
        if ($created > 0 && $skipped === 0) {
            return 'rent_schedule_generated';
        }
        if ($created > 0) {
            return 'rent_schedule_partial';
        }
        if ($existing > 0 && $skipped === 0) {
            return 'rent_schedule_existing';
        }

        return 'rent_schedule_partial';
    }

    private function rentalRentAmountDetailsFromBody(array $body, array $lease): ?array
    {
        $items = [];
        $amount = 0.0;
        if (($body['include_lease_rent'] ?? null) !== null) {
            $rent = round((float) ($lease['monthlyRent'] ?? 0), 2);
            if ($rent > 0) {
                $items[] = ['Loyer du bail', $rent];
                $amount += $rent;
            }
        }

        if (($body['include_lease_charges'] ?? null) !== null) {
            $charges = round((float) ($lease['chargesProvision'] ?? 0), 2);
            if ($charges > 0) {
                $items[] = ['Provision charges du bail', $charges];
                $amount += $charges;
            }
        }

        $labels = is_array($body['extra_label'] ?? null) ? $body['extra_label'] : [];
        $amounts = is_array($body['extra_amount'] ?? null) ? $body['extra_amount'] : [];
        $count = min(max(count($labels), count($amounts)), 20);
        for ($index = 0; $index < $count; ++$index) {
            $label = $this->sanitizeTextField((string) ($labels[$index] ?? ''), 80);
            $extraAmount = is_numeric($amounts[$index] ?? null) ? round((float) $amounts[$index], 2) : 0.0;
            if ($label === '' || $extraAmount <= 0) {
                continue;
            }

            $items[] = [$label, $extraAmount];
            $amount += $extraAmount;
        }

        $amount = round($amount, 2);
        if ($amount <= 0 || $items === []) {
            return null;
        }

        $notes = [];
        $manualNotes = is_string($body['notes'] ?? null) ? $this->sanitizeTextField((string) $body['notes'], 900) : '';
        if ($manualNotes !== '') {
            $notes[] = $manualNotes;
        }

        $breakdownLines = [];
        foreach ($items as $item) {
            $breakdownLines[] = sprintf('- %s: %.2f EUR', (string) $item[0], (float) $item[1]);
        }
        $notes[] = "Detail quittance:\n" . implode("\n", $breakdownLines);
        $receiptText = is_string($body['receipt_text'] ?? null) ? $this->sanitizeTextField((string) $body['receipt_text'], 700) : '';
        if ($receiptText !== '') {
            $notes[] = "Mention quittance:\n" . $receiptText;
        }

        return [
            'amount' => $amount,
            'notes' => $this->sanitizeTextField(implode("\n\n", $notes), 2000),
        ];
    }

    private function normalizeDocumentId(string $documentId): string
    {
        $normalized = trim($documentId);
        if ($normalized === '' || preg_match('/\A[A-Za-z0-9._-]{1,160}\z/', $normalized) !== 1) {
            return '';
        }

        return $normalized;
    }

    private function documentIdsFromPayload(mixed $payload): array
    {
        $values = is_array($payload) ? $payload : [$payload];
        $ids = [];
        foreach ($values as $value) {
            $id = $this->normalizeDocumentId((string) $value);
            if ($id !== '' && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function leaseBelongsToProperty(int $leaseId, int $propertyId): bool
    {
        if ($leaseId <= 0 || $propertyId <= 0) {
            return false;
        }

        $lease = $this->rentalLifecycleRepository()->findLeaseById($leaseId);
        return is_array($lease)
            && is_numeric($lease['rentalPropertyId'] ?? null)
            && (int) $lease['rentalPropertyId'] === $propertyId;
    }

    private function leaseBelongsToUnit(int $leaseId, int $unitId, int $propertyId): bool
    {
        if ($leaseId <= 0 || $unitId <= 0 || $propertyId <= 0) {
            return false;
        }

        $lease = $this->rentalLifecycleRepository()->findLeaseById($leaseId);
        return is_array($lease)
            && is_numeric($lease['rentalPropertyId'] ?? null)
            && is_numeric($lease['rentalUnitId'] ?? null)
            && (int) $lease['rentalPropertyId'] === $propertyId
            && (int) $lease['rentalUnitId'] === $unitId;
    }

    /**
     * Téléchargement streamé d'un document du hub central si l'identifiant en
     * est un ; null sinon (le flux legacy prend le relais).
     */
    private function tryHubDocumentDownload(string $documentId, int $userId): ?Response
    {
        if (preg_match('/\A[a-f0-9]{32}\z/', $documentId) !== 1) {
            return null;
        }

        $document = $this->documentHubRepository()->findDocumentByUid($documentId);
        if ($document === null) {
            return null;
        }

        $document['links'] = $this->documentHubRepository()->linksForDocument((int) $document['id']);
        if (!$this->documentHubLinkService()->userCanAccessDocument($document, $userId)) {
            return $this->handleModuleAccessDenied('real_estate_rental');
        }

        if ((string) ($document['scan_status'] ?? 'clean') === 'infected') {
            return $this->withPrivateHeaders(new Response(403, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Forbidden'));
        }

        $absolutePath = $this->documentHubStorage()->absolutePathForKey((string) ($document['storage_key'] ?? ''));
        if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return $this->withPrivateHeaders(new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Not Found'));
        }

        $mimeType = (string) ($document['mime_type'] ?? '');
        if (preg_match('/\A[a-z0-9.+-]+\/[a-z0-9.+-]+\z/i', $mimeType) !== 1) {
            $mimeType = 'application/octet-stream';
        }

        $this->logEvent('private.rental_document.downloaded', [
            'private_user_id' => $userId,
            'document_id' => $documentId,
            'source' => 'document_hub',
        ]);

        $filename = $this->sanitizeDownloadFilename((string) ($document['original_filename'] ?? 'document'));

        return $this->withPrivateHeaders(new StreamedFileResponse($absolutePath, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) (int) ($document['stored_size'] ?? 0),
            'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]));
    }

    private function deleteRentalDocuments(array $documentIds, array $propertyIds, int $userId): int
    {
        $deleted = 0;
        foreach ($documentIds as $documentId) {
            if (preg_match('/\A[a-f0-9]{32}\z/', (string) $documentId) === 1) {
                $hubDocument = $this->documentHubRepository()->findDocumentByUid((string) $documentId);
                if ($hubDocument !== null) {
                    $hubDocument['links'] = $this->documentHubRepository()->linksForDocument((int) $hubDocument['id']);
                    if (
                        $this->documentHubLinkService()->userCanAccessDocument($hubDocument, $userId)
                        && $this->documentHubRepository()->transitionStatus(
                            (int) $hubDocument['id'],
                            DocumentHubRepository::DOC_STATUS_TRASHED
                        )
                    ) {
                        ++$deleted;
                        $this->logEvent('private.rental_document.deleted', [
                            'private_user_id' => $userId,
                            'document_id' => (string) $documentId,
                            'source' => 'document_hub_trash',
                        ]);
                    }

                    continue;
                }
            }

            $document = $this->rentalLifecycleRepository()->findDocumentByDocumentId($documentId);
            $propertyId = is_array($document) && is_numeric($document['rentalPropertyId'] ?? null)
                ? (int) $document['rentalPropertyId']
                : 0;
            if (!is_array($document) || !in_array($propertyId, $propertyIds, true)) {
                continue;
            }

            if (!$this->rentalLifecycleRepository()->deactivateDocumentByDocumentId($documentId, $propertyIds)) {
                continue;
            }

            // Note: privateDocumentStorage() is not available in this controller
            // For now, we'll skip the storage deletion
            ++$deleted;
            $this->logEvent('private.rental_document.deleted', [
                'private_user_id' => $userId,
                'rental_property_id' => $propertyId,
                'document_id' => $documentId,
            ]);
        }

        return $deleted;
    }

    private function rentalPaymentDataFromBody(array $body): ?array
    {
        $paymentDate = is_string($body['payment_date'] ?? null) ? trim((string) $body['payment_date']) : '';
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $paymentDate) !== 1) {
            return null;
        }

        $amountPaid = is_numeric($body['amount_paid'] ?? null) ? round((float) $body['amount_paid'], 2) : 0.0;
        if ($amountPaid <= 0) {
            return null;
        }

        $status = is_string($body['status'] ?? null) ? strtolower(trim((string) $body['status'])) : 'draft';
        if (!in_array($status, ['draft', 'validated', 'cancelled'], true)) {
            return null;
        }

        $paymentKind = is_string($body['payment_kind'] ?? null) ? strtolower(trim((string) $body['payment_kind'])) : 'tenant';
        if ($paymentKind === 'regularization') {
            $paymentKind = 'adjustment';
        }
        if (!in_array($paymentKind, ['tenant', 'caf', 'refund', 'adjustment'], true)) {
            $paymentKind = 'tenant';
        }

        $paymentMethod = is_string($body['payment_method'] ?? null) ? strtolower(trim((string) $body['payment_method'])) : '';
        if ($paymentMethod === 'transfer') {
            $paymentMethod = 'bank_transfer';
        }
        if ($paymentMethod !== '' && !in_array($paymentMethod, ['bank_transfer', 'cash', 'cheque', 'card', 'direct_debit', 'other'], true)) {
            $paymentMethod = 'other';
        }

        $paymentReference = is_string($body['payment_reference'] ?? null) ? trim((string) $body['payment_reference']) : '';
        $notes = is_string($body['notes'] ?? null) ? trim((string) $body['notes']) : '';

        return [
            'paymentDate' => $paymentDate,
            'amountPaid' => $amountPaid,
            'status' => $status,
            'paymentKind' => $paymentKind,
            'paymentMethod' => $paymentMethod !== '' ? $paymentMethod : null,
            'paymentReference' => $paymentReference !== '' ? substr($paymentReference, 0, 160) : null,
            'notes' => $notes !== '' ? $notes : null,
            'confirmOverpayment' => isset($body['confirm_overpayment']) && (string) $body['confirm_overpayment'] === '1',
        ];
    }

    private function renderRentalDashboard(int $userId): Response
    {
        $propertyIds = $this->authorizedPropertyIds($userId);
        $properties = $propertyIds === [] ? [] : $this->rentalPropertyRepository()->listByIds($propertyIds, self::MAX_RENTAL_LIST);
        $units = $propertyIds === [] ? [] : $this->rentalUnitRepository()->listByPropertyIds($propertyIds, self::MAX_RENTAL_LIST);
        $tenants = $this->rentalLifecycleRepository()->listTenants($propertyIds, self::MAX_RENTAL_LIST);
        $leases = $this->rentalLifecycleRepository()->listLeases($propertyIds, self::MAX_RENTAL_LIST);
        $year = (int) date('Y');
        $month = (int) date('n');
        $dashboardStats = $this->rentalDashboardService()->build($year, $month, $propertyIds);
        $documents = $this->rentalLifecycleRepository()->listDocuments($propertyIds, self::MAX_RENTAL_LIST);
        $agencyDocuments = $this->agencyImportRepository()->listRecentDocumentsForUser($userId, self::MAX_RENTAL_LIST);
        $pendingAgencyDocuments = 0;
        foreach ($agencyDocuments as $document) {
            $status = is_array($document) && is_string($document['reviewStatus'] ?? null) ? (string) $document['reviewStatus'] : '';
            if (in_array($status, ['pending', 'to_review', 'new'], true)) { ++$pendingAgencyDocuments; }
        }
        return $this->render('modules/real-estate-rental/dashboard', array_merge(
            $this->rentalBaseViewModel('Tableau de bord locatif', '', ''),
            [
                'rentalCurrentSection' => 'dashboard',
                'rentalDashboardStats' => array_merge($dashboardStats, [
                    'year' => $year, 'month' => $month, 'propertyCount' => count($properties),
                    'unitCount' => count($units), 'tenantCount' => count($tenants),
                    'activeLeaseCount' => count(array_filter($leases, static fn (array $lease): bool => in_array((string) ($lease['status'] ?? ''), ['draft', 'validated'], true))),
                    'documentCount' => count($documents), 'agencyDocumentCount' => count($agencyDocuments),
                    'pendingAgencyDocumentCount' => $pendingAgencyDocuments, 'taxSummaryUrl' => private_portal_url('tax_dashboard'),
                ]),
                'rentalProperties' => $this->objectsToArrays($properties),
                'rentalRecentDocuments' => array_slice($documents, 0, 8),
                'agencyImportDocuments' => array_slice($agencyDocuments, 0, 8),
            ]
        ));
    }

    private function renderRentalPropertiesDashboard(int $userId): Response
    {
        $propertyIds = $this->authorizedPropertyIds($userId);
        $properties = $propertyIds === [] ? [] : $this->rentalPropertyRepository()->listByIds($propertyIds, self::MAX_RENTAL_LIST);
        $units = $propertyIds === [] ? [] : $this->rentalUnitRepository()->listByPropertyIds($propertyIds, self::MAX_RENTAL_LIST);
        $tenants = $this->rentalLifecycleRepository()->listTenants($propertyIds, self::MAX_RENTAL_LIST);
        $leases = $this->rentalLifecycleRepository()->listLeases($propertyIds, self::MAX_RENTAL_LIST);
        $year = (int) date('Y');
        $month = (int) date('n');
        $dashboardStats = $this->rentalDashboardService()->build($year, $month, $propertyIds);
        $documents = $this->rentalLifecycleRepository()->listDocuments($propertyIds, self::MAX_RENTAL_LIST);
        return $this->render('modules/real-estate-rental/properties-dashboard', array_merge(
            $this->rentalBaseViewModel('Tableau de bord - Biens et locations', '', ''),
            [
                'rentalCurrentSection' => 'personal',
                'rentalCurrentSubsection' => 'propertiesDashboard',
                'rentalDashboardStats' => array_merge($dashboardStats, [
                    'year' => $year, 'month' => $month, 'propertyCount' => count($properties),
                    'unitCount' => count($units), 'tenantCount' => count($tenants),
                    'activeLeaseCount' => count(array_filter($leases, static fn (array $lease): bool => in_array((string) ($lease['status'] ?? ''), ['draft', 'validated'], true))),
                    'documentCount' => count($documents),
                ]),
                'rentalProperties' => $this->objectsToArrays($properties),
                'rentalRecentDocuments' => array_slice($documents, 0, 8),
            ]
        ));
    }

    private function renderRentalAgencyDashboard(int $userId): Response
    {
        $agencyDocuments = $this->agencyImportRepository()->listRecentDocumentsForUser($userId, self::MAX_RENTAL_LIST);
        $agencyBatches = $this->agencyImportRepository()->listRecentBatches($userId, 50);
        $agencies = $this->agencyImportRepository()->listAgencies($userId, 100);
        $unitMappings = $this->agencyImportRepository()->listUnitMappings($userId, 200);
        $pendingAgencyDocuments = 0;
        foreach ($agencyDocuments as $document) {
            $status = is_array($document) && is_string($document['reviewStatus'] ?? null) ? (string) $document['reviewStatus'] : '';
            if (in_array($status, ['pending', 'to_review', 'new'], true)) { ++$pendingAgencyDocuments; }
        }
        return $this->render('modules/real-estate-rental/agency-dashboard', array_merge(
            $this->rentalBaseViewModel('Tableau de bord - Agence', '', ''),
            [
                'rentalCurrentSection' => 'agency',
                'rentalCurrentSubsection' => 'agencyDashboard',
                'agencyDashboardStats' => [
                    'agencyCount' => count($agencies),
                    'agencyDocumentCount' => count($agencyDocuments),
                    'pendingAgencyDocumentCount' => $pendingAgencyDocuments,
                    'agencyBatchCount' => count($agencyBatches),
                    'agencyUnitMappingCount' => count($unitMappings),
                ],
                'agencyImportDocuments' => array_slice($agencyDocuments, 0, 10),
                'agencyImportBatches' => array_map(
                    static fn ($batch): array => method_exists($batch, 'toArray') ? $batch->toArray() : [],
                    array_slice($agencyBatches, 0, 10)
                ),
            ]
        ));
    }

    private function renderRentalLessors(int $userId, string $notice = '', string $error = ''): Response
    {
        return $this->render('modules/real-estate-rental/lessors', array_merge(
            $this->rentalBaseViewModel('Bailleurs', $notice, $error),
            [
                'rentalCurrentSection' => 'personal',
                'rentalCurrentSubsection' => 'lessors',
                'rentalLessors' => $this->rentalLessorRepository()->listForUser($userId),
            ]
        ));
    }

    private function renderRentalProperties(int $userId, string $notice = '', string $error = ''): Response
    {
        $propertyIds = $this->authorizedPropertyIds($userId);
        $properties = $propertyIds === [] ? [] : $this->rentalPropertyRepository()->listByIds($propertyIds, self::MAX_RENTAL_LIST);
        $lessors = $this->rentalLessorRepository()->listForUser($userId, self::MAX_RENTAL_LIST);
        return $this->render('modules/real-estate-rental/properties', array_merge(
            $this->rentalBaseViewModel('Propriétés', $notice, $error),
            ['rentalCurrentSection' => 'personal', 'rentalCurrentSubsection' => 'properties',
             'rentalProperties' => $this->objectsToArrays($properties), 'rentalLessors' => $lessors,]
        ));
    }

    // Render Methods
    private function renderRentalUnits(
        int $userId,
        array $properties,
        array $units,
        string $notice = '',
        string $error = ''
    ): Response {
        return $this->render('modules/real-estate-rental/units', array_merge(
            $this->rentalBaseViewModel('Biens locatifs', $notice, $error),
            [
                'rentalCurrentSection' => 'personal',
                'rentalCurrentSubsection' => 'units',
                'rentalProperties' => $this->objectsToArrays($properties),
                'rentalUnits' => $this->objectsToArrays($units),
            ]
        ));
    }
    private function renderRentalMembers(int $userId, array $members, string $notice = '', string $error = ''): Response
    {
        $propertyIds = $this->authorizedPropertyIds($userId);
        $properties = $propertyIds === []
            ? []
            : $this->rentalPropertyRepository()->listByIds($propertyIds, self::MAX_RENTAL_LIST);

        return $this->render('modules/real-estate-rental/property-members', array_merge(
            $this->rentalBaseViewModel('Membres locatifs', $notice, $error),
            [
                'rentalCurrentSection' => 'personal',
                'rentalCurrentSubsection' => 'members',
                'rentalProperties' => $this->objectsToArrays($properties),
                'rentalMembers' => $members,
                'rentalCurrentPrivateUserId' => $userId,
            ]
        ));
    }
    private function renderRentalTenants(
        array $properties,
        array $units,
        array $tenants,
        string $notice = '',
        string $error = ''
    ): Response {
        return $this->render('modules/real-estate-rental/tenants', array_merge(
            $this->rentalBaseViewModel('Locataires locatifs', $notice, $error),
            [
                'rentalCurrentSection' => 'personal',
                'rentalCurrentSubsection' => 'tenants',
                'rentalProperties' => $this->objectsToArrays($properties),
                'rentalUnits' => $this->objectsToArrays($units),
                'rentalTenants' => $tenants,
            ]
        ));
    }
    private function renderRentalLeases(
        array $properties,
        array $units,
        array $tenants,
        array $leases,
        string $notice = '',
        string $error = ''
    ): Response {
        return $this->render('modules/real-estate-rental/leases', array_merge(
            $this->rentalBaseViewModel('Baux locatifs', $notice, $error),
            [
                'rentalCurrentSection' => 'personal',
                'rentalCurrentSubsection' => 'leases',
                'rentalProperties' => $this->objectsToArrays($properties),
                'rentalUnits' => $this->objectsToArrays($units),
                'rentalTenants' => $tenants,
                'rentalLeases' => $leases,
                'rentalLeaseTypes' => RentalLeaseTypeCatalog::options(),
            ]
        ));
    }
    private function renderRentalRents(
        array $leases,
        array $rents,
        string $notice = '',
        string $error = ''
    ): Response {
        $propertyIds = [];
        $rentIds = [];
        foreach ($rents as $rent) {
            if (!is_array($rent)) {
                continue;
            }
            if (is_numeric($rent['rentalPropertyId'] ?? null)) {
                $propertyIds[] = (int) $rent['rentalPropertyId'];
            }
            if (is_numeric($rent['id'] ?? null)) {
                $rentIds[] = (int) $rent['id'];
            }
        }
        $propertyIds = array_values(array_unique(array_filter($propertyIds, static fn (int $propertyId): bool => $propertyId > 0)));
        $paymentRequestPreviews = $propertyIds !== [] ? $this->rentalPaymentRequestPreviews($rents, $propertyIds) : [];
        $paymentRequestHistory = $this->rentalPaymentRequestHistory($rentIds);

        return $this->render('modules/real-estate-rental/rents', array_merge(
            $this->rentalBaseViewModel('Loyers locatifs', $notice, $error),
            [
                'rentalCurrentSection' => 'personal',
                'rentalCurrentSubsection' => 'rents',
                'rentalLeases' => $leases,
                'rentalRents' => $rents,
                'rentalPaymentRequestPreviews' => $paymentRequestPreviews,
                'rentalPaymentRequestHistory' => $paymentRequestHistory,
            ]
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $rents
     * @param array<int, int> $propertyIds
     * @return array<int, array<string, mixed>>
     */
    private function rentalPaymentRequestPreviews(array $rents, array $propertyIds): array
    {
        $previews = [];
        foreach ($rents as $rent) {
            if (!is_array($rent) || !is_numeric($rent['id'] ?? null)) {
                continue;
            }
            $rentId = (int) $rent['id'];
            $preview = $this->rentalPaymentRequestService()->previewForRent($rentId, $propertyIds);
            if (is_array($preview)) {
                $previews[$rentId] = $preview;
            }
        }

        return $previews;
    }

    /**
     * @param array<int, int> $rentIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function rentalPaymentRequestHistory(array $rentIds): array
    {
        $rentIds = array_values(array_unique(array_filter($rentIds, static fn (int $rentId): bool => $rentId > 0)));
        if ($rentIds === []) {
            return [];
        }

        $history = [];
        foreach ($this->rentalLifecycleRepository()->listPaymentRequestsForRents($rentIds) as $request) {
            if (!is_array($request) || !is_numeric($request['rentalRentId'] ?? null)) {
                continue;
            }
            $rentId = (int) $request['rentalRentId'];
            $history[$rentId] ??= [];
            if (count($history[$rentId]) < 3) {
                $history[$rentId][] = $request;
            }
        }

        return $history;
    }

    private function renderRentalPayments(
        array $rents,
        array $payments,
        string $notice = '',
        string $error = '',
        int $prefillRentId = 0
    ): Response {
        return $this->render('modules/real-estate-rental/payments', array_merge(
            $this->rentalBaseViewModel('Paiements locatifs', $notice, $error),
            [
                'rentalCurrentSection' => 'personal',
                'rentalCurrentSubsection' => 'payments',
                'rentalRents' => $rents,
                'rentalPayments' => $payments,
                'rentalPaymentPrefillRentId' => $prefillRentId,
            ]
        ));
    }

    private function renderRentalExpenses(
        array $properties,
        array $units,
        array $expenses,
        string $notice = '',
        string $error = ''
    ): Response {
        return $this->render('modules/real-estate-rental/expenses', array_merge(
            $this->rentalBaseViewModel('Charges locatives', $notice, $error),
            [
                'rentalCurrentSection' => 'personal',
                'rentalCurrentSubsection' => 'expenses',
                'rentalProperties' => $this->objectsToArrays($properties),
                'rentalUnits' => $this->objectsToArrays($units),
                'rentalExpenses' => $expenses,
                'rentalExpenseCategories' => \Caramagnols\PrivateApps\RealEstateRental\Domain\RentalExpenseCategoryCatalog::options(),
            ]
        ));
    }

    private function renderRentalRegularizations(
        array $properties,
        array $units,
        array $regularizations,
        ?array $preview = null,
        string $notice = '',
        string $error = ''
    ): Response {
        return $this->render('modules/real-estate-rental/regularizations', array_merge(
            $this->rentalBaseViewModel('Régularisations de charges', $notice, $error),
            [
                'rentalCurrentSection' => 'personal',
                'rentalCurrentSubsection' => 'regularizations',
                'rentalProperties' => $this->objectsToArrays($properties),
                'rentalUnits' => $this->objectsToArrays($units),
                'rentalRegularizations' => $regularizations,
                'rentalRegularizationPreview' => $preview,
            ]
        ));
    }

    private function renderRentalDocuments(
        array $properties,
        array $units,
        array $leases,
        array $documents,
        string $notice = '',
        string $error = ''
    ): Response {
        return $this->render('modules/real-estate-rental/documents', array_merge(
            $this->rentalBaseViewModel('Documents locatifs', $notice, $error),
            [
                'rentalCurrentSection' => 'personal',
                'rentalCurrentSubsection' => 'documents',
                'rentalProperties' => $this->objectsToArrays($properties),
                'rentalUnits' => $this->objectsToArrays($units),
                'rentalLeases' => $leases,
                'rentalDocuments' => $documents,
                'rentalDocumentCategories' => $this->documentTaxonomyRepository()->listActive(),
            ]
        ));
    }

    private function renderRentalAgencyImports(
        int $userId,
        string $notice = '',
        string $error = '',
        string $tab = 'documents'
    ): Response {
        $propertyIds = $this->authorizedPropertyIds($userId);
        $properties = $propertyIds === []
            ? []
            : $this->rentalPropertyRepository()->listByIds($propertyIds, self::MAX_RENTAL_LIST);
        $units = $propertyIds === []
            ? []
            : $this->rentalUnitRepository()->listByPropertyIds($propertyIds, self::MAX_RENTAL_LIST);

        return $this->render('modules/real-estate-rental/agency-imports', array_merge(
            $this->rentalBaseViewModel('Importer des documents agence', $notice, $error),
            [
                'rentalCurrentSection' => 'agency',
                'rentalCurrentSubsection' => 'agencyImports',
                'agencyImportCurrentTab' => $this->agencyImportTab($tab),
                'agencyImportDocuments' => $this->agencyImportRepository()->listRecentDocumentsForUser(
                    $userId,
                    self::MAX_RENTAL_LIST
                ),
                'agencyImportBatches' => array_map(
                    static fn ($batch): array => method_exists($batch, 'toArray') ? $batch->toArray() : [],
                    $this->agencyImportRepository()->listRecentBatches($userId, 50)
                ),
                'agencyImportAgencies' => $this->agencyImportRepository()->listAgencies($userId, 100),
                'agencyImportUnitMappings' => $this->agencyImportRepository()->listUnitMappings($userId, 200),
                'agencyImportProperties' => $this->objectsToArrays($properties),
                'agencyImportUnits' => $this->objectsToArrays($units),
            ]
        ));
    }

    private function agencyImportTab(mixed $value): string
    {
        $tab = is_scalar($value) ? trim((string) $value) : '';
        return in_array($tab, ['documents', 'imports', 'agencies'], true) ? $tab : 'documents';
    }

    private function rentalAgencyImportsUrl(string $tab = 'documents', string $notice = '', string $error = ''): string
    {
        $query = ['tab' => $this->agencyImportTab($tab)];
        if ($notice !== '') {
            $query['notice'] = $notice;
        }
        if ($error !== '') {
            $query['error'] = $error;
        }

        return private_portal_url('rental_agency_imports') . '?' . http_build_query($query);
    }

    private function renderRentalAgencyReview(
        int $userId,
        int $documentId = 0,
        string $notice = '',
        string $error = '',
        int $lineFeedbackId = 0,
        string $lineNotice = '',
        string $lineError = ''
    ): Response {
        $documents = $this->agencyImportRepository()->listRecentDocumentsForUser(
            $userId,
            self::MAX_RENTAL_LIST
        );
        if ($documentId <= 0 && $documents !== []) {
            $firstDocumentId = $documents[0]['id'] ?? null;
            $documentId = is_numeric($firstDocumentId) ? (int) $firstDocumentId : 0;
        }

        $selectedDocument = $documentId > 0
            ? $this->agencyImportRepository()->reviewDocumentForUser($userId, $documentId)
            : null;
        if ($documentId > 0 && !is_array($selectedDocument) && $error === '') {
            $error = 'agency_review_forbidden';
        }
        $propertyIds = $this->authorizedPropertyIds($userId);
        $properties = $propertyIds === []
            ? []
            : $this->rentalPropertyRepository()->listByIds($propertyIds, self::MAX_RENTAL_LIST);
        $units = $propertyIds === []
            ? []
            : $this->rentalUnitRepository()->listByPropertyIds($propertyIds, self::MAX_RENTAL_LIST);

        return $this->render('modules/real-estate-rental/agency-review', array_merge(
            $this->rentalBaseViewModel('Documents agence à classer', $notice, $error),
            [
                'rentalCurrentSection' => 'agency',
                'rentalCurrentSubsection' => 'agencyReview',
                'agencyReviewDocuments' => $documents,
                'agencyReviewSelectedDocument' => $selectedDocument,
                'agencyReviewReconciliation' => is_array($selectedDocument)
                    ? $this->agencyAdvancedReconciliationService()->summarizeDocument($selectedDocument)
                    : [],
                'agencyReviewProperties' => $this->objectsToArrays($properties),
                'agencyReviewUnits' => $this->objectsToArrays($units),
                'agencyReviewCategories' => [], // TODO: Implement agencyReviewCategories()
                'agencyReviewSensitiveCategories' => [], // TODO: Implement agencyFiscalReviewPolicy()->fiscalReviewCategories()
                'agencyReviewLineFeedbackId' => $lineFeedbackId,
                'agencyReviewLineNotice' => $this->rentalNotice($lineNotice),
                'agencyReviewLineError' => $this->rentalError($lineError),
            ]
        ));
    }

    private function rentalAgencyReviewUrl(int $documentId, string $notice = '', string $error = ''): string
    {
        $query = [];
        if ($documentId > 0) {
            $query['document_id'] = $documentId;
        }
        if ($notice !== '') {
            $query['notice'] = $notice;
        }
        if ($error !== '') {
            $query['error'] = $error;
        }

        return private_portal_url('rental_agency_review') . '?' . http_build_query($query);
    }

    private function rentalAgencyReviewLineUrl(
        int $documentId,
        int $lineId,
        string $notice = '',
        string $error = ''
    ): string {
        $query = ['document_id' => $documentId];
        if ($lineId > 0) {
            $query['line_id'] = $lineId;
        }
        if ($notice !== '') {
            $query['line_notice'] = $notice;
        }
        if ($error !== '') {
            $query['line_error'] = $error;
        }

        return private_portal_url('rental_agency_review') . '?' . http_build_query($query);
    }

    private function renderRentalSummary(array $summary, array $properties = [], array $tenants = []): Response
    {
        return $this->render('modules/real-estate-rental/summary', array_merge(
            $this->rentalBaseViewModel('Synthèse annuelle locative', '', ''),
            [
                'rentalCurrentSection' => 'reports',
                'rentalCurrentSubsection' => 'summary',
                'rentalSummary' => $summary,
                'rentalProperties' => $this->objectsToArrays($properties),
                'rentalTenants' => $tenants,
            ]
        ));
    }

    // Handle Methods
    private function handleRentalDashboard(Request $request): Response
    { $userId = $this->requireRentalModuleUser($request); if ($userId instanceof Response) { return $userId; } return $this->renderRentalDashboard($userId); }

    private function handleRentalPropertiesDashboard(Request $request): Response
    { $userId = $this->requireRentalModuleUser($request); if ($userId instanceof Response) { return $userId; } return $this->renderRentalPropertiesDashboard($userId); }

    private function handleRentalAgencyDashboard(Request $request): Response
    { $userId = $this->requireRentalModuleUser($request); if ($userId instanceof Response) { return $userId; } return $this->renderRentalAgencyDashboard($userId); }

    private function handleRentalLessors(Request $request): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response) { return $userId; }
        $query = $request->query();
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : '';
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : '';
        if ($request->method() !== self::METHOD_POST) { return $this->renderRentalLessors($userId, $notice, $error); }
        if (!$this->guard()->validateCsrf($request, self::CSRF_RENTAL)) {
            return $this->redirect(private_portal_url('rental_lessors') . '?error=rental_invalid_request');
        }
        $body = $request->body();
        $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : 'create_lessor';
        $lessorId = $this->normalizeNumericId($body['lessor_id'] ?? null);
        if ($action === 'delete_lessor') {
            $deleted = $this->rentalLessorRepository()->deleteForUser($lessorId, $userId);
            $this->logEvent('private.rental_lessor.deleted', ['private_user_id' => $userId, 'rental_lessor_id' => $lessorId]);
            return $this->redirect(private_portal_url('rental_lessors') . ($deleted ? '?notice=lessor_deleted' : '?error=lessor_delete_failed'));
        }
        $payload = ['last_name' => is_string($body['last_name'] ?? null) ? (string) $body['last_name'] : '',
            'first_name' => is_string($body['first_name'] ?? null) ? (string) $body['first_name'] : '',
            'address' => is_string($body['address'] ?? null) ? (string) $body['address'] : '',
            'phone' => is_string($body['phone'] ?? null) ? (string) $body['phone'] : '',
            'email' => is_string($body['email'] ?? null) ? (string) $body['email'] : '',];
        $saved = $action === 'update_lessor' && $lessorId > 0
            ? $this->rentalLessorRepository()->update($lessorId, $userId, $payload['last_name'], $payload['first_name'], $payload['address'], $payload['phone'], $payload['email'])
            : $this->rentalLessorRepository()->create($userId, $payload['last_name'], $payload['first_name'], $payload['address'], $payload['phone'], $payload['email']);
        if (!is_array($saved)) { return $this->renderRentalLessors($userId, '', 'rental_write_failed'); }
        $this->logEvent($action === 'update_lessor' ? 'private.rental_lessor.updated' : 'private.rental_lessor.created', ['private_user_id' => $userId, 'rental_lessor_id' => (int) ($saved['id'] ?? 0)]);
        return $this->redirect(private_portal_url('rental_lessors') . ($action === 'update_lessor' ? '?notice=lessor_updated' : '?notice=lessor_created'));
    }

    // Handle Methods
    private function handleRentalProperties(Request $request): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $body = $request->body();
        $query = $request->query();
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : '';
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : '';

        if ($request->method() !== self::METHOD_POST) {
            return $this->renderRentalProperties($userId, $notice, $error);
        }

        $action = strtolower(trim((string) ($body['action'] ?? '')));
        if (!$this->guard()->validateCsrf($request, self::CSRF_RENTAL)) {
            return $this->renderRentalProperties($userId, '', 'rental_invalid_request');
        }

        $status = $body['status'] ?? '';
        $statusValue = is_string($status) ? strtolower(trim($status)) : '';
        $propertyId = $this->normalizeNumericId($body['property_id'] ?? null);
        $rentalLessorId = $this->normalizeNumericId($body['rental_lessor_id'] ?? null);
        $createDefaultUnit = isset($body['create_default_unit']) && (int) $body['create_default_unit'] === 1;
        $defaultUnitSurface = is_numeric($body['default_unit_surface'] ?? null)
            ? (float) $body['default_unit_surface']
            : 0.0;
        $defaultUnitFurnished = isset($body['default_unit_furnished']) && (int) $body['default_unit_furnished'] === 1;
        if ($rentalLessorId > 0 && $this->rentalLessorRepository()->findForUser($rentalLessorId, $userId) === null) {
            return $this->renderRentalProperties($userId, '', 'property_forbidden');
        }

        if ($action === 'create_property') {
            if ($createDefaultUnit && $defaultUnitSurface <= 0) {
                return $this->renderRentalProperties($userId, '', 'rental_write_failed');
            }

            $created = $this->rentalPropertyRepository()->create(
                $userId,
                (string) ($body['name'] ?? ''),
                (string) ($body['address'] ?? ''),
                (string) ($body['property_type'] ?? ''),
                (string) ($body['ownership_mode'] ?? ''),
                $statusValue !== '' ? $statusValue : 'draft',
                is_string($body['notes'] ?? null) ? (string) $body['notes'] : null,
                $rentalLessorId > 0 ? $rentalLessorId : null
            );
            if (!($created instanceof \Caramagnols\PrivateApps\RealEstateRental\Domain\RentalProperty)) {
                return $this->renderRentalProperties($userId, '', 'rental_write_failed');
            }

            $this->rentalPropertyMemberRepository()->create($created->id, $userId, 'owner', $userId);
            if ($createDefaultUnit) {
                $createdUnit = $this->rentalUnitRepository()->create(
                    $created->id,
                    'Maison entière',
                    $defaultUnitSurface,
                    $defaultUnitFurnished,
                    'available',
                    null,
                    $userId,
                    'house',
                    (string) ($body['address'] ?? '')
                );
                if (!($createdUnit instanceof \Caramagnols\PrivateApps\RealEstateRental\Domain\RentalUnit)) {
                    return $this->renderRentalProperties($userId, '', 'rental_write_failed');
                }
            }

            $this->logEvent('private.rental_property.created', [
                'private_user_id' => $userId,
                'rental_property_id' => $created->id,
            ]);

            return $this->redirect(private_portal_url('rental_properties') . '?notice=property_created');
        }

        if ($action === 'update_property' && $propertyId > 0) {
            if (!$this->canWriteProperty($propertyId, $userId)) {
                return $this->forbiddenOrUnauthorized(private_portal_url('rental_properties') . '?error=property_forbidden');
            }

            $updated = $this->rentalPropertyRepository()->update(
                $propertyId,
                $userId,
                (string) ($body['name'] ?? ''),
                (string) ($body['address'] ?? ''),
                (string) ($body['property_type'] ?? ''),
                (string) ($body['ownership_mode'] ?? ''),
                $statusValue !== '' ? $statusValue : 'draft',
                is_string($body['notes'] ?? null) ? (string) $body['notes'] : null,
                $rentalLessorId > 0 ? $rentalLessorId : null
            );
            if (!($updated instanceof \Caramagnols\PrivateApps\RealEstateRental\Domain\RentalProperty)) {
                return $this->renderRentalProperties($userId, '', 'rental_write_failed');
            }

            $this->logEvent('private.rental_property.updated', [
                'private_user_id' => $userId,
                'rental_property_id' => $propertyId,
            ]);

            return $this->redirect(private_portal_url('rental_properties') . '?notice=property_updated');
        }

        return $this->renderRentalProperties($userId, '', 'rental_invalid_request');
    }
    private function handleRentalPropertyArchive(Request $request, int $propertyId): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response || $request->method() !== self::METHOD_POST) {
            return $userId instanceof Response ? $userId : $this->handleModuleAccessDenied('real_estate_rental');
        }

        if (
            !$this->guard()->validateCsrf($request, self::CSRF_RENTAL)
            || $propertyId <= 0
            || !$this->canDeleteProperty($propertyId, $userId)
        ) {
            return $this->forbiddenOrUnauthorized(private_portal_url('rental_properties') . '?error=property_forbidden');
        }

        if ($this->rentalPropertyRepository()->archive($propertyId, $userId)) {
            $this->rentalUnitRepository()->archiveByPropertyId($propertyId, $userId);
            $this->logEvent('private.rental_property.archived', [
                'private_user_id' => $userId,
                'rental_property_id' => $propertyId,
            ]);
            return $this->redirect(private_portal_url('rental_properties') . '?notice=property_archived');
        }

        return $this->renderRentalProperties($userId, '', 'rental_archive_failed');
    }
    private function handleRentalUnits(Request $request): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $authorizedProperties = $this->authorizedPropertyIds($userId);
        $units = $authorizedProperties === []
            ? []
            : $this->rentalUnitRepository()->listByPropertyIds($authorizedProperties, self::MAX_RENTAL_LIST);
        $properties = $this->rentalPropertyRepository()->listByIds($authorizedProperties, self::MAX_RENTAL_LIST);

        $body = $request->body();
        $query = $request->query();
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : '';
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : '';

        if ($request->method() !== self::METHOD_POST) {
            return $this->renderRentalUnits($userId, $properties, $units, $notice, $error);
        }

        if (!$this->guard()->validateCsrf($request, self::CSRF_RENTAL)) {
            return $this->renderRentalUnits($userId, $properties, $units, '', 'rental_invalid_request');
        }

        $action = strtolower(trim((string) ($body['action'] ?? '')));
        $propertyId = $this->normalizeNumericId($body['rental_property_id'] ?? null);
        $unitId = $this->normalizeNumericId($body['unit_id'] ?? null);
        $surfaceRaw = is_numeric($body['surface'] ?? null) ? (float) $body['surface'] : 0.0;
        $furnished = isset($body['furnished']) && (int) $body['furnished'] === 1;
        $unitType = is_string($body['unit_type'] ?? null) ? (string) $body['unit_type'] : 'other';
        $unitAddress = is_string($body['address'] ?? null) ? (string) $body['address'] : null;
        $unitBuilding = is_string($body['building'] ?? null) ? (string) $body['building'] : null;
        $unitFloor = is_string($body['floor'] ?? null) ? (string) $body['floor'] : null;
        $unitDoor = is_string($body['door'] ?? null) ? (string) $body['door'] : null;
        $unitDetails = $this->rentalUnitDetailsFromBody($body);
        $unavailableUntil = is_string($body['unavailable_until'] ?? null) ? (string) $body['unavailable_until'] : null;

        if (!$this->canWriteByPropertyId($propertyId, $userId)) {
            return $this->renderRentalUnits($userId, $properties, $units, '', 'unit_forbidden');
        }

        if ($action === 'create_unit') {
            $created = $this->rentalUnitRepository()->create(
                $propertyId,
                (string) ($body['label'] ?? ''),
                $surfaceRaw,
                $furnished,
                strtolower(trim((string) ($body['status'] ?? 'available'))),
                is_string($body['notes'] ?? null) ? (string) $body['notes'] : null,
                $userId,
                $unitType,
                $unitAddress,
                $unitBuilding,
                $unitFloor,
                $unitDoor,
                $unitDetails,
                $unavailableUntil
            );
            if (!($created instanceof \Caramagnols\PrivateApps\RealEstateRental\Domain\RentalUnit)) {
                return $this->renderRentalUnits($userId, $properties, $units, '', 'rental_write_failed');
            }

            $this->logEvent('private.rental_unit.created', [
                'private_user_id' => $userId,
                'rental_unit_id' => $created->id,
                'rental_property_id' => $created->rentalPropertyId,
            ]);

            return $this->redirect(private_portal_url('rental_units') . '?notice=unit_created');
        }

        if ($action === 'update_unit' && $unitId > 0) {
            $updated = $this->rentalUnitRepository()->update(
                $unitId,
                $userId,
                $propertyId,
                (string) ($body['label'] ?? ''),
                $surfaceRaw,
                $furnished,
                strtolower(trim((string) ($body['status'] ?? 'available'))),
                is_string($body['notes'] ?? null) ? (string) $body['notes'] : null,
                $unitType,
                $unitAddress,
                $unitBuilding,
                $unitFloor,
                $unitDoor,
                $unitDetails,
                $unavailableUntil
            );
            if (!($updated instanceof \Caramagnols\PrivateApps\RealEstateRental\Domain\RentalUnit)) {
                return $this->renderRentalUnits($userId, $properties, $units, '', 'rental_write_failed');
            }

            $this->logEvent('private.rental_unit.updated', [
                'private_user_id' => $userId,
                'rental_unit_id' => $unitId,
                'rental_property_id' => $propertyId,
            ]);

            return $this->redirect(private_portal_url('rental_units') . '?notice=unit_updated');
        }

        return $this->renderRentalUnits($userId, $properties, $units, '', 'rental_invalid_request');
    }
    private function handleRentalUnitArchive(Request $request, int $unitId): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response || $request->method() !== self::METHOD_POST) {
            return $userId instanceof Response ? $userId : $this->handleModuleAccessDenied('real_estate_rental');
        }

        if (!$this->guard()->validateCsrf($request, self::CSRF_RENTAL) || $unitId <= 0) {
            return $this->forbiddenOrUnauthorized(private_portal_url('rental_units') . '?error=unit_forbidden');
        }

        $unit = $this->rentalUnitRepository()->findById($unitId);
        if ($unit === null || !$this->canWriteByPropertyId($unit->rentalPropertyId, $userId)) {
            return $this->forbiddenOrUnauthorized(private_portal_url('rental_units') . '?error=unit_forbidden');
        }

        if ($this->rentalUnitRepository()->archive($unitId, $userId)) {
            $this->logEvent('private.rental_unit.archived', [
                'private_user_id' => $userId,
                'rental_unit_id' => $unitId,
                'rental_property_id' => $unit->rentalPropertyId,
            ]);

            return $this->redirect(private_portal_url('rental_units') . '?notice=unit_archived');
        }

        return $this->redirect(private_portal_url('rental_units') . '?error=unit_archive_failed');
    }
    private function handleRentalPropertyMembers(Request $request): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $authorizedProperties = $this->authorizedPropertyIds($userId);
        $members = $authorizedProperties === []
            ? []
            : $this->rentalPropertyMemberRepository()->listByPropertyIds($authorizedProperties, self::MAX_RENTAL_LIST);

        $body = $request->body();
        $query = $request->query();
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : '';
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : '';

        if ($request->method() !== self::METHOD_POST) {
            return $this->renderRentalMembers($userId, $members, $notice, $error);
        }

        if (!$this->guard()->validateCsrf($request, self::CSRF_RENTAL)) {
            return $this->renderRentalMembers($userId, $members, '', 'rental_invalid_request');
        }

        $action = strtolower(trim((string) ($body['action'] ?? '')));
        if (!in_array($action, ['create_member', 'update_member', 'delete_member'], true)) {
            return $this->renderRentalMembers($userId, $members, '', 'rental_invalid_request');
        }

        if ($action === 'update_member' || $action === 'delete_member') {
            $memberId = $this->normalizeNumericId($body['member_id'] ?? null);
            $member = $this->rentalPropertyMemberRepository()->findById($memberId);
            if ($member === null || !$member->isActive || $member->status !== 'active') {
                return $this->renderRentalMembers($userId, $members, '', 'member_forbidden');
            }

            if (!$this->canWriteByPropertyId($member->rentalPropertyId, $userId)) {
                return $this->renderRentalMembers($userId, $members, '', 'member_forbidden');
            }

            if ($member->privateUserId === $userId) {
                return $this->renderRentalMembers($userId, $members, '', 'member_self_forbidden');
            }

            if ($action === 'delete_member') {
                if (!$this->rentalPropertyMemberRepository()->deactivate($member->rentalPropertyId, $member->privateUserId, $userId)) {
                    return $this->renderRentalMembers($userId, $members, '', 'member_delete_failed');
                }

                $this->logEvent('private.rental_property_member.deleted', [
                    'private_user_id' => $userId,
                    'rental_property_id' => $member->rentalPropertyId,
                    'member_user_id' => $member->privateUserId,
                ]);

                return $this->redirect(private_portal_url('rental_property_members') . '?notice=member_deleted');
            }

            $role = is_string($body['role'] ?? null) ? strtolower(trim((string) $body['role'])) : '';
            $updated = $this->rentalPropertyMemberRepository()->update(
                $memberId,
                $role,
                is_string($body['notes'] ?? null) ? (string) $body['notes'] : null
            );
            if (!$updated) {
                return $this->renderRentalMembers($userId, $members, '', 'member_update_failed');
            }

            $this->logEvent('private.rental_property_member.updated', [
                'private_user_id' => $userId,
                'rental_property_id' => $member->rentalPropertyId,
                'member_user_id' => $member->privateUserId,
            ]);

            return $this->redirect(private_portal_url('rental_property_members') . '?notice=member_updated');
        }

        $propertyId = $this->normalizeNumericId($body['rental_property_id'] ?? null);
        if (!$this->canWriteByPropertyId($propertyId, $userId)) {
            return $this->renderRentalMembers($userId, $members, '', 'member_forbidden');
        }

        $email = is_string($body['private_user_email'] ?? null) ? trim((string) $body['private_user_email']) : '';
        if ($email === '') {
            return $this->renderRentalMembers($userId, $members, '', 'member_missing_email');
        }

        $memberUser = $this->privateUserRepository->findByEmail($email);
        $privateUserId = is_array($memberUser) ? (int) ($memberUser['id'] ?? 0) : 0;
        if ($privateUserId <= 0) {
            return $this->renderRentalMembers($userId, $members, '', 'member_unknown_user');
        }

        $role = is_string($body['role'] ?? null) ? strtolower(trim((string) $body['role'])) : '';
        $created = $this->rentalPropertyMemberRepository()->create(
            $propertyId,
            $privateUserId,
            $role,
            $userId,
            is_string($body['notes'] ?? null) ? (string) $body['notes'] : null
        );
        if (!($created instanceof \Caramagnols\PrivateApps\RealEstateRental\Domain\RentalPropertyMember)) {
            return $this->renderRentalMembers($userId, $members, '', 'member_create_failed');
        }

        $this->logEvent('private.rental_property_member.created', [
            'private_user_id' => $userId,
            'rental_property_id' => $propertyId,
            'member_user_id' => $privateUserId,
        ]);

        return $this->redirect(private_portal_url('rental_property_members') . '?notice=member_created');
    }
    private function handleRentalTenants(Request $request): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $propertyIds = $this->authorizedPropertyIds($userId);
        $properties = $this->rentalPropertyRepository()->listByIds($propertyIds, self::MAX_RENTAL_LIST);
        $units = $this->rentalUnitRepository()->listByPropertyIds($propertyIds, self::MAX_RENTAL_LIST);
        $tenants = $this->rentalLifecycleRepository()->listTenants($propertyIds, self::MAX_RENTAL_LIST);
        $query = $request->query();
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : '';
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : '';

        if ($request->method() !== self::METHOD_POST) {
            return $this->renderRentalTenants($properties, $units, $tenants, $notice, $error);
        }

        if (!$this->guard()->validateCsrf($request, self::CSRF_RENTAL)) {
            return $this->renderRentalTenants($properties, $units, $tenants, '', 'rental_invalid_request');
        }

        $body = $request->body();
        $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : 'create_tenant';
        if ($action === 'update_tenant') {
            $tenantId = $this->normalizeNumericId($body['tenant_id'] ?? null);
            $tenant = $this->rentalLifecycleRepository()->findTenantById($tenantId);
            $tenantPropertyId = is_array($tenant) && is_numeric($tenant['rentalPropertyId'] ?? null)
                ? (int) $tenant['rentalPropertyId']
                : 0;
            if ($tenantId <= 0 || !in_array($tenantPropertyId, $propertyIds, true) || !$this->canWriteByPropertyId($tenantPropertyId, $userId)) {
                return $this->renderRentalTenants($properties, $units, $tenants, '', 'property_forbidden');
            }

            $unitId = $this->normalizeNumericId($body['rental_unit_id'] ?? null);
            $unit = $this->rentalUnitRepository()->findById($unitId);
            $propertyId = $unit !== null ? $unit->rentalPropertyId : 0;
            if ($unit === null || !in_array($propertyId, $propertyIds, true) || !$this->canWriteByPropertyId($propertyId, $userId)) {
                return $this->renderRentalTenants($properties, $units, $tenants, '', 'unit_forbidden');
            }

            $updated = $this->rentalLifecycleRepository()->updateTenant(
                $tenantId,
                $propertyId,
                $unitId,
                $this->rentalTenantFullNameFromBody($body),
                is_string($body['email'] ?? null) ? (string) $body['email'] : null,
                is_string($body['phone'] ?? null) ? (string) $body['phone'] : null,
                is_string($body['status'] ?? null) ? (string) $body['status'] : 'draft',
                is_string($body['notes'] ?? null) ? (string) $body['notes'] : null,
                $this->rentalTenantDetailsFromBody($body)
            );
            if (!is_array($updated)) {
                return $this->renderRentalTenants($properties, $units, $tenants, '', 'tenant_update_failed');
            }

            $this->logEvent('private.rental_tenant.updated', [
                'private_user_id' => $userId,
                'rental_property_id' => $propertyId,
                'rental_unit_id' => $unitId,
                'rental_tenant_id' => $tenantId,
            ]);

            return $this->redirect(private_portal_url('rental_tenants') . '?notice=tenant_updated#rental-feedback');
        }

        if ($action === 'delete_tenant') {
            $tenantId = $this->normalizeNumericId($body['tenant_id'] ?? null);
            if ($tenantId <= 0 || !$this->rentalLifecycleRepository()->deleteTenant($tenantId, $propertyIds)) {
                return $this->renderRentalTenants($properties, $units, $tenants, '', 'rental_delete_failed');
            }

            $this->logEvent('private.rental_tenant.deleted', [
                'private_user_id' => $userId,
                'rental_tenant_id' => $tenantId,
            ]);

            return $this->redirect(private_portal_url('rental_tenants') . '?notice=tenant_deleted#rental-feedback');
        }

        if ($action !== 'create_tenant') {
            return $this->renderRentalTenants($properties, $units, $tenants, '', 'rental_invalid_request');
        }

        $unitId = $this->normalizeNumericId($body['rental_unit_id'] ?? null);
        $unit = $this->rentalUnitRepository()->findById($unitId);
        $propertyId = $unit !== null ? $unit->rentalPropertyId : 0;
        if ($unit === null || !in_array($propertyId, $propertyIds, true)) {
            return $this->renderRentalTenants($properties, $units, $tenants, '', 'unit_forbidden');
        }
        if (!$this->canWriteByPropertyId($propertyId, $userId)) {
            return $this->renderRentalTenants($properties, $units, $tenants, '', 'property_forbidden');
        }

        $created = $this->rentalLifecycleRepository()->createTenant(
            $propertyId,
            $unitId,
            $this->rentalTenantFullNameFromBody($body),
            is_string($body['email'] ?? null) ? (string) $body['email'] : null,
            is_string($body['phone'] ?? null) ? (string) $body['phone'] : null,
            is_string($body['status'] ?? null) ? (string) $body['status'] : 'draft',
            $userId,
            is_string($body['notes'] ?? null) ? (string) $body['notes'] : null,
            $this->rentalTenantDetailsFromBody($body)
        );
        if (!is_array($created)) {
            return $this->renderRentalTenants($properties, $units, $tenants, '', 'rental_write_failed');
        }

        $this->logEvent('private.rental_tenant.created', [
            'private_user_id' => $userId,
            'rental_property_id' => $propertyId,
            'rental_unit_id' => $unitId,
            'rental_tenant_id' => (int) ($created['id'] ?? 0),
        ]);

        return $this->redirect(private_portal_url('rental_tenants') . '?notice=tenant_created');
    }
    private function handleRentalLeases(Request $request): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $propertyIds = $this->authorizedPropertyIds($userId);
        $properties = $this->rentalPropertyRepository()->listByIds($propertyIds, self::MAX_RENTAL_LIST);
        $units = $this->rentalUnitRepository()->listByPropertyIds($propertyIds, self::MAX_RENTAL_LIST);
        $tenants = $this->rentalLifecycleRepository()->listTenants($propertyIds, self::MAX_RENTAL_LIST);
        $leases = $this->rentalLifecycleRepository()->listLeases($propertyIds, self::MAX_RENTAL_LIST);
        $query = $request->query();
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : '';
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : '';

        if ($request->method() !== self::METHOD_POST) {
            return $this->renderRentalLeases($properties, $units, $tenants, $leases, $notice, $error);
        }

        if (!$this->guard()->validateCsrf($request, self::CSRF_RENTAL)) {
            return $this->renderRentalLeases($properties, $units, $tenants, $leases, '', 'rental_invalid_request');
        }

        $body = $request->body();
        $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : 'create_lease';

        if ($action === 'delete_lease') {
            $leaseId = $this->normalizeNumericId($body['lease_id'] ?? null);
            if ($leaseId <= 0 || !$this->rentalLifecycleRepository()->deleteLease($leaseId, $propertyIds)) {
                return $this->renderRentalLeases($properties, $units, $tenants, $leases, '', 'rental_delete_failed');
            }

            $this->logEvent('private.rental_lease.deleted', [
                'private_user_id' => $userId,
                'rental_lease_id' => $leaseId,
            ]);

            return $this->redirect(private_portal_url('rental_leases') . '?notice=lease_deleted');
        }

        if ($action === 'adjust_lease') {
            $leaseId = $this->normalizeNumericId($body['lease_id'] ?? null);
            $lease = $this->rentalLifecycleRepository()->findLeaseById($leaseId);
            $leasePropertyId = is_array($lease) && is_numeric($lease['rentalPropertyId'] ?? null)
                ? (int) $lease['rentalPropertyId']
                : 0;
            if ($leaseId <= 0 || !in_array($leasePropertyId, $propertyIds, true) || !$this->canWriteByPropertyId($leasePropertyId, $userId)) {
                return $this->renderRentalLeases($properties, $units, $tenants, $leases, '', 'property_forbidden');
            }

            $monthlyRent = is_numeric($body['adjusted_monthly_rent'] ?? null)
                ? (float) $body['adjusted_monthly_rent']
                : (float) ($lease['monthlyRent'] ?? 0);
            $chargesProvision = is_numeric($body['adjusted_charges_provision'] ?? null)
                ? (float) $body['adjusted_charges_provision']
                : (float) ($lease['chargesProvision'] ?? 0);
            $notes = $this->appendRentalLeaseAdjustmentNote(
                is_string($lease['notes'] ?? null) ? (string) $lease['notes'] : null,
                is_string($body['adjustment_month'] ?? null) ? (string) $body['adjustment_month'] : '',
                $monthlyRent,
                $chargesProvision,
                is_string($body['adjustment_note'] ?? null) ? (string) $body['adjustment_note'] : ''
            );

            $updated = $this->rentalLifecycleRepository()->updateLease(
                $leaseId,
                $propertyIds,
                $leasePropertyId,
                is_numeric($lease['rentalUnitId'] ?? null) ? (int) $lease['rentalUnitId'] : 0,
                is_numeric($lease['rentalTenantId'] ?? null) ? (int) $lease['rentalTenantId'] : 0,
                is_string($lease['startDate'] ?? null) ? (string) $lease['startDate'] : '',
                is_string($lease['endDate'] ?? null) ? (string) $lease['endDate'] : null,
                $monthlyRent,
                $chargesProvision,
                is_string($lease['status'] ?? null) ? (string) $lease['status'] : 'draft',
                $notes,
                is_string($lease['leaseType'] ?? null) ? (string) $lease['leaseType'] : RentalLeaseTypeCatalog::DEFAULT
            );
            if (!is_array($updated)) {
                return $this->renderRentalLeases($properties, $units, $tenants, $leases, '', 'rental_write_failed');
            }

            $this->logEvent('private.rental_lease.adjusted', [
                'private_user_id' => $userId,
                'rental_property_id' => $leasePropertyId,
                'rental_lease_id' => $leaseId,
            ]);

            return $this->redirect(private_portal_url('rental_leases') . '?notice=lease_adjusted');
        }

        if ($action === 'update_lease') {
            $leaseId = $this->normalizeNumericId($body['lease_id'] ?? null);
            $lease = $this->rentalLifecycleRepository()->findLeaseById($leaseId);
            $leasePropertyId = is_array($lease) && is_numeric($lease['rentalPropertyId'] ?? null)
                ? (int) $lease['rentalPropertyId']
                : 0;
            if ($leaseId <= 0 || !in_array($leasePropertyId, $propertyIds, true) || !$this->canWriteByPropertyId($leasePropertyId, $userId)) {
                return $this->renderRentalLeases($properties, $units, $tenants, $leases, '', 'property_forbidden');
            }

            $propertyId = $this->normalizeNumericId($body['rental_property_id'] ?? null);
            $unitId = $this->normalizeNumericId($body['rental_unit_id'] ?? null);
            $tenantId = $this->normalizeNumericId($body['rental_tenant_id'] ?? null);
            if (!$this->canWriteByPropertyId($propertyId, $userId)) {
                return $this->renderRentalLeases($properties, $units, $tenants, $leases, '', 'property_forbidden');
            }
            if (!$this->unitBelongsToProperty($unitId, $propertyId) || !$this->tenantBelongsToUnit($tenantId, $unitId, $propertyId)) {
                return $this->renderRentalLeases($properties, $units, $tenants, $leases, '', 'tenant_required_for_unit');
            }

            $updated = $this->rentalLifecycleRepository()->updateLease(
                $leaseId,
                $propertyIds,
                $propertyId,
                $unitId,
                $tenantId,
                (string) ($body['start_date'] ?? ''),
                is_string($body['end_date'] ?? null) ? (string) $body['end_date'] : null,
                is_numeric($body['monthly_rent'] ?? null) ? (float) $body['monthly_rent'] : 0.0,
                is_numeric($body['charges_provision'] ?? null) ? (float) $body['charges_provision'] : 0.0,
                is_string($body['status'] ?? null) ? (string) $body['status'] : 'draft',
                is_string($body['notes'] ?? null) ? (string) $body['notes'] : null,
                is_string($body['lease_type'] ?? null) ? (string) $body['lease_type'] : RentalLeaseTypeCatalog::DEFAULT
            );
            if (!is_array($updated)) {
                return $this->renderRentalLeases($properties, $units, $tenants, $leases, '', 'rental_write_failed');
            }

            $this->logEvent('private.rental_lease.updated', [
                'private_user_id' => $userId,
                'rental_property_id' => $propertyId,
                'rental_lease_id' => $leaseId,
            ]);

            return $this->redirect(private_portal_url('rental_leases') . '?notice=lease_updated');
        }

        if ($action !== 'create_lease') {
            return $this->renderRentalLeases($properties, $units, $tenants, $leases, '', 'rental_invalid_request');
        }

        $propertyId = $this->normalizeNumericId($body['rental_property_id'] ?? null);
        $unitId = $this->normalizeNumericId($body['rental_unit_id'] ?? null);
        $tenantId = $this->normalizeNumericId($body['rental_tenant_id'] ?? null);
        if (!$this->canWriteByPropertyId($propertyId, $userId)) {
            return $this->renderRentalLeases($properties, $units, $tenants, $leases, '', 'property_forbidden');
        }
        if (!$this->unitBelongsToProperty($unitId, $propertyId) || !$this->tenantBelongsToUnit($tenantId, $unitId, $propertyId)) {
            return $this->renderRentalLeases($properties, $units, $tenants, $leases, '', 'tenant_required_for_unit');
        }
        $unit = $this->rentalUnitRepository()->findById($unitId);
        if ($unit === null || $unit->status !== 'available' || $this->rentalLifecycleRepository()->hasActiveLeaseForUnit($unitId)) {
            return $this->renderRentalLeases($properties, $units, $tenants, $leases, '', 'lease_unit_unavailable');
        }

        $created = $this->rentalLifecycleRepository()->createLease(
            $propertyId,
            $unitId,
            $tenantId,
            (string) ($body['start_date'] ?? ''),
            is_string($body['end_date'] ?? null) ? (string) $body['end_date'] : null,
            is_numeric($body['monthly_rent'] ?? null) ? (float) $body['monthly_rent'] : 0.0,
            is_numeric($body['charges_provision'] ?? null) ? (float) $body['charges_provision'] : 0.0,
            is_string($body['status'] ?? null) ? (string) $body['status'] : 'draft',
            $userId,
            is_string($body['notes'] ?? null) ? (string) $body['notes'] : null,
            is_string($body['lease_type'] ?? null) ? (string) $body['lease_type'] : RentalLeaseTypeCatalog::DEFAULT
        );
        if (!is_array($created)) {
            return $this->renderRentalLeases($properties, $units, $tenants, $leases, '', 'rental_write_failed');
        }

        $this->logEvent('private.rental_lease.created', [
            'private_user_id' => $userId,
            'rental_property_id' => $propertyId,
            'rental_lease_id' => (int) ($created['id'] ?? 0),
        ]);

        return $this->redirect(private_portal_url('rental_leases') . '?notice=lease_created');
    }
    private function handleRentalRents(Request $request): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $propertyIds = $this->authorizedPropertyIds($userId);
        $this->rentPaymentStatusService()->refreshPropertyRents($propertyIds);
        $leases = $this->rentalLifecycleRepository()->listLeases($propertyIds, self::MAX_RENTAL_LIST);
        $rents = $this->rentalLifecycleRepository()->listRents($propertyIds, null, self::MAX_RENTAL_LIST);
        $query = $request->query();
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : '';
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : '';

        if ($request->method() !== self::METHOD_POST) {
            return $this->renderRentalRents($leases, $rents, $notice, $error);
        }

        if (!$this->guard()->validateCsrf($request, self::CSRF_RENTAL)) {
            return $this->renderRentalRents($leases, $rents, '', 'rental_invalid_request');
        }

        $body = $request->body();
        $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : 'create_rent';

        // Action simplifiée - uniquement create_rent et delete_rent pour l'instant
        if ($action === 'delete_rent') {
            $rentId = $this->normalizeNumericId($body['rent_id'] ?? null);
            if ($rentId <= 0 || !$this->rentalLifecycleRepository()->deleteRent($rentId, $propertyIds)) {
                return $this->renderRentalRents($leases, $rents, '', 'rental_delete_failed');
            }

            $this->logEvent('private.rental_rent.deleted', [
                'private_user_id' => $userId,
                'rental_rent_id' => $rentId,
            ]);

            return $this->redirect(private_portal_url('rental_rents') . '?notice=rent_deleted');
        }

        if ($action === 'generate_rent_schedule') {
            $leaseId = $this->normalizeNumericId($body['rental_lease_id'] ?? null);
            $lease = $this->rentalLifecycleRepository()->findLeaseById($leaseId);
            $propertyId = is_array($lease) && is_numeric($lease['rentalPropertyId'] ?? null)
                ? (int) $lease['rentalPropertyId']
                : 0;
            if ($leaseId <= 0 || !in_array($propertyId, $propertyIds, true) || !$this->canWriteByPropertyId($propertyId, $userId)) {
                return $this->renderRentalRents($leases, $rents, '', 'property_forbidden');
            }

            [$periodYear, $periodMonth] = $this->rentalPeriodFromBody($body);
            if ($periodYear <= 0 || $periodMonth <= 0) {
                return $this->renderRentalRents($leases, $rents, '', 'rental_write_failed');
            }

            $result = $this->rentScheduleService()->generateForLeasePeriod($leaseId, $periodYear, $periodMonth, $userId);
            $this->logEvent('private.rental_rent_schedule.generated', [
                'private_user_id' => $userId,
                'rental_property_id' => $propertyId,
                'rental_lease_id' => $leaseId,
                'period_year' => $periodYear,
                'period_month' => $periodMonth,
                'created' => (int) ($result['created'] ?? 0),
                'existing' => (int) ($result['existing'] ?? 0),
                'skipped' => (int) ($result['skipped'] ?? 0),
            ]);
            $this->rentPaymentStatusService()->refreshPropertyRents([$propertyId]);

            return $this->redirect(private_portal_url('rental_rents') . '?notice=' . $this->rentScheduleNotice($result));
        }

        if ($action === 'generate_month_schedule') {
            $writablePropertyIds = $this->writableRentalPropertyIds($propertyIds, $userId);
            if ($writablePropertyIds === []) {
                return $this->renderRentalRents($leases, $rents, '', 'property_forbidden');
            }

            [$periodYear, $periodMonth] = $this->rentalPeriodFromBody($body);
            if ($periodYear <= 0 || $periodMonth <= 0) {
                return $this->renderRentalRents($leases, $rents, '', 'rental_write_failed');
            }

            $result = $this->rentScheduleService()->generateForMonth($writablePropertyIds, $periodYear, $periodMonth, $userId);
            $this->logEvent('private.rental_rent_schedule.generated', [
                'private_user_id' => $userId,
                'period_year' => $periodYear,
                'period_month' => $periodMonth,
                'property_count' => count($writablePropertyIds),
                'created' => (int) ($result['created'] ?? 0),
                'existing' => (int) ($result['existing'] ?? 0),
                'skipped' => (int) ($result['skipped'] ?? 0),
            ]);
            $this->rentPaymentStatusService()->refreshPropertyRents($writablePropertyIds);

            return $this->redirect(private_portal_url('rental_rents') . '?notice=' . $this->rentScheduleNotice($result));
        }

        if ($action !== 'create_rent') {
            return $this->renderRentalRents($leases, $rents, '', 'rental_invalid_request');
        }

        $leaseId = $this->normalizeNumericId($body['rental_lease_id'] ?? null);
        $lease = $this->rentalLifecycleRepository()->findLeaseById($leaseId);
        $propertyId = is_array($lease) && is_numeric($lease['rentalPropertyId'] ?? null)
            ? (int) $lease['rentalPropertyId']
            : 0;
        $unitId = is_array($lease) && is_numeric($lease['rentalUnitId'] ?? null)
            ? (int) $lease['rentalUnitId']
            : 0;
        if (!$this->canWriteByPropertyId($propertyId, $userId) || $unitId <= 0) {
            return $this->renderRentalRents($leases, $rents, '', 'property_forbidden');
        }

        [$periodYear, $periodMonth] = $this->rentalPeriodFromBody($body);
        $amountDetails = $this->rentalRentAmountDetailsFromBody($body, $lease);
        if ($periodYear <= 0 || $periodMonth <= 0 || $amountDetails === null) {
            return $this->renderRentalRents($leases, $rents, '', 'rental_write_failed');
        }

        $created = $this->rentalLifecycleRepository()->createRent(
            $leaseId,
            $propertyId,
            $unitId,
            $periodYear,
            $periodMonth,
            (string) ($body['due_date'] ?? ''),
            (float) $amountDetails['amount'],
            is_string($body['status'] ?? null) ? (string) $body['status'] : 'draft',
            $userId,
            $amountDetails['notes']
        );
        if (!is_array($created)) {
            return $this->renderRentalRents($leases, $rents, '', 'rental_write_failed');
        }
        $this->rentPaymentStatusService()->refreshRentStatus((int) ($created['id'] ?? 0));

        $this->logEvent('private.rental_rent.created', [
            'private_user_id' => $userId,
            'rental_property_id' => $propertyId,
            'rental_rent_id' => (int) ($created['id'] ?? 0),
        ]);

        return $this->redirect(private_portal_url('rental_rents') . '?notice=rent_created');
    }
    private function handleRentalPayments(Request $request): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $propertyIds = $this->authorizedPropertyIds($userId);
        $this->rentPaymentStatusService()->refreshPropertyRents($propertyIds);
        $rents = $this->rentalLifecycleRepository()->listRents($propertyIds, null, self::MAX_RENTAL_LIST);
        $payments = $this->rentalLifecycleRepository()->listPayments($propertyIds, null, self::MAX_RENTAL_LIST);
        $query = $request->query();
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : '';
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : '';
        $prefillRentId = $this->normalizeNumericId($query['rent_id'] ?? null);

        if ($request->method() !== self::METHOD_POST) {
            return $this->renderRentalPayments($rents, $payments, $notice, $error, $prefillRentId);
        }

        if (!$this->guard()->validateCsrf($request, self::CSRF_RENTAL)) {
            return $this->renderRentalPayments($rents, $payments, '', 'rental_invalid_request', $prefillRentId);
        }

        $body = $request->body();
        $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : 'create_payment';

        if ($action === 'delete_payment') {
            $paymentId = $this->normalizeNumericId($body['payment_id'] ?? null);
            $payment = $this->rentalLifecycleRepository()->findPaymentById($paymentId);
            $rentId = is_array($payment) && is_numeric($payment['rentalRentId'] ?? null)
                ? (int) $payment['rentalRentId']
                : 0;
            if ($paymentId <= 0 || !$this->rentalLifecycleRepository()->deletePayment($paymentId, $propertyIds)) {
                return $this->renderRentalPayments($rents, $payments, '', 'rental_delete_failed', $prefillRentId);
            }
            if ($rentId > 0) {
                $this->rentPaymentStatusService()->refreshRentStatus($rentId);
            }

            $this->logEvent('private.rental_payment.deleted', [
                'private_user_id' => $userId,
                'rental_payment_id' => $paymentId,
            ]);

            return $this->redirect(private_portal_url('rental_payments') . '?notice=payment_deleted');
        }

        if ($action === 'cancel_payment') {
            $paymentId = $this->normalizeNumericId($body['payment_id'] ?? null);
            $payment = $this->rentalLifecycleRepository()->findPaymentById($paymentId);
            $propertyId = is_array($payment) && is_numeric($payment['rentalPropertyId'] ?? null)
                ? (int) $payment['rentalPropertyId']
                : 0;
            $rentId = is_array($payment) && is_numeric($payment['rentalRentId'] ?? null)
                ? (int) $payment['rentalRentId']
                : 0;
            if ($paymentId <= 0 || !in_array($propertyId, $propertyIds, true) || !$this->canWriteByPropertyId($propertyId, $userId)) {
                return $this->renderRentalPayments($rents, $payments, '', 'property_forbidden', $prefillRentId);
            }

            $cancelled = $this->rentalLifecycleRepository()->cancelPayment($paymentId, $propertyIds);
            if (!is_array($cancelled)) {
                return $this->renderRentalPayments($rents, $payments, '', 'rental_write_failed', $prefillRentId);
            }
            if ($rentId > 0) {
                $this->rentPaymentStatusService()->refreshRentStatus($rentId);
            }

            $this->logEvent('private.rental_payment.cancelled', [
                'private_user_id' => $userId,
                'rental_property_id' => $propertyId,
                'rental_payment_id' => $paymentId,
            ]);

            return $this->redirect(private_portal_url('rental_payments') . '?notice=payment_cancelled');
        }

        if ($action === 'update_payment') {
            $paymentId = $this->normalizeNumericId($body['payment_id'] ?? null);
            $payment = $this->rentalLifecycleRepository()->findPaymentById($paymentId);
            $propertyId = is_array($payment) && is_numeric($payment['rentalPropertyId'] ?? null)
                ? (int) $payment['rentalPropertyId']
                : 0;
            $rentId = is_array($payment) && is_numeric($payment['rentalRentId'] ?? null)
                ? (int) $payment['rentalRentId']
                : 0;
            $paymentData = $this->rentalPaymentDataFromBody($body);
            if (
                $paymentId <= 0
                || $rentId <= 0
                || $paymentData === null
                || !in_array($propertyId, $propertyIds, true)
                || !$this->canWriteByPropertyId($propertyId, $userId)
            ) {
                return $this->renderRentalPayments($rents, $payments, '', 'rental_write_failed', $prefillRentId);
            }
            if (
                $paymentData['status'] === 'validated'
                && !$paymentData['confirmOverpayment']
                && $this->rentPaymentStatusService()->wouldOverpay(
                    $rentId,
                    $paymentData['amountPaid'],
                    $paymentData['paymentKind'],
                    $paymentId
                )
            ) {
                return $this->renderRentalPayments($rents, $payments, '', 'rental_overpayment_requires_confirmation', $prefillRentId);
            }

            $updated = $this->rentalLifecycleRepository()->updatePayment(
                $paymentId,
                $propertyIds,
                $paymentData['paymentDate'],
                $paymentData['amountPaid'],
                $paymentData['status'],
                $userId,
                $paymentData['notes'],
                $paymentData['paymentKind'],
                $paymentData['paymentMethod'],
                $paymentData['paymentReference']
            );
            if (!is_array($updated)) {
                return $this->renderRentalPayments($rents, $payments, '', 'rental_write_failed', $prefillRentId);
            }
            $this->rentPaymentStatusService()->refreshRentStatus($rentId);

            $this->logEvent('private.rental_payment.updated', [
                'private_user_id' => $userId,
                'rental_property_id' => $propertyId,
                'rental_payment_id' => $paymentId,
                'payment_kind' => $paymentData['paymentKind'],
                'payment_method' => $paymentData['paymentMethod'],
            ]);

            return $this->redirect(private_portal_url('rental_payments') . '?notice=payment_updated');
        }

        if ($action !== 'create_payment') {
            return $this->renderRentalPayments($rents, $payments, '', 'rental_invalid_request', $prefillRentId);
        }

        $rentId = $this->normalizeNumericId($body['rental_rent_id'] ?? null);
        $rent = $this->rentalLifecycleRepository()->findRentById($rentId);
        $leaseId = is_array($rent) && is_numeric($rent['rentalLeaseId'] ?? null)
            ? (int) $rent['rentalLeaseId']
            : 0;
        $propertyId = is_array($rent) && is_numeric($rent['rentalPropertyId'] ?? null)
            ? (int) $rent['rentalPropertyId']
            : 0;
        $unitId = is_array($rent) && is_numeric($rent['rentalUnitId'] ?? null)
            ? (int) $rent['rentalUnitId']
            : 0;
        $periodYear = is_array($rent) && is_numeric($rent['periodYear'] ?? null)
            ? (int) $rent['periodYear']
            : 0;
        $periodMonth = is_array($rent) && is_numeric($rent['periodMonth'] ?? null)
            ? (int) $rent['periodMonth']
            : 0;
        if (
            !$this->canWriteByPropertyId($propertyId, $userId)
            || $unitId <= 0
            || !in_array($propertyId, $propertyIds, true)
            || (is_array($rent) && ($rent['status'] ?? '') === 'cancelled')
        ) {
            return $this->renderRentalPayments($rents, $payments, '', 'property_forbidden', $prefillRentId);
        }

        $paymentData = $this->rentalPaymentDataFromBody($body);
        if ($paymentData === null) {
            return $this->renderRentalPayments($rents, $payments, '', 'rental_write_failed', $prefillRentId);
        }
        if (
            $paymentData['status'] === 'validated'
            && !$paymentData['confirmOverpayment']
            && $this->rentPaymentStatusService()->wouldOverpay($rentId, $paymentData['amountPaid'], $paymentData['paymentKind'])
        ) {
            return $this->renderRentalPayments($rents, $payments, '', 'rental_overpayment_requires_confirmation', $prefillRentId);
        }

        $created = $this->rentalLifecycleRepository()->createPayment(
            $leaseId,
            $propertyId,
            $unitId,
            $paymentData['paymentDate'],
            $periodYear,
            $periodMonth,
            0.0,
            $paymentData['amountPaid'],
            $paymentData['status'],
            $userId,
            $paymentData['notes'],
            $rentId,
            $paymentData['paymentKind'],
            $paymentData['paymentMethod'],
            $paymentData['paymentReference']
        );
        if (!is_array($created)) {
            return $this->renderRentalPayments($rents, $payments, '', 'rental_write_failed', $prefillRentId);
        }
        $this->rentPaymentStatusService()->refreshRentStatus($rentId);

        $this->logEvent('private.rental_payment.created', [
            'private_user_id' => $userId,
            'rental_property_id' => $propertyId,
            'rental_payment_id' => (int) ($created['id'] ?? 0),
            'payment_kind' => $paymentData['paymentKind'],
            'payment_method' => $paymentData['paymentMethod'],
        ]);

        return $this->redirect(private_portal_url('rental_payments') . '?notice=payment_created');
    }
    private function handleRentalExpenses(Request $request): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $propertyIds = $this->authorizedPropertyIds($userId);
        $properties = $this->rentalPropertyRepository()->listByIds($propertyIds, self::MAX_RENTAL_LIST);
        $units = $this->rentalUnitRepository()->listByPropertyIds($propertyIds, self::MAX_RENTAL_LIST);
        $expenses = $this->rentalLifecycleRepository()->listExpenses($propertyIds, null, self::MAX_RENTAL_LIST);
        $query = $request->query();
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : '';
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : '';

        if ($request->method() !== self::METHOD_POST) {
            return $this->renderRentalExpenses($properties, $units, $expenses, $notice, $error);
        }

        if (!$this->guard()->validateCsrf($request, self::CSRF_RENTAL)) {
            return $this->renderRentalExpenses($properties, $units, $expenses, '', 'rental_invalid_request');
        }

        $body = $request->body();
        $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : 'create_expense';
        if ($action === 'delete_expense') {
            $expenseId = $this->normalizeNumericId($body['expense_id'] ?? null);
            if ($expenseId <= 0 || !$this->rentalLifecycleRepository()->deleteExpense($expenseId, $propertyIds)) {
                return $this->renderRentalExpenses($properties, $units, $expenses, '', 'rental_delete_failed');
            }

            $this->logEvent('private.rental_expense.deleted', [
                'private_user_id' => $userId,
                'rental_expense_id' => $expenseId,
            ]);

            return $this->redirect(private_portal_url('rental_expenses') . '?notice=expense_deleted');
        }

        if ($action !== 'create_expense') {
            return $this->renderRentalExpenses($properties, $units, $expenses, '', 'rental_invalid_request');
        }

        $propertyId = $this->normalizeNumericId($body['rental_property_id'] ?? null);
        $unitId = $this->normalizeNumericId($body['rental_unit_id'] ?? null);
        if (!$this->canWriteByPropertyId($propertyId, $userId)) {
            return $this->renderRentalExpenses($properties, $units, $expenses, '', 'property_forbidden');
        }
        if ($unitId > 0 && !$this->unitBelongsToProperty($unitId, $propertyId)) {
            return $this->renderRentalExpenses($properties, $units, $expenses, '', 'rental_invalid_request');
        }

        $created = $this->rentalLifecycleRepository()->createExpense(
            $propertyId,
            $unitId > 0 ? $unitId : null,
            (string) ($body['expense_date'] ?? ''),
            (string) ($body['label'] ?? ''),
            is_numeric($body['amount'] ?? null) ? (float) $body['amount'] : 0.0,
            isset($body['is_recoverable']) && (int) $body['is_recoverable'] === 1,
            isset($body['is_deductible_candidate']) && (int) $body['is_deductible_candidate'] === 1,
            is_string($body['status'] ?? null) ? (string) $body['status'] : 'draft',
            $userId,
            is_string($body['notes'] ?? null) ? (string) $body['notes'] : null,
            is_string($body['expense_category'] ?? null) ? (string) $body['expense_category'] : RentalExpenseCategoryCatalog::DEFAULT,
            is_numeric($body['tax_year'] ?? null) ? (int) $body['tax_year'] : null
        );
        if (!is_array($created)) {
            return $this->renderRentalExpenses($properties, $units, $expenses, '', 'rental_write_failed');
        }

        $this->logEvent('private.rental_expense.created', [
            'private_user_id' => $userId,
            'rental_property_id' => $propertyId,
            'rental_expense_id' => (int) ($created['id'] ?? 0),
        ]);

        return $this->redirect(private_portal_url('rental_expenses') . '?notice=expense_created');
    }
    private function handleRentalRegularizations(Request $request): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $propertyIds = $this->authorizedPropertyIds($userId);
        $properties = $this->rentalPropertyRepository()->listByIds($propertyIds, self::MAX_RENTAL_LIST);
        $units = $this->rentalUnitRepository()->listByPropertyIds($propertyIds, self::MAX_RENTAL_LIST);
        $regularizations = $this->rentalLifecycleRepository()->listChargeRegularizations($propertyIds, null, self::MAX_RENTAL_LIST);
        $query = $request->query();
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : '';
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : '';

        if ($request->method() !== self::METHOD_POST) {
            return $this->renderRentalRegularizations($properties, $units, $regularizations, null, $notice, $error);
        }

        if (!$this->guard()->validateCsrf($request, self::CSRF_RENTAL)) {
            return $this->renderRentalRegularizations($properties, $units, $regularizations, null, '', 'rental_invalid_request');
        }

        $body = $request->body();
        $propertyId = $this->normalizeNumericId($body['rental_property_id'] ?? null);
        $unitId = $this->normalizeNumericId($body['rental_unit_id'] ?? null);
        if (!$this->canWriteByPropertyId($propertyId, $userId)) {
            return $this->renderRentalRegularizations($properties, $units, $regularizations, null, '', 'property_forbidden');
        }
        if ($unitId > 0 && !$this->unitBelongsToProperty($unitId, $propertyId)) {
            return $this->renderRentalRegularizations($properties, $units, $regularizations, null, '', 'rental_invalid_request');
        }

        $year = is_numeric($body['year'] ?? null) ? (int) $body['year'] : (int) date('Y');
        $share = is_numeric($body['tenant_share_percent'] ?? null) ? (float) $body['tenant_share_percent'] : 100.0;
        $preview = $this->chargeRegularizationService()->preview(
            $propertyId,
            $unitId > 0 ? $unitId : null,
            $year,
            $share,
            $propertyIds
        );
        if (!is_array($preview)) {
            return $this->renderRentalRegularizations($properties, $units, $regularizations, null, '', 'rental_write_failed');
        }

        $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : 'preview_regularization';
        if ($action === 'preview_regularization') {
            return $this->renderRentalRegularizations($properties, $units, $regularizations, $preview, '', '');
        }
        if ($action !== 'generate_regularization') {
            return $this->renderRentalRegularizations($properties, $units, $regularizations, null, '', 'rental_invalid_request');
        }

        $regularization = $this->chargeRegularizationService()->generate(
            $propertyId,
            $unitId > 0 ? $unitId : null,
            $year,
            $share,
            $propertyIds,
            $userId
        );
        if (!is_array($regularization)) {
            return $this->renderRentalRegularizations($properties, $units, $regularizations, $preview, '', 'rental_write_failed');
        }

        $this->logEvent('private.rental_charge_regularization.generated', [
            'private_user_id' => $userId,
            'rental_property_id' => $propertyId,
            'rental_charge_regularization_id' => is_numeric($regularization['id'] ?? null) ? (int) $regularization['id'] : 0,
        ]);

        return $this->redirect(private_portal_url('rental_regularizations') . '?notice=regularization_generated');
    }
    private function handleRentalDocuments(Request $request): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $propertyIds = $this->authorizedPropertyIds($userId);
        $properties = $this->rentalPropertyRepository()->listByIds($propertyIds, self::MAX_RENTAL_LIST);
        $units = $this->rentalUnitRepository()->listByPropertyIds($propertyIds, self::MAX_RENTAL_LIST);
        $leases = $this->rentalLifecycleRepository()->listLeases($propertyIds, self::MAX_RENTAL_LIST);
        $documents = array_merge(
            $this->hubDocumentsForRentalList($propertyIds),
            $this->rentalLifecycleRepository()->listDocuments($propertyIds, self::MAX_RENTAL_LIST)
        );
        $query = $request->query();
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : '';
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : '';

        if ($request->method() !== self::METHOD_POST) {
            return $this->renderRentalDocuments($properties, $units, $leases, $documents, $notice, $error);
        }

        if (!$this->guard()->validateCsrf($request, self::CSRF_RENTAL)) {
            return $this->renderRentalDocuments($properties, $units, $leases, $documents, '', 'rental_invalid_request');
        }

        $body = $request->body();
        $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : 'upload_document';

        if ($action === 'delete_document' || $action === 'delete_selected_documents' || $action === 'delete_all_documents') {
            if ($action === 'delete_all_documents') {
                $confirmation = is_string($body['confirm_delete_all'] ?? null) ? trim((string) $body['confirm_delete_all']) : '';
                if ($confirmation !== 'SUPPRIMER') {
                    return $this->renderRentalDocuments($properties, $units, $leases, $documents, '', 'rental_purge_confirmation_required');
                }
            }

            $documentIds = $action === 'delete_all_documents'
                ? array_values(array_filter(
                    array_map(
                        static fn (array $document): string => is_string($document['documentId'] ?? null) ? (string) $document['documentId'] : '',
                        $documents
                    ),
                    static fn (string $documentId): bool => $documentId !== ''
                ))
                : $this->documentIdsFromPayload($body['document_ids'] ?? $body['document_id'] ?? []);

            $deletedCount = $this->deleteRentalDocuments($documentIds, $propertyIds, $userId);
            if ($deletedCount <= 0) {
                return $this->renderRentalDocuments($properties, $units, $leases, $documents, '', 'rental_delete_failed');
            }

            return $this->redirect(private_portal_url('rental_documents') . '?notice=document_deleted');
        }

        if ($action === 'purge_rental_data') {
            $confirmation = is_string($body['confirm_purge'] ?? null) ? trim((string) $body['confirm_purge']) : '';
            if ($confirmation !== 'SUPPRIMER') {
                return $this->renderRentalDocuments($properties, $units, $leases, $documents, '', 'rental_purge_confirmation_required');
            }

            $deleted = $this->rentalLifecycleRepository()->deleteLifecycleDataByPropertyIds($propertyIds);

            $this->logEvent('private.rental_data.purged', [
                'private_user_id' => $userId,
                'counts' => $deleted,
            ]);

            return $this->redirect(private_portal_url('rental_documents') . '?notice=rental_data_purged');
        }

        if ($action !== 'upload_document') {
            return $this->renderRentalDocuments($properties, $units, $leases, $documents, '', 'rental_invalid_request');
        }

        $propertyId = $this->normalizeNumericId($body['rental_property_id'] ?? null);
        $unitId = $this->normalizeNumericId($body['rental_unit_id'] ?? null);
        $leaseId = $this->normalizeNumericId($body['rental_lease_id'] ?? null);
        if (!$this->canWriteByPropertyId($propertyId, $userId)) {
            return $this->renderRentalDocuments($properties, $units, $leases, $documents, '', 'property_forbidden');
        }
        if ($unitId > 0 && !$this->unitBelongsToProperty($unitId, $propertyId)) {
            return $this->renderRentalDocuments($properties, $units, $leases, $documents, '', 'rental_invalid_request');
        }

        $files = $this->normalizeRentalUploadedFiles($request->files()['rental_document_file'] ?? null);
        if ($files === []) {
            return $this->renderRentalDocuments($properties, $units, $leases, $documents, '', 'rental_missing_file');
        }

        $entityRefs = [DocumentEntityRef::of(RentalDocumentIntegration::ENTITY_PROPERTY, $propertyId)];
        if ($unitId > 0) {
            $entityRefs[] = DocumentEntityRef::of(RentalDocumentIntegration::ENTITY_UNIT, $unitId);
        }
        if ($leaseId > 0) {
            $entityRefs[] = DocumentEntityRef::of(RentalDocumentIntegration::ENTITY_LEASE, $leaseId);
        }

        $results = $this->documentHubImportService()->importBatch(
            $userId,
            RentalDocumentIntegration::PROFILE_DOCUMENTS,
            $files,
            $entityRefs,
            [
                'category_code' => is_string($body['category_code'] ?? null) ? trim((string) $body['category_code']) : '',
                'title' => is_string($body['display_name'] ?? null) ? trim((string) $body['display_name']) : '',
                'document_date' => is_string($body['document_date'] ?? null) ? trim((string) $body['document_date']) : '',
            ]
        );

        $imported = 0;
        $firstError = '';
        foreach ($results as $result) {
            if ($result['ok']) {
                ++$imported;
            } elseif ($firstError === '') {
                $firstError = (string) $result['error_code'];
            }
        }

        if ($imported === 0) {
            return $this->renderRentalDocuments(
                $properties,
                $units,
                $leases,
                $documents,
                '',
                $firstError !== '' ? 'hub_' . $firstError : 'rental_upload_failed'
            );
        }

        return $this->redirect(private_portal_url('rental_documents') . '?notice=document_uploaded');
    }

    /**
     * Documents du hub central rattachés aux biens autorisés, au format attendu
     * par le gabarit de l'onglet Documents (fusion avec les documents legacy).
     *
     * @param array<int, int> $propertyIds
     * @return array<int, array<string, mixed>>
     */
    private function hubDocumentsForRentalList(array $propertyIds): array
    {
        $taxonomy = $this->documentTaxonomyRepository();
        $linkService = $this->documentHubLinkService();
        $categoryLabels = [];
        foreach ($taxonomy->listActive() as $categoryRow) {
            $code = is_string($categoryRow['code'] ?? null) ? (string) $categoryRow['code'] : '';
            if ($code !== '') {
                $categoryLabels[$code] = is_string($categoryRow['label'] ?? null) ? (string) $categoryRow['label'] : $code;
            }
        }

        $rows = [];
        $seenUids = [];
        foreach ($propertyIds as $propertyId) {
            $hubDocuments = $this->documentHubRepository()->documentsForEntity(
                RentalDocumentIntegration::ENTITY_PROPERTY,
                (string) (int) $propertyId,
                self::MAX_RENTAL_LIST
            );
            foreach ($hubDocuments as $hubDocument) {
                $uid = is_string($hubDocument['document_uid'] ?? null) ? (string) $hubDocument['document_uid'] : '';
                if ($uid === '' || isset($seenUids[$uid])) {
                    continue;
                }
                $seenUids[$uid] = true;

                $links = is_array($hubDocument['links'] ?? null) ? $hubDocument['links'] : [];
                $propertyName = '';
                $unitLabel = '';
                foreach ($linkService->describeLinks($links) as $link) {
                    if ($link['entity_type'] === RentalDocumentIntegration::ENTITY_PROPERTY && $propertyName === '') {
                        $propertyName = $link['label'];
                    }
                    if ($link['entity_type'] === RentalDocumentIntegration::ENTITY_UNIT && $unitLabel === '') {
                        $unitLabel = $link['label'];
                    }
                }

                $categoryCode = (string) ($hubDocument['category_code'] ?? 'inbox');
                $rows[] = [
                    'documentId' => $uid,
                    'originalName' => (string) ($hubDocument['original_filename'] ?? ''),
                    'displayName' => (string) ($hubDocument['title'] ?? ''),
                    'category' => $categoryLabels[$categoryCode] ?? $categoryCode,
                    'sizeBytes' => (int) ($hubDocument['stored_size'] ?? 0),
                    'uploadedAt' => (string) ($hubDocument['created_at'] ?? ''),
                    'propertyName' => $propertyName,
                    'unitLabel' => $unitLabel,
                    'expenseLabel' => '',
                    'isHubDocument' => true,
                ];
            }
        }

        return $rows;
    }

    /**
     * Normalise un champ $_FILES simple ou multiple en liste de fichiers.
     *
     * @return array<int, array{name: string, tmp_name: string, size: int, error: int}>
     */
    private function normalizeRentalUploadedFiles(mixed $filesField): array
    {
        if (!is_array($filesField)) {
            return [];
        }

        if (is_string($filesField['name'] ?? null)) {
            $error = is_numeric($filesField['error'] ?? null) ? (int) $filesField['error'] : UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE) {
                return [];
            }

            return [[
                'name' => (string) $filesField['name'],
                'tmp_name' => is_string($filesField['tmp_name'] ?? null) ? (string) $filesField['tmp_name'] : '',
                'size' => is_numeric($filesField['size'] ?? null) ? (int) $filesField['size'] : 0,
                'error' => $error,
            ]];
        }

        if (!is_array($filesField['name'] ?? null)) {
            return [];
        }

        $files = [];
        foreach ($filesField['name'] as $index => $name) {
            $error = is_numeric($filesField['error'][$index] ?? null)
                ? (int) $filesField['error'][$index]
                : UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $files[] = [
                'name' => is_string($name) ? $name : '',
                'tmp_name' => is_string($filesField['tmp_name'][$index] ?? null) ? (string) $filesField['tmp_name'][$index] : '',
                'size' => is_numeric($filesField['size'][$index] ?? null) ? (int) $filesField['size'][$index] : 0,
                'error' => $error,
            ];
        }

        return $files;
    }
    private function handleRentalAgencyImports(Request $request): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $query = $request->query();
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : '';
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : '';
        $tab = $this->agencyImportTab($query['tab'] ?? null);

        if ($request->method() !== self::METHOD_POST) {
            return $this->renderRentalAgencyImports($userId, $notice, $error, $tab);
        }

        if (!$this->guard()->validateCsrf($request, self::CSRF_RENTAL)) {
            return $this->renderRentalAgencyImports($userId, '', 'rental_invalid_request', $tab);
        }

        $body = $request->body();
        $action = is_string($body['action'] ?? null) ? trim((string) $body['action']) : 'agency_import';
        if ($action === 'create_agency') {
            $agencyName = is_string($body['agency_name'] ?? null) ? trim((string) $body['agency_name']) : '';
            $created = $this->agencyImportRepository()->createAgency($userId, $agencyName);
            $this->logEvent('private.rental_agency_import.agency_created', [
                'private_user_id' => $userId,
                'agency_name' => $agencyName,
                'success' => $created,
            ]);

            return $this->redirect($this->rentalAgencyImportsUrl(
                'agencies',
                $created ? 'agency_created' : '',
                $created ? '' : 'agency_create_failed'
            ));
        }

        if ($action === 'create_agency_unit_mapping') {
            $agencyName = is_string($body['agency_name'] ?? null) ? trim((string) $body['agency_name']) : '';
            $matchText = is_string($body['match_text'] ?? null) ? trim((string) $body['match_text']) : '';
            $unitId = $this->normalizeNumericId($body['rental_unit_id'] ?? null);
            $unit = $unitId > 0 ? $this->rentalUnitRepository()->findById($unitId) : null;
            $propertyId = $unit !== null ? $unit->rentalPropertyId : 0;
            $created = $unit !== null
                && $this->canWriteByPropertyId($propertyId, $userId)
                && $this->agencyImportRepository()->createUnitMapping($userId, $agencyName, $matchText, $propertyId, $unitId);
            $this->logEvent('private.rental_agency_import.unit_mapping_created', [
                'private_user_id' => $userId,
                'agency_name' => $agencyName,
                'match_text' => $matchText,
                'rental_property_id' => $propertyId,
                'rental_unit_id' => $unitId,
                'success' => $created,
            ]);

            return $this->redirect($this->rentalAgencyImportsUrl(
                'agencies',
                $created ? 'agency_unit_mapping_created' : '',
                $created ? '' : 'agency_unit_mapping_failed'
            ));
        }

        if ($action === 'delete_agency_unit_mapping') {
            $mappingId = $this->normalizeNumericId($body['agency_unit_mapping_id'] ?? null);
            $deleted = $this->agencyImportRepository()->deleteUnitMappingForUser($userId, $mappingId);
            $this->logEvent('private.rental_agency_import.unit_mapping_deleted', [
                'private_user_id' => $userId,
                'agency_unit_mapping_id' => $mappingId,
                'success' => $deleted,
            ]);

            return $this->redirect($this->rentalAgencyImportsUrl(
                'agencies',
                $deleted ? 'agency_unit_mapping_deleted' : '',
                $deleted ? '' : 'agency_unit_mapping_delete_failed'
            ));
        }

        if ($action === 'delete_agency_document') {
            $documentId = is_numeric($body['agency_document_id'] ?? null) ? (int) $body['agency_document_id'] : 0;
            $deletedDocument = $this->agencyImportRepository()->deleteImportedDocumentForUser($userId, $documentId);
            if ($deletedDocument === null) {
                return $this->redirect($this->rentalAgencyImportsUrl('documents', '', 'agency_document_delete_failed'));
            }

            $this->logEvent('private.rental_agency_import.document_deleted', [
                'private_user_id' => $userId,
                'agency_imported_document_id' => $deletedDocument->id,
                'private_document_id' => $deletedDocument->privateDocumentId,
            ]);

            return $this->redirect($this->rentalAgencyImportsUrl('documents', 'agency_document_deleted'));
        }

        if ($action !== 'agency_import') {
            return $this->renderRentalAgencyImports($userId, '', 'rental_invalid_request', $tab);
        }

        // Import de fichier - pour l'instant, retourner une erreur
        return $this->renderRentalAgencyImports($userId, '', 'agency_import_not_yet_implemented', $tab);
    }
    private function handleRentalAgencyReview(Request $request): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $query = $request->query();
        $documentId = $this->normalizeNumericId($query['document_id'] ?? null);
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : '';
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : '';
        $lineFeedbackId = $this->normalizeNumericId($query['line_id'] ?? null);
        $lineNotice = is_string($query['line_notice'] ?? null) ? (string) $query['line_notice'] : '';
        $lineError = is_string($query['line_error'] ?? null) ? (string) $query['line_error'] : '';

        if ($request->method() !== self::METHOD_POST) {
            return $this->renderRentalAgencyReview(
                $userId,
                $documentId,
                $notice,
                $error,
                $lineFeedbackId,
                $lineNotice,
                $lineError
            );
        }

        $body = $request->body();
        $documentId = $this->normalizeNumericId($body['document_id'] ?? $documentId);
        if (!$this->guard()->validateCsrf($request, self::CSRF_RENTAL)) {
            return $this->redirect($this->rentalAgencyReviewUrl($documentId, '', 'rental_invalid_request'));
        }

        $repository = $this->agencyImportRepository();
        $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : '';

        // Pour l'instant, implémenter uniquement l'action update_statement_property
        if ($action === 'update_statement_property') {
            $propertyId = $this->normalizeNumericId($body['rental_property_id'] ?? null);
            $propertyId = $propertyId > 0 ? $propertyId : null;
            if ($propertyId !== null && !$this->canWriteByPropertyId($propertyId, $userId)) {
                return $this->redirect($this->rentalAgencyReviewUrl($documentId, '', 'property_forbidden'));
            }

            $updated = $repository->updateStatementPropertyForDocument($userId, $documentId, $propertyId);
            $this->logEvent('private.rental_agency_review.property_updated', [
                'private_user_id' => $userId,
                'agency_imported_document_id' => $documentId,
                'rental_property_id' => $propertyId,
                'success' => $updated,
            ]);

            return $this->redirect($this->rentalAgencyReviewUrl(
                $documentId,
                $updated ? 'agency_statement_property_updated' : '',
                $updated ? '' : 'agency_review_failed'
            ));
        }

        // Autres actions (validate_line, correct_line, ignore_line) - pour l'instant, retourner une erreur
        return $this->redirect($this->rentalAgencyReviewUrl($documentId, '', 'agency_review_not_yet_implemented'));
    }

    private function handleRentalDocumentFile(Request $request, string $documentId): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $documentId = $this->normalizeDocumentId($documentId);

        $hubResponse = $this->tryHubDocumentDownload($documentId, $userId);
        if ($hubResponse !== null) {
            return $hubResponse;
        }

        $document = $documentId !== '' ? $this->rentalLifecycleRepository()->findDocumentByDocumentId($documentId) : null;
        $propertyId = is_array($document) && is_numeric($document['rentalPropertyId'] ?? null)
            ? (int) $document['rentalPropertyId']
            : 0;
        if (!is_array($document) || !$this->rentalPropertyMemberRepository()->isActiveMember($propertyId, $userId)) {
            return $this->handleModuleAccessDenied('real_estate_rental');
        }

        $storagePath = is_string($document['storagePath'] ?? null) ? (string) $document['storagePath'] : '';
        $absolutePath = $this->privateDocumentStorage()->absolutePath($storagePath);
        if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return $this->withPrivateHeaders(new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Not Found'));
        }

        $body = file_get_contents($absolutePath);
        if (!is_string($body)) {
            return $this->withPrivateHeaders(new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Not Found'));
        }

        $filename = $this->sanitizeDownloadFilename((string) ($document['originalName'] ?? 'document'));
        $mimeType = is_string($document['mimeType'] ?? null) ? (string) $document['mimeType'] : 'application/octet-stream';

        $this->logEvent('private.rental_document.downloaded', [
            'private_user_id' => $userId,
            'rental_property_id' => $propertyId,
            'document_id' => $documentId,
        ]);

        return $this->withPrivateHeaders(new Response(200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ], $body));
    }

    private function handleRentalRegularizationFile(Request $request, string $documentId): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $documentId = $this->normalizeDocumentId($documentId);
        $propertyIds = $this->authorizedPropertyIds($userId);
        $document = $documentId !== ''
            ? $this->rentalLifecycleRepository()->findChargeRegularizationByDocumentId($documentId, $propertyIds)
            : null;
        if (!is_array($document)) {
            return $this->handleModuleAccessDenied('real_estate_rental');
        }

        $body = $this->chargeRegularizationService()->content($document);
        if (!is_string($body)) {
            return $this->withPrivateHeaders(new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Not Found'));
        }

        $filename = $this->sanitizeDownloadFilename((string) ($document['originalName'] ?? 'regularisation-charges.pdf'));
        $propertyId = is_numeric($document['rentalPropertyId'] ?? null) ? (int) $document['rentalPropertyId'] : 0;
        $this->logEvent('private.rental_charge_regularization.downloaded', [
            'private_user_id' => $userId,
            'rental_property_id' => $propertyId,
            'rental_charge_regularization_id' => is_numeric($document['id'] ?? null) ? (int) $document['id'] : 0,
            'document_id' => $documentId,
        ]);

        return $this->withPrivateHeaders(new Response(200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ], $body));
    }

    private function handleRentalSummary(Request $request): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $propertyIds = $this->authorizedPropertyIds($userId);
        $summary = $this->rentalAnnualSummaryService()->build($this->yearFromRequest($request), $propertyIds);

        return $this->renderRentalSummary(
            $summary,
            $this->objectsToArrays($this->rentalPropertyRepository()->listByIds($propertyIds, self::MAX_RENTAL_LIST)),
            $this->rentalLifecycleRepository()->listTenants($propertyIds, self::MAX_RENTAL_LIST)
        );
    }

    private function handleRentalExport(Request $request, string $format): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $year = $this->yearFromRequest($request);
        $query = $request->query();
        $kind = is_string($query['kind'] ?? null) ? (string) $query['kind'] : 'summary_' . $format;
        $propertyId = $this->normalizeNumericId($query['property_id'] ?? null);
        $tenantId = $this->normalizeNumericId($query['tenant_id'] ?? null);
        $export = $this->rentalExportService()->create(
            $userId,
            $year,
            $kind,
            $this->authorizedPropertyIds($userId),
            $propertyId > 0 ? $propertyId : null,
            $tenantId > 0 ? $tenantId : null
        );
        if (($export['error'] ?? '') === 'draft_data') {
            $this->logEvent('private.rental_export.rejected', [
                'private_user_id' => $userId,
                'year' => $year,
                'format' => $format,
                'reason' => 'draft_data',
            ]);

            return $this->withPrivateHeaders(new Response(
                409,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
                'Export bloque : donnees locatives brouillon.'
            ));
        }
        if (!($export['success'] ?? false)) {
            $this->logEvent('private.rental_export.rejected', [
                'private_user_id' => $userId,
                'year' => $year,
                'format' => $format,
                'kind' => $kind,
                'reason' => (string) ($export['error'] ?? 'export_failed'),
            ]);

            $status = ($export['error'] ?? '') === 'forbidden' ? 403 : 422;

            return $this->withPrivateHeaders(new Response(
                $status,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
                $status === 403 ? 'Export refuse.' : 'Export locatif impossible.'
            ));
        }

        $this->logEvent('private.rental_export.created', [
            'private_user_id' => $userId,
            'year' => $year,
            'format' => (string) ($export['format'] ?? $format),
            'kind' => (string) ($export['kind'] ?? $kind),
        ]);

        $content = is_string($export['content'] ?? null) ? (string) $export['content'] : '';
        $filename = $this->sanitizeDownloadFilename((string) ($export['filename'] ?? ('export-locatif.' . $format)));
        $mimeType = is_string($export['mimeType'] ?? null) ? (string) $export['mimeType'] : 'application/octet-stream';
        $this->rentalExportService()->cleanup($export);

        return $this->withPrivateHeaders(new Response(200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ], $content));
    }
}
