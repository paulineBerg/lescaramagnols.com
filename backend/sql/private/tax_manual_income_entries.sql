-- Private tax declaration helper schema - manual income entries

CREATE TABLE IF NOT EXISTS car_tax_manual_income_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    private_user_id INT NOT NULL,
    year SMALLINT NOT NULL,
    source_code VARCHAR(80) NOT NULL DEFAULT 'manual',
    label VARCHAR(160) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    category VARCHAR(64) NOT NULL,
    status ENUM('draft', 'validated', 'cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_by_private_user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_tax_manual_entries_user_year (private_user_id, year, status),
    KEY idx_tax_manual_entries_source (source_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
