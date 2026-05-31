-- FamilyDiscussion neutral notification preferences per conversation member.

CREATE TABLE IF NOT EXISTS car_discussion_notification_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    private_user_id INT NOT NULL,
    mode VARCHAR(16) NOT NULL DEFAULT 'notify',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_discussion_notification_preference (conversation_id, private_user_id),
    KEY idx_discussion_notification_user (private_user_id, mode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
