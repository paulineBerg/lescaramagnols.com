-- Private tax declaration helper schema - income sources

CREATE TABLE IF NOT EXISTS car_tax_income_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(80) NOT NULL,
    label VARCHAR(160) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tax_income_sources_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
