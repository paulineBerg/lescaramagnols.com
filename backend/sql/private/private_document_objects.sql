-- Document hub - physical objects (content-addressed storage, SHA-256 deduplicated).
-- File bytes live under backend/private/storage/document-hub/objects (outside webroot).

CREATE TABLE IF NOT EXISTS car_private_document_objects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sha256 CHAR(64) NOT NULL,
    mime_type VARCHAR(128) NOT NULL,
    extension VARCHAR(16) NOT NULL,
    storage_key VARCHAR(255) NOT NULL,
    original_size BIGINT UNSIGNED NOT NULL,
    stored_size BIGINT UNSIGNED NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'ready',
    scan_status VARCHAR(32) NOT NULL DEFAULT 'clean',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    integrity_checked_at DATETIME NULL,
    UNIQUE KEY uq_private_document_objects_sha256 (sha256),
    UNIQUE KEY uq_private_document_objects_storage_key (storage_key),
    KEY idx_private_document_objects_status (status, created_at),
    KEY idx_private_document_objects_integrity (integrity_checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
