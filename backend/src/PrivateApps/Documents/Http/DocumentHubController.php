<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Http;

use Caramagnols\Http\Request;
use Caramagnols\Http\Response;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PrivateApps\Documents\Contract\DocumentEntityRef;
use Caramagnols\PrivateApps\Documents\PersonalDocumentIntegration;
use Caramagnols\PrivateApps\Documents\Registry\DocumentIntegrationRegistry;
use Caramagnols\PrivateApps\Documents\Repository\DocumentHubRepository;
use Caramagnols\PrivateApps\Documents\Repository\DocumentTaxonomyRepository;
use Caramagnols\PrivateApps\Documents\Service\DocumentImportService;
use Caramagnols\PrivateApps\Documents\Service\DocumentLinkService;
use Caramagnols\PrivateApps\Documents\Service\DocumentStorageService;
use Caramagnols\PrivatePortal\Http\PrivateResponseHeaders;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivatePortalSecurityGuard;

/**
 * Centre de documents et point d'entrée HTTP unique du hub documentaire :
 * import profilé, téléchargement streamé, rattachements, catégorie, archivage,
 * corbeille. Toutes les webapps passent par ces routes.
 */
final class DocumentHubController
{
    private const METHOD_POST = 'POST';
    private const CSRF_HUB = 'private_document_hub';
    private const UPLOAD_FIELD = 'hub_files';
    private const PAGE_SIZE = 50;

    /**
     * @param \Closure(string, array<string, mixed>): Response $render
     */
    public function __construct(
        private readonly PrivateAuth $auth,
        private readonly PrivatePortalSecurityGuard $securityGuard,
        private readonly PrivateUserRepository $privateUserRepository,
        private readonly PrivateModulePermissionRepository $modulePermissionRepository,
        private readonly DocumentHubRepository $repository,
        private readonly DocumentTaxonomyRepository $taxonomy,
        private readonly DocumentStorageService $storage,
        private readonly DocumentImportService $importService,
        private readonly DocumentLinkService $linkService,
        private readonly \Closure $render,
        private readonly ?AppEventLogger $eventLogger = null
    ) {
    }

    public function index(Request $request): Response
    {
        $userId = $this->requireUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $query = $request->query();
        $view = is_string($query['vue'] ?? null) ? strtolower(trim((string) $query['vue'])) : '';
        $search = is_string($query['q'] ?? null) ? trim((string) $query['q']) : '';
        $category = is_string($query['categorie'] ?? null) ? strtolower(trim((string) $query['categorie'])) : '';
        $year = is_numeric($query['annee'] ?? null) ? (int) $query['annee'] : 0;
        $extension = is_string($query['type'] ?? null) ? strtolower(trim((string) $query['type'])) : '';
        $entityType = is_string($query['entite'] ?? null) ? trim((string) $query['entite']) : '';
        $entityId = is_string($query['entite_id'] ?? null) ? trim((string) $query['entite_id']) : '';
        $page = is_numeric($query['page'] ?? null) ? max(1, (int) $query['page']) : 1;

        $filters = [];
        if ($search !== '') {
            $filters['search'] = $search;
        }
        if ($category !== '' && preg_match('/\A[a-z0-9_.]+\z/', $category) === 1) {
            $filters['category_code'] = $category;
        }
        if ($year > 1900 && $year < 2200) {
            $filters['fiscal_year'] = $year;
        }
        if ($extension !== '' && preg_match('/\A[a-z0-9]{1,16}\z/', $extension) === 1) {
            $filters['extension'] = $extension;
        }
        if ($entityType !== '' && DocumentIntegrationRegistry::isKnownEntityType($entityType) && $entityId !== '') {
            $filters['entity_type'] = $entityType;
            $filters['entity_id'] = $entityId;
        }
        if ($view === 'a-classer') {
            $filters['inbox_only'] = true;
        } elseif ($view === 'corbeille') {
            $filters['status'] = DocumentHubRepository::DOC_STATUS_TRASHED;
        } elseif ($view === 'archives') {
            $filters['status'] = DocumentHubRepository::DOC_STATUS_ARCHIVED;
        }

        // Sur-échantillonnage puis filtrage par autorisation réelle (créateur ou
        // accès à une entité liée) ; jamais d'exposition par simple connaissance d'UID.
        $rows = $this->repository->listDocuments($filters, self::PAGE_SIZE * 3, ($page - 1) * self::PAGE_SIZE);
        $documents = [];
        foreach ($rows as $row) {
            if (!$this->linkService->userCanAccessDocument($row, $userId)) {
                continue;
            }

            $row['links_described'] = $this->linkService->describeLinks(is_array($row['links'] ?? null) ? $row['links'] : []);
            $documents[] = $row;
            if (count($documents) >= self::PAGE_SIZE) {
                break;
            }
        }

        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : '';
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : '';

        return ($this->render)('modules/documents/hub', [
            'privatePageTitle' => 'Bibliothèque de documents',
            'privateUserIdentifier' => is_string($this->auth->currentIdentifier()) ? (string) $this->auth->currentIdentifier() : '',
            'privateModules' => $this->moduleNamesForUser($userId),
            'hubDocuments' => $documents,
            'hubCategories' => $this->taxonomy->listActive(),
            'hubStats' => $this->repository->stats(),
            'hubFilters' => [
                'vue' => $view,
                'q' => $search,
                'categorie' => $category,
                'annee' => $year > 0 ? (string) $year : '',
                'type' => $extension,
                'entite' => $entityType,
                'entite_id' => $entityId,
                'page' => $page,
            ],
            'hubImportProfileCode' => PersonalDocumentIntegration::PROFILE_PERSONAL,
            'hubImportContext' => [
                ['entity_type' => PersonalDocumentIntegration::ENTITY_PERSONAL, 'entity_id' => (string) $userId],
            ],
            'hubCsrfToken' => csrf_token(self::CSRF_HUB),
            'hubUrl' => private_portal_url('documents_hub'),
            'hubImportUrl' => private_portal_url('documents_hub_import'),
            'hubActionUrl' => private_portal_url('documents_hub_action'),
            'hubFileBaseUrl' => private_portal_url('documents_hub_file'),
            'hubReturnRoute' => 'documents_hub',
            'notice' => $this->noticeMessage($notice, $query),
            'errorMessage' => $this->errorMessage($error),
            'privateDashboardLogoutUrl' => private_portal_url('logout'),
            'privateLogoutCsrfToken' => csrf_token('private_logout'),
        ]);
    }

    public function import(Request $request): Response
    {
        $userId = $this->requireUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $returnRoute = $this->normalizeReturnRoute($request->body()['return_route'] ?? null);
        $returnUrl = $this->normalizeReturnUrl($request->body()['return_url'] ?? null);
        if ($request->method() !== self::METHOD_POST || !$this->securityGuard->validateCsrf($request, self::CSRF_HUB)) {
            return $this->redirectTo($returnRoute, ['error' => 'invalid_request'], $returnUrl);
        }

        $body = $request->body();
        $profileCode = is_string($body['profile_code'] ?? null) ? trim((string) $body['profile_code']) : '';
        $entityRefs = $this->entityRefsFromBody($body);

        $files = $this->normalizeUploadedFiles($request->files()[self::UPLOAD_FIELD] ?? null);
        if ($files === []) {
            return $this->redirectTo($returnRoute, ['error' => 'missing_file'], $returnUrl);
        }

        $meta = [
            'category_code' => is_string($body['category_code'] ?? null) ? trim((string) $body['category_code']) : '',
            'title' => is_string($body['title'] ?? null) ? trim((string) $body['title']) : '',
            'description' => is_string($body['description'] ?? null) ? trim((string) $body['description']) : '',
            'document_date' => is_string($body['document_date'] ?? null) ? trim((string) $body['document_date']) : '',
            'fiscal_year' => is_numeric($body['fiscal_year'] ?? null) ? (int) $body['fiscal_year'] : null,
        ];

        $results = $this->importService->importBatch($userId, $profileCode, $files, $entityRefs, $meta);

        $imported = 0;
        $failed = 0;
        $firstError = '';
        foreach ($results as $result) {
            if ($result['ok']) {
                $imported++;
            } else {
                $failed++;
                if ($firstError === '') {
                    $firstError = $result['error_code'];
                }
            }
        }

        $params = ['notice' => 'hub_import_done', 'ok' => (string) $imported, 'ko' => (string) $failed];
        if ($failed > 0 && $firstError !== '') {
            $params['err'] = $firstError;
        }
        if ($imported === 0 && $failed > 0) {
            $params = ['error' => $firstError !== '' ? $firstError : 'upload_failed'];
        }

        return $this->redirectTo($returnRoute, $params, $returnUrl);
    }

    public function download(Request $request, string $documentUid): Response
    {
        $userId = $this->requireUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $document = $this->repository->findDocumentByUid($documentUid);
        if ($document === null) {
            return $this->plain(404, 'Not Found');
        }

        $document['links'] = $this->repository->linksForDocument((int) $document['id']);
        if (!$this->linkService->userCanAccessDocument($document, $userId)) {
            $this->logEvent('private.document_hub.access_denied', [
                'document_uid' => $documentUid,
                'private_user_id' => $userId,
            ], 'warning');

            return $this->plain(403, 'Forbidden');
        }

        $status = (string) ($document['status'] ?? '');
        if (in_array($status, [DocumentHubRepository::DOC_STATUS_PENDING_DELETION, DocumentHubRepository::DOC_STATUS_DELETED], true)) {
            return $this->plain(404, 'Not Found');
        }

        $scanStatus = (string) ($document['scan_status'] ?? 'clean');
        if ($scanStatus === 'infected') {
            return $this->plain(403, 'Forbidden');
        }

        $storageKey = (string) ($document['storage_key'] ?? '');
        $absolutePath = $this->storage->absolutePathForKey($storageKey);
        if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
            $this->logEvent('private.document_hub.file_missing', [
                'document_uid' => $documentUid,
            ], 'warning');

            return $this->plain(404, 'Not Found');
        }

        $mimeType = (string) ($document['mime_type'] ?? '');
        if (preg_match('/\A[a-z0-9.+-]+\/[a-z0-9.+-]+\z/i', $mimeType) !== 1) {
            $mimeType = 'application/octet-stream';
        }

        $filename = $this->downloadFilename($document);
        $this->logEvent('private.document_hub.downloaded', [
            'document_uid' => $documentUid,
            'private_user_id' => $userId,
            'size_bytes' => (int) ($document['stored_size'] ?? 0),
        ]);

        return PrivateResponseHeaders::apply(new StreamedFileResponse($absolutePath, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) (int) ($document['stored_size'] ?? filesize($absolutePath)),
            'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]));
    }

    public function action(Request $request): Response
    {
        $userId = $this->requireUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $body = $request->body();
        $returnRoute = $this->normalizeReturnRoute($body['return_route'] ?? null);
        if ($request->method() !== self::METHOD_POST || !$this->securityGuard->validateCsrf($request, self::CSRF_HUB)) {
            return $this->redirectTo($returnRoute, ['error' => 'invalid_request']);
        }

        $documentUid = is_string($body['document_uid'] ?? null) ? trim((string) $body['document_uid']) : '';
        $document = $this->repository->findDocumentByUid($documentUid);
        if ($document === null) {
            return $this->redirectTo($returnRoute, ['error' => 'document_not_found']);
        }

        $document['links'] = $this->repository->linksForDocument((int) $document['id']);
        if (!$this->linkService->userCanAccessDocument($document, $userId)) {
            return $this->redirectTo($returnRoute, ['error' => 'document_forbidden']);
        }

        $documentId = (int) $document['id'];
        $action = is_string($body['hub_action'] ?? null) ? strtolower(trim((string) $body['hub_action'])) : '';

        switch ($action) {
            case 'set_category':
                $categoryCode = is_string($body['category_code'] ?? null) ? strtolower(trim((string) $body['category_code'])) : '';
                if (!$this->taxonomy->isActiveCategoryCode($categoryCode)) {
                    return $this->redirectTo($returnRoute, ['error' => 'unknown_category']);
                }
                if (!$this->isEditableStatus($document)) {
                    return $this->redirectTo($returnRoute, ['error' => 'document_not_editable']);
                }
                $done = $this->repository->updateDocumentCategory($documentId, $categoryCode);

                return $this->redirectTo($returnRoute, $done ? ['notice' => 'hub_category_saved'] : ['error' => 'action_failed']);

            case 'update_meta':
                if (!$this->isEditableStatus($document)) {
                    return $this->redirectTo($returnRoute, ['error' => 'document_not_editable']);
                }
                $documentDate = is_string($body['document_date'] ?? null) ? trim((string) $body['document_date']) : '';
                $done = $this->repository->updateDocumentMeta(
                    $documentId,
                    is_string($body['title'] ?? null) ? trim((string) $body['title']) : '',
                    is_string($body['description'] ?? null) ? trim((string) $body['description']) : '',
                    preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $documentDate) === 1 ? $documentDate : null,
                    is_numeric($body['fiscal_year'] ?? null) ? (int) $body['fiscal_year'] : null
                );

                return $this->redirectTo($returnRoute, $done ? ['notice' => 'hub_meta_saved'] : ['error' => 'action_failed']);

            case 'archive':
                $done = $this->repository->transitionStatus($documentId, DocumentHubRepository::DOC_STATUS_ARCHIVED);
                if ($done) {
                    $this->logEvent('private.document_hub.archived', ['document_uid' => $documentUid, 'private_user_id' => $userId]);
                }

                return $this->redirectTo($returnRoute, $done ? ['notice' => 'hub_archived'] : ['error' => 'action_failed']);

            case 'trash':
                $done = $this->repository->transitionStatus($documentId, DocumentHubRepository::DOC_STATUS_TRASHED);
                if ($done) {
                    $this->logEvent('private.document_hub.trashed', ['document_uid' => $documentUid, 'private_user_id' => $userId]);
                }

                return $this->redirectTo($returnRoute, $done ? ['notice' => 'hub_trashed'] : ['error' => 'action_failed']);

            case 'restore':
                $done = $this->repository->transitionStatus($documentId, DocumentHubRepository::DOC_STATUS_ACTIVE);

                return $this->redirectTo($returnRoute, $done ? ['notice' => 'hub_restored'] : ['error' => 'action_failed']);

            case 'add_link':
            case 'remove_link':
                $ref = $this->entityRefFromValues(
                    $body['entity_type'] ?? null,
                    $body['entity_id'] ?? null,
                    $body['link_role'] ?? null
                );
                if ($ref === null) {
                    return $this->redirectTo($returnRoute, ['error' => 'invalid_entity']);
                }

                $error = $action === 'add_link'
                    ? $this->linkService->attach($documentId, $ref, $userId)
                    : $this->linkService->detach($documentId, $ref, $userId);
                if ($error !== null) {
                    return $this->redirectTo($returnRoute, ['error' => $error]);
                }

                $this->logEvent(
                    $action === 'add_link' ? 'private.document_hub.link_added' : 'private.document_hub.link_removed',
                    [
                        'document_uid' => $documentUid,
                        'entity_type' => $ref->entityType,
                        'private_user_id' => $userId,
                    ]
                );

                return $this->redirectTo($returnRoute, ['notice' => $action === 'add_link' ? 'hub_link_added' : 'hub_link_removed']);

            default:
                return $this->redirectTo($returnRoute, ['error' => 'invalid_request']);
        }
    }

    // ------------------------------------------------------------------
    // Interne
    // ------------------------------------------------------------------

    private function requireUser(Request $request): int|Response
    {
        $required = $this->securityGuard->requireAuthenticated($request, private_portal_url('login'), true);
        if ($required !== null) {
            return $required;
        }

        $identifier = $this->auth->currentIdentifier();
        $user = is_string($identifier) && trim($identifier) !== ''
            ? $this->privateUserRepository->findByEmail($identifier)
            : null;
        $userId = is_array($user) && is_numeric($user['id'] ?? null) ? (int) $user['id'] : 0;
        $status = is_array($user) && is_string($user['status'] ?? null) ? strtolower((string) $user['status']) : '';

        if ($userId <= 0 || $status !== 'active' || !$this->modulePermissionRepository->userHasModuleAccess($userId, 'documents')) {
            $this->logEvent('private.module.access_denied', [
                'module' => 'documents',
                'identifier' => AppEventLogger::maskIdentifier((string) $identifier),
            ], 'warning');

            return PrivateResponseHeaders::apply(new Response(302, ['Location' => private_portal_url('login')], ''));
        }

        return $userId;
    }

    /**
     * @param mixed $filesField champ $_FILES brut (multi ou simple)
     * @return array<int, array{name: string, tmp_name: string, size: int|string, error: int}>
     */
    private function normalizeUploadedFiles(mixed $filesField): array
    {
        if (!is_array($filesField)) {
            return [];
        }

        // Champ simple.
        if (is_string($filesField['name'] ?? null)) {
            return [[
                'name' => (string) $filesField['name'],
                'tmp_name' => is_string($filesField['tmp_name'] ?? null) ? (string) $filesField['tmp_name'] : '',
                'size' => is_numeric($filesField['size'] ?? null) ? (int) $filesField['size'] : 0,
                'error' => is_numeric($filesField['error'] ?? null) ? (int) $filesField['error'] : UPLOAD_ERR_NO_FILE,
            ]];
        }

        // Champ multiple (name[]).
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

    /**
     * @param array<string, mixed> $body
     * @return array<int, DocumentEntityRef>
     */
    private function entityRefsFromBody(array $body): array
    {
        $types = $body['entity_type'] ?? null;
        $ids = $body['entity_id'] ?? null;
        $roles = $body['link_role'] ?? null;

        if (is_string($types)) {
            $ref = $this->entityRefFromValues($types, $ids, is_string($roles) ? $roles : null);

            return $ref !== null ? [$ref] : [];
        }

        if (!is_array($types) || !is_array($ids)) {
            return [];
        }

        $refs = [];
        foreach ($types as $index => $type) {
            $ref = $this->entityRefFromValues(
                $type,
                $ids[$index] ?? null,
                is_array($roles) ? ($roles[$index] ?? null) : null
            );
            if ($ref !== null) {
                $refs[] = $ref;
            }
        }

        return $refs;
    }

    private function entityRefFromValues(mixed $type, mixed $id, mixed $role): ?DocumentEntityRef
    {
        $type = is_string($type) ? trim($type) : '';
        $id = is_string($id) || is_numeric($id) ? trim((string) $id) : '';
        $role = is_string($role) && trim($role) !== '' ? strtolower(trim($role)) : DocumentEntityRef::DEFAULT_ROLE;

        if ($type === '' || $id === '' || !DocumentIntegrationRegistry::isKnownEntityType($type)) {
            return null;
        }

        try {
            return new DocumentEntityRef($type, $id, $role);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function isEditableStatus(array $document): bool
    {
        return in_array((string) ($document['status'] ?? ''), [
            DocumentHubRepository::DOC_STATUS_ACTIVE,
            DocumentHubRepository::DOC_STATUS_CLOSED,
        ], true);
    }

    private function normalizeReturnRoute(mixed $value): string
    {
        $route = is_string($value) ? trim($value) : '';

        return preg_match('/\A[a-z0-9_]{1,64}\z/', $route) === 1 ? $route : 'documents_hub';
    }

    private function normalizeReturnUrl(mixed $value): ?string
    {
        $url = is_string($value) ? trim($value) : '';
        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || isset($parts['scheme']) || isset($parts['host'])) {
            return null;
        }

        $path = is_string($parts['path'] ?? null) ? (string) $parts['path'] : '';
        $privateBase = rtrim(dirname(private_portal_url('login')), '/');
        if ($privateBase === '' || $privateBase === '.') {
            $privateBase = '/';
        }
        if (!str_starts_with($path, $privateBase . '/')) {
            return null;
        }

        $query = is_string($parts['query'] ?? null) && $parts['query'] !== '' ? '?' . $parts['query'] : '';

        return $path . $query;
    }

    /**
     * @param array<string, string> $params
     */
    private function redirectTo(string $routeName, array $params, ?string $returnUrl = null): Response
    {
        $url = $returnUrl ?? private_portal_url($routeName);
        if ($params !== []) {
            $url .= str_contains($url, '?') ? '&' : '?';
            $url .= http_build_query($params);
        }

        return PrivateResponseHeaders::apply(new Response(302, ['Location' => $url], ''));
    }

    private function plain(int $status, string $message): Response
    {
        return PrivateResponseHeaders::apply(
            new Response($status, ['Content-Type' => 'text/plain; charset=UTF-8'], $message)
        );
    }

    /**
     * @param array<string, mixed> $document
     */
    private function downloadFilename(array $document): string
    {
        $name = is_string($document['original_filename'] ?? null) ? trim((string) $document['original_filename']) : '';
        if ($name === '') {
            $extension = is_string($document['extension'] ?? null) ? (string) $document['extension'] : 'bin';
            $name = 'document.' . $extension;
        }

        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]/', '', $name));
        $name = str_replace(["\r", "\n", "\t", '/', '\\'], ' ', $name);
        $name = trim((string) preg_replace('/\s+/', ' ', $name));

        return $name !== '' ? $name : 'document';
    }

    /**
     * @return array<int, string>
     */
    private function moduleNamesForUser(int $userId): array
    {
        return array_values(array_filter(array_map(
            static fn (array $module): string => (string) $module['name'],
            $this->modulePermissionRepository->activeModulesForUser($userId)
        )));
    }

    /**
     * @param array<string, mixed> $query
     */
    private function noticeMessage(string $notice, array $query): ?string
    {
        return match ($notice) {
            'hub_import_done' => sprintf(
                '%d document(s) importé(s), %d refusé(s).',
                is_numeric($query['ok'] ?? null) ? (int) $query['ok'] : 0,
                is_numeric($query['ko'] ?? null) ? (int) $query['ko'] : 0
            ),
            'hub_category_saved' => 'Catégorie enregistrée.',
            'hub_meta_saved' => 'Informations enregistrées.',
            'hub_archived' => 'Document archivé. Il reste consultable et exportable.',
            'hub_trashed' => 'Document placé dans la corbeille.',
            'hub_restored' => 'Document restauré.',
            'hub_link_added' => 'Rattachement ajouté.',
            'hub_link_removed' => 'Rattachement retiré.',
            default => null,
        };
    }

    private function errorMessage(string $error): ?string
    {
        return match ($error) {
            '' => null,
            'invalid_request' => 'Requête invalide.',
            'missing_file' => 'Aucun fichier reçu.',
            'forbidden_extension' => 'Format de fichier non autorisé.',
            'mime_mismatch' => 'Le contenu du fichier ne correspond pas à son extension.',
            'invalid_signature' => 'Le fichier est corrompu ou son format est invalide.',
            'macro_detected' => 'Les documents contenant des macros sont refusés.',
            'encrypted_document' => 'Les fichiers protégés par mot de passe ou chiffrés sont refusés.',
            'file_too_large' => 'Fichier trop volumineux.',
            'batch_too_large' => 'Le lot dépasse la taille maximale autorisée.',
            'image_too_many_pixels' => 'Image trop grande (nombre de pixels).',
            'infected' => 'Fichier bloqué par le contrôle antivirus.',
            'entity_forbidden', 'document_forbidden' => 'Vous n\'avez pas accès à cet élément.',
            'entity_not_found' => 'Élément métier introuvable.',
            'document_not_found' => 'Document introuvable.',
            'document_not_editable' => 'Ce document n\'est plus modifiable (archivé ou gelé).',
            'unknown_category' => 'Catégorie inconnue ou inactive.',
            'last_link' => 'Impossible de retirer le dernier rattachement ; utilisez la corbeille.',
            default => 'L\'opération a échoué (' . $error . ').',
        };
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logEvent(string $event, array $context, string $level = 'info'): void
    {
        $logger = $this->eventLogger ?? (function_exists('app_event_logger') ? app_event_logger() : null);
        if ($logger === null) {
            return;
        }

        $logger->security($event, $context, $level);
    }
}
