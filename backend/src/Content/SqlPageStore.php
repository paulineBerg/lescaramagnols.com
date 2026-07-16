<?php

declare(strict_types=1);

namespace Caramagnols\Content;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class SqlPageStore implements PageStoreInterface
{
    /**
     * @var array{meta: array<string, mixed>, pages: array<int, array<string, mixed>>}|null
     */
    private ?array $registryCache = null;

    public function __construct(
        private readonly EditorialDatabase $database,
        private readonly PagePayloadNormalizer $normalizer = new PagePayloadNormalizer()
    ) {
    }

    /**
     * @return array{meta: array<string, mixed>, pages: array<int, array<string, mixed>>}
     */
    public function registry(): array
    {
        if ($this->registryCache !== null) {
            return $this->registryCache;
        }

        try {
            $this->database->ensureReady();
            $pdo = $this->database->pdo();

            $pages = $pdo->query(
                sprintf(
                    'SELECT * FROM `%s` ORDER BY `sort_order` ASC, `id` ASC',
                    $this->database->table('pages')
                )
            )->fetchAll();

            if (!is_array($pages) || $pages === []) {
                return $this->rememberRegistry($this->normalizer->emptyRegistry());
            }

            $pageIds = array_values(
                array_map(static fn (array $row): int => (int) $row['id'], $pages)
            );
            $pageSections = $this->loadPageSections($pdo, $pageIds);
            $translations = $this->loadTranslations($pdo, $pageIds);

            $normalizedPages = [];

            foreach ($pages as $row) {
                $pageId = (int) $row['id'];
                $rawPage = [
                    'slug' => (string) $row['slug'],
                    'type' => (string) $row['type'],
                    'status' => (string) $row['status'],
                    'title' => $row['title'] !== null ? (string) $row['title'] : null,
                    'layout' => (string) $row['layout'],
                    'route' => $row['route'] !== null ? (string) $row['route'] : null,
                    'blocks' => $pageSections[$pageId]['blocks'] ?? [],
                    'regions' => $pageSections[$pageId]['regions'] ?? [],
                    'translations' => $translations[$pageId] ?? [],
                    'meta' => $this->decodeJson($row['meta_json'], []),
                ];

                $normalized = $this->normalizer->normalizePage($rawPage);
                if ($normalized !== null) {
                    $normalizedPages[] = $normalized;
                }
            }

            return $this->rememberRegistry([
                'meta' => ['version' => PageRepository::SCHEMA_VERSION],
                'pages' => $normalizedPages,
            ]);
        } catch (\Throwable $exception) {
            error_log('[pages_sql] ' . $exception->getMessage());

            return $this->rememberRegistry($this->normalizer->emptyRegistry());
        }
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

        try {
            $this->database->ensureReady();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();

            $lookupSlug = trim((string) ($originalSlug ?? $normalized['slug']));
            $existing = $this->pageRowBySlug($pdo, $lookupSlug);
            $existingId = is_array($existing) ? (int) $existing['id'] : null;

            if ($this->slugExists($pdo, (string) $normalized['slug'], $existingId)) {
                $pdo->rollBack();
                return false;
            }

            $route = $normalized['route'] ?? null;
            if (is_string($route) && $route !== '' && $this->routeExists($pdo, $route, $existingId)) {
                $pdo->rollBack();
                return false;
            }

            $pageId = $existingId ?? $this->insertPageRow($pdo, $normalized);
            if ($existingId !== null) {
                $this->updatePageRow($pdo, $pageId, $normalized, (int) ($existing['sort_order'] ?? 0));
            }

            $this->replacePageSections($pdo, $pageId, $normalized);
            $this->replaceTranslations($pdo, $pageId, $normalized);

            $pdo->commit();
            $this->clearCache();

            return true;
        } catch (\Throwable $exception) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('[pages_sql] ' . $exception->getMessage());

            return false;
        }
    }

    public function deletePage(string $slug): bool
    {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }

        try {
            $this->database->ensureReady();
            $pdo = $this->database->pdo();
            $existing = $this->pageRowBySlug($pdo, $slug);

            if (!is_array($existing)) {
                return false;
            }

            $pdo->beginTransaction();
            $delete = $pdo->prepare(
                sprintf('DELETE FROM `%s` WHERE `id` = :id', $this->database->table('pages'))
            );
            $delete->execute(['id' => (int) $existing['id']]);
            $pdo->commit();
            $this->clearCache();

            return true;
        } catch (\Throwable $exception) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('[pages_sql] ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * @param array{meta?: array<string, mixed>, pages?: array<int, array<string, mixed>>}|array<int, array<string, mixed>> $registry
     */
    public function replaceRegistry(array $registry): bool
    {
        $pages = $registry['pages'] ?? $registry;
        if (!is_array($pages)) {
            return false;
        }

        try {
            $this->database->ensureReady();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();

            $pdo->exec(sprintf('DELETE FROM `%s`', $this->database->table('pages')));

            $sortOrder = 0;
            foreach ($pages as $page) {
                if (!is_array($page)) {
                    continue;
                }

                $normalized = $this->normalizer->normalizePage($page);
                if ($normalized === null) {
                    throw new \RuntimeException('Page invalide pendant l’import SQL.');
                }

                $pageId = $this->insertPageRow($pdo, $normalized, $sortOrder);
                $this->replacePageSections($pdo, $pageId, $normalized);
                $this->replaceTranslations($pdo, $pageId, $normalized);
                $sortOrder++;
            }

            $pdo->commit();
            $this->clearCache();

            return true;
        } catch (\Throwable $exception) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('[pages_sql] ' . $exception->getMessage());

            return false;
        }
    }

    public function clearCache(): void
    {
        $this->registryCache = null;
    }

    /**
     * @param array<int, int> $pageIds
     * @return array<int, array{blocks: array<string, mixed>, regions: array<string, mixed>}>
     */
    private function loadPageSections(PDO $pdo, array $pageIds): array
    {
        if ($pageIds === []) {
            return [];
        }

        $statement = $pdo->prepare(
            sprintf(
                'SELECT `page_id`, `section_group`, `section_key`, `payload_json`
                 FROM `%s`
                 WHERE `page_id` IN (%s)
                 ORDER BY `page_id` ASC, `section_group` ASC, `section_key` ASC',
                $this->database->table('page_sections'),
                $this->placeholders(count($pageIds))
            )
        );
        $statement->execute($pageIds);

        $sections = [];

        foreach ($statement->fetchAll() as $row) {
            $pageId = (int) $row['page_id'];
            $group = (string) $row['section_group'];
            $key = (string) $row['section_key'];
            $sections[$pageId][$group][$key] = $this->decodeJson($row['payload_json'], '');
        }

        return $sections;
    }

    /**
     * @param array<int, int> $pageIds
     * @return array<int, array<string, array<string, mixed>>>
     */
    private function loadTranslations(PDO $pdo, array $pageIds): array
    {
        if ($pageIds === []) {
            return [];
        }

        $translationStatement = $pdo->prepare(
            sprintf(
                'SELECT * FROM `%s`
                 WHERE `page_id` IN (%s)
                 ORDER BY `page_id` ASC, `locale` ASC',
                $this->database->table('page_translations'),
                $this->placeholders(count($pageIds))
            )
        );
        $translationStatement->execute($pageIds);
        $translationRows = $translationStatement->fetchAll();

        if (!is_array($translationRows) || $translationRows === []) {
            return [];
        }

        $translationIds = array_values(
            array_map(static fn (array $row): int => (int) $row['id'], $translationRows)
        );
        $sectionStatement = $pdo->prepare(
            sprintf(
                'SELECT `translation_id`, `section_group`, `section_key`, `payload_json`
                 FROM `%s`
                 WHERE `translation_id` IN (%s)
                 ORDER BY `translation_id` ASC, `section_group` ASC, `section_key` ASC',
                $this->database->table('page_translation_sections'),
                $this->placeholders(count($translationIds))
            )
        );
        $sectionStatement->execute($translationIds);
        $sectionRows = $sectionStatement->fetchAll();

        $sections = [];
        foreach ($sectionRows as $row) {
            $translationId = (int) $row['translation_id'];
            $group = (string) $row['section_group'];
            $key = (string) $row['section_key'];
            $sections[$translationId][$group][$key] = $this->decodeJson($row['payload_json'], '');
        }

        $translations = [];
        foreach ($translationRows as $row) {
            $translationId = (int) $row['id'];
            $pageId = (int) $row['page_id'];
            $locale = (string) $row['locale'];
            $translations[$pageId][$locale] = [
                'title' => $row['title'] !== null ? (string) $row['title'] : null,
                'blocks' => $sections[$translationId]['blocks'] ?? [],
                'regions' => $sections[$translationId]['regions'] ?? [],
                'meta' => $this->decodeJson($row['meta_json'], []),
            ];
        }

        return $translations;
    }

    /**
     * @param array<string, mixed> $page
     */
    private function insertPageRow(PDO $pdo, array $page, ?int $sortOrder = null): int
    {
        $position = $sortOrder ?? $this->nextSortOrder($pdo);
        $statement = $pdo->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`slug`, `type`, `status`, `title`, `layout`, `route`, `meta_json`, `sort_order`)
                 VALUES
                    (:slug, :type, :status, :title, :layout, :route, :meta_json, :sort_order)',
                $this->database->table('pages')
            )
        );
        $statement->execute([
            'slug' => (string) $page['slug'],
            'type' => (string) $page['type'],
            'status' => (string) $page['status'],
            'title' => $page['title'],
            'layout' => (string) ($page['layout'] ?? ''),
            'route' => $page['route'],
            'meta_json' => $this->encodeJson($page['meta'] ?? []),
            'sort_order' => $position,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $page
     */
    private function updatePageRow(PDO $pdo, int $pageId, array $page, int $sortOrder): void
    {
        $statement = $pdo->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `slug` = :slug,
                     `type` = :type,
                     `status` = :status,
                     `title` = :title,
                     `layout` = :layout,
                     `route` = :route,
                     `meta_json` = :meta_json,
                     `sort_order` = :sort_order
                 WHERE `id` = :id',
                $this->database->table('pages')
            )
        );
        $statement->execute([
            'id' => $pageId,
            'slug' => (string) $page['slug'],
            'type' => (string) $page['type'],
            'status' => (string) $page['status'],
            'title' => $page['title'],
            'layout' => (string) ($page['layout'] ?? ''),
            'route' => $page['route'],
            'meta_json' => $this->encodeJson($page['meta'] ?? []),
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * @param array<string, mixed> $page
     */
    private function replacePageSections(PDO $pdo, int $pageId, array $page): void
    {
        $delete = $pdo->prepare(
            sprintf('DELETE FROM `%s` WHERE `page_id` = :page_id', $this->database->table('page_sections'))
        );
        $delete->execute(['page_id' => $pageId]);

        $insert = $pdo->prepare(
            sprintf(
                'INSERT INTO `%s` (`page_id`, `section_group`, `section_key`, `payload_json`)
                 VALUES (:page_id, :section_group, :section_key, :payload_json)',
                $this->database->table('page_sections')
            )
        );

        foreach (['blocks', 'regions'] as $group) {
            $sections = is_array($page[$group] ?? null) ? $page[$group] : [];
            foreach ($sections as $key => $payload) {
                $insert->execute([
                    'page_id' => $pageId,
                    'section_group' => $group,
                    'section_key' => (string) $key,
                    'payload_json' => $this->encodeJson($payload),
                ]);
            }
        }
    }

    /**
     * @param array<string, mixed> $page
     */
    private function replaceTranslations(PDO $pdo, int $pageId, array $page): void
    {
        $delete = $pdo->prepare(
            sprintf('DELETE FROM `%s` WHERE `page_id` = :page_id', $this->database->table('page_translations'))
        );
        $delete->execute(['page_id' => $pageId]);

        $insertTranslation = $pdo->prepare(
            sprintf(
                'INSERT INTO `%s` (`page_id`, `locale`, `title`, `meta_json`)
                 VALUES (:page_id, :locale, :title, :meta_json)',
                $this->database->table('page_translations')
            )
        );
        $insertSection = $pdo->prepare(
            sprintf(
                'INSERT INTO `%s` (`translation_id`, `section_group`, `section_key`, `payload_json`)
                 VALUES (:translation_id, :section_group, :section_key, :payload_json)',
                $this->database->table('page_translation_sections')
            )
        );

        $translations = is_array($page['translations'] ?? null) ? $page['translations'] : [];
        foreach ($translations as $locale => $translation) {
            if (!is_string($locale) || !is_array($translation)) {
                continue;
            }

            $insertTranslation->execute([
                'page_id' => $pageId,
                'locale' => $locale,
                'title' => $translation['title'] ?? null,
                'meta_json' => $this->encodeJson($translation['meta'] ?? []),
            ]);

            $translationId = (int) $pdo->lastInsertId();

            foreach (['blocks', 'regions'] as $group) {
                $sections = is_array($translation[$group] ?? null) ? $translation[$group] : [];
                foreach ($sections as $key => $payload) {
                    $insertSection->execute([
                        'translation_id' => $translationId,
                        'section_group' => $group,
                        'section_key' => (string) $key,
                        'payload_json' => $this->encodeJson($payload),
                    ]);
                }
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pageRowBySlug(PDO $pdo, string $slug): ?array
    {
        $statement = $pdo->prepare(
            sprintf('SELECT * FROM `%s` WHERE `slug` = :slug LIMIT 1', $this->database->table('pages'))
        );
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function slugExists(PDO $pdo, string $slug, ?int $ignoreId = null): bool
    {
        if ($ignoreId === null) {
            $statement = $pdo->prepare(
                sprintf('SELECT COUNT(*) FROM `%s` WHERE `slug` = :slug', $this->database->table('pages'))
            );
            $statement->execute(['slug' => $slug]);
        } else {
            $statement = $pdo->prepare(
                sprintf(
                    'SELECT COUNT(*) FROM `%s` WHERE `slug` = :slug AND `id` <> :ignore_id',
                    $this->database->table('pages')
                )
            );
            $statement->execute([
                'slug' => $slug,
                'ignore_id' => $ignoreId,
            ]);
        }

        return (int) $statement->fetchColumn() > 0;
    }

    private function routeExists(PDO $pdo, string $route, ?int $ignoreId = null): bool
    {
        if ($ignoreId === null) {
            $statement = $pdo->prepare(
                sprintf('SELECT COUNT(*) FROM `%s` WHERE `route` = :route', $this->database->table('pages'))
            );
            $statement->execute(['route' => $route]);
        } else {
            $statement = $pdo->prepare(
                sprintf(
                    'SELECT COUNT(*) FROM `%s` WHERE `route` = :route AND `id` <> :ignore_id',
                    $this->database->table('pages')
                )
            );
            $statement->execute([
                'route' => $route,
                'ignore_id' => $ignoreId,
            ]);
        }

        return (int) $statement->fetchColumn() > 0;
    }

    private function nextSortOrder(PDO $pdo): int
    {
        $value = $pdo->query(
            sprintf('SELECT COALESCE(MAX(`sort_order`), -1) + 1 FROM `%s`', $this->database->table('pages'))
        )->fetchColumn();

        return is_numeric($value) ? (int) $value : 0;
    }

    private function encodeJson(mixed $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? 'null' : $encoded;
    }

    private function decodeJson(?string $json, mixed $default): mixed
    {
        if ($json === null || trim($json) === '') {
            return $default;
        }

        $decoded = json_decode($json, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }

    /**
     * @return array{meta: array<string, mixed>, pages: array<int, array<string, mixed>>}
     */
    private function rememberRegistry(array $registry): array
    {
        $this->registryCache = $registry;

        return $registry;
    }

    private function placeholders(int $count): string
    {
        return implode(', ', array_fill(0, $count, '?'));
    }
}
