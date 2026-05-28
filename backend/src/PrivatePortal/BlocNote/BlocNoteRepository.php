<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\BlocNote;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class BlocNoteRepository
{
    public const DEFAULT_CATEGORY_NAME = 'Note';
    public const DEFAULT_COLOR = '#ffffff';
    public const CATEGORY_COLORS = [
        '#ffffff',
        '#fff1d6',
        '#ffe0e0',
        '#e1f7d5',
        '#d6ecff',
        '#eadbff',
        '#ffdff3',
    ];

    private bool $schemaReady = false;

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function notesTable(): string
    {
        return $this->database->table('private_blocnote_notes');
    }

    public function categoriesTable(): string
    {
        return $this->database->table('private_blocnote_categories');
    }

    public function ensureDefaultCategory(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $default = $this->defaultCategory($userId);
        if (is_array($default)) {
            return (int) $default['id'];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s` (`private_user_id`, `name`, `slug`, `color`, `is_default`, `created_at`, `updated_at`)
                     VALUES (:user_id, :name, :slug, :color, 1, :created_at, :updated_at)',
                    $this->categoriesTable()
                )
            );
            $now = $this->now();
            $statement->execute([
                'user_id' => $userId,
                'name' => self::DEFAULT_CATEGORY_NAME,
                'slug' => $this->slugify(self::DEFAULT_CATEGORY_NAME),
                'color' => self::DEFAULT_COLOR,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return (int) $this->database->pdo()->lastInsertId();
        } catch (\Throwable) {
            $default = $this->defaultCategory($userId);

            return is_array($default) ? (int) $default['id'] : 0;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listNotes(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT n.*, c.`name` AS `category_name`, c.`color` AS `category_color`, c.`is_default` AS `category_is_default`
                     FROM `%s` n
                     LEFT JOIN `%s` c ON c.`id` = n.`category_id`
                     WHERE n.`private_user_id` = :user_id
                     ORDER BY n.`updated_at` DESC, n.`id` DESC',
                    $this->notesTable(),
                    $this->categoriesTable()
                )
            );
            $statement->execute(['user_id' => $userId]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $notes = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $note = $this->hydrateNote($row);
            if (is_array($note)) {
                $notes[] = $note;
            }
        }

        return $notes;
    }

    public function findNote(int $noteId, int $userId): ?array
    {
        if ($noteId <= 0 || $userId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT n.*, c.`name` AS `category_name`, c.`color` AS `category_color`, c.`is_default` AS `category_is_default`
                     FROM `%s` n
                     LEFT JOIN `%s` c ON c.`id` = n.`category_id`
                     WHERE n.`id` = :id AND n.`private_user_id` = :user_id
                     LIMIT 1',
                    $this->notesTable(),
                    $this->categoriesTable()
                )
            );
            $statement->execute(['id' => $noteId, 'user_id' => $userId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->hydrateNote($row) : null;
    }

    /**
     * @param array{note_id?: int, title?: string, content?: string, category_id?: int} $values
     */
    public function saveNote(int $userId, array $values): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $title = $this->sanitizeText((string) ($values['title'] ?? ''), 191);
        $content = $this->prepareContent((string) ($values['content'] ?? ''));
        if ($title === '' && $content === '') {
            return false;
        }

        $category = $this->resolveCategory((int) ($values['category_id'] ?? 0), $userId);
        $categoryId = (int) ($category['id'] ?? 0);
        $categoryColor = (string) ($category['color'] ?? self::DEFAULT_COLOR);
        $noteId = (int) ($values['note_id'] ?? 0);
        $now = $this->now();

        try {
            $this->ensureSchema();
            if ($noteId > 0 && $this->findNote($noteId, $userId) !== null) {
                $statement = $this->database->pdo()->prepare(
                    sprintf(
                        'UPDATE `%s`
                         SET `title` = :title, `content` = :content, `category_id` = :category_id, `color` = :color, `updated_at` = :updated_at
                         WHERE `id` = :id AND `private_user_id` = :user_id',
                        $this->notesTable()
                    )
                );
                $statement->execute([
                    'title' => $title,
                    'content' => $content,
                    'category_id' => $categoryId > 0 ? $categoryId : null,
                    'color' => $categoryColor,
                    'updated_at' => $now,
                    'id' => $noteId,
                    'user_id' => $userId,
                ]);

                return true;
            }

            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s` (`private_user_id`, `category_id`, `title`, `content`, `color`, `created_at`, `updated_at`)
                     VALUES (:user_id, :category_id, :title, :content, :color, :created_at, :updated_at)',
                    $this->notesTable()
                )
            );
            $statement->execute([
                'user_id' => $userId,
                'category_id' => $categoryId > 0 ? $categoryId : null,
                'title' => $title,
                'content' => $content,
                'color' => $categoryColor,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function deleteNote(int $noteId, int $userId): bool
    {
        if ($noteId <= 0 || $userId <= 0) {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf('DELETE FROM `%s` WHERE `id` = :id AND `private_user_id` = :user_id', $this->notesTable())
            );
            $statement->execute(['id' => $noteId, 'user_id' => $userId]);

            return $statement->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listCategories(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $this->ensureDefaultCategory($userId);

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT c.*,
                            (
                                SELECT COUNT(*)
                                FROM `%s` n
                                WHERE n.`category_id` = c.`id`
                                  AND n.`private_user_id` = c.`private_user_id`
                            ) AS `notes_count`
                     FROM `%s` c
                     WHERE c.`private_user_id` = :user_id
                     ORDER BY c.`is_default` DESC, c.`name` ASC, c.`id` ASC',
                    $this->notesTable(),
                    $this->categoriesTable()
                )
            );
            $statement->execute(['user_id' => $userId]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $categories = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $category = $this->hydrateCategory($row);
            if (is_array($category)) {
                $categories[] = $category;
            }
        }

        return $categories;
    }

    public function saveCategory(int $userId, int $categoryId, string $name, string $color): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $name = $this->sanitizeText($name, 80);
        $slug = $this->slugify($name);
        $color = $this->sanitizeColor($color);
        if ($name === '' || $slug === '') {
            return false;
        }

        if ($this->categoryNameExists($userId, $slug, $categoryId)) {
            return false;
        }

        try {
            $this->ensureSchema();
            $now = $this->now();
            if ($categoryId > 0 && $this->findCategory($categoryId, $userId) !== null) {
                $statement = $this->database->pdo()->prepare(
                    sprintf(
                        'UPDATE `%s` SET `name` = :name, `slug` = :slug, `color` = :color, `updated_at` = :updated_at
                         WHERE `id` = :id AND `private_user_id` = :user_id',
                        $this->categoriesTable()
                    )
                );
                $statement->execute([
                    'name' => $name,
                    'slug' => $slug,
                    'color' => $color,
                    'updated_at' => $now,
                    'id' => $categoryId,
                    'user_id' => $userId,
                ]);
                $this->refreshNotesColor($userId, $categoryId, $color);

                return true;
            }

            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s` (`private_user_id`, `name`, `slug`, `color`, `is_default`, `created_at`, `updated_at`)
                     VALUES (:user_id, :name, :slug, :color, 0, :created_at, :updated_at)',
                    $this->categoriesTable()
                )
            );
            $statement->execute([
                'user_id' => $userId,
                'name' => $name,
                'slug' => $slug,
                'color' => $color,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function setDefaultCategory(int $userId, int $categoryId): bool
    {
        if ($userId <= 0 || $categoryId <= 0 || $this->findCategory($categoryId, $userId) === null) {
            return false;
        }

        try {
            $this->ensureSchema();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();
            $now = $this->now();
            $reset = $pdo->prepare(
                sprintf('UPDATE `%s` SET `is_default` = 0, `updated_at` = :updated_at WHERE `private_user_id` = :user_id', $this->categoriesTable())
            );
            $reset->execute(['updated_at' => $now, 'user_id' => $userId]);
            $set = $pdo->prepare(
                sprintf('UPDATE `%s` SET `is_default` = 1, `updated_at` = :updated_at WHERE `private_user_id` = :user_id AND `id` = :id', $this->categoriesTable())
            );
            $set->execute(['updated_at' => $now, 'user_id' => $userId, 'id' => $categoryId]);
            $pdo->commit();

            return true;
        } catch (\Throwable) {
            if ($this->database->pdo()->inTransaction()) {
                $this->database->pdo()->rollBack();
            }

            return false;
        }
    }

    public function deleteCategory(int $userId, int $categoryId): bool
    {
        $category = $this->findCategory($categoryId, $userId);
        if ($userId <= 0 || $categoryId <= 0 || !is_array($category) || !empty($category['isDefault'])) {
            return false;
        }

        $defaultId = $this->ensureDefaultCategory($userId);
        $default = $this->findCategory($defaultId, $userId);
        if ($defaultId <= 0 || !is_array($default)) {
            return false;
        }

        try {
            $this->ensureSchema();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();
            $updateNotes = $pdo->prepare(
                sprintf(
                    'UPDATE `%s` SET `category_id` = :default_id, `color` = :color WHERE `private_user_id` = :user_id AND `category_id` = :category_id',
                    $this->notesTable()
                )
            );
            $updateNotes->execute([
                'default_id' => $defaultId,
                'color' => (string) $default['color'],
                'user_id' => $userId,
                'category_id' => $categoryId,
            ]);
            $delete = $pdo->prepare(
                sprintf('DELETE FROM `%s` WHERE `private_user_id` = :user_id AND `id` = :id AND `is_default` = 0', $this->categoriesTable())
            );
            $delete->execute(['user_id' => $userId, 'id' => $categoryId]);
            $pdo->commit();

            return $delete->rowCount() > 0;
        } catch (\Throwable) {
            if ($this->database->pdo()->inTransaction()) {
                $this->database->pdo()->rollBack();
            }

            return false;
        }
    }

    public function findCategory(int $categoryId, int $userId): ?array
    {
        if ($categoryId <= 0 || $userId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf('SELECT * FROM `%s` WHERE `id` = :id AND `private_user_id` = :user_id LIMIT 1', $this->categoriesTable())
            );
            $statement->execute(['id' => $categoryId, 'user_id' => $userId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->hydrateCategory($row) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardData(int $userId): array
    {
        $notes = $this->listNotes($userId);
        $categories = $this->listCategories($userId);
        $now = time();
        $weekAgo = strtotime('-7 days', $now);
        $monthAgo = strtotime('-30 days', $now);
        $recentNotesCount = 0;
        $untitledNotesCount = 0;
        $orphanNotesCount = 0;
        $customCategoriesCount = 0;
        $categoryUsage = [];

        foreach ($categories as $category) {
            if (empty($category['isDefault'])) {
                ++$customCategoriesCount;
            }
        }

        foreach ($notes as $note) {
            if (trim((string) ($note['title'] ?? '')) === '') {
                ++$untitledNotesCount;
            }

            $updatedTimestamp = strtotime((string) ($note['updatedAt'] ?? ''));
            if ($updatedTimestamp !== false && $weekAgo !== false && $updatedTimestamp >= $weekAgo) {
                ++$recentNotesCount;
            }

            $categoryId = (int) ($note['categoryId'] ?? 0);
            $categoryName = trim((string) ($note['categoryName'] ?? ''));
            if ($categoryId <= 0 || $categoryName === '') {
                ++$orphanNotesCount;
                continue;
            }

            if (!isset($categoryUsage[$categoryId])) {
                $categoryUsage[$categoryId] = [
                    'id' => $categoryId,
                    'name' => $categoryName,
                    'color' => (string) ($note['categoryColor'] ?? $note['color'] ?? self::DEFAULT_COLOR),
                    'count' => 0,
                ];
            }
            ++$categoryUsage[$categoryId]['count'];
        }

        uasort($categoryUsage, static function (array $left, array $right): int {
            $countComparison = (int) $right['count'] <=> (int) $left['count'];

            return $countComparison !== 0 ? $countComparison : strcmp((string) $left['name'], (string) $right['name']);
        });

        $latestNote = $notes[0] ?? null;
        $latestTimestamp = is_array($latestNote) ? strtotime((string) ($latestNote['updatedAt'] ?? '')) : false;
        $latestAgeDays = $latestTimestamp !== false ? max(0, (int) floor(($now - $latestTimestamp) / 86400)) : null;
        $status = 'healthy';
        if ($notes === []) {
            $status = 'empty';
        } elseif ($orphanNotesCount > 0) {
            $status = 'warning';
        } elseif ($latestTimestamp !== false && $monthAgo !== false && $latestTimestamp < $monthAgo) {
            $status = 'idle';
        } elseif ($untitledNotesCount > 0 || $customCategoriesCount === 0) {
            $status = 'partial';
        }

        return [
            'status' => $status,
            'totalNotes' => count($notes),
            'recentNotesCount' => $recentNotesCount,
            'untitledNotesCount' => $untitledNotesCount,
            'orphanNotesCount' => $orphanNotesCount,
            'categoriesTotal' => count($categories),
            'customCategoriesTotal' => $customCategoriesCount,
            'latestNote' => $latestNote,
            'latestNoteAgeDays' => $latestAgeDays,
            'recentNotes' => array_slice($notes, 0, 5),
            'categoryUsage' => array_slice(array_values($categoryUsage), 0, 5),
        ];
    }

    public function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        $this->database->ensureReady();
        $pdo = $this->database->pdo();
        $userTable = $this->database->table('private_users');
        $categoryUserConstraint = $this->constraintName('categories_user');
        $noteUserConstraint = $this->constraintName('notes_user');
        $noteCategoryConstraint = $this->constraintName('notes_category');

        $pdo->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `private_user_id` INT NOT NULL,
                `name` VARCHAR(80) NOT NULL,
                `slug` VARCHAR(96) NOT NULL,
                `color` CHAR(7) NOT NULL DEFAULT \'#ffffff\',
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_private_blocnote_categories_user_slug` (`private_user_id`, `slug`),
                KEY `idx_private_blocnote_categories_user` (`private_user_id`, `is_default`, `name`),
                CONSTRAINT `%s` FOREIGN KEY (`private_user_id`) REFERENCES `%s` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $this->categoriesTable(),
            $categoryUserConstraint,
            $userTable
        ));
        $pdo->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `private_user_id` INT NOT NULL,
                `category_id` INT NULL,
                `title` VARCHAR(191) NOT NULL DEFAULT \'\',
                `content` LONGTEXT NOT NULL,
                `color` CHAR(7) NOT NULL DEFAULT \'#ffffff\',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_private_blocnote_notes_user_updated` (`private_user_id`, `updated_at`),
                KEY `idx_private_blocnote_notes_category` (`category_id`),
                CONSTRAINT `%s` FOREIGN KEY (`private_user_id`) REFERENCES `%s` (`id`) ON DELETE CASCADE,
                CONSTRAINT `%s` FOREIGN KEY (`category_id`) REFERENCES `%s` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $this->notesTable(),
            $noteUserConstraint,
            $userTable,
            $noteCategoryConstraint,
            $this->categoriesTable()
        ));

        $this->schemaReady = true;
    }

    private function defaultCategory(int $userId): ?array
    {
        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf('SELECT * FROM `%s` WHERE `private_user_id` = :user_id AND `is_default` = 1 ORDER BY `id` ASC LIMIT 1', $this->categoriesTable())
            );
            $statement->execute(['user_id' => $userId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->hydrateCategory($row) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveCategory(int $categoryId, int $userId): array
    {
        $category = $this->findCategory($categoryId, $userId);
        if (is_array($category)) {
            return $category;
        }

        $defaultId = $this->ensureDefaultCategory($userId);
        $default = $this->findCategory($defaultId, $userId);

        return is_array($default)
            ? $default
            : ['id' => 0, 'name' => self::DEFAULT_CATEGORY_NAME, 'color' => self::DEFAULT_COLOR, 'isDefault' => true];
    }

    private function categoryNameExists(int $userId, string $slug, int $excludedId): bool
    {
        try {
            $this->ensureSchema();
            $sql = sprintf('SELECT 1 FROM `%s` WHERE `private_user_id` = :user_id AND `slug` = :slug', $this->categoriesTable());
            $params = ['user_id' => $userId, 'slug' => $slug];
            if ($excludedId > 0) {
                $sql .= ' AND `id` <> :excluded_id';
                $params['excluded_id'] = $excludedId;
            }
            $statement = $this->database->pdo()->prepare($sql . ' LIMIT 1');
            $statement->execute($params);

            return (bool) $statement->fetchColumn();
        } catch (\Throwable) {
            return true;
        }
    }

    private function refreshNotesColor(int $userId, int $categoryId, string $color): void
    {
        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf('UPDATE `%s` SET `color` = :color WHERE `private_user_id` = :user_id AND `category_id` = :category_id', $this->notesTable())
            );
            $statement->execute(['color' => $this->sanitizeColor($color), 'user_id' => $userId, 'category_id' => $categoryId]);
        } catch (\Throwable) {
            return;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function hydrateNote(array $row): ?array
    {
        $id = is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0;
        if ($id <= 0) {
            return null;
        }

        $content = $this->normalizeContent((string) ($row['content'] ?? ''));
        $title = (string) ($row['title'] ?? '');
        $categoryName = is_string($row['category_name'] ?? null) ? trim((string) $row['category_name']) : '';
        $categoryColor = $this->sanitizeColor((string) ($row['category_color'] ?? $row['color'] ?? self::DEFAULT_COLOR));
        $createdAt = is_string($row['created_at'] ?? null) ? (string) $row['created_at'] : '';
        $updatedAt = is_string($row['updated_at'] ?? null) ? (string) $row['updated_at'] : '';

        return [
            'id' => $id,
            'privateUserId' => is_numeric($row['private_user_id'] ?? null) ? (int) $row['private_user_id'] : 0,
            'categoryId' => is_numeric($row['category_id'] ?? null) ? (int) $row['category_id'] : 0,
            'title' => $title,
            'displayTitle' => trim($title) !== '' ? $title : 'Sans titre',
            'content' => $content,
            'contentText' => $content,
            'contentHtml' => $this->contentHtml($content),
            'excerpt' => $this->excerpt($content),
            'color' => $this->sanitizeColor((string) ($row['color'] ?? self::DEFAULT_COLOR)),
            'categoryName' => $categoryName !== '' ? $categoryName : 'Sans catégorie',
            'categoryColor' => $categoryColor,
            'categoryIsDefault' => $this->truthy($row['category_is_default'] ?? null),
            'createdAt' => $createdAt,
            'updatedAt' => $updatedAt,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function hydrateCategory(array $row): ?array
    {
        $id = is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0;
        if ($id <= 0) {
            return null;
        }

        return [
            'id' => $id,
            'privateUserId' => is_numeric($row['private_user_id'] ?? null) ? (int) $row['private_user_id'] : 0,
            'name' => (string) ($row['name'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'color' => $this->sanitizeColor((string) ($row['color'] ?? self::DEFAULT_COLOR)),
            'isDefault' => $this->truthy($row['is_default'] ?? null),
            'notesCount' => is_numeric($row['notes_count'] ?? null) ? (int) $row['notes_count'] : 0,
            'createdAt' => is_string($row['created_at'] ?? null) ? (string) $row['created_at'] : '',
            'updatedAt' => is_string($row['updated_at'] ?? null) ? (string) $row['updated_at'] : '',
        ];
    }

    private function prepareContent(string $content): string
    {
        if ($this->containsHtml($content)) {
            $content = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $this->normalizeContent($content);
    }

    private function normalizeContent(string $content): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', '', $content) ?? $content;
        $content = trim($content);

        return $this->limitText($content, 50000);
    }

    private function containsHtml(string $content): bool
    {
        return preg_match('/<[^>]+>/', $content) === 1;
    }

    private function contentHtml(string $content): string
    {
        return nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
    }

    private function excerpt(string $content): string
    {
        $plain = trim((string) preg_replace('/\s+/', ' ', $content));
        if ($plain === '') {
            return 'Aucun contenu.';
        }

        $short = $this->limitText($plain, 180);

        return $short === $plain ? $short : rtrim($short, " \t\n\r\0\x0B.,;:") . '...';
    }

    private function sanitizeText(string $value, int $maxLength): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? $value;
        $value = trim((string) preg_replace('/\s+/', ' ', $value));

        return $this->limitText($value, $maxLength);
    }

    private function limitText(string $value, int $maxLength): string
    {
        if ($maxLength <= 0) {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength, 'UTF-8');
        }

        return substr($value, 0, $maxLength);
    }

    private function sanitizeColor(string $color): string
    {
        $color = strtolower(trim($color));
        if (preg_match('/\A#[0-9a-f]{6}\z/', $color) === 1) {
            return $color;
        }

        return self::DEFAULT_COLOR;
    }

    private function slugify(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && trim($ascii) !== '') {
            $value = $ascii;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $this->limitText($value, 96) : 'categorie';
    }

    private function constraintName(string $name): string
    {
        $base = preg_replace('/[^A-Za-z0-9_]+/', '_', $this->database->table('private_blocnote') . '_' . $name) ?? $name;
        $hash = substr(hash('sha1', $base), 0, 10);

        return 'fk_' . substr($base, 0, 45) . '_' . $hash;
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'active'], true);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
