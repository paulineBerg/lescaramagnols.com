-- Private real estate rental schema - agency line mappings

CREATE TABLE IF NOT EXISTS car_rental_agency_line_mappings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    raw_label_pattern VARCHAR(120) NOT NULL,
    source_document_type VARCHAR(80) NOT NULL DEFAULT 'unknown',
    mapped_category VARCHAR(80) NOT NULL,
    direction ENUM('income', 'expense', 'transfer', 'liability', 'balance', 'neutral') NOT NULL DEFAULT 'neutral',
    is_recoverable TINYINT(1) NOT NULL DEFAULT 0,
    is_tax_deductible_candidate TINYINT(1) NOT NULL DEFAULT 0,
    requires_review TINYINT(1) NOT NULL DEFAULT 1,
    validation_hint VARCHAR(255) NULL,
    confidence DECIMAL(4,2) NOT NULL DEFAULT 0.50,
    priority SMALLINT NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rental_agency_line_mapping (raw_label_pattern, source_document_type),
    KEY idx_rental_agency_line_mappings_category (mapped_category, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
