-- Private real estate rental schema - agency import batches

CREATE TABLE IF NOT EXISTS car_rental_agency_import_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_by_private_user_id INT NOT NULL,
    agency_name VARCHAR(120) NULL,
    status ENUM('draft', 'review', 'validated', 'cancelled') NOT NULL DEFAULT 'draft',
    source_directory VARCHAR(255) NULL,
    file_count INT NOT NULL DEFAULT 0,
    ignored_file_count INT NOT NULL DEFAULT 0,
    duplicate_file_count INT NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_rental_agency_import_batches_user (created_by_private_user_id, created_at),
    KEY idx_rental_agency_import_batches_agency (created_by_private_user_id, agency_name),
    KEY idx_rental_agency_import_batches_status (status, created_at),
    CONSTRAINT fk_rental_agency_import_batches_user
        FOREIGN KEY (created_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
