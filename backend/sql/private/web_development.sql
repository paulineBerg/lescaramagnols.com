-- Web development private preview schema.
-- Tables for static projects, releases and short-lived preview access tickets/sessions.

CREATE TABLE IF NOT EXISTS car_web_development_projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_key VARCHAR(80) NOT NULL,
    display_name VARCHAR(160) NOT NULL DEFAULT '',
    description TEXT NULL,
    current_public_path VARCHAR(255) NOT NULL DEFAULT '',
    current_release_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_private_user_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_web_development_projects_project_key (project_key),
    KEY idx_web_development_projects_active (is_active),
    KEY idx_web_development_projects_owner (created_by_private_user_id),
    KEY idx_web_development_projects_current_release (current_release_id),
    CONSTRAINT fk_web_development_projects_owner
        FOREIGN KEY (created_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_web_development_releases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    version VARCHAR(64) NOT NULL DEFAULT '1',
    public_path VARCHAR(255) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'draft',
    created_by_private_user_id INT NULL,
    source_commit VARCHAR(80) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at DATETIME NULL,
    UNIQUE KEY uq_web_development_releases_project_version (project_id, version),
    KEY idx_web_development_releases_project (project_id, status),
    KEY idx_web_development_releases_published_at (published_at),
    CONSTRAINT fk_web_development_releases_project
        FOREIGN KEY (project_id)
        REFERENCES car_web_development_projects (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_web_development_releases_owner
        FOREIGN KEY (created_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_web_development_preview_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    private_user_id INT NOT NULL,
    ticket_hash CHAR(64) NOT NULL,
    consumed_at DATETIME NULL,
    revoked_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    consumed_ip_hash CHAR(64) NULL,
    consumed_user_agent_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_web_development_preview_tickets_ticket_hash (ticket_hash),
    KEY idx_web_development_preview_tickets_project_user (project_id, private_user_id),
    KEY idx_web_development_preview_tickets_expiry (expires_at, revoked_at, consumed_at),
    CONSTRAINT fk_web_development_preview_tickets_project
        FOREIGN KEY (project_id)
        REFERENCES car_web_development_projects (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_web_development_preview_tickets_user
        FOREIGN KEY (private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_web_development_preview_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    private_user_id INT NOT NULL,
    session_hash CHAR(64) NOT NULL,
    client_ip_hash CHAR(64) NULL,
    user_agent_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    KEY idx_web_development_preview_sessions_project_user (project_id, private_user_id),
    KEY idx_web_development_preview_sessions_expiry (expires_at, revoked_at),
    KEY idx_web_development_preview_sessions_last_seen (last_seen_at),
    UNIQUE KEY uq_web_development_preview_sessions_session_hash (session_hash),
    CONSTRAINT fk_web_development_preview_sessions_project
        FOREIGN KEY (project_id)
        REFERENCES car_web_development_projects (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_web_development_preview_sessions_user
        FOREIGN KEY (private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
