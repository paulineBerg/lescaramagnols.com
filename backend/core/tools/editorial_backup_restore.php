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
    fwrite(STDERR, usage());
    exit(1);
}

try {
    $storageOverride = parseStorageOverride(array_slice($argv, 2));
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    fwrite(STDERR, usage());
    exit(1);
}

if ($storageOverride !== null) {
    applyEditorialStorageOverride($storageOverride);
}

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

    $backupPath = backupEditorial($outputPath);
    fwrite(STDOUT, sprintf("Backup éditorial créé : %s\n", $backupPath));
    exit(0);
}

if ($command === 'restore') {
    $backupPath = $argv[2] ?? '';
    $force = in_array('--force', $argv, true);

    if (!is_string($backupPath) || trim($backupPath) === '') {
        fwrite(STDERR, "Chemin du backup manquant.\n");
        fwrite(STDERR, usage());
        exit(1);
    }

    if (!$force) {
        fwrite(STDERR, "La restauration est destructive. Relancez avec --force.\n");
        exit(1);
    }

    $summary = restoreEditorial($backupPath);
    fwrite(
        STDOUT,
        sprintf(
            "Restauration terminée : %d page(s), %d emplacement(s) de navigation, %d article(s), %d fil(s) de discussion.\n",
            $summary['pages'],
            $summary['navigationLocations'],
            $summary['articles'],
            $summary['discussionThreads']
        )
    );
    exit(0);
}

fwrite(STDERR, sprintf("Commande inconnue: %s\n", $command));
fwrite(STDERR, usage());
exit(1);

function usage(): string
{
    return <<<TXT
Usage:
  php core/tools/editorial_backup_restore.php backup [--output=/chemin/backup.json] [--storage=json|sql|dual-write]
  php core/tools/editorial_backup_restore.php restore /chemin/backup.json --force [--storage=json|sql|dual-write]

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

function backupEditorial(?string $outputPath): string
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
        ],
        'pages' => $pageRepository->registry(),
        'navigation' => $navigationRepository->loadCanonical(),
        'blog' => [
            'articles' => $blogRepository->allArticles(),
        ],
        'discussions' => [
            'threads' => exportDiscussionThreads(blog_discussion_repository()),
        ],
    ];

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
 * @return array{pages: int, navigationLocations: int, articles: int, discussionThreads: int}
 */
function restoreEditorial(string $backupPath): array
{
    $decoded = readBackupFile($backupPath);

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
        if ($pageRepository->savePage($page, $slug)) {
            $savedPages++;
        }
    }

    foreach (array_keys($existingPageSlugs) as $existingSlug) {
        if (!isset($incomingPageSlugs[$existingSlug])) {
            $pageRepository->deletePage($existingSlug);
        }
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

    $discussionsPayload = is_array($decoded['discussions'] ?? null) ? $decoded['discussions'] : [];
    $incomingThreads = is_array($discussionsPayload['threads'] ?? null) ? $discussionsPayload['threads'] : [];
    $restoredThreads = restoreDiscussionThreads(blog_discussion_repository(), $blogRepository, $incomingThreads);

    return [
        'pages' => $savedPages,
        'navigationLocations' => $navigationLocations,
        'articles' => $savedArticles,
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
