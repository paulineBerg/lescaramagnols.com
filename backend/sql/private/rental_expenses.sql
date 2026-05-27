-- Private real estate rental schema - expenses

CREATE TABLE IF NOT EXISTS car_rental_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rental_property_id INT NOT NULL,
    rental_unit_id INT NULL,
    expense_date DATE NOT NULL,
    label VARCHAR(160) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    is_recoverable TINYINT(1) NOT NULL DEFAULT 0,
    is_deductible_candidate TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('draft', 'validated', 'cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_by_private_user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_rental_expenses_property_date (rental_property_id, expense_date, status),
    KEY idx_rental_expenses_flags (is_recoverable, is_deductible_candidate),
    CONSTRAINT fk_rental_expenses_property
        FOREIGN KEY (rental_property_id)
        REFERENCES car_rental_properties (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_expenses_unit
        FOREIGN KEY (rental_unit_id)
        REFERENCES car_rental_units (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_rental_expenses_created_by
        FOREIGN KEY (created_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
