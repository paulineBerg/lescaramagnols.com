<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal;

final class PrivateModuleRegistry
{
    public function __construct(
        private readonly ?PrivateAppPackageDiscovery $packageDiscovery = null
    ) {
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function allModules(): array
    {
        $modules = [
            [
                'code' => 'dashboard',
                'name' => 'Tableau de bord privé',
                'description' => 'Accès au tableau de bord principal de l’espace privé.',
            ],
            [
                'code' => 'documents',
                'name' => 'Documents',
                'description' => 'Accès au centre de stockage privé.',
            ],
            [
                'code' => 'blocnote',
                'name' => 'Bloc-note',
                'description' => 'Notes privées, catégories et suivi personnel.',
            ],
            [
                'code' => 'discussions',
                'name' => 'Discussions',
                'description' => 'Espace d’échanges privé.',
            ],
            [
                'code' => 'real_estate_rental',
                'name' => 'Locations immobilières',
                'description' => 'Gestion privée des biens, lots et membres locatifs.',
            ],
            [
                'code' => 'tax_declaration_helper',
                'name' => 'Aide impôts',
                'description' => 'Aide annuelle non officielle à la préparation des données fiscales privées.',
            ],
        ];

        return $this->mergePrivateAppManifests($modules);
    }

    /**
     * @param array<int, array<string, string>> $baseModules
     * @return array<int, array<string, string>>
     */
    private function mergePrivateAppManifests(array $baseModules): array
    {
        $result = [];
        $seenCodes = [];
        foreach ($baseModules as $module) {
            $code = (string) ($module['code'] ?? '');
            $result[] = $module;
            if ($code !== '') {
                $seenCodes[$code] = true;
            }
        }

        foreach ($this->privateAppManifestClasses() as $manifestClass) {
            $manifest = new $manifestClass();
            if (!$manifest instanceof PrivateAppManifest) {
                continue;
            }

            $code = $manifest->moduleCode();
            if ($code === '' || isset($seenCodes[$code])) {
                continue;
            }

            $result[] = [
                'code' => $code,
                'name' => $manifest->moduleName() !== '' ? $manifest->moduleName() : $code,
                'description' => $manifest->moduleDescription(),
            ];
            $seenCodes[$code] = true;
        }

        return $result;
    }

    /**
     * @return array<int, class-string<PrivateAppManifest>>
     */
    private function privateAppManifestClasses(): array
    {
        $privateAppsPath = dirname(__DIR__) . '/PrivateApps';
        $manifestClasses = [];
        if (is_dir($privateAppsPath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($privateAppsPath, \FilesystemIterator::SKIP_DOTS)
            );
            $sourceRoot = dirname(__DIR__);
            $sourceRootLength = strlen($sourceRoot) + 1;

            foreach ($iterator as $fileinfo) {
                if (!$fileinfo->isFile() || $fileinfo->getExtension() !== 'php') {
                    continue;
                }

                $filename = $fileinfo->getFilename();
                if (!str_ends_with($filename, 'Manifest.php')) {
                    continue;
                }

                $relativePath = str_replace('\\', '/', substr($fileinfo->getPathname(), $sourceRootLength));
                $className = 'Caramagnols\\' . str_replace('/', '\\', substr($relativePath, 0, -4));

                if (!str_starts_with($className, 'Caramagnols\\PrivateApps\\')) {
                    continue;
                }
                if (!class_exists($className) || !is_subclass_of($className, PrivateAppManifest::class)) {
                    continue;
                }

                $manifestClasses[] = $className;
            }
        }

        foreach (($this->packageDiscovery ?? new PrivateAppPackageDiscovery())->manifestClasses() as $manifestClass) {
            if (!class_exists($manifestClass) || !is_subclass_of($manifestClass, PrivateAppManifest::class)) {
                continue;
            }

            $manifestClasses[] = $manifestClass;
        }

        $manifestClasses = array_values(array_unique($manifestClasses));
        sort($manifestClasses);

        return array_values($manifestClasses);
    }

    /**
     * @return array<int, PrivateAppManifest>
     */
    public function privateAppManifests(): array
    {
        $manifests = [];
        foreach ($this->privateAppManifestClasses() as $manifestClass) {
            $manifest = new $manifestClass();
            if ($manifest instanceof PrivateAppManifest) {
                $manifests[] = $manifest;
            }
        }

        usort(
            $manifests,
            static fn (PrivateAppManifest $a, PrivateAppManifest $b): int => $a->order() <=> $b->order()
        );

        return $manifests;
    }

    public function moduleCode(string $code): ?array
    {
        $normalized = strtolower(trim($code));
        if ($normalized === '') {
            return null;
        }

        foreach ($this->allModules() as $module) {
            if (($module['code'] ?? '') === $normalized) {
                return $module;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function moduleCodes(): array
    {
        return array_values(
            array_filter(
                array_map(
                    static fn (array $module): string => is_string($module['code'] ?? null) ? trim((string) $module['code']) : '',
                    $this->allModules()
                ),
                static fn (string $code): bool => $code !== ''
            )
        );
    }
}
