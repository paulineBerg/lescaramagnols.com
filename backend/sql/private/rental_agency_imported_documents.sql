-- Private real estate rental schema - agency imported documents

CREATE TABLE IF NOT EXISTS car_rental_agency_imported_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    private_document_id VARCHAR(64) NULL,
    storage_path VARCHAR(255) NULL,
    filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NULL,
    file_size INT NULL,
    sha256 CHAR(64) NOT NULL,
    detected_document_type VARCHAR(80) NOT NULL DEFAULT 'unknown',
    detected_agency VARCHAR(120) NULL,
    parser_profile VARCHAR(120) NULL,
    classification_confidence DECIMAL(4,2) NOT NULL DEFAULT 0.00,
    text_extraction_status VARCHAR(64) NOT NULL DEFAULT 'unsupported',
    contains_sensitive_data TINYINT(1) NOT NULL DEFAULT 0,
    review_status ENUM('pending', 'review', 'validated', 'ignored', 'duplicate') NOT NULL DEFAULT 'pending',
    masked_text_preview MEDIUMTEXT NULL,
    parser_payload JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rental_agency_imported_documents_sha256 (sha256),
    KEY idx_rental_agency_imported_documents_batch (batch_id, review_status),
    KEY idx_rental_agency_imported_documents_type (detected_document_type, review_status),
    CONSTRAINT fk_rental_agency_imported_documents_batch
        FOREIGN KEY (batch_id)
        REFERENCES car_rental_agency_import_batches (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
