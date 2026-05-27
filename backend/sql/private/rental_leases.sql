-- Private real estate rental schema - leases

CREATE TABLE IF NOT EXISTS car_rental_leases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rental_property_id INT NOT NULL,
    rental_unit_id INT NOT NULL,
    rental_tenant_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    monthly_rent DECIMAL(10,2) NOT NULL,
    charges_provision DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('draft', 'validated', 'cancelled', 'ended') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_by_private_user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_rental_leases_property (rental_property_id, status),
    KEY idx_rental_leases_unit (rental_unit_id),
    KEY idx_rental_leases_tenant (rental_tenant_id),
    KEY idx_rental_leases_period (start_date, end_date),
    CONSTRAINT fk_rental_leases_property
        FOREIGN KEY (rental_property_id)
        REFERENCES car_rental_properties (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_leases_unit
        FOREIGN KEY (rental_unit_id)
        REFERENCES car_rental_units (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_leases_tenant
        FOREIGN KEY (rental_tenant_id)
        REFERENCES car_rental_tenants (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_leases_created_by
        FOREIGN KEY (created_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
