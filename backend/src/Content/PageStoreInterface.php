<?php

declare(strict_types=1);

namespace Caramagnols\Content;

interface PageStoreInterface
{
    /**
     * @return array{meta: array<string, mixed>, pages: array<int, array<string, mixed>>}
     */
    public function registry(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function published(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findByRoute(string $route): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findPublishedStructuredBySlug(string $slug, string $lang, string $fallbackLang = 'fr'): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findPublishedStructuredByRoute(string $route, string $lang, string $fallbackLang = 'fr'): ?array;

    /**
     * @param array<string, mixed> $page
     */
    public function savePage(array $page, ?string $originalSlug = null): bool;

    public function deletePage(string $slug): bool;

    public function clearCache(): void;
}
