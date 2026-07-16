-- Private tax declaration helper schema - source activations

CREATE TABLE IF NOT EXISTS car_tax_source_activations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    private_user_id INT NOT NULL,
    year SMALLINT NOT NULL,
    source_code VARCHAR(80) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    enabled_at DATETIME NULL,
    enabled_by_private_user_id INT NULL,
    disabled_at DATETIME NULL,
    disabled_by_private_user_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tax_source_activations_user_year_source (private_user_id, year, source_code),
    KEY idx_tax_source_activations_source (source_code, is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
