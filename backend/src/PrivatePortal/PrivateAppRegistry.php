<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal;

/**
 * Registre statique explicite des modules PrivateApps.
 * 
 * Principe : ajouter un module = 1 ligne dans le tableau MANIFEST_CLASSES.
 * Pas d'auto-découverte, pas de chargement dynamique, pas de système de hooks.
 * La modularité sert la lisibilité, l'analyse statique et la testabilité.
 */
final class PrivateAppRegistry
{
    /**
     * Liste explicite des classes de manifestes des modules PrivateApps.
     * L'ordre détermine l'ordre d'affichage dans le dashboard.
     *
     * @var array<int, class-string<PrivateAppManifest>>
     */
    private const MANIFEST_CLASSES = [
        \Caramagnols\PrivateApps\BlocNote\PrivateAppManifest::class,
        \Caramagnols\PrivateApps\Documents\PrivateAppManifest::class,
        \Caramagnols\PrivateApps\FamilyDiscussion\PrivateAppManifest::class,
        \Caramagnols\PrivateApps\RealEstateRental\PrivateAppManifest::class,
        \Caramagnols\PrivateApps\TaxDeclarationHelper\PrivateAppManifest::class,
    ];

    /**
     * Routes du socle (ne doivent pas être déclarées dans les modules).
     *
     * @var array<int, string>
     */
    private const CORE_ROUTES = [
        'login',
        'dashboard',
        'logout',
        'activate',
        'password',
        'parametres',
    ];

    /**
     * Tables du socle (ne doivent pas être déclarées dans les modules).
     *
     * @var array<int, string>
     */
    private const CORE_TABLES = [
        'private_users',
        'private_user_sessions',
        'private_user_permissions',
        'private_module_permissions',
    ];

    /**
     * @var array<string, PrivateAppManifest>|null
     */
    private static ?array $instances = null;

    /**
     * @var array<int, array{class: class-string<PrivateAppManifest>, instance: PrivateAppManifest}>|null
     */
    private static ?array $orderedManifests = null;

    /**
     * Retourne tous les manifestes instanciés, indexés par leur moduleCode.
     *
     * @return array<string, PrivateAppManifest>
     */
    public static function all(): array
    {
        if (self::$instances !== null) {
            return self::$instances;
        }

        self::$instances = [];
        foreach (self::MANIFEST_CLASSES as $manifestClass) {
            if (!class_exists($manifestClass)) {
                throw new \RuntimeException("La classe de manifeste {$manifestClass} n'existe pas.");
            }

            $manifest = new $manifestClass();
            if (!$manifest instanceof PrivateAppManifest) {
                throw new \RuntimeException("La classe {$manifestClass} n'implémente pas PrivateAppManifest.");
            }

            $moduleCode = $manifest->moduleCode();
            if (isset(self::$instances[$moduleCode])) {
                throw new \RuntimeException("Code de module dupliqué : {$moduleCode} (déjà déclaré par " . get_class(self::$instances[$moduleCode]) . ").");
            }

            self::$instances[$moduleCode] = $manifest;
        }

        // Validation des collisions
        self::validateNoCollisions();

        return self::$instances;
    }

    /**
     * Retourne les manifestes triés par ordre de dashboard.
     *
     * @return array<int, array{class: class-string<PrivateAppManifest>, instance: PrivateAppManifest}>
     */
    public static function ordered(): array
    {
        if (self::$orderedManifests !== null) {
            return self::$orderedManifests;
        }

        $manifests = [];
        foreach (self::MANIFEST_CLASSES as $manifestClass) {
            $manifest = self::all()[$manifestClass::moduleCode()] ?? null;
            if ($manifest === null) {
                // Ne devrait jamais arriver si all() est appelé avant
                $manifest = new $manifestClass();
            }

            $manifests[] = [
                'class' => $manifestClass,
                'instance' => $manifest,
            ];
        }

        // Tri par ordre
        usort($manifests, static function (array $a, array $b): int {
            return $a['instance']->order() <=> $b['instance']->order();
        });

        self::$orderedManifests = array_values($manifests);

        return self::$orderedManifests;
    }

    /**
     * Retourne un manifeste par son code de module.
     */
    public static function get(string $moduleCode): ?PrivateAppManifest
    {
        return self::all()[$moduleCode] ?? null;
    }

    /**
     * Retourne tous les noms de routes déclarées par les modules.
     *
     * @return array<int, string>
     */
    public static function allRouteNames(): array
    {
        $routes = [];
        foreach (self::all() as $manifest) {
            $routes = array_merge($routes, $manifest->routeNames());
        }

        return array_values(array_unique($routes));
    }

    /**
     * Retourne tous les chemins de routes (nom -> chemin).
     *
     * @return array<string, string>
     */
    public static function allRoutePaths(): array
    {
        $paths = [];
        foreach (self::all() as $manifest) {
            foreach ($manifest->routePaths() as $routeName => $path) {
                if (isset($paths[$routeName])) {
                    throw new \RuntimeException("Route dupliquée dans les manifestes : {$routeName} (déjà déclarée par " . get_class(self::getByRouteName($routeName)) . ").");
                }
                $paths[$routeName] = $path;
            }
        }

        return $paths;
    }

    /**
     * Retourne toutes les tables déclarées par les modules.
     *
     * @return array<int, string>
     */
    public static function allModuleTables(): array
    {
        $tables = [];
        foreach (self::all() as $manifest) {
            $tables = array_merge($tables, $manifest->tables());
        }

        return array_values(array_unique($tables));
    }

    /**
     * Retourne toutes les tables (socle + modules).
     *
     * @return array<int, string>
     */
    public static function allTables(): array
    {
        return array_values(array_unique(array_merge(
            self::CORE_TABLES,
            self::allModuleTables()
        )));
    }

    /**
     * Retourne tous les codes de permission de module.
     *
     * @return array<int, string>
     */
    public static function allPermissionCodes(): array
    {
        $codes = [];
        foreach (self::all() as $manifest) {
            $codes[] = $manifest->modulePermissionCode();
        }

        return array_values(array_unique($codes));
    }

    /**
     * Retourne les données de tuiles dashboard pour tous les modules.
     *
     * @return array<int, array{label: string, description: string, stat_code: string, module_code: string}>
     */
    public static function allDashboardTileData(): array
    {
        $tiles = [];
        foreach (self::ordered() as $item) {
            $manifest = $item['instance'];
            $tileData = $manifest->dashboardTileData();
            
            $tiles[] = [
                'label' => $tileData['label'],
                'description' => $tileData['description'],
                'stat_code' => $tileData['stat_code'],
                'module_code' => $manifest->moduleCode(),
            ];
        }

        return $tiles;
    }

    /**
     * Retourne le manifeste qui déclare une route donnée.
     */
    public static function getByRouteName(string $routeName): ?PrivateAppManifest
    {
        foreach (self::all() as $manifest) {
            if (in_array($routeName, $manifest->routeNames(), true)) {
                return $manifest;
            }
        }

        return null;
    }

    /**
     * Retourne le manifeste qui déclare une table donnée.
     */
    public static function getByTableName(string $tableName): ?PrivateAppManifest
    {
        foreach (self::all() as $manifest) {
            if (in_array($tableName, $manifest->tables(), true)) {
                return $manifest;
            }
        }

        return null;
    }

    /**
     * Vérifie qu'il n'y a pas de collision entre modules.
     */
    private static function validateNoCollisions(): void
    {
        $routeNames = [];
        $tables = [];
        $permissionCodes = [];
        $migrationCodes = [];
        $moduleCodes = [];

        foreach (self::all() as $manifest) {
            // Vérification des codes de module
            $moduleCode = $manifest->moduleCode();
            if (in_array($moduleCode, $moduleCodes, true)) {
                throw new \RuntimeException("Code de module dupliqué : {$moduleCode}");
            }
            $moduleCodes[] = $moduleCode;

            // Vérification des codes de migration
            $migrationCode = $manifest->migrationCode();
            if (in_array($migrationCode, $migrationCodes, true)) {
                throw new \RuntimeException("Code de migration dupliqué : {$migrationCode}");
            }
            $migrationCodes[] = $migrationCode;

            // Vérification des codes de permission
            $permissionCode = $manifest->modulePermissionCode();
            if (in_array($permissionCode, $permissionCodes, true)) {
                throw new \RuntimeException("Code de permission dupliqué : {$permissionCode}");
            }
            $permissionCodes[] = $permissionCode;

            // Vérification des noms de routes
            foreach ($manifest->routeNames() as $routeName) {
                if (in_array($routeName, $routeNames, true)) {
                    throw new \RuntimeException("Nom de route dupliqué : {$routeName}");
                }
                $routeNames[] = $routeName;
            }

            // Vérification des noms de tables
            foreach ($manifest->tables() as $table) {
                if (in_array($table, $tables, true)) {
                    throw new \RuntimeException("Nom de table dupliqué : {$table}");
                }
                $tables[] = $table;
            }

            // Vérification des chemins de routes
            $paths = $manifest->routePaths();
            foreach ($paths as $path) {
                if (in_array($path, array_values($paths), true) && count(array_keys($paths, $path)) > 1) {
                    throw new \RuntimeException("Chemin de route dupliqué : {$path}");
                }
            }
        }

        // Vérification que les modules ne déclarent pas de routes du socle
        foreach (self::CORE_ROUTES as $coreRoute) {
            foreach (self::all() as $manifest) {
                if (in_array($coreRoute, $manifest->routeNames(), true)) {
                    throw new \RuntimeException("La route socle '{$coreRoute}' est déclarée par le module " . get_class($manifest));
                }
            }
        }

        // Vérification que les modules ne déclarent pas de tables du socle
        foreach (self::CORE_TABLES as $coreTable) {
            foreach (self::all() as $manifest) {
                if (in_array($coreTable, $manifest->tables(), true)) {
                    throw new \RuntimeException("La table socle '{$coreTable}' est déclarée par le module " . get_class($manifest));
                }
            }
        }
    }

    /**
     * Réinitialise le cache des instances (utile pour les tests).
     */
    public static function reset(): void
    {
        self::$instances = null;
        self::$orderedManifests = null;
    }
}
