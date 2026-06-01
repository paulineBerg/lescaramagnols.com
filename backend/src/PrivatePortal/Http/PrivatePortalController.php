<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Http;

use Caramagnols\Http\Request;
use Caramagnols\Http\Response;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PrivateApps\FamilyDiscussion\Attachment\DiscussionAttachmentStorage;
use Caramagnols\PrivateApps\FamilyDiscussion\Realtime\ConversationEventStream;
use Caramagnols\PrivateApps\FamilyDiscussion\Repository\DiscussionRepository;
use Caramagnols\PrivateApps\FamilyDiscussion\Retention\DiscussionRetentionService;
use Caramagnols\PrivateApps\FamilyDiscussion\Service\DiscussionAccessPolicy;
use Caramagnols\PrivateApps\FamilyDiscussion\Service\DiscussionEventService;
use Caramagnols\PrivateApps\FamilyDiscussion\Service\DiscussionMediaService;
use Caramagnols\PrivateApps\FamilyDiscussion\Service\DiscussionNotificationService;
use Caramagnols\PrivateApps\FamilyDiscussion\Service\DiscussionService;
use Caramagnols\PrivateApps\FamilyDiscussion\Service\DiscussionTimelineService;
use Caramagnols\PrivateApps\RealEstateRental\Domain\RentalLeaseTypeCatalog;
use Caramagnols\PrivateApps\RealEstateRental\Domain\RentalExpenseCategoryCatalog;
use Caramagnols\PrivatePortal\Documents\PrivateDocumentRepository;
use Caramagnols\PrivatePortal\Documents\PrivateDocumentStorage;
use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivatePasswordPolicy;
use Caramagnols\PrivatePortal\Security\PrivatePortalSecurityGuard;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLessorRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyMemberRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalUnitRepository;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyImportedDocument;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyFiscalReviewPolicy;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import\AgencyImportService;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Repository\AgencyImportRepository;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Service\AgencyAdvancedReconciliationService;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Service\AgencyTaxBridgeNormalizer;
use Caramagnols\PrivateApps\RealEstateRental\Service\ChargeRegularizationService;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentScheduleService;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentPaymentStatusService;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalAnnualSummaryService;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalDashboardService;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalExportService;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalPaymentRequestService;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalReceiptService;
use Caramagnols\PrivateApps\RealEstateRental\TaxBridge\RentalTaxDataProvider;
use Caramagnols\PrivateApps\TaxDeclarationHelper\Repository\TaxDeclarationRepository;
use Caramagnols\PrivateApps\TaxDeclarationHelper\Service\TaxDeclarationSummaryService;
use Caramagnols\PrivatePortal\BlocNote\BlocNoteRepository;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use Caramagnols\PrivatePortal\Operations\PrivateBackupService;
use Caramagnols\PrivatePortal\Operations\PrivateDataProtectionService;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserMailSettingsRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivatePortal\TaxDeclarationHelper\Source\RentalTaxDataSource;

final class PrivatePortalController
{
    private const METHOD_GET = 'GET';
    private const METHOD_POST = 'POST';
    private const DOCUMENT_UPLOAD_FIELD = 'document_file';
    private const MAX_DOCUMENT_LIST = 50;
    private const CSRF_DOCUMENTS = 'private_documents';
    private const CSRF_RENTAL = 'private_rental';
    private const MAX_RENTAL_LIST = 200;
    private const RENTAL_DOCUMENT_UPLOAD_FIELD = 'rental_document_file';
    private const AGENCY_IMPORT_UPLOAD_FIELD = 'agency_import_file';
    private const CSRF_TAX = 'private_tax_declaration';
    private const CSRF_DISCUSSIONS = 'private_discussions';
    private const CSRF_BLOCNOTE = 'private_blocnote';
    private const CSRF_MEMBER_SETTINGS = 'private_member_settings';
    private const CSRF_SESSION = 'private_session';

    private ?string $lastPrivateMailFailure = null;
    private ?string $currentPrivatePage = null;

    public function __construct(
        private readonly PrivateAuth $auth,
        private readonly ?PrivatePortalSecurityGuard $securityGuard = null,
        private readonly ?AppEventLogger $eventLogger = null,
        private readonly ?PrivateUserRepository $privateUserRepository = null,
        private readonly ?PrivateModulePermissionRepository $modulePermissionRepository = null,
        private readonly ?PrivateDocumentRepository $privateDocumentRepository = null,
        private readonly ?PrivateDocumentStorage $privateDocumentStorage = null,
        private readonly ?RentalPropertyRepository $rentalPropertyRepository = null,
        private readonly ?RentalPropertyMemberRepository $rentalPropertyMemberRepository = null,
        private readonly ?RentalUnitRepository $rentalUnitRepository = null,
        private readonly ?RentalLifecycleRepository $rentalLifecycleRepository = null,
        private readonly ?RentalAnnualSummaryService $rentalAnnualSummaryService = null,
        private readonly ?TaxDeclarationRepository $taxDeclarationRepository = null,
        private readonly ?TaxDeclarationSummaryService $taxDeclarationSummaryService = null,
        private readonly ?PrivateDataProtectionService $privateDataProtectionService = null,
        private readonly ?PrivateBackupService $privateBackupService = null,
        private readonly ?DiscussionRepository $discussionRepository = null,
        private readonly ?DiscussionAttachmentStorage $discussionAttachmentStorage = null,
        private readonly ?DiscussionService $discussionService = null,
        private readonly ?DiscussionRetentionService $discussionRetentionService = null,
        private readonly ?RentScheduleService $rentScheduleService = null,
        private readonly ?RentPaymentStatusService $rentPaymentStatusService = null,
        private readonly ?RentalPaymentRequestService $rentalPaymentRequestService = null,
        private readonly ?RentalReceiptService $rentalReceiptService = null,
        private readonly ?ChargeRegularizationService $chargeRegularizationService = null,
        private readonly ?RentalDashboardService $rentalDashboardService = null,
        private readonly ?RentalExportService $rentalExportService = null,
        private readonly ?PrivateUserMailSettingsRepository $privateUserMailSettingsRepository = null,
        private readonly ?RentalLessorRepository $rentalLessorRepository = null
    ) {
    }

    public function handle(string $page, Request $request, array $routeParams = []): Response
    {
        $this->currentPrivatePage = $page;

        $response = match ($page) {
            'login' => $this->handleLogin($request),
            'dashboard' => $this->handleDashboard($request),
            'member_settings' => $this->handleMemberSettings($request),
            'documents' => $this->handleDocuments($request),
            'blocnote' => $this->handleBlocNote($request),
            'logout' => $this->handleLogout($request),
            'session_ping' => $this->handleSessionPing($request),
            'activate' => $this->handleActivate($request, (string) ($routeParams['token'] ?? '')),
            'password_forgot' => $this->handlePasswordForgot($request),
            'password_reset' => $this->handlePasswordReset(
                $request,
                (string) ($routeParams['token'] ?? '')
            ),
            'files' => $this->handleFiles($request, (string) ($routeParams['documentId'] ?? '')),
            'files_upload' => $this->handleFilesUpload($request),
            'files_categories' => $this->handleFilesCategoryCreate($request),
            'files_delete' => $this->handleFilesDelete(
                $request,
                (string) ($routeParams['documentId'] ?? '')
            ),
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
            'rental_agencies' => $this->handleRentalAgencies($request),
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
            'rental_export_csv' => $this->handleRentalExport($request, 'csv'),
            'rental_export_pdf' => $this->handleRentalExport($request, 'pdf'),
            'rental_export_zip' => $this->handleRentalExport($request, 'zip'),
            'tax_dashboard' => $this->handleTaxDashboard($request),
            'tax_year' => $this->handleTaxYear($request, (int) ($routeParams['year'] ?? 0)),
            'tax_manual_entries' => $this->handleTaxManualEntries($request, (int) ($routeParams['year'] ?? 0)),
            'tax_controls' => $this->handleTaxControls($request, (int) ($routeParams['year'] ?? 0)),
            'tax_documents' => $this->handleTaxDocuments($request, (int) ($routeParams['year'] ?? 0)),
            'tax_export' => $this->handleTaxExport($request, (int) ($routeParams['year'] ?? 0)),
            'discussion_index' => $this->handleDiscussionIndex($request),
            'discussion_new' => $this->handleDiscussionIndex($request),
            'discussion_conversation' => $this->handleDiscussionConversation(
                $request,
                (int) ($routeParams['conversationId'] ?? 0)
            ),
            'discussion_api_conversations' => $this->handleDiscussionApiConversations($request),
            'discussion_api_messages' => $this->handleDiscussionApiMessages(
                $request,
                (int) ($routeParams['conversationId'] ?? 0)
            ),
            'discussion_api_events' => $this->handleDiscussionApiEvents(
                $request,
                (int) ($routeParams['conversationId'] ?? 0)
            ),
            'discussion_api_client_events' => $this->handleDiscussionApiClientEvents($request),
            'discussion_api_crypto_devices' => $this->handleDiscussionApiCryptoDevices($request),
            'discussion_api_conversation_keys' => $this->handleDiscussionApiConversationKeys(
                $request,
                (int) ($routeParams['conversationId'] ?? 0)
            ),
            'discussion_api_members' => $this->handleDiscussionApiMembers(
                $request,
                (int) ($routeParams['conversationId'] ?? 0)
            ),
            'discussion_api_leave' => $this->handleDiscussionApiLeave(
                $request,
                (int) ($routeParams['conversationId'] ?? 0)
            ),
            'discussion_api_read' => $this->handleDiscussionApiRead(
                $request,
                (int) ($routeParams['conversationId'] ?? 0)
            ),
            'discussion_file' => $this->handleDiscussionFile(
                $request,
                (string) ($routeParams['attachmentId'] ?? ''),
                false
            ),
            'discussion_file_preview' => $this->handleDiscussionFile(
                $request,
                (string) ($routeParams['attachmentId'] ?? ''),
                true
            ),
            'privacy_export' => $this->handlePrivacyExport($request),
            'ops_backup' => $this->handleOpsBackup($request),
            default => $this->handleNotFound(),
        };

        return $this->withPrivateHeaders($response);
    }

    private function handleLogin(Request $request): Response
    {
        if ($this->auth->isAuthenticated()) {
            return $this->redirect(private_portal_url('dashboard'));
        }

        $this->guard();

        $body = $request->body();
        $identifier = is_string($body['identifier'] ?? null) ? trim((string) $body['identifier']) : '';

        $error = null;

        if ($request->method() === self::METHOD_POST) {
            $csrfToken = is_string($body['csrf_token'] ?? null) ? $body['csrf_token'] : null;
            if (!csrf_validate($csrfToken, 'private')) {
                $error = 'TXT_PRIVATE_ERROR_CSRF';

                $this->logEvent('private.login.rejected', ['reason' => 'csrf_invalid']);
            } else {
                $password = is_string($body['password'] ?? null) ? (string) $body['password'] : '';
                $clientIp = $request->clientIp((bool) app_config('private.trust_proxy_headers', false));

                if (!$this->auth->canAttemptLogin($identifier, $clientIp)) {
                    $error = $this->auth->failureReason() === 'account_locked'
                        ? 'TXT_PRIVATE_ERROR_ACCOUNT_LOCKED'
                        : 'TXT_PRIVATE_ERROR_RATE_LIMIT';

                    $this->logEvent('private.login.rejected', ['reason' => $this->auth->failureReason() ?? 'unknown', 'ip' => $clientIp ?? '']);
                } else {
                    $mfaCode = is_string($body['mfa_code'] ?? null) ? (string) $body['mfa_code'] : null;
                    if ($this->auth->login($identifier, $password, $clientIp ?? null, $mfaCode)) {
                        return $this->redirect(private_portal_url('dashboard'));
                    } else {
                        $error = in_array($this->auth->failureReason(), ['mfa_required', 'mfa_invalid'], true)
                            ? 'TXT_PRIVATE_ERROR_MFA'
                            : 'TXT_PRIVATE_ERROR_INVALID_CREDENTIALS';
                    }
                }
            }
        }

        return $this->render('login', [
            'privatePageTitle' => $this->translate('TXT_PRIVATE_LOGIN_PAGE_TITLE', 'Espace privé'),
            'identifier' => $identifier,
            'errorKey' => $error,
            'csrfToken' => csrf_token('private'),
            'privatePasswordForgotUrl' => private_portal_url('password_forgot'),
            'privateMfaEnabled' => (bool) app_config('private.mfa_totp_enabled', false),
        ]);
    }

    private function handleDashboard(Request $request): Response
    {
        $guard = $this->guard();
        $required = $guard->requireAuthenticated($request, private_portal_url('login'));
        if ($required !== null) {
            return $required;
        }

        $identifier = $this->auth->currentIdentifier();
        $privateModules = [];
        $privateModuleDataCounts = [];
        $userId = $this->currentPrivateUserId();
        if ($userId !== null) {
            $privateModules = $this->privateModuleNamesForUser($userId);
            $privateModuleDataCounts = $this->modulePermissionRepository()->moduleDataCountsForUser($userId);
        }

        $query = $request->query();
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : null;
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : null;

        return $this->render('dashboard', [
            'privatePageTitle' => $this->translate('TXT_PRIVATE_DASHBOARD_TITLE', 'Tableau de bord privé'),
            'privateUserIdentifier' => is_string($identifier) ? $identifier : '',
            'privateModules' => $privateModules,
            'privateModuleDataCounts' => $privateModuleDataCounts,
            'notice' => match ($notice) {
                'document_uploaded' => $this->translate('TXT_PRIVATE_DOCUMENT_UPLOAD_SUCCESS', 'Document envoyé.'),
                'document_quarantined' => $this->translate('TXT_PRIVATE_DOCUMENT_QUARANTINED', 'Document reçu, mais bloqué par le contrôle antivirus.'),
                'document_scan_unavailable' => $this->translate('TXT_PRIVATE_DOCUMENT_SCAN_UNAVAILABLE', 'Document reçu, mais indisponible tant que le contrôle antivirus n’est pas disponible.'),
                'document_deleted' => $this->translate('TXT_PRIVATE_DOCUMENT_DELETE_SUCCESS', 'Document supprimé.'),
                'document_category_created' => $this->translate('TXT_PRIVATE_DOCUMENT_CATEGORY_CREATE_SUCCESS', 'Catégorie créée.'),
                default => null,
            },
            'errorMessage' => match ($error) {
                'upload_failed' => $this->translate('TXT_PRIVATE_DOCUMENT_UPLOAD_ERROR', 'L’envoi du document a échoué.'),
                'upload_forbidden' => $this->translate('TXT_PRIVATE_DOCUMENT_UPLOAD_FORBIDDEN', 'Vous n’avez pas le droit d’ajouter des documents.'),
                'missing_file' => $this->translate('TXT_PRIVATE_DOCUMENT_UPLOAD_MISSING_FILE', 'Aucun fichier reçu.'),
                'delete_forbidden' => $this->translate('TXT_PRIVATE_DOCUMENT_DELETE_FORBIDDEN', 'Vous n’avez pas le droit de supprimer ce document.'),
                'delete_not_found' => $this->translate('TXT_PRIVATE_DOCUMENT_NOT_FOUND', 'Document introuvable.'),
                'category_failed' => $this->translate('TXT_PRIVATE_DOCUMENT_CATEGORY_CREATE_ERROR', 'La catégorie n’a pas pu être créée.'),
                'invalid_request' => $this->translate('TXT_PRIVATE_ERROR_CSRF', 'Requête invalide.'),
                default => null,
            },
            'privateDashboardLogoutUrl' => private_portal_url('logout'),
            'privateLogoutCsrfToken' => csrf_token('private_logout'),
            'privatePasswordForgotUrl' => private_portal_url('password_forgot'),
        ]);
    }

    private function handleMemberSettings(Request $request): Response
    {
        $userId = $this->requireAuthenticatedUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $repository = $this->privateUserRepository();
        $profile = $repository->profileForUser($userId);
        if (!is_array($profile)) {
            return $this->redirect(private_portal_url('login'));
        }

        $query = $request->query();
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : '';
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : '';
        $tab = is_string($query['tab'] ?? null) ? (string) $query['tab'] : 'profile';
        $formValues = [
            'email' => (string) $profile['email'],
            'fullName' => (string) $profile['fullName'],
            'postalAddress' => (string) $profile['postalAddress'],
            'phone' => (string) $profile['phone'],
        ];
        $smtpSettings = $this->privateUserMailSettingsRepository()->settingsForUser($userId);
        if ((string) ($smtpSettings['fromAddress'] ?? '') === '') {
            $smtpSettings['fromAddress'] = (string) $profile['email'];
        }
        if ((string) ($smtpSettings['replyTo'] ?? '') === '') {
            $smtpSettings['replyTo'] = (string) $profile['email'];
        }
        if ((string) ($smtpSettings['testRecipient'] ?? '') === '') {
            $smtpSettings['testRecipient'] = (string) $profile['email'];
        }

        if ($request->method() !== self::METHOD_POST) {
            return $this->renderMemberSettings($userId, $formValues, $smtpSettings, $notice, $error, $tab);
        }

        if (!$this->guard()->validateCsrf($request, self::CSRF_MEMBER_SETTINGS)) {
            return $this->renderMemberSettings($userId, $formValues, $smtpSettings, '', 'invalid_request', $tab);
        }

        $body = $request->body();
        $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : 'profile';
        if ($action === 'smtp_settings') {
            $result = $this->privateUserMailSettingsRepository()->saveForUser($userId, $body);
            $settings = is_array($result['settings'] ?? null) ? $result['settings'] : $smtpSettings;
            if ((string) ($settings['fromAddress'] ?? '') === '') {
                $settings['fromAddress'] = (string) $profile['email'];
            }
            if ((string) ($settings['replyTo'] ?? '') === '') {
                $settings['replyTo'] = (string) $profile['email'];
            }
            if ((string) ($settings['testRecipient'] ?? '') === '') {
                $settings['testRecipient'] = (string) $profile['email'];
            }
            if (empty($result['success'])) {
                return $this->renderMemberSettings($userId, $formValues, $settings, '', (string) ($result['error'] ?? 'save_failed'), 'smtp');
            }

            $noticeKey = 'smtp_saved';
            if (!empty($body['send_test'])) {
                $recipient = $this->normalizeEmailInput($body['test_recipient'] ?? null);
                if ($recipient === '') {
                    $recipient = (string) $profile['email'];
                }
                $settings['testRecipient'] = $recipient;
                $sent = $this->sendPrivateMail(
                    $recipient,
                    'Test SMTP espace privé',
                    '<p>Message de test SMTP de l’espace privé.</p>',
                    [],
                    $userId,
                    true
                );
                $noticeKey = $sent ? 'smtp_test_sent' : '';
                if (!$sent) {
                    $errorKey = $this->lastPrivateMailFailure === 'smtp_required'
                        ? 'smtp_required'
                        : 'smtp_test_failed';

                    return $this->renderMemberSettings($userId, $formValues, $settings, '', $errorKey, 'smtp');
                }
            }

            $this->logEvent('private.member_settings.smtp_saved', ['private_user_id' => $userId]);

            return $this->redirect(private_portal_url('member_settings') . '?tab=smtp&notice=' . $noticeKey);
        }

        $normalized = $repository->normalizeProfile(
            is_string($body['full_name'] ?? null) ? (string) $body['full_name'] : '',
            is_string($body['postal_address'] ?? null) ? (string) $body['postal_address'] : '',
            is_string($body['phone'] ?? null) ? (string) $body['phone'] : ''
        );
        $formValues = [
            'email' => (string) $profile['email'],
            'fullName' => $normalized['fullName'],
            'postalAddress' => $normalized['postalAddress'],
            'phone' => $normalized['phone'],
        ];

        if ($normalized['errors'] !== []) {
            $error = in_array('phone_invalid', $normalized['errors'], true) ? 'phone_invalid' : 'invalid_request';

            return $this->renderMemberSettings($userId, $formValues, $smtpSettings, '', $error, 'profile');
        }

        if (!$repository->updateMemberProfile($userId, $formValues['fullName'], $formValues['postalAddress'], $formValues['phone'])) {
            return $this->renderMemberSettings($userId, $formValues, $smtpSettings, '', 'save_failed', 'profile');
        }

        $this->logEvent('private.member_settings.saved', ['private_user_id' => $userId]);

        return $this->redirect(private_portal_url('member_settings') . '?tab=profile&notice=profile_saved');
    }

    private function handleDocuments(Request $request): Response
    {
        $userId = $this->requireDocumentsModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $query = $request->query();
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : null;
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : null;
        $privateModules = $this->privateModuleNamesForUser($userId);
        $rentalDocuments = [];
        $rentalDocumentsEnabled = $this->modulePermissionRepository()->userHasModuleAccess($userId, 'real_estate_rental');
        if ($rentalDocumentsEnabled) {
            $propertyIds = $this->authorizedPropertyIds($userId);
            $rentalDocuments = $propertyIds === []
                ? []
                : $this->rentalLifecycleRepository()->listDocuments($propertyIds, self::MAX_RENTAL_LIST);
        }

        return $this->render('modules/documents/index', [
            'privatePageTitle' => $this->translate('TXT_PRIVATE_DASHBOARD_DOCUMENTS_TITLE', 'Documents'),
            'privateUserIdentifier' => is_string($this->auth->currentIdentifier()) ? (string) $this->auth->currentIdentifier() : '',
            'privateModules' => $privateModules,
            'privateDocumentsEnabled' => true,
            'privateDocuments' => $this->privateDocumentRepository()->listActiveByUser($userId, self::MAX_DOCUMENT_LIST),
            'privateRentalDocumentsEnabled' => $rentalDocumentsEnabled,
            'privateRentalDocuments' => $rentalDocuments,
            'privateDocumentCategories' => $this->privateDocumentRepository()->listCategoriesForUser($userId),
            'privateDocumentCategoryColors' => PrivateDocumentRepository::CATEGORY_COLORS,
            'privateDocumentCategoryDefaultColor' => PrivateDocumentRepository::DEFAULT_CATEGORY_COLOR,
            'privateDocumentUploadCsrfToken' => csrf_token(self::CSRF_DOCUMENTS),
            'privateDocumentsUploadUrl' => private_portal_url('files_upload'),
            'privateDocumentCategoriesUrl' => private_portal_url('files_categories'),
            'privateFilesBaseUrl' => private_portal_url('files'),
            'privateRentalFilesBaseUrl' => private_portal_url('rental_documents'),
            'notice' => match ($notice) {
                'document_uploaded' => $this->translate('TXT_PRIVATE_DOCUMENT_UPLOAD_SUCCESS', 'Document envoyé.'),
                'document_quarantined' => $this->translate('TXT_PRIVATE_DOCUMENT_QUARANTINED', 'Document reçu, mais bloqué par le contrôle antivirus.'),
                'document_scan_unavailable' => $this->translate('TXT_PRIVATE_DOCUMENT_SCAN_UNAVAILABLE', 'Document reçu, mais indisponible tant que le contrôle antivirus n’est pas disponible.'),
                'document_deleted' => $this->translate('TXT_PRIVATE_DOCUMENT_DELETE_SUCCESS', 'Document supprimé.'),
                'document_category_created' => $this->translate('TXT_PRIVATE_DOCUMENT_CATEGORY_CREATE_SUCCESS', 'Catégorie créée.'),
                'document_category_saved' => 'Catégorie enregistrée.',
                'document_category_deleted' => 'Catégorie supprimée. Les documents rattachés passent en sans catégorie.',
                default => null,
            },
            'errorMessage' => match ($error) {
                'upload_failed' => $this->translate('TXT_PRIVATE_DOCUMENT_UPLOAD_ERROR', 'L’envoi du document a échoué.'),
                'upload_forbidden' => $this->translate('TXT_PRIVATE_DOCUMENT_UPLOAD_FORBIDDEN', 'Vous n’avez pas le droit d’ajouter des documents.'),
                'missing_file' => $this->translate('TXT_PRIVATE_DOCUMENT_UPLOAD_MISSING_FILE', 'Aucun fichier reçu.'),
                'delete_forbidden' => $this->translate('TXT_PRIVATE_DOCUMENT_DELETE_FORBIDDEN', 'Vous n’avez pas le droit de supprimer ce document.'),
                'delete_not_found' => $this->translate('TXT_PRIVATE_DOCUMENT_NOT_FOUND', 'Document introuvable.'),
                'category_failed' => $this->translate('TXT_PRIVATE_DOCUMENT_CATEGORY_CREATE_ERROR', 'La catégorie n’a pas pu être créée.'),
                'category_delete_failed' => 'La catégorie n’a pas pu être supprimée.',
                'invalid_request' => $this->translate('TXT_PRIVATE_ERROR_CSRF', 'Requête invalide.'),
                default => null,
            },
            'privateDashboardLogoutUrl' => private_portal_url('logout'),
            'privateLogoutCsrfToken' => csrf_token('private_logout'),
        ]);
    }

    private function handleBlocNote(Request $request): Response
    {
        $userId = $this->requireBlocNoteModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $repository = $this->blocNoteRepository();
        $defaultCategoryId = $repository->ensureDefaultCategory($userId);
        $query = $request->query();
        $view = $this->resolveBlocNoteView(is_string($query['view'] ?? null) ? (string) $query['view'] : 'dashboard');
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : null;
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : null;
        $formValues = $this->blocNoteDefaultFormValues($defaultCategoryId);

        $editingNoteId = $this->normalizeNumericId($query['note_id'] ?? null);
        if ($editingNoteId > 0) {
            $note = $repository->findNote($editingNoteId, $userId);
            if (is_array($note)) {
                $view = 'form';
                $formValues = $this->blocNoteFormValuesFromNote($note, $defaultCategoryId);
            } else {
                $error = 'note_not_found';
            }
        }

        if ($request->method() !== self::METHOD_POST) {
            return $this->renderBlocNote($userId, $repository, $view, $formValues, $notice, $error);
        }

        if (!$this->guard()->validateCsrf($request, self::CSRF_BLOCNOTE)) {
            return $this->renderBlocNote($userId, $repository, $view, $formValues, null, 'invalid_request');
        }

        $body = $request->body();
        $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : '';

        if ($action === 'save_note') {
            $formValues = $this->blocNoteFormValuesFromBody($body, $defaultCategoryId);
            if ($repository->saveNote($userId, $formValues)) {
                $this->logEvent('private.blocnote.note.saved', [
                    'private_user_id' => $userId,
                    'note_id' => (int) $formValues['note_id'],
                ]);

                return $this->redirect($this->blocNoteUrl(['view' => 'notes', 'notice' => 'note_saved']));
            }

            return $this->renderBlocNote($userId, $repository, 'form', $formValues, null, 'note_required');
        }

        if ($action === 'delete_note') {
            $noteId = $this->normalizeNumericId($body['note_id'] ?? $body['delete_note'] ?? null);
            if ($repository->deleteNote($noteId, $userId)) {
                $this->logEvent('private.blocnote.note.deleted', [
                    'private_user_id' => $userId,
                    'note_id' => $noteId,
                ]);

                return $this->redirect($this->blocNoteUrl(['view' => 'notes', 'notice' => 'note_deleted']));
            }

            return $this->redirect($this->blocNoteUrl(['view' => 'notes', 'error' => 'note_delete_failed']));
        }

        if ($action === 'save_category') {
            $categoryId = $this->normalizeNumericId($body['category_id'] ?? null);
            $name = is_string($body['category_name'] ?? null) ? (string) $body['category_name'] : '';
            $color = is_string($body['category_color'] ?? null) ? (string) $body['category_color'] : BlocNoteRepository::DEFAULT_COLOR;
            if ($repository->saveCategory($userId, $categoryId, $name, $color)) {
                $this->logEvent('private.blocnote.category.saved', [
                    'private_user_id' => $userId,
                    'category_id' => $categoryId,
                ]);

                return $this->redirect($this->blocNoteUrl(['view' => 'categories', 'notice' => 'category_saved']));
            }

            return $this->redirect($this->blocNoteUrl(['view' => 'categories', 'error' => 'category_failed']));
        }

        if ($action === 'set_default_category') {
            $categoryId = $this->normalizeNumericId($body['category_id'] ?? null);
            if ($repository->setDefaultCategory($userId, $categoryId)) {
                return $this->redirect($this->blocNoteUrl(['view' => 'categories', 'notice' => 'default_category_saved']));
            }

            return $this->redirect($this->blocNoteUrl(['view' => 'categories', 'error' => 'category_failed']));
        }

        if ($action === 'delete_category') {
            $categoryId = $this->normalizeNumericId($body['category_id'] ?? null);
            if ($repository->deleteCategory($userId, $categoryId)) {
                $this->logEvent('private.blocnote.category.deleted', [
                    'private_user_id' => $userId,
                    'category_id' => $categoryId,
                ]);

                return $this->redirect($this->blocNoteUrl(['view' => 'categories', 'notice' => 'category_deleted']));
            }

            return $this->redirect($this->blocNoteUrl(['view' => 'categories', 'error' => 'category_delete_failed']));
        }

        return $this->renderBlocNote($userId, $repository, $view, $formValues, null, 'invalid_request');
    }

    private function handleRentalDashboard(Request $request): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        return $this->renderRentalDashboard($userId);
    }

    private function handleRentalLessors(Request $request): Response
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
            return $this->renderRentalLessors($userId, $notice, $error);
        }

        if (!$this->guard()->validateCsrf($request, self::CSRF_RENTAL)) {
            return $this->renderRentalLessors($userId, '', 'rental_invalid_request');
        }

        $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : '';
        $lessorId = $this->normalizeNumericId($body['lessor_id'] ?? null);

        if ($action === 'create_lessor') {
            $created = $this->rentalLessorRepository()->create(
                $userId,
                (string) ($body['last_name'] ?? ''),
                (string) ($body['first_name'] ?? ''),
                (string) ($body['address'] ?? ''),
                (string) ($body['phone'] ?? ''),
                (string) ($body['email'] ?? '')
            );

            if (!is_array($created)) {
                return $this->renderRentalLessors($userId, '', 'lessor_write_failed');
            }

            $this->logEvent('private.rental_lessor.created', [
                'private_user_id' => $userId,
                'rental_lessor_id' => (int) ($created['id'] ?? 0),
            ]);

            return $this->redirect(private_portal_url('rental_lessors') . '?notice=lessor_created');
        }

        if ($action === 'update_lessor' && $lessorId > 0) {
            $updated = $this->rentalLessorRepository()->update(
                $lessorId,
                $userId,
                (string) ($body['last_name'] ?? ''),
                (string) ($body['first_name'] ?? ''),
                (string) ($body['address'] ?? ''),
                (string) ($body['phone'] ?? ''),
                (string) ($body['email'] ?? '')
            );

            if (!is_array($updated)) {
                return $this->renderRentalLessors($userId, '', 'lessor_write_failed');
            }

            $this->logEvent('private.rental_lessor.updated', [
                'private_user_id' => $userId,
                'rental_lessor_id' => $lessorId,
            ]);

            return $this->redirect(private_portal_url('rental_lessors') . '?notice=lessor_updated');
        }

        if ($action === 'delete_lessor' && $lessorId > 0) {
            if (!$this->rentalLessorRepository()->deleteForUser($lessorId, $userId)) {
                return $this->renderRentalLessors($userId, '', 'lessor_delete_failed');
            }

            $this->logEvent('private.rental_lessor.deleted', [
                'private_user_id' => $userId,
                'rental_lessor_id' => $lessorId,
            ]);

            return $this->redirect(private_portal_url('rental_lessors') . '?notice=lessor_deleted');
        }

        return $this->renderRentalLessors($userId, '', 'rental_invalid_request');
    }

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
        $lessorId = $this->normalizeNumericId($body['rental_lessor_id'] ?? null);
        if ($lessorId > 0 && !is_array($this->rentalLessorRepository()->findForUser($lessorId, $userId))) {
            return $this->renderRentalProperties($userId, '', 'lessor_forbidden');
        }
        $createDefaultUnit = isset($body['create_default_unit']) && (int) $body['create_default_unit'] === 1;
        $defaultUnitSurface = is_numeric($body['default_unit_surface'] ?? null)
            ? (float) $body['default_unit_surface']
            : 0.0;
        $defaultUnitFurnished = isset($body['default_unit_furnished']) && (int) $body['default_unit_furnished'] === 1;

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
                $lessorId > 0 ? $lessorId : null
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
                $lessorId > 0 ? $lessorId : null
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
        $unitUnavailableUntil = is_string($body['unavailable_until'] ?? null) ? (string) $body['unavailable_until'] : null;
        $unitDetails = $this->rentalUnitDetailsFromBody($body);

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
                $unitUnavailableUntil
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
                $unitUnavailableUntil
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

        $memberUser = $this->privateUserRepository()->findByEmail($email);
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

            $propertyId = $this->normalizeNumericId($body['rental_property_id'] ?? null);
            if (!in_array($propertyId, $propertyIds, true) || !$this->canWriteByPropertyId($propertyId, $userId)) {
                return $this->renderRentalTenants($properties, $units, $tenants, '', 'property_forbidden');
            }

            $updated = $this->rentalLifecycleRepository()->updateTenant(
                $tenantId,
                $propertyId,
                null,
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

        $propertyId = $this->normalizeNumericId($body['rental_property_id'] ?? null);
        if (!in_array($propertyId, $propertyIds, true) || !$this->canWriteByPropertyId($propertyId, $userId)) {
            return $this->renderRentalTenants($properties, $units, $tenants, '', 'property_forbidden');
        }

        $created = $this->rentalLifecycleRepository()->createTenant(
            $propertyId,
            null,
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
            'rental_tenant_id' => (int) ($created['id'] ?? 0),
        ]);

        return $this->redirect(private_portal_url('rental_tenants') . '?notice=tenant_created');
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function rentalTenantDetailsFromBody(array $body): array
    {
        return [
            'last_name' => $this->stringBodyValue($body, 'last_name'),
            'first_names' => $this->stringBodyValue($body, 'first_names'),
            'birth_date' => $this->stringBodyValue($body, 'birth_date'),
            'birth_city' => $this->stringBodyValue($body, 'birth_city'),
            'birth_country' => $this->stringBodyValue($body, 'birth_country'),
            'nationality' => $this->stringBodyValue($body, 'nationality'),
            'occupation' => $this->stringBodyValue($body, 'occupation'),
            'postal_address' => $this->stringBodyValue($body, 'postal_address'),
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function rentalTenantFullNameFromBody(array $body): string
    {
        $fromParts = trim($this->stringBodyValue($body, 'last_name') . ' ' . $this->stringBodyValue($body, 'first_names'));
        if ($fromParts !== '') {
            return $fromParts;
        }

        return $this->stringBodyValue($body, 'full_name');
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function rentalUnitDetailsFromBody(array $body): array
    {
        return [
            'tax_identifier' => $this->stringBodyValue($body, 'tax_identifier'),
            'room_count' => $this->stringBodyValue($body, 'room_count'),
            'designation' => $this->stringBodyValue($body, 'designation'),
            'other_details' => $this->stringBodyValue($body, 'other_details'),
            'equipment_elements' => $this->stringBodyValue($body, 'equipment_elements'),
            'heating_production_mode' => $this->stringBodyValue($body, 'heating_production_mode'),
            'hot_water_production_mode' => $this->stringBodyValue($body, 'hot_water_production_mode'),
            'sanitation' => $this->stringBodyValue($body, 'sanitation'),
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function stringBodyValue(array $body, string $key): string
    {
        return is_scalar($body[$key] ?? null) ? trim((string) $body[$key]) : '';
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
        if ($action === 'download_lease') {
            $response = $this->downloadRentalLeasePdf($body, $propertyIds, $userId);
            if ($response instanceof Response) {
                return $response;
            }

            return $this->renderRentalLeases($properties, $units, $tenants, $leases, '', 'rental_write_failed');
        }

        if ($action === 'upload_lease_document') {
            $leaseId = $this->normalizeNumericId($body['lease_id'] ?? null);
            $lease = $this->rentalLifecycleRepository()->findLeaseById($leaseId);
            $leasePropertyId = is_array($lease) && is_numeric($lease['rentalPropertyId'] ?? null)
                ? (int) $lease['rentalPropertyId']
                : 0;
            $leaseUnitId = is_array($lease) && is_numeric($lease['rentalUnitId'] ?? null)
                ? (int) $lease['rentalUnitId']
                : 0;
            if ($leaseId <= 0 || $leaseUnitId <= 0 || !in_array($leasePropertyId, $propertyIds, true) || !$this->canWriteByPropertyId($leasePropertyId, $userId)) {
                return $this->renderRentalLeases($properties, $units, $tenants, $leases, '', 'property_forbidden');
            }

            $files = $request->files();
            $uploadedFile = is_array($files[self::RENTAL_DOCUMENT_UPLOAD_FIELD] ?? null)
                ? $files[self::RENTAL_DOCUMENT_UPLOAD_FIELD]
                : null;
            if (!is_array($uploadedFile)) {
                return $this->renderRentalLeases($properties, $units, $tenants, $leases, '', 'missing_file');
            }

            if (!$this->storeRentalLeaseDocumentUpload($request, $body, $userId, $leaseId, $leasePropertyId, $leaseUnitId)) {
                return $this->renderRentalLeases($properties, $units, $tenants, $leases, '', 'upload_failed');
            }

            $this->logEvent('private.rental_lease.document_uploaded', [
                'private_user_id' => $userId,
                'rental_property_id' => $leasePropertyId,
                'rental_lease_id' => $leaseId,
            ]);

            return $this->redirect(private_portal_url('rental_leases') . '?notice=lease_document_uploaded');
        }

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
            if (!$this->unitBelongsToProperty($unitId, $propertyId) || !$this->tenantBelongsToProperty($tenantId, $propertyId)) {
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
        if (!$this->unitBelongsToProperty($unitId, $propertyId) || !$this->tenantBelongsToProperty($tenantId, $propertyId)) {
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
        $createdLeaseId = is_numeric($created['id'] ?? null) ? (int) $created['id'] : 0;
        if ($this->hasRentalLeaseDocumentUpload($request)) {
            if (!$this->storeRentalLeaseDocumentUpload($request, $body, $userId, $createdLeaseId, $propertyId, $unitId)) {
                $this->rentalLifecycleRepository()->deleteLease($createdLeaseId, [$propertyId]);
                return $this->renderRentalLeases($properties, $units, $tenants, $leases, '', 'upload_failed');
            }

            $this->logEvent('private.rental_lease.document_uploaded', [
                'private_user_id' => $userId,
                'rental_property_id' => $propertyId,
                'rental_lease_id' => $createdLeaseId,
            ]);
        }

        $this->logEvent('private.rental_lease.created', [
            'private_user_id' => $userId,
            'rental_property_id' => $propertyId,
            'rental_lease_id' => $createdLeaseId,
        ]);

        return $this->redirect(private_portal_url('rental_leases') . '?notice=lease_created');
    }

    private function hasRentalLeaseDocumentUpload(Request $request): bool
    {
        $files = $request->files();
        $uploadedFile = is_array($files[self::RENTAL_DOCUMENT_UPLOAD_FIELD] ?? null)
            ? $files[self::RENTAL_DOCUMENT_UPLOAD_FIELD]
            : null;
        if (!is_array($uploadedFile)) {
            return false;
        }

        $errorCode = is_numeric($uploadedFile['error'] ?? null) ? (int) $uploadedFile['error'] : UPLOAD_ERR_NO_FILE;
        return $errorCode !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function storeRentalLeaseDocumentUpload(
        Request $request,
        array $body,
        int $userId,
        int $leaseId,
        int $propertyId,
        int $unitId
    ): bool {
        if ($userId <= 0 || $leaseId <= 0 || $propertyId <= 0 || $unitId <= 0) {
            return false;
        }

        $files = $request->files();
        $uploadedFile = is_array($files[self::RENTAL_DOCUMENT_UPLOAD_FIELD] ?? null)
            ? $files[self::RENTAL_DOCUMENT_UPLOAD_FIELD]
            : null;
        if (!is_array($uploadedFile)) {
            return false;
        }

        $storage = $this->privateDocumentStorage();
        $metadata = $storage->validateUploadedFile($uploadedFile);
        $documentId = $storage->generateDocumentId();
        $stored = $metadata !== null && $documentId !== '' ? $storage->storeUploadedFile($metadata, $documentId) : null;
        if (!is_array($stored)) {
            return false;
        }

        $created = $this->rentalLifecycleRepository()->createDocument(
            $propertyId,
            $unitId,
            $leaseId,
            (string) $stored['documentId'],
            (string) $stored['storagePath'],
            (string) $stored['originalName'],
            (string) $stored['extension'],
            (string) $stored['mimeType'],
            (int) $stored['sizeBytes'],
            $userId,
            null,
            is_string($body['document_name'] ?? null) ? (string) $body['document_name'] : null,
            'Baux'
        );
        if (!is_array($created)) {
            $storage->deleteStoredDocument((string) $stored['storagePath'], (string) $stored['documentId']);
            return false;
        }

        return true;
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
        if ($action === 'send_payment_request') {
            $result = $this->sendRentalPaymentRequest($body, $propertyIds, $userId);
            if ($result === 'sent' || $result === 'duplicate') {
                return $this->redirect(private_portal_url('rental_rents') . '?notice=payment_request_sent');
            }
            if ($result === 'smtp_required') {
                return $this->redirect($this->privateSmtpSettingsRequiredUrl());
            }

            return $this->redirect(private_portal_url('rental_rents') . '?error=' . $result);
        }

        if ($action === 'download_payment_request_pdf') {
            $response = $this->downloadRentalPaymentRequestPdf($body, $propertyIds, $userId);
            if ($response instanceof Response) {
                return $response;
            }

            return $this->redirect(private_portal_url('rental_rents') . '?error=rental_write_failed');
        }

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
        if ($action === 'download_receipt') {
            $response = $this->downloadRentalReceiptPdf($body, $propertyIds, $userId);
            if ($response instanceof Response) {
                return $response;
            }

            return $this->renderRentalPayments($rents, $payments, '', 'rental_write_failed', $prefillRentId);
        }

        if ($action === 'email_receipt') {
            $sent = $this->sendRentalReceiptByEmail($body, $propertyIds, $userId);
            if (!$sent && $this->lastPrivateMailFailure === 'smtp_required') {
                return $this->redirect($this->privateSmtpSettingsRequiredUrl());
            }

            return $this->redirect(private_portal_url('rental_payments') . ($sent ? '?notice=receipt_emailed' : '?error=email_failed'));
        }

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

        $uploadedFile = $this->optionalRentalUploadedFile($request);
        if (is_array($uploadedFile)) {
            $expenseId = is_numeric($created['id'] ?? null) ? (int) $created['id'] : 0;
            if ($expenseId <= 0 || !$this->storeRentalSupportingDocument($uploadedFile, $propertyId, $unitId, null, $expenseId, $userId)) {
                return $this->renderRentalExpenses($properties, $units, $expenses, '', 'upload_failed');
            }
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
        $documents = $this->rentalLifecycleRepository()->listDocuments($propertyIds, self::MAX_RENTAL_LIST);
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

        if ($action === 'email_documents') {
            $sent = $this->sendRentalDocumentsByEmail($body, $propertyIds, $userId);
            if (!$sent && $this->lastPrivateMailFailure === 'smtp_required') {
                return $this->redirect($this->privateSmtpSettingsRequiredUrl());
            }

            return $this->redirect(private_portal_url('rental_documents') . ($sent ? '?notice=document_emailed' : '?error=email_failed'));
        }

        if ($action === 'purge_rental_data') {
            $confirmation = is_string($body['confirm_purge'] ?? null) ? trim((string) $body['confirm_purge']) : '';
            if ($confirmation !== 'SUPPRIMER') {
                return $this->renderRentalDocuments($properties, $units, $leases, $documents, '', 'rental_purge_confirmation_required');
            }

            $documentsBeforeDelete = $documents;
            $deleted = $this->rentalLifecycleRepository()->deleteLifecycleDataByPropertyIds($propertyIds);
            foreach ($documentsBeforeDelete as $document) {
                if (!is_array($document)) {
                    continue;
                }
                $this->privateDocumentStorage()->deleteStoredDocument(
                    is_string($document['storagePath'] ?? null) ? (string) $document['storagePath'] : '',
                    is_string($document['documentId'] ?? null) ? (string) $document['documentId'] : null
                );
            }

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
        $documentName = is_string($body['document_name'] ?? null) ? (string) $body['document_name'] : null;
        if (!$this->canWriteByPropertyId($propertyId, $userId)) {
            return $this->renderRentalDocuments($properties, $units, $leases, $documents, '', 'property_forbidden');
        }
        if ($unitId > 0 && !$this->unitBelongsToProperty($unitId, $propertyId)) {
            return $this->renderRentalDocuments($properties, $units, $leases, $documents, '', 'rental_invalid_request');
        }
        if ($leaseId > 0 && !$this->leaseBelongsToProperty($leaseId, $propertyId)) {
            return $this->renderRentalDocuments($properties, $units, $leases, $documents, '', 'rental_invalid_request');
        }
        if ($leaseId > 0 && $unitId > 0 && !$this->leaseBelongsToUnit($leaseId, $unitId, $propertyId)) {
            return $this->renderRentalDocuments($properties, $units, $leases, $documents, '', 'rental_invalid_request');
        }

        $files = $request->files();
        $uploadedFile = is_array($files[self::RENTAL_DOCUMENT_UPLOAD_FIELD] ?? null)
            ? $files[self::RENTAL_DOCUMENT_UPLOAD_FIELD]
            : null;
        if (!is_array($uploadedFile)) {
            return $this->renderRentalDocuments($properties, $units, $leases, $documents, '', 'missing_file');
        }

        $storage = $this->privateDocumentStorage();
        $metadata = $storage->validateUploadedFile($uploadedFile);
        $documentId = $storage->generateDocumentId();
        $stored = $metadata !== null && $documentId !== '' ? $storage->storeUploadedFile($metadata, $documentId) : null;
        if (!is_array($stored)) {
            return $this->renderRentalDocuments($properties, $units, $leases, $documents, '', 'upload_failed');
        }

        $created = $this->rentalLifecycleRepository()->createDocument(
            $propertyId,
            $unitId > 0 ? $unitId : null,
            $leaseId > 0 ? $leaseId : null,
            (string) $stored['documentId'],
            (string) $stored['storagePath'],
            (string) $stored['originalName'],
            (string) $stored['extension'],
            (string) $stored['mimeType'],
            (int) $stored['sizeBytes'],
            $userId,
            null,
            $documentName,
            $leaseId > 0 ? 'Baux' : 'Document'
        );
        if (!is_array($created)) {
            $storage->deleteStoredDocument((string) $stored['storagePath'], (string) $stored['documentId']);
            return $this->renderRentalDocuments($properties, $units, $leases, $documents, '', 'upload_failed');
        }

        $this->logEvent('private.rental_document.uploaded', [
            'private_user_id' => $userId,
            'rental_property_id' => $propertyId,
            'document_id' => (string) $stored['documentId'],
        ]);

        return $this->redirect(private_portal_url('rental_documents') . '?notice=document_uploaded');
    }

    private function handleRentalAgencies(Request $request): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $query = $request->query();
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : '';
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : '';

        if ($request->method() !== self::METHOD_POST) {
            return $this->renderRentalAgencies($userId, $notice, $error);
        }

        if (!$this->guard()->validateCsrf($request, self::CSRF_RENTAL)) {
            return $this->renderRentalAgencies($userId, '', 'rental_invalid_request');
        }

        $body = $request->body();
        $action = is_string($body['action'] ?? null) ? trim((string) $body['action']) : '';
        if ($action === 'create_agency') {
            $agencyName = is_string($body['agency_name'] ?? null) ? trim((string) $body['agency_name']) : '';
            $created = $this->agencyImportRepository()->createAgency($userId, $agencyName, $this->agencyDetailsFromBody($body));
            $this->logEvent('private.rental_agency.agency_created', [
                'private_user_id' => $userId,
                'agency_name' => $agencyName,
                'success' => $created,
            ]);

            return $this->redirect($this->rentalAgenciesUrl(
                $created ? 'agency_created' : '',
                $created ? '' : 'agency_create_failed'
            ));
        }

        if ($action === 'update_agency') {
            $agencyId = $this->normalizeNumericId($body['agency_id'] ?? null);
            $agencyName = is_string($body['agency_name'] ?? null) ? trim((string) $body['agency_name']) : '';
            $updated = $this->agencyImportRepository()->updateAgencyForUser(
                $userId,
                $agencyId,
                $agencyName,
                $this->agencyDetailsFromBody($body)
            );
            $this->logEvent('private.rental_agency.agency_updated', [
                'private_user_id' => $userId,
                'agency_id' => $agencyId,
                'agency_name' => $agencyName,
                'success' => $updated,
            ]);

            return $this->redirect($this->rentalAgenciesUrl(
                $updated ? 'agency_updated' : '',
                $updated ? '' : 'agency_update_failed'
            ));
        }

        if ($action === 'delete_agency') {
            $agencyId = $this->normalizeNumericId($body['agency_id'] ?? null);
            $deleted = $this->agencyImportRepository()->deleteAgencyForUser($userId, $agencyId);
            $this->logEvent('private.rental_agency.agency_deleted', [
                'private_user_id' => $userId,
                'agency_id' => $agencyId,
                'success' => $deleted,
            ]);

            return $this->redirect($this->rentalAgenciesUrl(
                $deleted ? 'agency_deleted' : '',
                $deleted ? '' : 'agency_delete_failed'
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
            $this->logEvent('private.rental_agency.unit_mapping_created', [
                'private_user_id' => $userId,
                'agency_name' => $agencyName,
                'match_text' => $matchText,
                'rental_property_id' => $propertyId,
                'rental_unit_id' => $unitId,
                'success' => $created,
            ]);

            return $this->redirect($this->rentalAgenciesUrl(
                $created ? 'agency_unit_mapping_created' : '',
                $created ? '' : 'agency_unit_mapping_failed'
            ));
        }

        if ($action === 'delete_agency_unit_mapping') {
            $mappingId = $this->normalizeNumericId($body['agency_unit_mapping_id'] ?? null);
            $deleted = $this->agencyImportRepository()->deleteUnitMappingForUser($userId, $mappingId);
            $this->logEvent('private.rental_agency.unit_mapping_deleted', [
                'private_user_id' => $userId,
                'agency_unit_mapping_id' => $mappingId,
                'success' => $deleted,
            ]);

            return $this->redirect($this->rentalAgenciesUrl(
                $deleted ? 'agency_unit_mapping_deleted' : '',
                $deleted ? '' : 'agency_unit_mapping_delete_failed'
            ));
        }

        return $this->renderRentalAgencies($userId, '', 'rental_invalid_request');
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
            $created = $this->agencyImportRepository()->createAgency($userId, $agencyName, $this->agencyDetailsFromBody($body));
            $this->logEvent('private.rental_agency_import.agency_created', [
                'private_user_id' => $userId,
                'agency_name' => $agencyName,
                'success' => $created,
            ]);

            return $this->redirect($this->rentalAgenciesUrl(
                $created ? 'agency_created' : '',
                $created ? '' : 'agency_create_failed'
            ));
        }

        if ($action === 'update_agency') {
            $agencyId = $this->normalizeNumericId($body['agency_id'] ?? null);
            $agencyName = is_string($body['agency_name'] ?? null) ? trim((string) $body['agency_name']) : '';
            $updated = $this->agencyImportRepository()->updateAgencyForUser(
                $userId,
                $agencyId,
                $agencyName,
                $this->agencyDetailsFromBody($body)
            );
            $this->logEvent('private.rental_agency_import.agency_updated', [
                'private_user_id' => $userId,
                'agency_id' => $agencyId,
                'agency_name' => $agencyName,
                'success' => $updated,
            ]);

            return $this->redirect($this->rentalAgenciesUrl(
                $updated ? 'agency_updated' : '',
                $updated ? '' : 'agency_update_failed'
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

            return $this->redirect($this->rentalAgenciesUrl(
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

            return $this->redirect($this->rentalAgenciesUrl(
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

            $this->deleteAgencyImportedDocumentFile($deletedDocument);
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

        $files = $request->files();
        $uploadedFile = is_array($files[self::AGENCY_IMPORT_UPLOAD_FIELD] ?? null)
            ? $files[self::AGENCY_IMPORT_UPLOAD_FIELD]
            : null;
        if (!is_array($uploadedFile)) {
            return $this->renderRentalAgencyImports($userId, '', 'missing_file', $tab);
        }

        $agencyName = is_string($body['agency_name'] ?? null) ? trim((string) $body['agency_name']) : null;
        if ($agencyName !== null && $agencyName !== '') {
            $this->agencyImportRepository()->createAgency($userId, $agencyName);
        }
        $documentType = is_string($body['document_type'] ?? null) ? trim((string) $body['document_type']) : null;
        $result = $this->agencyImportService()->importUploadedFile($userId, $uploadedFile, $agencyName, null, $documentType);
        if ($result->isImported()) {
            $this->logEvent('private.rental_agency_import.imported', [
                'private_user_id' => $userId,
                'agency_import_batch_id' => $result->batch?->id,
                'agency_imported_document_id' => $result->document?->id,
                'detected_document_type' => $result->document?->detectedDocumentType,
            ]);

            return $this->redirect($this->rentalAgencyImportsUrl('documents', 'agency_imported'));
        }

        if ($result->status === 'ignored') {
            return $this->redirect($this->rentalAgencyImportsUrl('documents', 'agency_import_ignored'));
        }

        if ($result->status === 'duplicate') {
            return $this->redirect($this->rentalAgencyImportsUrl('documents', '', 'agency_import_duplicate'));
        }

        if ($result->error !== null && str_starts_with($result->error, 'scan_')) {
            $this->logEvent('private.rental_agency_import.scan_blocked', [
                'private_user_id' => $userId,
                'agency_import_batch_id' => $result->batch?->id,
                'scan_error' => $result->error,
            ]);

            return $this->redirect($this->rentalAgencyImportsUrl(
                'documents',
                '',
                $result->error === 'scan_infected'
                    ? 'agency_import_scan_infected'
                    : 'agency_import_scan_unavailable'
            ));
        }

        return $this->renderRentalAgencyImports($userId, '', 'agency_import_failed');
    }

    private function agencyImportTab(mixed $value): string
    {
        $tab = is_scalar($value) ? trim((string) $value) : '';
        return in_array($tab, ['documents', 'imports'], true) ? $tab : 'documents';
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function agencyDetailsFromBody(array $body): array
    {
        return [
            'legal_name' => $body['legal_name'] ?? '',
            'contact_title' => $body['contact_title'] ?? '',
            'postal_address' => $body['postal_address'] ?? '',
            'phone' => $body['phone'] ?? '',
            'email' => $body['email'] ?? '',
            'advisor_name' => $body['advisor_name'] ?? '',
            'advisor_title' => $body['advisor_title'] ?? '',
            'advisor_phone' => $body['advisor_phone'] ?? '',
            'advisor_email' => $body['advisor_email'] ?? '',
            'notes' => $body['notes'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array{lineId: int, action: string, payload: array<string, mixed>}|null
     */
    private function submittedAgencyReviewLineAction(array $body): ?array
    {
        $actions = is_array($body['line_action'] ?? null) ? $body['line_action'] : [];
        $lines = is_array($body['lines'] ?? null) ? $body['lines'] : [];
        foreach ($actions as $lineIdKey => $submittedAction) {
            $lineId = $this->normalizeNumericId($lineIdKey);
            $action = is_scalar($submittedAction) ? strtolower(trim((string) $submittedAction)) : '';
            if ($lineId <= 0 || $action === '') {
                continue;
            }

            $linePayload = [];
            if (isset($lines[$lineIdKey]) && is_array($lines[$lineIdKey])) {
                $linePayload = $lines[$lineIdKey];
            } elseif (isset($lines[(string) $lineId]) && is_array($lines[(string) $lineId])) {
                $linePayload = $lines[(string) $lineId];
            }

            return [
                'lineId' => $lineId,
                'action' => $action,
                'payload' => $linePayload,
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{corrections: array<string, string>, error: string}
     */
    private function agencyReviewLineCorrectionsFromBody(array $payload, string $lineAction, int $userId): array
    {
        if (!in_array($lineAction, ['correct', 'validate'], true)) {
            return ['corrections' => [], 'error' => ''];
        }

        $mappedCategory = is_string($payload['mapped_category'] ?? null) ? (string) $payload['mapped_category'] : '';
        if (!array_key_exists($mappedCategory, $this->agencyReviewCategories())) {
            return ['corrections' => [], 'error' => 'rental_invalid_request'];
        }

        $linePropertyId = $this->normalizeNumericId($payload['rental_property_id'] ?? null);
        $lineUnitId = $this->normalizeNumericId($payload['rental_unit_id'] ?? null);
        if ($lineUnitId > 0) {
            $unit = $this->rentalUnitRepository()->findById($lineUnitId);
            $unitPropertyId = $unit !== null ? $unit->rentalPropertyId : 0;
            if (
                $unit === null
                || ($linePropertyId > 0 && $linePropertyId !== $unitPropertyId)
                || !$this->canWriteByPropertyId($unitPropertyId, $userId)
            ) {
                return ['corrections' => [], 'error' => 'rental_invalid_request'];
            }

            $linePropertyId = $unitPropertyId;
        }

        if ($linePropertyId > 0 && !$this->canWriteByPropertyId($linePropertyId, $userId)) {
            return ['corrections' => [], 'error' => 'property_forbidden'];
        }

        $manualFiscalReviewConfirmed = $this->agencyFiscalReviewPolicy()->isManualReviewConfirmed(
            $payload['manual_fiscal_review_confirmed'] ?? false
        );
        if (
            $lineAction === 'validate'
            && $this->agencyFiscalReviewPolicy()->requiresManualFiscalReview($mappedCategory)
            && !$manualFiscalReviewConfirmed
        ) {
            return ['corrections' => [], 'error' => 'manual_fiscal_review_required'];
        }

        return [
            'corrections' => [
                'rental_property_id' => $linePropertyId > 0 ? (string) $linePropertyId : '',
                'rental_unit_id' => $lineUnitId > 0 ? (string) $lineUnitId : '',
                'mapped_category' => $mappedCategory,
                'period_start' => is_string($payload['period_start'] ?? null) ? (string) $payload['period_start'] : '',
                'period_end' => is_string($payload['period_end'] ?? null) ? (string) $payload['period_end'] : '',
                'amount' => is_scalar($payload['amount'] ?? null) ? (string) $payload['amount'] : '',
                'debit_amount' => is_scalar($payload['debit_amount'] ?? null) ? (string) $payload['debit_amount'] : '',
                'credit_amount' => is_scalar($payload['credit_amount'] ?? null) ? (string) $payload['credit_amount'] : '',
                'manual_fiscal_review_confirmed' => $manualFiscalReviewConfirmed ? '1' : '',
            ],
            'error' => '',
        ];
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

    private function rentalAgenciesUrl(string $notice = '', string $error = ''): string
    {
        $query = [];
        if ($notice !== '') {
            $query['notice'] = $notice;
        }
        if ($error !== '') {
            $query['error'] = $error;
        }

        return private_portal_url('rental_agencies') . ($query === [] ? '' : '?' . http_build_query($query));
    }

    private function deleteAgencyImportedDocumentFile(AgencyImportedDocument $document): void
    {
        $storage = $this->privateDocumentStorage();
        $storagePath = $document->storagePath ?? null;
        $deleted = is_string($storagePath) && $storagePath !== ''
            ? $storage->deleteStoredDocument($storagePath, $document->privateDocumentId)
            : false;

        if (!$deleted && is_string($document->privateDocumentId) && $document->privateDocumentId !== '') {
            $storage->deleteStoredDocumentByDocumentId($document->privateDocumentId);
        }
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

        if ($action === 'bulk_update_lines') {
            $linesPayload = is_array($body['lines'] ?? null) ? $body['lines'] : [];
            $updatedCount = 0;
            $failedCount = 0;
            foreach ($linesPayload as $lineIdKey => $linePayload) {
                $lineId = $this->normalizeNumericId($lineIdKey);
                if ($lineId <= 0 || !is_array($linePayload)) {
                    continue;
                }

                $lineCorrections = $this->agencyReviewLineCorrectionsFromBody($linePayload, 'correct', $userId);
                if ($lineCorrections['error'] !== '') {
                    $failedCount++;
                    continue;
                }

                $line = $repository->reviewStatementLine($userId, $lineId, 'correct', $lineCorrections['corrections']);
                if ($line !== null) {
                    $updatedCount++;
                } else {
                    $failedCount++;
                }
            }

            $this->logEvent('private.rental_agency_review.lines_bulk_saved', [
                'private_user_id' => $userId,
                'agency_imported_document_id' => $documentId,
                'updated_count' => $updatedCount,
                'failed_count' => $failedCount,
            ]);

            return $this->redirect($this->rentalAgencyReviewUrl(
                $documentId,
                $updatedCount > 0 ? 'agency_lines_saved' : '',
                $updatedCount > 0 ? ($failedCount > 0 ? 'agency_lines_partial' : '') : 'agency_review_failed'
            ));
        }

        $lineSubmit = $this->submittedAgencyReviewLineAction($body);
        if ($lineSubmit !== null) {
            $lineId = (int) $lineSubmit['lineId'];
            $action = (string) $lineSubmit['action'];
            $lineBody = array_merge($body, is_array($lineSubmit['payload']) ? $lineSubmit['payload'] : []);
        } else {
            $lineId = $this->normalizeNumericId($body['line_id'] ?? null);
            $lineBody = $body;
        }

        $lineAction = match ($action) {
            'validate_line' => 'validate',
            'correct_line' => 'correct',
            'ignore_line' => 'ignore',
            default => '',
        };
        if ($lineAction === '') {
            return $this->redirect($this->rentalAgencyReviewUrl($documentId, '', 'rental_invalid_request'));
        }

        $lineCorrections = $this->agencyReviewLineCorrectionsFromBody($lineBody, $lineAction, $userId);
        if ($lineCorrections['error'] !== '') {
            if ($lineCorrections['error'] === 'manual_fiscal_review_required') {
                $this->logEvent('private.rental_agency_review.line_reviewed', [
                    'private_user_id' => $userId,
                    'agency_imported_document_id' => $documentId,
                    'agency_statement_line_id' => $lineId,
                    'action' => $lineAction,
                    'success' => false,
                    'reason' => 'manual_fiscal_review_required',
                ]);

                return $this->redirect($this->rentalAgencyReviewLineUrl(
                    $documentId,
                    $lineId,
                    '',
                    'agency_sensitive_review_required'
                ));
            }

            return $this->redirect($this->rentalAgencyReviewUrl($documentId, '', $lineCorrections['error']));
        }
        $line = $repository->reviewStatementLine($userId, $lineId, $lineAction, $lineCorrections['corrections']);
        $this->logEvent('private.rental_agency_review.line_reviewed', [
            'private_user_id' => $userId,
            'agency_imported_document_id' => $documentId,
            'agency_statement_line_id' => $lineId,
            'action' => $lineAction,
            'success' => $line !== null,
        ]);

        $lineNotice = match ($lineAction) {
            'validate' => 'agency_line_validated',
            'correct' => 'agency_line_corrected',
            'ignore' => 'agency_line_ignored',
        };

        return $this->redirect($this->rentalAgencyReviewLineUrl(
            $documentId,
            $lineId,
            $line !== null ? $lineNotice : '',
            $line !== null ? '' : 'agency_review_failed'
        ));
    }

    private function handleRentalDocumentFile(Request $request, string $documentId): Response
    {
        $userId = $this->requireRentalModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $documentId = $this->normalizeDocumentId($documentId);
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

    private function handleTaxDashboard(Request $request): Response
    {
        $userId = $this->requireTaxModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        unset($request);

        return $this->render('modules/tax-declaration-helper/dashboard', [
            'privatePageTitle' => 'Aide impôts',
            'taxYears' => $this->taxDeclarationRepository()->listYearsForUser($userId),
            'taxCurrentYear' => (int) date('Y'),
            'taxBaseUrl' => private_portal_url('tax_dashboard'),
            'privateDashboardLogoutUrl' => private_portal_url('logout'),
            'privateLogoutCsrfToken' => csrf_token('private_logout'),
        ]);
    }

    private function handleTaxYear(Request $request, int $year): Response
    {
        $userId = $this->requireTaxModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $year = $this->normalizeTaxYear($year);
        $query = $request->query();
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : '';
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : '';

        if ($request->method() === self::METHOD_POST) {
            if (!$this->guard()->validateCsrf($request, self::CSRF_TAX)) {
                return $this->renderTaxYear($userId, $year, '', 'tax_invalid_request');
            }

            $body = $request->body();
            $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : '';
            if ($action === 'enable_source' || $action === 'disable_source') {
                $sourceCode = is_string($body['source_code'] ?? null) ? (string) $body['source_code'] : '';
                $activation = $this->taxDeclarationRepository()->setSourceActivation(
                    $userId,
                    $year,
                    $sourceCode,
                    $action === 'enable_source',
                    $userId
                );
                if (!is_array($activation)) {
                    return $this->renderTaxYear($userId, $year, '', 'tax_source_link_failed');
                }

                $this->logEvent('private.tax_source_activation.updated', [
                    'private_user_id' => $userId,
                    'year' => $year,
                    'source_code' => $sourceCode,
                    'enabled' => $action === 'enable_source',
                ]);

                return $this->redirect($this->taxYearUrl($year) . '?notice=' . ($action === 'enable_source' ? 'source_enabled' : 'source_disabled'));
            }

            if ($action === 'generate_summary') {
                $generated = $this->taxDeclarationSummaryService()->generate(
                    $userId,
                    $year,
                    $this->authorizedPropertyIds($userId),
                    $userId
                );
                if (!is_array($generated)) {
                    return $this->renderTaxYear($userId, $year, '', 'tax_locked');
                }

                $this->logEvent('private.tax_summary.generated', ['private_user_id' => $userId, 'year' => $year]);
                return $this->redirect($this->taxYearUrl($year) . '?notice=summary_generated');
            }

            if ($action === 'lock_year') {
                if ($this->taxDeclarationRepository()->lockYear($userId, $year, $userId)) {
                    $this->logEvent('private.tax_year.locked', ['private_user_id' => $userId, 'year' => $year]);
                    return $this->redirect($this->taxYearUrl($year) . '?notice=year_locked');
                }

                return $this->renderTaxYear($userId, $year, '', 'tax_write_failed');
            }

            if ($action === 'unlock_year') {
                if ($this->taxDeclarationRepository()->unlockYear($userId, $year, $userId)) {
                    $this->logEvent('private.tax_year.unlocked', ['private_user_id' => $userId, 'year' => $year]);
                    return $this->redirect($this->taxYearUrl($year) . '?notice=year_unlocked');
                }

                return $this->renderTaxYear($userId, $year, '', 'tax_write_failed');
            }
        }

        return $this->renderTaxYear($userId, $year, $notice, $error);
    }

    private function handleTaxManualEntries(Request $request, int $year): Response
    {
        $userId = $this->requireTaxModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $year = $this->normalizeTaxYear($year);
        $query = $request->query();
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : '';
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : '';

        if ($request->method() === self::METHOD_POST) {
            if (!$this->guard()->validateCsrf($request, self::CSRF_TAX)) {
                return $this->renderTaxManualEntries($userId, $year, '', 'tax_invalid_request');
            }

            $body = $request->body();
            $created = $this->taxDeclarationRepository()->createManualEntry(
                $userId,
                $year,
                (string) ($body['label'] ?? ''),
                is_numeric($body['amount'] ?? null) ? (float) $body['amount'] : -1.0,
                (string) ($body['category'] ?? ''),
                is_string($body['status'] ?? null) ? (string) $body['status'] : 'draft',
                $userId,
                is_string($body['notes'] ?? null) ? (string) $body['notes'] : null
            );
            if (!is_array($created)) {
                return $this->renderTaxManualEntries($userId, $year, '', 'tax_locked_or_invalid');
            }

            $this->logEvent('private.tax_manual_income.created', ['private_user_id' => $userId, 'year' => $year]);
            return $this->redirect($this->taxManualUrl($year) . '?notice=manual_created');
        }

        return $this->renderTaxManualEntries($userId, $year, $notice, $error);
    }

    private function handleTaxControls(Request $request, int $year): Response
    {
        $userId = $this->requireTaxModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        return $this->render('modules/tax-declaration-helper/controls', $this->taxViewModel(
            $userId,
            $this->normalizeTaxYear($year),
            'Contrôles fiscaux'
        ));
    }

    private function handleTaxDocuments(Request $request, int $year): Response
    {
        $userId = $this->requireTaxModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $year = $this->normalizeTaxYear($year);
        if ($request->method() === self::METHOD_POST) {
            if (!$this->guard()->validateCsrf($request, self::CSRF_TAX)) {
                return $this->render('modules/tax-declaration-helper/documents', $this->taxViewModel(
                    $userId,
                    $year,
                    'Documents fiscaux',
                    '',
                    'tax_invalid_request'
                ));
            }

            $body = $request->body();
            $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : '';
            if ($action === 'email_tax_pdf') {
                $sent = $this->sendTaxPdfByEmail($body, $userId, $year);
                return $this->redirect($this->taxYearUrl($year) . '/documents' . ($sent ? '?notice=tax_pdf_emailed' : '?error=email_failed'));
            }
        }

        return $this->render('modules/tax-declaration-helper/documents', $this->taxViewModel(
            $userId,
            $year,
            'Documents fiscaux'
        ));
    }

    private function handleTaxExport(Request $request, int $year): Response
    {
        $userId = $this->requireTaxModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $year = $this->normalizeTaxYear($year);
        $query = $request->query();
        $format = is_string($query['format'] ?? null) ? strtolower(trim((string) $query['format'])) : 'csv';
        $format = $format === 'pdf' ? 'pdf' : 'csv';
        $summary = $this->taxDeclarationSummaryService()->build($userId, $year, $this->authorizedPropertyIds($userId));
        $this->taxDeclarationRepository()->createExportLog($userId, $year, $format, $summary, $userId);
        $this->logEvent('private.tax_export.created', ['private_user_id' => $userId, 'year' => $year, 'format' => $format]);

        if ($format === 'pdf') {
            return $this->withPrivateHeaders(new Response(200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="aide-impots-%d.pdf"', $year),
                'X-Content-Type-Options' => 'nosniff',
            ], $this->taxDeclarationSummaryService()->pdf($summary)));
        }

        return $this->withPrivateHeaders(new Response(200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="aide-impots-%d.csv"', $year),
            'X-Content-Type-Options' => 'nosniff',
        ], $this->taxDeclarationSummaryService()->csv($summary)));
    }

    private function handleDiscussionIndex(Request $request): Response
    {
        $userId = $this->requireDiscussionModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $this->discussionRetentionService()->purgeExpiredForUser($userId);

        if ($request->method() === self::METHOD_POST) {
            if (!$this->guard()->validateCsrf($request, self::CSRF_DISCUSSIONS)) {
                return $this->redirect(private_portal_url('discussion_index') . '?error=csrf');
            }
            if (!$this->discussionRateLimitHit($request, $userId, 'conversation')) {
                return $this->redirect(private_portal_url('discussion_index') . '?error=rate_limited');
            }

            $body = $request->body();
            $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : '';
            if ($action === 'invite_member') {
                $sent = $this->sendDiscussionInvitation($body, $userId);
                return $this->redirect(private_portal_url('discussion_index') . ($sent ? '?notice=invite_sent' : '?error=invite'));
            }

            $type = is_string($body['type'] ?? null) ? strtolower(trim((string) $body['type'])) : 'direct';
            $conversation = null;
            if ($type === 'group') {
                $conversation = $this->discussionService()->createGroupConversation(
                    $userId,
                    (string) ($body['title'] ?? ''),
                    $this->discussionMemberIdsFromPayload($body['member_ids'] ?? [])
                );
            } else {
                $recipientIds = $this->discussionMemberIdsFromPayload($body['recipient_ids'] ?? $body['recipient_id'] ?? []);
                $recipientId = count($recipientIds) === 1 ? $recipientIds[0] : 0;
                $conversation = $this->discussionService()->createDirectConversation($userId, $recipientId);
            }

            if (is_array($conversation)) {
                return $this->redirect(private_portal_url('discussion_index') . '/' . (int) $conversation['id']);
            }

            return $this->redirect(private_portal_url('discussion_index') . '?error=conversation');
        }

        return $this->render('modules/family-discussion/index', $this->discussionViewModel($userId, [
            'privatePageTitle' => 'Discussions famille',
            'conversations' => $this->discussionService()->listConversations($userId),
            'members' => $this->discussionService()->listActiveMembers($userId),
            'error' => is_string($request->query()['error'] ?? null) ? (string) $request->query()['error'] : '',
            'notice' => is_string($request->query()['notice'] ?? null) ? (string) $request->query()['notice'] : '',
        ]));
    }

    private function handleDiscussionConversation(Request $request, int $conversationId): Response
    {
        $userId = $this->requireDiscussionModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $this->discussionRetentionService()->purgeExpiredForUser($userId);
        $conversation = $this->discussionRepository()->findConversationForUser($conversationId, $userId);
        if (!is_array($conversation)) {
            $this->logEvent('private.discussion.access.denied', ['private_user_id' => $userId, 'conversation_id' => $conversationId]);
            return $this->handleModuleAccessDenied('discussions');
        }

        if ($request->method() === self::METHOD_POST) {
            if (!$this->guard()->validateCsrf($request, self::CSRF_DISCUSSIONS)) {
                return $this->redirect(private_portal_url('discussion_index') . '/' . $conversationId . '?error=csrf');
            }

            $body = $request->body();
            $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : 'send_message';
            if ($action === 'delete_message') {
                $messageId = $this->normalizeNumericId($body['message_id'] ?? null);
                $deleted = $this->discussionService()->deleteMessage($userId, $messageId, $this->discussionRequestId($request));

                return $this->redirect(private_portal_url('discussion_index') . '/' . $conversationId . ($deleted ? '?notice=deleted' : '?error=delete'));
            }
            if ($action === 'delete_attachment') {
                $attachmentId = is_string($body['attachment_id'] ?? null) ? (string) $body['attachment_id'] : '';
                $deleted = $this->discussionService()->deleteAttachment($userId, $attachmentId, $this->discussionRequestId($request));

                return $this->redirect(private_portal_url('discussion_index') . '/' . $conversationId . ($deleted ? '?notice=deleted' : '?error=delete'));
            }
            if ($action === 'update_notification_preference') {
                $updated = $this->discussionRepository()->setNotificationPreference(
                    $conversationId,
                    $userId,
                    is_string($body['notification_mode'] ?? null) ? (string) $body['notification_mode'] : 'notify'
                );

                return $this->redirect(private_portal_url('discussion_index') . '/' . $conversationId . ($updated ? '?notice=notifications_updated' : '?error=notification'));
            }
            if ($action !== 'send_message') {
                return $this->redirect(private_portal_url('discussion_index') . '/' . $conversationId . '?error=message');
            }
            if (!$this->discussionRateLimitHit($request, $userId, 'message')) {
                return $this->redirect(private_portal_url('discussion_index') . '/' . $conversationId . '?error=rate_limited');
            }

            $message = $this->discussionService()->sendMessage(
                $userId,
                $conversationId,
                is_string($body['body'] ?? null) ? (string) $body['body'] : '',
                $request->files(),
                $this->discussionEncryptionFromPayload($body),
                $this->discussionClientMessageIdFromPayload($body),
                $this->discussionRequestId($request)
            );

            return $this->redirect(
                private_portal_url('discussion_index') . '/' . $conversationId . (is_array($message) ? '?notice=sent#discussion-message-last' : '?error=message')
            );
        }

        $timeline = $this->discussionTimelineService()->timeline($conversationId, $userId);
        $this->discussionService()->markRead($conversationId, $userId, $this->discussionRequestId($request));

        return $this->render('modules/family-discussion/conversation', $this->discussionViewModel($userId, [
            'privatePageTitle' => 'Discussion',
            'conversation' => $conversation,
            'conversations' => $this->discussionService()->listConversations($userId),
            'messages' => $timeline['messages'],
            'discussionTimeline' => $timeline,
            'conversationMembers' => $this->discussionRepository()->listConversationMembers($conversationId),
            'notificationPreference' => $this->discussionRepository()->notificationPreferenceForUser($conversationId, $userId),
            'discussionMediaGallery' => $this->discussionRepository()->listMediaGalleryForUser($conversationId, $userId),
            'members' => $this->discussionService()->listActiveMembers($userId),
            'error' => is_string($request->query()['error'] ?? null) ? (string) $request->query()['error'] : '',
            'notice' => is_string($request->query()['notice'] ?? null) ? (string) $request->query()['notice'] : '',
        ]));
    }

    private function handleDiscussionApiConversations(Request $request): Response
    {
        $userId = $this->requireDiscussionModuleUser($request);
        if ($userId instanceof Response) {
            return $this->jsonPrivateResponse(['error' => 'forbidden'], 403);
        }

        $this->discussionRetentionService()->purgeExpiredForUser($userId);
        if ($request->method() === self::METHOD_GET) {
            return $this->jsonPrivateResponse(['conversations' => $this->discussionService()->listConversations($userId)]);
        }

        if (!$this->guard()->validateCsrf($request, self::CSRF_DISCUSSIONS)) {
            return $this->jsonPrivateResponse(['error' => 'csrf'], 403);
        }
        if (!$this->discussionRateLimitHit($request, $userId, 'conversation')) {
            return $this->jsonPrivateResponse(['error' => 'rate_limited'], 429);
        }

        $body = $request->body();
        $type = is_string($body['type'] ?? null) ? strtolower(trim((string) $body['type'])) : 'direct';
        if ($type === 'group') {
            $conversation = $this->discussionService()->createGroupConversation(
                $userId,
                (string) ($body['title'] ?? ''),
                $this->discussionMemberIdsFromPayload($body['member_ids'] ?? [])
            );
        } else {
            $recipientIds = $this->discussionMemberIdsFromPayload($body['recipient_ids'] ?? $body['recipient_id'] ?? []);
            $recipientId = count($recipientIds) === 1 ? $recipientIds[0] : 0;
            $conversation = $this->discussionService()->createDirectConversation($userId, $recipientId);
        }

        return is_array($conversation)
            ? $this->jsonPrivateResponse(['conversation' => $conversation], 201)
            : $this->jsonPrivateResponse(['error' => 'invalid_conversation'], 422);
    }

    private function handleDiscussionApiMessages(Request $request, int $conversationId): Response
    {
        $userId = $this->requireDiscussionModuleUser($request);
        if ($userId instanceof Response) {
            return $this->jsonPrivateResponse(['error' => 'forbidden'], 403);
        }

        $this->discussionRetentionService()->purgeExpiredForUser($userId);
        if (!$this->discussionRepository()->isParticipant($conversationId, $userId)) {
            $this->logEvent('private.discussion.access.denied', ['private_user_id' => $userId, 'conversation_id' => $conversationId]);
            return $this->jsonPrivateResponse(['error' => 'forbidden'], 403);
        }

        if ($request->method() === self::METHOD_GET) {
            $after = is_numeric($request->query()['after_id'] ?? $request->query()['after_message_id'] ?? null)
                ? (int) ($request->query()['after_id'] ?? $request->query()['after_message_id'])
                : 0;
            $before = is_numeric($request->query()['before_id'] ?? $request->query()['before_message_id'] ?? null)
                ? (int) ($request->query()['before_id'] ?? $request->query()['before_message_id'])
                : 0;
            $limit = is_numeric($request->query()['limit'] ?? null) ? (int) $request->query()['limit'] : 100;
            $timeline = $this->discussionTimelineService()->timeline($conversationId, $userId, $before, $after, $limit);
            if ($before <= 0) {
                $this->discussionService()->markRead($conversationId, $userId, $this->discussionRequestId($request));
            }

            return $this->jsonPrivateResponse($timeline);
        }

        if (!$this->guard()->validateCsrf($request, self::CSRF_DISCUSSIONS)) {
            return $this->jsonPrivateResponse(['error' => 'csrf'], 403);
        }
        if (!$this->discussionRateLimitHit($request, $userId, 'message')) {
            return $this->jsonPrivateResponse(['error' => 'rate_limited'], 429);
        }

        $body = $request->body();
        $message = $this->discussionService()->sendMessage(
            $userId,
            $conversationId,
            is_string($body['body'] ?? null) ? (string) $body['body'] : '',
            $request->files(),
            $this->discussionEncryptionFromPayload($body),
            $this->discussionClientMessageIdFromPayload($body),
            $this->discussionRequestId($request)
        );

        return is_array($message)
            ? $this->jsonPrivateResponse(['message' => $message], 201)
            : $this->jsonPrivateResponse(['error' => 'invalid_message'], 422);
    }

    private function handleDiscussionApiEvents(Request $request, int $conversationId): Response
    {
        $userId = $this->requireDiscussionModuleUser($request);
        if ($userId instanceof Response) {
            return $this->jsonPrivateResponse(['error' => 'forbidden'], 403);
        }

        if ($request->method() !== self::METHOD_GET) {
            return $this->jsonPrivateResponse(['error' => 'method_not_allowed'], 405);
        }

        if (!$this->discussionRepository()->isParticipant($conversationId, $userId)) {
            $this->logEvent('private.discussion.access.denied', ['private_user_id' => $userId, 'conversation_id' => $conversationId]);
            return $this->jsonPrivateResponse(['error' => 'forbidden'], 403);
        }

        $after = is_numeric($request->query()['after_event_id'] ?? $request->query()['last_event_id'] ?? null)
            ? (int) ($request->query()['after_event_id'] ?? $request->query()['last_event_id'])
            : 0;
        $lastEventId = $request->header('Last-Event-ID');
        if ($after <= 0 && is_string($lastEventId) && is_numeric($lastEventId)) {
            $after = (int) $lastEventId;
        }

        return $this->withPrivateHeaders(
            $this->discussionEventStream()->response($conversationId, $userId, $after, 100)
        );
    }

    private function handleDiscussionApiClientEvents(Request $request): Response
    {
        $userId = $this->requireDiscussionModuleUser($request);
        if ($userId instanceof Response) {
            return $this->jsonPrivateResponse(['error' => 'forbidden'], 403);
        }

        if ($request->method() !== self::METHOD_POST) {
            return $this->jsonPrivateResponse(['error' => 'method_not_allowed'], 405);
        }
        if (!$this->guard()->validateCsrf($request, self::CSRF_DISCUSSIONS)) {
            return $this->jsonPrivateResponse(['error' => 'csrf'], 403);
        }
        if (!$this->discussionRateLimitHit($request, $userId, 'client_event')) {
            return $this->jsonPrivateResponse(['error' => 'rate_limited'], 429);
        }

        $payload = $request->json();
        if ($payload === []) {
            $payload = $request->body();
        }

        $type = is_string($payload['type'] ?? null) ? strtolower(trim((string) $payload['type'])) : '';
        $conversationId = $this->normalizeNumericId($payload['conversation_id'] ?? $payload['conversationId'] ?? null);
        $messageId = $this->normalizeNumericId($payload['message_id'] ?? $payload['messageId'] ?? null);
        if ($conversationId <= 0 || !$this->discussionRepository()->isParticipant($conversationId, $userId)) {
            return $this->jsonPrivateResponse(['error' => 'forbidden'], 403);
        }

        $eventName = match ($type) {
            'decrypt_failed' => 'private.discussion.client_decrypt_failed',
            'stream_failed' => 'private.discussion.stream_failed',
            default => '',
        };
        if ($eventName === '') {
            return $this->jsonPrivateResponse(['error' => 'invalid_event'], 422);
        }

        $this->logEvent($eventName, [
            'private_user_id' => $userId,
            'conversation_id' => $conversationId,
            'message_id' => $messageId > 0 ? $messageId : null,
        ]);

        return $this->jsonPrivateResponse(['ok' => true]);
    }

    private function handleDiscussionApiCryptoDevices(Request $request): Response
    {
        $userId = $this->requireDiscussionModuleUser($request);
        if ($userId instanceof Response) {
            return $this->jsonPrivateResponse(['error' => 'forbidden'], 403);
        }

        if ($request->method() === self::METHOD_GET) {
            $conversationId = is_numeric($request->query()['conversation_id'] ?? null)
                ? (int) $request->query()['conversation_id']
                : 0;
            if ($conversationId > 0) {
                if (!$this->discussionRepository()->isParticipant($conversationId, $userId)) {
                    return $this->jsonPrivateResponse(['error' => 'forbidden'], 403);
                }

                $memberIds = array_values(array_filter(array_map(
                    static fn (array $member): int => (int) ($member['privateUserId'] ?? 0),
                    $this->discussionRepository()->listConversationMembers($conversationId)
                ), static fn (int $id): bool => $id > 0));

                return $this->jsonPrivateResponse([
                    'devices' => $this->discussionRepository()->listCryptoDevicesForUsers($memberIds),
                    'members' => $this->discussionRepository()->listConversationMembers($conversationId),
                ]);
            }

            return $this->jsonPrivateResponse([
                'devices' => $this->discussionRepository()->listCryptoDevicesForUsers([$userId]),
            ]);
        }

        if (!$this->guard()->validateCsrf($request, self::CSRF_DISCUSSIONS)) {
            return $this->jsonPrivateResponse(['error' => 'csrf'], 403);
        }

        $body = $request->body();
        $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : '';
        $conversationId = is_numeric($body['conversation_id'] ?? null) ? (int) $body['conversation_id'] : 0;
        if ($action === 'trust_device') {
            $trusted = $this->discussionRepository()->trustCryptoDevice(
                $userId,
                is_string($body['device_id'] ?? null) ? (string) $body['device_id'] : ''
            );

            return $trusted
                ? $this->jsonPrivateResponse(['ok' => true])
                : $this->jsonPrivateResponse(['error' => 'invalid_device'], 422);
        }

        if ($action === 'revoke_device') {
            $revoked = $this->discussionRepository()->revokeCryptoDevice(
                $userId,
                is_string($body['device_id'] ?? null) ? (string) $body['device_id'] : ''
            );
            if ($revoked && $conversationId > 0 && $this->discussionRepository()->isParticipant($conversationId, $userId)) {
                $this->discussionEventService()->cryptoDeviceRevoked($conversationId, $userId, $this->discussionRequestId($request));
            }

            return $revoked
                ? $this->jsonPrivateResponse(['ok' => true])
                : $this->jsonPrivateResponse(['error' => 'invalid_device'], 422);
        }

        $device = $this->discussionRepository()->registerCryptoDevice(
            $userId,
            is_string($body['device_id'] ?? null) ? (string) $body['device_id'] : '',
            is_string($body['public_key_jwk'] ?? null) ? (string) $body['public_key_jwk'] : '',
            is_string($body['device_label'] ?? null) ? (string) $body['device_label'] : ''
        );
        if (is_array($device) && $conversationId > 0 && $this->discussionRepository()->isParticipant($conversationId, $userId)) {
            $this->discussionEventService()->cryptoDeviceAdded($conversationId, $userId, $this->discussionRequestId($request));
        }

        return is_array($device)
            ? $this->jsonPrivateResponse(['device' => $device], 201)
            : $this->jsonPrivateResponse(['error' => 'invalid_device'], 422);
    }

    private function handleDiscussionApiConversationKeys(Request $request, int $conversationId): Response
    {
        $userId = $this->requireDiscussionModuleUser($request);
        if ($userId instanceof Response) {
            return $this->jsonPrivateResponse(['error' => 'forbidden'], 403);
        }

        if (!$this->discussionRepository()->isParticipant($conversationId, $userId)) {
            return $this->jsonPrivateResponse(['error' => 'forbidden'], 403);
        }

        if ($request->method() === self::METHOD_GET) {
            return $this->jsonPrivateResponse([
                'keys' => $this->discussionRepository()->listConversationKeysForUser($conversationId, $userId),
                'knownKeyCount' => $this->discussionRepository()->countConversationKeys($conversationId),
            ]);
        }

        if (!$this->guard()->validateCsrf($request, self::CSRF_DISCUSSIONS)) {
            return $this->jsonPrivateResponse(['error' => 'csrf'], 403);
        }

        $payload = $request->body() !== [] ? $request->body() : $request->json();
        $wrappers = $this->discussionKeyWrappersFromPayload($payload['keys'] ?? []);
        $count = $this->discussionRepository()->upsertConversationKeys($conversationId, $userId, $wrappers);
        if ($count > 0) {
            $this->discussionEventService()->conversationKeysUpdated($conversationId, $userId, $count, $this->discussionRequestId($request));
        }

        return $count > 0
            ? $this->jsonPrivateResponse(['ok' => true, 'count' => $count])
            : $this->jsonPrivateResponse(['error' => 'invalid_keys'], 422);
    }

    private function handleDiscussionApiMembers(Request $request, int $conversationId): Response
    {
        $userId = $this->requireDiscussionModuleUser($request);
        if ($userId instanceof Response) {
            return $this->jsonPrivateResponse(['error' => 'forbidden'], 403);
        }

        if ($request->method() !== self::METHOD_POST || !$this->guard()->validateCsrf($request, self::CSRF_DISCUSSIONS)) {
            return $this->jsonPrivateResponse(['error' => 'csrf'], 403);
        }

        $added = $this->discussionService()->addMembers(
            $userId,
            $conversationId,
            $this->discussionMemberIdsFromPayload($request->body()['member_ids'] ?? []),
            new DiscussionAccessPolicy($this->discussionRepository()),
            $this->discussionRequestId($request)
        );

        return $added ? $this->jsonPrivateResponse(['ok' => true]) : $this->jsonPrivateResponse(['error' => 'forbidden'], 403);
    }

    private function handleDiscussionApiLeave(Request $request, int $conversationId): Response
    {
        $userId = $this->requireDiscussionModuleUser($request);
        if ($userId instanceof Response) {
            return $this->jsonPrivateResponse(['error' => 'forbidden'], 403);
        }

        if ($request->method() !== self::METHOD_POST || !$this->guard()->validateCsrf($request, self::CSRF_DISCUSSIONS)) {
            return $this->jsonPrivateResponse(['error' => 'csrf'], 403);
        }

        return $this->discussionService()->leaveConversation($conversationId, $userId, $this->discussionRequestId($request))
            ? $this->jsonPrivateResponse(['ok' => true])
            : $this->jsonPrivateResponse(['error' => 'invalid_leave'], 422);
    }

    private function handleDiscussionApiRead(Request $request, int $conversationId): Response
    {
        $userId = $this->requireDiscussionModuleUser($request);
        if ($userId instanceof Response) {
            return $this->jsonPrivateResponse(['error' => 'forbidden'], 403);
        }

        if ($request->method() !== self::METHOD_POST || !$this->guard()->validateCsrf($request, self::CSRF_DISCUSSIONS)) {
            return $this->jsonPrivateResponse(['error' => 'csrf'], 403);
        }

        return $this->discussionService()->markRead($conversationId, $userId, $this->discussionRequestId($request))
            ? $this->jsonPrivateResponse(['ok' => true])
            : $this->jsonPrivateResponse(['error' => 'forbidden'], 403);
    }

    private function handleDiscussionFile(Request $request, string $attachmentId, bool $preview): Response
    {
        unset($request);
        $userId = $this->currentPrivateUserId();
        if ($userId === null || !$this->modulePermissionRepository()->userHasModuleAccess($userId, 'discussions')) {
            return $this->handleModuleAccessDenied('discussions');
        }

        $attachment = $this->discussionRepository()->findAttachmentForUser($attachmentId, $userId);
        if (!is_array($attachment)) {
            return $this->handleNotFound();
        }

        $storagePath = $preview && is_string($attachment['previewStoragePath'] ?? null) && trim((string) $attachment['previewStoragePath']) !== ''
            ? (string) $attachment['previewStoragePath']
            : (string) ($attachment['storagePath'] ?? '');
        $absolutePath = $this->discussionAttachmentStorage()->absolutePath($storagePath);
        if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return $this->handleNotFound();
        }
        $fileContent = $this->discussionAttachmentStorage()->read($storagePath);
        if ($fileContent === null) {
            return $this->handleNotFound();
        }

        $mimeType = is_string($attachment['mimeType'] ?? null) ? (string) $attachment['mimeType'] : 'application/octet-stream';
        $filename = $this->sanitizeDownloadFilename((string) ($attachment['originalFilename'] ?? 'piece-jointe'));
        $disposition = $preview || str_starts_with($mimeType, 'image/') ? 'inline' : 'attachment';
        $this->logEvent('private.discussion.attachment.downloaded', [
            'private_user_id' => $userId,
            'attachment_id' => (string) ($attachment['attachmentId'] ?? ''),
        ]);

        return $this->withPrivateHeaders(new Response(200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, addslashes($filename)),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], $fileContent));
    }

    private function handlePrivacyExport(Request $request): Response
    {
        $userId = $this->requireAuthenticatedUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $payload = $this->privateDataProtectionService()->exportAccount($userId);
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            $encoded = '{}';
        }

        $this->logEvent('private.privacy.exported', ['private_user_id' => $userId]);

        return $this->withPrivateHeaders(new Response(200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="private-account-export.json"',
            'X-Content-Type-Options' => 'nosniff',
        ], $encoded));
    }

    private function handleOpsBackup(Request $request): Response
    {
        $userId = $this->requireAuthenticatedUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $storage = $this->privateDocumentStorage();
        $backup = $this->privateBackupService()->createBackup($storage->exportsDirectory(), $storage->uploadsDirectory());
        $verification = !empty($backup['path']) && is_string($backup['path'])
            ? $this->privateBackupService()->verifyBackup($backup['path'])
            : ['valid' => false];
        $restoreCheck = !empty($backup['path']) && is_string($backup['path'])
            ? $this->privateBackupService()->restoreBackup($backup['path'], true)
            : ['success' => false, 'dryRun' => true];

        $payload = [
            'backup' => $backup,
            'verification' => $verification,
            'restoreDryRun' => $restoreCheck,
        ];
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            $encoded = '{}';
        }

        $this->logEvent('private.ops.backup_created', [
            'private_user_id' => $userId,
            'success' => !empty($backup['success']),
            'verified' => !empty($verification['valid']),
        ]);

        return $this->withPrivateHeaders(new Response(200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ], $encoded));
    }

    private function handleLogout(Request $request): Response
    {
        if ($request->method() === self::METHOD_GET) {
            $this->logEvent('private.logout.failed', ['reason' => 'method_not_allowed']);

            return $this->withPrivateHeaders(new Response(
                405,
                ['Allow' => self::METHOD_POST],
                'Cette action exige une requête POST.'
            ));
        }

        $body = $request->body();
        $bodyToken = is_string($body['csrf_token'] ?? null) ? (string) $body['csrf_token'] : '';
        $headerToken = is_string($request->header('X-CSRF-Token') ?? null)
            ? (string) $request->header('X-CSRF-Token')
            : '';
        $csrfToken = $bodyToken !== '' ? $bodyToken : $headerToken;

        $csrfValid = csrf_validate($csrfToken, 'private_logout') || csrf_validate($csrfToken, 'private');
        if ($csrfToken === '' || (!$csrfValid && !$this->auth->isAuthenticated())) {
            $this->logEvent('private.logout.rejected', ['reason' => 'csrf_invalid']);
            return $this->render('login', [
                'privatePageTitle' => $this->translate('TXT_PRIVATE_DASHBOARD_TITLE', 'Tableau de bord privé'),
                'errorKey' => 'TXT_PRIVATE_ERROR_CSRF',
                'csrfToken' => csrf_token('private'),
                'privatePasswordForgotUrl' => private_portal_url('password_forgot'),
            ]);
        }

        $this->auth->logout('manual');

        return $this->redirect(private_portal_url('login'));
    }

    private function handleSessionPing(Request $request): Response
    {
        if ($request->method() !== self::METHOD_POST) {
            return $this->jsonPrivateResponse(['ok' => false, 'error' => 'method_not_allowed'], 405);
        }

        if (!$this->auth->isAuthenticated()) {
            return $this->jsonPrivateResponse([
                'ok' => false,
                'error' => 'unauthenticated',
                'loginUrl' => private_portal_url('login'),
            ], 401);
        }

        if (!$this->guard()->validateCsrf($request, self::CSRF_SESSION)) {
            return $this->jsonPrivateResponse(['ok' => false, 'error' => 'csrf'], 419);
        }

        return $this->jsonPrivateResponse([
            'ok' => true,
            'remainingSeconds' => $this->auth->remainingInactivitySeconds(),
            'timeoutSeconds' => $this->auth->inactivityTimeoutSeconds(),
            'reauthFresh' => $this->auth->isReauthFresh(),
        ]);
    }

    private function handleActivate(Request $request, string $token): Response
    {
        $token = $this->normalizeDocumentId($token);
        if ($token === '') {
            return $this->handleNotFound();
        }

        $error = null;
        if ($request->method() === self::METHOD_POST) {
            if (!$this->guard()->validateCsrf($request, 'private_activate')) {
                $error = $this->translate('TXT_PRIVATE_ERROR_CSRF', 'Requête invalide');
            } else {
                $body = $request->body();
                $password = is_string($body['password'] ?? null) ? (string) $body['password'] : '';
                $confirmation = is_string($body['password_confirm'] ?? null) ? (string) $body['password_confirm'] : '';
                $policy = new PrivatePasswordPolicy();
                $errors = $policy->validate($password, $confirmation);
                if ($errors !== []) {
                    $error = $policy->errorMessage($errors);
                } else {
                    $hash = password_hash($password, PASSWORD_ARGON2ID);
                    $user = is_string($hash)
                        ? $this->privateUserRepository()->activateByInviteToken(
                            $token,
                            $hash,
                            $request->clientIp((bool) app_config('private.trust_proxy_headers', false)),
                            (string) ($request->server('HTTP_USER_AGENT', '') ?? '')
                        )
                        : null;
                    if (is_array($user)) {
                        $this->logEvent('private.invite.accepted', [
                            'identifier' => AppEventLogger::maskIdentifier((string) ($user['email'] ?? '')),
                        ]);

                        return $this->render('notice', [
                            'privatePageTitle' => $this->translate('TXT_PRIVATE_ACTIVATE_TITLE', 'Activation privée'),
                            'privateNoticeTitle' => $this->translate('TXT_PRIVATE_ACTIVATE_TITLE', 'Activation privée'),
                            'privateNoticeBody' => $this->translate('TXT_PRIVATE_ACTIVATE_SUCCESS', 'Votre espace privé est activé.'),
                            'privatePasswordForgotUrl' => private_portal_url('password_forgot'),
                        ]);
                    }

                    $this->logEvent('private.invite.accept_failed', ['reason' => 'invalid_or_expired_token']);
                    $error = $this->translate('TXT_PRIVATE_ACTIVATE_ERROR', 'Lien d’activation invalide ou expiré.');
                }
            }
        }

        return $this->render('password_form', [
            'privatePageTitle' => $this->translate('TXT_PRIVATE_ACTIVATE_TITLE', 'Activation privée'),
            'privateNoticeTitle' => $this->translate('TXT_PRIVATE_ACTIVATE_TITLE', 'Activation privée'),
            'privateFormAction' => private_portal_url('activate') . '/' . rawurlencode($token),
            'privateFormCsrfToken' => csrf_token('private_activate'),
            'privateFormSubmitLabel' => $this->translate('TXT_PRIVATE_ACTIVATE_SUBMIT', 'Activer'),
            'privateFormError' => $error,
        ]);
    }

    private function handlePasswordForgot(Request $request): Response
    {
        if ($request->method() === self::METHOD_POST && !$this->guard()->validateCsrf($request, 'private_password')) {
            return $this->render('notice', [
                'privatePageTitle' => $this->translate('TXT_PRIVATE_PASSWORD_FORGOT_TITLE', 'Réinitialisation privée'),
                'privateNoticeTitle' => $this->translate('TXT_PRIVATE_ERROR_CSRF', 'Requête invalide'),
                'privateNoticeBody' => $this->translate('TXT_PRIVATE_ERROR_CSRF', 'Requête invalide'),
            ]);
        }

        if ($request->method() === self::METHOD_POST) {
            $body = $request->body();
            $identifier = is_string($body['identifier'] ?? null) ? strtolower(trim((string) $body['identifier'])) : '';
            $user = $this->privateUserRepository()->findByEmail($identifier);
            if (is_array($user) && strtolower((string) ($user['status'] ?? '')) !== 'deleted') {
                $userId = is_numeric($user['id'] ?? null) ? (int) $user['id'] : 0;
                if ($userId > 0) {
                    $token = $this->privateUserRepository()->createPasswordResetToken(
                        $userId,
                        $request->clientIp((bool) app_config('private.trust_proxy_headers', false)),
                        (string) ($request->server('HTTP_USER_AGENT', '') ?? '')
                    );
                    if ($token !== null) {
                        $this->sendPasswordResetEmail($identifier, $token);
                    }
                    $this->logEvent($token !== null ? 'private.password_reset.requested' : 'private.password_reset.failed', [
                        'identifier' => AppEventLogger::maskIdentifier($identifier),
                    ]);
                }
            } else {
                $this->logEvent('private.password_reset.requested_unknown', [
                    'identifier' => AppEventLogger::maskIdentifier($identifier),
                ]);
            }

            return $this->render('notice', [
                'privatePageTitle' => $this->translate('TXT_PRIVATE_PASSWORD_FORGOT_TITLE', 'Réinitialisation privée'),
                'privateNoticeTitle' => $this->translate('TXT_PRIVATE_PASSWORD_FORGOT_TITLE', 'Réinitialisation privée'),
                'privateNoticeBody' => $this->translate('TXT_PRIVATE_PASSWORD_FORGOT_NEUTRAL', 'Si le compte existe, une réinitialisation a été préparée.'),
            ]);
        }

        return $this->render('password_forgot', [
            'privatePageTitle' => $this->translate('TXT_PRIVATE_PASSWORD_FORGOT_TITLE', 'Réinitialisation privée'),
            'csrfToken' => csrf_token('private_password'),
        ]);
    }

    private function handlePasswordReset(Request $request, string $token): Response
    {
        $token = $this->normalizeDocumentId($token);
        if ($token === '') {
            return $this->handleNotFound();
        }

        if ($request->method() === self::METHOD_POST && !$this->guard()->validateCsrf($request, 'private_password')) {
            return $this->render('notice', [
                'privatePageTitle' => $this->translate('TXT_PRIVATE_PASSWORD_RESET_TITLE', 'Réinitialisation privée'),
                'privateNoticeTitle' => $this->translate('TXT_PRIVATE_ERROR_CSRF', 'Requête invalide'),
                'privateNoticeBody' => $this->translate('TXT_PRIVATE_ERROR_CSRF', 'Requête invalide'),
            ]);
        }

        $error = null;
        if ($request->method() === self::METHOD_POST) {
            $body = $request->body();
            $password = is_string($body['password'] ?? null) ? (string) $body['password'] : '';
            $confirmation = is_string($body['password_confirm'] ?? null) ? (string) $body['password_confirm'] : '';
            $policy = new PrivatePasswordPolicy();
            $errors = $policy->validate($password, $confirmation);
            if ($errors !== []) {
                $error = $policy->errorMessage($errors);
            } else {
                $hash = password_hash($password, PASSWORD_ARGON2ID);
                $user = is_string($hash)
                    ? $this->privateUserRepository()->resetPasswordByToken(
                        $token,
                        $hash,
                        $request->clientIp((bool) app_config('private.trust_proxy_headers', false))
                    )
                    : null;
                if (is_array($user)) {
                    $this->logEvent('private.password_reset.completed', [
                        'identifier' => AppEventLogger::maskIdentifier((string) ($user['email'] ?? '')),
                    ]);

                    return $this->render('notice', [
                        'privatePageTitle' => $this->translate('TXT_PRIVATE_PASSWORD_RESET_TITLE', 'Réinitialisation privée'),
                        'privateNoticeTitle' => $this->translate('TXT_PRIVATE_PASSWORD_RESET_TITLE', 'Réinitialisation privée'),
                        'privateNoticeBody' => $this->translate('TXT_PRIVATE_PASSWORD_RESET_SUCCESS', 'Le mot de passe privé a été remplacé.'),
                    ]);
                }

                $this->logEvent('private.password_reset.completed_failed', ['reason' => 'invalid_or_expired_token']);
                $error = $this->translate('TXT_PRIVATE_PASSWORD_RESET_ERROR', 'Lien de réinitialisation invalide ou expiré.');
            }
        }

        return $this->render('password_form', [
            'privatePageTitle' => $this->translate('TXT_PRIVATE_PASSWORD_RESET_TITLE', 'Réinitialisation privée'),
            'privateNoticeTitle' => $this->translate('TXT_PRIVATE_PASSWORD_RESET_TITLE', 'Réinitialisation privée'),
            'privateFormAction' => private_portal_url('password_reset') . '/' . rawurlencode($token),
            'privateFormCsrfToken' => csrf_token('private_password'),
            'privateFormSubmitLabel' => $this->translate('TXT_PRIVATE_PASSWORD_RESET_SUBMIT', 'Remplacer le mot de passe'),
            'privateFormError' => $error,
        ]);
    }

    private function handleFiles(Request $request, string $documentId): Response
    {
        $userId = $this->userIdOrAccessDenied($request);
        if ($userId === null) {
            return $this->handleFilesAccessDenied($documentId);
        }

        $documentId = $this->normalizeDocumentId($documentId);
        if ($documentId === '') {
            return $this->handleNotFound();
        }

        $document = $this->privateDocumentRepository()->findByDocumentIdAndUser($documentId, $userId);
        if (!is_array($document)) {
            $this->logEvent('private.files.not_found', [
                'reason' => 'document_not_found',
                'document_id' => $documentId,
                'identifier' => AppEventLogger::maskIdentifier($this->auth->currentIdentifier()),
                'private_user_id' => $userId,
            ]);

            return $this->withPrivateHeaders(new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Not Found'));
        }

        $scanStatus = is_string($document['scanStatus'] ?? null)
            ? (string) $document['scanStatus']
            : PrivateDocumentRepository::SCAN_STATUS_CLEAN;
        if (!PrivateDocumentRepository::isDownloadableScanStatus($scanStatus)) {
            $this->logEvent('private.files.download_blocked_scan', [
                'document_id' => $documentId,
                'private_user_id' => $userId,
                'scan_status' => $scanStatus,
            ]);

            return $this->withPrivateHeaders(new Response(403, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Forbidden'));
        }

        $storagePath = is_string($document['storagePath'] ?? null) ? trim((string) $document['storagePath']) : '';
        $absolutePath = $this->privateDocumentStorage()->absolutePath($storagePath);
        if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
            $this->logEvent('private.files.not_found', [
                'reason' => $absolutePath === null ? 'invalid_path' : 'file_missing',
                'document_id' => $documentId,
                'private_user_id' => $userId,
                'storage_path' => $storagePath,
            ]);

            return $this->withPrivateHeaders(new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Not Found'));
        }

        $content = file_get_contents($absolutePath);
        if ($content === false) {
            $this->logEvent('private.files.read_failed', [
                'document_id' => $documentId,
                'private_user_id' => $userId,
                'storage_path' => $storagePath,
            ]);

            return $this->withPrivateHeaders(new Response(
                500,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
                'Unable to read the file.'
            ));
        }

        $fileSize = filesize($absolutePath);
        if (!is_int($fileSize)) {
            $fileSize = strlen($content);
        }

        $storedMimeType = is_string($document['mimeType'] ?? null) ? trim((string) $document['mimeType']) : '';
        $mimeType = $storedMimeType !== '' && preg_match('/\A[a-z0-9.+-]+\/[a-z0-9.+-]+\z/i', $storedMimeType) === 1
            ? $storedMimeType
            : 'application/octet-stream';

        $storedName = is_string($document['originalName'] ?? null) ? trim((string) $document['originalName']) : '';
        $filename = $this->sanitizeDownloadFilename($storedName !== '' ? $storedName : ($documentId . '.bin'));

        $this->logEvent('private.files.downloaded', [
            'document_id' => $documentId,
            'private_user_id' => $userId,
            'identifier' => AppEventLogger::maskIdentifier($this->auth->currentIdentifier()),
            'size_bytes' => $fileSize,
        ]);

        return $this->withPrivateHeaders(new Response(
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Length' => (string) $fileSize,
                'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
            $content
        ));
    }

    private function handleFilesUpload(Request $request): Response
    {
        $userId = $this->userIdOrAccessDenied($request);
        if ($userId === null) {
            return $this->handleFilesAccessDenied();
        }

        if ($request->method() !== self::METHOD_POST || !$this->guard()->validateCsrf($request, self::CSRF_DOCUMENTS)) {
            $this->logEvent('private.files.upload_rejected', [
                'reason' => $request->method() !== self::METHOD_POST ? 'method_not_allowed' : 'csrf_invalid',
                'private_user_id' => $userId,
            ]);

            return $this->redirect($this->documentsUrlWithError('upload_forbidden'));
        }

        $body = $request->files();
        $uploadedFile = is_array($body[self::DOCUMENT_UPLOAD_FIELD] ?? null) ? $body[self::DOCUMENT_UPLOAD_FIELD] : null;
        if (!is_array($uploadedFile)) {
            $this->logEvent('private.files.upload_rejected', [
                'reason' => 'missing_file',
                'private_user_id' => $userId,
            ]);

            return $this->redirect($this->documentsUrlWithError('missing_file'));
        }

        $post = $request->body();
        $categoryId = $this->normalizeNumericId($post['category_id'] ?? null);
        $categoryId = $categoryId > 0 ? $categoryId : null;
        $storage = $this->privateDocumentStorage();
        $validated = $storage->validateUploadedFile($uploadedFile);
        if ($validated === null) {
            $this->logEvent('private.files.upload_rejected', [
                'reason' => $storage->uploadError() ?? 'invalid_file',
                'private_user_id' => $userId,
            ]);

            return $this->redirect($this->documentsUrlWithError('upload_failed'));
        }

        $documentId = $storage->generateDocumentId();
        if ($documentId === '') {
            $this->logEvent('private.files.upload_rejected', [
                'reason' => 'document_id_generation_failed',
                'private_user_id' => $userId,
            ]);

            return $this->redirect($this->documentsUrlWithError('upload_failed'));
        }

        $stored = $storage->storeUploadedFile($validated, $documentId);
        if (!is_array($stored)) {
            $this->logEvent('private.files.upload_rejected', [
                'reason' => $storage->uploadError() ?? 'store_failed',
                'private_user_id' => $userId,
            ]);

            return $this->redirect($this->documentsUrlWithError('upload_failed'));
        }

        $created = $this->privateDocumentRepository()->create(
            $userId,
            (string) $stored['documentId'],
            (string) $stored['storagePath'],
            (string) $stored['originalName'],
            (string) $stored['extension'],
            (string) $stored['mimeType'],
            (int) $stored['sizeBytes'],
            $userId,
            $categoryId,
            is_string($stored['scanStatus'] ?? null) ? (string) $stored['scanStatus'] : PrivateDocumentRepository::SCAN_STATUS_CLEAN,
            is_int($stored['scanExitCode'] ?? null) ? (int) $stored['scanExitCode'] : null,
            is_int($stored['scanDurationMs'] ?? null) ? (int) $stored['scanDurationMs'] : null,
            is_string($stored['scanError'] ?? null) ? (string) $stored['scanError'] : '',
            is_string($stored['scannedAt'] ?? null) ? (string) $stored['scannedAt'] : null
        );
        if (!is_array($created)) {
            $storage->deleteStoredDocument((string) $stored['storagePath'], (string) $stored['documentId']);
            $this->logEvent('private.files.upload_rejected', [
                'reason' => 'database_failed',
                'document_id' => (string) $stored['documentId'],
                'private_user_id' => $userId,
            ]);

            return $this->redirect($this->documentsUrlWithError('upload_failed'));
        }

        $this->logEvent('private.files.uploaded', [
            'document_id' => (string) $stored['documentId'],
            'private_user_id' => $userId,
            'size_bytes' => (int) $stored['sizeBytes'],
            'storage_path' => (string) $stored['storagePath'],
            'scan_status' => is_string($stored['scanStatus'] ?? null) ? (string) $stored['scanStatus'] : PrivateDocumentRepository::SCAN_STATUS_CLEAN,
        ]);

        return $this->redirect($this->documentsUrlWithNotice(
            $this->documentUploadNoticeForScanStatus(
                is_string($stored['scanStatus'] ?? null) ? (string) $stored['scanStatus'] : PrivateDocumentRepository::SCAN_STATUS_CLEAN
            )
        ));
    }

    private function handleFilesCategoryCreate(Request $request): Response
    {
        $userId = $this->userIdOrAccessDenied($request);
        if ($userId === null) {
            return $this->handleFilesAccessDenied();
        }

        if ($request->method() !== self::METHOD_POST || !$this->guard()->validateCsrf($request, self::CSRF_DOCUMENTS)) {
            $this->logEvent('private.files.category_rejected', [
                'reason' => $request->method() !== self::METHOD_POST ? 'method_not_allowed' : 'csrf_invalid',
                'private_user_id' => $userId,
            ]);

            return $this->redirect($this->documentsUrlWithError('invalid_request'));
        }

        $body = $request->body();
        $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : 'save_category';
        $repository = $this->privateDocumentRepository();

        if ($action === 'delete_category') {
            $categoryId = $this->normalizeNumericId($body['category_id'] ?? null);
            if (!$repository->deactivateCategory($userId, $categoryId)) {
                $this->logEvent('private.files.category_rejected', [
                    'reason' => 'delete_failed',
                    'private_user_id' => $userId,
                    'category_id' => $categoryId,
                ]);

                return $this->redirect($this->documentsUrlWithError('category_delete_failed'));
            }

            $this->logEvent('private.files.category_deleted', [
                'private_user_id' => $userId,
                'category_id' => $categoryId,
            ]);

            return $this->redirect($this->documentsUrlWithNotice('document_category_deleted'));
        }

        if ($action !== 'save_category') {
            return $this->redirect($this->documentsUrlWithError('invalid_request'));
        }

        $categoryId = $this->normalizeNumericId($body['category_id'] ?? null);
        $category = $repository->saveCategory(
            $userId,
            $categoryId,
            is_string($body['category_name'] ?? null) ? (string) $body['category_name'] : '',
            is_string($body['category_color'] ?? null) ? (string) $body['category_color'] : ''
        );
        if (!is_array($category)) {
            $this->logEvent('private.files.category_rejected', [
                'reason' => 'invalid_category',
                'private_user_id' => $userId,
            ]);

            return $this->redirect($this->documentsUrlWithError('category_failed'));
        }

        $this->logEvent('private.files.category_created', [
            'private_user_id' => $userId,
            'category_id' => (int) ($category['id'] ?? 0),
        ]);

        return $this->redirect($this->documentsUrlWithNotice($categoryId > 0 ? 'document_category_saved' : 'document_category_created'));
    }

    private function handleFilesDelete(Request $request, string $documentId): Response
    {
        $userId = $this->userIdOrAccessDenied($request);
        if ($userId === null) {
            return $this->handleFilesAccessDenied();
        }

        if ($request->method() !== self::METHOD_POST || !$this->guard()->validateCsrf($request, self::CSRF_DOCUMENTS)) {
            $this->logEvent('private.files.delete_rejected', [
                'reason' => $request->method() !== self::METHOD_POST ? 'method_not_allowed' : 'csrf_invalid',
                'private_user_id' => $userId,
            ]);

            return $this->redirect($this->documentsUrlWithError('delete_forbidden'));
        }

        $documentId = $this->normalizeDocumentId($documentId);
        if ($documentId === '') {
            $this->logEvent('private.files.delete_rejected', [
                'reason' => 'invalid_document_id',
                'private_user_id' => $userId,
            ]);

            return $this->redirect($this->documentsUrlWithError('delete_not_found'));
        }

        $document = $this->privateDocumentRepository()->findByDocumentIdAndUser($documentId, $userId);
        if (!is_array($document)) {
            $this->logEvent('private.files.delete_rejected', [
                'reason' => 'document_not_found',
                'document_id' => $documentId,
                'private_user_id' => $userId,
            ]);

            return $this->redirect($this->documentsUrlWithError('delete_not_found'));
        }

        $storagePath = is_string($document['storagePath'] ?? null) ? trim((string) $document['storagePath']) : '';
        $deactivated = $this->privateDocumentRepository()->deactivateByDocumentId($documentId, $userId);
        if (!$deactivated) {
            $this->logEvent('private.files.delete_rejected', [
                'reason' => 'database_failed',
                'document_id' => $documentId,
                'private_user_id' => $userId,
            ]);

            return $this->redirect($this->documentsUrlWithError('upload_failed'));
        }

        $this->privateDocumentStorage()->deleteStoredDocument($storagePath, $documentId);
        $this->logEvent('private.files.deleted', [
            'document_id' => $documentId,
            'private_user_id' => $userId,
        ]);

        return $this->redirect($this->documentsUrlWithNotice('document_deleted'));
    }

    private function handleFilesAccessDenied(?string $documentId = null): Response
    {
        $this->logEvent('private.files.access_denied', [
            'reason' => 'forbidden',
            'document_id' => $documentId,
            'identifier' => AppEventLogger::maskIdentifier($this->auth->currentIdentifier()),
        ]);

        return $this->withPrivateHeaders(new Response(
            403,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
            'Forbidden'
        ));
    }

    private function renderRentalDashboard(int $userId): Response
    {
        $propertyIds = $this->authorizedPropertyIds($userId);
        $properties = $propertyIds === []
            ? []
            : $this->rentalPropertyRepository()->listByIds($propertyIds, self::MAX_RENTAL_LIST);
        $units = $propertyIds === []
            ? []
            : $this->rentalUnitRepository()->listByPropertyIds($propertyIds, self::MAX_RENTAL_LIST);
        $tenants = $this->rentalLifecycleRepository()->listTenants($propertyIds, self::MAX_RENTAL_LIST);
        $leases = $this->rentalLifecycleRepository()->listLeases($propertyIds, self::MAX_RENTAL_LIST);
        $year = (int) date('Y');
        $month = (int) date('n');
        $dashboardStats = $this->rentalDashboardService()->build($year, $month, $propertyIds);
        $documents = $this->rentalLifecycleRepository()->listDocuments($propertyIds, self::MAX_RENTAL_LIST);
        $agencyDocuments = $this->agencyImportRepository()->listRecentDocumentsForUser($userId, self::MAX_RENTAL_LIST);

        $pendingAgencyDocuments = 0;
        foreach ($agencyDocuments as $document) {
            $status = is_array($document) && is_string($document['reviewStatus'] ?? null)
                ? (string) $document['reviewStatus']
                : '';
            if (in_array($status, ['pending', 'to_review', 'new'], true)) {
                ++$pendingAgencyDocuments;
            }
        }

        return $this->render('modules/real-estate-rental/dashboard', array_merge(
            $this->rentalBaseViewModel('Tableau de bord locatif', '', ''),
            [
                'rentalCurrentSection' => 'dashboard',
                'rentalDashboardStats' => array_merge($dashboardStats, [
                    'year' => $year,
                    'month' => $month,
                    'propertyCount' => count($properties),
                    'unitCount' => count($units),
                    'tenantCount' => count($tenants),
                    'activeLeaseCount' => count(array_filter(
                        $leases,
                        static fn (array $lease): bool => in_array((string) ($lease['status'] ?? ''), ['draft', 'validated'], true)
                    )),
                    'documentCount' => count($documents),
                    'agencyDocumentCount' => count($agencyDocuments),
                    'pendingAgencyDocumentCount' => $pendingAgencyDocuments,
                    'taxSummaryUrl' => private_portal_url('tax_dashboard'),
                ]),
                'rentalProperties' => $this->objectsToArrays($properties),
                'rentalRecentDocuments' => array_slice($documents, 0, 8),
                'agencyImportDocuments' => array_slice($agencyDocuments, 0, 8),
            ]
        ));
    }

    private function renderRentalProperties(int $userId, string $notice = '', string $error = ''): Response
    {
        $propertyIds = $this->authorizedPropertyIds($userId);
        $properties = $propertyIds === []
            ? []
            : $this->rentalPropertyRepository()->listByIds($propertyIds, self::MAX_RENTAL_LIST);
        $lessors = $this->rentalLessorRepository()->listForUser($userId, self::MAX_RENTAL_LIST);

        return $this->render('modules/real-estate-rental/properties', array_merge(
            $this->rentalBaseViewModel('Propriétés', $notice, $error),
            [
                'rentalCurrentSection' => 'personal',
                'rentalCurrentSubsection' => 'properties',
                'rentalProperties' => $this->objectsToArrays($properties),
                'rentalLessors' => $lessors,
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
                'rentalLessors' => $this->rentalLessorRepository()->listForUser($userId, self::MAX_RENTAL_LIST),
            ]
        ));
    }

    /**
     * @param array<int, object> $properties
     * @param array<int, object> $units
     */
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
                'rentalLessors' => $this->rentalLessorRepository()->listForUser($userId, self::MAX_RENTAL_LIST),
            ]
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $members
     */
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

    /**
     * @param array<int, object> $properties
     * @param array<int, object> $units
     * @param array<int, array<string, mixed>> $tenants
     */
    private function renderRentalTenants(
        array $properties,
        array $units,
        array $tenants,
        string $notice = '',
        string $error = ''
    ): Response {
        return $this->render('modules/real-estate-rental/tenants', array_merge(
            $this->rentalBaseViewModel('Locataires', $notice, $error),
            [
                'rentalCurrentSection' => 'personal',
                'rentalCurrentSubsection' => 'tenants',
                'rentalProperties' => $this->objectsToArrays($properties),
                'rentalUnits' => $this->objectsToArrays($units),
                'rentalTenants' => $tenants,
            ]
        ));
    }

    /**
     * @param array<int, object> $properties
     * @param array<int, object> $units
     * @param array<int, array<string, mixed>> $tenants
     * @param array<int, array<string, mixed>> $leases
     */
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

    /**
     * @param array<int, array<string, mixed>> $leases
     * @param array<int, array<string, mixed>> $rents
     */
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
     * @param array<int, array<string, mixed>> $payments
     */
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

    /**
     * @param array<int, object> $properties
     * @param array<int, object> $units
     * @param array<int, array<string, mixed>> $expenses
     */
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
                'rentalExpenseCategories' => RentalExpenseCategoryCatalog::options(),
            ]
        ));
    }

    /**
     * @param array<int, object> $properties
     * @param array<int, object> $units
     * @param array<int, array<string, mixed>> $regularizations
     * @param array<string, mixed>|null $preview
     */
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
                'rentalChargeRegularizations' => $regularizations,
                'rentalChargeRegularizationPreview' => $preview,
            ]
        ));
    }

    /**
     * @param array<int, object> $properties
     * @param array<int, object> $units
     * @param array<int, array<string, mixed>> $leases
     * @param array<int, array<string, mixed>> $documents
     */
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

    private function renderRentalAgencies(int $userId, string $notice = '', string $error = ''): Response
    {
        $propertyIds = $this->authorizedPropertyIds($userId);
        $properties = $propertyIds === []
            ? []
            : $this->rentalPropertyRepository()->listByIds($propertyIds, self::MAX_RENTAL_LIST);
        $units = $propertyIds === []
            ? []
            : $this->rentalUnitRepository()->listByPropertyIds($propertyIds, self::MAX_RENTAL_LIST);

        return $this->render('modules/real-estate-rental/agencies', array_merge(
            $this->rentalBaseViewModel('Agences', $notice, $error),
            [
                'rentalCurrentSection' => 'agency',
                'rentalCurrentSubsection' => 'agencies',
                'agencyImportAgencies' => $this->agencyImportRepository()->listAgencies($userId, 100),
                'agencyImportUnitMappings' => $this->agencyImportRepository()->listUnitMappings($userId, 200),
                'agencyImportProperties' => $this->objectsToArrays($properties),
                'agencyImportUnits' => $this->objectsToArrays($units),
            ]
        ));
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
            $this->rentalBaseViewModel('Documents agence à valider', $notice, $error),
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
                'agencyReviewCategories' => $this->agencyReviewCategories(),
                'agencyReviewSensitiveCategories' => $this->agencyFiscalReviewPolicy()->fiscalReviewCategories(),
                'agencyReviewLineFeedbackId' => $lineFeedbackId,
                'agencyReviewLineNotice' => $this->rentalNotice($lineNotice),
                'agencyReviewLineError' => $this->rentalError($lineError),
            ]
        ));
    }

    /**
     * @param array<string, mixed> $summary
     */
    /**
     * @param array<string, mixed> $summary
     * @param array<int, array<string, mixed>> $properties
     * @param array<int, array<string, mixed>> $tenants
     */
    private function renderRentalSummary(array $summary, array $properties = [], array $tenants = []): Response
    {
        return $this->render('modules/real-estate-rental/summary', array_merge(
            $this->rentalBaseViewModel('Synthèse annuelle locative', '', ''),
            [
                'rentalCurrentSection' => 'reports',
                'rentalCurrentSubsection' => 'summary',
                'rentalSummary' => $summary,
                'rentalProperties' => $properties,
                'rentalTenants' => $tenants,
            ]
        ));
    }

    private function renderTaxYear(int $userId, int $year, string $notice = '', string $error = ''): Response
    {
        return $this->render('modules/tax-declaration-helper/year', $this->taxViewModel(
            $userId,
            $year,
            'Aide impôts ' . $year,
            $notice,
            $error
        ));
    }

    private function renderTaxManualEntries(int $userId, int $year, string $notice = '', string $error = ''): Response
    {
        return $this->render('modules/tax-declaration-helper/manual', $this->taxViewModel(
            $userId,
            $year,
            'Revenus manuels ' . $year,
            $notice,
            $error
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function taxViewModel(
        int $userId,
        int $year,
        string $title,
        string $notice = '',
        string $error = ''
    ): array {
        return [
            'privatePageTitle' => $title,
            'taxSummary' => $this->taxDeclarationSummaryService()->build($userId, $year, $this->authorizedPropertyIds($userId)),
            'taxManualEntries' => $this->taxDeclarationRepository()->listManualEntries($userId, $year),
            'taxCsrfToken' => csrf_token(self::CSRF_TAX),
            'taxNotice' => $this->taxNotice($notice),
            'taxError' => $this->taxError($error),
            'taxMailDefaults' => [
                'recipientEmail' => $this->auth->currentIdentifier(),
                'subject' => $this->privateMailTemplate('tax_subject', 'Aide impôts - document PDF'),
                'message' => $this->privateMailTemplate('tax_body'),
            ],
            'taxUrls' => [
                'dashboard' => private_portal_url('tax_dashboard'),
                'year' => $this->taxYearUrl($year),
                'manual' => $this->taxManualUrl($year),
                'controls' => $this->taxYearUrl($year) . '/controle',
                'documents' => $this->taxYearUrl($year) . '/documents',
                'exportCsv' => $this->taxYearUrl($year) . '/export?format=csv',
                'exportPdf' => $this->taxYearUrl($year) . '/export?format=pdf',
            ],
            'privateDashboardLogoutUrl' => private_portal_url('logout'),
            'privateLogoutCsrfToken' => csrf_token('private_logout'),
        ];
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function discussionViewModel(int $userId, array $extra = []): array
    {
        return array_merge([
            'privatePageTitle' => 'Discussions famille',
            'discussionCurrentUserId' => $userId,
            'discussionCsrfToken' => csrf_token(self::CSRF_DISCUSSIONS),
            'discussionUrls' => [
                'index' => private_portal_url('discussion_index'),
                'new' => private_portal_url('discussion_new'),
                'apiConversations' => private_portal_url('discussion_api_conversations'),
                'apiCryptoDevices' => private_portal_url('discussion_api_crypto_devices'),
                'apiClientEvents' => private_portal_url('discussion_api_client_events'),
                'files' => private_portal_url('discussion_files'),
            ],
            'discussionInviteDefaults' => [
                'subject' => $this->privateMailTemplate('discussion_invite_subject', 'Invitation à rejoindre les discussions famille'),
                'message' => $this->privateMailTemplate('discussion_invite_body'),
            ],
            'privateDashboardLogoutUrl' => private_portal_url('logout'),
            'privateLogoutCsrfToken' => csrf_token('private_logout'),
        ], $extra);
    }

    /**
     * @return array<int, string>
     */
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

    /**
     * @return array<string, mixed>|null
     */
    private function optionalRentalUploadedFile(Request $request): ?array
    {
        $files = $request->files();
        $uploadedFile = is_array($files[self::RENTAL_DOCUMENT_UPLOAD_FIELD] ?? null)
            ? $files[self::RENTAL_DOCUMENT_UPLOAD_FIELD]
            : null;
        if (!is_array($uploadedFile)) {
            return null;
        }

        $error = is_numeric($uploadedFile['error'] ?? null) ? (int) $uploadedFile['error'] : UPLOAD_ERR_NO_FILE;

        return $error === UPLOAD_ERR_NO_FILE ? null : $uploadedFile;
    }

    /**
     * @param array<string, mixed> $uploadedFile
     */
    private function storeRentalSupportingDocument(
        array $uploadedFile,
        int $propertyId,
        ?int $unitId,
        ?int $leaseId,
        ?int $expenseId,
        int $userId
    ): bool {
        $storage = $this->privateDocumentStorage();
        $metadata = $storage->validateUploadedFile($uploadedFile);
        $documentId = $storage->generateDocumentId();
        $stored = $metadata !== null && $documentId !== '' ? $storage->storeUploadedFile($metadata, $documentId) : null;
        if (!is_array($stored)) {
            return false;
        }

        $created = $this->rentalLifecycleRepository()->createDocument(
            $propertyId,
            $unitId !== null && $unitId > 0 ? $unitId : null,
            $leaseId !== null && $leaseId > 0 ? $leaseId : null,
            (string) $stored['documentId'],
            (string) $stored['storagePath'],
            (string) $stored['originalName'],
            (string) $stored['extension'],
            (string) $stored['mimeType'],
            (int) $stored['sizeBytes'],
            $userId,
            $expenseId !== null && $expenseId > 0 ? $expenseId : null
        );
        if (!is_array($created)) {
            $storage->deleteStoredDocument((string) $stored['storagePath'], (string) $stored['documentId']);
            return false;
        }

        $this->logEvent('private.rental_document.uploaded', [
            'private_user_id' => $userId,
            'rental_property_id' => $propertyId,
            'rental_expense_id' => $expenseId ?? 0,
            'document_id' => (string) $stored['documentId'],
        ]);

        return true;
    }

    /**
     * @param array<int, string> $documentIds
     * @param array<int, int> $propertyIds
     */
    private function deleteRentalDocuments(array $documentIds, array $propertyIds, int $userId): int
    {
        $deleted = 0;
        foreach ($documentIds as $documentId) {
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

            $this->privateDocumentStorage()->deleteStoredDocument(
                is_string($document['storagePath'] ?? null) ? (string) $document['storagePath'] : '',
                $documentId
            );
            ++$deleted;
            $this->logEvent('private.rental_document.deleted', [
                'private_user_id' => $userId,
                'rental_property_id' => $propertyId,
                'document_id' => $documentId,
            ]);
        }

        return $deleted;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<int, int> $propertyIds
     */
    private function sendRentalDocumentsByEmail(array $body, array $propertyIds, int $userId): bool
    {
        $to = $this->normalizeEmailInput($body['recipient_email'] ?? null);
        $documentIds = $this->documentIdsFromPayload($body['document_ids'] ?? []);
        if ($to === '' || $documentIds === []) {
            return false;
        }

        $attachments = [];
        foreach ($documentIds as $documentId) {
            $document = $this->rentalLifecycleRepository()->findDocumentByDocumentId($documentId);
            $propertyId = is_array($document) && is_numeric($document['rentalPropertyId'] ?? null)
                ? (int) $document['rentalPropertyId']
                : 0;
            if (!is_array($document) || !in_array($propertyId, $propertyIds, true)) {
                continue;
            }

            $storagePath = is_string($document['storagePath'] ?? null) ? (string) $document['storagePath'] : '';
            $absolutePath = $this->privateDocumentStorage()->absolutePath($storagePath);
            if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
                continue;
            }

            $attachments[] = [
                'path' => $absolutePath,
                'name' => $this->sanitizeDownloadFilename((string) ($document['originalName'] ?? 'document')),
                'mime' => is_string($document['mimeType'] ?? null) ? (string) $document['mimeType'] : 'application/octet-stream',
            ];
        }

        if ($attachments === []) {
            return false;
        }

        $variables = [
            'email' => $to,
        ];
        $subject = $this->mailSubjectFromBody($body, 'rental_subject', 'Document locatif', $variables);
        $html = $this->mailHtmlFromBody($body, 'rental_body', $variables);
        $sent = $this->sendPrivateMail($to, $subject, $html, $attachments, $userId, true);
        $this->logEvent($sent ? 'private.rental_document.email_sent' : 'private.rental_document.email_failed', [
            'private_user_id' => $userId,
            'recipient' => AppEventLogger::maskIdentifier($to),
            'attachment_count' => count($attachments),
        ]);

        return $sent;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<int, int> $propertyIds
     */
    private function sendRentalPaymentRequest(array $body, array $propertyIds, int $userId): string
    {
        $rentId = $this->normalizeNumericId($body['rent_id'] ?? null);
        $preview = $this->rentalPaymentRequestService()->previewForRent($rentId, $propertyIds);
        $propertyId = is_array($preview) && is_numeric($preview['propertyId'] ?? null)
            ? (int) $preview['propertyId']
            : 0;
        if (!is_array($preview) || !$this->canWriteByPropertyId($propertyId, $userId)) {
            return 'property_forbidden';
        }

        $result = $this->rentalPaymentRequestService()->send(
            $rentId,
            $propertyIds,
            is_string($body['recipient_email'] ?? null) ? (string) $body['recipient_email'] : '',
            is_string($body['subject'] ?? null) ? (string) $body['subject'] : '',
            is_string($body['message'] ?? null) ? (string) $body['message'] : '',
            is_string($body['signature'] ?? null) ? (string) $body['signature'] : '',
            $userId
        );

        $status = (string) ($result['status'] ?? 'failed');
        $request = is_array($result['request'] ?? null) ? $result['request'] : null;
        $recipient = is_string($result['recipient'] ?? null) ? (string) $result['recipient'] : '';
        $this->logEvent(
            in_array($status, ['sent', 'duplicate'], true) ? 'private.rental_payment_request.sent' : 'private.rental_payment_request.failed',
            [
                'private_user_id' => $userId,
                'rental_property_id' => $propertyId,
                'rental_rent_id' => $rentId,
                'rental_payment_request_id' => is_array($request) && is_numeric($request['id'] ?? null) ? (int) $request['id'] : 0,
                'recipient' => AppEventLogger::maskIdentifier($recipient),
                'status' => $status,
            ]
        );

        return match ($status) {
            'sent', 'duplicate' => $status,
            'invalid_email' => 'payment_request_invalid_email',
            'invalid_content', 'invalid_rent' => 'rental_write_failed',
            'failed' => $this->lastPrivateMailFailure === 'smtp_required' ? 'smtp_required' : 'email_failed',
            default => 'email_failed',
        };
    }

    /**
     * @param array<string, mixed> $body
     * @param array<int, int> $propertyIds
     */
    private function downloadRentalPaymentRequestPdf(array $body, array $propertyIds, int $userId): ?Response
    {
        $rentId = $this->normalizeNumericId($body['rent_id'] ?? null);
        $preview = $this->rentalPaymentRequestService()->previewForRent($rentId, $propertyIds);
        $propertyId = is_array($preview) && is_numeric($preview['propertyId'] ?? null)
            ? (int) $preview['propertyId']
            : 0;
        if (!is_array($preview) || !$this->canWriteByPropertyId($propertyId, $userId)) {
            return null;
        }

        $result = $this->rentalPaymentRequestService()->recordPdfExport(
            $rentId,
            $propertyIds,
            is_string($body['recipient_email'] ?? null) ? (string) $body['recipient_email'] : '',
            is_string($body['subject'] ?? null) ? (string) $body['subject'] : '',
            is_string($body['message'] ?? null) ? (string) $body['message'] : '',
            is_string($body['signature'] ?? null) ? (string) $body['signature'] : '',
            $userId
        );
        $request = is_array($result['request'] ?? null) ? $result['request'] : null;
        if ((string) ($result['status'] ?? '') !== 'exported' || !is_array($request)) {
            return null;
        }

        $this->logEvent('private.rental_payment_request.pdf_downloaded', [
            'private_user_id' => $userId,
            'rental_property_id' => $propertyId,
            'rental_rent_id' => $rentId,
            'rental_payment_request_id' => is_numeric($request['id'] ?? null) ? (int) $request['id'] : 0,
            'recipient' => AppEventLogger::maskIdentifier(is_string($result['recipient'] ?? null) ? (string) $result['recipient'] : ''),
        ]);

        $filename = sprintf(
            'demande-paiement-%s.pdf',
            preg_replace('/[^0-9-]+/', '-', str_replace('/', '-', (string) ($preview['periodLabel'] ?? date('m-Y')))) ?: date('m-Y')
        );
        $content = $this->rentalPaymentRequestService()->pdf($request, $preview);
        $generatedDocument = $this->storeRentalGeneratedPaymentNotice($preview, $request, $content, $userId);

        return $this->withPrivateHeaders(new Response(200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'X-Rental-Generated-Document' => is_array($generatedDocument) && is_string($generatedDocument['documentId'] ?? null) ? (string) $generatedDocument['documentId'] : '',
        ], $content));
    }

    /**
     * @param array<string, mixed> $preview
     * @param array<string, mixed> $request
     * @return array<string, mixed>|null
     */
    private function storeRentalGeneratedPaymentNotice(array $preview, array $request, string $content, int $userId): ?array
    {
        $snapshot = [
            'documentType' => 'payment_notice',
            'paymentRequestId' => is_numeric($request['id'] ?? null) ? (int) $request['id'] : 0,
            'rentId' => is_numeric($preview['rentId'] ?? null) ? (int) $preview['rentId'] : 0,
            'leaseId' => is_numeric($preview['leaseId'] ?? null) ? (int) $preview['leaseId'] : 0,
            'propertyId' => is_numeric($preview['propertyId'] ?? null) ? (int) $preview['propertyId'] : 0,
            'unitId' => is_numeric($preview['unitId'] ?? null) ? (int) $preview['unitId'] : 0,
            'tenantName' => is_string($preview['tenantName'] ?? null) ? (string) $preview['tenantName'] : '',
            'propertyName' => is_string($preview['propertyName'] ?? null) ? (string) $preview['propertyName'] : '',
            'unitLabel' => is_string($preview['unitLabel'] ?? null) ? (string) $preview['unitLabel'] : '',
            'periodLabel' => is_string($preview['periodLabel'] ?? null) ? (string) $preview['periodLabel'] : '',
            'amountDue' => is_string($preview['amountDue'] ?? null) ? (string) $preview['amountDue'] : '',
            'amountPaid' => is_string($preview['amountPaid'] ?? null) ? (string) $preview['amountPaid'] : '',
            'balanceDue' => is_string($preview['balanceDue'] ?? null) ? (string) $preview['balanceDue'] : '',
            'subject' => is_string($request['subject'] ?? null) ? (string) $request['subject'] : '',
        ];
        $idempotencySnapshot = $snapshot;
        $idempotencyKey = hash('sha256', json_encode($idempotencySnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: serialize($idempotencySnapshot));
        $existing = $this->rentalLifecycleRepository()->findGeneratedDocumentByIdempotencyKey($idempotencyKey);
        if (is_array($existing)) {
            return $existing;
        }

        $documentId = $this->privateDocumentStorage()->generateDocumentId();
        $filename = sprintf(
            'avis-paiement-%s.pdf',
            preg_replace('/[^0-9-]+/', '-', str_replace('/', '-', (string) $snapshot['periodLabel'])) ?: date('m-Y')
        );
        $stored = $documentId !== '' ? $this->privateDocumentStorage()->storeGeneratedDocument($content, $documentId, $filename) : null;
        if (!is_array($stored)) {
            return null;
        }

        return $this->rentalLifecycleRepository()->createGeneratedDocument(
            (int) $snapshot['rentId'],
            (int) $snapshot['leaseId'],
            null,
            (int) $snapshot['propertyId'],
            (int) $snapshot['unitId'],
            'payment_notice',
            (string) $stored['documentId'],
            (string) $stored['storagePath'],
            (string) $stored['originalName'],
            (string) $stored['mimeType'],
            (int) $stored['sizeBytes'],
            (string) $stored['sha256Hash'],
            $idempotencyKey,
            $snapshot,
            $userId
        );
    }

    /**
     * @param array<string, mixed> $body
     * @param array<int, int> $propertyIds
     */
    private function sendRentalReceiptByEmail(array $body, array $propertyIds, int $userId): bool
    {
        $paymentId = $this->normalizeNumericId($body['payment_id'] ?? null);
        $payment = $this->rentalLifecycleRepository()->findPaymentById($paymentId);
        $propertyId = is_array($payment) && is_numeric($payment['rentalPropertyId'] ?? null)
            ? (int) $payment['rentalPropertyId']
            : 0;
        if (!is_array($payment) || !in_array($propertyId, $propertyIds, true)) {
            return false;
        }

        $documentType = is_string($body['document_type'] ?? null) ? (string) $body['document_type'] : RentalReceiptService::DOCUMENT_RECEIPT;
        $document = $this->rentalReceiptService()->generateForPayment($paymentId, $propertyIds, $userId, $documentType);
        $content = is_array($document) ? $this->rentalReceiptService()->content($document) : null;
        if (!is_array($document) || !is_string($content)) {
            return false;
        }

        $leaseId = is_numeric($payment['rentalLeaseId'] ?? null) ? (int) $payment['rentalLeaseId'] : 0;
        $lease = $this->rentalLifecycleRepository()->findLeaseById($leaseId);
        $tenantId = is_array($lease) && is_numeric($lease['rentalTenantId'] ?? null) ? (int) $lease['rentalTenantId'] : 0;
        $tenant = $this->rentalLifecycleRepository()->findTenantById($tenantId);
        if (!is_array($lease) || !is_array($tenant)) {
            return false;
        }

        $to = $this->normalizeEmailInput($body['recipient_email'] ?? null);
        if ($to === '') {
            $to = $this->normalizeEmailInput($tenant['email'] ?? null);
        }
        if ($to === '') {
            return false;
        }

        $variables = [
            'email' => $to,
        ];
        $isReceipt = (string) ($document['documentType'] ?? '') === RentalReceiptService::DOCUMENT_RECEIPT;
        $subject = $this->mailSubjectFromBody($body, 'rental_subject', $isReceipt ? 'Quittance de loyer' : 'Reçu partiel de loyer', $variables);
        $html = $this->mailHtmlFromBody($body, 'rental_body', $variables);
        $filename = $this->sanitizeDownloadFilename((string) ($document['originalName'] ?? ($isReceipt ? 'quittance.pdf' : 'recu-partiel.pdf')));
        $sent = $this->sendPrivateMail($to, $subject, $html, [[
            'content' => $content,
            'name' => $filename,
            'mime' => is_string($document['mimeType'] ?? null) ? (string) $document['mimeType'] : 'application/pdf',
        ]], $userId, true);

        $this->logEvent($sent ? 'private.rental_receipt.email_sent' : 'private.rental_receipt.email_failed', [
            'private_user_id' => $userId,
            'rental_property_id' => $propertyId,
            'rental_payment_id' => $paymentId,
            'rental_generated_document_id' => is_numeric($document['id'] ?? null) ? (int) $document['id'] : 0,
            'document_type' => (string) ($document['documentType'] ?? ''),
            'recipient' => AppEventLogger::maskIdentifier($to),
        ]);

        return $sent;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<int, int> $propertyIds
     */
    private function downloadRentalReceiptPdf(array $body, array $propertyIds, int $userId): ?Response
    {
        $paymentId = $this->normalizeNumericId($body['payment_id'] ?? null);
        $documentType = is_string($body['document_type'] ?? null) ? (string) $body['document_type'] : RentalReceiptService::DOCUMENT_RECEIPT;
        $document = $this->rentalReceiptService()->generateForPayment($paymentId, $propertyIds, $userId, $documentType);
        $propertyId = is_array($document) && is_numeric($document['rentalPropertyId'] ?? null)
            ? (int) $document['rentalPropertyId']
            : 0;
        $content = is_array($document) ? $this->rentalReceiptService()->content($document) : null;
        if (!is_array($document) || !is_string($content) || !in_array($propertyId, $propertyIds, true)) {
            return null;
        }

        $filename = $this->sanitizeDownloadFilename((string) ($document['originalName'] ?? 'document-locatif.pdf'));
        $mimeType = is_string($document['mimeType'] ?? null) ? (string) $document['mimeType'] : 'application/pdf';

        $this->logEvent('private.rental_receipt.downloaded', [
            'private_user_id' => $userId,
            'rental_property_id' => $propertyId,
            'rental_payment_id' => $paymentId,
            'rental_generated_document_id' => is_numeric($document['id'] ?? null) ? (int) $document['id'] : 0,
            'document_type' => (string) ($document['documentType'] ?? ''),
        ]);

        return $this->withPrivateHeaders(new Response(200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ], $content));
    }

    /**
     * @param array<string, mixed> $body
     * @param array<int, int> $propertyIds
     */
    private function downloadRentalLeasePdf(array $body, array $propertyIds, int $userId): ?Response
    {
        $leaseId = $this->normalizeNumericId($body['lease_id'] ?? null);
        $lease = $this->rentalLifecycleRepository()->findLeaseById($leaseId);
        $propertyId = is_array($lease) && is_numeric($lease['rentalPropertyId'] ?? null)
            ? (int) $lease['rentalPropertyId']
            : 0;
        if (!is_array($lease) || !in_array($propertyId, $propertyIds, true)) {
            return null;
        }

        $tenantId = is_numeric($lease['rentalTenantId'] ?? null) ? (int) $lease['rentalTenantId'] : 0;
        $tenant = $this->rentalLifecycleRepository()->findTenantById($tenantId);
        $property = $this->rentalPropertyRepository()->findById($propertyId);
        $unitId = is_numeric($lease['rentalUnitId'] ?? null) ? (int) $lease['rentalUnitId'] : 0;
        $unit = $this->rentalUnitRepository()->findById($unitId);
        if (!is_array($tenant) || $property === null) {
            return null;
        }

        $filename = sprintf('bail-%d.pdf', $leaseId);
        $this->logEvent('private.rental_lease.downloaded', [
            'private_user_id' => $userId,
            'rental_property_id' => $propertyId,
            'rental_lease_id' => $leaseId,
        ]);

        return $this->withPrivateHeaders(new Response(200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
        ], $this->rentalLeasePdf($lease, $tenant, $property->name, $unit?->label ?? '')));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function sendTaxPdfByEmail(array $body, int $userId, int $year): bool
    {
        $to = $this->normalizeEmailInput($body['recipient_email'] ?? null);
        if ($to === '') {
            return false;
        }

        $summary = $this->taxDeclarationSummaryService()->build($userId, $year, $this->authorizedPropertyIds($userId));
        $variables = [
            'email' => $to,
        ];
        $subject = $this->mailSubjectFromBody($body, 'tax_subject', 'Aide impôts - document PDF', $variables);
        $html = $this->mailHtmlFromBody($body, 'tax_body', $variables);
        $sent = $this->sendPrivateMail($to, $subject, $html, [[
            'content' => $this->taxDeclarationSummaryService()->pdf($summary),
            'name' => sprintf('aide-impots-%d.pdf', $year),
            'mime' => 'application/pdf',
        ]], $userId);

        $this->logEvent($sent ? 'private.tax_pdf.email_sent' : 'private.tax_pdf.email_failed', [
            'private_user_id' => $userId,
            'recipient' => AppEventLogger::maskIdentifier($to),
            'year' => $year,
        ]);

        return $sent;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function sendDiscussionInvitation(array $body, int $actorUserId): bool
    {
        $email = $this->normalizeEmailInput($body['recipient_email'] ?? null);
        if ($email === '') {
            return false;
        }

        $user = $this->privateUserRepository()->findByEmail($email);
        $userId = is_array($user) && is_numeric($user['id'] ?? null) ? (int) $user['id'] : 0;
        if ($userId <= 0) {
            $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_ARGON2ID);
            if (!is_string($hash)) {
                return false;
            }
            $createdUserId = $this->privateUserRepository()->create($email, $hash, 'invited');
            $userId = is_int($createdUserId) ? $createdUserId : 0;
        }

        if ($userId <= 0) {
            return false;
        }

        $this->modulePermissionRepository()->setUserModules($userId, ['discussions'], $this->auth->currentIdentifier());
        $token = $this->privateUserRepository()->createInviteToken($userId, $email);
        if ($token === null) {
            return false;
        }

        $activationUrl = app_url(private_route_resolver()->canonicalPath('activate') . '/' . rawurlencode($token));
        $variables = [
            'activation_url' => $activationUrl,
            'email' => $email,
        ];
        $subject = $this->mailSubjectFromBody(
            $body,
            'discussion_invite_subject',
            'Invitation à rejoindre les discussions famille',
            $variables
        );
        $message = is_string($body['message'] ?? null) && trim((string) $body['message']) !== ''
            ? (string) $body['message']
            : $this->privateMailTemplate('discussion_invite_body');
        $message = $this->renderPrivateMailTemplate($message, $variables);
        $sent = $this->sendPrivateMail($email, $subject, $this->plainTextToHtml($message), [], $actorUserId);
        $this->logEvent($sent ? 'private.discussion.invite_email_sent' : 'private.discussion.invite_email_failed', [
            'private_user_id' => $actorUserId,
            'invited_private_user_id' => $userId,
            'recipient' => AppEventLogger::maskIdentifier($email),
        ]);

        return $sent;
    }

    /**
     * @param array<int, array{path?: string, content?: string, name?: string, mime?: string}> $attachments
     */
    private function sendPrivateMail(
        string $to,
        string $subject,
        string $html,
        array $attachments,
        ?int $privateUserId = null,
        bool $requireUserSmtp = false
    ): bool {
        $this->lastPrivateMailFailure = null;
        if (!function_exists('send_private_email')) {
            $mailerPath = ROOT_PATH . '/core/mailer.php';
            if (is_file($mailerPath)) {
                require_once $mailerPath;
            }
        }

        $privateUserId ??= $this->currentPrivateUserId();
        if ($privateUserId !== null && $privateUserId > 0) {
            $userMailConfig = $this->privateUserMailSettingsRepository()->mailConfigForUser($privateUserId);
            if (is_array($userMailConfig) && function_exists('send_private_email_with_config')) {
                return send_private_email_with_config($to, $subject, $html, $attachments, $userMailConfig);
            }

            if ($requireUserSmtp && !$this->privateGlobalMailConfigAvailable()) {
                $this->lastPrivateMailFailure = 'smtp_required';
                return false;
            }
        }

        return function_exists('send_private_email') ? send_private_email($to, $subject, $html, $attachments) : false;
    }

    private function privateGlobalMailConfigAvailable(): bool
    {
        if (!function_exists('app_config')) {
            return false;
        }

        $config = app_config('private.mail', []);
        if (!is_array($config) || empty($config['enabled'])) {
            return false;
        }

        $host = is_scalar($config['smtp_host'] ?? null) ? trim((string) $config['smtp_host']) : '';
        $from = is_scalar($config['from_address'] ?? null) ? trim((string) $config['from_address']) : '';
        $port = is_numeric($config['smtp_port'] ?? null) ? (int) $config['smtp_port'] : 0;
        $user = is_scalar($config['smtp_user'] ?? null) ? trim((string) $config['smtp_user']) : '';
        $password = is_scalar($config['smtp_password'] ?? null) ? trim((string) $config['smtp_password']) : '';

        return $host !== ''
            && $port > 0
            && filter_var($from, FILTER_VALIDATE_EMAIL) !== false
            && ($user === '' || $password !== '');
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function memberSmtpSettingsWithGlobalFallback(array $settings): array
    {
        $global = $this->privateGlobalMailDisplaySettings();
        if ($global === [] || !$this->privateGlobalMailConfigAvailable()) {
            return $settings;
        }

        $hasOwnSettings = !empty($settings['configured']);
        if (!$hasOwnSettings) {
            foreach (['enabled', 'smtpHost', 'smtpPort', 'smtpUser', 'smtpEncryption', 'fromAddress', 'fromName', 'replyTo'] as $key) {
                $settings[$key] = $global[$key] ?? ($settings[$key] ?? null);
            }
        } else {
            foreach (['smtpHost', 'smtpUser', 'smtpEncryption', 'fromAddress', 'fromName', 'replyTo'] as $key) {
                if (trim((string) ($settings[$key] ?? '')) === '' && isset($global[$key])) {
                    $settings[$key] = $global[$key];
                }
            }

            if ((int) ($settings['smtpPort'] ?? 0) <= 0 && isset($global['smtpPort'])) {
                $settings['smtpPort'] = $global['smtpPort'];
            }
        }

        if (empty($settings['smtpPasswordConfigured']) && !empty($global['smtpPasswordConfigured'])) {
            $settings['smtpPasswordDisplayConfigured'] = true;
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private function privateGlobalMailDisplaySettings(): array
    {
        if (!function_exists('app_config')) {
            return [];
        }

        $config = app_config('private.mail', []);
        if (!is_array($config)) {
            return [];
        }

        return [
            'enabled' => !empty($config['enabled']),
            'smtpHost' => is_scalar($config['smtp_host'] ?? null) ? trim((string) $config['smtp_host']) : '',
            'smtpPort' => is_numeric($config['smtp_port'] ?? null) ? (int) $config['smtp_port'] : 587,
            'smtpUser' => is_scalar($config['smtp_user'] ?? null) ? trim((string) $config['smtp_user']) : '',
            'smtpPasswordConfigured' => is_scalar($config['smtp_password'] ?? null)
                && trim((string) $config['smtp_password']) !== '',
            'smtpEncryption' => is_scalar($config['smtp_encryption'] ?? null)
                ? strtolower(trim((string) $config['smtp_encryption']))
                : 'tls',
            'fromAddress' => is_scalar($config['from_address'] ?? null) ? trim((string) $config['from_address']) : '',
            'fromName' => is_scalar($config['from_name'] ?? null) ? trim((string) $config['from_name']) : 'Les Caramagnols',
            'replyTo' => is_scalar($config['reply_to'] ?? null) ? trim((string) $config['reply_to']) : '',
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, scalar|null> $variables
     */
    private function mailSubjectFromBody(array $body, string $templateKey, string $fallback, array $variables = []): string
    {
        $subject = is_string($body['subject'] ?? null) ? trim((string) $body['subject']) : '';
        if ($subject === '') {
            $subject = $this->privateMailTemplate($templateKey, $fallback);
        }

        return sanitize_text_field($this->renderPrivateMailTemplate($subject, $variables), 180);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, scalar|null> $variables
     */
    private function mailHtmlFromBody(array $body, string $templateKey, array $variables = []): string
    {
        $message = is_string($body['message'] ?? null) ? trim((string) $body['message']) : '';
        if ($message === '') {
            $message = $this->privateMailTemplate($templateKey);
        }

        return $this->plainTextToHtml($this->renderPrivateMailTemplate($message, $variables));
    }

    private function privateMailTemplate(string $key, string $fallback = ''): string
    {
        $template = app_config('private.mail.templates.' . $key, $fallback);

        return is_scalar($template) ? (string) $template : $fallback;
    }

    /**
     * @param array<string, scalar|null> $variables
     */
    private function renderPrivateMailTemplate(string $template, array $variables = []): string
    {
        $common = [
            'today' => date('d/m/Y'),
            'login_url' => app_url(private_portal_url('login')),
            'private_url' => app_url(private_portal_url('login')),
            'site_name' => (string) app_config('site.name', 'Les Caramagnols'),
            'reply_to' => (string) app_config('private.mail.reply_to', 'private@lescaramagnols.com'),
        ];

        $replacements = [];
        foreach (array_merge($common, $variables) as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string) $value;
        }

        return strtr($template, $replacements);
    }

    private function plainTextToHtml(string $message): string
    {
        $message = sanitize_text_field($message, 4000);

        return '<p>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'), false) . '</p>';
    }

    private function normalizeEmailInput(mixed $value): string
    {
        $email = strtolower(trim((string) $value));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : '';
    }

    /**
     * @return array<int, int>
     */
    private function discussionMemberIdsFromPayload(mixed $payload): array
    {
        $values = is_array($payload) ? $payload : [$payload];
        $ids = [];
        foreach ($values as $value) {
            $id = is_numeric($value) ? (int) $value : 0;
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function discussionEncryptionFromPayload(array $payload): array
    {
        $mode = is_string($payload['encryption_mode'] ?? null) ? (string) $payload['encryption_mode'] : '';
        $encryptedPayload = is_string($payload['encrypted_payload'] ?? null) ? (string) $payload['encrypted_payload'] : '';
        $metadata = is_string($payload['encryption_metadata'] ?? null) ? (string) $payload['encryption_metadata'] : '';

        return [
            'mode' => $mode,
            'payload' => $encryptedPayload,
            'metadata' => $metadata,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function discussionClientMessageIdFromPayload(array $payload): ?string
    {
        $clientMessageId = is_string($payload['client_message_id'] ?? null)
            ? trim((string) $payload['client_message_id'])
            : '';
        if ($clientMessageId === '') {
            return null;
        }

        return preg_match('/\A[A-Za-z0-9._:-]{8,80}\z/', $clientMessageId) === 1 ? $clientMessageId : null;
    }

    private function discussionRequestId(Request $request): string
    {
        foreach ([$request->header('X-Request-Id'), $request->header('X-Correlation-Id')] as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $candidate = trim($candidate);
            if ($candidate !== '' && strlen($candidate) <= 128 && preg_match('/\A[A-Za-z0-9._:-]+\z/', $candidate) === 1) {
                return $candidate;
            }
        }

        return app_generate_request_id();
    }

    /**
     * @return array<int, array{privateUserId:int,deviceId:string,encryptedKey:string}>
     */
    private function discussionKeyWrappersFromPayload(mixed $payload): array
    {
        $items = is_array($payload) ? $payload : [];
        $wrappers = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $privateUserId = is_numeric($item['privateUserId'] ?? null) ? (int) $item['privateUserId'] : 0;
            $deviceId = is_string($item['deviceId'] ?? null) ? trim((string) $item['deviceId']) : '';
            $encryptedKey = is_string($item['encryptedKey'] ?? null) ? trim((string) $item['encryptedKey']) : '';
            if ($privateUserId <= 0 || $deviceId === '' || $encryptedKey === '') {
                continue;
            }

            $wrappers[] = [
                'privateUserId' => $privateUserId,
                'deviceId' => $deviceId,
                'encryptedKey' => $encryptedKey,
            ];
        }

        return $wrappers;
    }

    /**
     * @return array<string, mixed>
     */
    private function rentalBaseViewModel(string $title, string $notice, string $error): array
    {
        return [
            'privatePageTitle' => $title,
            'rentalCsrfToken' => csrf_token(self::CSRF_RENTAL),
            'rentalNotice' => $this->rentalNotice($notice),
            'rentalError' => $this->rentalError($error),
            'rentalUrls' => [
                'dashboard' => private_portal_url('rental_dashboard'),
                'lessors' => private_portal_url('rental_lessors'),
                'properties' => private_portal_url('rental_properties'),
                'units' => private_portal_url('rental_units'),
                'members' => private_portal_url('rental_property_members'),
                'tenants' => private_portal_url('rental_tenants'),
                'leases' => private_portal_url('rental_leases'),
                'rents' => private_portal_url('rental_rents'),
                'payments' => private_portal_url('rental_payments'),
                'expenses' => private_portal_url('rental_expenses'),
                'regularizations' => private_portal_url('rental_regularizations'),
                'documents' => private_portal_url('rental_documents'),
                'agencies' => private_portal_url('rental_agencies'),
                'agencyImports' => private_portal_url('rental_agency_imports'),
                'agencyReview' => private_portal_url('rental_agency_review'),
                'summary' => private_portal_url('rental_summary'),
                'exportCsv' => private_portal_url('rental_export_csv'),
                'exportPdf' => private_portal_url('rental_export_pdf'),
                'exportZip' => private_portal_url('rental_export_zip'),
            ],
            'rentalMailDefaults' => [
                'subject' => $this->privateMailTemplate('rental_subject', 'Document locatif'),
                'message' => $this->privateMailTemplate('rental_body'),
            ],
            'rentalPaymentRequestMailDefaults' => [
                'subject' => $this->privateMailTemplate('rental_payment_request_subject', 'Demande de paiement - loyer {{period}}'),
                'message' => $this->privateMailTemplate('rental_payment_request_body'),
                'signature' => $this->privateMailTemplate('rental_payment_request_signature', "Gestion locative {{site_name}}\nContact : {{reply_to}}"),
            ],
            'privateDashboardLogoutUrl' => private_portal_url('logout'),
            'privateLogoutCsrfToken' => csrf_token('private_logout'),
        ];
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

    /**
     * @param array<int, object> $objects
     * @return array<int, array<string, mixed>>
     */
    private function objectsToArrays(array $objects): array
    {
        return array_values(array_map(
            static fn ($object): array => is_object($object) && method_exists($object, 'toArray')
                ? $object->toArray()
                : [],
            $objects
        ));
    }

    /**
     * @return array<int, int>
     */
    private function authorizedPropertyIds(int $userId): array
    {
        return $this->rentalPropertyMemberRepository()->activePropertyIdsForUser($userId);
    }

    /**
     * @return array<string, string>
     */
    private function agencyReviewCategories(): array
    {
        return [
            'rent_income' => 'Loyer',
            'charge_provision_income' => 'Provision de charges',
            'recoverable_tax_income' => 'Taxe récupérable refacturée',
            'recoverable_charge_adjustment' => 'Régularisation de charges récupérables',
            'agency_management_fee' => 'Honoraires de gestion',
            'agency_fee_vat' => 'TVA honoraires',
            'agency_letting_fee' => 'Honoraires de location',
            'insurance_unpaid_rent' => 'Assurance loyers impayés',
            'property_tax_service_fee' => 'Frais taxe foncière',
            'works_expense' => 'Travaux',
            'copro_work_fund' => 'Fonds travaux copropriété',
            'condominium_current_charge' => 'Charges de copropriété',
            'recoverable_utility_charge' => 'Charge récupérable',
            'owner_transfer' => 'Reversement propriétaire',
            'security_deposit' => 'Dépôt de garantie',
            'agency_balance' => 'Solde agence',
            'other' => 'Autre / à qualifier',
        ];
    }

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

    /**
     * @param array<int, int> $propertyIds
     * @return array<int, int>
     */
    private function writableRentalPropertyIds(array $propertyIds, int $userId): array
    {
        $writable = [];
        foreach ($propertyIds as $propertyId) {
            if ($this->canWriteByPropertyId((int) $propertyId, $userId)) {
                $writable[] = (int) $propertyId;
            }
        }

        return array_values(array_unique($writable));
    }

    private function unitBelongsToProperty(int $unitId, int $propertyId): bool
    {
        if ($unitId <= 0 || $propertyId <= 0) {
            return false;
        }

        $unit = $this->rentalUnitRepository()->findById($unitId);
        return $unit !== null && $unit->rentalPropertyId === $propertyId;
    }

    private function tenantBelongsToProperty(int $tenantId, int $propertyId): bool
    {
        if ($tenantId <= 0 || $propertyId <= 0) {
            return false;
        }

        $tenant = $this->rentalLifecycleRepository()->findTenantById($tenantId);
        return is_array($tenant)
            && is_numeric($tenant['rentalPropertyId'] ?? null)
            && (int) $tenant['rentalPropertyId'] === $propertyId;
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

    private function yearFromRequest(Request $request): int
    {
        $query = $request->query();
        $year = is_numeric($query['year'] ?? null) ? (int) $query['year'] : (int) date('Y');
        if ($year < 2000 || $year > 2100) {
            return (int) date('Y');
        }

        return $year;
    }

    private function rentalAgencyReviewUrl(int $documentId = 0, string $notice = '', string $error = ''): string
    {
        $query = [];
        if ($documentId > 0) {
            $query['document_id'] = (string) $documentId;
        }
        if ($notice !== '') {
            $query['notice'] = $notice;
        }
        if ($error !== '') {
            $query['error'] = $error;
        }

        $url = private_portal_url('rental_agency_review');
        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }

    private function rentalAgencyReviewLineUrl(
        int $documentId,
        int $lineId,
        string $notice = '',
        string $error = ''
    ): string {
        $url = $this->rentalAgencyReviewUrl($documentId);
        $query = [];
        if ($lineId > 0) {
            $query['line_id'] = (string) $lineId;
        }
        if ($notice !== '') {
            $query['line_notice'] = $notice;
        }
        if ($error !== '') {
            $query['line_error'] = $error;
        }

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        return $lineId > 0 ? $url . '#agency-review-line-' . $lineId : $url;
    }

    /**
     * @param array<string, mixed> $body
     * @return array{0:int, 1:int}
     */
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

    /**
     * @param array{created?:int, existing?:int, skipped?:int} $result
     */
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

    /**
     * @param array<string, mixed> $body
     * @return array{paymentDate:string, amountPaid:float, status:string, paymentKind:string, paymentMethod:?string, paymentReference:?string, notes:?string, confirmOverpayment:bool}|null
     */
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

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $lease
     * @return array{amount: float, notes: string|null}|null
     */
    private function rentalRentAmountDetailsFromBody(array $body, array $lease): ?array
    {
        /** @var array<int, array{0:string, 1:float}> $items */
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
            $label = sanitize_text_field((string) ($labels[$index] ?? ''), 80);
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
        $manualNotes = is_string($body['notes'] ?? null) ? sanitize_text_field((string) $body['notes'], 900) : '';
        if ($manualNotes !== '') {
            $notes[] = $manualNotes;
        }

        $breakdownLines = [];
        foreach ($items as $item) {
            $breakdownLines[] = sprintf('- %s: %.2f EUR', (string) $item[0], (float) $item[1]);
        }
        $notes[] = "Detail quittance:\n" . implode("\n", $breakdownLines);
        $receiptText = is_string($body['receipt_text'] ?? null) ? sanitize_text_field((string) $body['receipt_text'], 700) : '';
        if ($receiptText !== '') {
            $notes[] = "Mention quittance:\n" . $receiptText;
        }

        return [
            'amount' => $amount,
            'notes' => sanitize_text_field(implode("\n\n", $notes), 2000),
        ];
    }

    private function appendRentalLeaseAdjustmentNote(
        ?string $existingNotes,
        string $month,
        float $monthlyRent,
        float $chargesProvision,
        string $freeNote
    ): string {
        $month = preg_match('/\A\d{4}-\d{2}\z/', trim($month)) === 1 ? trim($month) : date('Y-m');
        $freeNote = sanitize_text_field($freeNote, 500);
        $line = sprintf(
            '[%s] Reajustement annuel: loyer %.2f EUR, provision charges %.2f EUR.',
            $month,
            round($monthlyRent, 2),
            round($chargesProvision, 2)
        );
        if ($freeNote !== '') {
            $line .= ' ' . $freeNote;
        }

        return sanitize_text_field(trim((string) $existingNotes . "\n" . $line), 2000);
    }

    /**
     * @param array<string, mixed> $lease
     * @param array<string, mixed> $tenant
     */
    private function rentalLeasePdf(array $lease, array $tenant, string $propertyName, string $unitLabel): string
    {
        $leaseType = is_string($lease['leaseType'] ?? null) ? (string) $lease['leaseType'] : RentalLeaseTypeCatalog::DEFAULT;
        $typeLabel = RentalLeaseTypeCatalog::label($leaseType);
        $taxLabel = RentalLeaseTypeCatalog::taxLabel($leaseType);
        $templateNotice = match (RentalLeaseTypeCatalog::normalize($leaseType)) {
            'residential_furnished' => 'Modele de bail meuble a completer avec les annexes obligatoires.',
            'student_furnished' => 'Modele de bail etudiant meuble a completer avec les annexes obligatoires.',
            'mobility_furnished' => 'Modele de bail mobilite a completer avec la duree et les justificatifs requis.',
            'other' => 'Modele libre a verifier avant signature.',
            default => 'Modele de bail habitation vide a completer avec les annexes obligatoires.',
        };

        $text = sprintf(
            "Edition du bail\nType: %s\nTraitement fiscal indicatif: %s\nPropriete: %s\nBien locatif: %s\nLocataire: %s\nDebut: %s\nFin: %s\nLoyer mensuel: %.2f EUR\nProvision charges: %.2f EUR\n\n%s\n\nNotes:\n%s",
            $typeLabel,
            $taxLabel,
            $propertyName,
            $unitLabel !== '' ? $unitLabel : 'non precise',
            (string) ($tenant['fullName'] ?? 'Locataire'),
            (string) ($lease['startDate'] ?? ''),
            (string) ($lease['endDate'] ?? 'non precisee'),
            (float) ($lease['monthlyRent'] ?? 0),
            (float) ($lease['chargesProvision'] ?? 0),
            $templateNotice,
            trim((string) ($lease['notes'] ?? ''))
        );

        return "%PDF-1.4\n% Caramagnols private rental lease\n" . $text . "\n%%EOF\n";
    }

    private function normalizeNumericId(mixed $value): int
    {
        if (!is_numeric($value)) {
            return 0;
        }

        $id = (int) $value;
        return $id > 0 ? $id : 0;
    }

    private function rentalNotice(string $key): string
    {
        return match ($key) {
            'property_created' => 'Propriété créée.',
            'property_updated' => 'Propriété mise à jour.',
            'property_archived' => 'Propriété archivée.',
            'lessor_created' => 'Bailleur créé.',
            'lessor_updated' => 'Bailleur mis à jour.',
            'lessor_deleted' => 'Bailleur supprimé.',
            'unit_created' => 'Bien locatif créé.',
            'unit_updated' => 'Bien locatif mis à jour.',
            'unit_archived' => 'Bien locatif archivé.',
            'member_created' => 'Membre locatif ajouté.',
            'member_updated' => 'Accès membre mis à jour.',
            'member_deleted' => 'Accès membre supprimé.',
            'tenant_created' => 'Locataire créé.',
            'tenant_updated' => 'Locataire mis à jour.',
            'tenant_deleted' => 'Locataire supprimé.',
            'lease_created' => 'Bail créé.',
            'lease_updated' => 'Bail mis à jour.',
            'lease_adjusted' => 'Réajustement du bail appliqué.',
            'lease_document_uploaded' => 'Document de bail importé.',
            'lease_deleted' => 'Bail supprimé.',
            'rent_created' => 'Loyer créé.',
            'rent_schedule_generated' => 'Échéancier généré.',
            'rent_schedule_existing' => 'Échéancier déjà à jour.',
            'rent_schedule_partial' => 'Échéancier partiellement généré.',
            'rent_deleted' => 'Loyer supprimé.',
            'payment_created' => 'Paiement locatif créé.',
            'payment_updated' => 'Paiement locatif corrigé.',
            'payment_cancelled' => 'Paiement locatif annulé.',
            'payment_deleted' => 'Paiement locatif supprimé.',
            'receipt_emailed' => 'Quittance envoyée par email.',
            'expense_created' => 'Charge locative créée.',
            'expense_deleted' => 'Charge locative supprimée.',
            'regularization_generated' => 'Régularisation de charges générée.',
            'document_uploaded' => 'Document locatif envoyé.',
            'document_deleted' => 'Document locatif supprimé.',
            'document_emailed' => 'Email locatif envoyé.',
            'rental_data_purged' => 'Données locatives supprimées.',
            'agency_imported' => 'Document agence importé et préparé pour revue.',
            'agency_import_ignored' => 'Fichier annexe ignoré.',
            'agency_document_deleted' => 'Document agence supprimé.',
            'agency_created' => 'Agence créée.',
            'agency_updated' => 'Agence mise à jour.',
            'agency_deleted' => 'Agence supprimée.',
            'agency_unit_mapping_created' => 'Correspondance agence créée.',
            'agency_unit_mapping_deleted' => 'Correspondance agence supprimée.',
            'agency_statement_property_updated' => 'Rattachement du relevé mis à jour.',
            'agency_line_validated' => 'Ligne agence validée.',
            'agency_line_corrected' => 'Ligne agence corrigée.',
            'agency_line_ignored' => 'Ligne agence ignorée.',
            'agency_lines_saved' => 'Lignes agence enregistrées.',
            default => '',
        };
    }

    private function rentalError(string $key): string
    {
        return match ($key) {
            'rental_invalid_request' => 'Requête locative invalide.',
            'rental_write_failed' => 'Les données locatives sont invalides ou incomplètes.',
            'rental_overpayment_requires_confirmation' => 'Surpaiement détecté : cochez la confirmation pour enregistrer.',
            'rental_archive_failed' => 'Archivage locatif impossible.',
            'property_forbidden' => 'Vous n’avez pas le droit de modifier cette propriété.',
            'lessor_forbidden' => 'Bailleur introuvable ou non autorisé.',
            'lessor_write_failed' => 'Les informations du bailleur sont invalides ou incomplètes.',
            'lessor_delete_failed' => 'Suppression du bailleur impossible.',
            'unit_forbidden' => 'Vous n’avez pas le droit de modifier ce bien locatif.',
            'unit_archive_failed' => 'Archivage du bien locatif impossible.',
            'member_forbidden' => 'Vous n’avez pas le droit de modifier les membres de cette propriété.',
            'member_missing_email' => 'Adresse du membre obligatoire.',
            'member_unknown_user' => 'Compte privé introuvable.',
            'member_create_failed' => 'Ajout du membre locatif impossible.',
            'member_update_failed' => 'Mise à jour de l’accès membre impossible.',
            'member_delete_failed' => 'Suppression de l’accès membre impossible.',
            'member_self_forbidden' => 'Le compte connecté ne peut pas modifier ni supprimer son propre accès.',
            'missing_file' => 'Aucun fichier reçu.',
            'upload_failed' => 'Envoi du document locatif impossible.',
            'email_failed' => 'Envoi email impossible.',
            'tenant_update_failed' => 'Mise à jour du locataire impossible.',
            'tenant_required_for_unit' => 'Il faut créer un locataire pour cette propriété avant de créer un bail.',
            'lease_unit_unavailable' => 'Ce bien locatif est indisponible ou possède déjà un bail actif.',
            'rental_delete_failed' => 'Suppression locative impossible.',
            'rental_purge_confirmation_required' => 'Confirmez la suppression avec SUPPRIMER.',
            'agency_import_failed' => 'Import agence impossible.',
            'agency_import_duplicate' => 'Document agence déjà importé.',
            'agency_import_scan_infected' => 'Document agence refusé par le contrôle antivirus.',
            'agency_import_scan_unavailable' => 'Contrôle antivirus indisponible : import agence refusé.',
            'agency_document_delete_failed' => 'Suppression du document agence impossible.',
            'agency_create_failed' => 'Création de l’agence impossible.',
            'agency_update_failed' => 'Mise à jour de l’agence impossible.',
            'agency_delete_failed' => 'Suppression de l’agence impossible.',
            'agency_unit_mapping_failed' => 'Création de la correspondance agence impossible.',
            'agency_unit_mapping_delete_failed' => 'Suppression de la correspondance agence impossible.',
            'agency_review_failed' => 'Revue agence impossible.',
            'agency_review_forbidden' => 'Document agence introuvable ou non autorisé.',
            'agency_sensitive_review_required' => 'Cochez la revue fiscale avant de valider cette catégorie sensible.',
            'agency_lines_partial' => 'Certaines lignes n’ont pas pu être enregistrées.',
            default => '',
        };
    }

    private function taxNotice(string $key): string
    {
        return match ($key) {
            'summary_generated' => 'Synthèse annuelle générée.',
            'year_locked' => 'Année fiscale verrouillée.',
            'year_unlocked' => 'Année fiscale déverrouillée.',
            'manual_created' => 'Revenu manuel ajouté.',
            'tax_pdf_emailed' => 'PDF fiscal envoyé par email.',
            'source_enabled' => 'Liaison source activée pour cette année.',
            'source_disabled' => 'Liaison source désactivée pour cette année.',
            default => '',
        };
    }

    private function taxError(string $key): string
    {
        return match ($key) {
            'tax_invalid_request' => 'Requête fiscale invalide.',
            'tax_locked' => 'Année verrouillée : modification refusée.',
            'tax_write_failed' => 'Écriture fiscale impossible.',
            'tax_locked_or_invalid' => 'Revenu manuel refusé : année verrouillée ou données invalides.',
            'tax_source_link_failed' => 'Activation de la source refusée ou impossible.',
            'email_failed' => 'Envoi email impossible.',
            default => '',
        };
    }

    private function taxYearUrl(int $year): string
    {
        return rtrim(private_portal_url('tax_dashboard'), '/') . '/' . $year;
    }

    private function taxManualUrl(int $year): string
    {
        return $this->taxYearUrl($year) . '/revenus-manuels';
    }

    private function normalizeTaxYear(int $year): int
    {
        if ($year < 2000 || $year > 2100) {
            return (int) date('Y');
        }

        return $year;
    }

    private function userIdOrAccessDenied(Request $request): ?int
    {
        $required = $this->requireModuleOrUnauthorized($request, 'documents');
        if ($required === null || $required instanceof Response) {
            return null;
        }

        return $required;
    }

    private function requireModuleOrUnauthorized(Request $request, string $module): int|Response|null
    {
        $required = $this->guard()->requireAuthenticated($request, private_portal_url('login'));
        if ($required !== null) {
            return $required;
        }

        $userId = $this->currentPrivateUserId();
        if ($userId === null) {
            return null;
        }

        if (!$this->modulePermissionRepository()->userHasModuleAccess($userId, $module)) {
            return $this->handleModuleAccessDenied($module);
        }

        return $userId;
    }

    private function requireRentalModuleUser(Request $request): int|Response
    {
        $result = $this->requireModuleOrUnauthorized($request, 'real_estate_rental');
        if ($result === null) {
            return $this->handleModuleAccessDenied('real_estate_rental');
        }

        return $result;
    }

    private function requireDocumentsModuleUser(Request $request): int|Response
    {
        $result = $this->requireModuleOrUnauthorized($request, 'documents');
        if ($result === null) {
            return $this->handleModuleAccessDenied('documents');
        }

        return $result;
    }

    private function requireBlocNoteModuleUser(Request $request): int|Response
    {
        $result = $this->requireModuleOrUnauthorized($request, 'blocnote');
        if ($result === null) {
            return $this->handleModuleAccessDenied('blocnote');
        }

        return $result;
    }

    private function requireTaxModuleUser(Request $request): int|Response
    {
        $result = $this->requireModuleOrUnauthorized($request, 'tax_declaration_helper');
        if ($result === null) {
            return $this->handleModuleAccessDenied('tax_declaration_helper');
        }

        return $result;
    }

    private function requireDiscussionModuleUser(Request $request): int|Response
    {
        $result = $this->requireModuleOrUnauthorized($request, 'discussions');
        if ($result === null) {
            return $this->handleModuleAccessDenied('discussions');
        }

        return $result;
    }

    private function requireAuthenticatedUser(Request $request): int|Response
    {
        $required = $this->guard()->requireAuthenticated($request, private_portal_url('login'), true);
        if ($required !== null) {
            return $this->withPrivateHeaders($required);
        }

        $userId = $this->currentPrivateUserId();
        if ($userId === null) {
            return $this->redirect(private_portal_url('login'));
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

    private function forbiddenOrUnauthorized(string $location): Response
    {
        unset($location);

        return $this->handleModuleAccessDenied('real_estate_rental');
    }

    /**
     * @return array<int, string>
     */
    private function privateModuleNamesForUser(int $userId): array
    {
        return array_values(array_filter(array_map(
            static fn (array $module): string => (string) $module['name'],
            $this->modulePermissionRepository()->activeModulesForUser($userId)
        )));
    }

    /**
     * @param array{email: string, fullName: string, postalAddress: string, phone: string} $formValues
     * @param array<string, mixed> $smtpSettings
     */
    private function renderMemberSettings(
        int $userId,
        array $formValues,
        array $smtpSettings,
        string $notice,
        string $error,
        string $tab = 'profile'
    ): Response {
        $tab = in_array($tab, ['profile', 'smtp'], true) ? $tab : 'profile';
        $smtpSettings = $this->memberSmtpSettingsWithGlobalFallback($smtpSettings);
        $memberSmtpConfigured = $this->privateUserMailSettingsRepository()->isConfiguredForUser($userId);
        $effectiveSmtpConfigured = $memberSmtpConfigured || $this->privateGlobalMailConfigAvailable();

        return $this->render('settings', [
            'privatePageTitle' => $this->translate('TXT_PRIVATE_SETTINGS_PAGE_TITLE', 'Paramètres membre'),
            'privateUserIdentifier' => is_string($this->auth->currentIdentifier()) ? (string) $this->auth->currentIdentifier() : '',
            'privateModules' => $this->privateModuleNamesForUser($userId),
            'privateMemberProfile' => $formValues,
            'privateMemberSmtpSettings' => $smtpSettings,
            'privateMemberSmtpConfigured' => $effectiveSmtpConfigured,
            'privateSettingsActiveTab' => $tab,
            'privateSettingsFormAction' => private_portal_url('member_settings'),
            'privateSettingsCsrfToken' => csrf_token(self::CSRF_MEMBER_SETTINGS),
            'notice' => match ($notice) {
                'profile_saved' => $this->translate('TXT_PRIVATE_SETTINGS_SAVED', 'Paramètres enregistrés.'),
                'smtp_saved' => $this->translate('TXT_PRIVATE_SETTINGS_SMTP_SAVED', 'Paramètres SMTP enregistrés.'),
                'smtp_test_sent' => $this->translate('TXT_PRIVATE_SETTINGS_SMTP_TEST_SENT', 'Paramètres SMTP enregistrés et email de test envoyé.'),
                default => null,
            },
            'errorMessage' => match ($error) {
                'phone_invalid' => $this->translate('TXT_PRIVATE_SETTINGS_ERROR_PHONE', 'Le téléphone contient des caractères non autorisés.'),
                'save_failed' => $this->translate('TXT_PRIVATE_SETTINGS_ERROR_SAVE', 'Les paramètres n’ont pas pu être enregistrés.'),
                'smtp_required' => $this->translate('TXT_PRIVATE_SETTINGS_SMTP_REQUIRED', 'Veuillez remplir vos paramètres SMTP avant d’envoyer un email.'),
                'invalid_host' => $this->translate('TXT_PRIVATE_SETTINGS_SMTP_INVALID_HOST', 'Le serveur SMTP est invalide.'),
                'invalid_port' => $this->translate('TXT_PRIVATE_SETTINGS_SMTP_INVALID_PORT', 'Le port SMTP est invalide.'),
                'invalid_encryption' => $this->translate('TXT_PRIVATE_SETTINGS_SMTP_INVALID_SECURITY', 'La sécurité SMTP est invalide.'),
                'invalid_from_email' => $this->translate('TXT_PRIVATE_SETTINGS_SMTP_INVALID_FROM', 'L’adresse expéditeur est invalide.'),
                'invalid_reply_to' => $this->translate('TXT_PRIVATE_SETTINGS_SMTP_INVALID_REPLY_TO', 'L’adresse de réponse est invalide.'),
                'encryption_unavailable' => $this->translate('TXT_PRIVATE_SETTINGS_SMTP_ENCRYPTION_UNAVAILABLE', 'La clé de chiffrement SMTP privée est manquante.'),
                'smtp_test_failed' => $this->translate('TXT_PRIVATE_SETTINGS_SMTP_TEST_FAILED', 'Le test SMTP a échoué. Vérifiez vos paramètres.'),
                'invalid_request' => $this->translate('TXT_PRIVATE_ERROR_CSRF', 'Requête invalide.'),
                default => null,
            },
            'privateSettingsSmtpPopup' => $error === 'smtp_required',
            'privateDashboardLogoutUrl' => private_portal_url('logout'),
            'privateLogoutCsrfToken' => csrf_token('private_logout'),
        ]);
    }

    /**
     * @param array<string, mixed> $formValues
     */
    private function renderBlocNote(
        int $userId,
        BlocNoteRepository $repository,
        string $view,
        array $formValues,
        ?string $notice,
        ?string $error
    ): Response {
        $view = $this->resolveBlocNoteView($view);

        return $this->render('modules/blocnote/index', [
            'privatePageTitle' => 'Bloc-note',
            'privateUserIdentifier' => is_string($this->auth->currentIdentifier()) ? (string) $this->auth->currentIdentifier() : '',
            'privateModules' => $this->privateModuleNamesForUser($userId),
            'blocNote' => [
                'view' => $view,
                'baseUrl' => private_portal_url('blocnote'),
                'csrfToken' => csrf_token(self::CSRF_BLOCNOTE),
                'notes' => $repository->listNotes($userId),
                'categories' => $repository->listCategories($userId),
                'dashboard' => $repository->dashboardData($userId),
                'formValues' => $formValues,
                'categoryColors' => BlocNoteRepository::CATEGORY_COLORS,
                'categoryDefaultColor' => BlocNoteRepository::DEFAULT_COLOR,
            ],
            'notice' => $this->blocNoteNotice($notice),
            'errorMessage' => $this->blocNoteError($error),
            'privateDashboardLogoutUrl' => private_portal_url('logout'),
            'privateLogoutCsrfToken' => csrf_token('private_logout'),
        ]);
    }

    /**
     * @return array{note_id: int, title: string, content: string, category_id: int}
     */
    private function blocNoteDefaultFormValues(int $defaultCategoryId): array
    {
        return [
            'note_id' => 0,
            'title' => '',
            'content' => '',
            'category_id' => $defaultCategoryId,
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array{note_id: int, title: string, content: string, category_id: int}
     */
    private function blocNoteFormValuesFromBody(array $body, int $defaultCategoryId): array
    {
        $categoryId = $this->normalizeNumericId($body['category_id'] ?? null);

        return [
            'note_id' => $this->normalizeNumericId($body['note_id'] ?? null),
            'title' => is_string($body['title'] ?? null) ? (string) $body['title'] : '',
            'content' => is_string($body['content'] ?? null) ? (string) $body['content'] : '',
            'category_id' => $categoryId > 0 ? $categoryId : $defaultCategoryId,
        ];
    }

    /**
     * @param array<string, mixed> $note
     * @return array{note_id: int, title: string, content: string, category_id: int}
     */
    private function blocNoteFormValuesFromNote(array $note, int $defaultCategoryId): array
    {
        $categoryId = is_numeric($note['categoryId'] ?? null) ? (int) $note['categoryId'] : 0;

        return [
            'note_id' => is_numeric($note['id'] ?? null) ? (int) $note['id'] : 0,
            'title' => is_string($note['title'] ?? null) ? (string) $note['title'] : '',
            'content' => is_string($note['contentText'] ?? null) ? (string) $note['contentText'] : '',
            'category_id' => $categoryId > 0 ? $categoryId : $defaultCategoryId,
        ];
    }

    private function resolveBlocNoteView(string $view): string
    {
        $view = strtolower(trim($view));

        return match ($view) {
            'notes', 'my_notes', 'mes-notes' => 'notes',
            'form', 'new', 'edit', 'nouvelle-note' => 'form',
            'categories' => 'categories',
            'help', 'aide' => 'help',
            default => 'dashboard',
        };
    }

    /**
     * @param array<string, string> $params
     */
    private function blocNoteUrl(array $params = []): string
    {
        $url = private_portal_url('blocnote');
        if ($params === []) {
            return $url;
        }

        return $url . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function blocNoteNotice(?string $key): ?string
    {
        return match ($key) {
            'note_saved' => 'Note enregistrée.',
            'note_deleted' => 'Note supprimée.',
            'category_saved' => 'Catégorie enregistrée.',
            'category_deleted' => 'Catégorie supprimée.',
            'default_category_saved' => 'Catégorie par défaut mise à jour.',
            default => null,
        };
    }

    private function blocNoteError(?string $key): ?string
    {
        return match ($key) {
            'invalid_request' => $this->translate('TXT_PRIVATE_ERROR_CSRF', 'Requête invalide.'),
            'note_required' => 'Saisissez au moins un titre ou un contenu.',
            'note_not_found' => 'Note introuvable.',
            'note_delete_failed' => 'La note n’a pas pu être supprimée.',
            'category_failed' => 'La catégorie n’a pas pu être enregistrée. Vérifiez le nom et les doublons.',
            'category_delete_failed' => 'La catégorie n’a pas pu être supprimée. La catégorie par défaut ne peut pas être supprimée.',
            default => null,
        };
    }

    private function documentsUrlWithNotice(string $notice): string
    {
        return private_portal_url('documents') . '?notice=' . rawurlencode($notice);
    }

    private function documentsUrlWithError(string $error): string
    {
        return private_portal_url('documents') . '?error=' . rawurlencode($error);
    }

    private function handleNotFound(): Response
    {
        return $this->withPrivateHeaders(new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Not Found'));
    }

    private function jsonPrivateResponse(array $payload, int $status = 200): Response
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            $encoded = '{}';
        }

        return $this->withPrivateHeaders(new Response($status, ['Content-Type' => 'application/json; charset=UTF-8'], $encoded));
    }

    private function discussionRateLimitHit(Request $request, int $userId, string $action): bool
    {
        $config = is_array(app_config('private.discussions', [])) ? (array) app_config('private.discussions') : [];
        $action = match ($action) {
            'conversation' => 'conversation',
            'client_event' => 'client_event',
            default => 'message',
        };
        $attemptsKey = match ($action) {
            'conversation' => 'conversation_rate_limit_attempts',
            'client_event' => 'client_event_rate_limit_attempts',
            default => 'message_rate_limit_attempts',
        };
        $windowKey = match ($action) {
            'conversation' => 'conversation_rate_limit_window',
            'client_event' => 'client_event_rate_limit_window',
            default => 'message_rate_limit_window',
        };
        $defaultAttempts = match ($action) {
            'conversation' => 10,
            'client_event' => 20,
            default => 30,
        };
        $defaultWindow = $action === 'conversation' ? 300 : 60;
        $attempts = max(1, (int) ($config[$attemptsKey] ?? $defaultAttempts));
        $window = max(30, (int) ($config[$windowKey] ?? $defaultWindow));
        $ip = $request->clientIp((bool) app_config('private.trust_proxy_headers', false)) ?? 'unknown';
        $limiter = new \FileRateLimiter(
            'private_discussion_' . $action . '_' . $userId . '_' . hash('sha256', $ip),
            $attempts,
            $window
        );

        if ($limiter->hit()) {
            return true;
        }

        $this->logEvent('private.discussion.rate_limited', [
            'private_user_id' => $userId,
            'action' => $action,
            'ip' => $ip,
        ]);

        return false;
    }

    private function render(string $template, array $viewModel = []): Response
    {
        $contentTemplate = ROOT_PATH . '/templates/private/' . $template . '.php';
        if (!is_file($contentTemplate)) {
            return new Response(500, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Template introuvable: ' . $template);
        }

        $privatePortalEnabled = private_portal_enabled();
        $privatePageTitle = is_string($viewModel['privatePageTitle'] ?? null)
            ? $viewModel['privatePageTitle']
            : 'Espace privé';
        $privatePageDescription = is_string($viewModel['privatePageDescription'] ?? null)
            ? $viewModel['privatePageDescription']
            : '';
        $privateNoticeTitle = is_string($viewModel['privateNoticeTitle'] ?? null)
            ? $viewModel['privateNoticeTitle']
            : '';
        $privateNoticeBody = is_string($viewModel['privateNoticeBody'] ?? null)
            ? $viewModel['privateNoticeBody']
            : '';
        $privateNoticeToken = is_string($viewModel['privateNoticeToken'] ?? null)
            ? $viewModel['privateNoticeToken']
            : '';
        $identifier = is_string($viewModel['identifier'] ?? null)
            ? $viewModel['identifier']
            : '';
        $errorKey = is_string($viewModel['errorKey'] ?? null)
            ? $viewModel['errorKey']
            : null;
        $csrfToken = is_string($viewModel['csrfToken'] ?? null)
            ? $viewModel['csrfToken']
            : '';
        $privateUserIdentifier = is_string($viewModel['privateUserIdentifier'] ?? null)
            ? $viewModel['privateUserIdentifier']
            : '';
        $privateModules = is_array($viewModel['privateModules'] ?? null) ? $viewModel['privateModules'] : [];
        $privateDocumentsEnabled = is_bool($viewModel['privateDocumentsEnabled'] ?? null)
            ? (bool) $viewModel['privateDocumentsEnabled']
            : false;
        $privatePasswordForgotUrl = is_string($viewModel['privatePasswordForgotUrl'] ?? null)
            ? $viewModel['privatePasswordForgotUrl']
            : private_portal_url('password_forgot');
        $privateDocuments = is_array($viewModel['privateDocuments'] ?? null)
            ? $viewModel['privateDocuments']
            : [];
        $privateRentalDocumentsEnabled = is_bool($viewModel['privateRentalDocumentsEnabled'] ?? null)
            ? (bool) $viewModel['privateRentalDocumentsEnabled']
            : false;
        $privateRentalDocuments = is_array($viewModel['privateRentalDocuments'] ?? null)
            ? $viewModel['privateRentalDocuments']
            : [];
        $privateDocumentCategories = is_array($viewModel['privateDocumentCategories'] ?? null)
            ? $viewModel['privateDocumentCategories']
            : [];
        $privateDocumentUploadCsrfToken = is_string($viewModel['privateDocumentUploadCsrfToken'] ?? null)
            ? $viewModel['privateDocumentUploadCsrfToken']
            : '';
        $privateDocumentsUploadUrl = is_string($viewModel['privateDocumentsUploadUrl'] ?? null)
            ? $viewModel['privateDocumentsUploadUrl']
            : private_portal_url('files_upload');
        $privateDocumentCategoriesUrl = is_string($viewModel['privateDocumentCategoriesUrl'] ?? null)
            ? $viewModel['privateDocumentCategoriesUrl']
            : private_portal_url('files_categories');
        $privateFilesBaseUrl = is_string($viewModel['privateFilesBaseUrl'] ?? null)
            ? $viewModel['privateFilesBaseUrl']
            : private_portal_url('files');
        $privateRentalFilesBaseUrl = is_string($viewModel['privateRentalFilesBaseUrl'] ?? null)
            ? $viewModel['privateRentalFilesBaseUrl']
            : private_portal_url('rental_documents');
        $privateDashboardLogoutUrl = is_string($viewModel['privateDashboardLogoutUrl'] ?? null)
            ? $viewModel['privateDashboardLogoutUrl']
            : private_portal_url('logout');
        $privateLogoutCsrfToken = is_string($viewModel['privateLogoutCsrfToken'] ?? null)
            ? $viewModel['privateLogoutCsrfToken']
            : '';
        $privateFormAction = is_string($viewModel['privateFormAction'] ?? null)
            ? $viewModel['privateFormAction']
            : '';
        $privateFormCsrfToken = is_string($viewModel['privateFormCsrfToken'] ?? null)
            ? $viewModel['privateFormCsrfToken']
            : '';
        $privateFormSubmitLabel = is_string($viewModel['privateFormSubmitLabel'] ?? null)
            ? $viewModel['privateFormSubmitLabel']
            : $this->translate('TXT_PRIVATE_FORM_SUBMIT', 'Valider');
        $privateFormError = is_string($viewModel['privateFormError'] ?? null)
            ? $viewModel['privateFormError']
            : null;
        $privateDashboardNotice = is_string($viewModel['notice'] ?? null) ? (string) $viewModel['notice'] : '';
        $privateDashboardErrorMessage = is_string($viewModel['errorMessage'] ?? null) ? (string) $viewModel['errorMessage'] : '';
        $privateCurrentPage = is_string($viewModel['privateCurrentPage'] ?? null)
            ? trim((string) $viewModel['privateCurrentPage'])
            : ($this->currentPrivatePage ?? $this->privateCurrentPageFromTemplate($template));

        ob_start();
        include $contentTemplate;
        $privateContent = (string) ob_get_clean();

        ob_start();
        $privateIsAuthenticated = $this->auth->isAuthenticated();
        $privateNavigationModules = $privateModules;
        $privateMemberSettingsEnabled = false;
        $privateMemberSettingsUrl = private_portal_url('member_settings');
        if ($privateIsAuthenticated) {
            if ($privateLogoutCsrfToken === '') {
                $privateLogoutCsrfToken = csrf_token('private_logout');
            }

            $privateSessionPingUrl = private_portal_url('session_ping');
            $privateSessionCsrfToken = csrf_token(self::CSRF_SESSION);
            $privateSessionTimeoutSeconds = $this->auth->inactivityTimeoutSeconds();
            $privateSessionKeepAliveSeconds = max(60, min(300, (int) floor($privateSessionTimeoutSeconds / 3)));

            $currentIdentifier = $this->auth->currentIdentifier();
            if ($privateUserIdentifier === '' && is_string($currentIdentifier)) {
                $privateUserIdentifier = trim($currentIdentifier);
            }

            $currentUserId = $this->currentPrivateUserId();
            if ($currentUserId !== null) {
                $privateMemberSettingsEnabled = true;
                $privateNavigationModules = array_map(
                    static fn (array $module): string => (string) $module['name'],
                    $this->modulePermissionRepository()->activeModulesForUser($currentUserId)
                );
            }
        }
        $privateDashboardUrl = private_portal_url('dashboard');
        $privateLoginUrl = private_portal_url('login');
        include ROOT_PATH . '/templates/private/layout.php';
        $body = (string) ob_get_clean();

        return $this->withPrivateHeaders(new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $body));
    }

    private function privateCurrentPageFromTemplate(string $template): string
    {
        return match ($template) {
            'dashboard' => 'dashboard',
            'settings' => 'member_settings',
            'modules/documents/index' => 'documents',
            'modules/blocnote/index' => 'blocnote',
            'modules/real-estate-rental/dashboard' => 'rental_dashboard',
            'modules/real-estate-rental/lessors' => 'rental_lessors',
            'modules/real-estate-rental/properties' => 'rental_properties',
            'modules/real-estate-rental/units' => 'rental_units',
            'modules/real-estate-rental/property-members' => 'rental_property_members',
            'modules/real-estate-rental/tenants' => 'rental_tenants',
            'modules/real-estate-rental/leases' => 'rental_leases',
            'modules/real-estate-rental/rents' => 'rental_rents',
            'modules/real-estate-rental/payments' => 'rental_payments',
            'modules/real-estate-rental/expenses' => 'rental_expenses',
            'modules/real-estate-rental/regularizations' => 'rental_regularizations',
            'modules/real-estate-rental/documents' => 'rental_documents',
            'modules/real-estate-rental/agencies' => 'rental_agencies',
            'modules/real-estate-rental/agency-imports' => 'rental_agency_imports',
            'modules/real-estate-rental/agency-review' => 'rental_agency_review',
            'modules/real-estate-rental/summary' => 'rental_summary',
            'modules/tax-declaration-helper/dashboard' => 'tax_dashboard',
            'modules/tax-declaration-helper/year' => 'tax_year',
            'modules/tax-declaration-helper/manual' => 'tax_manual_entries',
            'modules/tax-declaration-helper/controls' => 'tax_controls',
            'modules/tax-declaration-helper/documents' => 'tax_documents',
            'modules/family-discussion/index' => 'discussion_index',
            'modules/family-discussion/conversation' => 'discussion_conversation',
            default => '',
        };
    }

    private function redirect(string $url): Response
    {
        return $this->withPrivateHeaders(new Response(302, ['Location' => $url], ''));
    }

    private function privateSmtpSettingsRequiredUrl(): string
    {
        return private_portal_url('member_settings') . '?tab=smtp&error=smtp_required';
    }

    private function withPrivateHeaders(Response $response): Response
    {
        return PrivateResponseHeaders::apply($response);
    }

    private function guard(): PrivatePortalSecurityGuard
    {
        return $this->securityGuard ?? new PrivatePortalSecurityGuard($this->auth, $this->eventLogger);
    }

    private function currentPrivateUserId(): ?int
    {
        $identifier = $this->auth->currentIdentifier();
        if (!is_string($identifier) || trim($identifier) === '') {
            return null;
        }

        $user = $this->privateUserRepository()->findByEmail($identifier);
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

    private function privateUserRepository(): PrivateUserRepository
    {
        return $this->privateUserRepository ?? new PrivateUserRepository(editorial_database());
    }

    private function privateUserMailSettingsRepository(): PrivateUserMailSettingsRepository
    {
        return $this->privateUserMailSettingsRepository
            ?? new PrivateUserMailSettingsRepository($this->privateUserRepository()->database());
    }

    private function documentUploadNoticeForScanStatus(string $scanStatus): string
    {
        return match ($scanStatus) {
            PrivateDocumentRepository::SCAN_STATUS_INFECTED => 'document_quarantined',
            PrivateDocumentRepository::SCAN_STATUS_PENDING_SCAN,
            PrivateDocumentRepository::SCAN_STATUS_UNAVAILABLE => 'document_scan_unavailable',
            default => 'document_uploaded',
        };
    }

    private function privateDocumentRepository(): PrivateDocumentRepository
    {
        return $this->privateDocumentRepository ?? new PrivateDocumentRepository(editorial_database());
    }

    private function blocNoteRepository(): BlocNoteRepository
    {
        return new BlocNoteRepository(editorial_database());
    }

    private function privateDocumentStorage(): PrivateDocumentStorage
    {
        return $this->privateDocumentStorage ?? PrivateDocumentStorage::fromAppConfig($this->eventLogger);
    }

    private function modulePermissionRepository(): PrivateModulePermissionRepository
    {
        return $this->modulePermissionRepository ?? new PrivateModulePermissionRepository(
            editorial_database(),
            new PrivateModuleRegistry()
        );
    }

    private function rentalPropertyRepository(): RentalPropertyRepository
    {
        return $this->rentalPropertyRepository ?? new RentalPropertyRepository(editorial_database());
    }

    private function rentalLessorRepository(): RentalLessorRepository
    {
        return $this->rentalLessorRepository ?? new RentalLessorRepository($this->privateUserRepository()->database());
    }

    private function rentalPropertyMemberRepository(): RentalPropertyMemberRepository
    {
        return $this->rentalPropertyMemberRepository ?? new RentalPropertyMemberRepository(editorial_database());
    }

    private function rentalUnitRepository(): RentalUnitRepository
    {
        return $this->rentalUnitRepository ?? new RentalUnitRepository(editorial_database());
    }

    private function rentalLifecycleRepository(): RentalLifecycleRepository
    {
        return $this->rentalLifecycleRepository ?? new RentalLifecycleRepository(editorial_database());
    }

    private function agencyImportRepository(): AgencyImportRepository
    {
        return new AgencyImportRepository(editorial_database());
    }

    private function agencyImportService(): AgencyImportService
    {
        return new AgencyImportService(
            $this->agencyImportRepository(),
            $this->privateDocumentStorage()
        );
    }

    private function agencyFiscalReviewPolicy(): AgencyFiscalReviewPolicy
    {
        return new AgencyFiscalReviewPolicy();
    }

    private function agencyAdvancedReconciliationService(): AgencyAdvancedReconciliationService
    {
        return new AgencyAdvancedReconciliationService($this->agencyFiscalReviewPolicy());
    }

    private function rentalAnnualSummaryService(): RentalAnnualSummaryService
    {
        return $this->rentalAnnualSummaryService
            ?? new RentalAnnualSummaryService($this->rentalLifecycleRepository());
    }

    private function rentalDashboardService(): RentalDashboardService
    {
        return $this->rentalDashboardService ?? new RentalDashboardService(
            $this->rentalLifecycleRepository(),
            $this->rentalUnitRepository(),
            $this->rentalAnnualSummaryService()
        );
    }

    private function rentalExportService(): RentalExportService
    {
        return $this->rentalExportService ?? new RentalExportService(
            $this->rentalLifecycleRepository(),
            $this->rentalAnnualSummaryService(),
            $this->privateDocumentStorage()
        );
    }

    private function rentScheduleService(): RentScheduleService
    {
        return $this->rentScheduleService ?? new RentScheduleService($this->rentalLifecycleRepository());
    }

    private function rentPaymentStatusService(): RentPaymentStatusService
    {
        return $this->rentPaymentStatusService ?? new RentPaymentStatusService($this->rentalLifecycleRepository());
    }

    private function rentalPaymentRequestService(): RentalPaymentRequestService
    {
        return $this->rentalPaymentRequestService ?? new RentalPaymentRequestService(
            $this->rentalLifecycleRepository(),
            $this->privateMailTemplate('rental_payment_request_subject', 'Demande de paiement - loyer {{period}}'),
            $this->privateMailTemplate(
                'rental_payment_request_body',
                "Bonjour {{tenant_name}},\n\nSauf erreur de notre part, le loyer de {{period}} pour {{property_name}} - {{unit_label}} presente un solde restant de {{balance_due}} EUR.\n\nMontant attendu : {{amount_due}} EUR\nMontant encaisse : {{amount_paid}} EUR\nDate d'echeance : {{due_date}}\n\nMerci de regulariser ce paiement ou de nous signaler tout decalage."
            ),
            $this->privateMailTemplate(
                'rental_payment_request_signature',
                "Gestion locative {{site_name}}\nContact : {{reply_to}}"
            ),
            fn (string $to, string $subject, string $html, array $attachments): bool => $this->sendPrivateMail(
                $to,
                $subject,
                $html,
                $attachments,
                $this->currentPrivateUserId(),
                true
            )
        );
    }

    private function rentalReceiptService(): RentalReceiptService
    {
        return $this->rentalReceiptService ?? new RentalReceiptService(
            $this->rentalLifecycleRepository(),
            $this->privateDocumentStorage()
        );
    }

    private function chargeRegularizationService(): ChargeRegularizationService
    {
        return $this->chargeRegularizationService ?? new ChargeRegularizationService(
            $this->rentalLifecycleRepository(),
            $this->privateDocumentStorage()
        );
    }

    private function taxDeclarationRepository(): TaxDeclarationRepository
    {
        return $this->taxDeclarationRepository ?? new TaxDeclarationRepository(editorial_database());
    }

    private function taxDeclarationSummaryService(): TaxDeclarationSummaryService
    {
        return $this->taxDeclarationSummaryService ?? new TaxDeclarationSummaryService(
            $this->taxDeclarationRepository(),
            [
                new RentalTaxDataSource(new RentalTaxDataProvider(
                    $this->rentalAnnualSummaryService(),
                    $this->rentalLifecycleRepository(),
                    new AgencyTaxBridgeNormalizer($this->agencyImportRepository())
                )),
            ]
        );
    }

    private function discussionRepository(): DiscussionRepository
    {
        return $this->discussionRepository ?? new DiscussionRepository(editorial_database());
    }

    private function discussionAttachmentStorage(): DiscussionAttachmentStorage
    {
        return $this->discussionAttachmentStorage ?? DiscussionAttachmentStorage::fromAppConfig();
    }

    private function discussionService(): DiscussionService
    {
        return $this->discussionService ?? new DiscussionService(
            $this->discussionRepository(),
            $this->privateUserRepository(),
            $this->discussionAttachmentStorage(),
            $this->eventLogger,
            $this->modulePermissionRepository(),
            mediaService: $this->discussionMediaService(),
            notificationService: $this->discussionNotificationService()
        );
    }

    private function discussionTimelineService(): DiscussionTimelineService
    {
        return new DiscussionTimelineService($this->discussionRepository());
    }

    private function discussionEventService(): DiscussionEventService
    {
        return new DiscussionEventService($this->discussionRepository());
    }

    private function discussionEventStream(): ConversationEventStream
    {
        return new ConversationEventStream($this->discussionEventService());
    }

    private function discussionNotificationService(): DiscussionNotificationService
    {
        return new DiscussionNotificationService(
            $this->discussionRepository(),
            $this->privateUserRepository(),
            fn (string $to, string $subject, string $html, int $_recipientUserId): bool => $this->sendPrivateMail(
                $to,
                $subject,
                $html,
                [],
                0
            )
        );
    }

    private function discussionMediaService(): DiscussionMediaService
    {
        return new DiscussionMediaService(
            $this->discussionRepository(),
            $this->discussionAttachmentStorage(),
            $this->eventLogger
        );
    }

    private function discussionRetentionService(): DiscussionRetentionService
    {
        return $this->discussionRetentionService ?? new DiscussionRetentionService(
            $this->discussionRepository(),
            $this->discussionAttachmentStorage(),
            $this->eventLogger
        );
    }

    private function privateDataProtectionService(): PrivateDataProtectionService
    {
        return $this->privateDataProtectionService ?? new PrivateDataProtectionService(editorial_database());
    }

    private function privateBackupService(): PrivateBackupService
    {
        return $this->privateBackupService ?? new PrivateBackupService(editorial_database());
    }

    private function normalizeDocumentId(string $documentId): string
    {
        $normalized = trim($documentId);
        if ($normalized === '' || preg_match('/\A[A-Za-z0-9._-]{1,160}\z/', $normalized) !== 1) {
            return '';
        }

        return $normalized;
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

    private function translate(string $key, string $fallback): string
    {
        if (!function_exists('t')) {
            return $fallback;
        }

        $translated = t($key);
        if (!is_string($translated) || $translated === '' || $translated === $key || $translated === '[[' . $key . ']]') {
            return $fallback;
        }

        return $translated;
    }

    private function sendPasswordResetEmail(string $email, string $token): void
    {
        $mailConfig = app_config('private.mail', []);
        if (!is_array($mailConfig) || $mailConfig === []) {
            $this->logEvent('private.password_reset.email_failed', [
                'identifier' => AppEventLogger::maskIdentifier($email),
            ]);

            return;
        }

        if (!function_exists('send_private_email')) {
            $mailerPath = ROOT_PATH . '/core/mailer.php';
            if (is_file($mailerPath)) {
                require_once $mailerPath;
            }
        }

        $url = app_url(private_portal_url('password_reset') . '/' . rawurlencode($token));
        $variables = [
            'email' => $email,
            'reset_url' => $url,
        ];
        $subject = $this->renderPrivateMailTemplate(
            $this->privateMailTemplate('password_reset_subject', 'Réinitialisation de votre espace privé'),
            $variables
        );
        $message = $this->renderPrivateMailTemplate(
            $this->privateMailTemplate(
                'password_reset_body',
                "Bonjour,\n\nRéinitialisez votre mot de passe avec ce lien sécurisé : {{reset_url}}\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message."
            ),
            $variables
        );
        $sent = function_exists('send_private_email')
            ? send_private_email(
                $email,
                sanitize_text_field($subject, 180),
                $this->plainTextToHtml($message)
            )
            : false;

        $this->logEvent($sent ? 'private.password_reset.email_sent' : 'private.password_reset.email_failed', [
            'identifier' => AppEventLogger::maskIdentifier($email),
        ]);
    }

    private function logEvent(string $event, array $context): void
    {
        $logger = $this->eventLogger ?? (function_exists('app_event_logger') ? app_event_logger() : null);
        if ($logger === null) {
            return;
        }

        $logger->security($event, $context, 'warning');
    }
}
