-- Private real estate rental schema - units

CREATE TABLE IF NOT EXISTS car_rental_units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rental_property_id INT NOT NULL,
    label VARCHAR(160) NOT NULL,
    unit_type VARCHAR(64) NOT NULL DEFAULT 'other',
    address VARCHAR(255) NULL,
    building VARCHAR(120) NULL,
    floor VARCHAR(64) NULL,
    door VARCHAR(64) NULL,
    tax_identifier VARCHAR(80) NULL,
    room_count SMALLINT UNSIGNED NULL,
    designation VARCHAR(160) NULL,
    other_details TEXT NULL,
    equipment_elements TEXT NULL,
    heating_production_mode VARCHAR(160) NULL,
    hot_water_production_mode VARCHAR(160) NULL,
    sanitation VARCHAR(160) NULL,
    surface DECIMAL(8,2) NOT NULL,
    furnished TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('available', 'unavailable', 'archived') NOT NULL DEFAULT 'available',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_by_private_user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    archived_by_private_user_id INT NULL,
    UNIQUE KEY uq_rental_units_property_label (rental_property_id, label),
    KEY idx_rental_units_property_active (rental_property_id, is_active),
    KEY idx_rental_units_type (unit_type),
    KEY idx_rental_units_status (status),
    KEY idx_rental_units_active (is_active),
    KEY idx_rental_units_created (created_by_private_user_id),
    CONSTRAINT fk_rental_units_property
        FOREIGN KEY (rental_property_id)
        REFERENCES car_rental_properties (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_units_created_by
        FOREIGN KEY (created_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_units_archived_by
        FOREIGN KEY (archived_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
