-- FamilyDiscussion attachments stored encrypted outside webroot.

CREATE TABLE IF NOT EXISTS car_discussion_message_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    attachment_id VARCHAR(64) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    storage_path VARCHAR(255) NOT NULL,
    preview_storage_path VARCHAR(255) NULL,
    mime_type VARCHAR(128) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    width INT NULL,
    height INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    purge_status VARCHAR(16) NOT NULL DEFAULT 'active',
    availability_status VARCHAR(16) NOT NULL DEFAULT 'available',
    scanned_at DATETIME NULL,
    scan_error VARCHAR(120) NULL,
    thumbnail_storage_path VARCHAR(255) NULL,
    UNIQUE KEY uq_discussion_attachments_attachment_id (attachment_id),
    UNIQUE KEY uq_discussion_attachments_storage_path (storage_path),
    KEY idx_discussion_attachments_message (message_id),
    KEY idx_discussion_attachments_status_message (message_id, purge_status, id),
    KEY idx_discussion_attachments_availability (availability_status, expires_at, id),
    KEY idx_discussion_attachments_expiry (expires_at, purge_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
