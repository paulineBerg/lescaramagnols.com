<?php

declare(strict_types=1);

use Caramagnols\Blog\SqlBlogDiscussionRepository;
use Caramagnols\Blog\SqlBlogRepository;
use Caramagnols\Content\PageRepository;
use Caramagnols\Content\StructuredPageRenderer;
use Caramagnols\Editorial\EditorialSqlMirrorExporter;
use Caramagnols\Navigation\NavigationRepository;

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit etre lancee en CLI.\n");
    exit(1);
}

$options = parse_export_sql_editorial_options(array_slice($argv, 1));
$database = editorial_database();
$exporter = new EditorialSqlMirrorExporter(
    new PageRepository(pages_data_path(), new StructuredPageRenderer(), 'sql', $database),
    new NavigationRepository(menus_data_path(), 'sql', $database),
    new SqlBlogRepository($database),
    new SqlBlogDiscussionRepository($database)
);

try {
    $summary = $exporter->export(
        (string) $options['pages'],
        (string) $options['menus'],
        (string) $options['blog_dir'],
        (string) $options['discussions_dir'],
        (bool) $options['include_discussions'],
        (bool) $options['prune'],
        (bool) $options['dry_run']
    );
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

$modeLabel = $summary['dry_run'] ? 'Simulation export SQL -> JSON' : 'Export SQL -> JSON termine';
fwrite(
    STDOUT,
    sprintf(
        "%s : %d page(s), %d emplacement(s) navigation, %d article(s), %d article(s) miroir supprime(s), %d discussion(s), %d fil(s) discussion, %d fil(s) discussion miroir supprime(s).\n",
        $modeLabel,
        $summary['pages'],
        $summary['navigation_locations'],
        $summary['articles_exported'],
        $summary['articles_pruned'],
        $summary['discussion_items_exported'],
        $summary['discussion_threads_exported'],
        $summary['discussion_threads_pruned']
    )
);
fwrite(STDOUT, sprintf("pages=%s\n", $summary['paths']['pages']));
fwrite(STDOUT, sprintf("menus=%s\n", $summary['paths']['menus']));
fwrite(STDOUT, sprintf("blog_dir=%s\n", $summary['paths']['blog']));
if ($summary['include_discussions'] && is_string($summary['paths']['discussions'])) {
    fwrite(STDOUT, sprintf("discussions_dir=%s\n", $summary['paths']['discussions']));
}

exit(0);

/**
 * @param array<int, string> $arguments
 * @return array{
 *     pages: string,
 *     menus: string,
 *     blog_dir: string,
 *     discussions_dir: string,
 *     include_discussions: bool,
 *     prune: bool,
 *     dry_run: bool
 * }
 */
function parse_export_sql_editorial_options(array $arguments): array
{
    $options = [
        'pages' => pages_data_path(),
        'menus' => menus_data_path(),
        'blog_dir' => blog_data_dir(),
        'discussions_dir' => blog_discussions_data_dir(),
        'include_discussions' => false,
        'prune' => true,
        'dry_run' => false,
    ];

    foreach ($arguments as $argument) {
        if ($argument === '--include-discussions') {
            $options['include_discussions'] = true;
            continue;
        }

        if ($argument === '--no-prune') {
            $options['prune'] = false;
            continue;
        }

        if ($argument === '--dry-run') {
            $options['dry_run'] = true;
            continue;
        }

        if (str_starts_with($argument, '--pages=')) {
            $options['pages'] = trim((string) substr($argument, 8));
            continue;
        }

        if (str_starts_with($argument, '--menus=')) {
            $options['menus'] = trim((string) substr($argument, 8));
            continue;
        }

        if (str_starts_with($argument, '--blog-dir=')) {
            $options['blog_dir'] = trim((string) substr($argument, 11));
            continue;
        }

        if (str_starts_with($argument, '--discussions-dir=')) {
            $options['discussions_dir'] = trim((string) substr($argument, 18));
            continue;
        }

        if ($argument === '-h' || $argument === '--help') {
            fwrite(STDOUT, export_sql_editorial_usage());
            exit(0);
        }

        fwrite(STDERR, sprintf("Option inconnue: %s\n", $argument));
        fwrite(STDERR, export_sql_editorial_usage());
        exit(1);
    }

    return $options;
}

function export_sql_editorial_usage(): string
{
    return <<<TXT
Usage:
  php backend/core/tools/export_sql_editorial_to_json.php [--pages=/path/pages.json] [--menus=/path/menus.json] [--blog-dir=/path/blog] [--include-discussions] [--discussions-dir=/path/discussions] [--no-prune] [--dry-run]

Description:
  Exporte le stockage editorial SQL courant vers les miroirs JSON versionnables.
  La source est forcee en SQL pour les pages, la navigation, le blog et, si demande, les discussions.

TXT;
}
