-- Private portal MFA backup codes.
-- Code values are stored hashed and consumed once.

CREATE TABLE IF NOT EXISTS car_private_mfa_backup_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    private_user_id INT NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME NULL,
    KEY idx_private_mfa_backup_codes_user (private_user_id),
    KEY idx_private_mfa_backup_codes_used (used_at),
    CONSTRAINT fk_private_mfa_backup_codes_user
        FOREIGN KEY (private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
