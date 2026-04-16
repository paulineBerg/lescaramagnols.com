<?php

declare(strict_types=1);

namespace Caramagnols\Assets;

final class ViteAssetManager
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $manifestCache = null;
    private ?int $manifestMtime = null;
    private ?bool $devServerReachable = null;

    public function __construct(
        private readonly string $manifestPath,
        private readonly string $devServerUrl = 'http://localhost:5173',
        private readonly bool $devMode = false
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function loadManifest(): ?array
    {
        if (!is_file($this->manifestPath)) {
            return null;
        }

        $mtime = @filemtime($this->manifestPath) ?: null;
        if ($this->manifestCache !== null && $this->manifestMtime === $mtime) {
            return $this->manifestCache;
        }

        $json = file_get_contents($this->manifestPath);
        $decoded = is_string($json) ? json_decode($json, true) : null;
        $this->manifestCache = is_array($decoded) ? $decoded : [];
        $this->manifestMtime = $mtime;

        return $this->manifestCache;
    }

    public function devServerUrl(): string
    {
        return rtrim($this->devServerUrl, '/');
    }

    public function devServerReachable(): bool
    {
        if ($this->devServerReachable !== null) {
            return $this->devServerReachable;
        }

        if (!$this->devMode) {
            $this->devServerReachable = false;
            return false;
        }

        $headers = @get_headers($this->devServerUrl() . '/@vite/client', true);
        if (!is_array($headers)) {
            $this->devServerReachable = false;
            return false;
        }

        $statusLine = $headers[0] ?? '';
        $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? '';

        if (is_array($contentType)) {
            $contentType = $contentType[0] ?? '';
        }

        $this->devServerReachable = is_string($statusLine)
            && str_contains($statusLine, '200')
            && is_string($contentType)
            && str_contains(strtolower($contentType), 'javascript');

        return $this->devServerReachable;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function entry(string $entry): ?array
    {
        $manifest = $this->loadManifest();
        if ($manifest === null) {
            return null;
        }

        $candidates = [$entry];

        if (str_ends_with($entry, '.ts')) {
            $candidates[] = substr($entry, 0, -3) . '.js';
        }

        if (str_ends_with($entry, '.tsx')) {
            $candidates[] = substr($entry, 0, -4) . '.js';
        }

        foreach ($candidates as $candidate) {
            $manifestEntry = $manifest[$candidate] ?? null;
            if (is_array($manifestEntry)) {
                return $manifestEntry;
            }
        }

        return null;
    }

    public function tags(string $entry = 'src/js/main.ts', ?string $nonce = null): string
    {
        $nonceAttr = '';
        if (is_string($nonce) && $nonce !== '') {
            $nonceAttr = sprintf(' nonce="%s"', htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8'));
        }

        if ($this->devServerReachable()) {
            $baseUrl = htmlspecialchars($this->devServerUrl(), ENT_QUOTES, 'UTF-8');

            return sprintf(
                '<script type="module"%s src="%s/@vite/client"></script>%s<script type="module"%s src="%s/%s"></script>%s',
                $nonceAttr,
                $baseUrl,
                PHP_EOL,
                $nonceAttr,
                $baseUrl,
                ltrim($entry, '/'),
                PHP_EOL
            );
        }

        $manifestEntry = $this->entry($entry);
        if ($manifestEntry === null) {
            return '<!-- ⚠️ Entrée Vite introuvable ou manifest indisponible -->' . PHP_EOL;
        }

        $tags = '';

        foreach ($this->cssUrls($entry) as $cssUrl) {
            $tags .= sprintf(
                '<link rel="stylesheet" href="%s">%s',
                htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8'),
                PHP_EOL
            );
        }

        $file = $manifestEntry['file'] ?? null;
        if (is_string($file) && $file !== '') {
            $tags .= sprintf(
                '<script type="module"%s src="%s"></script>%s',
                $nonceAttr,
                htmlspecialchars($this->publishedAssetUrl($file), ENT_QUOTES, 'UTF-8'),
                PHP_EOL
            );
        }

        if ($tags === '') {
            return '<!-- ⚠️ Aucun asset exploitable trouvé pour l’entrée Vite -->' . PHP_EOL;
        }

        return $tags;
    }

    public function assetUrl(string $entry): string
    {
        $manifestEntry = $this->entry($entry);
        $file = $manifestEntry['file'] ?? null;

        if (!is_string($file) || $file === '') {
            return '/assets/' . ltrim($entry, '/');
        }

        return $this->publishedAssetUrl($file);
    }

    /**
     * @return array<int, string>
     */
    public function cssUrls(string $entry): array
    {
        $manifestEntry = $this->entry($entry);
        if ($manifestEntry === null) {
            return [];
        }

        $cssFiles = $manifestEntry['css'] ?? [];
        if (!is_array($cssFiles)) {
            return [];
        }

        $urls = [];

        foreach ($cssFiles as $cssFile) {
            if (!is_string($cssFile) || $cssFile === '') {
                continue;
            }

            $urls[] = $this->publishedAssetUrl($cssFile);
        }

        return $urls;
    }

    private function publishedAssetUrl(string $manifestFile): string
    {
        $normalized = ltrim($manifestFile, '/');

        if (str_starts_with($normalized, 'assets/')) {
            $normalized = substr($normalized, strlen('assets/'));
        }

        return '/assets/' . $normalized;
    }
}
