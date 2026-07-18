-- Document hub - logical documents shared by every PrivateApps module.
-- A document references one immutable physical object and may be linked to many entities.

CREATE TABLE IF NOT EXISTS car_private_document_library (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_uid VARCHAR(64) NOT NULL,
    object_id INT NOT NULL,
    category_code VARCHAR(96) NOT NULL DEFAULT 'inbox',
    original_filename VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    description VARCHAR(1000) NOT NULL DEFAULT '',
    document_date DATE NULL,
    fiscal_year SMALLINT UNSIGNED NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    retention_until DATE NULL,
    legal_hold TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    trashed_at DATETIME NULL,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_private_document_library_uid (document_uid),
    KEY idx_private_document_library_object (object_id),
    KEY idx_private_document_library_category (category_code, status),
    KEY idx_private_document_library_status (status, created_at),
    KEY idx_private_document_library_fiscal_year (fiscal_year, status),
    KEY idx_private_document_library_date (document_date),
    KEY idx_private_document_library_created_by (created_by, status),
    CONSTRAINT fk_private_document_library_object
        FOREIGN KEY (object_id)
        REFERENCES car_private_document_objects (id),
    CONSTRAINT fk_private_document_library_created_by
        FOREIGN KEY (created_by)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
