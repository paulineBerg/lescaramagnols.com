-- Private real estate rental schema - charge regularizations

CREATE TABLE IF NOT EXISTS car_rental_charge_regularizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rental_property_id INT NOT NULL,
    rental_unit_id INT NULL,
    period_year SMALLINT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    provisions_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    recoverable_expenses_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    tenant_share_percent DECIMAL(5,2) NOT NULL DEFAULT 100,
    tenant_recoverable_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    balance_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
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
    UNIQUE KEY uq_rental_charge_regularizations_document_id (document_id),
    UNIQUE KEY uq_rental_charge_regularizations_idempotency (idempotency_key),
    KEY idx_rental_charge_regularizations_property_year (rental_property_id, period_year, is_active),
    KEY idx_rental_charge_regularizations_unit_year (rental_unit_id, period_year, is_active),
    CONSTRAINT fk_rental_charge_regularizations_property
        FOREIGN KEY (rental_property_id)
        REFERENCES car_rental_properties (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_rental_charge_regularizations_unit
        FOREIGN KEY (rental_unit_id)
        REFERENCES car_rental_units (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_rental_charge_regularizations_generated_by
        FOREIGN KEY (generated_by_private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
