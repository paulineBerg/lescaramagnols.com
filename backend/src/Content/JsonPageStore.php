<?php

declare(strict_types=1);

namespace Caramagnols\Content;

final class JsonPageStore implements PageStoreInterface
{
    /**
     * @var array{meta: array<string, mixed>, pages: array<int, array<string, mixed>>}|null
     */
    private ?array $registryCache = null;
    private ?int $registryMtime = null;

    public function __construct(
        private readonly string $path,
        private readonly PagePayloadNormalizer $normalizer = new PagePayloadNormalizer()
    ) {
    }

    /**
     * @return array{meta: array<string, mixed>, pages: array<int, array<string, mixed>>}
     */
    public function registry(): array
    {
        $mtime = is_file($this->path) ? (@filemtime($this->path) ?: null) : null;
        if ($this->registryCache !== null && $this->registryMtime === $mtime) {
            return $this->registryCache;
        }

        if (!is_file($this->path)) {
            return $this->rememberRegistry($mtime, $this->normalizer->emptyRegistry());
        }

        if (!is_readable($this->path)) {
            error_log('[pages_loader] Fichier non lisible : ' . $this->path);
            return $this->rememberRegistry($mtime, $this->normalizer->emptyRegistry());
        }

        $raw = file_get_contents($this->path);
        if ($raw === false) {
            error_log('[pages_loader] Impossible de lire le fichier : ' . $this->path);
            return $this->rememberRegistry($mtime, $this->normalizer->emptyRegistry());
        }

        $decoded = json_decode($raw, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            error_log('[pages_loader] JSON invalide dans ' . $this->path . ' : ' . json_last_error_msg());
            return $this->rememberRegistry($mtime, $this->normalizer->emptyRegistry());
        }

        if (!is_array($decoded)) {
            error_log('[pages_loader] Le fichier doit contenir un objet ou un tableau.');
            return $this->rememberRegistry($mtime, $this->normalizer->emptyRegistry());
        }

        $pages = $decoded['pages'] ?? $decoded;
        if (!is_array($pages)) {
            error_log('[pages_loader] La clé "pages" doit être un tableau.');
            return $this->rememberRegistry($mtime, $this->normalizer->emptyRegistry());
        }

        $normalizedPages = [];

        foreach ($pages as $page) {
            if (!is_array($page)) {
                continue;
            }

            $normalized = $this->normalizer->normalizePage($page);
            if ($normalized !== null) {
                $normalizedPages[] = $normalized;
            }
        }

        return $this->rememberRegistry(
            $mtime,
            [
                'meta' => ['version' => PageRepository::SCHEMA_VERSION],
                'pages' => $normalizedPages,
            ]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->registry()['pages'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function published(): array
    {
        return array_values(
            array_filter(
                $this->all(),
                static fn (array $page): bool => ($page['status'] ?? PageRepository::STATUS_DRAFT) === PageRepository::STATUS_PUBLISHED
            )
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        foreach ($this->all() as $page) {
            if (($page['slug'] ?? '') === $slug) {
                return $page;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByRoute(string $route): ?array
    {
        $normalizedRoute = '/' . ltrim(trim($route), '/');
        foreach ($this->all() as $page) {
            if (($page['route'] ?? null) === $normalizedRoute) {
                return $page;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPublishedStructuredBySlug(string $slug, string $lang, string $fallbackLang = 'fr'): ?array
    {
        $page = $this->findBySlug($slug);
        if ($page === null) {
            return null;
        }

        return $this->normalizer->buildRenderableStructuredPage($page, $lang, $fallbackLang);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPublishedStructuredByRoute(string $route, string $lang, string $fallbackLang = 'fr'): ?array
    {
        $page = $this->findByRoute($route);
        if ($page === null) {
            return null;
        }

        return $this->normalizer->buildRenderableStructuredPage($page, $lang, $fallbackLang);
    }

    /**
     * @param array<string, mixed> $page
     */
    public function savePage(array $page, ?string $originalSlug = null): bool
    {
        $normalized = $this->normalizer->normalizePage($page);
        if ($normalized === null) {
            return false;
        }

        $registry = $this->registry();
        $pages = $registry['pages'];
        $lookupSlug = trim((string) ($originalSlug ?? $normalized['slug']));
        $existingIndex = null;

        foreach ($pages as $index => $existingPage) {
            if (($existingPage['slug'] ?? '') === $lookupSlug) {
                $existingIndex = $index;
                break;
            }
        }

        foreach ($pages as $index => $existingPage) {
            if ($existingIndex !== null && $index === $existingIndex) {
                continue;
            }

            if (($existingPage['slug'] ?? '') === $normalized['slug']) {
                return false;
            }
        }

        if ($existingIndex === null) {
            $pages[] = $normalized;
        } else {
            $pages[$existingIndex] = $normalized;
        }

        return $this->saveRegistry([
            'meta' => ['version' => PageRepository::SCHEMA_VERSION],
            'pages' => array_values($pages),
        ]);
    }

    public function deletePage(string $slug): bool
    {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }

        $registry = $this->registry();
        $pages = $registry['pages'];
        $initialCount = count($pages);
        $pages = array_values(array_filter(
            $pages,
            static fn (array $page): bool => (string) ($page['slug'] ?? '') !== $slug
        ));

        if (count($pages) === $initialCount) {
            return false;
        }

        return $this->saveRegistry([
            'meta' => ['version' => PageRepository::SCHEMA_VERSION],
            'pages' => $pages,
        ]);
    }

    public function clearCache(): void
    {
        $this->registryCache = null;
        $this->registryMtime = null;
    }

    /**
     * @param array{meta: array<string, mixed>, pages: array<int, array<string, mixed>>} $registry
     */
    private function saveRegistry(array $registry): bool
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return false;
        }

        $json = json_encode(
            $registry,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            return false;
        }

        $backupPath = $this->path . '.bak';
        if (is_file($this->path)) {
            @copy($this->path, $backupPath);
        }

        $written = file_put_contents($this->path, $json);
        if ($written === false) {
            return false;
        }

        $this->rememberRegistry(@filemtime($this->path) ?: null, $registry);

        return true;
    }

    /**
     * @param array{meta: array<string, mixed>, pages: array<int, array<string, mixed>>} $registry
     * @return array{meta: array<string, mixed>, pages: array<int, array<string, mixed>>}
     */
    private function rememberRegistry(?int $mtime, array $registry): array
    {
        $this->registryMtime = $mtime;
        $this->registryCache = $registry;

        return $registry;
    }
}
