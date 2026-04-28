<?php

declare(strict_types=1);

use Caramagnols\Blog\BlogTaxonomy;

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit etre executee en CLI.\n");
    exit(1);
}

$jsonOutput = in_array('--json', array_slice($argv, 1), true);
$taxonomy = BlogTaxonomy::fromDefaultConfig();
$repository = blog_repository();
$articles = $repository->allArticles();

$report = [
    'storage' => [
        'mode' => blog_storage_mode(),
        'data_dir' => $repository->dataDir(),
    ],
    'taxonomy' => [
        'categories' => count($taxonomy->categoryOptions('fr')),
        'subcategories' => count($taxonomy->subcategoryOptions(null, 'fr')),
        'tags' => count($taxonomy->tagOptions('fr')),
        'max_tags_per_article' => BlogTaxonomy::MAX_TAGS,
    ],
    'articles_checked' => count($articles),
    'issues' => [],
];

foreach ($articles as $article) {
    if (!is_array($article)) {
        continue;
    }

    $slug = trim((string) ($article['slug'] ?? ''));
    $language = trim((string) ($article['lang'] ?? 'fr'));
    $articleKey = ($language !== '' ? $language : 'fr') . ':' . ($slug !== '' ? $slug : '(sans-slug)');
    $category = trim((string) ($article['category'] ?? ''));
    $subcategory = trim((string) ($article['subcategory'] ?? ''));
    $tags = is_array($article['tags'] ?? null) ? array_values($article['tags']) : [];

    $taxonomyResult = $taxonomy->validateArticleTaxonomy($category, $subcategory, $tags);
    foreach ($taxonomyResult['errors'] as $error) {
        $report['issues'][] = issue($articleKey, 'validation', $error);
    }

    $resolvedCategory = $taxonomy->resolveCategorySlug($category);
    if ($category !== '' && $resolvedCategory !== null && $category !== $resolvedCategory) {
        $report['issues'][] = issue($articleKey, 'category_mapping', sprintf('%s -> %s', $category, $resolvedCategory));
    }

    $resolvedSubcategory = $taxonomy->resolveSubcategorySlug($subcategory, $resolvedCategory);
    if ($subcategory !== '' && $resolvedSubcategory !== null && $subcategory !== $resolvedSubcategory) {
        $report['issues'][] = issue($articleKey, 'subcategory_mapping', sprintf('%s -> %s', $subcategory, $resolvedSubcategory));
    }

    $seenTags = [];
    foreach ($tags as $rawTag) {
        $tag = trim((string) $rawTag);
        if ($tag === '') {
            continue;
        }

        $canonical = $taxonomy->resolveTagSlug($tag);
        $normalized = $taxonomy->normalizeKebabSlug($tag);

        if ($canonical === null) {
            $report['issues'][] = issue($articleKey, 'tag_unknown', $tag);
            continue;
        }

        if (isset($seenTags[$canonical])) {
            $report['issues'][] = issue($articleKey, 'tag_duplicate', $tag . ' -> ' . $canonical);
        }
        $seenTags[$canonical] = true;

        if ($tag !== $canonical || $normalized !== $canonical) {
            $report['issues'][] = issue($articleKey, 'tag_mapping', sprintf('%s -> %s', $tag, $canonical));
        }
    }
}

if ($jsonOutput) {
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
}

fwrite(STDOUT, "Diagnostic taxonomie blog\n");
fwrite(STDOUT, sprintf("- stockage: %s (%s)\n", $report['storage']['mode'], $report['storage']['data_dir']));
fwrite(
    STDOUT,
    sprintf(
        "- referentiel: %d categories, %d sous-categories, %d tags, %d tags max/article\n",
        $report['taxonomy']['categories'],
        $report['taxonomy']['subcategories'],
        $report['taxonomy']['tags'],
        $report['taxonomy']['max_tags_per_article']
    )
);
fwrite(STDOUT, sprintf("- articles controles: %d\n", $report['articles_checked']));
fwrite(STDOUT, sprintf("- anomalies: %d\n", count($report['issues'])));

foreach ($report['issues'] as $issue) {
    if (!is_array($issue)) {
        continue;
    }

    fwrite(
        STDOUT,
        sprintf(
            "  * [%s] %s: %s\n",
            (string) ($issue['article'] ?? ''),
            (string) ($issue['type'] ?? ''),
            (string) ($issue['message'] ?? '')
        )
    );
}

exit(0);

/**
 * @return array{article: string, type: string, message: string}
 */
function issue(string $article, string $type, string $message): array
{
    return [
        'article' => $article,
        'type' => $type,
        'message' => $message,
    ];
}
