-- Private real estate rental schema - documents

CREATE TABLE IF NOT EXISTS car_rental_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rental_property_id INT NOT NULL,
    rental_unit_id INT NULL,
    rental_lease_id INT NULL,
    document_id VARCHAR(64) NOT NULL,
    storage_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    extension VARCHAR(16) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    uploaded_by_private_user_id INT NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rental_documents_document_id (document_id),
    KEY idx_rental_documents_property (rental_property_id, is_active),
    CONSTRAINT fk_rental_documents_property
        FOREIGN KEY (rental_property_id)
        REFERENCES car_rental_properties (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_documents_unit
        FOREIGN KEY (rental_unit_id)
        REFERENCES car_rental_units (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_rental_documents_lease
        FOREIGN KEY (rental_lease_id)
        REFERENCES car_rental_leases (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_rental_documents_uploaded_by
        FOREIGN KEY (uploaded_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
