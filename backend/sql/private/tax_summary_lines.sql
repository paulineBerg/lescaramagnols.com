-- Private tax declaration helper schema - summary lines

CREATE TABLE IF NOT EXISTS car_tax_summary_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tax_annual_summary_id INT NOT NULL,
    source_code VARCHAR(80) NOT NULL,
    source_label VARCHAR(160) NOT NULL,
    line_type ENUM('income', 'expense', 'control', 'document') NOT NULL,
    label VARCHAR(190) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    source_reference VARCHAR(190) NOT NULL,
    metadata_payload JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tax_summary_lines_summary (tax_annual_summary_id),
    KEY idx_tax_summary_lines_source (source_code, line_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
