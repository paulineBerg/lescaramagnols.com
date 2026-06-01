<?php

declare(strict_types=1);

use Caramagnols\Blog\BlogDiscussionRepositoryInterface;
use Caramagnols\Blog\BlogRepositoryInterface;

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit être exécutée en CLI.\n");
    exit(1);
}

$command = $argv[1] ?? '';

if (!is_string($command) || $command === '') {
    fwrite(STDERR, editorial_backup_restore_usage());
    exit(1);
}

try {
    $storageOverride = parseStorageOverride(array_slice($argv, 2));
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    fwrite(STDERR, editorial_backup_restore_usage());
    exit(1);
}

if ($storageOverride !== null) {
    applyEditorialStorageOverride($storageOverride);
}

$includeDiscussions = in_array('--include-discussions', array_slice($argv, 2), true);
$allowDelete = in_array('--allow-delete', array_slice($argv, 2), true);

if ($command === 'backup') {
    $outputPath = null;

    foreach (array_slice($argv, 2) as $argument) {
        if (!is_string($argument)) {
            continue;
        }

        if (str_starts_with($argument, '--output=')) {
            $outputPath = trim((string) substr($argument, 9));
        }
    }

    $backupPath = backupEditorial($outputPath, $includeDiscussions);
    fwrite(STDOUT, sprintf("Backup éditorial créé : %s\n", $backupPath));
    exit(0);
}

if ($command === 'restore') {
    $backupPath = $argv[2] ?? '';
    $force = in_array('--force', $argv, true);

    if (!is_string($backupPath) || trim($backupPath) === '') {
        fwrite(STDERR, "Chemin du backup manquant.\n");
        fwrite(STDERR, editorial_backup_restore_usage());
        exit(1);
    }

    if (!$force) {
        fwrite(STDERR, "La restauration est destructive. Relancez avec --force.\n");
        exit(1);
    }

    $summary = restoreEditorial($backupPath, $includeDiscussions, $allowDelete);
    fwrite(
        STDOUT,
        sprintf(
            "Restauration terminée : %d page(s), %d emplacement(s) de navigation, %d article(s), %d groupe(s) de tuiles, %d placement(s) de tuiles, %d fil(s) de discussion.\n",
            $summary['pages'],
            $summary['navigationLocations'],
            $summary['articles'],
            $summary['tileGroups'],
            $summary['tilePlacements'],
            $summary['discussionThreads']
        )
    );
    exit(0);
}

if ($command === 'diff') {
    $sourcePath = $argv[2] ?? '';
    $targetPath = $argv[3] ?? '';

    if (!is_string($sourcePath) || trim($sourcePath) === '' || !is_string($targetPath) || trim($targetPath) === '') {
        fwrite(STDERR, "Chemins source et cible manquants.\n");
        fwrite(STDERR, editorial_backup_restore_usage());
        exit(1);
    }

    $diff = diffEditorialPayloads(
        readBackupFile($sourcePath),
        readBackupFile($targetPath),
        $includeDiscussions
    );
    writeEditorialDiff($diff);

    if ($diff['deleteCount'] > 0 && !$allowDelete) {
        fwrite(
            STDERR,
            "Refus: des suppressions éditoriales sont détectées. Relancez avec --allow-delete après vérification.\n"
        );
        exit(3);
    }

    exit(0);
}

if ($command === 'compare') {
    $sourcePath = $argv[2] ?? '';
    $targetPath = $argv[3] ?? '';

    if (!is_string($sourcePath) || trim($sourcePath) === '' || !is_string($targetPath) || trim($targetPath) === '') {
        fwrite(STDERR, "Chemins source et cible manquants.\n");
        fwrite(STDERR, editorial_backup_restore_usage());
        exit(1);
    }

    $source = readBackupFile($sourcePath);
    $target = readBackupFile($targetPath);
    $sourceHash = hashEditorialPayload($source, $includeDiscussions);
    $targetHash = hashEditorialPayload($target, $includeDiscussions);
    fwrite(STDOUT, "source_hash={$sourceHash}\n");
    fwrite(STDOUT, "target_hash={$targetHash}\n");

    if ($sourceHash !== $targetHash) {
        fwrite(STDERR, "content_mismatch\n");
        exit(1);
    }

    fwrite(STDOUT, "content_match\n");
    exit(0);
}

fwrite(STDERR, sprintf("Commande inconnue: %s\n", $command));
fwrite(STDERR, editorial_backup_restore_usage());
exit(1);

function editorial_backup_restore_usage(): string
{
    return <<<TXT
Usage:
  php core/tools/editorial_backup_restore.php backup [--output=/chemin/backup.json] [--storage=json|sql|dual-write] [--include-discussions]
  php core/tools/editorial_backup_restore.php restore /chemin/backup.json --force [--storage=json|sql|dual-write] [--allow-delete] [--include-discussions]
  php core/tools/editorial_backup_restore.php diff /chemin/source.json[.gz] /chemin/cible.json[.gz] [--allow-delete] [--include-discussions]
  php core/tools/editorial_backup_restore.php compare /chemin/source.json[.gz] /chemin/cible.json[.gz] [--include-discussions]

TXT;
}

/**
 * @param array<int, mixed> $arguments
 */
function parseStorageOverride(array $arguments): ?string
{
    foreach ($arguments as $argument) {
        if (!is_string($argument) || !str_starts_with($argument, '--storage=')) {
            continue;
        }

        $value = strtolower(trim((string) substr($argument, 10)));
        if (!in_array($value, ['json', 'sql', 'dual-write'], true)) {
            throw new RuntimeException(sprintf(
                'Valeur --storage invalide: %s (attendu: json|sql|dual-write).',
                $value
            ));
        }

        return $value;
    }

    return null;
}

function applyEditorialStorageOverride(string $storageMode): void
{
    global $appConfig;

    if (!is_array($appConfig)) {
        return;
    }

    if (!isset($appConfig['editorial']) || !is_array($appConfig['editorial'])) {
        $appConfig['editorial'] = [];
    }
    if (!isset($appConfig['blog']) || !is_array($appConfig['blog'])) {
        $appConfig['blog'] = [];
    }

    $appConfig['editorial']['storage'] = $storageMode;
    $appConfig['blog']['storage'] = $storageMode;
}

function backupEditorial(?string $outputPath, bool $includeDiscussions = false): string
{
    $pageRepository = page_repository(pages_data_path());
    $navigationRepository = navigation_repository(menus_data_path());
    $blogRepository = blog_repository();

    $payload = [
        'meta' => [
            'schemaVersion' => 1,
            'generatedAt' => date('c'),
            'storageMode' => editorial_storage_mode(),
            'paths' => [
                'pages' => pages_data_path(),
                'menus' => menus_data_path(),
                'blog' => blog_data_dir(),
                'discussions' => blog_discussions_data_dir(),
            ],
            'scope' => array_values(array_filter([
                'pages',
                'navigation',
                'blog',
                'tiles',
                $includeDiscussions ? 'discussions' : null,
            ])),
        ],
        'pages' => $pageRepository->registry(),
        'navigation' => $navigationRepository->loadCanonical(),
        'blog' => [
            'articles' => $blogRepository->allArticles(),
        ],
        'tiles' => exportTileConfiguration(),
    ];

    if ($includeDiscussions) {
        $payload['discussions'] = [
            'threads' => exportDiscussionThreads(blog_discussion_repository()),
        ];
    }

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        throw new RuntimeException('Impossible d’encoder le backup éditorial en JSON.');
    }

    $targetPath = resolveBackupPath($outputPath);
    $targetDirectory = dirname($targetPath);

    if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
        throw new RuntimeException(sprintf('Impossible de créer le dossier de backup: %s', $targetDirectory));
    }

    if (file_put_contents($targetPath, $json) === false) {
        throw new RuntimeException(sprintf('Impossible d’écrire le backup: %s', $targetPath));
    }

    return $targetPath;
}

/**
 * @return array{pages: int, navigationLocations: int, articles: int, tileGroups: int, tilePlacements: int, discussionThreads: int}
 */
function restoreEditorial(string $backupPath, bool $includeDiscussions = false, bool $allowDelete = false): array
{
    $decoded = readBackupFile($backupPath);
    $currentPayload = buildCurrentEditorialPayload($includeDiscussions);
    $diff = diffEditorialPayloads($decoded, $currentPayload, $includeDiscussions);

    if ($diff['deleteCount'] > 0 && !$allowDelete) {
        writeEditorialDiff($diff);
        throw new RuntimeException(
            'Restauration refusée: des suppressions éditoriales sont détectées. Relancez avec --allow-delete après vérification.'
        );
    }

    $pageRegistry = is_array($decoded['pages'] ?? null) ? $decoded['pages'] : ['pages' => []];
    $incomingPages = is_array($pageRegistry['pages'] ?? null) ? $pageRegistry['pages'] : [];

    $pageRepository = page_repository(pages_data_path());
    $existingPageSlugs = [];

    foreach ($pageRepository->all() as $page) {
        $slug = trim((string) ($page['slug'] ?? ''));
        if ($slug !== '') {
            $existingPageSlugs[$slug] = true;
        }
    }

    $incomingPageSlugs = [];
    $incomingPagesBySlug = [];
    $savedPages = 0;

    foreach ($incomingPages as $page) {
        if (!is_array($page)) {
            continue;
        }

        $slug = trim((string) ($page['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }

        $incomingPageSlugs[$slug] = true;
        $incomingPagesBySlug[$slug] = $page;
    }

    $failedPages = [];

    foreach ($incomingPagesBySlug as $slug => $page) {
        if ($pageRepository->savePage($page, $slug)) {
            $savedPages++;
        } else {
            $failedPages[$slug] = $page;
        }
    }

    foreach (array_keys($existingPageSlugs) as $existingSlug) {
        if (!isset($incomingPageSlugs[$existingSlug])) {
            $pageRepository->deletePage($existingSlug);
        }
    }

    foreach ($failedPages as $slug => $page) {
        if ($pageRepository->findBySlug($slug) !== null) {
            unset($failedPages[$slug]);
            continue;
        }

        if ($pageRepository->savePage($page, $slug)) {
            $savedPages++;
            unset($failedPages[$slug]);
        }
    }

    if ($failedPages !== []) {
        throw new RuntimeException(sprintf(
            'Restauration incomplète: page(s) non restaurée(s): %s.',
            implode(', ', array_keys($failedPages))
        ));
    }

    pages_cache_clear();

    $navigation = is_array($decoded['navigation'] ?? null) ? $decoded['navigation'] : [];
    $navigationRepository = navigation_repository(menus_data_path());
    if ($navigation !== []) {
        $navigationRepository->saveCanonical($navigation);
    }

    $navigationLocations = is_array($navigation['locations'] ?? null) ? count($navigation['locations']) : 0;

    $blogPayload = is_array($decoded['blog'] ?? null) ? $decoded['blog'] : [];
    $incomingArticles = is_array($blogPayload['articles'] ?? null) ? $blogPayload['articles'] : [];
    $blogRepository = blog_repository();

    $existingArticleKeys = [];
    foreach ($blogRepository->allArticles() as $article) {
        $slug = trim((string) ($article['slug'] ?? ''));
        $language = trim((string) ($article['lang'] ?? 'fr'));
        if ($slug === '') {
            continue;
        }

        $existingArticleKeys[articleKey($slug, $language)] = [$slug, $language];
    }

    $incomingArticleKeys = [];
    $savedArticles = 0;

    foreach ($incomingArticles as $article) {
        if (!is_array($article)) {
            continue;
        }

        $slug = trim((string) ($article['slug'] ?? ''));
        $language = trim((string) ($article['lang'] ?? 'fr'));
        if ($slug === '') {
            continue;
        }

        $incomingArticleKeys[articleKey($slug, $language)] = true;
        $blogRepository->save($article, $slug, $language);
        $savedArticles++;
    }

    foreach ($existingArticleKeys as $key => $coordinates) {
        if (isset($incomingArticleKeys[$key])) {
            continue;
        }

        [$slug, $language] = $coordinates;
        $blogRepository->delete($slug, $language);
    }

    $tileSummary = ['groups' => 0, 'placements' => 0];
    if (array_key_exists('tiles', $decoded) && is_array($decoded['tiles'])) {
        $tileSummary = restoreTileConfiguration($decoded['tiles']);
    }

    $restoredThreads = 0;
    if ($includeDiscussions && is_array($decoded['discussions'] ?? null)) {
        $discussionsPayload = $decoded['discussions'];
        $incomingThreads = is_array($discussionsPayload['threads'] ?? null) ? $discussionsPayload['threads'] : [];
        $restoredThreads = restoreDiscussionThreads(blog_discussion_repository(), $blogRepository, $incomingThreads);
    }

    return [
        'pages' => $savedPages,
        'navigationLocations' => $navigationLocations,
        'articles' => $savedArticles,
        'tileGroups' => $tileSummary['groups'],
        'tilePlacements' => $tileSummary['placements'],
        'discussionThreads' => $restoredThreads,
    ];
}

/**
 * @return array<string, mixed>
 */
function readBackupFile(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException(sprintf('Backup introuvable ou illisible: %s', $path));
    }

    $raw = file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        throw new RuntimeException(sprintf('Backup vide: %s', $path));
    }
    if (str_ends_with($path, '.gz')) {
        $decodedGzip = gzdecode($raw);
        if (!is_string($decodedGzip) || trim($decodedGzip) === '') {
            throw new RuntimeException(sprintf('Backup gzip illisible: %s', $path));
        }
        $raw = $decodedGzip;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException(sprintf('Backup JSON invalide: %s', $path));
    }

    return $decoded;
}

function resolveBackupPath(?string $outputPath): string
{
    if (is_string($outputPath) && trim($outputPath) !== '') {
        return $outputPath;
    }

    return ROOT_PATH . '/data/backups/editorial-' . date('Ymd-His') . '.json';
}

/**
 * @return array<string, mixed>
 */
function buildCurrentEditorialPayload(bool $includeDiscussions): array
{
    $pageRepository = page_repository(pages_data_path());
    $navigationRepository = navigation_repository(menus_data_path());
    $blogRepository = blog_repository();

    $payload = [
        'pages' => $pageRepository->registry(),
        'navigation' => $navigationRepository->loadCanonical(),
        'blog' => [
            'articles' => $blogRepository->allArticles(),
        ],
        'tiles' => exportTileConfiguration(),
    ];

    if ($includeDiscussions) {
        $payload['discussions'] = [
            'threads' => exportDiscussionThreads(blog_discussion_repository()),
        ];
    }

    return $payload;
}

/**
 * @return array{groups: array<int, array<string, mixed>>, placements: array<int, array<string, mixed>>}
 */
function exportTileConfiguration(): array
{
    $database = editorial_database();
    $database->ensureReady();
    $pdo = $database->pdo();

    $groups = fetchSqlRows($pdo, sprintf(
        'SELECT `id`, `name`, `theme`
         FROM `%s`
         ORDER BY `id` ASC',
        $database->table('tile_groups')
    ));

    $itemsByGroup = [];
    $itemsById = [];
    foreach (fetchSqlRows($pdo, sprintf(
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
            `is_visible`,
            `open_in_new_tab`
         FROM `%s`
         ORDER BY `group_id` ASC, `sort_order` ASC, `id` ASC',
        $database->table('tile_group_items')
    )) as $row) {
        $item = normalizeTileItemBackupRow($row);
        $itemsByGroup[(int) $item['group_id']][] = $item;
        $itemsById[(int) $item['id']] = $item;
    }

    foreach (fetchSqlRows($pdo, sprintf(
        'SELECT `item_id`, `locale`, `label_text`, `accessibility_alt`, `accessibility_title`
         FROM `%s`
         ORDER BY `item_id` ASC, `locale` ASC',
        $database->table('tile_group_item_translations')
    )) as $row) {
        $itemId = (int) ($row['item_id'] ?? 0);
        if ($itemId <= 0 || !isset($itemsById[$itemId])) {
            continue;
        }

        $itemsById[$itemId]['translations'][] = [
            'locale' => trim((string) ($row['locale'] ?? '')),
            'label' => nullableBackupString($row['label_text'] ?? null),
            'alt' => nullableBackupString($row['accessibility_alt'] ?? null),
            'title' => nullableBackupString($row['accessibility_title'] ?? null),
        ];
    }

    foreach ($itemsById as $itemId => $item) {
        $groupId = (int) ($item['group_id'] ?? 0);
        foreach ($itemsByGroup[$groupId] ?? [] as $index => $groupItem) {
            if ((int) ($groupItem['id'] ?? 0) === $itemId) {
                $itemsByGroup[$groupId][$index] = $item;
                break;
            }
        }
    }

    $normalizedGroups = [];
    foreach ($groups as $row) {
        $groupId = (int) ($row['id'] ?? 0);
        if ($groupId <= 0) {
            continue;
        }

        $normalizedGroups[] = [
            'id' => $groupId,
            'name' => trim((string) ($row['name'] ?? '')),
            'theme' => trim((string) ($row['theme'] ?? 'windows10-classic')),
            'items' => $itemsByGroup[$groupId] ?? [],
        ];
    }

    $placements = [];
    $placementsById = [];
    foreach (fetchSqlRows($pdo, sprintf(
        'SELECT `id`, `page_slug`, `region_key`, `group_id`, `sort_order`
         FROM `%s`
         ORDER BY `page_slug` ASC, `region_key` ASC, `sort_order` ASC, `id` ASC',
        $database->table('page_tile_placements')
    )) as $row) {
        $placement = [
            'id' => (int) ($row['id'] ?? 0),
            'page_slug' => trim((string) ($row['page_slug'] ?? '')),
            'region_key' => trim((string) ($row['region_key'] ?? 'after_body')),
            'group_id' => (int) ($row['group_id'] ?? 0),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'overrides' => [],
        ];
        if ($placement['id'] <= 0 || $placement['page_slug'] === '' || $placement['group_id'] <= 0) {
            continue;
        }

        $placements[] = $placement;
        $placementsById[(int) $placement['id']] = count($placements) - 1;
    }

    foreach (fetchSqlRows($pdo, sprintf(
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
         ORDER BY `placement_id` ASC, `group_item_uid` ASC',
        $database->table('page_tile_item_overrides')
    )) as $row) {
        $placementId = (int) ($row['placement_id'] ?? 0);
        if (!isset($placementsById[$placementId])) {
            continue;
        }

        $placements[$placementsById[$placementId]]['overrides'][] = [
            'placement_id' => $placementId,
            'group_item_uid' => trim((string) ($row['group_item_uid'] ?? '')),
            'is_visible' => nullableBackupBoolInt($row['is_visible'] ?? null),
            'target_type' => nullableBackupString($row['target_type'] ?? null),
            'target_page_slug' => nullableBackupString($row['target_page_slug'] ?? null),
            'target_route' => nullableBackupString($row['target_route'] ?? null),
            'target_url' => nullableBackupString($row['target_url'] ?? null),
            'open_in_new_tab' => nullableBackupBoolInt($row['open_in_new_tab'] ?? null),
            'label_translations_json' => nullableBackupString($row['label_translations_json'] ?? null),
            'alt_translations_json' => nullableBackupString($row['alt_translations_json'] ?? null),
            'title_translations_json' => nullableBackupString($row['title_translations_json'] ?? null),
        ];
    }

    return [
        'groups' => $normalizedGroups,
        'placements' => $placements,
    ];
}

/**
 * @param array<string, mixed> $tiles
 * @return array{groups: int, placements: int}
 */
function restoreTileConfiguration(array $tiles): array
{
    $groups = is_array($tiles['groups'] ?? null) ? array_values(array_filter($tiles['groups'], 'is_array')) : [];
    $placements = is_array($tiles['placements'] ?? null) ? array_values(array_filter($tiles['placements'], 'is_array')) : [];

    $database = editorial_database();
    $database->ensureReady();
    $pdo = $database->pdo();
    $transactionStarted = $pdo->beginTransaction();

    try {
        foreach ([
            'page_tile_item_overrides',
            'page_tile_placements',
            'tile_group_item_translations',
            'tile_group_items',
            'tile_groups',
        ] as $tableName) {
            $pdo->exec(sprintf('DELETE FROM `%s`', $database->table($tableName)));
        }

        $insertGroup = $pdo->prepare(sprintf(
            'INSERT INTO `%s` (`id`, `name`, `theme`) VALUES (:id, :name, :theme)',
            $database->table('tile_groups')
        ));
        $insertItem = $pdo->prepare(sprintf(
            'INSERT INTO `%s`
                (`id`, `group_id`, `item_uid`, `sort_order`, `tile_size`, `color_token`, `image_src`, `image_width`, `image_height`, `target_type`, `target_page_slug`, `target_route`, `target_url`, `is_visible`, `open_in_new_tab`)
             VALUES
                (:id, :group_id, :item_uid, :sort_order, :tile_size, :color_token, :image_src, :image_width, :image_height, :target_type, :target_page_slug, :target_route, :target_url, :is_visible, :open_in_new_tab)',
            $database->table('tile_group_items')
        ));
        $insertTranslation = $pdo->prepare(sprintf(
            'INSERT INTO `%s`
                (`item_id`, `locale`, `label_text`, `accessibility_alt`, `accessibility_title`)
             VALUES
                (:item_id, :locale, :label_text, :accessibility_alt, :accessibility_title)',
            $database->table('tile_group_item_translations')
        ));
        $insertPlacement = $pdo->prepare(sprintf(
            'INSERT INTO `%s` (`id`, `page_slug`, `region_key`, `group_id`, `sort_order`)
             VALUES (:id, :page_slug, :region_key, :group_id, :sort_order)',
            $database->table('page_tile_placements')
        ));
        $insertOverride = $pdo->prepare(sprintf(
            'INSERT INTO `%s`
                (`placement_id`, `group_item_uid`, `is_visible`, `target_type`, `target_page_slug`, `target_route`, `target_url`, `open_in_new_tab`, `label_translations_json`, `alt_translations_json`, `title_translations_json`)
             VALUES
                (:placement_id, :group_item_uid, :is_visible, :target_type, :target_page_slug, :target_route, :target_url, :open_in_new_tab, :label_translations_json, :alt_translations_json, :title_translations_json)',
            $database->table('page_tile_item_overrides')
        ));

        $groupIds = [];
        $itemIds = [];
        $placementIds = [];
        $groupCount = 0;
        $placementCount = 0;

        foreach ($groups as $group) {
            $groupId = (int) ($group['id'] ?? 0);
            $name = trim((string) ($group['name'] ?? ''));
            if ($groupId <= 0 || $name === '') {
                continue;
            }

            $insertGroup->execute([
                'id' => $groupId,
                'name' => $name,
                'theme' => trim((string) ($group['theme'] ?? 'windows10-classic')) ?: 'windows10-classic',
            ]);
            $groupIds[$groupId] = true;
            $groupCount++;

            $items = is_array($group['items'] ?? null) ? array_values(array_filter($group['items'], 'is_array')) : [];
            foreach ($items as $item) {
                $itemId = (int) ($item['id'] ?? 0);
                $itemUid = trim((string) ($item['item_uid'] ?? ''));
                if ($itemId <= 0 || $itemUid === '') {
                    continue;
                }

                $insertItem->execute([
                    'id' => $itemId,
                    'group_id' => $groupId,
                    'item_uid' => $itemUid,
                    'sort_order' => (int) ($item['sort_order'] ?? 0),
                    'tile_size' => trim((string) ($item['tile_size'] ?? 'rectangle')) ?: 'rectangle',
                    'color_token' => trim((string) ($item['color_token'] ?? 'bleu')) ?: 'bleu',
                    'image_src' => nullableBackupString($item['image_src'] ?? null),
                    'image_width' => nullablePositiveInt($item['image_width'] ?? null),
                    'image_height' => nullablePositiveInt($item['image_height'] ?? null),
                    'target_type' => trim((string) ($item['target_type'] ?? 'page')) ?: 'page',
                    'target_page_slug' => nullableBackupString($item['target_page_slug'] ?? null),
                    'target_route' => nullableBackupString($item['target_route'] ?? null),
                    'target_url' => nullableBackupString($item['target_url'] ?? null),
                    'is_visible' => nullableBackupBoolInt($item['is_visible'] ?? null) ?? 1,
                    'open_in_new_tab' => !empty($item['open_in_new_tab']) ? 1 : 0,
                ]);
                $itemIds[$itemId] = true;

                $translations = is_array($item['translations'] ?? null) ? array_values(array_filter($item['translations'], 'is_array')) : [];
                foreach ($translations as $translation) {
                    $locale = trim((string) ($translation['locale'] ?? ''));
                    if ($locale === '') {
                        continue;
                    }

                    $insertTranslation->execute([
                        'item_id' => $itemId,
                        'locale' => $locale,
                        'label_text' => nullableBackupString($translation['label'] ?? null),
                        'accessibility_alt' => nullableBackupString($translation['alt'] ?? null),
                        'accessibility_title' => nullableBackupString($translation['title'] ?? null),
                    ]);
                }
            }
        }

        foreach ($placements as $placement) {
            $placementId = (int) ($placement['id'] ?? 0);
            $groupId = (int) ($placement['group_id'] ?? 0);
            $pageSlug = trim((string) ($placement['page_slug'] ?? ''));
            if ($placementId <= 0 || $groupId <= 0 || !isset($groupIds[$groupId]) || $pageSlug === '') {
                continue;
            }

            $insertPlacement->execute([
                'id' => $placementId,
                'page_slug' => $pageSlug,
                'region_key' => trim((string) ($placement['region_key'] ?? 'after_body')) ?: 'after_body',
                'group_id' => $groupId,
                'sort_order' => (int) ($placement['sort_order'] ?? 0),
            ]);
            $placementIds[$placementId] = true;
            $placementCount++;

            $overrides = is_array($placement['overrides'] ?? null) ? array_values(array_filter($placement['overrides'], 'is_array')) : [];
            foreach ($overrides as $override) {
                $groupItemUid = trim((string) ($override['group_item_uid'] ?? ''));
                if ($groupItemUid === '') {
                    continue;
                }

                $insertOverride->execute([
                    'placement_id' => $placementId,
                    'group_item_uid' => $groupItemUid,
                    'is_visible' => nullableBackupBoolInt($override['is_visible'] ?? null),
                    'target_type' => nullableBackupString($override['target_type'] ?? null),
                    'target_page_slug' => nullableBackupString($override['target_page_slug'] ?? null),
                    'target_route' => nullableBackupString($override['target_route'] ?? null),
                    'target_url' => nullableBackupString($override['target_url'] ?? null),
                    'open_in_new_tab' => nullableBackupBoolInt($override['open_in_new_tab'] ?? null),
                    'label_translations_json' => nullableBackupString($override['label_translations_json'] ?? null),
                    'alt_translations_json' => nullableBackupString($override['alt_translations_json'] ?? null),
                    'title_translations_json' => nullableBackupString($override['title_translations_json'] ?? null),
                ]);
            }
        }

        if ($transactionStarted && $pdo->inTransaction()) {
            $pdo->commit();
        }
        if (function_exists('tile_repository_cache_clear')) {
            tile_repository_cache_clear();
        }

        return [
            'groups' => $groupCount,
            'placements' => $placementCount,
        ];
    } catch (Throwable $exception) {
        if ($transactionStarted && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function fetchSqlRows(PDO $pdo, string $sql): array
{
    $statement = $pdo->query($sql);
    if (!$statement instanceof PDOStatement) {
        return [];
    }

    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function normalizeTileItemBackupRow(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'group_id' => (int) ($row['group_id'] ?? 0),
        'item_uid' => trim((string) ($row['item_uid'] ?? '')),
        'sort_order' => (int) ($row['sort_order'] ?? 0),
        'tile_size' => trim((string) ($row['tile_size'] ?? 'rectangle')),
        'color_token' => trim((string) ($row['color_token'] ?? 'bleu')),
        'image_src' => nullableBackupString($row['image_src'] ?? null),
        'image_width' => nullablePositiveInt($row['image_width'] ?? null),
        'image_height' => nullablePositiveInt($row['image_height'] ?? null),
        'target_type' => trim((string) ($row['target_type'] ?? 'page')),
        'target_page_slug' => nullableBackupString($row['target_page_slug'] ?? null),
        'target_route' => nullableBackupString($row['target_route'] ?? null),
        'target_url' => nullableBackupString($row['target_url'] ?? null),
        'is_visible' => !array_key_exists('is_visible', $row) || !empty($row['is_visible']),
        'open_in_new_tab' => !empty($row['open_in_new_tab']),
        'translations' => [],
    ];
}

function nullableBackupString(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    $normalized = trim((string) $value);

    return $normalized !== '' ? $normalized : null;
}

function nullablePositiveInt(mixed $value): ?int
{
    if (!is_numeric($value)) {
        return null;
    }

    $normalized = (int) $value;

    return $normalized > 0 ? $normalized : null;
}

function nullableBackupBoolInt(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    return !empty($value) ? 1 : 0;
}

/**
 * @param array<string, mixed> $source
 * @param array<string, mixed> $target
 * @return array{changes: array<string, array{created: array<int, string>, updated: array<int, string>, deleted: array<int, string>}>, createCount: int, updateCount: int, deleteCount: int}
 */
function diffEditorialPayloads(array $source, array $target, bool $includeDiscussions = false): array
{
    $sourceItems = comparableEditorialItems($source, $includeDiscussions);
    $targetItems = comparableEditorialItems($target, $includeDiscussions);
    $changes = [];
    $createCount = 0;
    $updateCount = 0;
    $deleteCount = 0;

    foreach ($sourceItems as $scope => $items) {
        $targetScopeItems = $targetItems[$scope] ?? [];
        $created = [];
        $updated = [];
        $deleted = [];

        foreach ($items as $key => $hash) {
            if (!array_key_exists($key, $targetScopeItems)) {
                $created[] = $key;
                continue;
            }

            if ($targetScopeItems[$key] !== $hash) {
                $updated[] = $key;
            }
        }

        foreach ($targetScopeItems as $key => $_hash) {
            if (!array_key_exists($key, $items)) {
                $deleted[] = $key;
            }
        }

        sort($created, SORT_STRING);
        sort($updated, SORT_STRING);
        sort($deleted, SORT_STRING);

        if ($created !== [] || $updated !== [] || $deleted !== []) {
            $changes[$scope] = [
                'created' => $created,
                'updated' => $updated,
                'deleted' => $deleted,
            ];
            $createCount += count($created);
            $updateCount += count($updated);
            $deleteCount += count($deleted);
        }
    }

    ksort($changes);

    return [
        'changes' => $changes,
        'createCount' => $createCount,
        'updateCount' => $updateCount,
        'deleteCount' => $deleteCount,
    ];
}

/**
 * @param array{changes: array<string, array{created: array<int, string>, updated: array<int, string>, deleted: array<int, string>}>, createCount: int, updateCount: int, deleteCount: int} $diff
 */
function writeEditorialDiff(array $diff): void
{
    fwrite(STDOUT, sprintf(
        "editorial_diff created=%d updated=%d deleted=%d\n",
        $diff['createCount'],
        $diff['updateCount'],
        $diff['deleteCount']
    ));

    foreach ($diff['changes'] as $scope => $changes) {
        fwrite(STDOUT, sprintf(
            "- %s: +%d ~%d -%d\n",
            $scope,
            count($changes['created']),
            count($changes['updated']),
            count($changes['deleted'])
        ));

        foreach (['created' => '+', 'updated' => '~', 'deleted' => '-'] as $kind => $prefix) {
            foreach (array_slice($changes[$kind], 0, 20) as $key) {
                fwrite(STDOUT, sprintf("  %s %s\n", $prefix, $key));
            }
            if (count($changes[$kind]) > 20) {
                fwrite(STDOUT, sprintf("  ... %d autre(s)\n", count($changes[$kind]) - 20));
            }
        }
    }
}

function hashEditorialPayload(array $payload, bool $includeDiscussions = false): string
{
    return hash(
        'sha256',
        json_encode(comparableEditorialItems($payload, $includeDiscussions), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ?: ''
    );
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, array<string, string>>
 */
function comparableEditorialItems(array $payload, bool $includeDiscussions = false): array
{
    $items = [];

    if (array_key_exists('pages', $payload)) {
        $items['pages'] = [];
        $pages = is_array($payload['pages']['pages'] ?? null) ? $payload['pages']['pages'] : [];
        foreach ($pages as $page) {
            if (!is_array($page)) {
                continue;
            }
            $slug = normalizeBackupSlug((string) ($page['slug'] ?? ''));
            if ($slug !== '') {
                $items['pages'][$slug] = hashComparableItem($page);
            }
        }
        ksort($items['pages'], SORT_STRING);
    }

    if (array_key_exists('navigation', $payload)) {
        $items['navigation'] = [];
        $locations = is_array($payload['navigation']['locations'] ?? null) ? $payload['navigation']['locations'] : [];
        foreach ($locations as $locationKey => $location) {
            if (!is_string($locationKey)) {
                continue;
            }
            $items['navigation'][$locationKey] = hashComparableItem($location);
        }
        ksort($items['navigation'], SORT_STRING);
    }

    if (array_key_exists('blog', $payload)) {
        $items['blog_articles'] = [];
        $articles = is_array($payload['blog']['articles'] ?? null) ? $payload['blog']['articles'] : [];
        foreach ($articles as $article) {
            if (!is_array($article)) {
                continue;
            }
            $slug = normalizeBackupSlug((string) ($article['slug'] ?? ''));
            $language = normalizeBackupLanguage((string) ($article['lang'] ?? 'fr'));
            if ($slug !== '') {
                $items['blog_articles'][articleKey($slug, $language)] = hashComparableItem($article);
            }
        }
        ksort($items['blog_articles'], SORT_STRING);
    }

    if (array_key_exists('tiles', $payload)) {
        $tiles = is_array($payload['tiles'] ?? null) ? $payload['tiles'] : [];
        $items['tile_groups'] = [];
        foreach (is_array($tiles['groups'] ?? null) ? $tiles['groups'] : [] as $group) {
            if (!is_array($group)) {
                continue;
            }
            $groupId = (int) ($group['id'] ?? 0);
            if ($groupId > 0) {
                $items['tile_groups'][(string) $groupId] = hashComparableItem($group);
            }
        }
        ksort($items['tile_groups'], SORT_STRING);

        $items['tile_placements'] = [];
        foreach (is_array($tiles['placements'] ?? null) ? $tiles['placements'] : [] as $placement) {
            if (!is_array($placement)) {
                continue;
            }
            $pageSlug = normalizeBackupSlug((string) ($placement['page_slug'] ?? ''));
            $regionKey = trim((string) ($placement['region_key'] ?? 'after_body'));
            $groupId = (int) ($placement['group_id'] ?? 0);
            $sortOrder = (int) ($placement['sort_order'] ?? 0);
            if ($pageSlug !== '' && $regionKey !== '' && $groupId > 0) {
                $key = implode('|', [$pageSlug, $regionKey, (string) $groupId, (string) $sortOrder]);
                $items['tile_placements'][$key] = hashComparableItem(comparableTilePlacement($placement));
            }
        }
        ksort($items['tile_placements'], SORT_STRING);
    }

    if ($includeDiscussions && array_key_exists('discussions', $payload)) {
        $items['discussions'] = [];
        $threads = is_array($payload['discussions']['threads'] ?? null) ? $payload['discussions']['threads'] : [];
        foreach ($threads as $thread) {
            if (!is_array($thread)) {
                continue;
            }
            $article = is_array($thread['article'] ?? null) ? $thread['article'] : [];
            $slug = normalizeBackupSlug((string) ($article['slug'] ?? ''));
            $language = normalizeBackupLanguage((string) ($article['lang'] ?? 'fr'));
            if ($slug !== '') {
                $items['discussions'][articleKey($slug, $language)] = hashComparableItem($thread);
            }
        }
        ksort($items['discussions'], SORT_STRING);
    }

    ksort($items, SORT_STRING);

    return $items;
}

function hashComparableItem(mixed $item): string
{
    return hash('sha256', json_encode(sortComparableValue($item), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
}

/**
 * @param array<string, mixed> $placement
 * @return array<string, mixed>
 */
function comparableTilePlacement(array $placement): array
{
    unset($placement['id']);

    return $placement;
}

function sortComparableValue(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    $isList = array_keys($value) === range(0, count($value) - 1);
    if ($isList) {
        $items = array_map('sortComparableValue', $value);
        usort($items, static function (mixed $left, mixed $right): int {
            return strcmp(
                json_encode($left, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
                json_encode($right, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: ''
            );
        });

        return $items;
    }

    ksort($value, SORT_STRING);
    foreach ($value as $key => $child) {
        $value[$key] = sortComparableValue($child);
    }

    return $value;
}

/**
 * @return array<int, array<string, mixed>>
 */
function exportDiscussionThreads(BlogDiscussionRepositoryInterface $discussionRepository): array
{
    $threadsByKey = [];

    foreach ($discussionRepository->all() as $row) {
        if (!is_array($row)) {
            continue;
        }

        $slug = normalizeBackupSlug((string) ($row['article_slug'] ?? ''));
        $language = normalizeBackupLanguage((string) ($row['article_lang'] ?? 'fr'));
        if ($slug === '') {
            continue;
        }

        $key = articleKey($slug, $language);
        if (!isset($threadsByKey[$key])) {
            $threadsByKey[$key] = [
                'article' => [
                    'slug' => $slug,
                    'lang' => $language,
                ],
                'items' => [],
            ];
        }

        $threadsByKey[$key]['items'][] = normalizeBackupDiscussionItem($row);
    }

    foreach ($threadsByKey as &$thread) {
        usort(
            $thread['items'],
            static fn (array $left, array $right): int => strcmp(
                (string) ($left['created_at'] ?? ''),
                (string) ($right['created_at'] ?? '')
            )
        );
    }
    unset($thread);

    ksort($threadsByKey, SORT_STRING);

    return array_values($threadsByKey);
}

/**
 * @param array<int, array<string, mixed>> $threads
 */
function restoreDiscussionThreads(
    BlogDiscussionRepositoryInterface $discussionRepository,
    BlogRepositoryInterface $blogRepository,
    array $threads
): int {
    $existingKeys = [];
    foreach ($discussionRepository->all() as $row) {
        if (!is_array($row)) {
            continue;
        }

        $slug = normalizeBackupSlug((string) ($row['article_slug'] ?? ''));
        $language = normalizeBackupLanguage((string) ($row['article_lang'] ?? 'fr'));
        if ($slug === '') {
            continue;
        }

        $existingKeys[articleKey($slug, $language)] = [$slug, $language];
    }

    foreach ($existingKeys as [$slug, $language]) {
        $discussionRepository->deleteThreadForArticle($slug, $language);
    }

    $restored = 0;

    foreach ($threads as $thread) {
        if (!is_array($thread)) {
            continue;
        }

        $article = is_array($thread['article'] ?? null) ? $thread['article'] : [];
        $slug = normalizeBackupSlug((string) ($article['slug'] ?? ''));
        $language = normalizeBackupLanguage((string) ($article['lang'] ?? 'fr'));
        if ($slug === '' || !is_array($blogRepository->find($slug, $language))) {
            continue;
        }

        $items = is_array($thread['items'] ?? null) ? array_values(array_filter($thread['items'], 'is_array')) : [];
        if ($items === []) {
            continue;
        }

        foreach ($items as $item) {
            $discussionRepository->submitPending($slug, $language, normalizeBackupDiscussionItem($item));
        }

        $restored++;
    }

    return $restored;
}

/**
 * @param array<string, mixed> $item
 * @return array<string, mixed>
 */
function normalizeBackupDiscussionItem(array $item): array
{
    $status = strtolower(trim((string) ($item['status'] ?? 'pending')));
    if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $status = 'pending';
    }

    $createdAt = trim((string) ($item['created_at'] ?? date('c')));
    if ($createdAt === '') {
        $createdAt = date('c');
    }

    $updatedAt = trim((string) ($item['updated_at'] ?? $createdAt));
    if ($updatedAt === '') {
        $updatedAt = $createdAt;
    }

    $moderatedAt = trim((string) ($item['moderated_at'] ?? ''));
    $moderatedBy = trim((string) ($item['moderated_by'] ?? ''));
    if ($status === 'pending') {
        $moderatedAt = '';
        $moderatedBy = '';
    }

    return [
        'id' => trim((string) ($item['id'] ?? '')),
        'author' => trim((string) ($item['author'] ?? '')),
        'email' => trim((string) ($item['email'] ?? '')),
        'content' => trim((string) ($item['content'] ?? '')),
        'status' => $status,
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
        'moderated_at' => $moderatedAt !== '' ? $moderatedAt : null,
        'moderated_by' => $moderatedBy !== '' ? $moderatedBy : null,
        'ip_hash' => trim((string) ($item['ip_hash'] ?? '')),
        'user_agent_hash' => trim((string) ($item['user_agent_hash'] ?? '')),
    ];
}

function normalizeBackupSlug(string $slug): string
{
    $normalized = strtolower(trim($slug));
    $normalized = preg_replace('/[^a-z0-9-]+/i', '-', $normalized) ?? '';

    return trim($normalized, '-');
}

function normalizeBackupLanguage(string $language): string
{
    $normalized = strtolower(trim($language));
    $normalized = preg_replace('/[^a-z]/', '', $normalized) ?? '';

    return $normalized !== '' ? $normalized : 'fr';
}

function articleKey(string $slug, string $language): string
{
    return strtolower(trim($slug)) . '|' . strtolower(trim($language));
}
