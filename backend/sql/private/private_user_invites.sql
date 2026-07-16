-- Private portal IAM schema - invitation workflow
-- invitation token values stored in token_hash must be hashed (Argon2id or equivalent).

CREATE TABLE IF NOT EXISTS car_private_user_invites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    private_user_id INT NULL,
    email VARCHAR(254) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    invited_by_admin_id INT NULL,
    status ENUM('pending', 'accepted', 'cancelled', 'expired') NOT NULL DEFAULT 'pending',
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    attempts_count INT NOT NULL DEFAULT 0,
    ip_hash VARCHAR(64) NULL,
    user_agent_hash VARCHAR(255) NULL,
    UNIQUE KEY uq_private_user_invites_token (token_hash),
    KEY idx_private_user_invites_user (private_user_id),
    KEY idx_private_user_invites_email_status (email, status),
    KEY idx_private_user_invites_expires_at (expires_at),
    CONSTRAINT fk_private_user_invites_user
        FOREIGN KEY (private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

