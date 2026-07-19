-- Private real estate rental schema - export logs

CREATE TABLE IF NOT EXISTS car_rental_export_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    private_user_id INT NOT NULL,
    year SMALLINT NOT NULL,
    format ENUM('csv', 'pdf') NOT NULL,
    summary_payload JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rental_export_logs_user_year (private_user_id, year, format),
    CONSTRAINT fk_rental_export_logs_private_user
        FOREIGN KEY (private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
