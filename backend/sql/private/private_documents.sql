-- Private portal document metadata.
-- File blobs are stored below storage root (outside webroot), with controlled access.

CREATE TABLE IF NOT EXISTS car_private_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    private_user_id INT NOT NULL,
    document_id VARCHAR(64) NOT NULL,
    storage_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    extension VARCHAR(32) NOT NULL,
    mime_type VARCHAR(128) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    uploaded_by_private_user_id INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    deleted_by_private_user_id INT NULL,
    UNIQUE KEY uq_private_documents_document_id (document_id),
    UNIQUE KEY uq_private_documents_storage_path (storage_path),
    KEY idx_private_documents_user_active (private_user_id, is_active),
    KEY idx_private_documents_active (is_active),
    KEY idx_private_documents_uploaded (uploaded_at),
    CONSTRAINT fk_private_documents_user
        FOREIGN KEY (private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_private_documents_uploader
        FOREIGN KEY (uploaded_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_private_documents_deleter
        FOREIGN KEY (deleted_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
