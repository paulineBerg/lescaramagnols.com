-- Private portal module migration status.
-- Tracks the short coexistence window and prevents durable double-write.

CREATE TABLE IF NOT EXISTS car_private_module_migrations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    module_code VARCHAR(80) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'php_source',
    source_checksum CHAR(64) NULL,
    target_checksum CHAR(64) NULL,
    last_reconciled_at DATETIME NULL,
    updated_by VARCHAR(190) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_private_module_migrations_module (module_code),
    KEY idx_private_module_migrations_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
