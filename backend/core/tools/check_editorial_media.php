<?php

declare(strict_types=1);

use Caramagnols\Editorial\EditorialMediaValidator;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit etre executee en CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../bootstrap.php';

$options = parse_cli_options(array_slice($argv, 1));

if (isset($options['help']) || isset($options['h'])) {
    fwrite(STDOUT, editorial_media_usage());
    exit(0);
}

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
$includeDrafts = isset($options['include-drafts']);
$jsonOutput = isset($options['json']);

if (!$skipSourceAssets && !is_dir($frontendImageRoot)) {
    fwrite(STDERR, sprintf("[editorial-media] Source assets introuvables: %s\n", $frontendImageRoot));
    exit(2);
}

if (!is_dir($publicRoot)) {
    fwrite(STDERR, sprintf("[editorial-media] Dossier public introuvable: %s\n", $publicRoot));
    exit(2);
}

$validator = new EditorialMediaValidator(
    normalize_filesystem_path($frontendImageRoot),
    normalize_filesystem_path($publicRoot)
);

$result = $validator->validate(
    build_editorial_entries($includeDrafts),
    $checkPublishedAssets,
    $skipSourceAssets
);

if ($jsonOutput) {
    $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        fwrite(STDERR, "[editorial-media] Encodage JSON impossible.\n");
        exit(2);
    }

    fwrite($result['issues'] === [] ? STDOUT : STDERR, $json . "\n");
    exit($result['issues'] === [] ? 0 : 1);
}

if ($result['issues'] !== []) {
    fwrite(STDERR, "[editorial-media] Verification echouee.\n");
    foreach ($result['issues'] as $issue) {
        fwrite(
            STDERR,
            sprintf(
                "  - [%s] %s :: %s :: %s :: %s\n",
                $issue['type'] ?? 'issue',
                $issue['scope'] ?? 'editorial',
                $issue['entity'] ?? 'unknown',
                $issue['field'] ?? 'payload',
                $issue['path'] ?? ''
            )
        );
    }

    exit(1);
}

fwrite(
    STDOUT,
    sprintf(
        "[editorial-media] OK: %d perimetre(s), %d reference(s) controlee(s).\n",
        (int) $result['entry_count'],
        (int) $result['reference_count']
    )
);

exit(0);

function editorial_media_usage(): string
{
    return <<<USAGE
Usage:
  php core/tools/check_editorial_media.php [--check-published-assets] [--skip-source-assets] [--include-drafts] [--json]

Description:
  Verifie les references medias du contenu editorial actif.
  - /assets/images/... doit exister dans frontend/src/assets/images/**
  - /uploads/editorial/... doit exister dans backend/public/uploads/editorial/**
  - avec --check-published-assets, controle aussi backend/public/assets/images/**

Options:
  --check-published-assets  Exige aussi la presence du miroir publie sous backend/public/assets/images/**.
  --skip-source-assets      Ignore la verification frontend/src/assets/images/** (utile cote OVH).
  --include-drafts          Controle aussi les pages/articles brouillon.
  --json                    Sortie JSON exploitable en script.
  --frontend-image-root=PATH
  --public-root=PATH
  -h, --help                Affiche cette aide.

USAGE;
}

/**
 * @param array<int, string> $arguments
 * @return array<string, string|true>
 */
function parse_cli_options(array $arguments): array
{
    $options = [];

    foreach ($arguments as $argument) {
        if (!str_starts_with($argument, '--')) {
            continue;
        }

        $parts = explode('=', substr($argument, 2), 2);
        if (!isset($parts[1])) {
            $options[$parts[0]] = true;
            continue;
        }

        $options[$parts[0]] = $parts[1];
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
        $cwdCandidate = normalize_filesystem_path($cwd . '/' . $path);
        if (file_exists($cwdCandidate)) {
            return $cwdCandidate;
        }
    }

    return normalize_filesystem_path($repoRoot . '/' . $path);
}

function normalize_filesystem_path(string $path): string
{
    return rtrim(str_replace('\\', '/', $path), '/');
}

/**
 * @return array<int, array{scope: string, entity: string, payload: array<string, mixed>}>
 */
function build_editorial_entries(bool $includeDrafts): array
{
    $entries = [];

    $pageRepository = page_repository(pages_data_path());
    $pages = $includeDrafts ? $pageRepository->all() : $pageRepository->published();
    foreach ($pages as $page) {
        if (!is_array($page)) {
            continue;
        }

        $entries[] = [
            'scope' => 'page',
            'entity' => trim((string) ($page['slug'] ?? 'unknown')),
            'payload' => $page,
        ];
    }

    $entries[] = [
        'scope' => 'navigation',
        'entity' => 'canonical',
        'payload' => navigation_repository(menus_data_path())->loadCanonical(),
    ];

    foreach (blog_repository()->allArticles() as $article) {
        if (!is_array($article) || !should_validate_article($article, $includeDrafts)) {
            continue;
        }

        $entries[] = [
            'scope' => 'blog',
            'entity' => trim((string) ($article['slug'] ?? 'unknown')) . '.' . trim((string) ($article['lang'] ?? 'fr')),
            'payload' => $article,
        ];
    }

    $tileRepository = tile_repository();
    foreach ($tileRepository->listGroupSummaries() as $summary) {
        if (!is_array($summary) || (int) ($summary['placementCount'] ?? 0) <= 0) {
            continue;
        }

        $groupId = (int) ($summary['id'] ?? 0);
        $group = $tileRepository->findGroupForAdmin($groupId);
        if (!is_array($group)) {
            continue;
        }

        $entries[] = [
            'scope' => 'tile_group',
            'entity' => sprintf('%d:%s', $groupId, trim((string) ($group['name'] ?? 'groupe'))),
            'payload' => $group,
        ];
    }

    return $entries;
}

/**
 * @param array<string, mixed> $article
 */
function should_validate_article(array $article, bool $includeDrafts): bool
{
    if ($includeDrafts) {
        return true;
    }

    $status = strtolower(trim((string) ($article['status'] ?? 'draft')));

    return in_array($status, ['published', 'scheduled'], true);
}
