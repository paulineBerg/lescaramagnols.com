<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Http;

use Caramagnols\Http\Request;
use Caramagnols\Http\Response;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import\AgencyImportService;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Repository\AgencyImportRepository;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Repository\AgencyMappingRepository;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Service\AgencyAdvancedReconciliationService;
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
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalReceiptService;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentPaymentStatusService;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentScheduleService;
use Caramagnols\PrivatePortal\Http\PrivateResponseHeaders;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivatePortalSecurityGuard;

/**
 * Controller for the RealEstateRental private module.
 * Extracts rental-related handling from PrivatePortalController.
 */
final class RealEstateRentalController
{
    private const METHOD_GET = 'GET';
    private const METHOD_POST = 'POST';
    private const CSRF_RENTAL = 'private_rental';
    private const MAX_RENTAL_LIST = 200;
    private const RENTAL_DOCUMENT_UPLOAD_FIELD = 'rental_document_file';
    private const AGENCY_IMPORT_UPLOAD_FIELD = 'agency_import_file';

    /**
     * @param \Closure(string, array<string, mixed>): Response $render
     */
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
        private readonly ?AgencyStatementValidationService $agencyStatementValidationService = null,
    ) {
    }

    /**
     * Main handler for rental routes.
     */
    public function handle(string $page, Request $request, array $routeParams = []): Response
    {
        return match ($page) {
            'rental_dashboard' => $this->handleRentalDashboard($request),
            'rental_lessors' => $this->handleRentalLessors($request),
            'rental_properties' => $this->handleRentalProperties($request),
            'rental_property_archive' => $this->handleRentalPropertyArchive(
                $request,
                (int) ($routeParams['propertyId'] ?? 0)
            ),
            'rental_units' => $this->handleRentalUnits($request),
            'rental_unit_archive' => $this->handleRentalUnitArchive(
                $request,
                (int) ($routeParams['unitId'] ?? 0)
            ),
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
            'rental_document_file' => $this->handleRentalDocumentFile(
                $request,
                (string) ($routeParams['documentId'] ?? '')
            ),
            'rental_regularization_file' => $this->handleRentalRegularizationFile(
                $request,
                (string) ($routeParams['documentId'] ?? '')
            ),
            'rental_summary' => $this->handleRentalSummary($request),
            'rental_export' => $this->handleRentalExport($request, (string) ($routeParams['format'] ?? '')),
            default => throw new \InvalidArgumentException("Unknown rental page: {$page}"),
        };
    }

    // TODO: Move all handleRental* methods from PrivatePortalController here
    // This is a placeholder - the actual methods need to be moved

    private function redirect(string $url): Response
    {
        return new Response(302, ['Location' => $url], '');
    }

    /**
     * @param array<string, mixed> $viewModel
     */
    private function render(string $template, array $viewModel = []): Response
    {
        return ($this->render)($template, $viewModel);
    }

    // ========== Methods to be moved from PrivatePortalController ==========
    // These will be implemented by extracting from PrivatePortalController

    private function handleRentalDashboard(Request $request): Response
    {
        // TODO: Extract from PrivatePortalController line 367
        return $this->redirect(private_portal_url('rental_dashboard'));
    }

    private function handleRentalLessors(Request $request): Response
    {
        // TODO: Extract from PrivatePortalController line 377
        return $this->redirect(private_portal_url('rental_lessors'));
    }

    private function handleRentalProperties(Request $request): Response
    {
        // TODO: Extract from PrivatePortalController line 449
        return $this->redirect(private_portal_url('rental_properties'));
    }

    private function handleRentalPropertyArchive(Request $request, int $propertyId): Response
    {
        // TODO: Extract from PrivatePortalController line 559
        return $this->redirect(private_portal_url('rental_properties'));
    }

    private function handleRentalUnits(Request $request): Response
    {
        // TODO: Extract from PrivatePortalController line 586
        return $this->redirect(private_portal_url('rental_units'));
    }

    private function handleRentalUnitArchive(Request $request, int $unitId): Response
    {
        // TODO: Extract from PrivatePortalController line 693
        return $this->redirect(private_portal_url('rental_units'));
    }

    private function handleRentalPropertyMembers(Request $request): Response
    {
        // TODO: Extract from PrivatePortalController line 722
        return $this->redirect(private_portal_url('rental_property_members'));
    }

    private function handleRentalTenants(Request $request): Response
    {
        // TODO: Extract from PrivatePortalController line 837
        return $this->redirect(private_portal_url('rental_tenants'));
    }

    private function handleRentalLeases(Request $request): Response
    {
        // TODO: Extract from PrivatePortalController line 957
        return $this->redirect(private_portal_url('rental_leases'));
    }

    private function handleRentalRents(Request $request): Response
    {
        // TODO: Extract from PrivatePortalController line 1148
        return $this->redirect(private_portal_url('rental_rents'));
    }

    private function handleRentalPayments(Request $request): Response
    {
        // TODO: Extract from PrivatePortalController line 1310
        return $this->redirect(private_portal_url('rental_payments'));
    }

    private function handleRentalExpenses(Request $request): Response
    {
        // TODO: Extract from PrivatePortalController line 1537
        return $this->redirect(private_portal_url('rental_expenses'));
    }

    private function handleRentalRegularizations(Request $request): Response
    {
        // TODO: Extract from PrivatePortalController line 1636
        return $this->redirect(private_portal_url('rental_regularizations'));
    }

    private function handleRentalDocuments(Request $request): Response
    {
        // TODO: Extract from PrivatePortalController line 1711
        return $this->redirect(private_portal_url('rental_documents'));
    }

    private function handleRentalAgencyImports(Request $request): Response
    {
        // TODO: Extract from PrivatePortalController line 1860
        return $this->redirect(private_portal_url('rental_agency_imports'));
    }

    private function handleRentalAgencyReview(Request $request): Response
    {
        // TODO: Extract from PrivatePortalController line 2024
        return $this->redirect(private_portal_url('rental_agency_review'));
    }

    private function handleRentalDocumentFile(Request $request, string $documentId): Response
    {
        // TODO: Extract from PrivatePortalController line 2179
        return $this->redirect(private_portal_url('rental_documents'));
    }

    private function handleRentalRegularizationFile(Request $request, string $documentId): Response
    {
        // TODO: Extract from PrivatePortalController line 2222
        return $this->redirect(private_portal_url('rental_regularizations'));
    }

    private function handleRentalSummary(Request $request): Response
    {
        // TODO: Extract from PrivatePortalController line 2259
        return $this->redirect(private_portal_url('rental_summary'));
    }

    private function handleRentalExport(Request $request, string $format): Response
    {
        // TODO: Extract from PrivatePortalController line 2276
        return $this->redirect(private_portal_url('rental_export'));
    }
}
