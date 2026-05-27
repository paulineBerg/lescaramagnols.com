-- FamilyDiscussion purge runs.

CREATE TABLE IF NOT EXISTS car_discussion_retention_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    private_user_id INT NULL,
    scope VARCHAR(32) NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME NULL,
    purged_messages_count INT NOT NULL DEFAULT 0,
    purged_attachments_count INT NOT NULL DEFAULT 0,
    status VARCHAR(32) NOT NULL DEFAULT 'running',
    error_message VARCHAR(255) NULL,
    KEY idx_discussion_retention_started (started_at),
    KEY idx_discussion_retention_user (private_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
