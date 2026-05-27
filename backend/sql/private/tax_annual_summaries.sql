-- Private tax declaration helper schema - annual summaries

CREATE TABLE IF NOT EXISTS car_tax_annual_summaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    private_user_id INT NOT NULL,
    year SMALLINT NOT NULL,
    status ENUM('draft', 'generated', 'locked') NOT NULL DEFAULT 'generated',
    totals_payload JSON NULL,
    generated_by_private_user_id INT NOT NULL,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tax_annual_summaries_user_year (private_user_id, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
