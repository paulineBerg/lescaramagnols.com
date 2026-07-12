-- Private real estate rental schema - agency unit mappings

CREATE TABLE IF NOT EXISTS car_rental_agency_unit_mappings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_by_private_user_id INT NOT NULL,
    agency_name VARCHAR(120) NOT NULL,
    match_text VARCHAR(160) NOT NULL,
    rental_property_id INT NOT NULL,
    rental_unit_id INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rental_agency_unit_mapping (created_by_private_user_id, agency_name, match_text),
    KEY idx_rental_agency_unit_mappings_unit (rental_property_id, rental_unit_id, is_active),
    KEY idx_rental_agency_unit_mappings_agency (created_by_private_user_id, agency_name, is_active),
    CONSTRAINT fk_rental_agency_unit_mappings_user
        FOREIGN KEY (created_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_agency_unit_mappings_property
        FOREIGN KEY (rental_property_id)
        REFERENCES car_rental_properties (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_agency_unit_mappings_unit
        FOREIGN KEY (rental_unit_id)
        REFERENCES car_rental_units (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
