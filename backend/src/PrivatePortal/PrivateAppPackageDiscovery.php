<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal;

final class PrivateAppPackageDiscovery
{
    private const PACKAGE_TYPE = 'caramagnols-private-app';
    private const EXTRA_KEY = 'caramagnols-private-app';

    public function __construct(
        private readonly ?string $installedPackagesPath = null
    ) {
    }

    /**
     * @return array<int, class-string<PrivateAppManifest>>
     */
    public function manifestClasses(): array
    {
        $packages = $this->installedPackages();
        $manifestClasses = [];

        foreach ($packages as $package) {
            if (($package['type'] ?? null) !== self::PACKAGE_TYPE) {
                continue;
            }

            $extra = is_array($package['extra'] ?? null) ? $package['extra'] : [];
            $metadata = is_array($extra[self::EXTRA_KEY] ?? null) ? $extra[self::EXTRA_KEY] : [];
            $manifests = is_array($metadata['manifests'] ?? null) ? $metadata['manifests'] : [];

            foreach ($manifests as $manifestClass) {
                if (!is_string($manifestClass) || !$this->isClassName($manifestClass)) {
                    continue;
                }

                /** @var class-string<PrivateAppManifest> $manifestClass */
                $manifestClasses[] = $manifestClass;
            }
        }

        $manifestClasses = array_values(array_unique($manifestClasses));
        sort($manifestClasses);

        return $manifestClasses;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function installedPackages(): array
    {
        $path = $this->installedPackagesPath
            ?? dirname(__DIR__, 2) . '/vendor/composer/installed.json';
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if (!is_string($contents) || trim($contents) === '') {
            return [];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $packages = array_is_list($decoded) ? $decoded : ($decoded['packages'] ?? []);
        if (!is_array($packages)) {
            return [];
        }

        return array_values(array_filter($packages, 'is_array'));
    }

    private function isClassName(string $value): bool
    {
        return preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*\\\\)+[A-Za-z_][A-Za-z0-9_]*$/D', $value) === 1;
    }
}
