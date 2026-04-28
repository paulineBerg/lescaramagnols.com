<?php

declare(strict_types=1);

use Caramagnols\Blog\BlogTaxonomy;

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit etre executee en CLI.\n");
    exit(1);
}

$jsonOutput = in_array('--json', array_slice($argv, 1), true);
$configPath = ROOT_PATH . '/config/blog_taxonomy.php';
$config = is_file($configPath) ? require $configPath : [];
$config = is_array($config) ? $config : [];
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
    'taxonomy_config_issues' => taxonomy_config_issues($config, $taxonomy),
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
fwrite(STDOUT, sprintf("- anomalies referentiel: %d\n", count($report['taxonomy_config_issues'])));
foreach ($report['taxonomy_config_issues'] as $issue) {
    if (!is_array($issue)) {
        continue;
    }

    fwrite(
        STDOUT,
        sprintf(
            "  * [%s] %s %s: %s\n",
            (string) ($issue['kind'] ?? ''),
            (string) ($issue['slug'] ?? ''),
            (string) ($issue['type'] ?? ''),
            (string) ($issue['message'] ?? '')
        )
    );
}

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

/**
 * @param array<string, mixed> $config
 * @return array<int, array{kind: string, slug: string, type: string, message: string}>
 */
function taxonomy_config_issues(array $config, BlogTaxonomy $taxonomy): array
{
    $issues = [];
    $categories = is_array($config['categories'] ?? null) ? $config['categories'] : [];
    $subcategories = is_array($config['subcategories'] ?? null) ? $config['subcategories'] : [];
    $tags = is_array($config['tags'] ?? null) ? $config['tags'] : [];

    if (count($categories) > 4) {
        $issues[] = taxonomy_issue('categories', '*', 'limit', 'Le referentiel depasse 4 categories principales.');
    }
    if (count($subcategories) > 8) {
        $issues[] = taxonomy_issue('subcategories', '*', 'limit', 'Le referentiel depasse 8 sous-categories.');
    }
    if (count($tags) > 30) {
        $issues[] = taxonomy_issue('tags', '*', 'limit', 'Le referentiel depasse 30 tags.');
    }

    $issues = array_merge(
        $issues,
        node_issues('category', $categories, $taxonomy, false),
        node_issues('subcategory', $subcategories, $taxonomy, false),
        node_issues('tag', $tags, $taxonomy, true)
    );

    foreach ($categories as $slug => $category) {
        if (!is_string($slug) || !is_array($category)) {
            continue;
        }

        $listedSubcategories = is_array($category['subcategories'] ?? null) ? $category['subcategories'] : [];
        foreach ($listedSubcategories as $subcategorySlug) {
            if (!is_string($subcategorySlug) || !isset($subcategories[$subcategorySlug])) {
                $issues[] = taxonomy_issue('category', $slug, 'subcategory_unknown', (string) $subcategorySlug);
                continue;
            }

            $subcategory = is_array($subcategories[$subcategorySlug]) ? $subcategories[$subcategorySlug] : [];
            if ((string) ($subcategory['category'] ?? '') !== $slug) {
                $issues[] = taxonomy_issue('category', $slug, 'subcategory_parent_mismatch', $subcategorySlug);
            }
        }
    }

    foreach ($subcategories as $slug => $subcategory) {
        if (!is_string($slug) || !is_array($subcategory)) {
            continue;
        }

        $categorySlug = (string) ($subcategory['category'] ?? '');
        if ($categorySlug === '' || !isset($categories[$categorySlug])) {
            $issues[] = taxonomy_issue('subcategory', $slug, 'category_unknown', $categorySlug);
            continue;
        }

        $category = is_array($categories[$categorySlug]) ? $categories[$categorySlug] : [];
        $listedSubcategories = is_array($category['subcategories'] ?? null) ? $category['subcategories'] : [];
        if (!in_array($slug, $listedSubcategories, true)) {
            $issues[] = taxonomy_issue('subcategory', $slug, 'category_missing_relation', $categorySlug);
        }
    }

    $aliases = is_array($config['aliases'] ?? null) ? $config['aliases'] : [];
    foreach (['categories' => $categories, 'subcategories' => $subcategories, 'tags' => $tags] as $kind => $allowed) {
        $values = is_array($aliases[$kind] ?? null) ? $aliases[$kind] : [];
        foreach ($values as $alias => $target) {
            if (!is_string($target) || !isset($allowed[$target])) {
                $issues[] = taxonomy_issue('alias', (string) $alias, 'target_unknown', (string) $target);
            }
        }
    }

    $seo = is_array($config['seo'] ?? null) ? $config['seo'] : [];
    if (($seo['tag_default'] ?? '') !== 'noindex') {
        $issues[] = taxonomy_issue('seo', 'tag_default', 'invalid', 'Les tags doivent etre noindex par defaut.');
    }
    if (($seo['tag_robots'] ?? '') !== 'noindex,follow') {
        $issues[] = taxonomy_issue('seo', 'tag_robots', 'invalid', 'Les pages tag doivent emettre noindex,follow.');
    }

    return $issues;
}

/**
 * @param array<string, mixed> $nodes
 * @return array<int, array{kind: string, slug: string, type: string, message: string}>
 */
function node_issues(string $kind, array $nodes, BlogTaxonomy $taxonomy, bool $tagNode): array
{
    $issues = [];
    $seenNormalized = [];

    foreach ($nodes as $slug => $node) {
        if (!is_string($slug) || !is_array($node)) {
            $issues[] = taxonomy_issue($kind, (string) $slug, 'invalid_node', 'Chaque entree doit etre un tableau.');
            continue;
        }

        $normalized = $taxonomy->normalizeKebabSlug($slug);
        if ($slug !== $normalized) {
            $issues[] = taxonomy_issue($kind, $slug, 'slug_not_normalized', $normalized);
        }
        if (isset($seenNormalized[$normalized])) {
            $issues[] = taxonomy_issue($kind, $slug, 'duplicate_normalized_slug', $seenNormalized[$normalized]);
        }
        $seenNormalized[$normalized] = $slug;

        if (($node['slug'] ?? '') !== $slug) {
            $issues[] = taxonomy_issue($kind, $slug, 'slug_field_mismatch', (string) ($node['slug'] ?? ''));
        }

        $labels = is_array($node['label'] ?? null) ? $node['label'] : [];
        foreach (['fr', 'en', 'de'] as $language) {
            if (!is_string($labels[$language] ?? null) || trim((string) $labels[$language]) === '') {
                $issues[] = taxonomy_issue($kind, $slug, 'missing_label', $language);
            }
        }

        $seo = (string) ($node['seo'] ?? '');
        if (!in_array($seo, ['index', 'noindex'], true)) {
            $issues[] = taxonomy_issue($kind, $slug, 'invalid_seo', $seo);
        }
        if ($tagNode && $seo !== 'noindex') {
            $issues[] = taxonomy_issue($kind, $slug, 'tag_must_be_noindex', $seo);
        }
    }

    return $issues;
}

/**
 * @return array{kind: string, slug: string, type: string, message: string}
 */
function taxonomy_issue(string $kind, string $slug, string $type, string $message): array
{
    return [
        'kind' => $kind,
        'slug' => $slug,
        'type' => $type,
        'message' => $message,
    ];
}
