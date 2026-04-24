<?php

declare(strict_types=1);

namespace Caramagnols\Content;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class TileRepository
{
    public const DEFAULT_THEME = 'windows10-classic';
    public const DEFAULT_REGION = 'after_body';
    public const DEFAULT_SIZE = 'rectangle';

    /**
     * @var array<string, string>
     */
    private const THEME_LABELS = [
        self::DEFAULT_THEME => 'Windows 10 classique',
    ];

    /**
     * @var array<string, string>
     */
    private const COLOR_LABELS = [
        'bleu' => 'Bleu',
        'bleufonce' => 'Bleu fonce',
        'bleuturquoise' => 'Bleu turquoise',
        'bleuvert' => 'Bleu vert',
        'blanc' => 'Blanc',
        'gris' => 'Gris',
        'jaune' => 'Jaune',
        'noir' => 'Noir',
        'orange' => 'Orange',
        'rose' => 'Rose',
        'rouge' => 'Rouge',
        'rougefonce' => 'Rouge fonce',
        'vertfonce' => 'Vert fonce',
        'violet' => 'Violet',
        'violetfonce' => 'Violet fonce',
    ];

    /**
     * @var array<string, string>
     */
    private const SIZE_LABELS = [
        'rectangle' => 'Rectangle',
        'large' => 'Grand',
        'medium' => 'Moyen',
        'small' => 'Petit',
    ];

    /**
     * @var array<string, string>
     */
    private const SIZE_FOLDERS = [
        'small' => 'boutonpetit',
        'medium' => 'boutonmoyen',
        'large' => 'boutongrand',
        'rectangle' => 'boutonrectangle',
    ];

    /**
     * @var array<string, string>
     */
    private const SIZE_FILE_PREFIXES = [
        'small' => 'btptt_',
        'medium' => 'btmoy_',
        'large' => 'btgrd_',
        'rectangle' => 'btrect_',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const SIZE_SUPPORTED_COLORS = [
        'small' => [
            'blanc',
            'bleu',
            'bleufonce',
            'bleuturquoise',
            'bleuvert',
            'gris',
            'jaune',
            'noir',
            'orange',
            'rouge',
            'rougefonce',
            'vertfonce',
            'violet',
            'violetfonce',
        ],
        'medium' => [
            'blanc',
            'bleu',
            'bleufonce',
            'bleuturquoise',
            'bleuvert',
            'gris',
            'jaune',
            'noir',
            'orange',
            'rouge',
            'rougefonce',
            'vertfonce',
            'violet',
            'violetfonce',
        ],
        'large' => [
            'blanc',
            'bleu',
            'bleufonce',
            'bleuturquoise',
            'bleuvert',
            'gris',
            'jaune',
            'noir',
            'orange',
            'rose',
            'rouge',
            'rougefonce',
            'vertfonce',
            'violet',
            'violetfonce',
        ],
        'rectangle' => [
            'blanc',
            'bleu',
            'bleufonce',
            'bleuturquoise',
            'bleuvert',
            'gris',
            'jaune',
            'noir',
            'orange',
            'rouge',
            'rougefonce',
            'vertfonce',
            'violet',
            'violetfonce',
        ],
    ];

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $pagePlacementsCache = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $groupCache = [];

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $groupSummariesCache = null;

    public function __construct(
        private readonly EditorialDatabase $database
    ) {
    }

    public function clearCache(): void
    {
        $this->pagePlacementsCache = [];
        $this->groupCache = [];
        $this->groupSummariesCache = null;
    }

    /**
     * @return array<string, string>
     */
    public static function themeLabels(): array
    {
        return self::THEME_LABELS;
    }

    /**
     * @return array<string, string>
     */
    public static function colorLabels(): array
    {
        return self::COLOR_LABELS;
    }

    /**
     * @return array<string, string>
     */
    public static function sizeLabels(): array
    {
        return self::SIZE_LABELS;
    }

    public static function normalizeTileSizeValue(string $size): string
    {
        $normalized = strtolower(trim($size));

        return array_key_exists($normalized, self::SIZE_LABELS) ? $normalized : self::DEFAULT_SIZE;
    }

    public static function buttonFolderForSize(string $size): string
    {
        $normalizedSize = self::normalizeTileSizeValue($size);

        return self::SIZE_FOLDERS[$normalizedSize] ?? self::SIZE_FOLDERS[self::DEFAULT_SIZE];
    }

    public static function buttonColorToken(string $size, string $colorToken): string
    {
        $normalizedSize = self::normalizeTileSizeValue($size);
        $normalizedColor = self::normalizeColorToken($colorToken);
        $supportedColors = self::SIZE_SUPPORTED_COLORS[$normalizedSize] ?? [];

        if ($supportedColors !== [] && in_array($normalizedColor, $supportedColors, true)) {
            return $normalizedColor;
        }

        return 'bleu';
    }

    public static function buttonFilename(string $size, string $colorToken, string $state = 'default'): string
    {
        $normalizedSize = self::normalizeTileSizeValue($size);
        $normalizedColor = self::buttonColorToken($normalizedSize, $colorToken);
        $prefix = self::SIZE_FILE_PREFIXES[$normalizedSize] ?? self::SIZE_FILE_PREFIXES[self::DEFAULT_SIZE];
        $suffix = match (strtolower(trim($state))) {
            'hover', 'selection', 'selected' => '_selection',
            'active', 'clic', 'click' => '_clic',
            default => '',
        };

        return $prefix . $normalizedColor . $suffix . '.png';
    }

    /**
     * @return array<int, array{id: int, name: string, theme: string, tileCount: int}>
     */
    public function groupReferenceOptions(): array
    {
        return array_map(
            static fn (array $summary): array => [
                'id' => (int) $summary['id'],
                'name' => (string) $summary['name'],
                'theme' => (string) $summary['theme'],
                'tileCount' => (int) $summary['tileCount'],
            ],
            $this->listGroupSummaries()
        );
    }

    /**
     * @return array<int, array{id: int, name: string, theme: string, tileCount: int, placementCount: int}>
     */
    public function listGroupSummaries(): array
    {
        if ($this->groupSummariesCache !== null) {
            return $this->groupSummariesCache;
        }

        try {
            $this->database->ensureReady();
            $pdo = $this->database->pdo();
            $statement = $pdo->query(
                sprintf(
                    'SELECT
                        g.`id`,
                        g.`name`,
                        g.`theme`,
                        COUNT(DISTINCT i.`id`) AS tile_count,
                        COUNT(DISTINCT p.`id`) AS placement_count
                     FROM `%s` g
                     LEFT JOIN `%s` i ON i.`group_id` = g.`id`
                     LEFT JOIN `%s` p ON p.`group_id` = g.`id`
                     GROUP BY g.`id`, g.`name`, g.`theme`
                     ORDER BY g.`name` ASC, g.`id` ASC',
                    $this->database->table('tile_groups'),
                    $this->database->table('tile_group_items'),
                    $this->database->table('page_tile_placements')
                )
            );

            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows)) {
                return $this->groupSummariesCache = [];
            }

            $summaries = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $summaries[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => trim((string) ($row['name'] ?? '')),
                    'theme' => self::normalizeTheme((string) ($row['theme'] ?? self::DEFAULT_THEME)),
                    'tileCount' => max(0, (int) ($row['tile_count'] ?? 0)),
                    'placementCount' => max(0, (int) ($row['placement_count'] ?? 0)),
                ];
            }

            return $this->groupSummariesCache = $summaries;
        } catch (\Throwable $exception) {
            $this->reportFailure($exception);

            return $this->groupSummariesCache = [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findGroupForAdmin(int $groupId): ?array
    {
        if ($groupId <= 0) {
            return null;
        }

        $groups = $this->loadGroupsByIds([$groupId]);

        return $groups[$groupId] ?? null;
    }

    public function saveGroup(array $group): int|false
    {
        $normalized = $this->normalizeGroupInput($group);
        if ($normalized === null) {
            return false;
        }

        try {
            $this->database->ensureReady();
            $pdo = $this->database->pdo();
            $transactionStarted = $pdo->beginTransaction();

            $groupId = (int) ($normalized['id'] ?? 0);
            if ($groupId > 0 && $this->groupExists($pdo, $groupId)) {
                $update = $pdo->prepare(
                    sprintf(
                        'UPDATE `%s`
                         SET `name` = :name, `theme` = :theme
                         WHERE `id` = :id',
                        $this->database->table('tile_groups')
                    )
                );
                $update->execute([
                    'id' => $groupId,
                    'name' => (string) $normalized['name'],
                    'theme' => (string) $normalized['theme'],
                ]);
            } else {
                $insert = $pdo->prepare(
                    sprintf(
                        'INSERT INTO `%s` (`name`, `theme`) VALUES (:name, :theme)',
                        $this->database->table('tile_groups')
                    )
                );
                $insert->execute([
                    'name' => (string) $normalized['name'],
                    'theme' => (string) $normalized['theme'],
                ]);
                $groupId = (int) $pdo->lastInsertId();
            }

            $deleteItems = $pdo->prepare(
                sprintf(
                    'DELETE FROM `%s` WHERE `group_id` = :group_id',
                    $this->database->table('tile_group_items')
                )
            );
            $deleteItems->execute(['group_id' => $groupId]);

            $insertItem = $pdo->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`group_id`, `item_uid`, `sort_order`, `tile_size`, `color_token`, `image_src`, `image_width`, `image_height`, `target_type`, `target_page_slug`, `target_route`, `target_url`, `open_in_new_tab`)
                     VALUES
                        (:group_id, :item_uid, :sort_order, :tile_size, :color_token, :image_src, :image_width, :image_height, :target_type, :target_page_slug, :target_route, :target_url, :open_in_new_tab)',
                    $this->database->table('tile_group_items')
                )
            );
            $insertTranslation = $pdo->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`item_id`, `locale`, `label_text`, `accessibility_alt`, `accessibility_title`)
                     VALUES
                        (:item_id, :locale, :label_text, :accessibility_alt, :accessibility_title)',
                    $this->database->table('tile_group_item_translations')
                )
            );

            $itemUids = [];
            foreach ($normalized['items'] as $item) {
                $insertItem->execute([
                    'group_id' => $groupId,
                    'item_uid' => (string) $item['item_uid'],
                    'sort_order' => (int) $item['sort_order'],
                    'tile_size' => (string) $item['tile_size'],
                    'color_token' => (string) $item['color_token'],
                    'image_src' => $item['image_src'] !== '' ? (string) $item['image_src'] : null,
                    'image_width' => $item['image_width'],
                    'image_height' => $item['image_height'],
                    'target_type' => (string) $item['target_type'],
                    'target_page_slug' => $item['target_page_slug'] !== '' ? (string) $item['target_page_slug'] : null,
                    'target_route' => $item['target_route'] !== '' ? (string) $item['target_route'] : null,
                    'target_url' => $item['target_url'] !== '' ? (string) $item['target_url'] : null,
                    'open_in_new_tab' => !empty($item['open_in_new_tab']) ? 1 : 0,
                ]);

                $itemId = (int) $pdo->lastInsertId();
                $itemUids[] = (string) $item['item_uid'];

                foreach ($item['translations'] as $locale => $translation) {
                    $insertTranslation->execute([
                        'item_id' => $itemId,
                        'locale' => (string) $locale,
                        'label_text' => $translation['label'] !== '' ? (string) $translation['label'] : null,
                        'accessibility_alt' => $translation['alt'] !== '' ? (string) $translation['alt'] : null,
                        'accessibility_title' => $translation['title'] !== '' ? (string) $translation['title'] : null,
                    ]);
                }
            }

            $this->cleanupGroupOverrideOrphans($pdo, $groupId, $itemUids);
            $pdo->commit();
            $this->clearCache();

            return $groupId;
        } catch (\Throwable $exception) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $this->reportFailure($exception);

            return false;
        }
    }

    public function deleteGroup(int $groupId): bool
    {
        if ($groupId <= 0) {
            return false;
        }

        try {
            $this->database->ensureReady();
            $pdo = $this->database->pdo();
            $delete = $pdo->prepare(
                sprintf(
                    'DELETE FROM `%s` WHERE `id` = :id',
                    $this->database->table('tile_groups')
                )
            );
            $delete->execute(['id' => $groupId]);
            $this->clearCache();

            return $delete->rowCount() > 0;
        } catch (\Throwable $exception) {
            $this->reportFailure($exception);

            return false;
        }
    }

    /**
     * @param array<int, string> $availableLanguages
     * @return array<int, array<string, mixed>>
     */
    public function placementsForPageEditor(string $pageSlug, array $availableLanguages): array
    {
        $placements = $this->loadPagePlacements($pageSlug, self::DEFAULT_REGION);
        if ($placements === []) {
            return [];
        }

        $groupIds = [];
        foreach ($placements as $placement) {
            $groupId = (int) ($placement['group_id'] ?? 0);
            if ($groupId > 0) {
                $groupIds[] = $groupId;
            }
        }

        $groups = $this->loadGroupsByIds(array_values(array_unique($groupIds)));
        $formPlacements = [];

        foreach ($placements as $placement) {
            $groupId = (int) ($placement['group_id'] ?? 0);
            $group = $groups[$groupId] ?? null;
            if (!is_array($group)) {
                continue;
            }

            $placementOverrides = is_array($placement['overrides'] ?? null) ? $placement['overrides'] : [];
            $groupItems = is_array($group['items'] ?? null) ? $group['items'] : [];
            $groupItemsForForm = [];
            $overridesForForm = [];

            foreach ($groupItems as $groupItem) {
                if (!is_array($groupItem)) {
                    continue;
                }

                $itemUid = (string) ($groupItem['item_uid'] ?? '');
                if ($itemUid === '') {
                    continue;
                }

                $target = is_array($groupItem['target'] ?? null) ? $groupItem['target'] : [];
                $groupItemsForForm[] = [
                    'item_uid' => $itemUid,
                    'label' => $this->preferredLabel($groupItem, $availableLanguages),
                    'tile_size' => (string) ($groupItem['tile_size'] ?? self::DEFAULT_SIZE),
                    'color_token' => (string) ($groupItem['color_token'] ?? 'bleu'),
                    'image_src' => (string) ($groupItem['image_src'] ?? ''),
                    'target_summary' => $this->targetSummary($target),
                ];

                $override = is_array($placementOverrides[$itemUid] ?? null) ? $placementOverrides[$itemUid] : [];
                $overridesForForm[$itemUid] = $this->placementOverrideFormData($override, $availableLanguages);
            }

            $formPlacements[] = [
                'placement_id' => (string) ($placement['id'] ?? ''),
                'group_id' => (string) $groupId,
                'group_name' => (string) ($group['name'] ?? ''),
                'region_key' => (string) ($placement['region_key'] ?? self::DEFAULT_REGION),
                'sort_order' => (string) ($placement['sort_order'] ?? 0),
                'group_items' => $groupItemsForForm,
                'overrides' => $overridesForForm,
            ];
        }

        return $formPlacements;
    }

    /**
     * @param array<int, array<string, mixed>> $placements
     */
    public function replacePlacementsForPage(string $pageSlug, array $placements, ?string $originalPageSlug = null): bool
    {
        $normalizedPageSlug = trim($pageSlug);
        if ($normalizedPageSlug === '') {
            return false;
        }

        $normalizedPlacements = $this->normalizePlacementList($placements);

        try {
            $this->database->ensureReady();
            $pdo = $this->database->pdo();
            $transactionStarted = $pdo->beginTransaction();

            $slugsToDelete = [$normalizedPageSlug];
            $normalizedOriginalSlug = trim((string) ($originalPageSlug ?? ''));
            if ($normalizedOriginalSlug !== '' && $normalizedOriginalSlug !== $normalizedPageSlug) {
                $slugsToDelete[] = $normalizedOriginalSlug;
            }

            $this->deletePlacementsForPageSlugs($pdo, array_values(array_unique($slugsToDelete)));
            if ($normalizedPlacements !== []) {
                $groupIds = [];
                foreach ($normalizedPlacements as $placement) {
                    $groupIds[] = (int) $placement['group_id'];
                }

                $groups = $this->loadGroupsByIds(array_values(array_unique($groupIds)));
                $insertPlacement = $pdo->prepare(
                    sprintf(
                        'INSERT INTO `%s`
                            (`page_slug`, `region_key`, `group_id`, `sort_order`)
                         VALUES
                            (:page_slug, :region_key, :group_id, :sort_order)',
                        $this->database->table('page_tile_placements')
                    )
                );
                $insertOverride = $pdo->prepare(
                    sprintf(
                        'INSERT INTO `%s`
                            (`placement_id`, `group_item_uid`, `is_visible`, `target_type`, `target_page_slug`, `target_route`, `target_url`, `open_in_new_tab`, `label_translations_json`, `alt_translations_json`, `title_translations_json`)
                         VALUES
                            (:placement_id, :group_item_uid, :is_visible, :target_type, :target_page_slug, :target_route, :target_url, :open_in_new_tab, :label_translations_json, :alt_translations_json, :title_translations_json)',
                        $this->database->table('page_tile_item_overrides')
                    )
                );

                foreach ($normalizedPlacements as $placement) {
                    $groupId = (int) ($placement['group_id'] ?? 0);
                    $group = $groups[$groupId] ?? null;
                    if (!is_array($group)) {
                        continue;
                    }

                    $allowedItemUids = [];
                    foreach (($group['items'] ?? []) as $groupItem) {
                        if (!is_array($groupItem)) {
                            continue;
                        }

                        $itemUid = trim((string) ($groupItem['item_uid'] ?? ''));
                        if ($itemUid !== '') {
                            $allowedItemUids[$itemUid] = true;
                        }
                    }

                    $insertPlacement->execute([
                        'page_slug' => $normalizedPageSlug,
                        'region_key' => self::DEFAULT_REGION,
                        'group_id' => $groupId,
                        'sort_order' => (int) ($placement['sort_order'] ?? 0),
                    ]);
                    $placementId = (int) $pdo->lastInsertId();

                    $overrides = is_array($placement['overrides'] ?? null) ? $placement['overrides'] : [];
                    foreach ($overrides as $itemUid => $override) {
                        if (!is_string($itemUid) || !isset($allowedItemUids[$itemUid]) || !is_array($override)) {
                            continue;
                        }

                        if (!$this->placementOverrideHasPersistedValue($override)) {
                            continue;
                        }

                        $insertOverride->execute([
                            'placement_id' => $placementId,
                            'group_item_uid' => $itemUid,
                            'is_visible' => array_key_exists('is_visible', $override)
                                ? $this->normalizeNullableBoolValue($override['is_visible'])
                                : null,
                            'target_type' => $override['target_type'] ?? null,
                            'target_page_slug' => $override['target_page_slug'] !== '' ? (string) $override['target_page_slug'] : null,
                            'target_route' => $override['target_route'] !== '' ? (string) $override['target_route'] : null,
                            'target_url' => $override['target_url'] !== '' ? (string) $override['target_url'] : null,
                            'open_in_new_tab' => array_key_exists('open_in_new_tab', $override)
                                ? $this->normalizeNullableBoolValue($override['open_in_new_tab'])
                                : null,
                            'label_translations_json' => $this->encodeJsonMap($override['labels'] ?? []),
                            'alt_translations_json' => $this->encodeJsonMap($override['alts'] ?? []),
                            'title_translations_json' => $this->encodeJsonMap($override['titles'] ?? []),
                        ]);
                    }
                }
            }

            if ($transactionStarted && $pdo->inTransaction()) {
                $pdo->commit();
            }
            $this->clearCache();

            return true;
        } catch (\Throwable $exception) {
            if (
                isset($pdo, $transactionStarted)
                && $pdo instanceof PDO
                && $transactionStarted
                && $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            $this->reportFailure($exception);

            return false;
        }
    }

    public function deletePlacementsForPage(string $pageSlug): bool
    {
        $pageSlug = trim($pageSlug);
        if ($pageSlug === '') {
            return false;
        }

        try {
            $this->database->ensureReady();
            $pdo = $this->database->pdo();
            $this->deletePlacementsForPageSlugs($pdo, [$pageSlug]);
            $this->clearCache();

            return true;
        } catch (\Throwable $exception) {
            $this->reportFailure($exception);

            return false;
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function referencesToPageSlug(string $pageSlug): array
    {
        $pageSlug = trim($pageSlug);
        if ($pageSlug === '') {
            return [];
        }

        try {
            $this->database->ensureReady();
            $pdo = $this->database->pdo();
            $references = [];

            $defaultTargetStatement = $pdo->prepare(
                sprintf(
                    'SELECT
                        p.`page_slug`,
                        p.`region_key`,
                        g.`name` AS `group_name`,
                        i.`item_uid`
                     FROM `%s` p
                     INNER JOIN `%s` g ON g.`id` = p.`group_id`
                     INNER JOIN `%s` i ON i.`group_id` = g.`id`
                     WHERE i.`target_type` = :target_type
                       AND i.`target_page_slug` = :target_page_slug
                     ORDER BY p.`page_slug` ASC, p.`sort_order` ASC, i.`sort_order` ASC',
                    $this->database->table('page_tile_placements'),
                    $this->database->table('tile_groups'),
                    $this->database->table('tile_group_items')
                )
            );
            $defaultTargetStatement->execute([
                'target_type' => 'page',
                'target_page_slug' => $pageSlug,
            ]);

            foreach ($defaultTargetStatement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $references[] = [
                    'location' => 'Tuiles after_body',
                    'context' => 'Groupe de tuiles',
                    'path' => trim((string) ($row['page_slug'] ?? '')) . ' > ' . trim((string) ($row['group_name'] ?? '')),
                ];
            }

            $overrideTargetStatement = $pdo->prepare(
                sprintf(
                    'SELECT
                        p.`page_slug`,
                        p.`region_key`,
                        g.`name` AS `group_name`,
                        o.`group_item_uid`
                     FROM `%s` o
                     INNER JOIN `%s` p ON p.`id` = o.`placement_id`
                     INNER JOIN `%s` g ON g.`id` = p.`group_id`
                     WHERE o.`target_type` = :target_type
                       AND o.`target_page_slug` = :target_page_slug
                     ORDER BY p.`page_slug` ASC, p.`sort_order` ASC, o.`group_item_uid` ASC',
                    $this->database->table('page_tile_item_overrides'),
                    $this->database->table('page_tile_placements'),
                    $this->database->table('tile_groups')
                )
            );
            $overrideTargetStatement->execute([
                'target_type' => 'page',
                'target_page_slug' => $pageSlug,
            ]);

            foreach ($overrideTargetStatement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $references[] = [
                    'location' => 'Tuiles after_body',
                    'context' => 'Override de tuile',
                    'path' => trim((string) ($row['page_slug'] ?? '')) . ' > ' . trim((string) ($row['group_name'] ?? '')),
                ];
            }

            return $references;
        } catch (\Throwable $exception) {
            $this->reportFailure($exception);

            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function renderablePlacements(string $pageSlug, string $regionKey = self::DEFAULT_REGION): array
    {
        $placements = $this->loadPagePlacements($pageSlug, $regionKey);
        if ($placements === []) {
            return [];
        }

        $groupIds = [];
        foreach ($placements as $placement) {
            $groupId = (int) ($placement['group_id'] ?? 0);
            if ($groupId > 0) {
                $groupIds[] = $groupId;
            }
        }

        $groups = $this->loadGroupsByIds(array_values(array_unique($groupIds)));
        $renderable = [];

        foreach ($placements as $placement) {
            $groupId = (int) ($placement['group_id'] ?? 0);
            $group = $groups[$groupId] ?? null;
            if (!is_array($group)) {
                continue;
            }

            $renderable[] = [
                'id' => (int) ($placement['id'] ?? 0),
                'page_slug' => (string) ($placement['page_slug'] ?? ''),
                'region_key' => (string) ($placement['region_key'] ?? self::DEFAULT_REGION),
                'sort_order' => (int) ($placement['sort_order'] ?? 0),
                'group' => [
                    'id' => $groupId,
                    'name' => (string) ($group['name'] ?? ''),
                    'theme' => (string) ($group['theme'] ?? self::DEFAULT_THEME),
                    'items' => is_array($group['items'] ?? null) ? $group['items'] : [],
                ],
                'overrides' => is_array($placement['overrides'] ?? null) ? $placement['overrides'] : [],
            ];
        }

        return $renderable;
    }

    /**
     * @param array<int, int> $groupIds
     * @return array<int, array<string, mixed>>
     */
    private function loadGroupsByIds(array $groupIds, ?PDO $pdo = null): array
    {
        $groupIds = array_values(array_unique(array_filter($groupIds, static fn (int $groupId): bool => $groupId > 0)));
        if ($groupIds === []) {
            return [];
        }

        $result = [];
        $missingIds = [];
        foreach ($groupIds as $groupId) {
            if (isset($this->groupCache[$groupId])) {
                $result[$groupId] = $this->groupCache[$groupId];
                continue;
            }

            $missingIds[] = $groupId;
        }

        if ($missingIds === []) {
            return $result;
        }

        try {
            if (!$pdo instanceof PDO) {
                $this->database->ensureReady();
                $pdo = $this->database->pdo();
            }
            $placeholders = implode(', ', array_fill(0, count($missingIds), '?'));
            $statement = $pdo->prepare(
                sprintf(
                    'SELECT `id`, `name`, `theme`
                     FROM `%s`
                     WHERE `id` IN (%s)',
                    $this->database->table('tile_groups'),
                    $placeholders
                )
            );
            $statement->execute($missingIds);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $itemsByGroup = $this->loadGroupItems($pdo, $missingIds);
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $groupId = (int) ($row['id'] ?? 0);
                if ($groupId <= 0) {
                    continue;
                }

                $group = [
                    'id' => $groupId,
                    'name' => trim((string) ($row['name'] ?? '')),
                    'theme' => self::normalizeTheme((string) ($row['theme'] ?? self::DEFAULT_THEME)),
                    'items' => $itemsByGroup[$groupId] ?? [],
                ];
                $this->groupCache[$groupId] = $group;
                $result[$groupId] = $group;
            }
        } catch (\Throwable $exception) {
            $this->reportFailure($exception);
        }

        return $result;
    }

    /**
     * @param array<int, int> $groupIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function loadGroupItems(PDO $pdo, array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($groupIds), '?'));
        $statement = $pdo->prepare(
            sprintf(
                'SELECT
                    `id`,
                    `group_id`,
                    `item_uid`,
                    `sort_order`,
                    `tile_size`,
                    `color_token`,
                    `image_src`,
                    `image_width`,
                    `image_height`,
                    `target_type`,
                    `target_page_slug`,
                    `target_route`,
                    `target_url`,
                    `open_in_new_tab`
                 FROM `%s`
                 WHERE `group_id` IN (%s)
                 ORDER BY `sort_order` ASC, `id` ASC',
                $this->database->table('tile_group_items'),
                $placeholders
            )
        );
        $statement->execute($groupIds);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $itemIds = [];
        foreach ($rows as $row) {
            if (is_array($row) && (int) ($row['id'] ?? 0) > 0) {
                $itemIds[] = (int) $row['id'];
            }
        }

        $translationsByItem = $this->loadItemTranslations($pdo, $itemIds);
        $itemsByGroup = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $groupId = (int) ($row['group_id'] ?? 0);
            $itemId = (int) ($row['id'] ?? 0);
            if ($groupId <= 0 || $itemId <= 0) {
                continue;
            }

            $itemsByGroup[$groupId][] = [
                'id' => $itemId,
                'item_uid' => (string) ($row['item_uid'] ?? ''),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'tile_size' => self::normalizeTileSizeValue((string) ($row['tile_size'] ?? self::DEFAULT_SIZE)),
                'color_token' => self::normalizeColorToken((string) ($row['color_token'] ?? 'bleu')),
                'image_src' => trim((string) ($row['image_src'] ?? '')),
                'image_width' => $this->normalizePositiveInt($row['image_width'] ?? null),
                'image_height' => $this->normalizePositiveInt($row['image_height'] ?? null),
                'target' => [
                    'type' => self::normalizeTargetType((string) ($row['target_type'] ?? 'page')),
                    'pageSlug' => trim((string) ($row['target_page_slug'] ?? '')),
                    'route' => trim((string) ($row['target_route'] ?? '')),
                    'url' => trim((string) ($row['target_url'] ?? '')),
                ],
                'open_in_new_tab' => !empty($row['open_in_new_tab']),
                'translations' => $translationsByItem[$itemId] ?? [],
            ];
        }

        return $itemsByGroup;
    }

    /**
     * @param array<int, int> $itemIds
     * @return array<int, array<string, array<string, string>>>
     */
    private function loadItemTranslations(PDO $pdo, array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($itemIds), '?'));
        $statement = $pdo->prepare(
            sprintf(
                'SELECT `item_id`, `locale`, `label_text`, `accessibility_alt`, `accessibility_title`
                 FROM `%s`
                 WHERE `item_id` IN (%s)
                 ORDER BY `locale` ASC, `id` ASC',
                $this->database->table('tile_group_item_translations'),
                $placeholders
            )
        );
        $statement->execute($itemIds);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $translations = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $itemId = (int) ($row['item_id'] ?? 0);
            $locale = trim((string) ($row['locale'] ?? ''));
            if ($itemId <= 0 || $locale === '') {
                continue;
            }

            $translations[$itemId][$locale] = [
                'label' => trim((string) ($row['label_text'] ?? '')),
                'alt' => trim((string) ($row['accessibility_alt'] ?? '')),
                'title' => trim((string) ($row['accessibility_title'] ?? '')),
            ];
        }

        return $translations;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadPagePlacements(string $pageSlug, string $regionKey): array
    {
        $normalizedPageSlug = trim($pageSlug);
        $normalizedRegionKey = trim($regionKey);
        if ($normalizedPageSlug === '' || $normalizedRegionKey === '') {
            return [];
        }

        $cacheKey = $normalizedPageSlug . '|' . $normalizedRegionKey;
        if (array_key_exists($cacheKey, $this->pagePlacementsCache)) {
            return $this->pagePlacementsCache[$cacheKey];
        }

        try {
            $this->database->ensureReady();
            $pdo = $this->database->pdo();
            $statement = $pdo->prepare(
                sprintf(
                    'SELECT `id`, `page_slug`, `region_key`, `group_id`, `sort_order`
                     FROM `%s`
                     WHERE `page_slug` = :page_slug
                       AND `region_key` = :region_key
                     ORDER BY `sort_order` ASC, `id` ASC',
                    $this->database->table('page_tile_placements')
                )
            );
            $statement->execute([
                'page_slug' => $normalizedPageSlug,
                'region_key' => $normalizedRegionKey,
            ]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $placementIds = [];
            foreach ($rows as $row) {
                if (is_array($row) && (int) ($row['id'] ?? 0) > 0) {
                    $placementIds[] = (int) $row['id'];
                }
            }

            $overridesByPlacement = $this->loadPlacementOverrides($pdo, $placementIds);
            $placements = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $placementId = (int) ($row['id'] ?? 0);
                if ($placementId <= 0) {
                    continue;
                }

                $placements[] = [
                    'id' => $placementId,
                    'page_slug' => trim((string) ($row['page_slug'] ?? '')),
                    'region_key' => trim((string) ($row['region_key'] ?? self::DEFAULT_REGION)),
                    'group_id' => (int) ($row['group_id'] ?? 0),
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'overrides' => $overridesByPlacement[$placementId] ?? [],
                ];
            }

            return $this->pagePlacementsCache[$cacheKey] = $placements;
        } catch (\Throwable $exception) {
            $this->reportFailure($exception);

            return $this->pagePlacementsCache[$cacheKey] = [];
        }
    }

    /**
     * @param array<int, int> $placementIds
     * @return array<int, array<string, array<string, mixed>>>
     */
    private function loadPlacementOverrides(PDO $pdo, array $placementIds): array
    {
        if ($placementIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($placementIds), '?'));
        $statement = $pdo->prepare(
            sprintf(
                'SELECT
                    `placement_id`,
                    `group_item_uid`,
                    `is_visible`,
                    `target_type`,
                    `target_page_slug`,
                    `target_route`,
                    `target_url`,
                    `open_in_new_tab`,
                    `label_translations_json`,
                    `alt_translations_json`,
                    `title_translations_json`
                 FROM `%s`
                 WHERE `placement_id` IN (%s)',
                $this->database->table('page_tile_item_overrides'),
                $placeholders
            )
        );
        $statement->execute($placementIds);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $overridesByPlacement = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $placementId = (int) ($row['placement_id'] ?? 0);
            $itemUid = trim((string) ($row['group_item_uid'] ?? ''));
            if ($placementId <= 0 || $itemUid === '') {
                continue;
            }

            $override = [
                'is_visible' => $this->normalizeNullableBool($row['is_visible'] ?? null),
                'target_type' => $this->normalizeOverrideTargetType($row['target_type'] ?? null),
                'target_page_slug' => trim((string) ($row['target_page_slug'] ?? '')),
                'target_route' => trim((string) ($row['target_route'] ?? '')),
                'target_url' => trim((string) ($row['target_url'] ?? '')),
                'open_in_new_tab' => $this->normalizeNullableBool($row['open_in_new_tab'] ?? null),
                'labels' => $this->decodeJsonMap(is_string($row['label_translations_json'] ?? null) ? $row['label_translations_json'] : null),
                'alts' => $this->decodeJsonMap(is_string($row['alt_translations_json'] ?? null) ? $row['alt_translations_json'] : null),
                'titles' => $this->decodeJsonMap(is_string($row['title_translations_json'] ?? null) ? $row['title_translations_json'] : null),
            ];

            $overridesByPlacement[$placementId][$itemUid] = $override;
        }

        return $overridesByPlacement;
    }

    private function groupExists(PDO $pdo, int $groupId): bool
    {
        $statement = $pdo->prepare(
            sprintf(
                'SELECT `id` FROM `%s` WHERE `id` = :id',
                $this->database->table('tile_groups')
            )
        );
        $statement->execute(['id' => $groupId]);

        return (bool) $statement->fetchColumn();
    }

    /**
     * @param array<int, string> $itemUids
     */
    private function cleanupGroupOverrideOrphans(PDO $pdo, int $groupId, array $itemUids): void
    {
        if ($groupId <= 0) {
            return;
        }

        if ($itemUids === []) {
            $statement = $pdo->prepare(
                sprintf(
                    'DELETE o
                     FROM `%s` o
                     INNER JOIN `%s` p ON p.`id` = o.`placement_id`
                     WHERE p.`group_id` = :group_id',
                    $this->database->table('page_tile_item_overrides'),
                    $this->database->table('page_tile_placements')
                )
            );
            $statement->execute(['group_id' => $groupId]);

            return;
        }

        $placeholders = implode(', ', array_fill(0, count($itemUids), '?'));
        $statement = $pdo->prepare(
            sprintf(
                'DELETE o
                 FROM `%s` o
                 INNER JOIN `%s` p ON p.`id` = o.`placement_id`
                 WHERE p.`group_id` = ?
                   AND o.`group_item_uid` NOT IN (%s)',
                $this->database->table('page_tile_item_overrides'),
                $this->database->table('page_tile_placements'),
                $placeholders
            )
        );
        $statement->execute(array_merge([$groupId], $itemUids));
    }

    /**
     * @param array<int, string> $pageSlugs
     */
    private function deletePlacementsForPageSlugs(PDO $pdo, array $pageSlugs): void
    {
        $pageSlugs = array_values(
            array_filter(
                array_map(static fn (mixed $pageSlug): string => trim((string) $pageSlug), $pageSlugs),
                static fn (string $pageSlug): bool => $pageSlug !== ''
            )
        );
        if ($pageSlugs === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($pageSlugs), '?'));
        $statement = $pdo->prepare(
            sprintf(
                'DELETE FROM `%s` WHERE `page_slug` IN (%s)',
                $this->database->table('page_tile_placements'),
                $placeholders
            )
        );
        $statement->execute($pageSlugs);
    }

    /**
     * @param array<int, array<string, mixed>> $placements
     * @return array<int, array<string, mixed>>
     */
    private function normalizePlacementList(array $placements): array
    {
        $normalizedPlacements = [];
        foreach (array_values($placements) as $index => $placement) {
            if (!is_array($placement)) {
                continue;
            }

            $normalized = $this->normalizePlacementInput($placement, $index);
            if ($normalized === null) {
                continue;
            }

            $normalizedPlacements[] = $normalized;
        }

        return $normalizedPlacements;
    }

    /**
     * @param array<string, mixed> $placement
     * @return array<string, mixed>|null
     */
    private function normalizePlacementInput(array $placement, int $index): ?array
    {
        $groupId = max(0, (int) ($placement['group_id'] ?? 0));
        if ($groupId <= 0) {
            return null;
        }

        $sortOrder = max(0, (int) ($placement['sort_order'] ?? (($index + 1) * 10)));
        $overridesInput = is_array($placement['overrides'] ?? null) ? $placement['overrides'] : [];
        $overrides = [];

        foreach ($overridesInput as $itemUid => $overrideInput) {
            if (!is_string($itemUid) || !is_array($overrideInput)) {
                continue;
            }

            $override = $this->normalizePlacementOverrideInput($overrideInput);
            if ($override === null) {
                continue;
            }

            $overrides[$itemUid] = $override;
        }

        return [
            'group_id' => $groupId,
            'sort_order' => $sortOrder,
            'overrides' => $overrides,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null
     */
    private function normalizePlacementOverrideInput(array $input): ?array
    {
        $visibilityMode = strtolower(trim((string) ($input['visibility_mode'] ?? 'default')));
        $isVisible = match ($visibilityMode) {
            'hidden', '0', 'false' => false,
            'visible', '1', 'true' => true,
            default => null,
        };

        $targetMode = $this->normalizeOverrideTargetType($input['target_mode'] ?? null);
        $labels = [];
        $alts = [];
        $titles = [];
        $translationsInput = is_array($input['translations'] ?? null) ? $input['translations'] : [];

        foreach ($translationsInput as $locale => $translationInput) {
            if (!is_string($locale)) {
                continue;
            }

            $translation = is_array($translationInput) ? $translationInput : [];
            $label = trim((string) ($translation['label'] ?? ''));
            $alt = trim((string) ($translation['alt'] ?? ''));
            $title = trim((string) ($translation['title'] ?? ''));

            if ($label !== '') {
                $labels[$locale] = $label;
            }
            if ($alt !== '') {
                $alts[$locale] = $alt;
            }
            if ($title !== '') {
                $titles[$locale] = $title;
            }
        }

        $override = [
            'is_visible' => $isVisible,
            'target_type' => $targetMode,
            'target_page_slug' => '',
            'target_route' => '',
            'target_url' => '',
            'labels' => $labels,
            'alts' => $alts,
            'titles' => $titles,
        ];

        if ($targetMode === 'page') {
            $override['target_page_slug'] = trim((string) ($input['target_page_slug'] ?? ''));
        } elseif ($targetMode === 'route') {
            $override['target_route'] = trim((string) ($input['target_route'] ?? ''));
        } elseif ($targetMode === 'external') {
            $override['target_url'] = trim((string) ($input['target_url'] ?? ''));
        }

        if (!$this->placementOverrideHasPersistedValue($override)) {
            return null;
        }

        return $override;
    }

    /**
     * @param array<string, mixed> $override
     * @param array<int, string> $availableLanguages
     * @return array<string, mixed>
     */
    private function placementOverrideFormData(array $override, array $availableLanguages): array
    {
        $formData = [
            'visibility_mode' => 'default',
            'target_mode' => 'default',
            'target_page_slug' => '',
            'target_route' => '',
            'target_url' => '',
            'translations' => [],
        ];

        if (array_key_exists('is_visible', $override)) {
            $formData['visibility_mode'] = ($override['is_visible'] ?? null) === false ? 'hidden' : 'visible';
        }

        if (is_string($override['target_type'] ?? null) && trim((string) $override['target_type']) !== '') {
            $formData['target_mode'] = (string) $override['target_type'];
        }

        $formData['target_page_slug'] = trim((string) ($override['target_page_slug'] ?? ''));
        $formData['target_route'] = trim((string) ($override['target_route'] ?? ''));
        $formData['target_url'] = trim((string) ($override['target_url'] ?? ''));
        $labels = $this->decodeJsonMap($this->encodeJsonMap($override['labels'] ?? []));
        $alts = $this->decodeJsonMap($this->encodeJsonMap($override['alts'] ?? []));
        $titles = $this->decodeJsonMap($this->encodeJsonMap($override['titles'] ?? []));

        foreach ($availableLanguages as $language) {
            if (!is_string($language) || trim($language) === '') {
                continue;
            }

            $formData['translations'][$language] = [
                'label' => (string) ($labels[$language] ?? ''),
                'alt' => (string) ($alts[$language] ?? ''),
                'title' => (string) ($titles[$language] ?? ''),
            ];
        }

        return $formData;
    }

    /**
     * @param array<string, mixed> $group
     * @return array<string, mixed>|null
     */
    private function normalizeGroupInput(array $group): ?array
    {
        $name = trim((string) ($group['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $theme = self::normalizeTheme((string) ($group['theme'] ?? self::DEFAULT_THEME));
        $groupId = max(0, (int) ($group['id'] ?? 0));
        $itemsInput = is_array($group['items'] ?? null) ? array_values($group['items']) : [];
        $items = [];

        foreach ($itemsInput as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $normalizedItem = $this->normalizeGroupItemInput($item, $index);
            if ($normalizedItem === null) {
                continue;
            }

            $items[] = $normalizedItem;
        }

        if ($items === []) {
            return null;
        }

        return [
            'id' => $groupId,
            'name' => $name,
            'theme' => $theme,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>|null
     */
    private function normalizeGroupItemInput(array $item, int $index): ?array
    {
        $translationsInput = is_array($item['translations'] ?? null) ? $item['translations'] : [];
        $translations = [];

        foreach ($translationsInput as $locale => $translationInput) {
            if (!is_string($locale) || !is_array($translationInput)) {
                continue;
            }

            $label = trim((string) ($translationInput['label'] ?? ''));
            $alt = trim((string) ($translationInput['alt'] ?? ''));
            $title = trim((string) ($translationInput['title'] ?? ''));

            if ($label === '' && $alt === '' && $title === '') {
                continue;
            }

            $translations[$locale] = [
                'label' => $label,
                'alt' => $alt,
                'title' => $title,
            ];
        }

        if ($translations === []) {
            return null;
        }

        $targetType = self::normalizeTargetType((string) ($item['target_type'] ?? 'page'));
        $targetPageSlug = trim((string) ($item['target_page_slug'] ?? ''));
        $targetRoute = trim((string) ($item['target_route'] ?? ''));
        $targetUrl = trim((string) ($item['target_url'] ?? ''));
        if (
            ($targetType === 'page' && $targetPageSlug === '')
            || ($targetType === 'route' && $targetRoute === '')
            || ($targetType === 'external' && $targetUrl === '')
        ) {
            return null;
        }

        return [
            'item_uid' => $this->normalizeItemUid((string) ($item['item_uid'] ?? '')),
            'sort_order' => max(0, (int) ($item['sort_order'] ?? (($index + 1) * 10))),
            'tile_size' => self::normalizeTileSizeValue((string) ($item['tile_size'] ?? self::DEFAULT_SIZE)),
            'color_token' => self::normalizeColorToken((string) ($item['color_token'] ?? 'bleu')),
            'image_src' => trim((string) ($item['image_src'] ?? '')),
            'image_width' => $this->normalizePositiveInt($item['image_width'] ?? null),
            'image_height' => $this->normalizePositiveInt($item['image_height'] ?? null),
            'target_type' => $targetType,
            'target_page_slug' => $targetPageSlug,
            'target_route' => $targetRoute,
            'target_url' => $targetUrl,
            'open_in_new_tab' => !empty($item['open_in_new_tab']),
            'translations' => $translations,
        ];
    }

    private function placementOverrideHasPersistedValue(array $override): bool
    {
        if (array_key_exists('is_visible', $override) && $override['is_visible'] !== null) {
            return true;
        }

        if (is_string($override['target_type'] ?? null) && trim((string) $override['target_type']) !== '') {
            return true;
        }

        if (($override['labels'] ?? []) !== []) {
            return true;
        }

        if (($override['alts'] ?? []) !== []) {
            return true;
        }

        return ($override['titles'] ?? []) !== [];
    }

    /**
     * @param array<string, mixed> $target
     */
    private function targetSummary(array $target): string
    {
        $targetType = self::normalizeTargetType((string) ($target['type'] ?? 'page'));

        return match ($targetType) {
            'page' => 'Page : ' . trim((string) ($target['pageSlug'] ?? '')),
            'route' => 'Route : ' . trim((string) ($target['route'] ?? '')),
            'external' => 'URL : ' . trim((string) ($target['url'] ?? '')),
            default => 'Aucune cible',
        };
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, string> $availableLanguages
     */
    private function preferredLabel(array $item, array $availableLanguages): string
    {
        $translations = is_array($item['translations'] ?? null) ? $item['translations'] : [];

        foreach ($availableLanguages as $language) {
            if (!is_string($language)) {
                continue;
            }

            $label = trim((string) ($translations[$language]['label'] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }

        foreach ($translations as $translation) {
            if (!is_array($translation)) {
                continue;
            }

            $label = trim((string) ($translation['label'] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }

        return 'Tuile';
    }

    private static function normalizeTheme(string $theme): string
    {
        $normalized = strtolower(trim($theme));

        return array_key_exists($normalized, self::THEME_LABELS) ? $normalized : self::DEFAULT_THEME;
    }

    private static function normalizeColorToken(string $colorToken): string
    {
        $normalized = strtolower(trim($colorToken));
        $normalized = str_replace(['boutonrectangle', '_', ' '], ['', '', ''], $normalized);
        $normalized = trim($normalized, '-');

        return array_key_exists($normalized, self::COLOR_LABELS) ? $normalized : 'bleu';
    }

    private static function normalizeTargetType(string $targetType): string
    {
        $normalized = strtolower(trim($targetType));
        if ($normalized === 'url') {
            $normalized = 'external';
        }

        return in_array($normalized, ['page', 'route', 'external'], true) ? $normalized : 'page';
    }

    private function normalizeOverrideTargetType(mixed $targetType): ?string
    {
        if (!is_string($targetType)) {
            return null;
        }

        $normalized = strtolower(trim($targetType));
        if ($normalized === '' || $normalized === 'default') {
            return null;
        }

        return self::normalizeTargetType($normalized);
    }

    private function normalizeItemUid(string $itemUid): string
    {
        $normalized = strtolower(trim($itemUid));
        $normalized = preg_replace('/[^a-z0-9_-]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-_');

        if ($normalized === '') {
            try {
                $normalized = 'tile_' . bin2hex(random_bytes(6));
            } catch (\Throwable) {
                $normalized = 'tile_' . uniqid('', false);
            }
        }

        return $normalized;
    }

    private function normalizePositiveInt(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }

    private function normalizeNullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => null,
        };
    }

    private function normalizeNullableBoolValue(mixed $value): ?int
    {
        $normalized = $this->normalizeNullableBool($value);
        if ($normalized === null) {
            return null;
        }

        return $normalized ? 1 : 0;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function encodeJsonMap(array $values): ?string
    {
        $normalized = [];
        foreach ($values as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $text = trim((string) $value);
            if ($text !== '') {
                $normalized[$key] = $text;
            }
        }

        if ($normalized === []) {
            return null;
        }

        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($json) && $json !== '' ? $json : null;
    }

    /**
     * @return array<string, string>
     */
    private function decodeJsonMap(?string $json): array
    {
        if (!is_string($json) || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $normalized = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $text = trim((string) $value);
            if ($text !== '') {
                $normalized[$key] = $text;
            }
        }

        return $normalized;
    }

    private function reportFailure(\Throwable $exception): void
    {
        if ($this->isExpectedConfigurationFailure($exception)) {
            return;
        }

        error_log('[tile_repository] ' . $exception->getMessage());
    }

    private function isExpectedConfigurationFailure(\Throwable $exception): bool
    {
        return str_contains($exception->getMessage(), 'Configuration SQL éditoriale incomplète.');
    }
}
