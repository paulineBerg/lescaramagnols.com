-- Private tax declaration helper schema - years

CREATE TABLE IF NOT EXISTS car_tax_years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    private_user_id INT NOT NULL,
    year SMALLINT NOT NULL,
    status ENUM('draft', 'locked') NOT NULL DEFAULT 'draft',
    locked_at DATETIME NULL,
    locked_by_private_user_id INT NULL,
    unlocked_at DATETIME NULL,
    unlocked_by_private_user_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tax_years_user_year (private_user_id, year),
    KEY idx_tax_years_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
