-- Private real estate rental schema - lessors

CREATE TABLE IF NOT EXISTS car_rental_lessors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_by_private_user_id INT NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    first_name VARCHAR(120) NULL,
    address VARCHAR(500) NULL,
    phone VARCHAR(80) NULL,
    email VARCHAR(254) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    KEY idx_rental_lessors_user_active (created_by_private_user_id, is_active),
    KEY idx_rental_lessors_name (last_name, first_name),
    CONSTRAINT fk_rental_lessors_created_by
        FOREIGN KEY (created_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
