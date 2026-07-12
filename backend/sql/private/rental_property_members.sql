-- Private real estate rental schema - property members

CREATE TABLE IF NOT EXISTS car_rental_property_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rental_property_id INT NOT NULL,
    private_user_id INT NOT NULL,
    role ENUM('owner', 'co_owner', 'occupant', 'manager') NOT NULL,
    status ENUM('active', 'inactive', 'revoked', 'pending') NOT NULL DEFAULT 'active',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    added_by_private_user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    removed_at DATETIME NULL,
    removed_by_private_user_id INT NULL,
    UNIQUE KEY uq_rental_property_members_property_user (rental_property_id, private_user_id),
    KEY idx_rental_property_members_property (rental_property_id, is_active),
    KEY idx_rental_property_members_user (private_user_id, is_active),
    CONSTRAINT fk_rental_property_members_property
        FOREIGN KEY (rental_property_id)
        REFERENCES car_rental_properties (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_property_members_user
        FOREIGN KEY (private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_property_members_added_by_user
        FOREIGN KEY (added_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_property_members_removed_by_user
        FOREIGN KEY (removed_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
