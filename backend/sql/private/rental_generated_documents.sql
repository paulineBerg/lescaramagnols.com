-- Private real estate rental schema - generated documents

CREATE TABLE IF NOT EXISTS car_rental_generated_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rental_rent_id INT NOT NULL,
    rental_lease_id INT NOT NULL,
    rental_payment_id INT NULL,
    rental_property_id INT NOT NULL,
    rental_unit_id INT NOT NULL,
    document_type ENUM('receipt', 'partial_receipt', 'payment_notice') NOT NULL,
    document_id VARCHAR(64) NOT NULL,
    storage_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes INT NOT NULL,
    sha256_hash CHAR(64) NOT NULL,
    idempotency_key CHAR(64) NOT NULL,
    snapshot_payload JSON NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    generated_by_private_user_id INT NOT NULL,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rental_generated_documents_document_id (document_id),
    UNIQUE KEY uq_rental_generated_documents_idempotency (idempotency_key),
    KEY idx_rental_generated_documents_property (rental_property_id, document_type, is_active),
    KEY idx_rental_generated_documents_rent (rental_rent_id, document_type, is_active),
    CONSTRAINT fk_rental_generated_documents_rent
        FOREIGN KEY (rental_rent_id)
        REFERENCES car_rental_rents (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_generated_documents_lease
        FOREIGN KEY (rental_lease_id)
        REFERENCES car_rental_leases (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_generated_documents_payment
        FOREIGN KEY (rental_payment_id)
        REFERENCES car_rental_payments (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_rental_generated_documents_property
        FOREIGN KEY (rental_property_id)
        REFERENCES car_rental_properties (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_generated_documents_unit
        FOREIGN KEY (rental_unit_id)
        REFERENCES car_rental_units (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_generated_documents_generated_by
        FOREIGN KEY (generated_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
