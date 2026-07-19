-- Private tax declaration helper schema - export logs

CREATE TABLE IF NOT EXISTS car_tax_export_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    private_user_id INT NOT NULL,
    year SMALLINT NOT NULL,
    format ENUM('csv', 'pdf') NOT NULL,
    summary_payload JSON NULL,
    exported_by_private_user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tax_export_logs_user_year (private_user_id, year, format)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
