-- Document hub - recreatable derivatives (thumbnails, previews).
-- Derivatives are excluded from full backups and can always be regenerated from the original object.

CREATE TABLE IF NOT EXISTS car_private_document_derivatives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    derivative_type VARCHAR(32) NOT NULL,
    storage_key VARCHAR(255) NOT NULL,
    mime_type VARCHAR(128) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    generator_version VARCHAR(32) NOT NULL DEFAULT '1',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_accessed_at DATETIME NULL,
    UNIQUE KEY uq_private_document_derivatives_type (object_id, derivative_type),
    UNIQUE KEY uq_private_document_derivatives_key (storage_key),
    CONSTRAINT fk_private_document_derivatives_object
        FOREIGN KEY (object_id)
        REFERENCES car_private_document_objects (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
