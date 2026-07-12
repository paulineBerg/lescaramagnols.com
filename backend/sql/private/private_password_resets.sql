-- Private portal IAM schema - password reset workflow
-- reset token values stored in token_hash must be hashed (Argon2id or equivalent).

CREATE TABLE IF NOT EXISTS car_private_password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    private_user_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    requested_at_ip VARCHAR(39) NULL,
    used_at_ip VARCHAR(39) NULL,
    request_user_agent_hash VARCHAR(255) NULL,
    UNIQUE KEY uq_private_password_resets_token (token_hash),
    KEY idx_private_password_resets_user (private_user_id),
    KEY idx_private_password_resets_expires_at (expires_at),
    CONSTRAINT fk_private_password_resets_user
        FOREIGN KEY (private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

