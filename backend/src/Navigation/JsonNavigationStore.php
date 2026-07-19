<?php

declare(strict_types=1);

namespace Caramagnols\Navigation;

final class JsonNavigationStore implements NavigationStoreInterface
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $canonicalCache = null;
    private ?int $canonicalMtime = null;

    public function __construct(private readonly string $path)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function loadCanonical(array $fallbackLegacy = []): array
    {
        $mtime = is_file($this->path) ? (@filemtime($this->path) ?: null) : null;
        if ($this->canonicalCache !== null && $this->canonicalMtime === $mtime) {
            return $this->canonicalCache;
        }

        if (!is_file($this->path)) {
            return $this->rememberCanonical($mtime, NavigationNormalizer::legacyToCanonical($fallbackLegacy));
        }

        if (!is_readable($this->path)) {
            error_log('[menu_loader] Fichier non lisible : ' . $this->path);
            return $this->rememberCanonical($mtime, NavigationNormalizer::legacyToCanonical($fallbackLegacy));
        }

        $raw = file_get_contents($this->path);
        if ($raw === false) {
            error_log('[menu_loader] Impossible de lire le fichier : ' . $this->path);
            return $this->rememberCanonical($mtime, NavigationNormalizer::legacyToCanonical($fallbackLegacy));
        }

        $decoded = json_decode($raw, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            error_log('[menu_loader] JSON invalide dans ' . $this->path . ' : ' . json_last_error_msg());
            return $this->rememberCanonical($mtime, NavigationNormalizer::legacyToCanonical($fallbackLegacy));
        }

        if (!is_array($decoded)) {
            error_log('[menu_loader] Le fichier doit contenir un objet ou un tableau.');
            return $this->rememberCanonical($mtime, NavigationNormalizer::legacyToCanonical($fallbackLegacy));
        }

        if (NavigationNormalizer::looksLikeCanonical($decoded)) {
            return $this->rememberCanonical($mtime, NavigationNormalizer::normalizeCanonical($decoded));
        }

        return $this->rememberCanonical($mtime, NavigationNormalizer::legacyToCanonical($decoded));
    }

    /**
     * @return array<string, mixed>
     */
    public function loadLegacyConfig(array $fallbackLegacy = []): array
    {
        return NavigationNormalizer::canonicalToLegacy($this->loadCanonical($fallbackLegacy));
    }

    public function saveLegacyConfig(array $legacy): bool
    {
        return $this->saveCanonical(NavigationNormalizer::legacyToCanonical($legacy));
    }

    /**
     * @param array<string, mixed> $canonical
     */
    public function saveCanonical(array $canonical): bool
    {
        $normalized = NavigationNormalizer::normalizeCanonical($canonical);
        $dir = dirname($this->path);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }

        $json = json_encode(
            $normalized,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            return false;
        }

        $backupPath = $this->path . '.bak';
        if (file_exists($this->path)) {
            @copy($this->path, $backupPath);
        }

        $written = file_put_contents($this->path, $json);
        if ($written === false) {
            return false;
        }

        $this->rememberCanonical(@filemtime($this->path) ?: null, $normalized);

        return true;
    }

    public function clearCache(): void
    {
        $this->canonicalCache = null;
        $this->canonicalMtime = null;
    }

    /**
     * @return array<string, mixed>
     */
    private function rememberCanonical(?int $mtime, array $canonical): array
    {
        $this->canonicalCache = $canonical;
        $this->canonicalMtime = $mtime;

        return $canonical;
    }
}
