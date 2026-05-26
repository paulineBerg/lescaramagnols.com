-- Private portal IAM schema - family accounts
-- Tokens and secrets stored hashed; no plaintext secrets in these tables.

CREATE TABLE IF NOT EXISTS car_private_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('invited', 'active', 'suspended', 'disabled', 'deleted') NOT NULL DEFAULT 'invited',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL,
    last_login_ip VARBINARY(16) NULL,
    mfa_enabled TINYINT(1) NOT NULL DEFAULT 0,
    mfa_secret_encrypted VARBINARY(255) NULL,
    UNIQUE KEY uq_private_users_email (email),
    KEY idx_private_users_status (status),
    KEY idx_private_users_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

