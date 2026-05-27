-- FamilyDiscussion conversation memberships.

CREATE TABLE IF NOT EXISTS car_discussion_conversation_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    private_user_id INT NOT NULL,
    role VARCHAR(16) NOT NULL DEFAULT 'member',
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    left_at DATETIME NULL,
    muted_until DATETIME NULL,
    last_opened_at DATETIME NULL,
    UNIQUE KEY uq_discussion_members_user (conversation_id, private_user_id),
    KEY idx_discussion_members_user_active (private_user_id, left_at),
    KEY idx_discussion_members_conversation (conversation_id, left_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
