<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\FamilyDiscussion\Repository;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class DiscussionRepository
{
    /** @var array<int, string> */
    private const MESSAGE_PURGE_STATUSES = ['active', 'deleted', 'redacted', 'expired', 'purged'];

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
                KEY `idx_discussion_conversations_activity` (`archived_at`, `last_message_at`, `updated_at`, `id`),
                KEY `idx_discussion_conversations_created_by` (`created_by_private_user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            $this->conversationTable()
        ));
        $this->ensureIndex(
            $pdo,
            $this->conversationTable(),
            'idx_discussion_conversations_activity',
            'ADD INDEX `idx_discussion_conversations_activity` (`archived_at`, `last_message_at`, `updated_at`, `id`)'
        );

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
                KEY `idx_discussion_members_user_opened` (`private_user_id`, `left_at`, `last_opened_at`, `conversation_id`),
                KEY `idx_discussion_members_conversation` (`conversation_id`, `left_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            $this->memberTable()
        ));
        $this->ensureIndex(
            $pdo,
            $this->memberTable(),
            'idx_discussion_members_user_opened',
            'ADD INDEX `idx_discussion_members_user_opened` (`private_user_id`, `left_at`, `last_opened_at`, `conversation_id`)'
        );

        $pdo->exec(sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `conversation_id` INT NOT NULL,
                `sender_private_user_id` INT NOT NULL,
                `client_message_id` VARCHAR(80) NULL,
                `body` TEXT NULL,
                `body_format` VARCHAR(16) NOT NULL DEFAULT 'plain',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `edited_at` DATETIME NULL,
                `deleted_at` DATETIME NULL,
                `expires_at` DATETIME NOT NULL,
                `purge_status` VARCHAR(16) NOT NULL DEFAULT 'active',
                `encryption_mode` VARCHAR(32) NOT NULL DEFAULT 'none',
                `encrypted_payload` MEDIUMTEXT NULL,
                `encryption_metadata` TEXT NULL,
                UNIQUE KEY `uq_discussion_messages_client_message` (`conversation_id`, `sender_private_user_id`, `client_message_id`),
                KEY `idx_discussion_messages_conversation` (`conversation_id`, `id`),
                KEY `idx_discussion_messages_conversation_status` (`conversation_id`, `purge_status`, `deleted_at`, `id`),
                KEY `idx_discussion_messages_sender` (`sender_private_user_id`),
                KEY `idx_discussion_messages_expiry` (`expires_at`, `purge_status`),
                KEY `idx_discussion_messages_encryption` (`encryption_mode`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            $this->messageTable()
        ));
        $this->ensureColumn($pdo, $this->messageTable(), 'client_message_id', '`client_message_id` VARCHAR(80) NULL');
        $this->ensureColumn($pdo, $this->messageTable(), 'encryption_mode', "`encryption_mode` VARCHAR(32) NOT NULL DEFAULT 'none'");
        $this->ensureColumn($pdo, $this->messageTable(), 'encrypted_payload', '`encrypted_payload` MEDIUMTEXT NULL');
        $this->ensureColumn($pdo, $this->messageTable(), 'encryption_metadata', '`encryption_metadata` TEXT NULL');
        $this->ensureIndex(
            $pdo,
            $this->messageTable(),
            'uq_discussion_messages_client_message',
            'ADD UNIQUE KEY `uq_discussion_messages_client_message` (`conversation_id`, `sender_private_user_id`, `client_message_id`)'
        );
        $this->ensureIndex(
            $pdo,
            $this->messageTable(),
            'idx_discussion_messages_conversation_status',
            'ADD INDEX `idx_discussion_messages_conversation_status` (`conversation_id`, `purge_status`, `deleted_at`, `id`)'
        );

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
                KEY `idx_discussion_attachments_status_message` (`message_id`, `purge_status`, `id`),
                KEY `idx_discussion_attachments_expiry` (`expires_at`, `purge_status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            $this->attachmentTable()
        ));
        $this->ensureIndex(
            $pdo,
            $this->attachmentTable(),
            'idx_discussion_attachments_status_message',
            'ADD INDEX `idx_discussion_attachments_status_message` (`message_id`, `purge_status`, `id`)'
        );

        $pdo->exec(sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `conversation_id` INT NOT NULL,
                `message_id` INT NOT NULL,
                `private_user_id` INT NOT NULL,
                `read_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_discussion_reads_user_message` (`message_id`, `private_user_id`),
                KEY `idx_discussion_reads_unread` (`conversation_id`, `private_user_id`, `read_at`, `message_id`),
                KEY `idx_discussion_reads_conversation_user` (`conversation_id`, `private_user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            $this->readTable()
        ));
        $this->ensureIndex(
            $pdo,
            $this->readTable(),
            'idx_discussion_reads_unread',
            'ADD INDEX `idx_discussion_reads_unread` (`conversation_id`, `private_user_id`, `read_at`, `message_id`)'
        );

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

        $pdo->exec(sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `private_user_id` INT NOT NULL,
                `device_id` VARCHAR(64) NOT NULL,
                `device_label` VARCHAR(120) NOT NULL DEFAULT '',
                `public_key_jwk` MEDIUMTEXT NOT NULL,
                `algorithm` VARCHAR(64) NOT NULL DEFAULT 'RSA-OAEP-256',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `revoked_at` DATETIME NULL,
                UNIQUE KEY `uq_discussion_crypto_device` (`private_user_id`, `device_id`),
                KEY `idx_discussion_crypto_devices_user` (`private_user_id`, `revoked_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            $this->cryptoDeviceTable()
        ));

        $pdo->exec(sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `conversation_id` INT NOT NULL,
                `private_user_id` INT NOT NULL,
                `device_id` VARCHAR(64) NOT NULL,
                `encrypted_key` MEDIUMTEXT NOT NULL,
                `algorithm` VARCHAR(64) NOT NULL DEFAULT 'RSA-OAEP-256/AES-GCM-256',
                `created_by_private_user_id` INT NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `revoked_at` DATETIME NULL,
                UNIQUE KEY `uq_discussion_conversation_key` (`conversation_id`, `private_user_id`, `device_id`),
                KEY `idx_discussion_conversation_keys_user` (`private_user_id`, `device_id`, `revoked_at`),
                KEY `idx_discussion_conversation_keys_conversation` (`conversation_id`, `revoked_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            $this->conversationKeyTable()
        ));

        $pdo->exec(sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `conversation_id` INT NOT NULL,
                `actor_user_id` INT NULL,
                `event_type` VARCHAR(64) NOT NULL,
                `event_payload_json` TEXT NULL,
                `client_event_id` VARCHAR(120) NULL,
                `request_id` VARCHAR(128) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_discussion_events_client` (`conversation_id`, `client_event_id`),
                KEY `idx_discussion_events_conversation` (`conversation_id`, `id`),
                KEY `idx_discussion_events_type` (`event_type`, `created_at`),
                KEY `idx_discussion_events_actor` (`actor_user_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            $this->eventTable()
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
                        SELECT CASE
                            WHEN last_message.`encryption_mode` <> 'none' THEN '[message chiffre]'
                            ELSE last_message.`body`
                        END
                        FROM `%s` last_message
                        WHERE last_message.`conversation_id` = c.`id`
                          AND last_message.`purge_status` = 'active'
                          AND last_message.`deleted_at` IS NULL
                        ORDER BY last_message.`id` DESC
                        LIMIT 1
                    ) AS last_body,
                    (
                        SELECT other_user.`email`
                        FROM `%s` other_cm
                        INNER JOIN `%s` other_user ON other_user.`id` = other_cm.`private_user_id`
                        WHERE other_cm.`conversation_id` = c.`id`
                          AND other_cm.`private_user_id` <> :direct_user_id
                          AND other_cm.`left_at` IS NULL
                        ORDER BY other_cm.`private_user_id` ASC
                        LIMIT 1
                    ) AS direct_member_email
                 FROM `%s` c
                 INNER JOIN `%s` cm ON cm.`conversation_id` = c.`id`
                 WHERE cm.`private_user_id` = :user_id
                   AND cm.`left_at` IS NULL
                   AND c.`archived_at` IS NULL
                 ORDER BY COALESCE(c.`last_message_at`, c.`updated_at`, c.`created_at`) DESC, c.`id` DESC
                 LIMIT :limit",
                $this->messageTable(),
                $this->messageTable(),
                $this->memberTable(),
                $this->privateUserTable(),
                $this->conversationTable(),
                $this->memberTable()
            ));
            $statement->bindValue(':unread_user_id', $userId, PDO::PARAM_INT);
            $statement->bindValue(':direct_user_id', $userId, PDO::PARAM_INT);
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
                "SELECT c.*, cm.`role`, cm.`last_opened_at`, 0 AS unread_count, NULL AS last_body,
                    (
                        SELECT other_user.`email`
                        FROM `%s` other_cm
                        INNER JOIN `%s` other_user ON other_user.`id` = other_cm.`private_user_id`
                        WHERE other_cm.`conversation_id` = c.`id`
                          AND other_cm.`private_user_id` <> :direct_user_id
                          AND other_cm.`left_at` IS NULL
                        ORDER BY other_cm.`private_user_id` ASC
                        LIMIT 1
                    ) AS direct_member_email
                 FROM `%s` c
                 INNER JOIN `%s` cm ON cm.`conversation_id` = c.`id`
                 WHERE c.`id` = :conversation_id
                   AND cm.`private_user_id` = :user_id
                   AND cm.`left_at` IS NULL
                   AND c.`archived_at` IS NULL
                 LIMIT 1",
                $this->memberTable(),
                $this->privateUserTable(),
                $this->conversationTable(),
                $this->memberTable()
            ));
            $statement->execute([
                'conversation_id' => $conversationId,
                'direct_user_id' => $userId,
                'user_id' => $userId,
            ]);
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

    public function createMessage(
        int $conversationId,
        int $senderUserId,
        string $body,
        string $expiresAt,
        string $encryptionMode = 'none',
        ?string $encryptedPayload = null,
        ?string $encryptionMetadata = null,
        ?string $clientMessageId = null
    ): ?array {
        $body = $this->normalizeBody($body);
        $encryptionMode = $this->normalizeEncryptionMode($encryptionMode);
        $encryptedPayload = $encryptedPayload !== null ? $this->normalizeEncryptedPayload($encryptedPayload) : null;
        $encryptionMetadata = $encryptionMetadata !== null ? $this->normalizeEncryptionMetadata($encryptionMetadata) : null;
        $clientMessageId = $clientMessageId !== null ? $this->normalizeClientMessageId($clientMessageId) : null;
        if ($encryptionMode === 'none') {
            $encryptedPayload = null;
            $encryptionMetadata = null;
        } elseif ($encryptedPayload === '' || $encryptionMetadata === '') {
            return null;
        }

        if ($conversationId <= 0 || $senderUserId <= 0 || $expiresAt === '' || ($body === '' && $encryptedPayload === null)) {
            return null;
        }

        try {
            $this->ensureSchema();
            $now = $this->now();
            $pdo = $this->database->pdo();
            if ($clientMessageId !== null) {
                $existing = $this->findMessageByClientId($conversationId, $senderUserId, $clientMessageId);
                if (is_array($existing)) {
                    $existing['idempotentReplay'] = true;

                    return $existing;
                }
            }

            $statement = $pdo->prepare(sprintf(
                "INSERT INTO `%s`
                    (`conversation_id`, `sender_private_user_id`, `client_message_id`, `body`, `body_format`, `created_at`, `expires_at`, `encryption_mode`, `encrypted_payload`, `encryption_metadata`)
                 VALUES
                    (:conversation_id, :sender_id, :client_message_id, :body, :body_format, :created_at, :expires_at, :encryption_mode, :encrypted_payload, :encryption_metadata)",
                $this->messageTable()
            ));
            $statement->execute([
                'conversation_id' => $conversationId,
                'sender_id' => $senderUserId,
                'client_message_id' => $clientMessageId,
                'body' => $encryptionMode === 'none' && $body !== '' ? $body : null,
                'body_format' => $encryptionMode === 'none' ? 'plain' : 'encrypted',
                'created_at' => $now,
                'expires_at' => $expiresAt,
                'encryption_mode' => $encryptionMode,
                'encrypted_payload' => $encryptedPayload,
                'encryption_metadata' => $encryptionMetadata,
            ]);
            $messageId = (int) $pdo->lastInsertId();
            $this->touchConversation($conversationId, $now);
        } catch (\Throwable) {
            if ($clientMessageId !== null) {
                $existing = $this->findMessageByClientId($conversationId, $senderUserId, $clientMessageId);
                if (is_array($existing)) {
                    $existing['idempotentReplay'] = true;

                    return $existing;
                }
            }

            return null;
        }

        return $messageId > 0 ? $this->findMessageById($messageId) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMessagesForUser(int $conversationId, int $userId, int $afterMessageId = 0, int $limit = 100): array
    {
        return $this->findMessagesAfter($conversationId, $userId, $afterMessageId, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findMessagesAfter(int $conversationId, int $userId, int $afterMessageId = 0, int $limit = 100): array
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findMessagesBefore(int $conversationId, int $userId, int $beforeMessageId, int $limit = 100): array
    {
        if ($conversationId <= 0 || $userId <= 0 || $beforeMessageId <= 0 || !$this->isParticipant($conversationId, $userId)) {
            return [];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT *
                 FROM `%s`
                 WHERE `conversation_id` = :conversation_id
                   AND `id` < :before_message_id
                   AND `purge_status` = 'active'
                   AND `deleted_at` IS NULL
                 ORDER BY `id` DESC
                 LIMIT :limit",
                $this->messageTable()
            ));
            $statement->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
            $statement->bindValue(':before_message_id', $beforeMessageId, PDO::PARAM_INT);
            $statement->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $messages = [];
        foreach (array_reverse(is_array($rows) ? $rows : []) as $row) {
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findConversationTimeline(int $conversationId, int $userId, int $limit = 100): array
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
                   AND `purge_status` = 'active'
                   AND `deleted_at` IS NULL
                 ORDER BY `id` DESC
                 LIMIT :limit",
                $this->messageTable()
            ));
            $statement->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
            $statement->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $messages = [];
        foreach (array_reverse(is_array($rows) ? $rows : []) as $row) {
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

    /**
     * @return array<int, int>
     */
    public function findUnreadCountsForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT cm.`conversation_id`, COUNT(m.`id`) AS unread_count
                 FROM `%s` cm
                 INNER JOIN `%s` m ON m.`conversation_id` = cm.`conversation_id`
                 WHERE cm.`private_user_id` = :user_id
                   AND cm.`left_at` IS NULL
                   AND m.`sender_private_user_id` <> :message_user_id
                   AND m.`purge_status` = 'active'
                   AND m.`deleted_at` IS NULL
                   AND (cm.`last_opened_at` IS NULL OR m.`created_at` > cm.`last_opened_at`)
                 GROUP BY cm.`conversation_id`",
                $this->memberTable(),
                $this->messageTable()
            ));
            $statement->execute(['user_id' => $userId, 'message_user_id' => $userId]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $counts = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $conversationId = is_numeric($row['conversation_id'] ?? null) ? (int) $row['conversation_id'] : 0;
            if ($conversationId > 0) {
                $counts[$conversationId] = max(0, (int) ($row['unread_count'] ?? 0));
            }
        }

        return $counts;
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

    public function findMessageByClientId(int $conversationId, int $senderUserId, string $clientMessageId): ?array
    {
        $clientMessageId = $this->normalizeClientMessageId($clientMessageId);
        if ($conversationId <= 0 || $senderUserId <= 0 || $clientMessageId === null) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT *
                 FROM `%s`
                 WHERE `conversation_id` = :conversation_id
                   AND `sender_private_user_id` = :sender_user_id
                   AND `client_message_id` = :client_message_id
                 LIMIT 1",
                $this->messageTable()
            ));
            $statement->execute([
                'conversation_id' => $conversationId,
                'sender_user_id' => $senderUserId,
                'client_message_id' => $clientMessageId,
            ]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($row)) {
            return null;
        }

        $message = $this->hydrateMessage($row);
        if (is_array($message)) {
            $message['attachments'] = $this->listAttachmentsForMessage((int) $message['id']);
        }

        return $message;
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
                 SET `storage_path` = CONCAT('purged/', `id`),
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

    public function redactAttachment(int $attachmentRowId, string $status = 'deleted'): bool
    {
        if ($attachmentRowId <= 0) {
            return false;
        }

        $status = $this->normalizePurgeStatus($status, 'deleted');
        if ($status === 'active' || $status === 'purged') {
            $status = 'deleted';
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "UPDATE `%s`
                 SET `storage_path` = CONCAT(:purge_status, '/', `id`),
                     `preview_storage_path` = NULL,
                     `purge_status` = :purge_status
                 WHERE `id` = :id
                   AND `purge_status` = 'active'",
                $this->attachmentTable()
            ));
            $statement->execute(['purge_status' => $status, 'id' => $attachmentRowId]);

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
                     `encrypted_payload` = NULL,
                     `encryption_metadata` = NULL,
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

    public function redactMessageContent(int $messageId, string $status = 'deleted'): bool
    {
        if ($messageId <= 0) {
            return false;
        }

        $status = $this->normalizePurgeStatus($status, 'deleted');
        if ($status === 'active' || $status === 'purged') {
            $status = 'deleted';
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "UPDATE `%s`
                 SET `body` = NULL,
                     `encrypted_payload` = NULL,
                     `encryption_metadata` = NULL,
                     `deleted_at` = :deleted_at,
                     `purge_status` = :purge_status
                 WHERE `id` = :id
                   AND `purge_status` = 'active'",
                $this->messageTable()
            ));
            $statement->execute(['deleted_at' => $this->now(), 'purge_status' => $status, 'id' => $messageId]);

            return $statement->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function findMessageForUser(int $messageId, int $userId): ?array
    {
        if ($messageId <= 0 || $userId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT m.*
                 FROM `%s` m
                 INNER JOIN `%s` cm ON cm.`conversation_id` = m.`conversation_id`
                 WHERE m.`id` = :message_id
                   AND cm.`private_user_id` = :user_id
                   AND cm.`left_at` IS NULL
                   AND m.`purge_status` = 'active'
                   AND m.`deleted_at` IS NULL
                 LIMIT 1",
                $this->messageTable(),
                $this->memberTable()
            ));
            $statement->execute(['message_id' => $messageId, 'user_id' => $userId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->hydrateMessage($row) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listActiveAttachmentsForMessage(int $messageId): array
    {
        if ($messageId <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT * FROM `%s`
                 WHERE `message_id` = :message_id
                   AND `purge_status` = 'active'",
                $this->attachmentTable()
            ));
            $statement->execute(['message_id' => $messageId]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (array $row): ?array => $this->hydrateAttachment($row),
            is_array($rows) ? $rows : []
        )));
    }

    /**
     * @return array<int, int>
     */
    public function listActiveMessageIdsForSender(int $conversationId, int $userId): array
    {
        if ($conversationId <= 0 || $userId <= 0 || !$this->isParticipant($conversationId, $userId)) {
            return [];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT `id`
                 FROM `%s`
                 WHERE `conversation_id` = :conversation_id
                   AND `sender_private_user_id` = :user_id
                   AND `purge_status` = 'active'
                   AND `deleted_at` IS NULL",
                $this->messageTable()
            ));
            $statement->execute(['conversation_id' => $conversationId, 'user_id' => $userId]);
            $ids = $statement->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, is_array($ids) ? $ids : []),
            static fn (int $id): bool => $id > 0
        ));
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

    public function registerCryptoDevice(int $userId, string $deviceId, string $publicKeyJwk, string $label = ''): ?array
    {
        $deviceId = $this->normalizeDeviceId($deviceId);
        $publicKeyJwk = $this->normalizePublicKeyJwk($publicKeyJwk);
        $label = $this->normalizeDeviceLabel($label);
        if ($userId <= 0 || $deviceId === '' || $publicKeyJwk === '') {
            return null;
        }

        try {
            $this->ensureSchema();
            $now = $this->now();
            $statement = $this->database->pdo()->prepare(sprintf(
                "INSERT INTO `%s`
                    (`private_user_id`, `device_id`, `device_label`, `public_key_jwk`, `algorithm`, `created_at`, `last_seen_at`, `revoked_at`)
                 VALUES
                    (:user_id, :device_id, :device_label, :public_key_jwk, 'RSA-OAEP-256', :created_at, :last_seen_at, NULL)
                 ON DUPLICATE KEY UPDATE
                    `device_label` = VALUES(`device_label`),
                    `public_key_jwk` = VALUES(`public_key_jwk`),
                    `last_seen_at` = VALUES(`last_seen_at`),
                    `revoked_at` = NULL",
                $this->cryptoDeviceTable()
            ));
            $statement->execute([
                'user_id' => $userId,
                'device_id' => $deviceId,
                'device_label' => $label,
                'public_key_jwk' => $publicKeyJwk,
                'created_at' => $now,
                'last_seen_at' => $now,
            ]);
        } catch (\Throwable) {
            return null;
        }

        return $this->findCryptoDevice($userId, $deviceId);
    }

    public function findCryptoDevice(int $userId, string $deviceId): ?array
    {
        $deviceId = $this->normalizeDeviceId($deviceId);
        if ($userId <= 0 || $deviceId === '') {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT *
                 FROM `%s`
                 WHERE `private_user_id` = :user_id
                   AND `device_id` = :device_id
                   AND `revoked_at` IS NULL
                 LIMIT 1",
                $this->cryptoDeviceTable()
            ));
            $statement->execute(['user_id' => $userId, 'device_id' => $deviceId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->hydrateCryptoDevice($row) : null;
    }

    public function revokeCryptoDevice(int $userId, string $deviceId): bool
    {
        $deviceId = $this->normalizeDeviceId($deviceId);
        if ($userId <= 0 || $deviceId === '') {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "UPDATE `%s`
                 SET `revoked_at` = COALESCE(`revoked_at`, :revoked_at)
                 WHERE `private_user_id` = :user_id
                   AND `device_id` = :device_id
                   AND `revoked_at` IS NULL",
                $this->cryptoDeviceTable()
            ));
            $statement->execute([
                'revoked_at' => $this->now(),
                'user_id' => $userId,
                'device_id' => $deviceId,
            ]);

            return $statement->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listConversationMembers(int $conversationId): array
    {
        if ($conversationId <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT `private_user_id`, `role`, `joined_at`, `last_opened_at`
                 FROM `%s`
                 WHERE `conversation_id` = :conversation_id
                   AND `left_at` IS NULL
                 ORDER BY `role` DESC, `private_user_id` ASC",
                $this->memberTable()
            ));
            $statement->execute(['conversation_id' => $conversationId]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $members = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row) || !is_numeric($row['private_user_id'] ?? null)) {
                continue;
            }

            $members[] = [
                'privateUserId' => (int) $row['private_user_id'],
                'role' => is_string($row['role'] ?? null) ? (string) $row['role'] : 'member',
                'joinedAt' => is_string($row['joined_at'] ?? null) ? (string) $row['joined_at'] : '',
                'lastOpenedAt' => is_string($row['last_opened_at'] ?? null) ? (string) $row['last_opened_at'] : '',
            ];
        }

        return $members;
    }

    /**
     * @param array<int, int> $userIds
     * @return array<int, array<string, mixed>>
     */
    public function listCryptoDevicesForUsers(array $userIds): array
    {
        $userIds = $this->normalizeIds($userIds);
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT *
                 FROM `%s`
                 WHERE `private_user_id` IN (%s)
                   AND `revoked_at` IS NULL
                 ORDER BY `private_user_id` ASC, `last_seen_at` DESC, `id` DESC",
                $this->cryptoDeviceTable(),
                $placeholders
            ));
            foreach ($userIds as $index => $userId) {
                $statement->bindValue($index + 1, $userId, PDO::PARAM_INT);
            }
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $devices = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $device = $this->hydrateCryptoDevice($row);
            if (is_array($device)) {
                $devices[] = $device;
            }
        }

        return $devices;
    }

    /**
     * @param array<int, array<string, mixed>> $wrappers
     */
    public function upsertConversationKeys(int $conversationId, int $createdByUserId, array $wrappers): int
    {
        if ($conversationId <= 0 || $createdByUserId <= 0 || $wrappers === []) {
            return 0;
        }

        $members = array_map(
            static fn (array $member): int => (int) ($member['privateUserId'] ?? 0),
            $this->listConversationMembers($conversationId)
        );
        $members = array_values(array_filter($members, static fn (int $id): bool => $id > 0));
        if (!in_array($createdByUserId, $members, true)) {
            return 0;
        }

        $validDeviceKeys = [];
        foreach ($this->listCryptoDevicesForUsers($members) as $device) {
            $validDeviceKeys[(int) ($device['privateUserId'] ?? 0) . ':' . (string) ($device['deviceId'] ?? '')] = true;
        }

        $inserted = 0;
        try {
            $this->ensureSchema();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();
            $statement = $pdo->prepare(sprintf(
                "INSERT INTO `%s`
                    (`conversation_id`, `private_user_id`, `device_id`, `encrypted_key`, `algorithm`, `created_by_private_user_id`, `created_at`, `revoked_at`)
                 VALUES
                    (:conversation_id, :user_id, :device_id, :encrypted_key, 'RSA-OAEP-256/AES-GCM-256', :created_by, :created_at, NULL)
                 ON DUPLICATE KEY UPDATE
                    `encrypted_key` = VALUES(`encrypted_key`),
                    `algorithm` = VALUES(`algorithm`),
                    `created_by_private_user_id` = VALUES(`created_by_private_user_id`),
                    `created_at` = VALUES(`created_at`),
                    `revoked_at` = NULL",
                $this->conversationKeyTable()
            ));
            foreach ($wrappers as $wrapper) {
                $recipientUserId = is_numeric($wrapper['privateUserId'] ?? null) ? (int) $wrapper['privateUserId'] : 0;
                $deviceId = $this->normalizeDeviceId(is_string($wrapper['deviceId'] ?? null) ? (string) $wrapper['deviceId'] : '');
                $encryptedKey = $this->normalizeEncryptedKey(is_string($wrapper['encryptedKey'] ?? null) ? (string) $wrapper['encryptedKey'] : '');
                if ($recipientUserId <= 0 || $deviceId === '' || $encryptedKey === '') {
                    continue;
                }
                if (empty($validDeviceKeys[$recipientUserId . ':' . $deviceId])) {
                    continue;
                }

                $statement->execute([
                    'conversation_id' => $conversationId,
                    'user_id' => $recipientUserId,
                    'device_id' => $deviceId,
                    'encrypted_key' => $encryptedKey,
                    'created_by' => $createdByUserId,
                    'created_at' => $this->now(),
                ]);
                ++$inserted;
            }
            $pdo->commit();
        } catch (\Throwable) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return 0;
        }

        return $inserted;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listConversationKeysForUser(int $conversationId, int $userId): array
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
                   AND `private_user_id` = :user_id
                   AND `revoked_at` IS NULL
                 ORDER BY `created_at` DESC, `id` DESC",
                $this->conversationKeyTable()
            ));
            $statement->execute(['conversation_id' => $conversationId, 'user_id' => $userId]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $keys = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row) || !is_numeric($row['id'] ?? null)) {
                continue;
            }

            $keys[] = [
                'id' => (int) $row['id'],
                'conversationId' => (int) ($row['conversation_id'] ?? 0),
                'privateUserId' => (int) ($row['private_user_id'] ?? 0),
                'deviceId' => is_string($row['device_id'] ?? null) ? (string) $row['device_id'] : '',
                'encryptedKey' => is_string($row['encrypted_key'] ?? null) ? (string) $row['encrypted_key'] : '',
                'algorithm' => is_string($row['algorithm'] ?? null) ? (string) $row['algorithm'] : '',
                'createdByPrivateUserId' => (int) ($row['created_by_private_user_id'] ?? 0),
                'createdAt' => is_string($row['created_at'] ?? null) ? (string) $row['created_at'] : '',
            ];
        }

        return $keys;
    }

    public function countConversationKeys(int $conversationId): int
    {
        if ($conversationId <= 0) {
            return 0;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT COUNT(*)
                 FROM `%s`
                 WHERE `conversation_id` = :conversation_id
                   AND `revoked_at` IS NULL",
                $this->conversationKeyTable()
            ));
            $statement->execute(['conversation_id' => $conversationId]);

            return max(0, (int) $statement->fetchColumn());
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

    /**
     * @param array<string, mixed> $payload
     */
    public function createConversationEvent(
        int $conversationId,
        ?int $actorUserId,
        string $eventType,
        array $payload = [],
        ?string $clientEventId = null,
        ?string $requestId = null
    ): ?array {
        $eventType = $this->normalizeEventType($eventType);
        $clientEventId = $clientEventId !== null ? $this->normalizeClientEventId($clientEventId) : null;
        $requestId = $requestId !== null ? $this->normalizeRequestId($requestId) : null;
        $actorUserId = $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null;
        if ($conversationId <= 0 || $eventType === '') {
            return null;
        }

        $payload = $this->normalizeEventPayload($payload);
        $payloadJson = $payload !== [] ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        if ($payloadJson === false) {
            return null;
        }

        try {
            $this->ensureSchema();
            if ($clientEventId !== null) {
                $existing = $this->findConversationEventByClientId($conversationId, $clientEventId);
                if (is_array($existing)) {
                    return $existing;
                }
            }

            $statement = $this->database->pdo()->prepare(sprintf(
                "INSERT INTO `%s`
                    (`conversation_id`, `actor_user_id`, `event_type`, `event_payload_json`, `client_event_id`, `request_id`, `created_at`)
                 VALUES
                    (:conversation_id, :actor_user_id, :event_type, :event_payload_json, :client_event_id, :request_id, :created_at)",
                $this->eventTable()
            ));
            $statement->execute([
                'conversation_id' => $conversationId,
                'actor_user_id' => $actorUserId,
                'event_type' => $eventType,
                'event_payload_json' => is_string($payloadJson) ? $payloadJson : null,
                'client_event_id' => $clientEventId,
                'request_id' => $requestId,
                'created_at' => $this->now(),
            ]);
            $id = (int) $this->database->pdo()->lastInsertId();
        } catch (\Throwable) {
            if ($clientEventId !== null) {
                return $this->findConversationEventByClientId($conversationId, $clientEventId);
            }

            return null;
        }

        return $id > 0 ? $this->findConversationEventById($id) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listConversationEventsAfter(int $conversationId, int $userId, int $afterEventId = 0, int $limit = 100): array
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
                   AND `id` > :after_event_id
                 ORDER BY `id` ASC
                 LIMIT :limit",
                $this->eventTable()
            ));
            $statement->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
            $statement->bindValue(':after_event_id', max(0, $afterEventId), PDO::PARAM_INT);
            $statement->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return $this->hydrateEvents(is_array($rows) ? $rows : []);
    }

    public function findConversationEventById(int $eventId): ?array
    {
        if ($eventId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT * FROM `%s` WHERE `id` = :id LIMIT 1",
                $this->eventTable()
            ));
            $statement->execute(['id' => $eventId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->hydrateEvent($row) : null;
    }

    public function findConversationEventByClientId(int $conversationId, string $clientEventId): ?array
    {
        $clientEventId = $this->normalizeClientEventId($clientEventId);
        if ($conversationId <= 0 || $clientEventId === null) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                "SELECT *
                 FROM `%s`
                 WHERE `conversation_id` = :conversation_id
                   AND `client_event_id` = :client_event_id
                 LIMIT 1",
                $this->eventTable()
            ));
            $statement->execute(['conversation_id' => $conversationId, 'client_event_id' => $clientEventId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->hydrateEvent($row) : null;
    }

    private function conversationTable(): string
    {
        return $this->database->table('discussion_conversations');
    }

    private function privateUserTable(): string
    {
        return $this->database->table('private_users');
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

    private function cryptoDeviceTable(): string
    {
        return $this->database->table('discussion_crypto_devices');
    }

    private function conversationKeyTable(): string
    {
        return $this->database->table('discussion_conversation_keys');
    }

    private function eventTable(): string
    {
        return $this->database->table('discussion_conversation_events');
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
            'directMemberEmail' => is_string($row['direct_member_email'] ?? null)
                ? strtolower(trim((string) $row['direct_member_email']))
                : '',
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
            'clientMessageId' => is_string($row['client_message_id'] ?? null) ? (string) $row['client_message_id'] : '',
            'body' => is_string($row['body'] ?? null) ? (string) $row['body'] : '',
            'createdAt' => is_string($row['created_at'] ?? null) ? (string) $row['created_at'] : '',
            'expiresAt' => is_string($row['expires_at'] ?? null) ? (string) $row['expires_at'] : '',
            'purgeStatus' => is_string($row['purge_status'] ?? null) ? (string) $row['purge_status'] : '',
            'encryptionMode' => is_string($row['encryption_mode'] ?? null) ? (string) $row['encryption_mode'] : 'none',
            'encryptedPayload' => is_string($row['encrypted_payload'] ?? null) ? (string) $row['encrypted_payload'] : '',
            'encryptionMetadata' => is_string($row['encryption_metadata'] ?? null) ? (string) $row['encryption_metadata'] : '',
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

    private function hydrateCryptoDevice(array $row): ?array
    {
        $id = (int) ($row['id'] ?? 0);
        $userId = (int) ($row['private_user_id'] ?? 0);
        $deviceId = is_string($row['device_id'] ?? null) ? (string) $row['device_id'] : '';
        $publicKeyJwk = is_string($row['public_key_jwk'] ?? null) ? (string) $row['public_key_jwk'] : '';
        if ($id <= 0 || $userId <= 0 || $deviceId === '' || $publicKeyJwk === '') {
            return null;
        }

        return [
            'id' => $id,
            'privateUserId' => $userId,
            'deviceId' => $deviceId,
            'deviceLabel' => is_string($row['device_label'] ?? null) ? (string) $row['device_label'] : '',
            'publicKeyJwk' => $publicKeyJwk,
            'algorithm' => is_string($row['algorithm'] ?? null) ? (string) $row['algorithm'] : '',
            'createdAt' => is_string($row['created_at'] ?? null) ? (string) $row['created_at'] : '',
            'lastSeenAt' => is_string($row['last_seen_at'] ?? null) ? (string) $row['last_seen_at'] : '',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function hydrateEvents(array $rows): array
    {
        $events = [];
        foreach ($rows as $row) {
            $event = $this->hydrateEvent($row);
            if (is_array($event)) {
                $events[] = $event;
            }
        }

        return $events;
    }

    private function hydrateEvent(array $row): ?array
    {
        $id = (int) ($row['id'] ?? 0);
        $conversationId = (int) ($row['conversation_id'] ?? 0);
        $eventType = is_string($row['event_type'] ?? null) ? (string) $row['event_type'] : '';
        if ($id <= 0 || $conversationId <= 0 || $eventType === '') {
            return null;
        }

        $payload = [];
        if (is_string($row['event_payload_json'] ?? null) && trim((string) $row['event_payload_json']) !== '') {
            $decoded = json_decode((string) $row['event_payload_json'], true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        return [
            'id' => $id,
            'conversationId' => $conversationId,
            'actorUserId' => is_numeric($row['actor_user_id'] ?? null) ? (int) $row['actor_user_id'] : null,
            'eventType' => $eventType,
            'payload' => $payload,
            'clientEventId' => is_string($row['client_event_id'] ?? null) ? (string) $row['client_event_id'] : '',
            'requestId' => is_string($row['request_id'] ?? null) ? (string) $row['request_id'] : '',
            'createdAt' => is_string($row['created_at'] ?? null) ? (string) $row['created_at'] : '',
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

    private function normalizeEncryptionMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return in_array($mode, ['none', 'client_aes_gcm_v1'], true) ? $mode : 'none';
    }

    private function normalizePurgeStatus(string $status, string $fallback): string
    {
        $status = strtolower(trim($status));
        $fallback = in_array($fallback, self::MESSAGE_PURGE_STATUSES, true) ? $fallback : 'active';

        return in_array($status, self::MESSAGE_PURGE_STATUSES, true) ? $status : $fallback;
    }

    private function normalizeClientMessageId(string $clientMessageId): ?string
    {
        $clientMessageId = trim($clientMessageId);
        if ($clientMessageId === '') {
            return null;
        }

        return preg_match('/\A[A-Za-z0-9._:-]{8,80}\z/', $clientMessageId) === 1 ? $clientMessageId : null;
    }

    private function normalizeClientEventId(string $clientEventId): ?string
    {
        $clientEventId = trim($clientEventId);
        if ($clientEventId === '') {
            return null;
        }

        return preg_match('/\A[A-Za-z0-9._:-]{8,120}\z/', $clientEventId) === 1 ? $clientEventId : null;
    }

    private function normalizeRequestId(string $requestId): ?string
    {
        $requestId = trim($requestId);
        if ($requestId === '') {
            return null;
        }

        return preg_match('/\A[A-Za-z0-9._:-]{1,128}\z/', $requestId) === 1 ? $requestId : null;
    }

    private function normalizeEventType(string $eventType): string
    {
        $eventType = strtolower(trim($eventType));

        return preg_match('/\A[a-z0-9_.-]{3,64}\z/', $eventType) === 1 ? $eventType : '';
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, scalar|null>
     */
    private function normalizeEventPayload(array $payload): array
    {
        $normalized = [];
        foreach ($payload as $key => $value) {
            if (!is_string($key) || preg_match('/\A[a-zA-Z0-9_]{1,48}\z/', $key) !== 1) {
                continue;
            }
            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $normalized[$key] = $value;
                continue;
            }
            if (is_string($value)) {
                $normalized[$key] = mb_substr($value, 0, 190);
            }
        }

        return $normalized;
    }

    private function normalizeEncryptedPayload(string $payload): string
    {
        $payload = trim($payload);
        if ($payload === '' || strlen($payload) > 50000) {
            return '';
        }

        return preg_match('/\A[A-Za-z0-9+\/=_-]+\z/', $payload) === 1 ? $payload : '';
    }

    private function normalizeEncryptionMetadata(string $metadata): string
    {
        $metadata = trim($metadata);
        if ($metadata === '' || strlen($metadata) > 2000) {
            return '';
        }

        $decoded = json_decode($metadata, true);
        if (!is_array($decoded)) {
            return '';
        }

        return $metadata;
    }

    private function normalizeDeviceId(string $deviceId): string
    {
        $deviceId = trim($deviceId);

        return preg_match('/\A[A-Za-z0-9._-]{16,64}\z/', $deviceId) === 1 ? $deviceId : '';
    }

    private function normalizeDeviceLabel(string $label): string
    {
        $label = sanitize_text_field($label, 120);
        $label = trim((string) preg_replace('/\s+/', ' ', $label));

        return strlen($label) <= 120 ? $label : '';
    }

    private function normalizePublicKeyJwk(string $publicKeyJwk): string
    {
        $publicKeyJwk = trim($publicKeyJwk);
        if ($publicKeyJwk === '' || strlen($publicKeyJwk) > 12000) {
            return '';
        }

        $decoded = json_decode($publicKeyJwk, true);
        if (!is_array($decoded) || ($decoded['kty'] ?? '') !== 'RSA' || ($decoded['alg'] ?? '') !== 'RSA-OAEP-256') {
            return '';
        }

        return $publicKeyJwk;
    }

    private function normalizeEncryptedKey(string $encryptedKey): string
    {
        $encryptedKey = trim($encryptedKey);
        if ($encryptedKey === '' || strlen($encryptedKey) > 12000) {
            return '';
        }

        return preg_match('/\A[A-Za-z0-9+\/=_-]+\z/', $encryptedKey) === 1 ? $encryptedKey : '';
    }

    private function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        try {
            $statement = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND COLUMN_NAME = :column"
            );
            $statement->execute(['table' => $table, 'column' => $column]);
            if ((int) $statement->fetchColumn() > 0) {
                return;
            }

            $pdo->exec(sprintf('ALTER TABLE `%s` ADD COLUMN %s', $table, $definition));
        } catch (\Throwable) {
            return;
        }
    }

    private function ensureIndex(PDO $pdo, string $table, string $index, string $definition): void
    {
        try {
            $statement = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND INDEX_NAME = :index_name"
            );
            $statement->execute(['table' => $table, 'index_name' => $index]);
            if ((int) $statement->fetchColumn() > 0) {
                return;
            }

            $pdo->exec(sprintf('ALTER TABLE `%s` %s', $table, $definition));
        } catch (\Throwable) {
            return;
        }
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
