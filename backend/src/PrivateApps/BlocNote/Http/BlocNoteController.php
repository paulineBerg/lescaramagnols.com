<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\BlocNote\Http;

use Caramagnols\Http\Request;
use Caramagnols\Http\Response;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PrivateApps\BlocNote\BlocNoteRepository;
use Caramagnols\PrivatePortal\Http\PrivateResponseHeaders;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivatePortalSecurityGuard;

final class BlocNoteController
{
    private const METHOD_POST = 'POST';
    private const CSRF_BLOCNOTE = 'private_blocnote';

    /**
     * @param \Closure(string, array<string, mixed>): Response $render
     */
    public function __construct(
        private readonly PrivateAuth $auth,
        private readonly PrivatePortalSecurityGuard $securityGuard,
        private readonly PrivateUserRepository $privateUserRepository,
        private readonly PrivateModulePermissionRepository $modulePermissionRepository,
        private readonly BlocNoteRepository $repository,
        private readonly \Closure $render,
        private readonly ?AppEventLogger $eventLogger = null
    ) {
    }

    public function handle(Request $request): Response
    {
        $userId = $this->requireBlocNoteModuleUser($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $defaultCategoryId = $this->repository->ensureDefaultCategory($userId);
        $query = $request->query();
        $view = $this->resolveView(is_string($query['view'] ?? null) ? (string) $query['view'] : 'dashboard');
        $notice = is_string($query['notice'] ?? null) ? (string) $query['notice'] : null;
        $error = is_string($query['error'] ?? null) ? (string) $query['error'] : null;
        $formValues = $this->defaultFormValues($defaultCategoryId);

        $editingNoteId = $this->normalizeNumericId($query['note_id'] ?? null);
        if ($editingNoteId > 0) {
            $note = $this->repository->findNote($editingNoteId, $userId);
            if (is_array($note)) {
                $view = 'form';
                $formValues = $this->formValuesFromNote($note, $defaultCategoryId);
            } else {
                $error = 'note_not_found';
            }
        }

        if ($request->method() !== self::METHOD_POST) {
            return $this->renderBlocNote($userId, $view, $formValues, $notice, $error);
        }

        if (!$this->securityGuard->validateCsrf($request, self::CSRF_BLOCNOTE)) {
            return $this->renderBlocNote($userId, $view, $formValues, null, 'invalid_request');
        }

        $body = $request->body();
        $action = is_string($body['action'] ?? null) ? strtolower(trim((string) $body['action'])) : '';

        if ($action === 'save_note') {
            $formValues = $this->formValuesFromBody($body, $defaultCategoryId);
            if ($this->repository->saveNote($userId, $formValues)) {
                $this->logEvent('private.blocnote.note.saved', [
                    'private_user_id' => $userId,
                    'note_id' => (int) $formValues['note_id'],
                ]);

                return $this->redirect($this->blocNoteUrl(['view' => 'notes', 'notice' => 'note_saved']));
            }

            return $this->renderBlocNote($userId, 'form', $formValues, null, 'note_required');
        }

        if ($action === 'delete_note') {
            $noteId = $this->normalizeNumericId($body['note_id'] ?? $body['delete_note'] ?? null);
            if ($this->repository->deleteNote($noteId, $userId)) {
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
            $color = is_string($body['category_color'] ?? null)
                ? (string) $body['category_color']
                : BlocNoteRepository::DEFAULT_COLOR;
            if ($this->repository->saveCategory($userId, $categoryId, $name, $color)) {
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
            if ($this->repository->setDefaultCategory($userId, $categoryId)) {
                return $this->redirect($this->blocNoteUrl(['view' => 'categories', 'notice' => 'default_category_saved']));
            }

            return $this->redirect($this->blocNoteUrl(['view' => 'categories', 'error' => 'category_failed']));
        }

        if ($action === 'delete_category') {
            $categoryId = $this->normalizeNumericId($body['category_id'] ?? null);
            if ($this->repository->deleteCategory($userId, $categoryId)) {
                $this->logEvent('private.blocnote.category.deleted', [
                    'private_user_id' => $userId,
                    'category_id' => $categoryId,
                ]);

                return $this->redirect($this->blocNoteUrl(['view' => 'categories', 'notice' => 'category_deleted']));
            }

            return $this->redirect($this->blocNoteUrl(['view' => 'categories', 'error' => 'category_delete_failed']));
        }

        return $this->renderBlocNote($userId, $view, $formValues, null, 'invalid_request');
    }

    private function requireBlocNoteModuleUser(Request $request): int|Response
    {
        $required = $this->securityGuard->requireAuthenticated($request, private_portal_url('login'), true);
        if ($required !== null) {
            return $required;
        }

        $userId = $this->currentPrivateUserId();
        if ($userId === null) {
            return $this->handleModuleAccessDenied('blocnote');
        }

        if (!$this->modulePermissionRepository->userHasModuleAccess($userId, 'blocnote')) {
            return $this->handleModuleAccessDenied('blocnote');
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

    /**
     * @param array<string, mixed> $formValues
     */
    private function renderBlocNote(
        int $userId,
        string $view,
        array $formValues,
        ?string $notice,
        ?string $error
    ): Response {
        $view = $this->resolveView($view);

        return ($this->render)('modules/blocnote/index', [
            'privatePageTitle' => 'Bloc-note',
            'privateUserIdentifier' => is_string($this->auth->currentIdentifier())
                ? (string) $this->auth->currentIdentifier()
                : '',
            'privateModules' => $this->privateModuleNamesForUser($userId),
            'privateTopNavLabel' => 'Pages du bloc-note',
            'privateTopNavItems' => [
                ['label' => 'Tableau de bord', 'href' => $this->blocNoteUrl(['view' => 'dashboard']), 'icon' => '📊', 'active' => $view === 'dashboard'],
                ['label' => 'Mes notes', 'href' => $this->blocNoteUrl(['view' => 'notes']), 'icon' => '📝', 'active' => $view === 'notes'],
                ['label' => 'Catégories', 'href' => $this->blocNoteUrl(['view' => 'categories']), 'icon' => '🏷', 'active' => $view === 'categories'],
                ['label' => 'Aide', 'href' => $this->blocNoteUrl(['view' => 'help']), 'icon' => '?', 'active' => $view === 'help'],
            ],
            'blocNote' => [
                'view' => $view,
                'baseUrl' => private_portal_url('blocnote'),
                'csrfToken' => csrf_token(self::CSRF_BLOCNOTE),
                'notes' => $this->repository->listNotes($userId),
                'categories' => $this->repository->listCategories($userId),
                'dashboard' => $this->repository->dashboardData($userId),
                'formValues' => $formValues,
                'categoryColors' => BlocNoteRepository::CATEGORY_COLORS,
                'categoryDefaultColor' => BlocNoteRepository::DEFAULT_COLOR,
            ],
            'notice' => $this->notice($notice),
            'errorMessage' => $this->error($error),
            'privateDashboardLogoutUrl' => private_portal_url('logout'),
            'privateLogoutCsrfToken' => csrf_token('private_logout'),
        ]);
    }

    /**
     * @return array{note_id: int, title: string, content: string, category_id: int}
     */
    private function defaultFormValues(int $defaultCategoryId): array
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
    private function formValuesFromBody(array $body, int $defaultCategoryId): array
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
    private function formValuesFromNote(array $note, int $defaultCategoryId): array
    {
        $categoryId = is_numeric($note['categoryId'] ?? null) ? (int) $note['categoryId'] : 0;

        return [
            'note_id' => is_numeric($note['id'] ?? null) ? (int) $note['id'] : 0,
            'title' => is_string($note['title'] ?? null) ? (string) $note['title'] : '',
            'content' => is_string($note['contentText'] ?? null) ? (string) $note['contentText'] : '',
            'category_id' => $categoryId > 0 ? $categoryId : $defaultCategoryId,
        ];
    }

    private function normalizeNumericId(mixed $value): int
    {
        if (!is_numeric($value)) {
            return 0;
        }

        $id = (int) $value;
        return $id > 0 ? $id : 0;
    }

    private function resolveView(string $view): string
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

    private function notice(?string $key): ?string
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

    private function error(?string $key): ?string
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
        return PrivateResponseHeaders::apply(new Response(302, ['Location' => $url], ''));
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
