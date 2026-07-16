-- Private real estate rental schema - agency statements

CREATE TABLE IF NOT EXISTS car_rental_agency_statements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    imported_document_id INT NOT NULL,
    rental_property_id INT NULL,
    agency_name VARCHAR(120) NULL,
    parser_profile VARCHAR(120) NOT NULL,
    statement_period_start DATE NULL,
    statement_period_end DATE NULL,
    statement_date DATE NULL,
    statement_number VARCHAR(120) NULL,
    owner_account_reference VARCHAR(120) NULL,
    status ENUM('draft', 'review', 'validated', 'cancelled') NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rental_agency_statements_document (imported_document_id),
    KEY idx_rental_agency_statements_period (statement_period_start, statement_period_end, status),
    CONSTRAINT fk_rental_agency_statements_document
        FOREIGN KEY (imported_document_id)
        REFERENCES car_rental_agency_imported_documents (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_agency_statements_property
        FOREIGN KEY (rental_property_id)
        REFERENCES car_rental_properties (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
