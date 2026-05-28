<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Operations;

use Caramagnols\PrivatePortal\Http\PrivateRouteResolver;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;

final class PrivateLegacyRetirementService
{
    public const STATUS_KEPT = 'kept';
    public const STATUS_REDIRECTED = 'redirected';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_RETIRED = 'retired';

    /** @var array<int, string> */
    private const ACTIVE_PRIVATE_TEMPLATES = [
        'dashboard.php',
        'layout.php',
        'login.php',
        'notice.php',
        'password_forgot.php',
        'password_form.php',
    ];

    /** @var array<int, array{methods: array<int, string>, path: string, handler: string, status: string, reason: string}> */
    private const BLOCKED_LEGACY_ROUTES = [
        [
            'methods' => ['POST'],
            'path' => '/privacy/anonymize',
            'handler' => 'privacy_anonymize',
            'status' => self::STATUS_BLOCKED,
            'reason' => 'Endpoint retire: la suppression de compte passe par sauvegarde, purge donnees, avertissement J+20 et suppression compte J+30.',
        ],
    ];

    public function __construct(
        private readonly PrivateRouteResolver $routeResolver,
        private readonly PrivateModuleRegistry $moduleRegistry,
        private readonly string $templateDirectory = ''
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function inventory(): array
    {
        $routes = $this->routeInventory();
        $blockedRoutes = $this->blockedRouteInventory();
        $templates = $this->templateInventory();
        $permissions = $this->permissionInventory();
        $fileEndpoints = $this->fileEndpointInventory($routes);

        return [
            'success' => true,
            'ready' => $this->isReady($routes, $blockedRoutes, $templates, $permissions),
            'summary' => [
                'activeRoutes' => count($routes),
                'blockedLegacyRoutes' => count($blockedRoutes),
                'templates' => count($templates),
                'permissions' => count($permissions),
                'fileEndpoints' => count($fileEndpoints),
            ],
            'routes' => $routes,
            'blockedLegacyRoutes' => $blockedRoutes,
            'templates' => $templates,
            'permissions' => $permissions,
            'fileEndpoints' => $fileEndpoints,
            'obsoletePermissions' => [],
            'obsoleteTemplates' => [],
            'runbook' => [
                'backupBeforeDelete' => true,
                'tagBeforeDelete' => true,
                'noDirectPrivateFileEndpoint' => $this->fileEndpointsAreControlled($fileEndpoints),
                'legacyRouteResolutionBlocked' => $this->blockedRoutesAreNotActive($routes, $blockedRoutes),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function allowedStatuses(): array
    {
        return [
            self::STATUS_KEPT,
            self::STATUS_REDIRECTED,
            self::STATUS_BLOCKED,
            self::STATUS_RETIRED,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function routeInventory(): array
    {
        $routes = [];
        foreach ($this->routeResolver->routeDefinitions() as $definition) {
            $handler = is_array($definition['handler'] ?? null) ? $definition['handler'] : [];
            $handlerType = (string) ($handler['type'] ?? '');
            $page = (string) ($handler['page'] ?? '');
            $path = (string) ($definition['path'] ?? '');
            $status = $handlerType === 'redirect' ? self::STATUS_REDIRECTED : self::STATUS_KEPT;
            $routes[] = [
                'methods' => array_values(array_map('strval', (array) ($definition['methods'] ?? []))),
                'path' => $path,
                'handlerType' => $handlerType,
                'handler' => $page !== '' ? $page : (string) ($handler['location'] ?? ''),
                'status' => $status,
                'reason' => $this->routeReason($status, $path, $page),
            ];
        }

        return $routes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function blockedRouteInventory(): array
    {
        $basePath = $this->routeResolver->basePath();

        return array_map(
            static function (array $route) use ($basePath): array {
                return [
                    'methods' => $route['methods'],
                    'path' => $basePath . $route['path'],
                    'handler' => $route['handler'],
                    'status' => $route['status'],
                    'reason' => $route['reason'],
                ];
            },
            self::BLOCKED_LEGACY_ROUTES
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templateInventory(): array
    {
        $directory = $this->templateDirectory !== '' ? $this->templateDirectory : ROOT_PATH . '/templates/private';
        $templates = [];
        foreach (self::ACTIVE_PRIVATE_TEMPLATES as $template) {
            $templates[] = [
                'file' => $template,
                'path' => $directory . '/' . $template,
                'status' => self::STATUS_KEPT,
                'exists' => is_file($directory . '/' . $template),
                'reason' => 'Template prive encore utilise par le front-controller prive.',
            ];
        }

        return $templates;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function permissionInventory(): array
    {
        return array_map(
            static fn (string $moduleCode): array => [
                'module' => $moduleCode,
                'status' => self::STATUS_KEPT,
                'obsolete' => false,
                'reason' => 'Permission active dans PrivateModuleRegistry.',
            ],
            $this->moduleRegistry->moduleCodes()
        );
    }

    /**
     * @param array<int, array<string, mixed>> $routes
     *
     * @return array<int, array<string, mixed>>
     */
    private function fileEndpointInventory(array $routes): array
    {
        $fileEndpoints = [];
        foreach ($routes as $route) {
            $handler = (string) ($route['handler'] ?? '');
            if (!in_array($handler, ['files', 'rental_document_file', 'discussion_file', 'discussion_file_preview'], true)) {
                continue;
            }
            $fileEndpoints[] = [
                'path' => (string) ($route['path'] ?? ''),
                'handler' => $handler,
                'status' => self::STATUS_KEPT,
                'controlled' => true,
                'reason' => 'Endpoint fichier conserve uniquement derriere session, permission module et controle metier.',
            ];
        }

        return $fileEndpoints;
    }

    private function routeReason(string $status, string $path, string $page): string
    {
        if ($status === self::STATUS_REDIRECTED) {
            return 'Route legacy redirigee explicitement.';
        }

        if ($page === 'privacy_export' || $page === 'ops_backup') {
            return 'Route operationnelle conservee pour export/restauration et exploitation controlee.';
        }

        if (str_contains($path, '/files/')) {
            return 'Endpoint fichier conserve derriere controles serveur.';
        }

        return 'Route privee conservee jusqu a bascule applicative validee.';
    }

    /**
     * @param array<int, array<string, mixed>> $routes
     * @param array<int, array<string, mixed>> $blockedRoutes
     * @param array<int, array<string, mixed>> $templates
     * @param array<int, array<string, mixed>> $permissions
     */
    private function isReady(array $routes, array $blockedRoutes, array $templates, array $permissions): bool
    {
        if (!$this->blockedRoutesAreNotActive($routes, $blockedRoutes)) {
            return false;
        }

        foreach ($routes as $route) {
            if (!in_array((string) ($route['status'] ?? ''), $this->allowedStatuses(), true)) {
                return false;
            }
        }

        foreach ($templates as $template) {
            if (($template['exists'] ?? false) !== true) {
                return false;
            }
        }

        foreach ($permissions as $permission) {
            if (($permission['obsolete'] ?? true) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array<string, mixed>> $routes
     * @param array<int, array<string, mixed>> $blockedRoutes
     */
    private function blockedRoutesAreNotActive(array $routes, array $blockedRoutes): bool
    {
        $activePaths = [];
        $activeHandlers = [];
        foreach ($routes as $route) {
            $activePaths[] = (string) ($route['path'] ?? '');
            $activeHandlers[] = (string) ($route['handler'] ?? '');
        }

        foreach ($blockedRoutes as $blockedRoute) {
            if (in_array((string) ($blockedRoute['path'] ?? ''), $activePaths, true)) {
                return false;
            }
            if (in_array((string) ($blockedRoute['handler'] ?? ''), $activeHandlers, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array<string, mixed>> $fileEndpoints
     */
    private function fileEndpointsAreControlled(array $fileEndpoints): bool
    {
        foreach ($fileEndpoints as $endpoint) {
            if (($endpoint['controlled'] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }
}
