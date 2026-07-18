-- Document hub - global two-level taxonomy shared by all PrivateApps modules.
-- System categories (is_system=1) are stable; user categories are global, never per-user.

CREATE TABLE IF NOT EXISTS car_private_document_taxonomy (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(96) NOT NULL,
    parent_code VARCHAR(96) NULL,
    label VARCHAR(120) NOT NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    export_directory VARCHAR(120) NOT NULL DEFAULT '',
    retention_days INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_private_document_taxonomy_code (code),
    KEY idx_private_document_taxonomy_parent (parent_code, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
