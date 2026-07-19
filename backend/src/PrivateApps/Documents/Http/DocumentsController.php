<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Http;

use Caramagnols\Http\Request;
use Caramagnols\Http\Response;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PrivateApps\Documents\PrivateDocumentRepository;
use Caramagnols\PrivateApps\Documents\PrivateDocumentStorage;
use Caramagnols\PrivatePortal\Http\PrivateResponseHeaders;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivatePortalSecurityGuard;

final class DocumentsController
{
    private const METHOD_POST = 'POST';
    private const DOCUMENT_UPLOAD_FIELD = 'document_file';
    private const MAX_DOCUMENT_LIST = 50;
    private const CSRF_DOCUMENTS = 'private_documents';

    /**
     * @param \Closure(string, array<string, mixed>): Response $render
     */
    public function __construct(
        private readonly PrivateAuth $auth,
        private readonly PrivatePortalSecurityGuard $securityGuard,
        private readonly PrivateUserRepository $privateUserRepository,
        private readonly PrivateModulePermissionRepository $modulePermissionRepository,
        private readonly PrivateDocumentRepository $repository,
        private readonly PrivateDocumentStorage $storage,
        private readonly \Closure $render,
        private readonly ?AppEventLogger $eventLogger = null
    ) {
    }

    public function index(Request $request): Response
    {
        $userId = $this->requireDocumentsModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $query = $request->query();
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : null;
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : null;

        return ($this->render)('modules/documents/index', [
            'privatePageTitle' => $this->translate('TXT_PRIVATE_DASHBOARD_DOCUMENTS_TITLE', 'Documents'),
            'privateUserIdentifier' => is_string($this->auth->currentIdentifier()) ? (string) $this->auth->currentIdentifier() : '',
            'privateModules' => $this->privateModuleNamesForUser($userId),
            'privateDocumentsEnabled' => true,
            'privateDocuments' => $this->repository->listActiveByUser($userId, self::MAX_DOCUMENT_LIST),
            'privateDocumentCategories' => $this->repository->listCategoriesForUser($userId),
            'privateDocumentCategoryColors' => PrivateDocumentRepository::CATEGORY_COLORS,
            'privateDocumentCategoryDefaultColor' => PrivateDocumentRepository::DEFAULT_CATEGORY_COLOR,
            'privateDocumentUploadCsrfToken' => csrf_token(self::CSRF_DOCUMENTS),
            'privateDocumentsUploadUrl' => private_portal_url('files_upload'),
            'privateDocumentCategoriesUrl' => private_portal_url('files_categories'),
            'privateFilesBaseUrl' => private_portal_url('files'),
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

    public function download(Request $request, string $documentId): Response
    {
        $userId = $this->userIdOrAccessDenied($request);
        if ($userId === null) {
            return $this->accessDenied($documentId);
        }

        $documentId = $this->normalizeDocumentId($documentId);
        if ($documentId === '') {
            return $this->notFound();
        }

        $document = $this->repository->findByDocumentIdAndUser($documentId, $userId);
        if (!is_array($document)) {
            $this->logEvent('private.files.not_found', [
                'reason' => 'document_not_found',
                'document_id' => $documentId,
                'identifier' => AppEventLogger::maskIdentifier((string) $this->auth->currentIdentifier()),
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
        $absolutePath = $this->storage->absolutePath($storagePath);
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
            'identifier' => AppEventLogger::maskIdentifier((string) $this->auth->currentIdentifier()),
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

    public function upload(Request $request): Response
    {
        $userId = $this->userIdOrAccessDenied($request);
        if ($userId === null) {
            return $this->accessDenied();
        }

        if ($request->method() !== self::METHOD_POST || !$this->securityGuard->validateCsrf($request, self::CSRF_DOCUMENTS)) {
            $this->logEvent('private.files.upload_rejected', [
                'reason' => $request->method() !== self::METHOD_POST ? 'method_not_allowed' : 'csrf_invalid',
                'private_user_id' => $userId,
            ]);

            return $this->redirect($this->documentsUrlWithError('upload_forbidden'));
        }

        $files = $request->files();
        $uploadedFile = is_array($files[self::DOCUMENT_UPLOAD_FIELD] ?? null) ? $files[self::DOCUMENT_UPLOAD_FIELD] : null;
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
        $validated = $this->storage->validateUploadedFile($uploadedFile);
        if ($validated === null) {
            $this->logEvent('private.files.upload_rejected', [
                'reason' => $this->storage->uploadError() ?? 'invalid_file',
                'private_user_id' => $userId,
            ]);

            return $this->redirect($this->documentsUrlWithError('upload_failed'));
        }

        $documentId = $this->storage->generateDocumentId();
        if ($documentId === '') {
            $this->logEvent('private.files.upload_rejected', [
                'reason' => 'document_id_generation_failed',
                'private_user_id' => $userId,
            ]);

            return $this->redirect($this->documentsUrlWithError('upload_failed'));
        }

        $stored = $this->storage->storeUploadedFile($validated, $documentId);
        if (!is_array($stored)) {
            $this->logEvent('private.files.upload_rejected', [
                'reason' => $this->storage->uploadError() ?? 'store_failed',
                'private_user_id' => $userId,
            ]);

            return $this->redirect($this->documentsUrlWithError('upload_failed'));
        }

        $created = $this->repository->create(
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
            $this->storage->deleteStoredDocument((string) $stored['storagePath'], (string) $stored['documentId']);
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

    public function categories(Request $request): Response
    {
        $userId = $this->userIdOrAccessDenied($request);
        if ($userId === null) {
            return $this->accessDenied();
        }

        if ($request->method() !== self::METHOD_POST || !$this->securityGuard->validateCsrf($request, self::CSRF_DOCUMENTS)) {
            $this->logEvent('private.files.category_rejected', [
                'reason' => $request->method() !== self::METHOD_POST ? 'method_not_allowed' : 'csrf_invalid',
                'private_user_id' => $userId,
            ]);

            return $this->redirect($this->documentsUrlWithError('invalid_request'));
        }

        $body = $request->body();
        $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : 'save_category';

        if ($action === 'delete_category') {
            $categoryId = $this->normalizeNumericId($body['category_id'] ?? null);
            if (!$this->repository->deactivateCategory($userId, $categoryId)) {
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
        $category = $this->repository->saveCategory(
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

    public function delete(Request $request, string $documentId): Response
    {
        $userId = $this->userIdOrAccessDenied($request);
        if ($userId === null) {
            return $this->accessDenied();
        }

        if ($request->method() !== self::METHOD_POST || !$this->securityGuard->validateCsrf($request, self::CSRF_DOCUMENTS)) {
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

        $document = $this->repository->findByDocumentIdAndUser($documentId, $userId);
        if (!is_array($document)) {
            $this->logEvent('private.files.delete_rejected', [
                'reason' => 'document_not_found',
                'document_id' => $documentId,
                'private_user_id' => $userId,
            ]);

            return $this->redirect($this->documentsUrlWithError('delete_not_found'));
        }

        $storagePath = is_string($document['storagePath'] ?? null) ? trim((string) $document['storagePath']) : '';
        $deactivated = $this->repository->deactivateByDocumentId($documentId, $userId);
        if (!$deactivated) {
            $this->logEvent('private.files.delete_rejected', [
                'reason' => 'database_failed',
                'document_id' => $documentId,
                'private_user_id' => $userId,
            ]);

            return $this->redirect($this->documentsUrlWithError('upload_failed'));
        }

        $this->storage->deleteStoredDocument($storagePath, $documentId);
        $this->logEvent('private.files.deleted', [
            'document_id' => $documentId,
            'private_user_id' => $userId,
        ]);

        return $this->redirect($this->documentsUrlWithNotice('document_deleted'));
    }

    private function requireDocumentsModuleUser(Request $request): int|Response
    {
        $required = $this->securityGuard->requireAuthenticated($request, private_portal_url('login'), true);
        if ($required !== null) {
            return $required;
        }

        $userId = $this->currentPrivateUserId();
        if ($userId === null) {
            return $this->handleModuleAccessDenied();
        }

        if (!$this->modulePermissionRepository->userHasModuleAccess($userId, 'documents')) {
            return $this->handleModuleAccessDenied();
        }

        return $userId;
    }

    private function userIdOrAccessDenied(Request $request): ?int
    {
        $required = $this->securityGuard->requireAuthenticated($request, private_portal_url('login'), true);
        if ($required !== null) {
            return null;
        }

        $userId = $this->currentPrivateUserId();
        if ($userId === null) {
            return null;
        }

        if (!$this->modulePermissionRepository->userHasModuleAccess($userId, 'documents')) {
            return null;
        }

        return $userId;
    }

    private function handleModuleAccessDenied(): Response
    {
        $this->logEvent('private.module.access_denied', [
            'module' => 'documents',
            'identifier' => AppEventLogger::maskIdentifier((string) $this->auth->currentIdentifier()),
        ]);

        return $this->redirect(private_portal_url('login'));
    }

    private function accessDenied(?string $documentId = null): Response
    {
        $this->logEvent('private.files.access_denied', [
            'reason' => 'forbidden',
            'document_id' => $documentId,
            'identifier' => AppEventLogger::maskIdentifier((string) $this->auth->currentIdentifier()),
        ]);

        return $this->withPrivateHeaders(new Response(
            403,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
            'Forbidden'
        ));
    }

    private function notFound(): Response
    {
        return $this->withPrivateHeaders(new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Not Found'));
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

    /**
     * @return array<int, string>
     */
    private function privateModuleNamesForUser(int $userId): array
    {
        return array_values(array_filter(array_map(
            static fn (array $module): string => (string) $module['name'],
            $this->modulePermissionRepository->activeModulesForUser($userId)
        )));
    }

    private function documentsUrlWithNotice(string $notice): string
    {
        return private_portal_url('documents') . '?notice=' . rawurlencode($notice);
    }

    private function documentsUrlWithError(string $error): string
    {
        return private_portal_url('documents') . '?error=' . rawurlencode($error);
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

    private function normalizeNumericId(mixed $value): int
    {
        if (!is_numeric($value)) {
            return 0;
        }

        $id = (int) $value;
        return $id > 0 ? $id : 0;
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

    private function redirect(string $url): Response
    {
        return $this->withPrivateHeaders(new Response(302, ['Location' => $url], ''));
    }

    private function withPrivateHeaders(Response $response): Response
    {
        return PrivateResponseHeaders::apply($response);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logEvent(string $event, array $context): void
    {
        $logger = $this->eventLogger ?? (function_exists('app_event_logger') ? app_event_logger() : null);
        if ($logger === null) {
            return;
        }

        $logger->security($event, $context, 'warning');
    }
}
