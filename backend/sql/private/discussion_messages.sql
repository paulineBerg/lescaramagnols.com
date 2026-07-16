-- FamilyDiscussion messages with short retention.

CREATE TABLE IF NOT EXISTS car_discussion_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_private_user_id INT NOT NULL,
    body TEXT NULL,
    body_format VARCHAR(16) NOT NULL DEFAULT 'plain',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_at DATETIME NULL,
    deleted_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    purge_status VARCHAR(16) NOT NULL DEFAULT 'active',
    encryption_mode VARCHAR(32) NOT NULL DEFAULT 'none',
    encrypted_payload MEDIUMTEXT NULL,
    encryption_metadata TEXT NULL,
    KEY idx_discussion_messages_conversation (conversation_id, id),
    KEY idx_discussion_messages_sender (sender_private_user_id),
    KEY idx_discussion_messages_expiry (expires_at, purge_status),
    KEY idx_discussion_messages_encryption (encryption_mode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
