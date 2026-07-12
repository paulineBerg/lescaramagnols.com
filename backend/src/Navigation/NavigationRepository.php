<?php

declare(strict_types=1);

namespace Caramagnols\Navigation;

use Caramagnols\Database\EditorialDatabase;

final class NavigationRepository
{
    public const SCHEMA_VERSION = 2;
    private const SNAPSHOT_RETENTION = 20;

    private NavigationStoreInterface $readerStore;
    private ?NavigationStoreInterface $secondaryWriterStore;
    private readonly string $snapshotDir;
    private readonly string $snapshotBaseName;

    public function __construct(
        string $path,
        ?string $storageMode = null,
        ?EditorialDatabase $database = null
    ) {
        $this->snapshotDir = dirname($path) . '/snapshots';
        $baseName = pathinfo($path, PATHINFO_FILENAME);
        $baseName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $baseName);
        $this->snapshotBaseName = is_string($baseName) && $baseName !== '' ? $baseName : 'menus';

        $jsonStore = new JsonNavigationStore($path);
        $mode = $this->resolveStorageMode($path, $storageMode);

        $this->secondaryWriterStore = null;

        if ($mode === 'sql') {
            $this->readerStore = new SqlNavigationStore($database ?? editorial_database());
            return;
        }

        if ($mode === 'dual-write') {
            $this->readerStore = $jsonStore;
            $this->secondaryWriterStore = new SqlNavigationStore($database ?? editorial_database());
            return;
        }

        $this->readerStore = $jsonStore;
    }

    /**
     * @return array<string, mixed>
     */
    public function loadCanonical(array $fallbackLegacy = []): array
    {
        return $this->normalizeCanonicalRoutes($this->readerStore->loadCanonical($fallbackLegacy));
    }

    /**
     * @return array<string, mixed>
     */
    public function loadLegacyConfig(array $fallbackLegacy = []): array
    {
        return self::canonicalToLegacy($this->loadCanonical($fallbackLegacy));
    }

    public function saveLegacyConfig(array $legacy): bool
    {
        $legacy = self::canonicalToLegacy(self::legacyToCanonical($legacy));

        if (!$this->persistSnapshotOfCurrentState()) {
            error_log('[navigation_repository] Snapshot impossible, poursuite de la sauvegarde sans snapshot.');
        }

        if ($this->secondaryWriterStore !== null && !$this->secondaryWriterStore->saveLegacyConfig($legacy)) {
            return false;
        }

        return $this->readerStore->saveLegacyConfig($legacy);
    }

    /**
     * @param array<string, mixed> $canonical
     */
    public function saveCanonical(array $canonical): bool
    {
        $canonical = $this->normalizeCanonicalRoutes($canonical);

        if (!$this->persistSnapshotOfCurrentState()) {
            error_log('[navigation_repository] Snapshot impossible, poursuite de la sauvegarde sans snapshot.');
        }

        if ($this->secondaryWriterStore !== null && !$this->secondaryWriterStore->saveCanonical($canonical)) {
            return false;
        }

        return $this->readerStore->saveCanonical($canonical);
    }

    public function clearCache(): void
    {
        $this->readerStore->clearCache();

        if ($this->secondaryWriterStore !== null) {
            $this->secondaryWriterStore->clearCache();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalizeLegacyConfig(array $menus): array
    {
        return NavigationNormalizer::normalizeLegacyConfig($menus);
    }

    /**
     * @return array<string, mixed>
     */
    public static function legacyToCanonical(array $legacy): array
    {
        return NavigationNormalizer::legacyToCanonical($legacy);
    }

    /**
     * @param array<string, mixed> $canonical
     * @return array<string, mixed>
     */
    public static function canonicalToLegacy(array $canonical): array
    {
        return NavigationNormalizer::canonicalToLegacy($canonical);
    }

    private function resolveStorageMode(string $path, ?string $storageMode): string
    {
        if ($storageMode !== null) {
            return $storageMode;
        }

        $defaultPath = ROOT_PATH . '/data/menus.json';
        if ($path !== $defaultPath) {
            return 'json';
        }

        return editorial_storage_mode();
    }

    /**
     * @param array<string, mixed> $canonical
     * @return array<string, mixed>
     */
    private function normalizeCanonicalRoutes(array $canonical): array
    {
        $locations = is_array($canonical['locations'] ?? null) ? $canonical['locations'] : [];

        foreach (['utility', 'primary', 'footer', 'sideLeft', 'sideRight'] as $locationKey) {
            $items = is_array($locations[$locationKey] ?? null) ? $locations[$locationKey] : [];
            $locations[$locationKey] = $this->normalizeCanonicalItems($items);
        }

        $canonical['locations'] = $locations;

        return $canonical;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeCanonicalItems(array $items): array
    {
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $target = is_array($item['target'] ?? null) ? $item['target'] : [];
            if (isset($target['route']) && is_string($target['route'])) {
                $target['route'] = normalize_public_route($target['route']) ?? $target['route'];
            }
            $item['target'] = $target;

            $presentation = is_array($item['presentation'] ?? null) ? $item['presentation'] : [];
            $featuredCard = is_array($presentation['featuredCard'] ?? null) ? $presentation['featuredCard'] : [];
            $featuredTarget = is_array($featuredCard['target'] ?? null) ? $featuredCard['target'] : [];
            if (isset($featuredTarget['route']) && is_string($featuredTarget['route'])) {
                $featuredTarget['route'] = normalize_public_route($featuredTarget['route']) ?? $featuredTarget['route'];
            }
            if ($featuredTarget !== []) {
                $featuredCard['target'] = $featuredTarget;
                $presentation['featuredCard'] = $featuredCard;
                $item['presentation'] = $presentation;
            }

            $children = is_array($item['children'] ?? null) ? $item['children'] : [];
            if ($children !== []) {
                $item['children'] = $this->normalizeCanonicalItems($children);
            }

            $items[$index] = $item;
        }

        return $items;
    }

    private function persistSnapshotOfCurrentState(): bool
    {
        $currentCanonical = $this->loadCanonical();
        if (!$this->hasMeaningfulContent($currentCanonical['locations'] ?? null)) {
            return true;
        }

        if (!is_dir($this->snapshotDir) && !mkdir($this->snapshotDir, 0755, true) && !is_dir($this->snapshotDir)) {
            return false;
        }

        $json = json_encode(
            $currentCanonical,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (!is_string($json)) {
            return false;
        }

        $written = file_put_contents($this->nextSnapshotPath(), $json);
        if ($written === false) {
            return false;
        }

        $this->pruneSnapshots();

        return true;
    }

    private function nextSnapshotPath(): string
    {
        try {
            $suffix = bin2hex(random_bytes(4));
        } catch (\Throwable) {
            $suffix = uniqid('', true);
        }

        return sprintf(
            '%s/%s-%s-%s.json',
            $this->snapshotDir,
            $this->snapshotBaseName,
            date('Ymd-His'),
            $suffix
        );
    }

    private function pruneSnapshots(): void
    {
        $pattern = sprintf('%s/%s-*.json', $this->snapshotDir, $this->snapshotBaseName);
        $snapshots = glob($pattern) ?: [];
        rsort($snapshots, SORT_STRING);

        foreach (array_slice($snapshots, self::SNAPSHOT_RETENTION) as $snapshotPath) {
            @unlink($snapshotPath);
        }
    }

    private function hasMeaningfulContent(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $child) {
                if ($this->hasMeaningfulContent($child)) {
                    return true;
                }
            }

            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return true;
        }

        return false;
    }
}
