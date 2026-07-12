<?php

declare(strict_types=1);

use Caramagnols\Blog\JsonBlogDiscussionRepository;
use Caramagnols\Blog\JsonBlogRepository;
use Caramagnols\Blog\SqlBlogDiscussionRepository;
use Caramagnols\Blog\SqlBlogRepository;

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit être lancée en CLI.\n");
    exit(1);
}

$options = array_slice($argv, 1);
$importArticles = !in_array('--discussions-only', $options, true);
$importDiscussions = !in_array('--articles-only', $options, true);
$prune = !in_array('--no-prune', $options, true);

$sourceArticleRepository = new JsonBlogRepository(blog_data_dir());
$sourceDiscussionRepository = new JsonBlogDiscussionRepository(blog_discussions_data_dir());
$sqlArticleRepository = new SqlBlogRepository(editorial_database());
$sqlDiscussionRepository = new SqlBlogDiscussionRepository(editorial_database());

$importedArticles = 0;
$removedArticles = 0;
$importedDiscussions = 0;
$removedDiscussionThreads = 0;
$skippedOrphanDiscussions = 0;

if ($importArticles) {
    $incomingArticleKeys = [];

    foreach ($sourceArticleRepository->allArticles() as $article) {
        if (!is_array($article)) {
            continue;
        }

        $slug = normalize_slug((string) ($article['slug'] ?? ''));
        $language = normalize_language((string) ($article['lang'] ?? 'fr'));

        if ($slug === '') {
            continue;
        }

        $incomingArticleKeys[article_key($slug, $language)] = true;
        $sqlArticleRepository->save($article, $slug, $language);
        $importedArticles++;
    }

    if ($prune) {
        foreach ($sqlArticleRepository->allArticles() as $article) {
            if (!is_array($article)) {
                continue;
            }

            $slug = normalize_slug((string) ($article['slug'] ?? ''));
            $language = normalize_language((string) ($article['lang'] ?? 'fr'));
            if ($slug === '') {
                continue;
            }

            if (isset($incomingArticleKeys[article_key($slug, $language)])) {
                continue;
            }

            if ($sqlArticleRepository->delete($slug, $language)) {
                $removedArticles++;
            }
        }
    }
}

if ($importDiscussions) {
    $sourceRows = array_values(array_filter($sourceDiscussionRepository->all(), 'is_array'));

    if ($prune) {
        $existingThreadKeys = [];
        foreach ($sqlDiscussionRepository->all() as $row) {
            if (!is_array($row)) {
                continue;
            }

            $slug = normalize_slug((string) ($row['article_slug'] ?? ''));
            $language = normalize_language((string) ($row['article_lang'] ?? 'fr'));
            if ($slug === '') {
                continue;
            }

            $existingThreadKeys[article_key($slug, $language)] = [$slug, $language];
        }

        foreach ($existingThreadKeys as [$slug, $language]) {
            $removedDiscussionThreads += (int) ($sqlDiscussionRepository->deleteThreadForArticle($slug, $language) > 0);
        }
    }

    foreach ($sourceRows as $row) {
        $slug = normalize_slug((string) ($row['article_slug'] ?? ''));
        $language = normalize_language((string) ($row['article_lang'] ?? 'fr'));

        if ($slug === '') {
            continue;
        }

        if (!is_array($sqlArticleRepository->find($slug, $language))) {
            $skippedOrphanDiscussions++;
            continue;
        }

        $sqlDiscussionRepository->submitPending($slug, $language, [
            'id' => (string) ($row['id'] ?? ''),
            'author' => (string) ($row['author'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'content' => (string) ($row['content'] ?? ''),
            'status' => (string) ($row['status'] ?? 'pending'),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'moderated_at' => $row['moderated_at'] ?? null,
            'moderated_by' => $row['moderated_by'] ?? null,
            'ip_hash' => (string) ($row['ip_hash'] ?? ''),
            'user_agent_hash' => (string) ($row['user_agent_hash'] ?? ''),
        ]);
        $importedDiscussions++;
    }
}

fwrite(
    STDOUT,
    sprintf(
        "Import blog SQL terminé : %d article(s) importé(s), %d article(s) supprimé(s), %d discussion(s) importée(s), %d fil(s) discussion supprimé(s), %d discussion(s) orpheline(s) ignorée(s).\n",
        $importedArticles,
        $removedArticles,
        $importedDiscussions,
        $removedDiscussionThreads,
        $skippedOrphanDiscussions
    )
);

exit(0);

function normalize_slug(string $slug): string
{
    $normalized = strtolower(trim($slug));
    $normalized = preg_replace('/[^a-z0-9-]+/i', '-', $normalized) ?? '';

    return trim($normalized, '-');
}

function normalize_language(string $language): string
{
    $normalized = strtolower(trim($language));
    $normalized = preg_replace('/[^a-z]/', '', $normalized) ?? '';

    return $normalized !== '' ? $normalized : 'fr';
}

function article_key(string $slug, string $language): string
{
    return normalize_slug($slug) . '|' . normalize_language($language);
}
