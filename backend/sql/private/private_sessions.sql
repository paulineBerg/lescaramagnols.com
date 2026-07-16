-- Private portal IAM schema - dedicated private session store
-- Session identifiers and opaque tokens must remain non-guessable.

CREATE TABLE IF NOT EXISTS car_private_sessions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    private_user_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    php_session_name VARCHAR(64) NOT NULL,
    ip_hash VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    invalidated_at DATETIME NULL,
    user_agent_hash VARCHAR(255) NULL,
    UNIQUE KEY uq_private_sessions_session_id (session_id),
    KEY idx_private_sessions_user_expires (private_user_id, expires_at),
    KEY idx_private_sessions_last_activity (last_activity_at),
    CONSTRAINT fk_private_sessions_user
        FOREIGN KEY (private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

