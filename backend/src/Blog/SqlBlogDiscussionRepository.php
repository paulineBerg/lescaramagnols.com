<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class SqlBlogDiscussionRepository implements BlogDiscussionRepositoryInterface
{
    private const STATUSES = ['pending', 'approved', 'rejected'];

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function dataDir(): string
    {
        return 'sql://' . $this->database->table('blog_discussions');
    }

    /**
     * @param array<string, mixed> $comment
     * @return array<string, mixed>
     */
    public function submitPending(string $articleSlug, string $articleLanguage, array $comment): array
    {
        $slug = $this->normalizeSlug($articleSlug);
        $language = $this->normalizeLanguage($articleLanguage);

        if ($slug === '') {
            throw new \InvalidArgumentException('Slug d’article discussion invalide.');
        }

        $now = date('c');
        $status = $this->normalizeStatus((string) ($comment['status'] ?? 'pending')) ?? 'pending';
        $createdAt = $this->normalizeDateTime($comment['created_at'] ?? null, $now);
        $updatedAt = $this->normalizeDateTime($comment['updated_at'] ?? null, $createdAt);
        $moderatedAt = $this->normalizeOptionalDateTime($comment['moderated_at'] ?? null);
        $moderatedBy = $this->normalizeOptionalString($comment['moderated_by'] ?? null);

        if ($status !== 'pending' && $moderatedAt === null) {
            $moderatedAt = $updatedAt;
        }

        if ($status === 'pending') {
            $moderatedAt = null;
            $moderatedBy = null;
        }

        $item = [
            'id' => $this->normalizeIdentifier((string) ($comment['id'] ?? '')),
            'author' => trim((string) ($comment['author'] ?? '')),
            'email' => trim((string) ($comment['email'] ?? '')),
            'content' => trim((string) ($comment['content'] ?? '')),
            'status' => $status,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'moderated_at' => $moderatedAt,
            'moderated_by' => $moderatedBy,
            'ip_hash' => $this->normalizeOptionalString($comment['ip_hash'] ?? null),
            'user_agent_hash' => $this->normalizeOptionalString($comment['user_agent_hash'] ?? null),
        ];

        if ($item['id'] === '') {
            $item['id'] = bin2hex(random_bytes(12));
        }

        $this->database->ensureReady();
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`id`, `article_slug`, `article_lang`, `author`, `email`, `content`, `status`,
                     `created_at`, `updated_at`, `moderated_at`, `moderated_by`, `ip_hash`, `user_agent_hash`)
                 VALUES
                    (:id, :article_slug, :article_lang, :author, :email, :content, :status,
                     :created_at, :updated_at, :moderated_at, :moderated_by, :ip_hash, :user_agent_hash)
                 ON DUPLICATE KEY UPDATE
                    `article_slug` = VALUES(`article_slug`),
                    `article_lang` = VALUES(`article_lang`),
                    `author` = VALUES(`author`),
                    `email` = VALUES(`email`),
                    `content` = VALUES(`content`),
                    `status` = VALUES(`status`),
                    `created_at` = VALUES(`created_at`),
                    `updated_at` = VALUES(`updated_at`),
                    `moderated_at` = VALUES(`moderated_at`),
                    `moderated_by` = VALUES(`moderated_by`),
                    `ip_hash` = VALUES(`ip_hash`),
                    `user_agent_hash` = VALUES(`user_agent_hash`)',
                $this->database->table('blog_discussions')
            )
        );
        $statement->execute([
            'id' => $item['id'],
            'article_slug' => $slug,
            'article_lang' => $language,
            'author' => $item['author'],
            'email' => $item['email'],
            'content' => $item['content'],
            'status' => $item['status'],
            'created_at' => $item['created_at'],
            'updated_at' => $item['updated_at'],
            'moderated_at' => $item['moderated_at'],
            'moderated_by' => $item['moderated_by'],
            'ip_hash' => $item['ip_hash'],
            'user_agent_hash' => $item['user_agent_hash'],
        ]);

        return $this->withThreadContext($item, $slug, $language);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function approvedForArticle(string $articleSlug, string $articleLanguage): array
    {
        return $this->loadForArticleByStatus($articleSlug, $articleLanguage, 'approved', 'ASC');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(?string $status = null): array
    {
        $this->database->ensureReady();
        $normalizedStatus = $this->normalizeStatus($status);
        $table = $this->database->table('blog_discussions');
        $pdo = $this->database->pdo();

        if ($normalizedStatus !== null) {
            $statement = $pdo->prepare(
                sprintf(
                    'SELECT * FROM `%s` WHERE `status` = :status ORDER BY `created_at` DESC, `id` DESC',
                    $table
                )
            );
            $statement->execute(['status' => $normalizedStatus]);
            $rows = $statement->fetchAll();
        } else {
            $rows = $pdo->query(
                sprintf('SELECT * FROM `%s` ORDER BY `created_at` DESC, `id` DESC', $table)
            )->fetchAll();
        }

        $comments = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $comments[] = $this->rowToComment($row);
        }

        return $comments;
    }

    /**
     * @return array{total: int, pending: int, approved: int, rejected: int}
     */
    public function stats(): array
    {
        $this->database->ensureReady();
        $statement = $this->database->pdo()->query(
            sprintf(
                'SELECT `status`, COUNT(*) AS `count` FROM `%s` GROUP BY `status`',
                $this->database->table('blog_discussions')
            )
        );

        $stats = [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
        ];

        foreach ($statement->fetchAll() ?: [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $status = $this->normalizeStatus((string) ($row['status'] ?? ''));
            $count = is_numeric($row['count'] ?? null) ? (int) $row['count'] : 0;

            if ($status !== null) {
                $stats[$status] += $count;
                $stats['total'] += $count;
            }
        }

        return $stats;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function moderate(string $id, string $status, ?string $moderatorIdentifier = null): ?array
    {
        $this->database->ensureReady();
        $normalizedId = $this->normalizeIdentifier($id);
        $normalizedStatus = $this->normalizeStatus($status);

        if ($normalizedId === '' || $normalizedStatus === null) {
            return null;
        }

        $now = date('c');
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `status` = :status,
                     `updated_at` = :updated_at,
                     `moderated_at` = :moderated_at,
                     `moderated_by` = :moderated_by
                 WHERE `id` = :id',
                $this->database->table('blog_discussions')
            )
        );
        $statement->execute([
            'status' => $normalizedStatus,
            'updated_at' => $now,
            'moderated_at' => $now,
            'moderated_by' => $this->normalizeOptionalString($moderatorIdentifier),
            'id' => $normalizedId,
        ]);

        if ($statement->rowCount() === 0) {
            return null;
        }

        return $this->findById($normalizedId);
    }

    public function delete(string $id): bool
    {
        $this->database->ensureReady();
        $normalizedId = $this->normalizeIdentifier($id);
        if ($normalizedId === '') {
            return false;
        }

        $statement = $this->database->pdo()->prepare(
            sprintf(
                'DELETE FROM `%s` WHERE `id` = :id',
                $this->database->table('blog_discussions')
            )
        );
        $statement->execute(['id' => $normalizedId]);

        return $statement->rowCount() > 0;
    }

    public function deleteThreadForArticle(string $articleSlug, string $articleLanguage): int
    {
        $this->database->ensureReady();
        $slug = $this->normalizeSlug($articleSlug);
        $language = $this->normalizeLanguage($articleLanguage);

        if ($slug === '') {
            return 0;
        }

        $statement = $this->database->pdo()->prepare(
            sprintf(
                'DELETE FROM `%s` WHERE `article_slug` = :article_slug AND `article_lang` = :article_lang',
                $this->database->table('blog_discussions')
            )
        );
        $statement->execute([
            'article_slug' => $slug,
            'article_lang' => $language,
        ]);

        return $statement->rowCount();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadForArticleByStatus(
        string $articleSlug,
        string $articleLanguage,
        string $status,
        string $sortDirection
    ): array {
        $this->database->ensureReady();
        $slug = $this->normalizeSlug($articleSlug);
        $language = $this->normalizeLanguage($articleLanguage);

        if ($slug === '') {
            return [];
        }

        $statement = $this->database->pdo()->prepare(
            sprintf(
                'SELECT * FROM `%s`
                 WHERE `article_slug` = :article_slug
                   AND `article_lang` = :article_lang
                   AND `status` = :status
                 ORDER BY `created_at` %s, `id` %s',
                $this->database->table('blog_discussions'),
                $sortDirection,
                $sortDirection
            )
        );
        $statement->execute([
            'article_slug' => $slug,
            'article_lang' => $language,
            'status' => $status,
        ]);

        $rows = $statement->fetchAll();
        $comments = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $comments[] = $this->rowToComment($row);
        }

        return $comments;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findById(string $id): ?array
    {
        $this->database->ensureReady();
        $statement = $this->database->pdo()->prepare(
            sprintf(
                'SELECT * FROM `%s` WHERE `id` = :id LIMIT 1',
                $this->database->table('blog_discussions')
            )
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->rowToComment($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function rowToComment(array $row): array
    {
        $item = [
            'id' => $this->normalizeIdentifier((string) ($row['id'] ?? '')),
            'author' => trim((string) ($row['author'] ?? '')),
            'email' => trim((string) ($row['email'] ?? '')),
            'content' => trim((string) ($row['content'] ?? '')),
            'status' => $this->normalizeStatus((string) ($row['status'] ?? 'pending')) ?? 'pending',
            'created_at' => $this->normalizeDateTime($row['created_at'] ?? null, date('c')),
            'updated_at' => $this->normalizeDateTime($row['updated_at'] ?? null, date('c')),
            'moderated_at' => $this->normalizeOptionalDateTime($row['moderated_at'] ?? null),
            'moderated_by' => $this->normalizeOptionalString($row['moderated_by'] ?? null),
            'ip_hash' => $this->normalizeOptionalString($row['ip_hash'] ?? null),
            'user_agent_hash' => $this->normalizeOptionalString($row['user_agent_hash'] ?? null),
        ];

        return $this->withThreadContext(
            $item,
            $this->normalizeSlug((string) ($row['article_slug'] ?? '')),
            $this->normalizeLanguage((string) ($row['article_lang'] ?? 'fr'))
        );
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

    private function normalizeDateTime(mixed $value, string $fallback): string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : $fallback;
    }

    private function normalizeOptionalDateTime(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}
