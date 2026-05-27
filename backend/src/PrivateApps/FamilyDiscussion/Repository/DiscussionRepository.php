<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\FamilyDiscussion\Repository;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class DiscussionRepository
{
    private bool $schemaReady = false;

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        $this->database->ensureReady();
        $pdo = $this->database->pdo();

        $pdo->exec(sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `type` VARCHAR(16) NOT NULL,
                `direct_key` VARCHAR(64) NULL,
                `title` VARCHAR(160) NULL,
                `created_by_private_user_id` INT NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `last_message_at` DATETIME NULL,
                `archived_at` DATETIME NULL,
                UNIQUE KEY `uq_discussion_conversations_direct_key` (`direct_key`),
                KEY `idx_discussion_conversations_last_message` (`last_message_at`),
                KEY `idx_discussion_conversations_created_by` (`created_by_private_user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            $this->conversationTable()
        ));

        $pdo->exec(sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `conversation_id` INT NOT NULL,
                `private_user_id` INT NOT NULL,
                `role` VARCHAR(16) NOT NULL DEFAULT 'member',
                `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `left_at` DATETIME NULL,
                `muted_until` DATETIME NULL,
                `last_opened_at` DATETIME NULL,
                UNIQUE KEY `uq_discussion_members_user` (`conversation_id`, `private_user_id`),
                KEY `idx_discussion_members_user_active` (`private_user_id`, `left_at`),
                KEY `idx_discussion_members_conversation` (`conversation_id`, `left_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            $this->memberTable()
        ));

        $pdo->exec(sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `conversation_id` INT NOT NULL,
                `sender_private_user_id` INT NOT NULL,
                `body` TEXT NULL,
                `body_format` VARCHAR(16) NOT NULL DEFAULT 'plain',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `edited_at` DATETIME NULL,
                `deleted_at` DATETIME NULL,
                `expires_at` DATETIME NOT NULL,
                `purge_status` VARCHAR(16) NOT NULL DEFAULT 'active',
                KEY `idx_discussion_messages_conversation` (`conversation_id`, `id`),
                KEY `idx_discussion_messages_sender` (`sender_private_user_id`),
                KEY `idx_discussion_messages_expiry` (`expires_at`, `purge_status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            $this->messageTable()
        ));

        $pdo->exec(sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `message_id` INT NOT NULL,
                `attachment_id` VARCHAR(64) NOT NULL,
                `original_filename` VARCHAR(255) NOT NULL,
                `storage_path` VARCHAR(255) NOT NULL,
                `preview_storage_path` VARCHAR(255) NULL,
                `mime_type` VARCHAR(128) NOT NULL,
                `size_bytes` BIGINT UNSIGNED NOT NULL,
                `sha256` CHAR(64) NOT NULL,
                `width` INT NULL,
                `height` INT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `expires_at` DATETIME NOT NULL,
                `purge_status` VARCHAR(16) NOT NULL DEFAULT 'active',
                UNIQUE KEY `uq_discussion_attachments_attachment_id` (`attachment_id`),
                UNIQUE KEY `uq_discussion_attachments_storage_path` (`storage_path`),
                KEY `idx_discussion_attachments_message` (`message_id`),
                KEY `idx_discussion_attachments_expiry` (`expires_at`, `purge_status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            $this->attachmentTable()
        ));

        $pdo->exec(sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `conversation_id` INT NOT NULL,
                `message_id` INT NOT NULL,
                `private_user_id` INT NOT NULL,
                `read_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_discussion_reads_user_message` (`message_id`, `private_user_id`),
                KEY `idx_discussion_reads_conversation_user` (`conversation_id`, `private_user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            $this->readTable()
        ));

        $pdo->exec(sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `private_user_id` INT NULL,
                `scope` VARCHAR(32) NOT NULL,
                `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `finished_at` DATETIME NULL,
                `purged_messages_count` INT NOT NULL DEFAULT 0,
                `purged_attachments_count` INT NOT NULL DEFAULT 0,
                `status` VARCHAR(32) NOT NULL DEFAULT 'running',
                `error_message` VARCHAR(255) NULL,
                KEY `idx_discussion_retention_started` (`started_at`),
                KEY `idx_discussion_retention_user` (`private_user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            $this->retentionRunTable()
        ));

        $this->schemaReady = true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listConversationsForUser(int $userId, int $limit = 50): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT c.*, cm.`role`, cm.`last_opened_at`,
                    (
                        SELECT COUNT(*)
                        FROM `%s` unread
                        WHERE unread.`conversation_id` = c.`id`
                          AND unread.`sender_private_user_id` <> :unread_user_id
                          AND unread.`purge_status` = 'active'
                          AND unread.`deleted_at` IS NULL
                          AND (cm.`last_opened_at` IS NULL OR unread.`created_at` > cm.`last_opened_at`)
                    ) AS unread_count,
                    (
                        SELECT last_message.`body`
                        FROM `%s` last_message
                        WHERE last_message.`conversation_id` = c.`id`
                          AND last_message.`purge_status` = 'active'
                          AND last_message.`deleted_at` IS NULL
                        ORDER BY last_message.`id` DESC
                        LIMIT 1
                    ) AS last_body
                 FROM `%s` c
                 INNER JOIN `%s` cm ON cm.`conversation_id` = c.`id`
                 WHERE cm.`private_user_id` = :user_id
                   AND cm.`left_at` IS NULL
                   AND c.`archived_at` IS NULL
                 ORDER BY COALESCE(c.`last_message_at`, c.`updated_at`, c.`created_at`) DESC, c.`id` DESC
                 LIMIT :limit",
                $this->messageTable(),
                $this->messageTable(),
                $this->conversationTable(),
                $this->memberTable()
            ));
            $statement->bindValue(':unread_user_id', $userId, PDO::PARAM_INT);
            $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $statement->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return $this->hydrateConversations(is_array($rows) ? $rows : []);
    }

    public function findConversationForUser(int $conversationId, int $userId): ?array
    {
        if ($conversationId <= 0 || $userId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT c.*, cm.`role`, cm.`last_opened_at`, 0 AS unread_count, NULL AS last_body
                 FROM `%s` c
                 INNER JOIN `%s` cm ON cm.`conversation_id` = c.`id`
                 WHERE c.`id` = :conversation_id
                   AND cm.`private_user_id` = :user_id
                   AND cm.`left_at` IS NULL
                   AND c.`archived_at` IS NULL
                 LIMIT 1",
                $this->conversationTable(),
                $this->memberTable()
            ));
            $statement->execute(['conversation_id' => $conversationId, 'user_id' => $userId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->hydrateConversation($row) : null;
    }

    public function userRoleInConversation(int $conversationId, int $userId): ?string
    {
        if ($conversationId <= 0 || $userId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT `role`
                 FROM `%s`
                 WHERE `conversation_id` = :conversation_id
                   AND `private_user_id` = :user_id
                   AND `left_at` IS NULL
                 LIMIT 1",
                $this->memberTable()
            ));
            $statement->execute(['conversation_id' => $conversationId, 'user_id' => $userId]);
            $role = $statement->fetchColumn();
        } catch (\Throwable) {
            return null;
        }

        $role = is_string($role) ? strtolower(trim($role)) : '';

        return in_array($role, ['owner', 'member'], true) ? $role : null;
    }

    public function isParticipant(int $conversationId, int $userId): bool
    {
        return $this->userRoleInConversation($conversationId, $userId) !== null;
    }

    public function createDirectConversation(int $creatorId, int $otherUserId): ?array
    {
        if ($creatorId <= 0 || $otherUserId <= 0 || $creatorId === $otherUserId) {
            return null;
        }

        $ids = [$creatorId, $otherUserId];
        sort($ids);
        $directKey = 'direct:' . implode(':', $ids);

        try {
            $this->ensureSchema();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();
            $conversationId = $this->conversationIdByDirectKey($pdo, $directKey);
            if ($conversationId === null) {
                $now = $this->now();
                $statement = $pdo->prepare(sprintf(
                    "INSERT INTO `%s`
                        (`type`, `direct_key`, `title`, `created_by_private_user_id`, `created_at`, `updated_at`)
                     VALUES
                        ('direct', :direct_key, NULL, :creator_id, :created_at, :updated_at)",
                    $this->conversationTable()
                ));
                $statement->execute([
                    'direct_key' => $directKey,
                    'creator_id' => $creatorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $conversationId = (int) $pdo->lastInsertId();
            }

            foreach ($ids as $id) {
                $this->upsertMember($pdo, $conversationId, $id, $id === $creatorId ? 'owner' : 'member');
            }
            $pdo->commit();
        } catch (\Throwable) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return null;
        }

        return $conversationId > 0 ? $this->findConversationForUser($conversationId, $creatorId) : null;
    }

    /**
     * @param array<int, int> $memberIds
     */
    public function createGroupConversation(int $creatorId, string $title, array $memberIds): ?array
    {
        $title = $this->normalizeTitle($title);
        $memberIds = $this->normalizeIds(array_merge([$creatorId], $memberIds));
        if ($creatorId <= 0 || $title === '' || $memberIds === []) {
            return null;
        }

        try {
            $this->ensureSchema();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();
            $now = $this->now();
            $statement = $pdo->prepare(sprintf(
                "INSERT INTO `%s`
                    (`type`, `direct_key`, `title`, `created_by_private_user_id`, `created_at`, `updated_at`)
                 VALUES
                    ('group', NULL, :title, :creator_id, :created_at, :updated_at)",
                $this->conversationTable()
            ));
            $statement->execute([
                'title' => $title,
                'creator_id' => $creatorId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $conversationId = (int) $pdo->lastInsertId();
            foreach ($memberIds as $memberId) {
                $this->upsertMember($pdo, $conversationId, $memberId, $memberId === $creatorId ? 'owner' : 'member');
            }
            $pdo->commit();
        } catch (\Throwable) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return null;
        }

        return $conversationId > 0 ? $this->findConversationForUser($conversationId, $creatorId) : null;
    }

    /**
     * @param array<int, int> $memberIds
     */
    public function addMembers(int $conversationId, array $memberIds): bool
    {
        $memberIds = $this->normalizeIds($memberIds);
        if ($conversationId <= 0 || $memberIds === []) {
            return false;
        }

        try {
            $this->ensureSchema();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();
            foreach ($memberIds as $memberId) {
                $this->upsertMember($pdo, $conversationId, $memberId, 'member');
            }
            $pdo->commit();

            return true;
        } catch (\Throwable) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return false;
        }
    }

    public function leaveConversation(int $conversationId, int $userId): bool
    {
        if ($conversationId <= 0 || $userId <= 0) {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "UPDATE `%s`
                 SET `left_at` = :left_at
                 WHERE `conversation_id` = :conversation_id
                   AND `private_user_id` = :user_id
                   AND `left_at` IS NULL",
                $this->memberTable()
            ));
            $statement->execute([
                'left_at' => $this->now(),
                'conversation_id' => $conversationId,
                'user_id' => $userId,
            ]);

            return $statement->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function createMessage(int $conversationId, int $senderUserId, string $body, string $expiresAt): ?array
    {
        $body = $this->normalizeBody($body);
        if ($conversationId <= 0 || $senderUserId <= 0 || $expiresAt === '') {
            return null;
        }

        try {
            $this->ensureSchema();
            $now = $this->now();
            $pdo = $this->database->pdo();
            $statement = $pdo->prepare(sprintf(
                "INSERT INTO `%s`
                    (`conversation_id`, `sender_private_user_id`, `body`, `body_format`, `created_at`, `expires_at`)
                 VALUES
                    (:conversation_id, :sender_id, :body, 'plain', :created_at, :expires_at)",
                $this->messageTable()
            ));
            $statement->execute([
                'conversation_id' => $conversationId,
                'sender_id' => $senderUserId,
                'body' => $body !== '' ? $body : null,
                'created_at' => $now,
                'expires_at' => $expiresAt,
            ]);
            $messageId = (int) $pdo->lastInsertId();
            $this->touchConversation($conversationId, $now);
        } catch (\Throwable) {
            return null;
        }

        return $messageId > 0 ? $this->findMessageById($messageId) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMessagesForUser(int $conversationId, int $userId, int $afterMessageId = 0, int $limit = 100): array
    {
        if ($conversationId <= 0 || $userId <= 0 || !$this->isParticipant($conversationId, $userId)) {
            return [];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT *
                 FROM `%s`
                 WHERE `conversation_id` = :conversation_id
                   AND `id` > :after_message_id
                   AND `purge_status` = 'active'
                   AND `deleted_at` IS NULL
                 ORDER BY `id` ASC
                 LIMIT :limit",
                $this->messageTable()
            ));
            $statement->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
            $statement->bindValue(':after_message_id', max(0, $afterMessageId), PDO::PARAM_INT);
            $statement->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $messages = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $message = $this->hydrateMessage($row);
            if (!is_array($message)) {
                continue;
            }

            $message['attachments'] = $this->listAttachmentsForMessage((int) $message['id']);
            $messages[] = $message;
        }

        return $messages;
    }

    public function findMessageById(int $messageId): ?array
    {
        if ($messageId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT * FROM `%s` WHERE `id` = :id LIMIT 1",
                $this->messageTable()
            ));
            $statement->execute(['id' => $messageId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->hydrateMessage($row) : null;
    }

    public function createAttachment(
        int $messageId,
        string $attachmentId,
        string $originalFilename,
        string $storagePath,
        ?string $previewStoragePath,
        string $mimeType,
        int $sizeBytes,
        string $sha256,
        ?int $width,
        ?int $height,
        string $expiresAt
    ): ?array {
        $attachmentId = $this->normalizePublicId($attachmentId);
        $originalFilename = $this->normalizeFilename($originalFilename);
        $storagePath = $this->normalizeStoragePath($storagePath);
        $previewStoragePath = $previewStoragePath !== null ? $this->normalizeStoragePath($previewStoragePath) : null;
        $sha256 = strtolower(trim($sha256));
        if (
            $messageId <= 0
            || $attachmentId === ''
            || $originalFilename === ''
            || $storagePath === ''
            || trim($mimeType) === ''
            || $sizeBytes <= 0
            || preg_match('/\A[a-f0-9]{64}\z/', $sha256) !== 1
            || $expiresAt === ''
        ) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "INSERT INTO `%s`
                    (`message_id`, `attachment_id`, `original_filename`, `storage_path`, `preview_storage_path`, `mime_type`, `size_bytes`, `sha256`, `width`, `height`, `expires_at`)
                 VALUES
                    (:message_id, :attachment_id, :original_filename, :storage_path, :preview_storage_path, :mime_type, :size_bytes, :sha256, :width, :height, :expires_at)",
                $this->attachmentTable()
            ));
            $statement->execute([
                'message_id' => $messageId,
                'attachment_id' => $attachmentId,
                'original_filename' => $originalFilename,
                'storage_path' => $storagePath,
                'preview_storage_path' => $previewStoragePath,
                'mime_type' => strtolower(trim($mimeType)),
                'size_bytes' => $sizeBytes,
                'sha256' => $sha256,
                'width' => $width,
                'height' => $height,
                'expires_at' => $expiresAt,
            ]);
            $id = (int) $this->database->pdo()->lastInsertId();
        } catch (\Throwable) {
            return null;
        }

        return $id > 0 ? $this->findAttachmentByPublicId($attachmentId) : null;
    }

    public function findAttachmentForUser(string $attachmentId, int $userId): ?array
    {
        $attachmentId = $this->normalizePublicId($attachmentId);
        if ($attachmentId === '' || $userId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT a.*, m.`conversation_id`, m.`sender_private_user_id`
                 FROM `%s` a
                 INNER JOIN `%s` m ON m.`id` = a.`message_id`
                 INNER JOIN `%s` cm ON cm.`conversation_id` = m.`conversation_id`
                 WHERE a.`attachment_id` = :attachment_id
                   AND a.`purge_status` = 'active'
                   AND a.`expires_at` > :now
                   AND m.`purge_status` = 'active'
                   AND cm.`private_user_id` = :user_id
                   AND cm.`left_at` IS NULL
                 LIMIT 1",
                $this->attachmentTable(),
                $this->messageTable(),
                $this->memberTable()
            ));
            $statement->execute([
                'attachment_id' => $attachmentId,
                'now' => $this->now(),
                'user_id' => $userId,
            ]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->hydrateAttachment($row) : null;
    }

    public function markConversationRead(int $conversationId, int $userId, ?int $messageId = null): bool
    {
        if ($conversationId <= 0 || $userId <= 0 || !$this->isParticipant($conversationId, $userId)) {
            return false;
        }

        try {
            $this->ensureSchema();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();
            $messageId = $messageId ?? $this->latestMessageId($conversationId);
            if ($messageId !== null && $messageId > 0) {
                $statement = $pdo->prepare(sprintf(
                    "INSERT INTO `%s` (`conversation_id`, `message_id`, `private_user_id`, `read_at`)
                     VALUES (:conversation_id, :message_id, :user_id, :read_at)
                     ON DUPLICATE KEY UPDATE `read_at` = VALUES(`read_at`)",
                    $this->readTable()
                ));
                $statement->execute([
                    'conversation_id' => $conversationId,
                    'message_id' => $messageId,
                    'user_id' => $userId,
                    'read_at' => $this->now(),
                ]);
            }

            $memberStatement = $pdo->prepare(sprintf(
                "UPDATE `%s`
                 SET `last_opened_at` = :last_opened_at
                 WHERE `conversation_id` = :conversation_id
                   AND `private_user_id` = :user_id
                   AND `left_at` IS NULL",
                $this->memberTable()
            ));
            $memberStatement->execute([
                'last_opened_at' => $this->now(),
                'conversation_id' => $conversationId,
                'user_id' => $userId,
            ]);
            $pdo->commit();

            return true;
        } catch (\Throwable) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listExpiredAttachmentsForUser(int $userId, int $limit = 100): array
    {
        return $this->listExpiredAttachments(max(1, $limit), $userId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listExpiredAttachmentsAll(int $limit = 200): array
    {
        return $this->listExpiredAttachments(max(1, $limit), null);
    }

    /**
     * @return array<int, int>
     */
    public function listExpiredMessageIdsForUser(int $userId, int $limit = 200): array
    {
        return $this->listExpiredMessageIds(max(1, $limit), $userId);
    }

    /**
     * @return array<int, int>
     */
    public function listExpiredMessageIdsAll(int $limit = 500): array
    {
        return $this->listExpiredMessageIds(max(1, $limit), null);
    }

    public function purgeAttachment(int $attachmentRowId): bool
    {
        if ($attachmentRowId <= 0) {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "UPDATE `%s`
                 SET `storage_path` = '',
                     `preview_storage_path` = NULL,
                     `purge_status` = 'purged'
                 WHERE `id` = :id
                   AND `purge_status` <> 'purged'",
                $this->attachmentTable()
            ));
            $statement->execute(['id' => $attachmentRowId]);

            return $statement->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function purgeMessageContent(int $messageId): bool
    {
        if ($messageId <= 0) {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "UPDATE `%s`
                 SET `body` = NULL,
                     `deleted_at` = :deleted_at,
                     `purge_status` = 'purged'
                 WHERE `id` = :id
                   AND `purge_status` <> 'purged'",
                $this->messageTable()
            ));
            $statement->execute(['deleted_at' => $this->now(), 'id' => $messageId]);

            return $statement->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function createRetentionRun(?int $userId, string $scope): int
    {
        $scope = preg_match('/\A[a-z0-9_-]{1,32}\z/', $scope) === 1 ? $scope : 'scheduled';

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "INSERT INTO `%s` (`private_user_id`, `scope`, `started_at`, `status`)
                 VALUES (:user_id, :scope, :started_at, 'running')",
                $this->retentionRunTable()
            ));
            $statement->execute([
                'user_id' => $userId !== null && $userId > 0 ? $userId : null,
                'scope' => $scope,
                'started_at' => $this->now(),
            ]);

            return (int) $this->database->pdo()->lastInsertId();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function finishRetentionRun(int $runId, int $messageCount, int $attachmentCount, string $status = 'completed', ?string $error = null): void
    {
        if ($runId <= 0) {
            return;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "UPDATE `%s`
                 SET `finished_at` = :finished_at,
                     `purged_messages_count` = :messages,
                     `purged_attachments_count` = :attachments,
                     `status` = :status,
                     `error_message` = :error_message
                 WHERE `id` = :id",
                $this->retentionRunTable()
            ));
            $statement->execute([
                'finished_at' => $this->now(),
                'messages' => max(0, $messageCount),
                'attachments' => max(0, $attachmentCount),
                'status' => substr(trim($status), 0, 32) ?: 'completed',
                'error_message' => $error !== null ? substr(trim($error), 0, 255) : null,
                'id' => $runId,
            ]);
        } catch (\Throwable) {
            return;
        }
    }

    private function conversationTable(): string
    {
        return $this->database->table('discussion_conversations');
    }

    private function memberTable(): string
    {
        return $this->database->table('discussion_conversation_members');
    }

    private function messageTable(): string
    {
        return $this->database->table('discussion_messages');
    }

    private function attachmentTable(): string
    {
        return $this->database->table('discussion_message_attachments');
    }

    private function readTable(): string
    {
        return $this->database->table('discussion_message_reads');
    }

    private function retentionRunTable(): string
    {
        return $this->database->table('discussion_retention_runs');
    }

    private function conversationIdByDirectKey(PDO $pdo, string $directKey): ?int
    {
        $statement = $pdo->prepare(sprintf(
            "SELECT `id` FROM `%s` WHERE `direct_key` = :direct_key AND `archived_at` IS NULL LIMIT 1",
            $this->conversationTable()
        ));
        $statement->execute(['direct_key' => $directKey]);
        $id = $statement->fetchColumn();
        $id = is_numeric($id) ? (int) $id : 0;

        return $id > 0 ? $id : null;
    }

    private function upsertMember(PDO $pdo, int $conversationId, int $userId, string $role): void
    {
        $role = in_array($role, ['owner', 'member'], true) ? $role : 'member';
        $statement = $pdo->prepare(sprintf(
            "INSERT INTO `%s` (`conversation_id`, `private_user_id`, `role`, `joined_at`, `left_at`)
             VALUES (:conversation_id, :user_id, :role, :joined_at, NULL)
             ON DUPLICATE KEY UPDATE
                `role` = IF(`role` = 'owner', `role`, VALUES(`role`)),
                `left_at` = NULL",
            $this->memberTable()
        ));
        $statement->execute([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'role' => $role,
            'joined_at' => $this->now(),
        ]);
    }

    private function touchConversation(int $conversationId, string $dateTime): void
    {
        $statement = $this->database->pdo()->prepare(sprintf(
            "UPDATE `%s`
             SET `updated_at` = :updated_at,
                 `last_message_at` = :last_message_at
             WHERE `id` = :id",
            $this->conversationTable()
        ));
        $statement->execute(['updated_at' => $dateTime, 'last_message_at' => $dateTime, 'id' => $conversationId]);
    }

    private function latestMessageId(int $conversationId): ?int
    {
        $statement = $this->database->pdo()->prepare(sprintf(
            "SELECT `id`
             FROM `%s`
             WHERE `conversation_id` = :conversation_id
               AND `purge_status` = 'active'
             ORDER BY `id` DESC
             LIMIT 1",
            $this->messageTable()
        ));
        $statement->execute(['conversation_id' => $conversationId]);
        $id = $statement->fetchColumn();
        $id = is_numeric($id) ? (int) $id : 0;

        return $id > 0 ? $id : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listAttachmentsForMessage(int $messageId): array
    {
        if ($messageId <= 0) {
            return [];
        }

        try {
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT *
                 FROM `%s`
                 WHERE `message_id` = :message_id
                   AND `purge_status` = 'active'
                 ORDER BY `id` ASC",
                $this->attachmentTable()
            ));
            $statement->execute(['message_id' => $messageId]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return $this->hydrateAttachments(is_array($rows) ? $rows : []);
    }

    private function findAttachmentByPublicId(string $attachmentId): ?array
    {
        try {
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT * FROM `%s` WHERE `attachment_id` = :attachment_id LIMIT 1",
                $this->attachmentTable()
            ));
            $statement->execute(['attachment_id' => $attachmentId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->hydrateAttachment($row) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listExpiredAttachments(int $limit, ?int $userId): array
    {
        try {
            $this->ensureSchema();
            $join = $userId !== null ? sprintf("INNER JOIN `%s` cm ON cm.`conversation_id` = m.`conversation_id`", $this->memberTable()) : '';
            $userCondition = $userId !== null ? 'AND cm.`private_user_id` = :user_id AND cm.`left_at` IS NULL' : '';
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT DISTINCT a.*
                 FROM `%s` a
                 INNER JOIN `%s` m ON m.`id` = a.`message_id`
                 %s
                 WHERE a.`purge_status` <> 'purged'
                   AND a.`expires_at` <= :now
                   %s
                 ORDER BY a.`expires_at` ASC, a.`id` ASC
                 LIMIT :limit",
                $this->attachmentTable(),
                $this->messageTable(),
                $join,
                $userCondition
            ));
            if ($userId !== null) {
                $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
            }
            $statement->bindValue(':now', $this->now(), PDO::PARAM_STR);
            $statement->bindValue(':limit', min(1000, $limit), PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return $this->hydrateAttachments(is_array($rows) ? $rows : []);
    }

    /**
     * @return array<int, int>
     */
    private function listExpiredMessageIds(int $limit, ?int $userId): array
    {
        try {
            $this->ensureSchema();
            $join = $userId !== null ? sprintf("INNER JOIN `%s` cm ON cm.`conversation_id` = m.`conversation_id`", $this->memberTable()) : '';
            $userCondition = $userId !== null ? 'AND cm.`private_user_id` = :user_id AND cm.`left_at` IS NULL' : '';
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT m.`id`
                 FROM `%s` m
                 %s
                 WHERE m.`purge_status` <> 'purged'
                   AND m.`expires_at` <= :now
                   %s
                 ORDER BY m.`expires_at` ASC, m.`id` ASC
                 LIMIT :limit",
                $this->messageTable(),
                $join,
                $userCondition
            ));
            if ($userId !== null) {
                $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
            }
            $statement->bindValue(':now', $this->now(), PDO::PARAM_STR);
            $statement->bindValue(':limit', min(1000, $limit), PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $row): int => is_numeric($row) ? (int) $row : 0,
            is_array($rows) ? $rows : []
        ))));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function hydrateConversations(array $rows): array
    {
        $conversations = [];
        foreach ($rows as $row) {
            $conversation = $this->hydrateConversation($row);
            if (is_array($conversation)) {
                $conversations[] = $conversation;
            }
        }

        return $conversations;
    }

    private function hydrateConversation(array $row): ?array
    {
        $id = (int) ($row['id'] ?? 0);
        $type = is_string($row['type'] ?? null) ? strtolower(trim($row['type'])) : '';
        if ($id <= 0 || !in_array($type, ['direct', 'group'], true)) {
            return null;
        }

        return [
            'id' => $id,
            'type' => $type,
            'title' => is_string($row['title'] ?? null) ? (string) $row['title'] : '',
            'createdByPrivateUserId' => (int) ($row['created_by_private_user_id'] ?? 0),
            'createdAt' => is_string($row['created_at'] ?? null) ? (string) $row['created_at'] : '',
            'updatedAt' => is_string($row['updated_at'] ?? null) ? (string) $row['updated_at'] : '',
            'lastMessageAt' => is_string($row['last_message_at'] ?? null) ? (string) $row['last_message_at'] : '',
            'role' => is_string($row['role'] ?? null) ? (string) $row['role'] : '',
            'lastOpenedAt' => is_string($row['last_opened_at'] ?? null) ? (string) $row['last_opened_at'] : '',
            'unreadCount' => max(0, (int) ($row['unread_count'] ?? 0)),
            'lastBody' => is_string($row['last_body'] ?? null) ? (string) $row['last_body'] : '',
        ];
    }

    private function hydrateMessage(array $row): ?array
    {
        $id = (int) ($row['id'] ?? 0);
        $conversationId = (int) ($row['conversation_id'] ?? 0);
        $senderId = (int) ($row['sender_private_user_id'] ?? 0);
        if ($id <= 0 || $conversationId <= 0 || $senderId <= 0) {
            return null;
        }

        return [
            'id' => $id,
            'conversationId' => $conversationId,
            'senderPrivateUserId' => $senderId,
            'body' => is_string($row['body'] ?? null) ? (string) $row['body'] : '',
            'createdAt' => is_string($row['created_at'] ?? null) ? (string) $row['created_at'] : '',
            'expiresAt' => is_string($row['expires_at'] ?? null) ? (string) $row['expires_at'] : '',
            'purgeStatus' => is_string($row['purge_status'] ?? null) ? (string) $row['purge_status'] : '',
            'attachments' => [],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function hydrateAttachments(array $rows): array
    {
        $attachments = [];
        foreach ($rows as $row) {
            $attachment = $this->hydrateAttachment($row);
            if (is_array($attachment)) {
                $attachments[] = $attachment;
            }
        }

        return $attachments;
    }

    private function hydrateAttachment(array $row): ?array
    {
        $id = (int) ($row['id'] ?? 0);
        $messageId = (int) ($row['message_id'] ?? 0);
        $attachmentId = is_string($row['attachment_id'] ?? null) ? (string) $row['attachment_id'] : '';
        if ($id <= 0 || $messageId <= 0 || $attachmentId === '') {
            return null;
        }

        return [
            'id' => $id,
            'messageId' => $messageId,
            'attachmentId' => $attachmentId,
            'originalFilename' => is_string($row['original_filename'] ?? null) ? (string) $row['original_filename'] : '',
            'storagePath' => is_string($row['storage_path'] ?? null) ? (string) $row['storage_path'] : '',
            'previewStoragePath' => is_string($row['preview_storage_path'] ?? null) ? (string) $row['preview_storage_path'] : '',
            'mimeType' => is_string($row['mime_type'] ?? null) ? (string) $row['mime_type'] : '',
            'sizeBytes' => max(0, (int) ($row['size_bytes'] ?? 0)),
            'sha256' => is_string($row['sha256'] ?? null) ? (string) $row['sha256'] : '',
            'width' => is_numeric($row['width'] ?? null) ? (int) $row['width'] : null,
            'height' => is_numeric($row['height'] ?? null) ? (int) $row['height'] : null,
            'createdAt' => is_string($row['created_at'] ?? null) ? (string) $row['created_at'] : '',
            'expiresAt' => is_string($row['expires_at'] ?? null) ? (string) $row['expires_at'] : '',
            'purgeStatus' => is_string($row['purge_status'] ?? null) ? (string) $row['purge_status'] : '',
        ];
    }

    /**
     * @param array<int, int|string> $ids
     * @return array<int, int>
     */
    private function normalizeIds(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            $value = is_numeric($id) ? (int) $id : 0;
            if ($value > 0 && !in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }

    private function normalizeTitle(string $title): string
    {
        $title = sanitize_text_field($title, 160);
        $title = trim((string) preg_replace('/\s+/', ' ', $title));

        return strlen($title) <= 160 ? $title : '';
    }

    private function normalizeBody(string $body): string
    {
        $body = trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $body));

        return strlen($body) <= 4000 ? $body : substr($body, 0, 4000);
    }

    private function normalizePublicId(string $id): string
    {
        $id = trim($id);

        return preg_match('/\A[A-Za-z0-9._-]{1,64}\z/', $id) === 1 ? $id : '';
    }

    private function normalizeFilename(string $filename): string
    {
        $filename = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $filename));
        $filename = trim(str_replace(["\r", "\n", "\t", '/', '\\'], ' ', $filename));
        $filename = preg_replace('/\s+/', ' ', $filename);
        if (!is_string($filename) || $filename === '' || strlen($filename) > 255) {
            return '';
        }

        return $filename;
    }

    private function normalizeStoragePath(string $storagePath): string
    {
        $storagePath = trim(str_replace('\\', '/', $storagePath));
        if ($storagePath === '' || strlen($storagePath) > 255 || str_contains($storagePath, '..')) {
            return '';
        }

        return preg_match('/\A[a-z0-9._\/-]+\z/i', $storagePath) === 1 ? $storagePath : '';
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
