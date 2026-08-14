<?php

declare(strict_types=1);

use Caramagnols\Blog\BlogEditorialQualityAuditor;

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit être exécutée en CLI.\n");
    exit(1);
}

$arguments = array_slice($argv, 1);
$jsonOutput = in_array('--json', $arguments, true);
$localFiles = in_array('--local-files', $arguments, true);
$backupPath = null;
foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--backup=')) {
        $backupPath = trim((string) substr($argument, 9));
    }
}

try {
    if ($backupPath !== null && $backupPath !== '') {
        $articles = articlesFromBackup($backupPath);
    } elseif ($localFiles) {
        $articles = articlesFromLocalFiles(dirname(__DIR__, 2) . '/data/blog');
    } else {
        $articles = array_values(array_filter(blog_repository()->allArticles(), 'is_array'));
    }
    $result = (new BlogEditorialQualityAuditor())->audit($articles);
} catch (Throwable $exception) {
    fwrite(STDERR, '[blog-editorial-quality] ' . $exception->getMessage() . "\n");
    exit(2);
}

if ($jsonOutput) {
    $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        fwrite(STDERR, "[blog-editorial-quality] Encodage JSON impossible.\n");
        exit(2);
    }
    fwrite($result['error_count'] === 0 ? STDOUT : STDERR, $json . "\n");

    exit($result['error_count'] === 0 ? 0 : 1);
}

fwrite(
    $result['error_count'] === 0 ? STDOUT : STDERR,
    sprintf(
        "[blog-editorial-quality] %d article(s), %d slug(s), %d erreur(s), %d avertissement(s).\n",
        $result['article_count'],
        $result['slug_count'],
        $result['error_count'],
        $result['warning_count']
    )
);
foreach ($result['issues'] as $issue) {
    fwrite(
        $issue['severity'] === 'error' ? STDERR : STDOUT,
        sprintf(
            "  - [%s] %s :: %s :: %s :: %s\n",
            $issue['severity'],
            $issue['type'],
            $issue['entity'],
            $issue['field'],
            $issue['detail']
        )
    );
}

exit($result['error_count'] === 0 ? 0 : 1);

/**
 * @return array<int, array<string, mixed>>
 */
function articlesFromBackup(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Backup introuvable ou illisible.');
    }

    $contents = str_ends_with(strtolower($path), '.gz')
        ? gzdecode((string) file_get_contents($path))
        : file_get_contents($path);
    if (!is_string($contents) || $contents === '') {
        throw new RuntimeException('Backup vide ou impossible à décompresser.');
    }

    $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    $articles = is_array($payload['blog']['articles'] ?? null) ? $payload['blog']['articles'] : null;
    if ($articles === null) {
        throw new RuntimeException('Structure blog.articles absente du backup.');
    }

    return array_values(array_filter($articles, 'is_array'));
}

/**
 * @return array<int, array<string, mixed>>
 */
function articlesFromLocalFiles(string $directory): array
{
    $files = glob(rtrim($directory, '/') . '/*.json') ?: [];
    sort($files);
    $articles = [];
    foreach ($files as $file) {
        $payload = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        if (is_array($payload)) {
            $articles[] = $payload;
        }
    }

    return $articles;
}
