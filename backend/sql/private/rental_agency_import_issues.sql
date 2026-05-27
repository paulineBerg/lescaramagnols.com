-- Private real estate rental schema - agency import issues

CREATE TABLE IF NOT EXISTS car_rental_agency_import_issues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    imported_document_id INT NULL,
    issue_type VARCHAR(80) NOT NULL,
    severity ENUM('info', 'warning', 'error') NOT NULL DEFAULT 'warning',
    message VARCHAR(255) NOT NULL,
    source_page INT NULL,
    resolved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rental_agency_import_issues_document (imported_document_id, severity, resolved_at),
    CONSTRAINT fk_rental_agency_import_issues_document
        FOREIGN KEY (imported_document_id)
        REFERENCES car_rental_agency_imported_documents (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
