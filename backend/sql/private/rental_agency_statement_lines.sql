-- Private real estate rental schema - agency statement lines

CREATE TABLE IF NOT EXISTS car_rental_agency_statement_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    statement_id INT NOT NULL,
    imported_document_id INT NOT NULL,
    rental_property_id INT NULL,
    rental_unit_id INT NULL,
    source_page INT NOT NULL DEFAULT 1,
    source_line_hash CHAR(64) NOT NULL,
    line_date DATE NULL,
    period_start DATE NULL,
    period_end DATE NULL,
    amount DECIMAL(10,2) NULL,
    debit_amount DECIMAL(10,2) NULL,
    credit_amount DECIMAL(10,2) NULL,
    called_amount DECIMAL(10,2) NULL,
    paid_amount DECIMAL(10,2) NULL,
    owner_transfer_amount DECIMAL(10,2) NULL,
    raw_label VARCHAR(255) NOT NULL,
    mapped_category VARCHAR(80) NOT NULL,
    mapping_status ENUM('suggested', 'review', 'validated', 'ignored') NOT NULL DEFAULT 'review',
    property_label VARCHAR(160) NULL,
    unit_label VARCHAR(160) NULL,
    tenant_name VARCHAR(160) NULL,
    confidence_status VARCHAR(40) NOT NULL DEFAULT 'review',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rental_agency_statement_lines_statement (statement_id, source_page),
    KEY idx_rental_agency_statement_lines_document (imported_document_id),
    KEY idx_rental_agency_statement_lines_property (rental_property_id, mapping_status),
    KEY idx_rental_agency_statement_lines_unit (rental_unit_id, mapping_status),
    KEY idx_rental_agency_statement_lines_category (mapped_category, mapping_status),
    UNIQUE KEY uq_rental_agency_statement_line_hash (statement_id, source_line_hash),
    CONSTRAINT fk_rental_agency_statement_lines_statement
        FOREIGN KEY (statement_id)
        REFERENCES car_rental_agency_statements (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_agency_statement_lines_document
        FOREIGN KEY (imported_document_id)
        REFERENCES car_rental_agency_imported_documents (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_agency_statement_lines_property
        FOREIGN KEY (rental_property_id)
        REFERENCES car_rental_properties (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_rental_agency_statement_lines_unit
        FOREIGN KEY (rental_unit_id)
        REFERENCES car_rental_units (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
