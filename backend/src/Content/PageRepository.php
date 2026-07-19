<?php

declare(strict_types=1);

namespace Caramagnols\Content;

use Caramagnols\Database\EditorialDatabase;

final class PageRepository
{
    public const SCHEMA_VERSION = 2;
    public const TYPE_STRUCTURED_PAGE = 'structured_page';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    private PageStoreInterface $readerStore;
    private ?PageStoreInterface $secondaryWriterStore;

    public function __construct(
        string $path,
        StructuredPageRenderer $renderer = new StructuredPageRenderer(),
        ?string $storageMode = null,
        ?EditorialDatabase $database = null
    ) {
        $normalizer = new PagePayloadNormalizer($renderer);
        $jsonStore = new JsonPageStore($path, $normalizer);
        $mode = $this->resolveStorageMode($path, $storageMode);

        $this->secondaryWriterStore = null;

        if ($mode === 'sql') {
            $this->readerStore = new SqlPageStore($database ?? editorial_database(), $normalizer);
            return;
        }

        if ($mode === 'dual-write') {
            $this->readerStore = $jsonStore;
            $this->secondaryWriterStore = new SqlPageStore($database ?? editorial_database(), $normalizer);
            return;
        }

        $this->readerStore = $jsonStore;
    }

    /**
     * @return array{meta: array<string, mixed>, pages: array<int, array<string, mixed>>}
     */
    public function registry(): array
    {
        $registry = $this->readerStore->registry();
        $pages = is_array($registry['pages'] ?? null) ? $registry['pages'] : [];

        foreach ($pages as $index => $page) {
            if (!is_array($page)) {
                continue;
            }

            $pages[$index] = $this->normalizePageRoutes($page);
        }

        $registry['pages'] = $pages;

        return $registry;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_map(
            fn (array $page): array => $this->normalizePageRoutes($page),
            $this->readerStore->all()
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function published(): array
    {
        return array_map(
            fn (array $page): array => $this->normalizePageRoutes($page),
            $this->readerStore->published()
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        $page = $this->readerStore->findBySlug($slug);

        return is_array($page) ? $this->normalizePageRoutes($page) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByRoute(string $route): ?array
    {
        foreach (public_route_variants($route) as $candidate) {
            $page = $this->readerStore->findByRoute($candidate);
            if (is_array($page)) {
                return $this->normalizePageRoutes($page);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPublishedStructuredBySlug(string $slug, string $lang, string $fallbackLang = 'fr'): ?array
    {
        $page = $this->readerStore->findPublishedStructuredBySlug($slug, $lang, $fallbackLang);

        return is_array($page) ? $this->normalizePageRoutes($page) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPublishedStructuredByRoute(string $route, string $lang, string $fallbackLang = 'fr'): ?array
    {
        foreach (public_route_variants($route) as $candidate) {
            $page = $this->readerStore->findPublishedStructuredByRoute($candidate, $lang, $fallbackLang);
            if (is_array($page)) {
                return $this->normalizePageRoutes($page);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $page
     */
    public function savePage(array $page, ?string $originalSlug = null): bool
    {
        if ($this->secondaryWriterStore !== null && !$this->secondaryWriterStore->savePage($page, $originalSlug)) {
            return false;
        }

        return $this->readerStore->savePage($page, $originalSlug);
    }

    public function deletePage(string $slug): bool
    {
        if ($this->secondaryWriterStore !== null && !$this->secondaryWriterStore->deletePage($slug)) {
            return false;
        }

        return $this->readerStore->deletePage($slug);
    }

    public function clearCache(): void
    {
        $this->readerStore->clearCache();

        if ($this->secondaryWriterStore !== null) {
            $this->secondaryWriterStore->clearCache();
        }
    }

    private function resolveStorageMode(string $path, ?string $storageMode): string
    {
        if ($storageMode !== null) {
            return $storageMode;
        }

        $defaultPath = ROOT_PATH . '/data/pages.json';
        if ($path !== $defaultPath) {
            return 'json';
        }

        return editorial_storage_mode();
    }

    /**
     * @param array<string, mixed> $page
     * @return array<string, mixed>
     */
    private function normalizePageRoutes(array $page): array
    {
        if (isset($page['route']) && is_string($page['route'])) {
            $page['route'] = normalize_public_route($page['route']) ?? $page['route'];
        }

        $translations = is_array($page['translations'] ?? null) ? $page['translations'] : [];
        foreach ($translations as $language => $translation) {
            if (!is_string($language) || !is_array($translation)) {
                continue;
            }

            if (isset($translation['route']) && is_string($translation['route'])) {
                $translation['route'] = normalize_public_route($translation['route']) ?? $translation['route'];
            }

            $translations[$language] = $translation;
        }

        if ($translations !== []) {
            $page['translations'] = $translations;
        }

        return $page;
    }
}
