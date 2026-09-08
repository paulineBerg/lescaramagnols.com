<?php

declare(strict_types=1);

use Caramagnols\Editorial\EditorialMediaValidator;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit etre executee en CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../bootstrap.php';

$options = parse_options(array_slice($argv, 1));
if (isset($options['help']) || isset($options['h'])) {
    fwrite(STDOUT, usage());
    exit(0);
}

$sourcePath = trim((string) ($options['source'] ?? ''));
$apply = isset($options['apply']);
$repoRoot = dirname(ROOT_PATH);
$frontendImageRoot = resolve_path(
    (string) ($options['frontend-image-root'] ?? ($repoRoot . '/frontend/src/assets/images')),
    $repoRoot
);
$publicRoot = resolve_path(
    (string) ($options['public-root'] ?? (ROOT_PATH . '/public')),
    $repoRoot
);
$checkPublishedAssets = isset($options['check-published-assets']);
$skipSourceAssets = isset($options['skip-source-assets']);

if ($sourcePath === '') {
    fwrite(STDERR, "[blog-media-repair] Option --source obligatoire.\n");
    fwrite(STDERR, usage());
    exit(2);
}

if (!$skipSourceAssets && !is_dir($frontendImageRoot)) {
    fwrite(STDERR, sprintf("[blog-media-repair] Source assets introuvables: %s\n", $frontendImageRoot));
    exit(2);
}

if (!is_dir($publicRoot)) {
    fwrite(STDERR, sprintf("[blog-media-repair] Dossier public introuvable: %s\n", $publicRoot));
    exit(2);
}

$validator = new EditorialMediaValidator(
    normalize_filesystem_path($frontendImageRoot),
    normalize_filesystem_path($publicRoot)
);
$repository = blog_repository();
$sourceArticles = load_source_articles(resolve_path($sourcePath, $repoRoot));
$activeArticles = $repository->allArticles();
$activeMap = index_articles($activeArticles);

$result = $validator->validate(
    build_blog_entries($activeArticles),
    $checkPublishedAssets,
    $skipSourceAssets
);

/** @var array<string, array{featured: bool, content: bool, types: array<string, bool>}> $repairs */
$repairs = [];
foreach ($result['issues'] as $issue) {
    if (($issue['scope'] ?? '') !== 'blog') {
        continue;
    }

    $entity = trim((string) ($issue['entity'] ?? ''));
    if ($entity === '') {
        continue;
    }

    $type = trim((string) ($issue['type'] ?? ''));
    $repairs[$entity] ??= ['featured' => false, 'content' => false, 'types' => []];
    $repairs[$entity]['types'][$type] = true;

    if (str_starts_with($type, 'featured_')) {
        $repairs[$entity]['featured'] = true;
    }
    if (str_starts_with($type, 'body_')) {
        $repairs[$entity]['content'] = true;
    }
}

$updated = 0;
$skipped = [];
$unchanged = 0;

foreach ($repairs as $entity => $repair) {
    [$slug, $language] = split_entity($entity);
    $key = article_key($slug, $language);
    $current = $activeMap[$key] ?? null;
    $source = $sourceArticles[$key] ?? null;

    if (!is_array($current)) {
        $skipped[] = [$entity, 'article actif introuvable'];
        continue;
    }
    if (!is_array($source)) {
        $skipped[] = [$entity, 'article source introuvable'];
        continue;
    }

    $candidate = $current;
    if ($repair['featured']) {
        $featured = is_array($source['featured_image'] ?? null) ? $source['featured_image'] : [];
        if (!is_valid_featured_image($featured)) {
            $skipped[] = [$entity, 'featured_image source invalide'];
            continue;
        }
        $candidate['featured_image'] = $featured;
    }

    if ($repair['content']) {
        $content = is_string($source['content'] ?? null) ? (string) $source['content'] : '';
        if (!has_valid_body_image($content)) {
            $skipped[] = [$entity, 'content source sans image exploitable'];
            continue;
        }
        $candidate['content'] = $content;
    }

    $candidateValidation = $validator->validate(
        [['scope' => 'blog', 'entity' => $entity, 'payload' => $candidate]],
        $checkPublishedAssets,
        $skipSourceAssets
    );
    if ($candidateValidation['issues'] !== []) {
        $skipped[] = [$entity, 'article source encore invalide apres patch'];
        continue;
    }

    if (
        ($candidate['featured_image'] ?? null) === ($current['featured_image'] ?? null)
        && (string) ($candidate['content'] ?? '') === (string) ($current['content'] ?? '')
    ) {
        $unchanged++;
        continue;
    }

    if ($apply) {
        $repository->save($candidate, $slug, $language);
    }
    $updated++;
}

fwrite(
    STDOUT,
    sprintf(
        "[blog-media-repair] %s: %d article(s) cible(s), %d article(s) %s, %d inchange(s), %d ignore(s).\n",
        $apply ? 'APPLY' : 'DRY-RUN',
        count($repairs),
        $updated,
        $apply ? 'mis a jour' : 'a mettre a jour',
        $unchanged,
        count($skipped)
    )
);

foreach (array_slice($skipped, 0, 30) as [$entity, $reason]) {
    fwrite(STDERR, sprintf("[blog-media-repair] ignore %s: %s\n", $entity, $reason));
}
if (count($skipped) > 30) {
    fwrite(STDERR, sprintf("[blog-media-repair] ... %d autre(s) ignore(s).\n", count($skipped) - 30));
}

exit($skipped === [] ? 0 : 1);

function usage(): string
{
    return <<<USAGE
Usage:
  php core/tools/repair_blog_media_from_backup.php --source=/chemin/backup.json[.gz] [--apply] [--check-published-assets] [--skip-source-assets]

Description:
  Repare uniquement les champs medias des articles blog actuellement signales par
  le validateur editorial, en copiant les champs valides depuis une sauvegarde.
  Sans --apply, la commande effectue un dry-run.

USAGE;
}

/**
 * @param array<int, string> $arguments
 * @return array<string, string|true>
 */
function parse_options(array $arguments): array
{
    $options = [];
    foreach ($arguments as $argument) {
        if (!str_starts_with($argument, '--')) {
            continue;
        }
        $parts = explode('=', substr($argument, 2), 2);
        $options[$parts[0]] = $parts[1] ?? true;
    }

    return $options;
}

function resolve_path(string $path, string $repoRoot): string
{
    $path = trim($path);
    if ($path === '') {
        return $repoRoot;
    }
    if (str_starts_with($path, '/')) {
        return normalize_filesystem_path($path);
    }
    $cwd = getcwd();
    if (is_string($cwd)) {
        $candidate = normalize_filesystem_path($cwd . '/' . $path);
        if (file_exists($candidate)) {
            return $candidate;
        }
    }

    return normalize_filesystem_path($repoRoot . '/' . $path);
}

function normalize_filesystem_path(string $path): string
{
    return rtrim(str_replace('\\', '/', $path), '/');
}

/**
 * @return array<string, array<string, mixed>>
 */
function load_source_articles(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Sauvegarde source introuvable ou illisible.');
    }

    $raw = file_get_contents($path);
    $contents = str_ends_with(strtolower($path), '.gz') && is_string($raw) ? gzdecode($raw) : $raw;
    if (!is_string($contents) || $contents === '') {
        throw new RuntimeException('Sauvegarde source vide ou impossible a decoder.');
    }

    $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    $articles = is_array($payload['blog']['articles'] ?? null) ? $payload['blog']['articles'] : [];

    return index_articles(array_filter($articles, 'is_array'));
}

/**
 * @param array<int, array<string, mixed>> $articles
 * @return array<string, array<string, mixed>>
 */
function index_articles(array $articles): array
{
    $indexed = [];
    foreach ($articles as $article) {
        $slug = normalize_slug((string) ($article['slug'] ?? ''));
        $language = normalize_language((string) ($article['lang'] ?? 'fr'));
        if ($slug === '') {
            continue;
        }

        $indexed[article_key($slug, $language)] = $article;
    }

    return $indexed;
}

/**
 * @param array<int, array<string, mixed>> $articles
 * @return array<int, array{scope: string, entity: string, payload: array<string, mixed>}>
 */
function build_blog_entries(array $articles): array
{
    $entries = [];
    foreach ($articles as $article) {
        if (!should_validate_article($article)) {
            continue;
        }
        $entries[] = [
            'scope' => 'blog',
            'entity' => normalize_slug((string) ($article['slug'] ?? 'unknown')) . '.' . normalize_language((string) ($article['lang'] ?? 'fr')),
            'payload' => $article,
        ];
    }

    return $entries;
}

/**
 * @param array<string, mixed> $article
 */
function should_validate_article(array $article): bool
{
    $status = strtolower(trim((string) ($article['status'] ?? 'draft')));

    return in_array($status, ['published', 'scheduled'], true);
}

/**
 * @param array<string, mixed> $featured
 */
function is_valid_featured_image(array $featured): bool
{
    if (trim((string) ($featured['src'] ?? '')) === '') {
        return false;
    }
    foreach (['alt', 'title', 'caption'] as $field) {
        if (trim((string) ($featured[$field] ?? '')) === '') {
            return false;
        }
    }

    return is_numeric($featured['width'] ?? null)
        && (int) $featured['width'] > 0
        && is_numeric($featured['height'] ?? null)
        && (int) $featured['height'] > 0;
}

function has_valid_body_image(string $content): bool
{
    if (preg_match('/<img\b[^>]*>/i', $content, $matches) !== 1) {
        return false;
    }

    $tag = (string) $matches[0];
    foreach (['src', 'alt', 'title'] as $attribute) {
        if (html_attribute($tag, $attribute) === '') {
            return false;
        }
    }

    foreach (['width', 'height'] as $attribute) {
        $value = html_attribute($tag, $attribute);
        if (!ctype_digit($value) || (int) $value <= 0) {
            return false;
        }
    }

    return true;
}

function html_attribute(string $tag, string $attribute): string
{
    if (preg_match('/\b' . preg_quote($attribute, '/') . '\s*=\s*(["\'])(.*?)\1/is', $tag, $matches) !== 1) {
        return '';
    }

    return trim(html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

/**
 * @return array{0: string, 1: string}
 */
function split_entity(string $entity): array
{
    $position = strrpos($entity, '.');
    if ($position === false) {
        return [normalize_slug($entity), 'fr'];
    }

    return [
        normalize_slug(substr($entity, 0, $position)),
        normalize_language(substr($entity, $position + 1)),
    ];
}

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
