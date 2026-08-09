-- FamilyDiscussion conversations.

CREATE TABLE IF NOT EXISTS car_discussion_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(16) NOT NULL,
    direct_key VARCHAR(64) NULL,
    title VARCHAR(160) NULL,
    encryption_secret CHAR(64) NULL,
    created_by_private_user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_message_at DATETIME NULL,
    archived_at DATETIME NULL,
    UNIQUE KEY uq_discussion_conversations_direct_key (direct_key),
    KEY idx_discussion_conversations_last_message (last_message_at),
    KEY idx_discussion_conversations_created_by (created_by_private_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
