<?php

declare(strict_types=1);

namespace Caramagnols\Navigation;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class SqlNavigationStore implements NavigationStoreInterface
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $canonicalCache = null;

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function loadCanonical(array $fallbackLegacy = []): array
    {
        if ($this->canonicalCache !== null) {
            return $this->canonicalCache;
        }

        $fallbackCanonical = NavigationNormalizer::normalizeCanonical(
            NavigationNormalizer::legacyToCanonical($fallbackLegacy)
        );

        try {
            $this->database->ensureReady();
            $pdo = $this->database->pdo();
            $setRows = $pdo->query(
                sprintf(
                    'SELECT * FROM `%s` ORDER BY `id` ASC',
                    $this->database->table('navigation_sets')
                )
            )->fetchAll();

            if (!is_array($setRows) || $setRows === []) {
                return $this->rememberCanonical($fallbackCanonical);
            }

            $setsByLocation = [];
            $setIds = [];
            foreach ($setRows as $row) {
                $locationKey = (string) $row['location_key'];
                $setsByLocation[$locationKey] = $row;
                $setIds[] = (int) $row['id'];
            }

            $itemsBySet = $this->loadItems($pdo, $setIds);
            $locations = [];

            foreach (NavigationNormalizer::locationKeys() as $locationKey) {
                $set = $setsByLocation[$locationKey] ?? null;

                if (!is_array($set)) {
                    $locations[$locationKey] = $this->fallbackLocation($fallbackCanonical, $locationKey);
                    continue;
                }

                if (in_array($locationKey, ['banner', 'remonter', 'footerNotice'], true)) {
                    $locations[$locationKey] = $this->decodeJson(
                        $set['settings_json'],
                        $this->fallbackLocation($fallbackCanonical, $locationKey)
                    );
                    continue;
                }

                $locations[$locationKey] = $itemsBySet[(int) $set['id']] ?? [];
            }

            return $this->rememberCanonical(
                NavigationNormalizer::normalizeCanonical([
                    'meta' => ['version' => NavigationRepository::SCHEMA_VERSION],
                    'locations' => $locations,
                ])
            );
        } catch (\Throwable $exception) {
            error_log('[navigation_sql] ' . $exception->getMessage());

            return $this->rememberCanonical($fallbackCanonical);
        }
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

        try {
            $this->database->ensureReady();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();

            $setIds = [];
            foreach (NavigationNormalizer::locationKeys() as $locationKey) {
                $setId = $this->upsertSet(
                    $pdo,
                    $locationKey,
                    in_array($locationKey, ['banner', 'remonter', 'footerNotice'], true)
                        ? ($normalized['locations'][$locationKey] ?? [])
                        : null
                );
                $setIds[$locationKey] = $setId;
            }

            $deleteItems = $pdo->prepare(
                sprintf('DELETE FROM `%s` WHERE `set_id` = :set_id', $this->database->table('navigation_items'))
            );

            foreach (NavigationNormalizer::itemLocationKeys() as $locationKey) {
                $setId = $setIds[$locationKey];
                $deleteItems->execute(['set_id' => $setId]);
                $items = is_array($normalized['locations'][$locationKey] ?? null)
                    ? $normalized['locations'][$locationKey]
                    : [];
                $this->insertItems($pdo, $setId, null, $items);
            }

            $pdo->commit();
            $this->clearCache();

            return true;
        } catch (\Throwable $exception) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('[navigation_sql] ' . $exception->getMessage());

            return false;
        }
    }

    public function clearCache(): void
    {
        $this->canonicalCache = null;
    }

    /**
     * @param array<int, int> $setIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function loadItems(PDO $pdo, array $setIds): array
    {
        if ($setIds === []) {
            return [];
        }

        $statement = $pdo->prepare(
            sprintf(
                'SELECT * FROM `%s`
                 WHERE `set_id` IN (%s)
                 ORDER BY `set_id` ASC, `parent_id` ASC, `sort_order` ASC, `id` ASC',
                $this->database->table('navigation_items'),
                implode(', ', array_fill(0, count($setIds), '?'))
            )
        );
        $statement->execute($setIds);
        $rows = $statement->fetchAll();

        $bySet = [];
        foreach ($setIds as $setId) {
            $bySet[$setId] = [];
        }

        $childrenIndex = [];
        foreach ($rows as $row) {
            $parentKey = $row['parent_id'] === null ? 'root' : 'parent-' . (string) $row['parent_id'];
            $childrenIndex[(int) $row['set_id']][$parentKey][] = $row;
        }

        foreach ($setIds as $setId) {
            $bySet[$setId] = $this->buildTree((int) $setId, $childrenIndex, null);
        }

        return $bySet;
    }

    /**
     * @param array<int, array<string, mixed>> $childrenIndex
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(int $setId, array $childrenIndex, ?int $parentId): array
    {
        $parentKey = $parentId === null ? 'root' : 'parent-' . $parentId;
        $rows = $childrenIndex[$setId][$parentKey] ?? [];
        $items = [];

        foreach ($rows as $row) {
            $itemId = (int) $row['id'];
            $labelTranslations = $this->normalizeLocalizedTranslations(
                $this->decodeJson($row['label_translations_json'] ?? null, [])
            );
            $labelDefaultLanguage = $this->normalizeLanguageCode($row['label_default_language'] ?? null);
            $items[] = [
                'id' => (string) $row['item_uid'],
                'kind' => (string) $row['kind'],
                'label' => [
                    'text' => $row['label_text'] !== null ? (string) $row['label_text'] : null,
                    'translationKey' => $row['label_translation_key'] !== null
                        ? (string) $row['label_translation_key']
                        : null,
                    'defaultLanguage' => $labelTranslations !== [] ? $labelDefaultLanguage : null,
                    'translations' => $labelTranslations,
                ],
                'target' => [
                    'pageSlug' => $row['target_page_slug'] !== null ? (string) $row['target_page_slug'] : null,
                    'route' => $row['target_route'] !== null ? (string) $row['target_route'] : null,
                    'url' => $row['target_url'] !== null ? (string) $row['target_url'] : null,
                    'openInNewTab' => (bool) $row['open_in_new_tab'],
                ],
                'media' => [
                    'image' => $row['media_image'] !== null ? (string) $row['media_image'] : null,
                ],
                'content' => [
                    'text' => $row['content_text'] !== null ? (string) $row['content_text'] : null,
                ],
                'accessibility' => [
                    'alt' => $row['accessibility_alt'] !== null ? (string) $row['accessibility_alt'] : null,
                    'title' => $row['accessibility_title'] !== null ? (string) $row['accessibility_title'] : null,
                ],
                'presentation' => [
                    'displayMode' => $row['display_mode'] !== null ? (string) $row['display_mode'] : null,
                    'columnCount' => $row['column_count'] !== null ? (int) $row['column_count'] : null,
                    'menuTemplate' => $row['menu_template'] !== null ? (string) $row['menu_template'] : null,
                    'isHighlight' => (bool) ($row['is_highlight'] ?? false),
                    'featuredCard' => [
                        'title' => $row['featured_title'] !== null ? (string) $row['featured_title'] : null,
                        'text' => $row['featured_text'] !== null ? (string) $row['featured_text'] : null,
                        'image' => $row['featured_image'] !== null ? (string) $row['featured_image'] : null,
                        'ctaLabel' => $row['featured_cta_label'] !== null ? (string) $row['featured_cta_label'] : null,
                        'target' => [
                            'pageSlug' => $row['featured_target_page_slug'] !== null ? (string) $row['featured_target_page_slug'] : null,
                            'route' => $row['featured_target_route'] !== null ? (string) $row['featured_target_route'] : null,
                            'url' => $row['featured_target_url'] !== null ? (string) $row['featured_target_url'] : null,
                            'openInNewTab' => (bool) ($row['featured_open_in_new_tab'] ?? false),
                        ],
                    ],
                ],
                'children' => $this->buildTree($setId, $childrenIndex, $itemId),
            ];
        }

        return $items;
    }

    private function upsertSet(PDO $pdo, string $locationKey, mixed $settings): int
    {
        $existing = $pdo->prepare(
            sprintf('SELECT `id` FROM `%s` WHERE `location_key` = :location_key LIMIT 1', $this->database->table('navigation_sets'))
        );
        $existing->execute(['location_key' => $locationKey]);
        $existingId = $existing->fetchColumn();

        if ($existingId !== false) {
            $update = $pdo->prepare(
                sprintf(
                    'UPDATE `%s` SET `settings_json` = :settings_json WHERE `id` = :id',
                    $this->database->table('navigation_sets')
                )
            );
            $update->execute([
                'id' => (int) $existingId,
                'settings_json' => $settings === null ? null : $this->encodeJson($settings),
            ]);

            return (int) $existingId;
        }

        $insert = $pdo->prepare(
            sprintf(
                'INSERT INTO `%s` (`location_key`, `settings_json`) VALUES (:location_key, :settings_json)',
                $this->database->table('navigation_sets')
            )
        );
        $insert->execute([
            'location_key' => $locationKey,
            'settings_json' => $settings === null ? null : $this->encodeJson($settings),
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function insertItems(PDO $pdo, int $setId, ?int $parentId, array $items): void
    {
        $statement = $pdo->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`set_id`, `parent_id`, `item_uid`, `sort_order`, `kind`, `label_text`, `label_translation_key`,
                     `label_default_language`, `label_translations_json`,
                     `display_mode`, `column_count`, `menu_template`, `is_highlight`,
                     `featured_title`, `featured_text`, `featured_image`, `featured_cta_label`,
                     `featured_target_page_slug`, `featured_target_route`, `featured_target_url`, `featured_open_in_new_tab`,
                     `target_page_slug`, `target_route`, `target_url`, `open_in_new_tab`, `media_image`, `content_text`,
                     `accessibility_alt`, `accessibility_title`)
                 VALUES
                    (:set_id, :parent_id, :item_uid, :sort_order, :kind, :label_text, :label_translation_key,
                     :label_default_language, :label_translations_json,
                     :display_mode, :column_count, :menu_template, :is_highlight,
                     :featured_title, :featured_text, :featured_image, :featured_cta_label,
                     :featured_target_page_slug, :featured_target_route, :featured_target_url, :featured_open_in_new_tab,
                     :target_page_slug, :target_route, :target_url, :open_in_new_tab, :media_image, :content_text,
                     :accessibility_alt, :accessibility_title)',
                $this->database->table('navigation_items')
            )
        );

        foreach (array_values($items) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = is_array($item['label'] ?? null) ? $item['label'] : [];
            $target = is_array($item['target'] ?? null) ? $item['target'] : [];
            $media = is_array($item['media'] ?? null) ? $item['media'] : [];
            $content = is_array($item['content'] ?? null) ? $item['content'] : [];
            $accessibility = is_array($item['accessibility'] ?? null) ? $item['accessibility'] : [];
            $presentation = is_array($item['presentation'] ?? null) ? $item['presentation'] : [];
            $featuredCard = is_array($presentation['featuredCard'] ?? null) ? $presentation['featuredCard'] : [];
            $featuredTarget = is_array($featuredCard['target'] ?? null) ? $featuredCard['target'] : [];
            $labelTranslations = $this->normalizeLocalizedTranslations($label['translations'] ?? null);

            $statement->execute([
                'set_id' => $setId,
                'parent_id' => $parentId,
                'item_uid' => (string) ($item['id'] ?? ''),
                'sort_order' => $index,
                'kind' => (string) ($item['kind'] ?? 'route'),
                'label_text' => $label['text'] ?? null,
                'label_translation_key' => $label['translationKey'] ?? null,
                'label_default_language' => $this->normalizeLanguageCode($label['defaultLanguage'] ?? null),
                'label_translations_json' => $labelTranslations === [] ? null : $this->encodeJson($labelTranslations),
                'display_mode' => $presentation['displayMode'] ?? null,
                'column_count' => $presentation['columnCount'] ?? null,
                'menu_template' => $presentation['menuTemplate'] ?? null,
                'is_highlight' => !empty($presentation['isHighlight']) ? 1 : 0,
                'featured_title' => $featuredCard['title'] ?? null,
                'featured_text' => $featuredCard['text'] ?? null,
                'featured_image' => $featuredCard['image'] ?? null,
                'featured_cta_label' => $featuredCard['ctaLabel'] ?? null,
                'featured_target_page_slug' => $featuredTarget['pageSlug'] ?? null,
                'featured_target_route' => $featuredTarget['route'] ?? null,
                'featured_target_url' => $featuredTarget['url'] ?? null,
                'featured_open_in_new_tab' => !empty($featuredTarget['openInNewTab']) ? 1 : 0,
                'target_page_slug' => $target['pageSlug'] ?? null,
                'target_route' => $target['route'] ?? null,
                'target_url' => $target['url'] ?? null,
                'open_in_new_tab' => !empty($target['openInNewTab']) ? 1 : 0,
                'media_image' => $media['image'] ?? null,
                'content_text' => $content['text'] ?? null,
                'accessibility_alt' => $accessibility['alt'] ?? null,
                'accessibility_title' => $accessibility['title'] ?? null,
            ]);

            $itemId = (int) $pdo->lastInsertId();
            $children = is_array($item['children'] ?? null) ? $item['children'] : [];
            if ($children !== []) {
                $this->insertItems($pdo, $setId, $itemId, $children);
            }
        }
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
     * @return array<string, string>
     */
    private function normalizeLocalizedTranslations(mixed $translations): array
    {
        if (!is_array($translations)) {
            return [];
        }

        $normalized = [];
        foreach ($translations as $language => $value) {
            $languageCode = $this->normalizeLanguageCode($language);
            if ($languageCode === null || !is_string($value) || trim($value) === '') {
                continue;
            }

            $normalized[$languageCode] = trim($value);
        }

        ksort($normalized);

        return $normalized;
    }

    private function normalizeLanguageCode(mixed $language): ?string
    {
        if (!is_string($language)) {
            return null;
        }

        $normalized = strtolower(trim($language));
        if ($normalized === '' || preg_match('/^[a-z]{2,5}$/', $normalized) !== 1) {
            return null;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $fallbackCanonical
     * @return array<string, mixed>|array<int, array<string, mixed>>
     */
    private function fallbackLocation(array $fallbackCanonical, string $locationKey): array
    {
        $locations = is_array($fallbackCanonical['locations'] ?? null) ? $fallbackCanonical['locations'] : [];
        $fallback = $locations[$locationKey] ?? [];

        return is_array($fallback) ? $fallback : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function rememberCanonical(array $canonical): array
    {
        $this->canonicalCache = $canonical;

        return $canonical;
    }
}
