-- Private real estate rental schema - properties

CREATE TABLE IF NOT EXISTS car_rental_properties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_by_private_user_id INT NOT NULL,
    rental_lessor_id INT NULL,
    name VARCHAR(160) NOT NULL,
    address VARCHAR(255) NOT NULL,
    property_type VARCHAR(64) NOT NULL,
    ownership_mode VARCHAR(64) NOT NULL,
    status ENUM('draft', 'active', 'archived') NOT NULL DEFAULT 'active',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    archived_by_private_user_id INT NULL,
    UNIQUE KEY uq_rental_properties_name_owner (name, created_by_private_user_id),
    KEY idx_rental_properties_active (is_active),
    KEY idx_rental_properties_status (status),
    KEY idx_rental_properties_lessor (rental_lessor_id),
    KEY idx_rental_properties_created (created_by_private_user_id),
    CONSTRAINT fk_rental_properties_created_by
        FOREIGN KEY (created_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_properties_archived_by
        FOREIGN KEY (archived_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
