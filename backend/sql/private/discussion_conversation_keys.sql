-- FamilyDiscussion encrypted conversation keys per member device.

CREATE TABLE IF NOT EXISTS car_discussion_conversation_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    private_user_id INT NOT NULL,
    device_id VARCHAR(64) NOT NULL,
    encrypted_key MEDIUMTEXT NOT NULL,
    algorithm VARCHAR(64) NOT NULL DEFAULT 'RSA-OAEP-256/AES-GCM-256',
    created_by_private_user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at DATETIME NULL,
    UNIQUE KEY uq_discussion_conversation_key (conversation_id, private_user_id, device_id),
    KEY idx_discussion_conversation_keys_user (private_user_id, device_id, revoked_at),
    KEY idx_discussion_conversation_keys_conversation (conversation_id, revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
