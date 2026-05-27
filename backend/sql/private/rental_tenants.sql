-- Private real estate rental schema - tenants

CREATE TABLE IF NOT EXISTS car_rental_tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rental_property_id INT NOT NULL,
    full_name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(64) NULL,
    status ENUM('draft', 'validated', 'cancelled') NOT NULL DEFAULT 'draft',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_by_private_user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_rental_tenants_property (rental_property_id, is_active),
    KEY idx_rental_tenants_status (status),
    CONSTRAINT fk_rental_tenants_property
        FOREIGN KEY (rental_property_id)
        REFERENCES car_rental_properties (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_tenants_created_by
        FOREIGN KEY (created_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
