<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

final class JsonBlogDiscussionRepository implements BlogDiscussionRepositoryInterface
{
    private const STATUSES = ['pending', 'approved', 'rejected'];

    public function __construct(private readonly string $dataDir)
    {
    }

    public function dataDir(): string
    {
        return $this->dataDir;
    }

    /**
     * @param array<string, mixed> $comment
     * @return array<string, mixed>
     */
    public function submitPending(string $articleSlug, string $articleLanguage, array $comment): array
    {
        $slug = $this->normalizeSlug($articleSlug);
        $language = $this->normalizeLanguage($articleLanguage);
        $path = $this->threadPath($slug, $language);

        return $this->withThreadLock($path, function () use ($path, $slug, $language, $comment): array {
            $thread = $this->readThreadFile($path, $slug, $language);
            $now = date('c');
            $status = $this->normalizeStatus((string) ($comment['status'] ?? 'pending')) ?? 'pending';
            $createdAt = trim((string) ($comment['created_at'] ?? $now));
            $updatedAt = trim((string) ($comment['updated_at'] ?? $createdAt));
            $moderatedAt = trim((string) ($comment['moderated_at'] ?? ''));
            $moderatedBy = trim((string) ($comment['moderated_by'] ?? ''));

            if ($status !== 'pending' && $moderatedAt === '') {
                $moderatedAt = $updatedAt;
            }

            $item = [
                'id' => $this->normalizeIdentifier((string) ($comment['id'] ?? '')),
                'author' => trim((string) ($comment['author'] ?? '')),
                'email' => trim((string) ($comment['email'] ?? '')),
                'content' => trim((string) ($comment['content'] ?? '')),
                'status' => $status,
                'created_at' => $createdAt !== '' ? $createdAt : $now,
                'updated_at' => $updatedAt !== '' ? $updatedAt : $now,
                'moderated_at' => $status !== 'pending' && $moderatedAt !== '' ? $moderatedAt : null,
                'moderated_by' => $status !== 'pending' && $moderatedBy !== '' ? $moderatedBy : null,
                'ip_hash' => trim((string) ($comment['ip_hash'] ?? '')),
                'user_agent_hash' => trim((string) ($comment['user_agent_hash'] ?? '')),
            ];

            if ($item['id'] === '') {
                $item['id'] = bin2hex(random_bytes(12));
            }

            $thread['items'][] = $item;
            $thread['article'] = [
                'slug' => $slug,
                'lang' => $language,
            ];

            $this->writeThreadFile($path, $thread);

            return $this->withThreadContext($item, $slug, $language);
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function approvedForArticle(string $articleSlug, string $articleLanguage): array
    {
        $slug = $this->normalizeSlug($articleSlug);
        $language = $this->normalizeLanguage($articleLanguage);
        $path = $this->threadPath($slug, $language);
        $thread = $this->readThreadFile($path, $slug, $language);

        $approved = array_values(array_filter(
            $thread['items'],
            static fn (array $item): bool => (string) ($item['status'] ?? '') === 'approved'
        ));

        usort(
            $approved,
            static fn (array $left, array $right): int => strtotime((string) ($left['created_at'] ?? '')) <=> strtotime((string) ($right['created_at'] ?? ''))
        );

        return array_map(
            fn (array $item): array => $this->withThreadContext($item, $slug, $language),
            $approved
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(?string $status = null): array
    {
        $normalizedStatus = $this->normalizeStatus($status);
        $rows = [];

        foreach ($this->threadFiles() as $path) {
            $thread = $this->readThreadFile($path);
            $slug = (string) ($thread['article']['slug'] ?? '');
            $language = (string) ($thread['article']['lang'] ?? 'fr');

            foreach ($thread['items'] as $item) {
                if ($normalizedStatus !== null && (string) ($item['status'] ?? '') !== $normalizedStatus) {
                    continue;
                }

                $rows[] = $this->withThreadContext($item, $slug, $language);
            }
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => strtotime((string) ($right['created_at'] ?? '')) <=> strtotime((string) ($left['created_at'] ?? ''))
        );

        return $rows;
    }

    /**
     * @return array{total: int, pending: int, approved: int, rejected: int}
     */
    public function stats(): array
    {
        $stats = [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
        ];

        foreach ($this->all() as $item) {
            $stats['total']++;
            $status = (string) ($item['status'] ?? 'pending');

            if (array_key_exists($status, $stats)) {
                $stats[$status]++;
            }
        }

        return $stats;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function moderate(string $id, string $status, ?string $moderatorIdentifier = null): ?array
    {
        $normalizedId = $this->normalizeIdentifier($id);
        $normalizedStatus = $this->normalizeStatus($status);

        if ($normalizedId === '' || $normalizedStatus === null) {
            return null;
        }

        foreach ($this->threadFiles() as $path) {
            $updated = $this->withThreadLock($path, function () use ($path, $normalizedId, $normalizedStatus, $moderatorIdentifier): ?array {
                $thread = $this->readThreadFile($path);
                $slug = (string) ($thread['article']['slug'] ?? '');
                $language = (string) ($thread['article']['lang'] ?? 'fr');
                $found = null;
                $changed = false;
                $now = date('c');

                foreach ($thread['items'] as $index => $item) {
                    if ((string) ($item['id'] ?? '') !== $normalizedId) {
                        continue;
                    }

                    $item['status'] = $normalizedStatus;
                    $item['updated_at'] = $now;
                    $item['moderated_at'] = $now;
                    $item['moderated_by'] = $moderatorIdentifier !== null && trim($moderatorIdentifier) !== ''
                        ? trim($moderatorIdentifier)
                        : null;

                    $thread['items'][$index] = $item;
                    $found = $this->withThreadContext($item, $slug, $language);
                    $changed = true;
                    break;
                }

                if ($changed) {
                    $this->writeThreadFile($path, $thread);
                }

                return $found;
            });

            if (is_array($updated)) {
                return $updated;
            }
        }

        return null;
    }

    public function delete(string $id): bool
    {
        $normalizedId = $this->normalizeIdentifier($id);
        if ($normalizedId === '') {
            return false;
        }

        foreach ($this->threadFiles() as $path) {
            $deleted = $this->withThreadLock($path, function () use ($path, $normalizedId): bool {
                $thread = $this->readThreadFile($path);
                $initialCount = count($thread['items']);

                $thread['items'] = array_values(array_filter(
                    $thread['items'],
                    static fn (array $item): bool => (string) ($item['id'] ?? '') !== $normalizedId
                ));

                if (count($thread['items']) === $initialCount) {
                    return false;
                }

                if ($thread['items'] === []) {
                    if (file_exists($path)) {
                        @unlink($path);
                    }

                    return true;
                }

                $this->writeThreadFile($path, $thread);

                return true;
            });

            if ($deleted) {
                return true;
            }
        }

        return false;
    }

    public function deleteThreadForArticle(string $articleSlug, string $articleLanguage): int
    {
        if (!is_dir($this->dataDir)) {
            return 0;
        }

        $slug = $this->normalizeSlug($articleSlug);
        $language = $this->normalizeLanguage($articleLanguage);
        $path = $this->threadPath($slug, $language);
        $lockPath = $path . '.lock';

        if (!is_file($path) && !is_file($lockPath)) {
            return 0;
        }

        $removedCount = 0;

        $this->withThreadLock($path, function () use ($path, $slug, $language, &$removedCount): void {
            $thread = $this->readThreadFile($path, $slug, $language);
            $removedCount = count($thread['items']);

            if (is_file($path)) {
                @unlink($path);
            }
        });

        if (is_file($lockPath)) {
            @unlink($lockPath);
        }

        return $removedCount;
    }

    /**
     * @param callable(): mixed $callback
     */
    private function withThreadLock(string $threadPath, callable $callback): mixed
    {
        $lockPath = $threadPath . '.lock';
        $lockDirectory = dirname($lockPath);
        if (!is_dir($lockDirectory) && !mkdir($lockDirectory, 0775, true) && !is_dir($lockDirectory)) {
            throw new \RuntimeException(sprintf('Impossible de créer le dossier discussions: %s', $lockDirectory));
        }

        $lockHandle = fopen($lockPath, 'c+');

        if ($lockHandle === false) {
            throw new \RuntimeException(sprintf('Impossible de verrouiller le fichier discussions: %s', $lockPath));
        }

        if (!flock($lockHandle, LOCK_EX)) {
            fclose($lockHandle);

            throw new \RuntimeException(sprintf('Impossible d’obtenir le verrou discussions: %s', $lockPath));
        }

        try {
            return $callback();
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /**
     * @return array<int, string>
     */
    private function threadFiles(): array
    {
        if (!is_dir($this->dataDir)) {
            return [];
        }

        $files = glob(rtrim($this->dataDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.json');

        if ($files === false) {
            return [];
        }

        sort($files);

        return $files;
    }

    private function threadPath(string $slug, string $language): string
    {
        return rtrim($this->dataDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $slug . '.' . $language . '.json';
    }

    /**
     * @return array{article: array{slug: string, lang: string}, items: array<int, array<string, mixed>>}
     */
    private function readThreadFile(string $path, ?string $fallbackSlug = null, ?string $fallbackLanguage = null): array
    {
        $thread = [
            'article' => [
                'slug' => $fallbackSlug !== null ? $this->normalizeSlug($fallbackSlug) : '',
                'lang' => $fallbackLanguage !== null ? $this->normalizeLanguage($fallbackLanguage) : 'fr',
            ],
            'items' => [],
        ];

        if (!is_file($path) || !is_readable($path)) {
            return $thread;
        }

        $raw = file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return $thread;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $thread;
        }

        if (is_array($decoded['article'] ?? null)) {
            $thread['article']['slug'] = $this->normalizeSlug((string) ($decoded['article']['slug'] ?? $thread['article']['slug']));
            $thread['article']['lang'] = $this->normalizeLanguage((string) ($decoded['article']['lang'] ?? $thread['article']['lang']));
        }

        if ($thread['article']['slug'] === '' || $thread['article']['lang'] === '') {
            $fromFilename = $this->extractThreadCoordinates($path);
            if ($fromFilename !== null) {
                $thread['article']['slug'] = $fromFilename['slug'];
                $thread['article']['lang'] = $fromFilename['lang'];
            }
        }

        $items = is_array($decoded['items'] ?? null) ? $decoded['items'] : [];
        foreach ($items as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $thread['items'][] = $this->normalizeItem($rawItem);
        }

        return $thread;
    }

    /**
     * @param array{article: array{slug: string, lang: string}, items: array<int, array<string, mixed>>} $thread
     */
    private function writeThreadFile(string $path, array $thread): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Impossible de créer le dossier discussions: %s', $directory));
        }

        $payload = [
            'article' => [
                'slug' => $this->normalizeSlug((string) ($thread['article']['slug'] ?? '')),
                'lang' => $this->normalizeLanguage((string) ($thread['article']['lang'] ?? 'fr')),
            ],
            'items' => array_values(array_map(
                fn (array $item): array => $this->normalizeItem($item),
                is_array($thread['items'] ?? null) ? $thread['items'] : []
            )),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new \RuntimeException('Impossible d’encoder les discussions en JSON.');
        }

        $temporaryPath = $path . '.tmp';
        if (file_put_contents($temporaryPath, $json) === false) {
            throw new \RuntimeException(sprintf('Impossible d’écrire le fichier temporaire discussions: %s', $temporaryPath));
        }

        if (!rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new \RuntimeException(sprintf('Impossible de déplacer le fichier discussions vers: %s', $path));
        }
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function normalizeItem(array $item): array
    {
        $status = $this->normalizeStatus((string) ($item['status'] ?? 'pending')) ?? 'pending';
        $createdAt = trim((string) ($item['created_at'] ?? date('c')));
        $updatedAt = trim((string) ($item['updated_at'] ?? $createdAt));
        $moderatedAt = trim((string) ($item['moderated_at'] ?? ''));
        $moderatedBy = trim((string) ($item['moderated_by'] ?? ''));

        return [
            'id' => $this->normalizeIdentifier((string) ($item['id'] ?? '')),
            'author' => trim((string) ($item['author'] ?? '')),
            'email' => trim((string) ($item['email'] ?? '')),
            'content' => trim((string) ($item['content'] ?? '')),
            'status' => $status,
            'created_at' => $createdAt !== '' ? $createdAt : date('c'),
            'updated_at' => $updatedAt !== '' ? $updatedAt : date('c'),
            'moderated_at' => $moderatedAt !== '' ? $moderatedAt : null,
            'moderated_by' => $moderatedBy !== '' ? $moderatedBy : null,
            'ip_hash' => trim((string) ($item['ip_hash'] ?? '')),
            'user_agent_hash' => trim((string) ($item['user_agent_hash'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function withThreadContext(array $item, string $slug, string $language): array
    {
        $item['article_slug'] = $slug;
        $item['article_lang'] = $language;

        return $item;
    }

    /**
     * @return array{slug: string, lang: string}|null
     */
    private function extractThreadCoordinates(string $path): ?array
    {
        $basename = basename($path, '.json');
        if (preg_match('/^(.+)\.([a-z]+)$/', $basename, $matches) !== 1) {
            return null;
        }

        return [
            'slug' => $this->normalizeSlug($matches[1]),
            'lang' => $this->normalizeLanguage($matches[2]),
        ];
    }

    private function normalizeSlug(string $slug): string
    {
        $normalized = strtolower(trim($slug));
        $normalized = preg_replace('/[^a-z0-9-]+/i', '-', $normalized) ?? '';

        return trim($normalized, '-');
    }

    private function normalizeLanguage(string $language): string
    {
        $normalized = strtolower(trim($language));
        $normalized = preg_replace('/[^a-z]/', '', $normalized) ?? '';

        return $normalized !== '' ? $normalized : 'fr';
    }

    private function normalizeIdentifier(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9]/', '', $normalized) ?? '';

        return $normalized;
    }

    private function normalizeStatus(?string $status): ?string
    {
        if (!is_string($status)) {
            return null;
        }

        $normalized = strtolower(trim($status));

        return in_array($normalized, self::STATUSES, true) ? $normalized : null;
    }
}
