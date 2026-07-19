-- Private real estate rental schema - rents

CREATE TABLE IF NOT EXISTS car_rental_rents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rental_lease_id INT NOT NULL,
    rental_property_id INT NOT NULL,
    rental_unit_id INT NOT NULL,
    period_year SMALLINT NOT NULL,
    period_month TINYINT NOT NULL,
    due_date DATE NOT NULL,
    amount_due DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('pending', 'partial', 'paid', 'late', 'cancelled') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_by_private_user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rental_rents_lease_period (rental_lease_id, period_year, period_month),
    KEY idx_rental_rents_property_year (rental_property_id, period_year, status),
    KEY idx_rental_rents_lease (rental_lease_id),
    KEY idx_rental_rents_unit (rental_unit_id),
    CONSTRAINT fk_rental_rents_lease
        FOREIGN KEY (rental_lease_id)
        REFERENCES car_rental_leases (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_rents_property
        FOREIGN KEY (rental_property_id)
        REFERENCES car_rental_properties (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_rents_unit
        FOREIGN KEY (rental_unit_id)
        REFERENCES car_rental_units (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_rents_created_by
        FOREIGN KEY (created_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
