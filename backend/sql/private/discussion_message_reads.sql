-- FamilyDiscussion read markers.

CREATE TABLE IF NOT EXISTS car_discussion_message_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    message_id INT NOT NULL,
    private_user_id INT NOT NULL,
    read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_discussion_reads_user_message (message_id, private_user_id),
    KEY idx_discussion_reads_unread (conversation_id, private_user_id, read_at, message_id),
    KEY idx_discussion_reads_conversation_user (conversation_id, private_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
