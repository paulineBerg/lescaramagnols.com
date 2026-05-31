-- Private real estate rental schema - managed agencies

CREATE TABLE IF NOT EXISTS car_rental_agencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_by_private_user_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    legal_name VARCHAR(190) NULL,
    contact_title VARCHAR(120) NULL,
    postal_address VARCHAR(500) NULL,
    phone VARCHAR(80) NULL,
    email VARCHAR(254) NULL,
    advisor_name VARCHAR(160) NULL,
    advisor_title VARCHAR(120) NULL,
    advisor_phone VARCHAR(80) NULL,
    advisor_email VARCHAR(254) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rental_agencies_user_name (created_by_private_user_id, name),
    KEY idx_rental_agencies_user_updated (created_by_private_user_id, updated_at),
    CONSTRAINT fk_rental_agencies_user
        FOREIGN KEY (created_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
