-- Document hub - import traceability. One row per attempted file import.
-- Statuses: quarantined, validating, processing, ready, rejected, failed.

CREATE TABLE IF NOT EXISTS car_private_document_import_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NULL,
    import_source VARCHAR(64) NOT NULL DEFAULT '',
    profile_code VARCHAR(96) NOT NULL DEFAULT '',
    context_type VARCHAR(64) NOT NULL DEFAULT '',
    context_id VARCHAR(64) NOT NULL DEFAULT '',
    original_filename VARCHAR(255) NOT NULL DEFAULT '',
    classification_source VARCHAR(32) NOT NULL DEFAULT '',
    classification_confidence TINYINT UNSIGNED NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'quarantined',
    error_code VARCHAR(64) NULL,
    error_message_sanitized VARCHAR(255) NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    KEY idx_private_document_import_jobs_status (status, created_at),
    KEY idx_private_document_import_jobs_document (document_id),
    KEY idx_private_document_import_jobs_profile (profile_code, created_at),
    CONSTRAINT fk_private_document_import_jobs_document
        FOREIGN KEY (document_id)
        REFERENCES car_private_document_library (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_private_document_import_jobs_created_by
        FOREIGN KEY (created_by)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
