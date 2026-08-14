<?php

declare(strict_types=1);

use Caramagnols\Blog\JsonBlogRepository;

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit être exécutée en CLI.\n");
    exit(1);
}

$backupPath = is_string($argv[1] ?? null) ? trim((string) $argv[1]) : '';
$arguments = array_slice($argv, 2);
$apply = in_array('--apply', $arguments, true);
$allowDelete = in_array('--allow-delete', $arguments, true);
$targetDirectory = blog_data_dir();
foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--target-dir=')) {
        $targetDirectory = trim((string) substr($argument, 13));
    }
}

if ($backupPath === '' || !is_file($backupPath) || !is_readable($backupPath)) {
    fwrite(STDERR, "Usage: php core/tools/sync_blog_backup_to_json.php BACKUP.json[.gz] [--target-dir=PATH] [--apply] [--allow-delete]\n");
    exit(2);
}
if ($targetDirectory === '') {
    fwrite(STDERR, "[blog-backup-sync] Dossier cible vide.\n");
    exit(2);
}

try {
    $raw = file_get_contents($backupPath);
    $contents = str_ends_with(strtolower($backupPath), '.gz') && is_string($raw) ? gzdecode($raw) : $raw;
    if (!is_string($contents) || $contents === '') {
        throw new RuntimeException('Backup vide ou impossible à décompresser.');
    }
    $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    $incoming = is_array($payload['blog']['articles'] ?? null) ? $payload['blog']['articles'] : null;
    if ($incoming === null) {
        throw new RuntimeException('Structure blog.articles absente du backup.');
    }

    $repository = new JsonBlogRepository($targetDirectory);
    $existingByKey = [];
    foreach ($repository->allArticles() as $article) {
        if (!is_array($article)) {
            continue;
        }
        $existingByKey[articleKey($article)] = $article;
    }

    $incomingByKey = [];
    foreach ($incoming as $article) {
        if (!is_array($article)) {
            continue;
        }
        $key = articleKey($article);
        if ($key === '|') {
            throw new RuntimeException('Article sans slug ni langue dans le backup.');
        }
        if (isset($incomingByKey[$key])) {
            throw new RuntimeException('Variante dupliquée dans le backup : ' . $key);
        }
        $incomingByKey[$key] = $article;
    }

    $created = array_diff_key($incomingByKey, $existingByKey);
    $removed = array_diff_key($existingByKey, $incomingByKey);
    $updated = [];
    $unchanged = [];
    foreach (array_intersect_key($incomingByKey, $existingByKey) as $key => $article) {
        if (articleChecksum($article) === articleChecksum($existingByKey[$key])) {
            $unchanged[$key] = true;
        } else {
            $updated[$key] = true;
        }
    }

    if ($removed !== [] && !$allowDelete) {
        throw new RuntimeException(sprintf(
            '%d suppression(s) détectée(s) ; relancer avec --allow-delete après contrôle explicite.',
            count($removed)
        ));
    }

    if ($apply) {
        foreach ($incomingByKey as $article) {
            $repository->save(
                $article,
                (string) ($article['slug'] ?? ''),
                (string) ($article['lang'] ?? 'fr')
            );
        }
        if ($allowDelete) {
            foreach ($removed as $article) {
                $repository->delete((string) ($article['slug'] ?? ''), (string) ($article['lang'] ?? 'fr'));
            }
        }
    }

    fwrite(STDOUT, json_encode([
        'mode' => $apply ? 'apply' : 'dry-run',
        'target' => $targetDirectory,
        'incoming' => count($incomingByKey),
        'created' => count($created),
        'updated' => count($updated),
        'unchanged' => count($unchanged),
        'removed' => count($removed),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, '[blog-backup-sync] ' . $exception->getMessage() . "\n");
    exit(2);
}

exit(0);

/**
 * @param array<string, mixed> $article
 */
function articleKey(array $article): string
{
    return strtolower(trim((string) ($article['slug'] ?? '')))
        . '|'
        . strtolower(trim((string) ($article['lang'] ?? '')));
}

/**
 * @param array<string, mixed> $article
 */
function articleChecksum(array $article): string
{
    unset($article['created_at'], $article['updated_at']);
    ksort($article);

    return hash('sha256', (string) json_encode($article, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}
