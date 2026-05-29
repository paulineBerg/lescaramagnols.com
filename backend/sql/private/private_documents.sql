-- Private portal document metadata.
-- File blobs are stored below storage root (outside webroot), with controlled access.

CREATE TABLE IF NOT EXISTS car_private_document_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    private_user_id INT NOT NULL,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(96) NOT NULL,
    color CHAR(7) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_private_document_categories_user_slug (private_user_id, slug),
    KEY idx_private_document_categories_user (private_user_id, is_active, name),
    CONSTRAINT fk_private_document_categories_user
        FOREIGN KEY (private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_private_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    private_user_id INT NOT NULL,
    category_id INT NULL,
    document_id VARCHAR(64) NOT NULL,
    storage_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    extension VARCHAR(32) NOT NULL,
    mime_type VARCHAR(128) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    scan_status VARCHAR(32) NOT NULL DEFAULT 'clean',
    scan_exit_code INT NULL,
    scan_duration_ms INT UNSIGNED NULL,
    scan_error VARCHAR(255) NOT NULL DEFAULT '',
    scanned_at DATETIME NULL,
    uploaded_by_private_user_id INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    deleted_by_private_user_id INT NULL,
    UNIQUE KEY uq_private_documents_document_id (document_id),
    UNIQUE KEY uq_private_documents_storage_path (storage_path),
    KEY idx_private_documents_user_active (private_user_id, is_active),
    KEY idx_private_documents_category (category_id, is_active),
    KEY idx_private_documents_scan_status (scan_status, is_active),
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
    ,
    CONSTRAINT fk_private_documents_category
        FOREIGN KEY (category_id)
        REFERENCES car_private_document_categories (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
